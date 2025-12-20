<?php

declare(strict_types=1);

namespace YaPro\MonologExt\Tests\Unit\WhiteBox\Processor;

use DateTimeImmutable;
use Exception;
use Generator;
use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use Throwable;
use YaPro\Helper\LiberatorTrait;
use YaPro\MonologExt\ExtraException;
use YaPro\MonologExt\Processor\AddInformationAboutExceptionProcessor;
use YaPro\MonologExt\VarHelper;

class AddInformationAboutExceptionProcessorTest extends TestCase
{
    use LiberatorTrait;

    public function invokeProvider(): array
    {
        return [
            [// record level is less than the Processor logLevel - record is not changed
                'record' => [
                    'level' => Logger::DEBUG,
                    'context' => ['foo' => 'bar'],
                ],
                'expected' => [
                    'level' => Logger::DEBUG,
                    'context' => ['foo' => 'bar'],
                ],
            ],
            [// record level is equal to the Processor logLevel, but the processing is disabled - record isn't changed
                'record' => [
                    'level' => Logger::WARNING,
                    'context' => ['foo' => 'bar', AddInformationAboutExceptionProcessor::DISABLE => true,],

                ],
                'expected' => [
                    'level' => Logger::WARNING,
                    'context' => ['foo' => 'bar', AddInformationAboutExceptionProcessor::DISABLE => true,],
                ],
            ],
            [// record level is equal to the Processor logLevel - record is changed
                'record' => [
                    'level' => Logger::WARNING,
                    'context' => ['foo' => 'bar', 'the' => 'zoo',],
                ],
                'expected' => [
                    'level' => Logger::WARNING,
                    'context' => ['foo' => 'bar', 'the' => 'zoo',],
                ],
            ],
            [// record level is greater than the Processor logLevel and record depth level is three - record is changed
                'record' => [
                    'level' => Logger::WARNING,
                    'context' => ['foo' => 'bar', 'the' => 'zoo',],
                ],
                'expected' => [
                    'level' => Logger::WARNING,
                    'context' => ['foo' => 'bar', 'the' => 'zoo',],
                ],
            ],
        ];
    }

    /**
     * @dataProvider invokeProvider
     */
    public function testInvoke(array $record, array $expected): void
    {
        $processor = $this->getMockBuilder(AddInformationAboutExceptionProcessor::class)
            ->setConstructorArgs(['logLevel' => 'WARNING'])
            ->setMethodsExcept(['__invoke'])
            ->getMock();
        $processor->method('handleException')->willReturnArgument(0);
        $logRecord = new LogRecord(new DateTimeImmutable(), 'channel', Level::fromValue($record['level']), 'any message', $record['context']);
        $result = $processor($logRecord);
        $this->assertEquals($expected, [
            'level' => $result->level->value,
            'context' => $result->context,
        ]);
    }

    public function handleExceptionProvider(): array
    {
        return [
            [
                'value' => 'foo',
                'expected' => 'foo',
            ],
            [
                'value' => new Exception('is not used in the test'),
                'expected' => AddInformationAboutExceptionProcessor::THE_MAX_DEPTH_LEVEL_HAS_BEEN_REACHED_MESSAGE,
                'maxDepthLevel' => -1,
            ],
            [
                'value' => new Exception('foo'),
                'expected' => ['foo'],
            ],
            [
                'value' => (new ExtraException('foo'))->setData('bar'),
                'expected' => ['foo', 'extraData' => 'bar'],
                'maxDepthLevel' => 12345,
                'isExtraDataExists' => true,
            ],
            [
                'value' => new Exception(
                    'third',
                    3,
                    new Exception(
                        'second',
                        2,
                        new Exception('first', 1)
                    )
                ),
                'expected' => [
                    'third',
                    'previous' => [
                        'second',
                        'previous' => [
                            'first',
                        ],
                    ],
                ],
            ],
            [
                'value' =>
                    new Exception(
                        'third', 3,
                        new Exception(
                            'second', 2,
                            new Exception('first')
                        )
                    ),
                'expected' => [
                    'third',
                    'previous' => [
                        'second',
                        'previous' => [
                            'first',
                        ],
                    ],
                ]
            ],
        ];
    }

    /**
     * @dataProvider handleExceptionProvider
     */
    public function testHandleException(
        $exception,
        $expected,
        int $maxDepthLevel = 12345,
        bool $isExtraDataExists = false
    ): void {
        $processor = $this->getMockBuilder(AddInformationAboutExceptionProcessor::class)
            ->setMethodsExcept(['handleException'])
            ->getMock();
        $processor->method('isExtraDataExists')->willReturn($isExtraDataExists);

        $varHelper = $this->createMock(VarHelper::class);
        $varHelper->method('dumpException')->willReturnCallback(function (Throwable $exception) {
            return [$exception->getMessage()];
        });
        $varHelper->method('dump')->willReturnCallback(function ($value) {
            return $value;
        });
        $this->setClassPropertyValue($processor, 'varHelper', $varHelper);

        $result = $processor->handleException($exception, $maxDepthLevel);
        $this->assertEquals($expected, $result);
    }

    public function isExtraDataExistsProvider(): Generator
    {
        yield ['exception' => new Exception('foo'), 'expected' => false];
        yield ['exception' => (new ExtraException('foo'))->setData('bar'), 'expected' => true];
    }

    /**
     * @dataProvider isExtraDataExistsProvider
     */
    public function testIsExtraDataExists($exception, $expected): void
    {
        $processor = $this->getMockBuilder(AddInformationAboutExceptionProcessor::class)
            ->setConstructorArgs([])
            ->setMethodsExcept(['__construct', 'isExtraDataExists'])
            ->getMock();

        $result = $processor->isExtraDataExists($exception);
        $this->assertEquals($expected, $result);
    }
}
