<?php

declare(strict_types=1);

namespace YaPro\MonologExt\Tests\Unit\WhiteBox\Processor;

use PHPUnit\Framework\TestCase;
use YaPro\MonologExt\Processor\AddStackTraceOfCallPlaceProcessor;

class AddStackTraceOfCallPlaceProcessorTest extends TestCase
{
    public function invokeProvider(): array
    {
        return [
            [
                'record' => [
                    'context' => [
                    ],
                ],
                'stackTraceBeforeMonolog' => [1, 2, 3],
                'expectedStack' => [1, 2, 3],
            ],
            [
                'record' => [
                    'context' => [
                        'stackTraceOfCallPlace' => [4, 5, 6],
                    ],
                ],
                'stackTraceBeforeMonolog' => [1, 2, 3],
                'expectedStack' => [4, 5, 6],
            ],
        ];
    }

    /**
     * @dataProvider invokeProvider
     */
    public function WIP_testInvoke(array $record, array $stackTraceBeforeMonolog, array $expectedStack)
    {
        $processor = $this->getMockBuilder(AddStackTraceOfCallPlaceProcessor::class)
            ->setMethodsExcept(['disableOnce', '__invoke'])
            ->getMock();
        $processor->method('getStackTraceBeforeMonolog')->willReturn($stackTraceBeforeMonolog);

        $record = $processor($record);
        $this->assertEquals($expectedStack, $record['context']['stackTraceOfCallPlace']);
    }

    public function getStackTraceBeforeMonologProvider(): array
    {
        return [
            [
                'recordArg' => [
                    0 => [
                        'function' => 'someFunction1',
                        'type' => '->',
                        'class' => 'Symfony\Component\HttpKernel\HttpKernel',
                        'file' => 'someFile.php',
                        'line' => 33,
                        'args' => [],
                    ],
                    1 => [
                        'function' => 'someFunction2',
                        'type' => '->',
                        'class' => 'Monolog\Logger',
                        'file' => 'someFile.php',
                        'line' => 66,
                        'args' => [],
                    ],
                    2 => [
                        'function' => 'someFunction2',
                        'type' => '->',
                        'class' => 'Symfony\Component\HttpKernel\HttpKernel',
                        'file' => 'someFile.php',
                        'line' => 66,
                        'args' => [],
                    ],
                ],
                'expected' => [
                    2 => [
                        'function' => 'someFunction2',
                        'type' => '->',
                        'class' => 'Symfony\Component\HttpKernel\HttpKernel',
                        'file' => 'someFile.php',
                        'line' => 66,
                        'args' => [],
                    ],
                ],
            ],
        ];
    }

    /**
     * @dataProvider getStackTraceBeforeMonologProvider
     */
    public function WIP_testGetStackTraceBeforeMonolog(array $recordArg, array $expected)
    {
        $processor = new AddStackTraceOfCallPlaceProcessor();
        $this->assertEquals($expected, $processor->getStackTraceBeforeMonolog($recordArg));
    }
}
