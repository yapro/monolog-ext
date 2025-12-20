<?php
declare(strict_types=1);

namespace YaPro\MonologExt\Tests\Unit\WhiteBox\Handler;

use Closure;
use DateTimeImmutable;
use Generator;
use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use stdClass;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use YaPro\Helper\LiberatorTrait;
use YaPro\MonologExt\Handler\WiseHandler;
use YaPro\MonologExt\VarHelper;

class WiseHandlerTest extends TestCase
{
    use LiberatorTrait;

    private function getLength(array $value): int
    {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR );
        return mb_strlen($json);
    }

    public function testWrite(): void
    {
        // проверка дубля записи (когда хендлер ошибок пишет ошикбу + хендрел шатдауна ее дублирует)
        $mock = $this->getMockBuilder(WiseHandler::class)
            ->disableOriginalConstructor()
            ->setMethodsExcept(['write'])
            ->getMock();
        $mock->expects($this->exactly(1))->method('writeToStdErr');
        $record = new LogRecord(new DateTimeImmutable(), 'channel', Level::fromName(LogLevel::INFO), 'message');
        $mock->write($record);
        $mock->write($record);

        // проверка: после дубля сообщения нормально пишутся
        $mock = $this->getMockBuilder(WiseHandler::class)
            ->disableOriginalConstructor()
            ->setMethodsExcept(['write'])
            ->getMock();
        $mock->expects($this->exactly(2))->method('writeToStdErr');
        // За счет повторения 2х сообщений и должно получится 2 вызова writeToStdErr, а не 3:
        $mock->method('getMessage')->willReturn(
            'LogRecord 1',
            'LogRecord 1', // этот не будет писаться в writeToStdErr
            'LogRecord 2'
        );
        $mock->write(new LogRecord(new DateTimeImmutable(), 'channel', Level::fromName(LogLevel::INFO), 'any message'));
        $mock->write(new LogRecord(new DateTimeImmutable(), 'channel', Level::fromName(LogLevel::INFO), 'any message'));
        $mock->write(new LogRecord(new DateTimeImmutable(), 'channel', Level::fromName(LogLevel::INFO), 'any message'));
    }

    public function scalarProvider(): array
    {
        return [
            'int' => [1, 1],
            'float' => [1.5, 1.5],
            'string' => ['abc', 'abc'],
            'bool' => [true, true],
            'null' => [null, null],
        ];
    }
    
    /**
     * @dataProvider scalarProvider
     */
    public function testScalarValues(mixed $input, mixed $expected): void
    {
        $handler = new WiseHandler();
        $this->assertSame(
            $expected,
            $handler->dumpToScalarArray($input)
        );
    }

    public function arrayProvider(): array
    {
        return [
            'simple array' => [
                ['a' => 1, 'b' => 2],
                ['a' => 1, 'b' => 2],
            ],
            'nested array' => [
                ['a' => ['b' => true]],
                ['a' => ['b' => true]],
            ],
        ];
    }
    
    /**
     * @dataProvider arrayProvider
     */
    public function testArrays(mixed $input, mixed $expected): void
    {
        $handler = new WiseHandler();
        $this->assertSame(
            $expected,
            $handler->dumpToScalarArray($input)
        );
    }

    public function testObjectDump(): void
    {
        $handler = new WiseHandler();

        $obj = new class {
            public string $a = 'x';
            private int $b = 10;
        };

        $dump = $handler->dumpToScalarArray($obj);

        $this->assertSame('object', $dump['__type']);
        $this->assertArrayHasKey('__class', $dump);
        $this->assertSame('x', $dump['a']);
        $this->assertSame(10, $dump['b']);



        $object = new stdClass();
        $object->foo = new stdClass();
        $object->foo->baz = new stdClass();
        $object->bar = new stdClass();
        $object->bar->baz = new stdClass();
        $record = [
            'first-1' => [
                'second-1' => 'string',
                'second-2' => [
                    'third-1' => 'string',
                    'third-2' => ['string'],
                ],
            ],
            'first-2' => [
                'second-3' => 'string',
                'second-4' => [
                    'third-3' => 12345,
                    'third-4' => true,
                    'third-5' => $object,
                ],
                'second-5' => 'string',
            ],
        ];

        $dump = $handler->dumpToScalarArray($record);
        $json = json_encode($dump, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR );
        $this->assertSame('{
    "first-1": {
        "second-1": "string",
        "second-2": {
            "third-1": "string",
            "third-2": [
                "string"
            ]
        }
    },
    "first-2": {
        "second-3": "string",
        "second-4": {
            "third-3": 12345,
            "third-4": true,
            "third-5": {
                "__type": "object",
                "__class": "stdClass",
                "foo": {
                    "__type": "object",
                    "__class": "stdClass",
                    "baz": {
                        "__type": "object",
                        "__class": "stdClass"
                    }
                },
                "bar": {
                    "__type": "object",
                    "__class": "stdClass",
                    "baz": {
                        "__type": "object",
                        "__class": "stdClass"
                    }
                }
            }
        },
        "second-5": "string"
    }
}', $json);
    }

    public function testRecursion(): void
    {
        $handler = new WiseHandler();
        
        $obj = new stdClass();
        $obj->self = $obj;

        $dump = $handler->dumpToScalarArray($obj);

        $this->assertSame(
            '**RECURSION(stdClass)**',
            $dump['self']
        );
    }

    public function testMaxDepth(): void
    {
        $handler = new WiseHandler();
        
        $data = ['a' => ['b' => ['c' => ['d' => 1]]]];

        $dump = $handler->dumpToScalarArray($data, 2);

        $this->assertSame(
            '**MAX_DEPTH**',
            $dump['a']['b']['c']
        );
    }

    public function normalizeKeyProvider(): array
    {
        return [
            'int key' => [1, 1],
            'string key' => ['a', 'a'],
            'numeric string' => ['123', '123'],
        ];
    }

    /**
     * @dataProvider normalizeKeyProvider
     */
    public function testNormalizeKey(mixed $key, string|int $expected): void
    {
        $handler = new WiseHandler();
        
        $this->assertSame(
            $expected,
            $handler->normalizeKey($key)
        );
    }

    public function normalizeObjectKeyProvider(): array
    {
        return [
            'public property' => ['prop', 'prop'],
            'protected property' => ["\0*\0prop", 'prop'],
            'private property' => ["\0Class\0prop", 'prop'],
        ];
    }
    
    /**
     * @dataProvider normalizeObjectKeyProvider
     */
    public function testNormalizeObjectKey(string $key, string $expected): void
    {
        $handler = new WiseHandler();
        
        $this->assertSame(
            $expected,
            $handler->normalizeObjectKey($key)
        );
    }
}
