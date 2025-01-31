<?php

declare(strict_types=1);

namespace YaPro\MonologExt\Handler;

use Exception;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use Symfony\Component\HttpKernel\Exception\HttpException;
use JsonException;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\AbstractDumper;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use Symfony\Component\VarDumper\Dumper\ContextualizedDumper;
use Throwable;
use YaPro\MonologExt\Processor\AddStackTraceOfCallPlaceProcessor;
use YaPro\MonologExt\VarHelper;
use function is_numeric;

// todo покрыть методы тестами
class JsonToStdErrHandler extends AbstractProcessingHandler
{
    const THE_VALUE_IS_TOO_BIG = 'too big';

    private VarHelper $varHelper;
    private VarCloner $varCloner;
    private CliDumper $varDumper;
    
    /**
     * @var false|resource
     */
    private $stderr;
    
    // используется для игнорирования повтороного сообщения (такое бывает, когда приложение завершается с ошибкой, при
    // этом set_exception_handler пишет ошибку, а потом register_shutdown_function пишет ее же (еще раз)
    private string $lastRecordHash = '';
    
    private bool $devModePhpFpm = false;
    private bool $devModePhpCli = false;
    private int $ignoreRecordLevelBelow = 0;
    private int $stopRequestWhenRecordLevelAbove = 0;

    public const MAX_DUMP_LEVEL_DEFAULT = 5;
    private int $maxDumpLevel = self::MAX_DUMP_LEVEL_DEFAULT;
    /**
     * Проблема масштабная:
     *  1. PHP дробит строк длинной больше чем значение log_limit https://www.php.net/manual/en/install.fpm.configuration.php#log-limit
     *  2. Docker дробит строки длинной больше 16 Кб https://github.com/moby/moby/issues/34855
     *  3. Инфраструктура обрабатывающая разбитые записи теряет их
     * Решение: перед записью в stderr проверять длинну сообщения на значение maxRecordLength
     * История - как получилось данное значение:
     *  1. некий bukka указал для докер-файла размер log_limit = 1024 https://github.com/docker-library/php/pull/725#issuecomment-443540114
     *  2. затем jnoordsij установил log_limit = 8192 https://github.com/docker-library/php/blame/396ead877c1751e756f484e01ac72c93925dfaa8/8.3/alpine3.19/fpm/Dockerfile#L231
     * Важно: сокращение записей до данной длины может не помогать, когда используются UTF8-символы (где на один символ
     * прходится несколько байт), в этом случае подрезанное сообщение все равно будет разбито на Х строк (docker-ом или
     * PHP, который в документации не говорит какой кодировки characters он будет подсчитывать)
     */
    public const MAX_RECORD_LENGTH_DEFAULT = 16000;
    private int $maxRecordLength = self::MAX_RECORD_LENGTH_DEFAULT;

    public function __construct(int $maxRecordLength = 0) {
        parent::__construct();
        $this->stderr = fopen('php://stderr', 'w');
        $this->varHelper = new VarHelper();
        $this->devModePhpFpm = !empty($_ENV['EH_DEV_MODE_PHP_FPM']);
        $this->devModePhpCli = !empty($_ENV['EH_DEV_MODE_PHP_CLI']);
        if (isset($_ENV['EH_MAX_DUMP_LEVEL'])) {
            $this->maxDumpLevel = (int) $_ENV['EH_MAX_DUMP_LEVEL'];
        }
        if (isset($_ENV['EH_IGNORE_RECORD_LEVEL_BELOW'])) {
            $this->ignoreRecordLevelBelow = (int) $_ENV['EH_IGNORE_RECORD_LEVEL_BELOW'];
        }
        if (isset($_ENV['EH_STOP_RECORD_LEVEL_ABOVE'])) {
            $this->stopRequestWhenRecordLevelAbove = (int) $_ENV['EH_STOP_WHEN_RECORD_LEVEL_ABOVE'];
        }
        if ($maxRecordLength) {
            $this->maxRecordLength = $maxRecordLength;
        } else {
            $limit = (int) ini_get('log_limit'); // по-умолчанию данная настройка не задана, но PHP настроен на 1024 символа
            if ($limit) {
                $this->maxRecordLength = $limit;
            }
        }
        $this->varCloner = new VarCloner();
        $this->varDumper = new CliDumper(null, null, AbstractDumper::DUMP_LIGHT_ARRAY);
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
        if ($record['level'] < $this->ignoreRecordLevelBelow) {
            return false;
        }
        // обрабатываем все лог-записи в коде приложения (в директории src)
        if ($record['channel'] === 'app') {
            return true;
        }
        // обрабатываем все ошибки (в том числе в библиотеках) если их уровень больше, чем INFO:
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
        $record = $this->processRecord($record);
        $this->write($record);

        return false;
    }

