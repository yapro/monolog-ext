<?php

namespace Debug\Monolog\Processor;

use Debug\ErrorHandler;
use Debug\ExtraException;
use Monolog\Logger;

class Debug
{
    const DISABLE = 'disableMonologProcessorDebug';

    /**
     * @param  array $record
     * @return array
     */
    public function __invoke(array $record)
    {
        $weakLevel = array_key_exists('level', $record) && $record['level'] < Logger::NOTICE;
        if ($weakLevel === true || isset($record['context'][self::DISABLE])) {
            return $record;
        }
        if (!array_key_exists('extra', $record)) {
            $record['extra'] = null;
        }
        $e = null;
        if (!empty($record['context']['exception']) && $record['context']['exception'] instanceof \Exception) {
            $e = $record['context']['exception'];
            unset($record['context']['exception']);
        } elseif ($record['message'] instanceof \Exception) {
            $e = $record['message'];
        } else {
            $e = null;
        }

        if ($e instanceof \Exception) {
            $record['message'] = $e->getMessage();
            $record['extra']['code'] = $e->getCode();
            $record['extra']['class'] = get_class($e);
            $record['extra']['trace'] = $e->getTraceAsString();
            if (!array_key_exists('context', $record)) {
                $record['context'] = null;
            }
            // Exception of lambda function does not have file and line values:
            if ($e->getFile()) {
                $record['context']['file'] = $e->getFile();
            }
            if ($e->getLine()) {
                $record['context']['line'] = $e->getLine();
            }
        } else {
            if (empty($record['context']['stack'])){// try to find real trace:
                $record['context']['stack'] = $this->getStackTraceBeforeMonolog();
            }
            if (// Let`s get a stack which returned from \Symfony\Component\Debug\ErrorHandler::handleException and formatted to IDE-format
                !empty($record['context']['stack']) &&
                is_array($record['context']['stack'])
            ) {
                // @todo delete it when in symfony will be implemented:
                // https://github.com/symfony/symfony/pull/17168
                // https://github.com/symfony/monolog-bundle/pull/153
                $record['extra']['trace'] = self::getStackTraceForPhpStorm($record['context']['stack']);
                unset($record['context']['stack']);
            }
        }

        return $record;
    }

    /**
     * @return array
     */
    private static function getStackTraceBeforeMonolog()
    {
        $trace = (new \Exception())->getTrace();
        foreach ($trace as $i => $info) {
            if (array_key_exists('class', $info) && $info['class'] === 'Monolog\Logger') {
                unset($trace[$i]);// remove a call from Monolog\Logger::addRecord
                return $trace;
            }
            unset($trace[$i]);
        }
        return $trace;
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
            if (array_key_exists('file', $frame)) {
                $file = $frame['file'];
                $line = $frame['line'];
            }
            $class = array_key_exists('class', $frame) ? $frame['class'] : '';
            $type = array_key_exists('type', $frame) ? $frame['type'] : '';
            $function = array_key_exists('function', $frame) ? $frame['function'] : '';
            if (substr($function, 0, 16) === 'call_user_func:{') {
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
