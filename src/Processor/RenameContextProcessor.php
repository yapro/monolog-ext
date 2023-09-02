<?php

declare(strict_types=1);

namespace YaPro\MonologExt\Processor;

/**
 * Перемещает содержимое поля context в поле указанное в конструкторе процессора + удаляет поле context
 */
class RenameContextProcessor
{
    private string $destinationFieldName;

    public function __construct(string $destinationFieldName = 'debugInfo')
    {
        $this->destinationFieldName = $destinationFieldName;
    }

    public function __invoke(array $record): array
    {
        if (isset($record['context'])) {
            $record[$this->destinationFieldName] = $record['context'];
            unset($record[$this->destinationFieldName]);
        }

        return $record;
    }
}
