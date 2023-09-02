<?php

declare(strict_types=1);

namespace YaPro\MonologExt\Tests\Unit\WhiteBox\Processor;

use Exception;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use YaPro\MonologExt\Processor\AddStackTraceForPhpStormProcessor;

class AddStackTraceForPhpStormProcessorTest extends TestCase
{
    private const EXAMPLE_STACK_TRACE_STRING = 'EXAMPLE_STACK_TRACE_STRING';

    public function invokeProvider(): array
    {
        return [
            [   // 1 кейс - в контексте есть stack массив
                'record' => [
                    'context' => ['stack' => ['bar']],
                ],
                'expected' => [
                    'context' => [],
                    'extra' => ['trace' => self::EXAMPLE_STACK_TRACE_STRING],
                ],
            ],
            [   // 2 кейс - в контексте есть stack НЕ массив
                'record' => [
                    'context' => ['stack' => '[]'],
                ],
                'expected' => [
                    'context' => ['stack' => '[]'],
                ],
            ],
            [   // 3 кейс - в контексте НЕТ stack
                'record' => [
                    'context' => ['foo' => 'bar'],
                ],
                'expected' => [
                    'context' => ['foo' => 'bar'],
                ],
            ],
        ];
    }

    /**
     * @dataProvider invokeProvider
     */
    public function testInvoke(array $record, array $expected)
    {
        $processor = $this->getMockBuilder(AddStackTraceForPhpStormProcessor::class)
            ->setMethodsExcept(['__invoke'])
            ->getMock();
        $processor->method('getStackTraceForPhpStorm')->willReturn(self::EXAMPLE_STACK_TRACE_STRING);

        $this->assertEquals($expected, $processor($record));
    }

    public function getFrameToStringProvider(): array
    {
        return [
            [
                'input' => [
                    'frame' => [
                        'function' => 'handle',
                        'type' => '->',
                        'class' => 'App\Component\Kernel\Kernel',
                        'file' => '/var/www/sources/App/index.php',
                        'line' => 33,
                        'args' => ['p' => true],
                    ],
                    'frameId' => 2,
                ],
                'expectedReturn' => '#2 /var/www/sources/App/index.php(33): App\Component\Kernel\Kernel->handle(true)' . PHP_EOL,
            ],
            [
                'input' => [
                    'frame' => [
                        'function' => 'handle',
                        'type' => '->',
                        'class' => 'App\Component\Kernel\Kernel',
                        'file' => '/var/www/sources/App/Component/Kernel.php',
                        'line' => 196,
                        'args' => ['p' => true, 'y' => 45],
                    ],
                    'frameId' => 1,
                ],
                'expectedReturn' => '#1 /var/www/sources/App/Component/Kernel.php(196): App\Component\Kernel\Kernel->handle(true, 45)' . PHP_EOL,
            ],
        ];
    }

    /**
     * @dataProvider getFrameToStringProvider
     */
    public function testGetFrameToString(array $input, string $expectedReturn): void
    {
        $processor = new AddStackTraceForPhpStormProcessor();

        $class = new ReflectionClass($processor);
        $method = $class->getMethod('getFrameToString');
        $method->setAccessible(true);

        $this->assertEquals(
            $expectedReturn,
            $method->invokeArgs($processor, [$input['frame'], $input['frameId']])
        );
    }

    public function getArgsToStringProvider(): array
    {
        return [
            [
                'input' => [
                    // структура фрейма мнимая, для метода нужен только ['args']
                    'function' => 'handle',
                    'args' => [
                        'exception' => new Exception(),
                    ],
                ],
                'expectedReturn' => 'Exception',
            ],
            [
                'input' => [
                    'function' => 'handle',
                    'args' => [
                        'exception' => new Exception(),
                        'is' => true,
                        'code' => 200,
                    ],
                ],
                'expectedReturn' => 'Exception, true, 200',
            ],
            [
                'input' => [
                    'function' => 'handle',
                ],
                'expectedReturn' => null,
            ],
        ];
    }

    /**
     * @dataProvider getArgsToStringProvider
     */
    public function testGetArgsToString(array $input, ?string $expectedReturn)
    {
        $processor = new AddStackTraceForPhpStormProcessor();

        $class = new ReflectionClass($processor);
        $method = $class->getMethod('getArgsToString');
        $method->setAccessible(true);

        $this->assertEquals(
            $expectedReturn,
            $method->invokeArgs($processor, [$input])
        );
    }
}
