<?php

declare(strict_types=1);

namespace YaPro\MonologExt;

use Exception;
use Throwable;

/**
 * Исключение позволяет передавать в свойстве 'extraData' дополнительные данные любого типа. Это очень удобно тем, что
 * такое исключение переданное в Logger будет обработано Logger-процессором AddInformationAboutExceptionProcessor
 * который добавит в log-record указанные 'extraData'. Вот пример использования:
 * try {
 *      throw new ExtraDataException('Exception message', $mixedTypeValue);
 * } catch (\Exception $e) {
 *      $this->logger->error('My error message', [$e]);
 *      throw $e;
 * }.
 */
class ExtraDataException extends Exception implements ExtraDataExceptionInterface
{
    private $extraData = null;

    public function __construct(string $message = '', $extraData = null, Throwable $previous = null, $code = 0)
    {
        // если понадобится использовать $code чаще чем $previous, то сделаем параметры mixed, а тут проверим и
        // поменяем местами переменные, чтобы правильно их передать в parent
        parent::__construct($message, $code, $previous);
        $this->extraData = $extraData;
    }

    public function getData()
    {
        return $this->extraData;
    }

    public function setData($extraData): self
    {
        $this->extraData = $extraData;

        return $this;
    }
}
