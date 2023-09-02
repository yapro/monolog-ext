<?php

declare(strict_types=1);

namespace YaPro\MonologExt\Processor;

use Exception;

/**
 * Добавляет стектрейс места возникновения log-record (помогает понять в каком месте контекста произошла проблема).
 */
class AddStackTraceOfCallPlaceProcessor
{
    public function __invoke(array $record): array
    {
        if (empty($record['context']['stack'])) {// try to find real trace:
            $trace = (new Exception())->getTrace();
            $record['context']['stack'] = $this->getStackTraceBeforeMonolog($trace);
        }

        return $record;
    }

    public function getStackTraceBeforeMonolog(array $trace): array
    {
        foreach ($trace as $i => $info) {
            if (array_key_exists('class', $info) && $info['class'] === 'Monolog\Logger') {
                unset($trace[$i]); // remove a call from Monolog\Logger::addRecord

                return $trace;
            }
            unset($trace[$i]);
        }

        return $trace;
    }
}
