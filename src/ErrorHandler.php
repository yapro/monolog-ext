<?php
namespace Debug;

final class ErrorHandler
{
    private $errorReporting;

    public function register($errorReporting)
    {
        $this->errorReporting = $errorReporting;
        register_shutdown_function([$this, 'handleShutdown']);
        set_exception_handler([$this, 'handleException']);
        set_error_handler([$this, 'handleError'], E_ALL);
    }

    public function handleShutdown()
    {
        $lastError = error_get_last();
        if (empty($lastError['type'])) {
            return;
        }
        $e = (new ExtraException)
            ->setCode($lastError['type'])
            ->setFile($lastError['file'])
            ->setLine($lastError['line'])
            ->setMessage($lastError['message']);
        $this->log($e);
    }

    /**
     * @param \Throwable $e
     */
    public function handleException($e)
    {
        $this->log($e);
    }

    /**
     * @param number $type
     * @param string $message
     * @param string $file
     * @param int $line
     * @param array $extra
     * @throws ExtraException if error type is E_RECOVERABLE_ERROR
     */
    public function handleError($type, $message, $file = '', $line = 0, array $extra = array())
    {
        $e = (new ExtraException)
            ->setCode($type)
            ->setFile($file)
            ->setLine($line)
            ->setMessage($message)
            ->setExtra($extra);
        $this->log($e);
        if ($type === E_RECOVERABLE_ERROR) {
            restore_error_handler();
            restore_exception_handler();
            throw new $e;
        }
    }

    /**
     * @param int $code
     * @return bool
     */
    private function isErrorIgnored($code)
    {
        // converting code of exception
        if ($code === 0) {
            $code = E_ERROR;
        }
        if ($code & $this->errorReporting) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * @param \Throwable $e
     */
    private function log($e)
    {
        if ($this->isErrorIgnored($e->getCode())) {
            return;
        }
        Log::critical($e);
    }
}