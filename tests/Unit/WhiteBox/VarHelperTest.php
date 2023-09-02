<?php

declare(strict_types=1);

namespace YaPro\MonologExt\Tests\Unit\WhiteBox;

use Exception;
use PHPUnit\Framework\TestCase;
use YaPro\MonologExt\VarHelper;

class VarHelperTest extends TestCase
{
    public function testDumpException(): void
    {
        $exception = new Exception();
        $expected = [
            'code' => $exception->getCode(),
            'message' => $exception->getMessage(),
            'class' => Exception::class,
            'trace' => $exception->getTraceAsString(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ];;
        $varHelper = $this->getMockBuilder(VarHelper::class)->setMethodsExcept(['dumpException'])->getMock();
        $result = $varHelper->dumpException($exception);
        $this->assertEquals($expected, $result);
    }

}
