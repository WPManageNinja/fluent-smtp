<?php

namespace FluentMail\Includes\Support;

use Exception;

class ValidationException extends Exception
{
    public function __construct($message = "", $code = 0 , Exception $previous = NULL, $errors = [])
    {
        $this->errors = $errors;

        parent::__construct($message, $code, $previous);
    }

    public function errors()
    {
        return $this->errors;
    }
}
