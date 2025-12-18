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

    public function providerFindMaxDumpLevel(): Generator
    {
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
                'second-3' => 'привет',
                'second-4' => [
                    'third-3' => 12345,
                    'third-4' => true,
                    'third-5' => $object,
                ],
                'second-5' => 'мир',
            ],
        ];
        yield [
            'record' => $record,
            'maxRecordLength' => $this->getLength($record) + 81, // todo такая разница в количестве символов связана с тем, что CliDumper делает слишком много пробелов, поэтому нужно написать свой Dumper - JsonDumper
            'expectedMaxDumpLevel' => 2,
        ];
        yield [
            'record' => $record,
            'maxRecordLength' => $this->getLength($record) + 82, // todo такая разница в количестве символов связана с тем, что CliDumper делает слишком много пробелов, поэтому нужно написать свой Dumper - JsonDumper
            'expectedMaxDumpLevel' => 3,
        ];
    }

    private function getLength(array $value): int
    {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR );
        return mb_strlen($json);
    }

    /**
     * @dataProvider providerFindMaxDumpLevel
     */
    public function testFindMaxDumpLevel(array $record, int $maxRecordLength, int $expectedMaxDumpLevel): void
    {
        $rat = new WiseHandler();
        $this->setClassPropertyValue($rat, 'maxRecordLength', $maxRecordLength);

        $result = $rat->findMaxDumpLevel($record);
        $this->assertEquals($expectedMaxDumpLevel, $result);
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

    public function providerGetMessage(): Generator
    {
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
        yield [
            'record' => $record,
            'maxRecordLength' => 174,
            'expected' => '{"first-1":{"second-1":"string","second-2":"[ …2]"},"first-2":{"second-3":"string","second-4":"[ …3]","second-5":"too big"}}',
        ];
        yield [
            'record' => $record,
            'maxRecordLength' => 175,
            'expected' => '{"first-1":{"second-1":"string","second-2":{"third-1":"string","third-2":"[ …1]"}},"first-2":{"second-3":"string","second-4":{"third-3":"12345","third-4":"true","third-5":"{#274 …2}"},"second-5":"string"}}',
        ];
    }

    /**
     * @dataProvider providerGetMessage
     */
    public function testGetMessage(array $record, int $maxRecordLength, string $expected): void
    {
        $rat = new WiseHandler();
        $this->setClassPropertyValue($rat, 'maxRecordLength', $maxRecordLength);
        $result = $rat->getMessage($record);
        //$this->assertSame($expected, $result);
        $this->assertJsonSame($expected, $result);
    }

    private function assertJsonSame(string $jsonExpected, string $jsonResult)
    {
        $arrayExpected = json_decode($jsonExpected, true, 512, JSON_THROW_ON_ERROR);
        $arrayResult = json_decode($jsonResult, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(
            $arrayExpected,
            $arrayResult,
        );
    }

    public function providerDump(): Generator
    {
        // тесты на типы данных - во что они будут сериализованы
        yield [
            'value' => 'string',
            'expected' => 'string',
            'level' => 0,
        ];
        yield [
            'value' => 12345,
            'expected' => '12345',
            'level' => 0,
        ];
        yield [
            'value' => 0,
            'expected' => '0',
            'level' => 0,
        ];
        yield [
            'value' => 0.12345,
            'expected' => '0.12345',
            'level' => 0,
        ];
        yield [
            'value' => 1.2345,
            'expected' => '1.2345',
            'level' => 0,
        ];
        yield [
            'value' => true,
            'expected' => 'true',
            'level' => 0,
        ];
        yield [
            'value' => false,
            'expected' => 'false',
            'level' => 0,
        ];
        yield [
            'value' => ['string'],
            'expected' => '[ …1]',
            'level' => 0,
        ];
        $object = new stdClass();
        $object->foo = new stdClass();
        $object->foo->baz = new stdClass();
        $object->bar = new stdClass();
        $object->bar->baz = new stdClass();
        yield [ // 8
            'value' => $object,
            'expected' => '{#262 …2}',
            'level' => 0,
        ];
        yield [ // 9
            'value' => $object,
            'expected' => '{#262   +"foo": {#261 …1}   +"bar": {#259 …1} }',
            'level' => 1,
        ];
        yield [
            'value' => $object,
            'expected' => '{#262   +"foo": {#261     +"baz": {#260}   }   +"bar": {#259     +"baz": {#258}   } }',
            'level' => 2,
        ];
        $value = [
            'first-1' => 'string',
            'first-2' => 12345,
            'first-3' => 1.2345,
            'first-4' => true,
            'first-5' => ['string'],
            'first-6' => $object,
        ];
        yield [
            'value' => $value,
            'expected' => '[ …6]',
            'level' => 0,
        ];
        yield [
            'value' => $value,
            'expected' => '[   "first-1" => "string"   "first-2" => 12345   "first-3" => 1.2345   "first-4" => true   "first-5" => [ …1]   "first-6" => {#262 …2} ]',
            'level' => 1,
        ];
        yield [
            'value' => $value,
            'expected' => '[   "first-1" => "string"   "first-2" => 12345   "first-3" => 1.2345   "first-4" => true   "first-5" => [     "string"   ]   "first-6" => {#262     +"foo": {#261 …1}     +"bar": {#259 …1}   } ]',
            'level' => 2,
        ];
        // тест на глубину - во что будут сериализованы многомерные массивы:
        $value = [
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
        yield [
            'value' => $value,
            'expected' => '[   "first-1" => [ …2]   "first-2" => [ …3] ]',
            'level' => 1,
        ];
        yield [
            'value' => $value,
            'expected' => '[   "first-1" => [     "second-1" => "string"     "second-2" => [ …2]   ]   "first-2" => [     "second-3" => "string"     "second-4" => [ …3]     "second-5" => "string"   ] ]',
            'level' => 2,
        ];
    }

    /**
     * @dataProvider providerDump
     */
    public function testTheDump($value, string $expected, int $level = 0): void
    {
        $rat = new WiseHandler();
        $result = $rat->dump($value, $level);
        $this->assertSame($expected, $result);
    }

    public function testDumpRecordDataOnTheLevel(): void
    {
        $rat = new WiseHandler();

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
                        'third-5' => new stdClass(),
                    ],
                    'second-5' => 12345,
                ],
        ];
        $expected = [
                'first-1' => [
                    'second-1' => 'string',
                    'second-2' => '[ …2]',
                ],
                'first-2' => [
                    'second-3' => 'string',
                    'second-4' => '[ …3]',
                    'second-5' => '12345',
                ],
        ];

        $result = $rat->dumpRecordDataOnTheLevel($record, 2);
        $this->assertSame($expected, $result);
    }

    public function testReduceRecordDataOnTheLevel(): void
    {

        $record = [
            'first-1' => [
                'second-1' => str_repeat('ю', 100),
                'second-2' => str_repeat('ю', 100),
            ],
            'first-2' => [
                'second-3' => str_repeat('ю', 100),
                'second-4' => str_repeat('ю', 100),
                'second-5' => str_repeat('ю', 100),
            ],
        ];
        $expected = [
            'first-1' => [
                'second-1' => str_repeat('ю', 100),
                'second-2' => str_repeat('ю', 100),
            ],
            'first-2' => [
                'second-3' => str_repeat('ю', 100),
                'second-4' => str_repeat('ю', 100),
                'second-5' => WiseHandler::THE_VALUE_IS_TOO_BIG,
            ],
        ];

        $rat = new WiseHandler();
        $this->setClassPropertyValue($rat, 'maxRecordLength', 400);
        $maxLevel = 2; // мы знаем, что значения на этом уровене делают $record слишком большим для записи, поэтому будем уменьшать значения ключей на нем

        $result = $rat->reduceRecordDataOnTheLevel($record, 2, 1, $record);
        $this->assertSame($expected, $result);
    }
}
