<?php

namespace Debug\Monolog\Processor;

use Debug\ErrorHandler;
use Debug\ExtraException;

class Debug
{
    /**
     * @param  array $record
     * @return array
     */
    public function __invoke(array $record)
    {
        if ($record['message'] instanceof \Exception) {
            $e = $record['message'];
            $record['message'] = $e->getMessage();
            $record['code'] = $e->getCode();
            $record['class'] = get_class($e);
        } else {
            $e = new ExtraException();
        }

        if ($e instanceof ExtraException) {
            // set real values of fields File and Line (if they will found)
            $placeTheCall = self::getInfoTheCall($e);
            $record['trace'] = self::getRealTraceString($e, $placeTheCall);
            if ($e->getExtra()) {
                $record['extra'] = $e->getExtra();
            }
        } else {
            $record['trace'] = $e->getTraceAsString();
        }
        return $record;
    }

    /**
     * @param ExtraException $e
     * @return int
     */
    private static function getInfoTheCall(ExtraException $e)
    {
        $placeTheCall = 0;
        $trace = $e->getTrace();
        foreach ($trace as $place => $info) {
            if (array_key_exists('class', $info) && self::isNotTrackClass($info['class'])) {
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
    private static function isNotTrackClass($className)
    {
        return ($className === self::class || $className === ErrorHandler::class);
    }

    /**
     * remove trace-line which contains a call the current method
     * @param ExtraException $e
     * @param int $placeTheCall
     * @return string
     */
    public static function getRealTraceString(ExtraException $e, $placeTheCall = 0)
    {
        $realTrace = $e->getCustomTrace();
        for ($i = 0; $i < $placeTheCall; $i++) {
            $realTrace = strstr($realTrace, PHP_EOL);
        }
        return trim($realTrace);
    }
}
