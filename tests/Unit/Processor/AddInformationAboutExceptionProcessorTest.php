<?php

declare(strict_types=1);

namespace YaPro\MonologExt\Tests\Unit\Processor;

use Exception;
use Monolog\Logger;
use YaPro\MonologExt\ExtraException;
use YaPro\MonologExt\Processor\AddInformationAboutExceptionProcessor;
use PHPUnit\Framework\TestCase;

class AddInformationAboutExceptionProcessorTest extends TestCase
{
    /**
     * @const - заготовка для "мока" dumpException()
     */
    private const DUMP_EXCEPTION_MOCK = ['message' => 'dumpException Mock'];

    /**
     * @const - заготовка для "мока" dump()
     */
    private const DUMP_VAR_MOCK = 'dumpException Mock';

    /**
     * @return \array[][]
     */
    public function invokeProvider(): array
    {
        return [
            [   // 1 кейс - level соответствует минимальному уровню, есть исключение
                'input' => [
                    'record' => [
                        'level' => Logger::WARNING,
                        'context' => ['foo' => 'bar'],
                    ],
                    'getExceptionReturn' => new Exception(),
                ],
                'expected' => [
                    'level' => Logger::WARNING,
                    'context' => array_merge(self::DUMP_EXCEPTION_MOCK, ['foo' => 'bar']),
                ],
            ],
            [   // 2 кейс - level НИЖЕ минимального уровня, есть исключение
                'input' => [
                    'record' => [
                        'level' => Logger::INFO,
                        'context' => ['foo' => 'bar'],
                    ],
                    'getExceptionReturn' => new Exception(),
                ],
                'expected' => [
                    'level' => Logger::INFO,
                    'context' => ['foo' => 'bar'],
                ],
            ],
            [   // 3 кейс - установлен флаг игнорировать исключение
                'input' => [
                    'record' => [
                        'level' => Logger::WARNING,
                        'context' => ['foo' => 'bar', AddInformationAboutExceptionProcessor::DISABLE => true],
                    ],
                    'getExceptionReturn' => new Exception(),
                ],
                'expected' => [
                    'level' => Logger::WARNING,
                    'context' => ['foo' => 'bar', AddInformationAboutExceptionProcessor::DISABLE => true],
                ],
            ],
            [   // 4 кейс - исключение отсутствует
                'input' => [
                    'record' => [
                        'level' => Logger::WARNING,
                        'context' => ['foo' => 'bar'],
                    ],
                    'getExceptionReturn' => null,
                ],
                'expected' => [
                    'level' => Logger::WARNING,
                    'context' => ['foo' => 'bar'],
                ],
            ],
            [   // 5 кейс - исключение ExtraException с доп. данными
                'input' => [
                    'record' => [
                        'level' => Logger::WARNING,
                        'context' => ['foo' => 'bar'],
                    ],
                    'getExceptionReturn' => (new ExtraException())->setData(self::DUMP_VAR_MOCK),
                ],
                'expected' => [
                    'level' => Logger::WARNING,
                    'context' => array_merge(self::DUMP_EXCEPTION_MOCK, ['foo' => 'bar']),
                    'extra' => [
                        'data' => self::DUMP_VAR_MOCK,
                    ],
                ],
            ],
        ];
    }

    /**
     * @dataProvider invokeProvider
     */
    public function testInvoke(array $input, array $expected): void
    {
        // создаем частичный "мок" процессора
        $processor = $this->getMockBuilder(AddInformationAboutExceptionProcessor::class)
            ->setConstructorArgs(['logLevel' => 'WARNING'])
            ->setMethodsExcept(['__invoke'])
            ->getMock();

        // "мокаем" все метод кроме тестируемого
        $processor->method('getException')->willReturn($input['getExceptionReturn']);
        $processor->method('dumpException')->willReturn(self::DUMP_EXCEPTION_MOCK);
        $processor->method('dump')->willReturn(self::DUMP_VAR_MOCK);

        $result = $processor($input['record']);

        $this->assertEquals($expected, $result);
    }

    public function getExceptionProvider(): array
    {
        return [
            [   // 1 кейс: Исключение присутствует с ключем 0, вернет эго
                'inputContext' => [
                    '3' => 'foo',
                    0 => new Exception(),
                    1 => 33,
                ],
                'isReturnException' => true,
            ],
            [   // 2 кейс: Исключение присутствует с ключем "exception", вернет эго
                'inputContext' => [
                    'foo',
                    345,
                    'exception' => new Exception(),
                ],
                'isReturnException' => true,
            ],
            [   // 3 кейс: Исключение присутствует с ключем 1, вернет NULL
                'inputContext' => [
                    1 => new Exception(),
                    0 => 33,
                    2 => 'foo',
                ],
                'isReturnException' => false,
            ],
        ];
    }

    /**
     * Проверяем что метод вернет или нет исключение
     *
     * @dataProvider getExceptionProvider
     */
    public function testGetException(array $inputContext, bool $isReturnException): void
    {
        $processor = new AddInformationAboutExceptionProcessor();
        // для метода важно только содержание "context"
        $input = ['context' => $inputContext];

        $this->assertEquals($isReturnException, (bool) $processor->getException($input));
    }
}
