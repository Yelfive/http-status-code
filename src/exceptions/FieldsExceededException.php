<?php

/**
 * @author Felix Huang <yelfivehuang@gmail.com>
 * @date 2017-09-11
 */

namespace fk\http\exceptions;

use Throwable;

class FieldsExceededException extends \Exception
{

    public function __construct($message = "", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

}