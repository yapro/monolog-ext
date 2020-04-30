<?php

declare(strict_types=1);

namespace YaPro\MonologExt;

use Exception;
use Throwable;

/**
 * Исключение позволяет передавать в свойстве 'extraData' дополнительные данные любого типа. Это очень удобно тем, что
 * такой исключение переданный в Logger будет обработан Logger-процессором Logger\Processor\ExceptionProcessor
 * который скажет Logger-у логировать 'extraData'. Вот 2 простых примера как пользоваться ExtraException-ом:
 * try {
 *      throw (new ExtraException())->setData(mixed);
 * } catch (\Exception $e) {
 *      $this->logger->error('My error message', [$e]);
 *      $this->logger->error('My error message', ['exception' => $e,]);
 *      throw $e;
 * }.
 */
class ExtraException extends Exception
{
    /**
     * @var mixed
     */
    private $extraData;

    public function __construct($message = '', Throwable $previous = null, $code = 0)
    {
        // если понадобится использовать $code чаще чем $previous, то сделаем параметры mixed, а тут проверим и
        // поменяем местами переменные, чтобы правильно их передать в parent
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return mixed
     */
    public function getData()
    {
        return $this->extraData;
    }

    /**
     * @param mixed $extraData
     *
     * @return self
     */
    public function setData($extraData): self
    {
        $this->extraData = $extraData;

        return $this;
    }
}
