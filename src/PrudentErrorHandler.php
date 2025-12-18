<?php
declare(strict_types=1);

namespace YaPro\MonologExt;

use Psr\Log\LoggerInterface;
use Throwable;

final class PrudentErrorHandler
{
    public function __construct(
        private LoggerInterface $logger,
        int $errorReporting = E_ALL & ~E_DEPRECATED & ~E_STRICT
    ) {
        register_shutdown_function([$this, 'handleShutdown']);
        set_exception_handler([$this, 'handleException']);
        set_error_handler([$this, 'handleError'], $errorReporting);
    }

    public function handleShutdown()
    {
        $lastError = error_get_last();
        if (empty($lastError['type'])) {
            return;
        }
        $message = $lastError['message'];
        unset($lastError['message']);
        $this->logger->error($message, $lastError);
    }

    public function handleException(Throwable $e): void
    {
        $this->logger->error('Unhandled exception', [$e]);
    }

    public function handleError(int $type, string $message, string $file = '', int $line = 0): void
    {
        $this->logger->error($message, [
            'file' => $file,
            'line' => $line,
            'php_error_type' => $type,
        ]);
        // To avoid recursion in case of failure we will restore error handlers:
        // https://www.php.net/manual/ru/errorfunc.constants.php#constant.e-recoverable-error
        if ($type === E_ERROR || $type === E_RECOVERABLE_ERROR) {
            restore_error_handler();
            restore_exception_handler();
        }
    }
}