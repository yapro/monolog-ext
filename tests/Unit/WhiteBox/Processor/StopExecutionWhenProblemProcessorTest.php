<?php

declare(strict_types=1);

namespace YaPro\MonologExt\Tests\Unit\WhiteBox\Processor;

use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use YaPro\MonologExt\Processor\StopExecutionWhenProblemProcessor;

/**
 * Тестирование \YaPro\MonologExt\Processor\StopExecutionWhenProblemProcessor
 */
class StopExecutionWhenProblemProcessorTest extends TestCase
{
    public function invokeProvider(): array
    {
        return [
            [
                'isProcessReturn' => true,  // что возвращает isProcess()
                'expectedIsExit' => true,   // ожидается завершение скрипта
            ],
            [
                'isProcessReturn' => false,
                'expectedIsExit' => false,
            ],
        ];
    }

    /**
     * @dataProvider invokeProvider
     */
    public function testInvoke(bool $isProcessReturn, bool $expectedIsExit): void
    {
        $processor = $this->getMockBuilder(StopExecutionWhenProblemProcessor::class)
            ->setMethodsExcept(['__invoke'])
            ->getMock();
        // "мокаем" метод handler()
        $processor->method('handler')->willReturn(null);
        // Устанавливаем возврат из условий кейса
        $processor->method('isProcess')->willReturn($isProcessReturn);

        if ($expectedIsExit) {
            $processor->expects($this->once())->method('handler');
            $processor([]);

            return;
        }
        $processor->expects($this->never())->method('handler');
        $processor([]);
    }

    public function isProcessProvider(): array
    {
        return [
            [
                'record' => ['level' => Logger::WARNING],  // запись лога
                'isDisableOnce' => false,   // вызывать ли disableOnce()
                'expectedReturn' => true,   // ожидается завершение скрипта
            ],
            [
                'record' => ['level' => Logger::INFO],
                'isDisableOnce' => false,
                'expectedReturn' => false,
            ],
            [
                'record' => ['level' => Logger::WARNING],
                'isDisableOnce' => true,
                'expectedReturn' => false,
            ],
        ];
    }

    /**
     * @dataProvider isProcessProvider
     */
    public function testIsProcess(array $record, bool $isDisableOnce, bool $expectedReturn): void
    {
        $processor = $this->getMockBuilder(StopExecutionWhenProblemProcessor::class)
            ->setMethodsExcept(['isProcess'])
            ->getMock();

        if ($isDisableOnce) {
            StopExecutionWhenProblemProcessor::disableOnce();
        }

        $this->assertEquals($expectedReturn, $processor->isProcess($record));
    }
}
