<?php

namespace Debug;

use Cascade\Cascade;
use Monolog\Logger;
use Doctrine\Common\Util\Debug;

final class Log
{
    const LEVEL_DEPTH = 2;

    /**
     * System is unusable.
     *
     * @param string $message
     * @param mixed $context
     * @return null
     */
    public static function emergency($message, $context = null)
    {
        self::log(Logger::EMERGENCY, $message, $context);
    }

    /**
     * Action must be taken immediately.
     *
     * Example: Entire website down, database unavailable, etc. This should
     * trigger the SMS alerts and wake you up.
     *
     * @param string $message
     * @param mixed $context
     * @return null
     */
    public static function alert($message, $context = null)
    {
        self::log(Logger::ALERT, $message, $context);
    }

    /**
     * Critical conditions.
     *
     * Example: Application component unavailable, unexpected exception.
     *
     * @param string $message
     * @param mixed $context
     * @return null
     */
    public static function critical($message, $context = null)
    {
        self::log(Logger::CRITICAL, $message, $context);
    }

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     *
     * @param string $message
     * @param mixed $context
     * @return null
     */
    public static function error($message, $context = null)
    {
        self::log(Logger::ERROR, $message, $context);
    }

    /**
     * Exceptional occurrences that are not errors.
     *
     * Example: Use of deprecated APIs, poor use of an API, undesirable things
     * that are not necessarily wrong.
     *
     * @param string $message
     * @param mixed $context
     * @return null
     */
    public static function warning($message, $context = null)
    {
        self::log(Logger::WARNING, $message, $context);
    }

    /**
     * Normal but significant events.
     *
     * @param string $message
     * @param mixed $context
     * @return null
     */
    public static function notice($message, $context = null)
    {
        self::log(Logger::NOTICE, $message, $context);
    }

    /**
     * Interesting events.
     *
     * Example: User logs in, SQL logs.
     *
     * @param string $message
     * @param mixed $context
     * @return null
     */
    public static function info($message, $context = null)
    {
        self::log(Logger::INFO, $message, $context);
    }

    /**
     * Detailed debug information.
     *
     * @param string $message
     * @param mixed $context
     * @return null
     */
    public static function debug($message, $context = null)
    {
        self::log(Logger::DEBUG, $message, $context);
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed $level
     * @param string $message
     * @param mixed $context
     * @return null
     */
    private static function log($level, $message, $context = null)
    {
        // on this place we can allow create unique indexes for the platform of Kibana
        $context = [
            'value' => $context,
        ];

        if ($message instanceof \Exception) {
            $e = $message;
            $message = $e->getMessage();
            $context['code'] = $e->getCode();
            $context['class'] = get_class($e);
        } else {
            $e = new DebugException;
        }

        if ($e instanceof DebugException) {
            // set real values of fields File and Line (if they will found)
            $placeTheCall = self::getInfoTheCall($e);
            $context['trace'] = self::getRealTraceString($e, $placeTheCall);
            if ($e->getExtra()) {
                $context['extra'] = $e->getExtra();
            }
        } else {
            $context['trace'] = $e->getTraceAsString();
        }

        // Exception of lambda function not have this methods
        if ($e->getFile()) {
            $context['file'] = $e->getFile();
        }
        if ($e->getLine()) {
            $context['line'] = $e->getLine();
        }

        if (class_exists('TC\Logger\LoggerProfiler')) {
            $loggerProfiler = \TC\Logger\LoggerProfiler::getInstance();
            $context['requestId'] = $loggerProfiler->getRequestId();
            $context['subrequestId'] = $loggerProfiler->getSubrequestId();
        }

        if (class_exists('TC\Logger\CurrentRequestHelper')) {
            $context['requestAsCurl'] = (new \TC\Logger\CurrentRequestHelper())->getRequestAsCurl();
        }

        if (
            class_exists('GuzzleHttp\Exception\RequestException') &&
            class_exists('GuzzleHttp\Post\PostBody') &&
            $e instanceof \GuzzleHttp\Exception\RequestException
        ) {
            $request = $e->getRequest();
            $body = $request->getBody();
            if ($body instanceof \GuzzleHttp\Post\PostBody && $fields = $body->getFields()) {
                // find bad provider
                if (!empty($fields['providers'][0])) {
                    $context['providerId'] = (int)$fields['providers'][0];
                }
            }
            $headers = [];
            foreach ($request->getHeaders() as $name => $values) {
                $headers[$name] = implode(', ', $values);
            }

            $context['guzzleRequest'] = [
                'url' => $request->getUrl(),
                'method' => $request->getMethod(),
                'config' => $request->getConfig(),
                'headers' => implode(': ', $headers),
            ];
        }

        Cascade::getLogger('mainLogger')->log($level, self::export($message), self::dumpContext($context));
    }

    /**
     * @param array $context
     * @return array where every value is string
     */
    private static function dumpContext(array $context)
    {
        foreach ($context as $key => &$value) {
            $value = self::export($value);
        }
        return $context;
    }

    /**
     * @param mixed $value
     * @return string
     */
    private static function export($value = null)
    {
        $value = Debug::export($value, self::LEVEL_DEPTH);
        // if $context was object - he will be converted to \StdClass
        if ($value instanceof \StdClass) {
            $value = (array)$value;
        }
        return stripslashes(var_export($value, true));
    }

    /**
     * @param DebugException $e
     * @return int
     */
    private static function getInfoTheCall(DebugException $e)
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
     * @param DebugException $e
     * @param int $placeTheCall
     * @return string
     */
    public static function getRealTraceString(DebugException $e, $placeTheCall = 0)
    {
        $realTrace = $e->getCustomTrace();
        for ($i = 0; $i < $placeTheCall; $i++) {
            $realTrace = strstr($realTrace, PHP_EOL);
        }
        return trim($realTrace);
    }
}