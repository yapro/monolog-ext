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
        if(!array_key_exists('extra', $record)){
            $record['extra'] = null;
        }
        $e = null;
        if(!empty($record['context']['exception']) && $record['context']['exception'] instanceof \Exception){
            $e = $record['context']['exception'];
            unset($record['context']['exception']);
        }elseif($record['message'] instanceof \Exception){
            $e = $record['message'];
        }else{
            $e = null;
        }

        if ($e instanceof \Exception) {
            $record['message'] = $e->getMessage();
            $record['extra']['code'] = $e->getCode();
            $record['extra']['exceptionClass'] = get_class($e);
            $record['extra']['trace'] = $e->getTraceAsString();
            if(!array_key_exists('context', $record)){
                $record['context'] = null;
            }
            // Exception of lambda function not have this methods
            if ($e->getFile()) {
                $record['context']['file'] = $e->getFile();
            }
            if ($e->getLine()) {
                $record['context']['line'] = $e->getLine();
            }
        } else {
            if(// \Symfony\Component\Debug\ErrorHandler::handleException
                !empty($record['context']['stack']) &&
                is_array($record['context']['stack'])
            ){
                // @todo delete it when in symfony will be implemented:
                // https://github.com/symfony/symfony/pull/17168
                // https://github.com/symfony/monolog-bundle/pull/153
                $record['extra']['trace'] = self::getStackTraceForPhpStorm($record['context']['stack']);
                unset($record['context']['stack']);
            }else{
                $e = new ExtraException();
                // set real values of fields File and Line (if they will found)
                $placeTheCall = self::getInfoTheCall($e);
                $record['extra']['trace'] = self::getRealTraceString($e, $placeTheCall);
                if ($e->getExtra()) {
                    $record['extra'] = $e->getExtra();
                }
            }
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

    /**
     * @param array $trace
     * @return string
     */
    public static function getStackTraceForPhpStorm(array $trace)
    {
        $rtn = "";
        $count = count($trace);
        foreach ($trace as $frame) {
            $count--;
            $args = "";
            if (isset($frame['args'])) {
                $args = array();
                foreach ($frame['args'] as $arg) {
                    if (is_string($arg)) {
                        $args[] = "'" . $arg . "'";
                    } elseif (is_array($arg)) {
                        $args[] = "Array";
                    } elseif (is_null($arg)) {
                        $args[] = 'NULL';
                    } elseif (is_bool($arg)) {
                        $args[] = ($arg) ? "true" : "false";
                    } elseif (is_object($arg)) {
                        $args[] = get_class($arg);
                    } elseif (is_resource($arg)) {
                        $args[] = get_resource_type($arg);
                    } else {
                        $args[] = $arg;
                    }
                }
                $args = join(", ", $args);
            }
            $file = '[internal function]';
            $line = '';
            if(array_key_exists('file', $frame)){
                $file = $frame['file'];
                $line = $frame['line'];
            }
            $class = array_key_exists('class', $frame) ? $frame['class'] : '';
            $type = array_key_exists('type', $frame) ? $frame['type'] : '';
            $function = array_key_exists('function', $frame) ? $frame['function'] : '';
            if(substr($function, 0, 16) === 'call_user_func:{'){
                $function = substr($function, 0, 14);
            }
            $rtn .= sprintf("#%s %s(%s): %s%s%s(%s)\n",
                $count,
                $file,
                $line,
                $class,
                $type,
                $function,
                $args
            );
        }
        return $rtn;
    }
}
