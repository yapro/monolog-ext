<?php

namespace Bankiru\LogContracts\Exception;

use Exception;
use Throwable;

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
