<?php

namespace Debug;

final class ExtraException extends \Exception
{
    /**
     * @var string
     */
    private $customTrace;

    /**
     * @var mixed
     */
    private $extra;

    /**
     * @return string
     */
    public function getCustomTrace()
    {
        return $this->customTrace ?: $this->getTraceAsString();
    }

    /**
     * @param string $customTrace
     * @return $this
     */
    public function setCustomTrace($customTrace)
    {
        $this->customTrace = $customTrace;
        return $this;
    }

    /**
     * @param string $message
     * @return $this
     */
    public function setMessage($message)
    {
        $this->message = $message;
        return $this;
    }

    /**
     * @param integer $code
     * @return $this
     */
    public function setCode($code)
    {
        $this->code = $code;
        return $this;
    }

    /**
     * @param string $file
     * @return $this
     */
    public function setFile($file)
    {
        $this->file = $file;
        return $this;
    }

    /**
     * @param integer $line
     * @return $this
     */
    public function setLine($line)
    {
        $this->line = $line;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getExtra()
    {
        return $this->extra;
    }

    /**
     * @param mixed $extra
     * @return $this
     */
    public function setExtra($extra)
    {
        $this->extra = $extra;
        return $this;
    }
}