    public function getDebugInfo(string $prefix, $objectOrArray, int $level = 0): string
    {
        // сначала печатаем примитивные типы до уровня 3, а затем все остальные в виде дампов:
        if ($level === 3) {
            return $prefix.' : ' .$this->dump($objectOrArray, 1) . PHP_EOL;
        }
        $result = '';
        is_array($objectOrArray) && asort($objectOrArray);
        foreach ($objectOrArray as $key => $value) {
            $fieldName = $prefix === '' ? $key : $prefix.'.'. $key;
            if (is_scalar($value) || is_null($value)) {
                $result .= trim($fieldName .' : ' . $value) . PHP_EOL;
            } else {
                $result .= $this->getDebugInfo($fieldName, $value, $level + 1);
            }
        }
        return $result;
    }

    /**
     * @throws JsonException
     */
    public function write(array $record): void
    {
        if ($this->devModePhpFpm || $this->devModePhpCli) {
            if (PHP_SAPI === 'fpm-fcgi') {
                if (!$this->devModePhpFpm) {
                    return;
                }
            } else {
                if (!$this->devModePhpCli) {
                    return;
                }
            }

            $result = PHP_EOL . ':::::::::::::::::::: ' . __CLASS__ . ' informs ::::::::::::::::::' . PHP_EOL . PHP_EOL;

            $result .= $record['message'] . PHP_EOL;
            unset($record['message']);

            $stackTraceOfCallPlaceProcessor = new AddStackTraceOfCallPlaceProcessor();
            $trace = (new Exception())->getTrace();
            $stackTraceBeforeMonolog = $stackTraceOfCallPlaceProcessor->getStackTraceBeforeMonolog($trace);
            $callPlace = reset($stackTraceBeforeMonolog);
            $result .= 'The log entry has been wrote by ' . ($callPlace['file'] ?? '') . ':' . ($callPlace['line'] ?? '')  . PHP_EOL;

            $result .= $this->getDebugInfo('', $record);
            // $message .= json_encode($this->varHelper->dump($record), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
            if (PHP_SAPI === 'fpm-fcgi') {
                http_response_code(500);
                echo '<pre>'.$result;
            } else {
                $this->writeToStdErr($result);
            }
            exit(121);
        };
        $result = $this->getMessage($record);
        if (sha1($result) === $this->lastRecordHash) {
            return;
        }
        $this->lastRecordHash = sha1($result);
        $this->writeToStdErr($result);
        if (!isset($record['level'])) {
            http_response_code(500);
            echo 'Sorry, an unexpected error has occurred. The log entry has no level. The error has been logged.';
            exit(122);
        }
        // По причине https://yapro.ru/article/16221 останавливаю обработку ошибок:
        if ($record['level'] > $this->stopRequestWhenRecordLevelAbove) {
            http_response_code(500);
            echo 'Sorry, an unexpected error has occurred. The error has been logged.';
            exit(123);
        }
    }

    public function writeToStdErr(string $message)
    {
        // todo можно подумать над тем, чтобы сплитить запись на несколько при превышении длинны
        fwrite($this->stderr, $message . PHP_EOL);
    }


    public function reduceRecord(array $record, $maxLevel, $currentLevel = 0)
    {
        $currentLevel++;
        foreach ($record as $key => $value) {
            if ($currentLevel === $maxLevel) {
                $value = self::THE_VALUE_IS_TOO_BIG; // The maximum dump level has been reached.
            }
            if (is_iterable($value)) {
                $record[$key] = $this->reduceRecord($value, $maxLevel, $currentLevel);
            } else {
                $record[$key] = $value;
            }
        }
        return $record;
    }

    // возвращает $record, которая на уровне $maxLevel имеет строковые значения (задампленные значения)
    public function dumpRecordDataOnTheLevel(array $record, $maxLevel, $currentLevel = 1)
    {
        foreach ($record as $key => $value) {
            if ($currentLevel === $maxLevel) {
                $value = $this->dump($value);
            }
            if (is_iterable($value)) {
                $record[$key] = $this->dumpRecordDataOnTheLevel($value, $maxLevel, $currentLevel+1);
            } else {
                $record[$key] = $value;
            }
        }
        return $record;
    }

    public function reduceRecordDataOnTheLevel(array &$record, $maxLevel, $currentLevel = 1)
    {
        if ($currentLevel === $maxLevel) {
            $reversed = array_reverse($record, true);
            foreach ($reversed as $key => $value) {
                $record[$key] = self::THE_VALUE_IS_TOO_BIG;
                $changeableRecordAsJson = $this->getJson($record);
                if ($this->isRecordShort($changeableRecordAsJson)) {
                    return $record;
                }
            }
        } else {
            foreach ($record as $key => $value) {
                if (is_iterable($value)) {
                    $record[$key] = $this->reduceRecordDataOnTheLevel($value, $maxLevel, $currentLevel++);
                } else {
                    $record[$key] = $value;
                }
            }
        }
        return $record;
    }

