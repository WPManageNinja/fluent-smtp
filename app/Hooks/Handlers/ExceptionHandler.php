<?php

namespace FluentMail\App\Hooks\Handlers;

class ExceptionHandler
{
    protected $handlers = [
        'FluentMail\Includes\Support\ForbiddenException'   => 'handleForbiddenException',
        'FluentMail\Includes\Support\ValidationException'  => 'handleValidationException'
    ];

    public function handle($e)
    {
        foreach ($this->handlers as $key => $value) {
            if ($e instanceof $key) {
                return $this->{$value}($e);
            }
        }
    }

    public function handleForbiddenException($e)
    {
        wp_send_json_error([
            'message' => $e->getMessage()
        ], $e->getCode() ?: 403);
    }

    public function handleValidationException($e)
    {
        wp_send_json_error([
            'message' => $e->getMessage(),
            'errors'  => $e->errors()
        ], $e->getCode() ?: 422);
    }
}
