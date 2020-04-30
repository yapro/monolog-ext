<?php

declare(strict_types=1);

namespace YaPro\MonologExt;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;

class LogHandler extends AbstractProcessingHandler
{
    public const TABLE_NAME = 'system_log';

    private Connection $connection;
    private string $logFile;

    public function __construct(
        string $dbHost,
        string $dbPort,
        string $dbName,
        string $dbUserName,
        string $dbPassword,
        string $logFileForUnexpectedErrors
    ) {
        parent::__construct(Logger::NOTICE, true);
        $this->setFormatter(new DbRecordFormatter());
        // отдельный коннект к отдельной бд потому что:
        // - лучше использовать отдельную бд которую не нужно бэкапить
        // - приложение иногда начинает транзакцию, которая может не закончится и затем пишет в лог, а транзакция ведь
        //   может закончится падением и следовательно никаких логов мы не увидим.
        // https://www.doctrine-project.org/projects/doctrine-dbal/en/2.10/reference/configuration.html
        $this->connection = DriverManager::getConnection([
            'host' => $dbHost,
            'port' => $dbPort,
            'dbname' => $dbName,
            'user' => $dbUserName,
            'password' => $dbPassword,
            'driver' => 'pdo_mysql',
        ]);
        $this->logFile = $logFileForUnexpectedErrors;
    }

    protected function write(array $record): void
    {
        try {
            if (
                $record['channel'] === 'event' ||
                $record['channel'] === 'doctrine'
            ) {
                return;
            }
            $this->connection->insert(self::TABLE_NAME, $record['formatted']);
        } catch (\Throwable $exception) {
            $logRecord = [
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'code' => $exception->getCode(),
                'class' => get_class($exception),
                'trace' => $exception->getTraceAsString(),
            ];
            file_put_contents(
                $this->logFile,
                date('Y-m-d H:i:s') . ':' . print_r($logRecord, true) . PHP_EOL,
                FILE_APPEND);
        }
    }
}
