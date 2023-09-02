<?php

declare(strict_types=1);

namespace YaPro\MonologExt\Processor;

use Exception;
use Monolog\Logger;

class StopExecutionWhenProblemProcessor
{
    private static bool $disableOnce = false;

    public function __invoke(array $record): array
    {
        if ($this->isProcess($record)) {
            self::$disableOnce = false;
            $this->handler($record);
        }

        return $record;
    }

    /**
     * Единоразово отключает распечатку информации об ошибке. Применяется при негативных тестах (например в
     * функциональном тесте, проверяем что ошибка обработана (например через try...catch) и залогирована, а функция
     * возвратила результат согласно негативному сценарию).
     */
    public static function disableOnce(): void
    {
        self::$disableOnce = true;
    }

    /**
     * Обработчик процессора
     */
    public function isProcess(array $record): bool
    {
        return
            array_key_exists('level', $record) &&
            $record['level'] > Logger::INFO &&
            self::$disableOnce === false;
    }

    /**
     * Обработчик процессора
     *
     * @param array $record
     */
    public function handler(array $record)
    {
        $message = $record['message'] ?? 'no message';
        $message .= PHP_EOL . (new Exception())->getTraceAsString();
        fwrite(STDERR, PHP_EOL . __FILE__ . ':' . __LINE__ . ' : ' . $message . PHP_EOL);
        // trigger_error() вызвать нельзя, т.к. выполнится перехват хендлером, например \Symfony\Bridge\PhpUnit\DeprecationErrorHandler::handleError()
    }
}
