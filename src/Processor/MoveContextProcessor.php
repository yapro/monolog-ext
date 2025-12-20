<?php

declare(strict_types=1);

namespace YaPro\MonologExt\Processor;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Перемещает содержимое поля context в указанное в record-поле место + удаляет поле context
 */
class MoveContextProcessor implements ProcessorInterface
{
    public const DESTINATION_FIELD_NAME = 'destinationFieldName';

    public function __invoke(LogRecord $record): LogRecord
    {
        if (isset($record['context'][self::DESTINATION_FIELD_NAME])) {
            $newContextFieldName = $record['context'][self::DESTINATION_FIELD_NAME];
            $record[$newContextFieldName] = $record['context'];
            unset($record['context'], $record[$newContextFieldName][self::DESTINATION_FIELD_NAME]);
        }

        return $record;
    }
}
