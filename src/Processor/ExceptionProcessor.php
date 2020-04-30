<?php

declare(strict_types=1);

namespace YaPro\MonologExt\Processor;

use Monolog\Logger;
use YaPro\MonologExt\ExtraException;
use YaPro\MonologExt\VarHelper;
use Throwable;

/**
 * Дополняет лог-запись данными из исключения (если таковой есть в context-е), несколько простых примеров использования:
 * try {
 *      throw new Exception();
 * } catch (\Exception $e) {
 *      $this->logger->error('My error message', [$e]);
 *      // Если не нужно, чтобы ExceptionProcessor НЕ обрабатывал запись:
 *      $this->logger->error('My error message', [$e, ExceptionProcessor::DISABLE => true ]);
 *      // Не будет работать, если передать исключение вторым аргументом:
 *      $this->logger->error('My error message', ['bar', $e,]);
 *      // но, будет работать если указать ключ exception:
 *      $this->logger->error('My error message', ['foo' => 'bar', 'exception' => $e,]);
 *      throw $e;
 * }.
 */
class ExceptionProcessor
{
    /**
     * @cont - ключ флага отключения процессора
     */
    public const DISABLE = 'disableExceptionProcessor';

    /**
     * @var int уровень log-records которые будут обрабатываться, т.е. records уровнем меньше - обрабатываться не будут.
     */
    private int $logLevel;

    private VarHelper $varHelper;

    public function __construct(string $logLevel = 'INFO')
    {
        $this->logLevel = Logger::toMonologLevel($logLevel);

        // использование статических методов Не приветствуется PHPMD, используем "инстанс"
        $this->varHelper = new VarHelper();
    }

    public function __invoke(array $record): array
    {
        // НЕ достигнут минимальный уровень логирования
        $weakLevel = array_key_exists('level', $record) && $record['level'] < $this->logLevel;
        if ($weakLevel === true) {
            return $record;
        }

        // установлен флаг "игнорировать данный процессор"
        if (isset($record['context'][self::DISABLE])) {
            return $record;
        }

        $exception = $this->getException($record);
        if (!$exception) {
            // исключение не найдено
            return $record;
        }

        if ($exception instanceof ExtraException && $exception->getData()) {
            $record['extra']['data'] = $this->dump($exception->getData());
        }

        // преобразовываем Исключение и экспортируем в "context" записи
        if (isset($record['context'])) {
            $record['context'] = array_merge($record['context'], $this->dumpException($exception));

            return $record;
        }
        $record['context'] = $this->dumpException($exception);

        return $record;
    }

    /**
     * Получение исключения из контекста записи лога
     * - исключение обрабатывается только если находится в ['context']['exception'] или ['context'][0]
     */
    public function getException(array &$record): ?Throwable
    {
        if (isset($record['context']['exception']) && $record['context']['exception'] instanceof Throwable) {
            $exception = $record['context']['exception'];
            unset($record['context']['exception']);

            return $exception;
        }
        if (isset($record['context'][0]) && $record['context'][0] instanceof Throwable) {
            $exception = $record['context'][0];
            unset($record['context'][0]);

            return $exception;
        }

        return null;
    }

    public function dumpException(Throwable $exception): array
    {
        return $this->varHelper->dumpException($exception);
    }

    public function dump($value): string
    {
        return $this->varHelper->dump($value);
    }
}
