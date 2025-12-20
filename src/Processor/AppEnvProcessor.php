<?php declare(strict_types=1);

namespace YaPro\MonologExt\Processor;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

class AppEnvProcessor implements ProcessorInterface
{
    /**
     * {@inheritDoc}
     */
    public function __invoke(LogRecord $record): LogRecord
    {
        $record['extra']['env'] = $_ENV['APP_ENV'] ?? '-';

        return $record;
    }
}
