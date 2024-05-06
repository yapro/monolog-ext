<?php

declare(strict_types=1);

namespace YaPro\MonologExt\Handler;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use Symfony\Component\HttpKernel\Exception\HttpException;
use JsonException;
use YaPro\MonologExt\VarHelper;
use function is_numeric;

// todo покрыть методы тестами
class JsonToStdErrHandler extends AbstractProcessingHandler
{
    // PHP дробит строки данной длинны, а инфраструктура обрабатывающая запись теряет их: https://github.com/docker-library/php/pull/725#issuecomment-443540114
    public const MAX_RECORD_LENGTH = 8192;
    const THE_LOG_ENTRY_IS_TOO_LONG = 'the log entry is too long';
    const THE_LOG_ENTRY_IS_TOO_LONG_SO_IT_IS_REDUCED = self::THE_LOG_ENTRY_IS_TOO_LONG . ', so it is reduced: ';
    /**
     * @var false|resource
     */
    private $stderr;
    private VarHelper $varHelper;
    
    // используется для игнорирования повтороного сообщения (такое бывает, когда приложение завершается с ошибкой, при
    // этом set_exception_handler пишет ошибку, а потом register_shutdown_function пишет ее же (еще раз)
    private string $lastMessageHash = '';

    public function __construct() {
        parent::__construct();
        $this->stderr = fopen('php://stderr', 'w');
        $this->varHelper = new VarHelper();
    }

    // Не реализуем метод isHandling т.к. он уже реализован \Monolog\Handler\AbstractHandler::isHandling(), а главное
    // то, что его обязанность лишь в проверке уровня ошибки, по-умолчанию DEBUG, см. детали:
    // \Monolog\Logger::addRecord() : if (!$handler->isHandling(['level' => $level])) {
    public function isSupporting(array $record): bool
    {
        // игнорируем http ошибки клиента (4xx):
        if (isset($record['context']['exception']) &&
            $record['context']['exception'] instanceof HttpException &&
            $record['context']['exception']->getStatusCode() < 500
        ) {
            return false;
        }
        // обрабатываем странные сообщения
        if (!isset($record['level']) || !isset($record['channel'])) {
            return true;
        }
        if (isset($_ENV['MONOLOG_EXT_JSON_TO_STD_ERR_HANDLER_LEVEL'])
            && is_numeric($_ENV['MONOLOG_EXT_JSON_TO_STD_ERR_HANDLER_LEVEL'])
            && $record['level'] < $_ENV['MONOLOG_EXT_JSON_TO_STD_ERR_HANDLER_LEVEL']
        ) {
            return false;
        }
        // обрабатываем лог-записи всех уровней в коде приложении (src dir)
        if ($record['channel'] === 'app') {
            return true;
        }
        // обрабатываем все ошибки (в том числе в библиотеках)
        if ($record['level'] > Logger::INFO) {
            return true;
        }

        return false;
    }

    public function handle(array $record): bool
    {
        if (!$this->isSupporting($record)) {
            return false;
        }
        $this->write($this->processRecord($record));

        return false;
    }

    /**
     * @throws JsonException
     */
    protected function write(array $record): void
    {
        $message = $this->getMessage($record);
        if (sha1($message) === $this->lastMessageHash) {
            return;
        }
        $this->lastMessageHash = sha1($message);
        // todo можно подумать над тем, чтобы сплитить запись на несколько при превышении длинны
        fwrite($this->stderr, $this->getMessage($record) . PHP_EOL);
    }

    // todo вынести в либу и актуализировать в других сервисах:
    public function getMessage(array $record): string
    {
        if (isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'dev') {
            return json_encode($this->varHelper->dump($record), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
        }
        $result = $this->getJson($record);
        if ($this->isMessageShort($result)) {
            return $result;
        }
        // здесь указаны ключи массива, в которых будет выполнен поиск ключей с большим значением
        // при нахождении ключей с большим значением, они по очереди удаляются, пока лог-запись не станет приемлемого размера
        if (isset($record['context'])) {
            $result = $this->getReducedRecord($record, 'context');
            if ($this->isMessageShort($result)) {
                return $result;
            }
        }
        if (isset($record['debugInfo'])) {
            $result = $this->getReducedRecord($record, 'debugInfo');
            if ($this->isMessageShort($result)) {
                return $result;
            }
        }
        // попробуем сохранить хотя бы часть сообщения:
        $record['message'] = mb_substr($record['message'], 0, self::MAX_RECORD_LENGTH - mb_strlen('{"message":""}'));
        $record = ['message' => $record['message']];

        return $this->getJson($record);
    }

    public function getReducedRecord(array &$record, $keyName): string
    {
        $mysteriousCharacters = 2;
        $explanation = self::THE_LOG_ENTRY_IS_TOO_LONG_SO_IT_IS_REDUCED;
        $explanationLength = mb_strlen($explanation);
        $preserved = array_reverse($record[$keyName], true);
        foreach ($preserved as $key => $value) {
            $result = $this->getJson($record);
            if ($this->isMessageShort($result)) {
                return $result;
            }
            // находим, на сколько символов нужно уменьшить $record (лишнее количество символов):
            $excessCharactersInTheRecord = mb_strlen($result) - self::MAX_RECORD_LENGTH;
            if ($excessCharactersInTheRecord > 0) {
                // находим насколько мы должны подрезать value:
                $valueAsString = $this->varHelper->dump($value);
                // почему-то функция dump добавляет к строкам двойные кавычки, пока не разобрался, поэтому:
                if (is_string($value) && mb_substr($valueAsString, 0, 1) === '"' && mb_substr($value, 0, 1) !== '"') {
                    $valueAsString = mb_substr($valueAsString, 1);
                }
                $newValueMaxLength = mb_strlen($valueAsString) - $excessCharactersInTheRecord - $explanationLength - $mysteriousCharacters;
                if ($newValueMaxLength > 0) {// даем пояснение + подрезаем value:
                    $record[$keyName][$key] = $explanation . mb_substr($valueAsString, 0, $newValueMaxLength);
                } else {// символов на подрезку не остается, увы удаляем value:
                    $record[$keyName][$key] = self::THE_LOG_ENTRY_IS_TOO_LONG;
                }
            }
        }

        return $this->getJson($record);
    }

    public function isMessageShort(string $record): bool
    {
        return mb_strlen($record) < self::MAX_RECORD_LENGTH;
    }

    public function getJson(array $record): string
    {
        return json_encode($this->varHelper->dump($record), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
