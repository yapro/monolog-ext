<?php

declare(strict_types=1);

namespace YaPro\MonologExt\Processor;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Перемещает содержимое поля context в поле указанное в конструкторе процессора + удаляет поле context
 */
class RenameContextProcessor implements ProcessorInterface
{
    private string $destinationFieldName;

    public function __construct(string $destinationFieldName = 'debugInfo')
    {
        $this->destinationFieldName = $destinationFieldName;
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        if (isset($record['context'])) {
            $record[$this->destinationFieldName] = $record['context'];
            unset($record['context']);
        }

        return $record;
    }
}
