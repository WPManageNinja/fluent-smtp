<?php

namespace FluentMail\Includes\Response;

class Response
{
    protected $app = null;

    public function __construct($app)
    {
        $this->app = $app;
    }

    public function json($data = null, $code = 200)
    {
        wp_send_json($data, $code);
    }

    public function send($data = null, $code = 200)
    {
        wp_send_json($data, $code);
    }

    public function sendSuccess($data = null, $code = null)
    {
        wp_send_json_success($data, $code);
    }

    public function sendError($data = null, $code = null)
    {
        wp_send_json_error($data, $code);
    }
}
