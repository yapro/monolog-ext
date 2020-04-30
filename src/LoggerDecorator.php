<?php

declare(strict_types=1);

namespace YaPro\MonologExt;

use Symfony\Bridge\Monolog\Logger;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class LoggerDecorator extends Logger
{
    public function addRecord(int $level, string $message, array $context = []): bool
    {
        if (reset($context) instanceof AccessDeniedHttpException) {
            return true;
        }

        return parent::addRecord($level, $message, $context);
    }
}
