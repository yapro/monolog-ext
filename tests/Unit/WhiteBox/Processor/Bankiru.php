<?php

namespace Bankiru\LogContracts\Exception;

use Exception;
use Throwable;

/**
 * @deprecated use \YaPro\MonologExt\ExtraDataExceptionInterface instead
 */
interface ExtraDataExceptionInterface extends Throwable
{
    /**
     * @return mixed
     */
    public function getData();

    /**
     * @param mixed $extraData
     *
     * @return self
     */
    public function setData($extraData);
}

/**
 * @deprecated use \YaPro\MonologExt\ExtraDataException instead
 */
class ExtraDataException extends Exception implements ExtraDataExceptionInterface
{
    /**
     * @var mixed
     */
    private $extraData = null;

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
