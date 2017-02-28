<?php
declare(strict_types = 1);

namespace Debug;

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
}