    // Находим $maxDumpLevel требуемый для безопасного сохранения сообщения в stderr
    public function findMaxDumpLevel(array $record): int
    {
        for ($maxDumpLevel = $this->maxDumpLevel; $maxDumpLevel > 0; $maxDumpLevel--) {
            $string = $this->dump($record, $maxDumpLevel);
            if ($this->isRecordShort($string)) {
                break;
            }
        }
        return $maxDumpLevel;
    }

    public function getMessage(array $record): string
    {
        $maxDumpLevel = $this->findMaxDumpLevel($record);
        // В данной строке мы знаем приемлемый уровень для создания строки log-записи с учетом $this->maxRecordLength, но
        // попробуем не укорачивать глобально по уровню, а попробуем укоротить уменьшая даннные на уровне $maxDumpLevel+1
        // Для этого сначала задампим данные на уровне $maxDumpLevel+1, а потом будем отбрасывать значения
        $dumpedRecord = $this->dumpRecordDataOnTheLevel($record, $maxDumpLevel+1);
        $this->reduceRecordDataOnTheLevel($dumpedRecord, $maxDumpLevel+1, 1);

        return $this->getJson($dumpedRecord);
/*
        $result = $this->getJson($record);
        if ($this->isMessageShort($result)) {
            return $result;
        }
        // здесь указаны ключи массива, в которых будет выполнен поиск ключей с большим значением
        // при нахождении ключей с большим значением, они по очереди удаляются, пока лог-запись не станет приемлемого размера
        if (isset($record['context'])) {
            $result = $this->getReducedRecord($record, 'context');
            return $result;
        }
        // если вдруг админы решили не индексировать поле context, то просто можно начать вместо него использовать поле debugInfo:
        if (isset($record['debugInfo'])) {
            $result = $this->getReducedRecord($record, 'debugInfo');
            if ($this->isMessageShort($result)) {
                return $result;
            }
        }
        // попробуем сохранить хотя бы часть сообщения:
        $record['message'] = mb_substr($record['message'], 0, $this->maxRecordLength - mb_strlen('{"message":""}'));
        $record = ['message' => $record['message']];

        return $this->getJson($record);
        */
    }

    /**
     * @param $value
     * @param int $maxLevel - уровень, на котором скалярные значения остаются как есть, а другие типы превращаются в строку, например массив с двумя элементами превращается в "[ …2]"
     * @return string
     */
    public function dump($value, int $maxLevel = 0): string
    {
        if (is_null($value)) {
            return 'null';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_scalar($value)) {
            return (string) $value;
        }
        //return $this->varCloner->cloneVar($value)->dump(new CliDumper());
        //return (new CliDumper())->dump($serializebleClone, true);
        // return print_r($serializebleClone, true); // var_export
        $serializebleClone = $this->varCloner->cloneVar($value);
        $data = $serializebleClone->withMaxDepth($maxLevel);
        return trim(str_replace(PHP_EOL, ' ', $this->varDumper->dump($data, true)));
    }

    public function isRecordShort(string $record): bool
    {
        return mb_strlen($record) < $this->maxRecordLength;
    }

    public function getJson(array $record): string
    {
        return json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    }
/*
    public function getReducedRecord(array &$changeableRecord, array &$changeableRecordValue): string
    {
        $mysteriousCharacters = 2;
        $explanation = self::THE_LOG_ENTRY_IS_TOO_LONG_SO_IT_IS_REDUCED;
        $explanationLength = mb_strlen($explanation);
        $reversed = array_reverse($record, true);
        foreach ($reversed as $key => $value) {
            $result = $this->dump($value);
            $result = $this->getJson($record);
            if ($this->isMessageShort($result)) {
                return $result;
            }
            // находим, на сколько символов нужно уменьшить $record (лишнее количество символов):
            $excessCharactersInTheRecord = mb_strlen($result) - $this->maxRecordLength;
            if ($excessCharactersInTheRecord > 0) {
                // находим насколько мы должны подрезать value:
                $valueAsString = $this->varHelper->dump($value);
                // почему-то функция dump добавляет к строкам двойные кавычки, пока не разобрался, поэтому:
                if (is_string($value) && mb_substr($valueAsString, 0, 1) === '"' && mb_substr($value, 0, 1) !== '"') {
                    $valueAsString = mb_substr($valueAsString, 1);
                }
                $newValueMaxLength = mb_strlen($valueAsString) - $excessCharactersInTheRecord - $explanationLength - $mysteriousCharacters;
                if ($newValueMaxLength > 0) {// даем пояснение + подрезаем value:
                    $record[$key] = $explanation . mb_substr($valueAsString, 0, $newValueMaxLength);
                } else {// символов на подрезку не остается, увы удаляем value:
                    $record[$key] = self::THE_LOG_ENTRY_IS_TOO_LONG;
                }
            }
        }

        return $this->getJson($record);
    }*/
}
