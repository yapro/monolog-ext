<?php

declare(strict_types=1);

namespace YaPro\MonologExt\Tests\Unit\Processor;

use YaPro\MonologExt\Processor\AddLogRecordStackTraceProcessor;
use PHPUnit\Framework\TestCase;

/**
 * Тестирование \YaPro\MonologExt\Processor\AddLogRecordStackTraceProcessor
 */
class AddLogRecordStackTraceProcessorTest extends TestCase
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
                        'stack' => [4, 5, 6],
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
    public function testInvoke(array $record, array $stackTraceBeforeMonolog, array $expectedStack)
    {
        $processor = $this->getMockBuilder(AddLogRecordStackTraceProcessor::class)
            ->setMethodsExcept(['disableOnce', '__invoke'])
            ->getMock();
        $processor->method('getStackTraceBeforeMonolog')->willReturn($stackTraceBeforeMonolog);

        $record = $processor($record);
        $this->assertEquals($expectedStack, $record['context']['stack']);
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
    public function testGetStackTraceBeforeMonolog(array $recordArg, array $expected)
    {
        $processor = new AddLogRecordStackTraceProcessor();
        $this->assertEquals($expected, $processor->getStackTraceBeforeMonolog($recordArg));
    }
}
