<?php
declare(strict_types = 1);

namespace Debug;

use Doctrine\Common\Util\Debug as DoctrineDebug;

class DebugUtility
{
    const DEPTH_LEVEL = 2;

    /**
     * @param mixed $value
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
     * @param \Exception $e
     * @return array
     */
    public static function exportException(\Exception $e)
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
}