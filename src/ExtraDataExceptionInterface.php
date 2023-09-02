<?php

namespace YaPro\MonologExt;

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
