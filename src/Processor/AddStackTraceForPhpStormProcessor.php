<?php

declare(strict_types=1);

namespace YaPro\MonologExt\Processor;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Процессор получает строковое представление стека вызовов ['context']['stack'] пригодное для PhpStorm
 */
class AddStackTraceForPhpStormProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        if (
            // Let`s get a stack trace as string like it doing \Symfony\Component\Debug\ErrorHandler::handleException
            !empty($record['context']['stack']) &&
            is_array($record['context']['stack'])
        ) {
            // it can be deleted if you use symfony/monolog-bundle:
            // https://github.com/symfony/symfony/pull/17168
            // https://github.com/symfony/monolog-bundle/pull/153
            $context = $record['context'];
            $record['extra']['trace'] = $this->getStackTraceForPhpStorm($context['stack']);
            // It's not supported anymore: unset($record['context']['stack']);
            // And this too - see \Monolog\LogRecord::offsetSet:
            // unset($context['stack']);
            // $record['context'] = $context;
        }

        return $record;
    }

    public function getStackTraceForPhpStorm(array $trace): string
    {
        $rtn = '';
        $count = count($trace);
        foreach ($trace as $frame) {
            --$count;
            $rtn .= $this->getFrameToString($frame, $count);
        }

        return $rtn;
    }

    /**
     * Получение строкового представления Обработка элемента stackTrace
     */
    private function getFrameToString(array $frame, int $frameId): string
    {
        $argsAsString = $this->getArgsToString($frame) ?: '';
        $file = '[internal function]';
        $line = '';
        if (array_key_exists('file', $frame)) {
            $file = $frame['file'];
            $line = $frame['line'];
        }
        $class = array_key_exists('class', $frame) ? $frame['class'] : '';
        $type = array_key_exists('type', $frame) ? $frame['type'] : '';
        $function = array_key_exists('function', $frame) ? $frame['function'] : '';
        if (strpos($function, 'call_user_func:{') === 0) {
            $function = substr($function, 0, 14);
        }

        return sprintf(
            "#%s %s(%s): %s%s%s(%s)\n",
            $frameId,
            $file,
            $line,
            $class,
            $type,
            $function,
            $argsAsString
        );
    }

    /**
     * Получение строкового представления Аргументов функции элемента stackTrace
     */
    private function getArgsToString(array $frame): ?string
    {
        if (!isset($frame['args'])) {
            return null;
        }
        $args = [];
        foreach ($frame['args'] as $arg) {
            $argString = $this->getArgToString($arg);
            $args[] = $argString !== null ? $argString : $arg;
        }

        return implode(', ', $args);
    }

    /**
     * Получение строкового представления аргумента функции элемента stackTrace
     *
     * @param mixed $arg
     *
     * @return string|null
     */
    private function getArgToString($arg): ?string
    {
        if (is_string($arg)) {
            return "'$arg'";
        } elseif (is_array($arg)) {
            return 'Array';
        } elseif ($arg === null) {
            return 'NULL';
        } elseif (is_bool($arg)) {
            return ($arg) ? 'true' : 'false';
        } elseif (is_object($arg)) {
            return get_class($arg);
        } elseif (is_resource($arg)) {
            return get_resource_type($arg);
        }

        return null;
    }
}
