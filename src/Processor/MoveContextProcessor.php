<?php

declare(strict_types=1);

namespace YaPro\MonologExt\Processor;

/**
 * Перемещает содержимое поля context в указанное в record-поле место + удаляет поле context
 */
class MoveContextProcessor
{
    public const DESTINATION_FIELD_NAME = 'destinationFieldName';

    public function __invoke(array $record): array
    {
        if (isset($record['context'][self::DESTINATION_FIELD_NAME])) {
            $newContextFieldName = $record['context'][self::DESTINATION_FIELD_NAME];
            $record[$newContextFieldName] = $record['context'];
            unset($record['context'], $record[$newContextFieldName][self::DESTINATION_FIELD_NAME]);
        }

        return $record;
    }
}
