<?php

namespace Debug\Monolog\Processor;

use Debug\ErrorHandler;
use Debug\ExtraException;
use Doctrine\Common\Util\Debug as DoctrineDebug;

class Debug
{
    const LEVEL_DEPTH = 5;

    /**
     * @param  array $record
     * @return array
     */
    public function __invoke(array $record)
    {
        if (
            array_key_exists('context', $record) &&
            array_key_exists('exception', $record['context']) &&
            $record['context']['exception'] instanceof \Exception
        ) {
            $e = $record['context']['exception'];
            unset($record['context']['exception']);
            if ($message = $e->getMessage()) {
                $record['message'] = $message;
            }
            $record['code'] = $e->getCode();
            $record['class'] = get_class($e);
        } else {
            $e = new ExtraException();
        }

        if ($e instanceof ExtraException) {
            // set real values of fields File and Line (if they will found)
            $placeTheCall = $this->getInfoTheCall($e);
            $record['context']['trace'] = $this->getRealTraceString($e, $placeTheCall);
            if ($e->getExtra()) {
                $record['extra'] = $e->getExtra();
            }
        } else {
            $record['context']['trace'] = $e->getTraceAsString();
        }

        // Exception of lambda function not have this methods
        if ($e->getFile()) {
            $record['file'] = $e->getFile();
        }
        if ($e->getLine()) {
            $record['line'] = $e->getLine();
        }

        return $this->dump($record);
    }

    /**
     * @param ExtraException $e
     * @return int
     */
    private function getInfoTheCall(ExtraException $e)
    {
        $placeTheCall = 0;
        $trace = $e->getTrace();
        foreach ($trace as $place => $info) {
            if (array_key_exists('class', $info) && $this->isNotTrackClass($info['class'])) {
                $placeTheCall = $place;
                // in latest iteration contained real file and line will
                if (array_key_exists('file', $info)) {
                    $e->setFile($info['file']);
                }
                if (array_key_exists('line', $info)) {
                    $e->setLine($info['line']);
                }
            } else {
                break;
            }
        }
        return $placeTheCall;
    }

    /**
     * @param string $className
     * @return bool : true - if class not tracked
     */
    private function isNotTrackClass($className)
    {
        return ($className === self::class || $className === ErrorHandler::class);
    }

    /**
     * remove trace-line which contains a call the current method
     * @param ExtraException $e
     * @param int $placeTheCall
     * @return string
     */
    public function getRealTraceString(ExtraException $e, $placeTheCall = 0)
    {
        $realTrace = $e->getCustomTrace();
        for ($i = 0; $i < $placeTheCall; $i++) {
            $realTrace = strstr($realTrace, PHP_EOL);
        }
        return trim($realTrace);
    }

    /**
     * @param array $record
     * @return array where every value is string
     */
    public function dump(array $record)
    {
        foreach ($record as $key => &$value) {
            $value = $this->export($value);
        }
        return $record;
    }

    /**
     * @param mixed $value
     * @param int $maxDepth
     * @return mixed
     */
    public function export($value = null, $maxDepth = self::LEVEL_DEPTH)
    {
        $value = DoctrineDebug::export($value, $maxDepth);
        // if $value was object - DoctrineDebug convert his to \StdClass
        if ($value instanceof \StdClass) {
            $value = (array)$value;
        }
        return $value;
    }

    /**
     * @param mixed $value
     * @return string
     */
    public function getString($value = null)
    {
        return stripslashes(var_export($value));
    }
}
