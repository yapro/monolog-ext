<?php
declare(strict_types=1);

namespace YaPro\MonologExt\Tests\Unit\WhiteBox\Handler;

use PHPUnit\Framework\TestCase;
use YaPro\Helper\LiberatorTrait;
use YaPro\MonologExt\Handler\JsonToStdErrHandler;
use YaPro\MonologExt\VarHelper;

class JsonToStdErrHandlerTest extends TestCase
{
    use LiberatorTrait;

    public function testGetReducedRecord(): void
    {
        $mock = $this->getMockBuilder(JsonToStdErrHandler::class)
            ->disableOriginalConstructor()
            ->setMethodsExcept(['getReducedRecord', 'getJson', 'isMessageShort'])
            ->getMock();

        $this->setClassPropertyValue($mock, 'varHelper', new VarHelper());

        // подрезка сообщения:
        $keyName = 'context';
        $record = [
            $keyName => [
                'first' => 'string',
                'second' => str_repeat('ю', JsonToStdErrHandler::MAX_RECORD_LENGTH),
                'thirs'  => 'string',
            ],
        ];
        $explanationMessagesLength = 195;
        $expected = [
            $keyName => [
                'first' => 'string',
                'second' => JsonToStdErrHandler::THE_LOG_ENTRY_IS_TOO_LONG_SO_IT_IS_REDUCED . str_repeat('ю', JsonToStdErrHandler::MAX_RECORD_LENGTH - $explanationMessagesLength),
                'thirs'  => JsonToStdErrHandler::THE_LOG_ENTRY_IS_TOO_LONG,
            ],
        ];
        $result = $mock->getReducedRecord($record, $keyName);
        $this->assertEquals($mock->getJson($expected), $result);

        // сообщение не подрезается:
        $keyName = 'context';
        $record = [
            $keyName => [
                'first' => 'string',
                'second' => 'string',
                'thirs'  => 'string',
            ],
        ];
        $expected = [
            $keyName => [
                'first' => 'string',
                'second' => 'string',
                'thirs'  => 'string',
            ],
        ];
        $result = $mock->getReducedRecord($record, $keyName);
        $this->assertEquals($mock->getJson($expected), $result);
    }
}
