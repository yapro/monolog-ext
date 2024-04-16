<?php
declare(strict_types=1);

namespace YaPro\MonologExt\Tests\Unit\WhiteBox\Handler;

use PHPUnit\Framework\TestCase;
use YaPro\MonologExt\Handler\JsonToStdErrHandler;

class JsonToStdErrHandlerTest extends TestCase
{
    public function testGetReducedRecord(): void
    {
        $mock = $this->getMockBuilder(JsonToStdErrHandler::class)
            ->disableOriginalConstructor()
            ->setMethodsExcept(['getReducedRecord', 'getJson', 'isMessageShort'])
            ->getMock();

        // подрезка сообщения:
        $keyName = 'context';
        $record = [
            $keyName => [
                'first' => 'string',
                'second' => 'string' . str_repeat('ю', JsonToStdErrHandler::MAX_RECORD_LENGTH),
                'thirs'  => 'string',
            ],
        ];
        $explanationMessagesLength = 129;
        $expected = [
            $keyName => [
                'first' => 'string',
                'second' => JsonToStdErrHandler::THE_LOG_ENTRY_IS_TOO_LONG_SO_IT_IS_REDUCED . 'string' . str_repeat('ю', JsonToStdErrHandler::MAX_RECORD_LENGTH - $explanationMessagesLength),
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
