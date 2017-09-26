<?php
declare(strict_types = 1);

namespace Debug;

use Doctrine\Common\Util\Debug as DoctrineDebug;

class DebugUtility
{
    const DEPTH_LEVEL = 2;

    /**
     * @param mixed $value
     * @param int $depthLevel
     * @return string
     */
    public static function export($value = null, $depthLevel = self::DEPTH_LEVEL)
    {
        $value = DoctrineDebug::export($value, $depthLevel);
        // if $context was object - he will be converted to \StdClass
        if ($value instanceof \stdClass) {
            $value = (array)$value;
        }
        return stripslashes(var_export($value, true));
    }

    /**
     * @param \Throwable $e
     * @return array
     */
    public static function exportException($e)
    {
        $record = array(
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'class' => get_class($e),
            'trace' => $e->getTraceAsString(),
        );
        if ($e instanceof ExtraException && $e->getExtra()) {
            $record['extra'] = is_string($e->getExtra()) ? $e->getExtra() : self::export($e->getExtra());
        }
        // Exception of lambda function does not have file and line values:
        if ($e->getFile()) {
            $record['file'] = $e->getFile();
        }
        if ($e->getLine()) {
            $record['line'] = $e->getLine();
        }
        return $record;
    }

    /**
     * for support php 5.x and php 7.x
     * @param mixed $e
     * @return bool
     */
    public static function isException($e)
    {
        return $e instanceof \Exception || (class_exists('Throwable') && $e instanceof \Throwable);
    }

    /**
     * @return string
     */
    public static function getHttpRequestAsCurl(): string
    {
        $data = file_get_contents(STDIN);
        return 'curl \'http'.($_SERVER['HTTPS'] ? 's':'').'://' . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'] . '\' ' .
            implode(' ', self::getHeaderList()) . ' '.
            ($_SERVER['REQUEST_METHOD'] === 'GET' ? '' : ' -X '.$_SERVER['REQUEST_METHOD'].($data ? ' --data \'' .$data.'\'' : ''))
            ;
    }

    /**
     * @return array
     */
    private static function getHeaderList(): array
    {
        $headerList = [];
        foreach ($_SERVER as $name => $value) {
            if (preg_match('/^HTTP_/',$name)) {
                // convert HTTP_HEADER_NAME to Header-Name
                $name = strtr(substr($name,5),'_',' ');
                $name = ucwords(strtolower($name));
                $name = strtr($name,' ','-');
                $headerList[] = '-H \'' . $name . ': ' . $value . '\'';
            }
        }
        return $headerList;
    }
}