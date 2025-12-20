<?php declare(strict_types=1);

namespace YaPro\MonologExt\Processor;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

class RequestIdProcessor implements ProcessorInterface
{
    /**
     * {@inheritDoc}
     */
    public function __invoke(LogRecord $record): LogRecord
    {
        $record['extra']['request_id'] = $_SERVER['REQUEST_ID'] ?? '-';
        $record['extra']['request_id_forwarded'] = $_SERVER['HTTP_X_FORWARDED_REQUEST_ID'] ?? '-';

        return $record;
    }
}
