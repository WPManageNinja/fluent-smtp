<?php

namespace FluentMail\Includes\Support;

use Exception;

class ValidationException extends Exception
{

    protected $errors = [];

    public function __construct($message = "", $code = 0, ?Exception $previous = null, $errors = [])
    {
        $this->errors = $errors;

        parent::__construct($message, $code, $previous);
    }

    public function errors()
    {
        return $this->errors;
    }
}
