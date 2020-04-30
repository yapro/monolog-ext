<?php

declare(strict_types=1);

namespace YaPro\MonologExt;

use Monolog\Formatter\NormalizerFormatter;

/**
 * Partially copied from \Monolog\Formatter\ElasticsearchFormatter
 */
class DbRecordFormatter extends NormalizerFormatter
{
    public const DATE_FORMAT = 'Y-m-d H:i:s';

    private string $projectName;
    private string $httpRequestId;

    public function __construct(string $projectName = 'app')
    {
        parent::__construct(self::DATE_FORMAT);
        $this->projectName = $projectName;
        $this->httpRequestId = $this->generateUid();
    }

    /**
     * @param array $record
     *
     * @return array
     */
    public function format(array $record): array
    {
        $record = parent::format($record);

        return [
            'project_name' => $this->projectName,
            'source_name' => PHP_SAPI,
            'level_name' => $record['level_name'] ?? '',
            'message' => $record['message'] ?? '',
            'datetime' => $record['datetime'] ?? date(self::DATE_FORMAT),
            // Дополнительные данные, которые могут отсутствовать:
            'http_request_id' => $record['extra']['uid'] ?? $this->httpRequestId,
            'channel' => $record['channel'] ?? '',
            'context' => isset($record['context']) ? json_encode($record['context'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '',
            'extra' => isset($record['extra']) ? json_encode($record['extra'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '',
        ];
    }

    private function generateUid(int $length = 7): string
    {
        return substr(bin2hex(random_bytes((int) ceil($length / 2))), 0, $length);
    }
}
