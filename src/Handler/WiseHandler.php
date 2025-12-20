<?php

declare(strict_types=1);

namespace YaPro\MonologExt\Handler;

use Exception;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use Monolog\LogRecord;
use JsonException;
use YaPro\MonologExt\Processor\AddStackTraceOfCallPlaceProcessor;

// В прод-режиме пишет логи в stderr в json-формате
class WiseHandler extends AbstractProcessingHandler
{
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
    private int $stopRequestWhenRecordLevelAbove = Logger::CRITICAL;

    public const MAX_DUMP_LEVEL_DEFAULT = 5;
    private int $maxDumpLevel = self::MAX_DUMP_LEVEL_DEFAULT;
    /**
     * Проблема масштабная:
     *  1. PHP дробит строки длинной больше чем значение log_limit https://www.php.net/manual/en/install.fpm.configuration.php#log-limit
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
        $this->devModePhpFpm = !empty($_ENV['EH_DEV_MODE_PHP_FPM']);
        $this->devModePhpCli = !empty($_ENV['EH_DEV_MODE_PHP_CLI']);
        if (isset($_ENV['EH_MAX_DUMP_LEVEL'])) {
            $this->maxDumpLevel = (int) $_ENV['EH_MAX_DUMP_LEVEL'];
        }
        if (isset($_ENV['EH_IGNORE_RECORD_LEVEL_BELOW'])) {
            $this->ignoreRecordLevelBelow = (int) $_ENV['EH_IGNORE_RECORD_LEVEL_BELOW'];
        }
        if (isset($_ENV['EH_STOP_WHEN_RECORD_LEVEL_ABOVE'])) {
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
    }

    // Не реализуем метод isHandling т.к. он уже реализован \Monolog\Handler\AbstractHandler::isHandling(), а главное
    // то, что его обязанность лишь в проверке уровня ошибки, по-умолчанию DEBUG, см. детали:
    // \Monolog\Logger::addRecord() : if (!$handler->isHandling(['level' => $level])) {
    public function isSupporting(array $record): bool
    {
        // игнорируем http ошибки клиента (4xx):
        if (isset($record['context']['exception']) &&
            class_exists('\Symfony\Component\HttpKernel\Exception\HttpException') &&
            is_a($record['context']['exception'], '\Symfony\Component\HttpKernel\Exception\HttpException') &&
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

    public function handle(LogRecord $record): bool
    {
        if (!$this->isSupporting($record->toArray())) {
            return false;
        }
        $record = $this->processRecord($record);
        $this->write($record);

        return false;
    }

    public function getDevFormattedMessage(array $record): string
    {
        $result = PHP_EOL . ':::::::::::::::::::: ' . __CLASS__ . ' informs ::::::::::::::::::' . PHP_EOL . PHP_EOL;

        $result .= $record['message'] . PHP_EOL;
        unset($record['message']);

        $stackTraceOfCallPlaceProcessor = new AddStackTraceOfCallPlaceProcessor();
        $trace = (new Exception())->getTrace();
        $stackTraceBeforeMonolog = $stackTraceOfCallPlaceProcessor->getStackTraceBeforeMonolog($trace);
        $callPlace = reset($stackTraceBeforeMonolog);
        $result .= 'The log entry has been wrote by ' . ($callPlace['file'] ?? '') . ':' . ($callPlace['line'] ?? '') . PHP_EOL;

        $result .= $this->getJson($this->dumpToScalarArray($record, $this->maxDumpLevel));

        return $result;
    }

    /**
     * @throws JsonException
     */
    public function write(LogRecord $record): void
    {
        $record = $record->toArray();
        $isHttp = PHP_SAPI === 'fpm-fcgi';
        if ($this->devModePhpFpm && $isHttp) {
            http_response_code(500);
            echo '<pre>' . $this->getDevFormattedMessage($record);
            exit(120);
        }
        if ($this->devModePhpCli && !$isHttp) {
            $this->writeToStdErr($this->getDevFormattedMessage($record));
            exit(121);
        }
        $result = $this->getMessage($record);
        if (sha1($result) === $this->lastRecordHash) {
            return;
        }
        $this->lastRecordHash = sha1($result);
        $this->writeToStdErr($result);
        if (!isset($record['level'])) {
            $isHttp && http_response_code(500);
            echo 'Sorry, an unexpected error has occurred. The log entry has no level. The error has been logged.';
            exit(122);
        }
        // По причине https://yapro.ru/article/16221 останавливаю обработку ошибок:
        if ($record['level'] > $this->stopRequestWhenRecordLevelAbove) {
            $isHttp && http_response_code(500);
            echo 'Sorry, an unexpected error has occurred. The error has been logged.';
            exit(123);
        }
    }

    public function writeToStdErr(string $message)
    {
        // todo можно подумать над тем, чтобы сплитить запись на несколько при превышении длинны
        fwrite($this->stderr, $message . PHP_EOL);
    }

    public function getMessage(array $record): string
    {
        for ($maxDumpLevel = $this->maxDumpLevel; $maxDumpLevel > 0; $maxDumpLevel--) {
            $dump = $this->dumpToScalarArray($record, $maxDumpLevel);
            $json = $this->getJson($dump);
            $isRecordShort = strlen($json) < $this->maxRecordLength;
            if ($isRecordShort) {
                return $json;
            }
        }
        return '{"message": "the record is too big"}';
    }

    public function getJson(array $record): string
    {
        return json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    }
    public function dumpToScalarArray(
        mixed $value,
        int $maxDepth = 10,
        int $depth = 0,
        array &$refs = []
    ): mixed {
        if ($depth > $maxDepth) {
            return '**MAX_DEPTH**';
        }

        if ($value === null || is_scalar($value)) {
            return $value;
        }

        if (is_array($value)) {
            $result = [];
            foreach ($value as $k => $v) {
                $result[$this->normalizeKey($k)] =
                    $this->dumpToScalarArray($v, $maxDepth, $depth + 1, $refs);
            }
            return $result;
        }

        if (is_object($value)) {
            $id = spl_object_id($value);

            if (isset($refs[$id])) {
                return '**RECURSION(' . get_class($value) . ')**';
            }

            $refs[$id] = true;

            $data = [
                '__type'  => 'object',
                '__class' => get_class($value),
            ];

            foreach ((array) $value as $key => $val) {
                $key = $this->normalizeObjectKey($key);
                $data[$key] =
                    $this->dumpToScalarArray($val, $maxDepth, $depth + 1, $refs);
            }

            return $data;
        }

        if (is_resource($value)) {
            return '**RESOURCE(' . get_resource_type($value) . ')**';
        }

        if (is_callable($value)) {
            return '**CALLABLE**';
        }

        return '**UNKNOWN**';
    }

    public function normalizeKey(mixed $key): string|int
    {
        return is_int($key) ? $key : (string) $key;
    }

    public function normalizeObjectKey(string $key): string
    {
        if (str_contains($key, "\0")) {
            $parts = explode("\0", $key);
            return end($parts);
        }

        return $key;
    }
}
