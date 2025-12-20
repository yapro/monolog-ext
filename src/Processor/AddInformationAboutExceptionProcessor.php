<?php

declare(strict_types=1);

namespace YaPro\MonologExt\Processor;

use Monolog\Logger;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use YaPro\MonologExt\ExtraDataExceptionInterface;
use YaPro\MonologExt\VarHelper;
use Throwable;

/**
 * Дополняет лог-запись данными из исключения (если таковой есть в context-е), несколько простых примеров использования:
 * try {
 *      throw new Exception();
 * } catch (\Exception $e) {
 *      $this->logger->error('My error message', [$e]);
 *      $this->logger->error('My error message', ['foo' => 'bar', 'exception' => $e]);
 *      // Если нужно, чтобы данный Processor НЕ обрабатывал запись:
 *      $this->logger->error('My error message', [$e, AddInformationAboutExceptionProcessor::DISABLE => true]);
 *      // Если нужно, чтобы данный Processor обработал запись с исключениями только на указанную глубину вложенности:
 *      $this->logger->error('My error message', [$e, AddInformationAboutExceptionProcessor::DEPTH_LEVEL => 3]);
 *      throw $e;
 * }.
 */
class AddInformationAboutExceptionProcessor implements ProcessorInterface
{
    /**
     * @cont - ключ флага отключения процессора
     */
    public const DISABLE = 'disableAddInformationAboutExceptionProcessor';

    /**
     * @cont - ключ флага максимальной глубинны вложенности исключений при экспорте
     */
    public const DEPTH_LEVEL = 'depthLevelAddInformationAboutExceptionProcessor';
    public const THE_MAX_DEPTH_LEVEL_HAS_BEEN_REACHED_MESSAGE = 'The max depth level has been reached';

    private int $logLevel;
    private int $maxDepthLevel;
    private VarHelper $varHelper;

    /**
     * @param string $logLevel - уровень log-records, которые будут залогированы
     * @param int $maxDepthLevel - максимальный уровень вложенности исключений при экспорте
     */
    public function __construct(string $logLevel = 'DEBUG', int $maxDepthLevel = 100500)
    {
        $this->logLevel = Logger::toMonologLevel($logLevel)->value;
        $this->maxDepthLevel = $maxDepthLevel;
        // использование статических методов Не приветствуется PHPMD, используем "инстанс"
        $this->varHelper = new VarHelper();
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        // НЕ достигнут минимальный уровень логирования
        if ($record->level < $this->logLevel) {
            return $record;
        }

        // установлен флаг "игнорировать данный процессор"
        if (isset($record['context'][self::DISABLE])) {
            return $record;
        }
        $maxDepthLevel = $record['context'][self::DEPTH_LEVEL] ?? $this->maxDepthLevel;
        foreach ($record['context'] as $key => $value) {
            $record['context'][$key] = $this->handleException($value, $maxDepthLevel);
        }

        return $record;
    }

    /**
     * @internal private
     */
    public function handleException($exception, int $maxDepthLevel, int $level = 0)
    {
        if (!$exception instanceof Throwable) {
            return $exception;
        }
        if ($level > $maxDepthLevel) {
            return self::THE_MAX_DEPTH_LEVEL_HAS_BEEN_REACHED_MESSAGE;
        }
        $result = $this->varHelper->dumpException($exception);
        if ($this->isExtraDataExists($exception)) {
            $result['extraData'] = $exception->getData();
        }
        if ($exception = $exception->getPrevious()) {
            $result['previous'] = $this->handleException($exception, $maxDepthLevel, ++$level);
        }

        return $result;
    }

    public function isExtraDataExists($exception): bool
    {
        if ($exception instanceof ExtraDataExceptionInterface) {
            return true;
        }

        return false;
    }
}
