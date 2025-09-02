<?php declare(strict_types=1);

namespace YaPro\MonologExt\Processor;

use Monolog\Processor\ProcessorInterface;

class AppEnvProcessor implements ProcessorInterface
{
    /**
     * {@inheritDoc}
     */
    public function __invoke(array $record): array
    {
        $record['extra']['env'] = $_ENV['APP_ENV'] ?? '-';

        return $record;
    }
}
