<?php

namespace FluentMail\App\Http\Controllers;

use FluentMail\App\App;

abstract class Controller
{
    /**
     * @var \FluentMail\App\Plugin
     */
    protected $app = null;

    /**
     * @var \FluentMail\Includes\Request\Request
     */
    protected $request = null;

    /**
     * @var \FluentMail\Includes\Response\Response
     */
    protected $response = null;

    public function __construct()
    {
        $this->app = App::getInstance();
        $this->request = $this->app['request'];
        $this->response = $this->app['response'];
    }

    public function send($data = null, $code = 200)
    {
        return $this->response->send($data, $code);
    }

    public function sendSuccess($data = null, $code = 200)
    {
        return $this->response->sendSuccess($data, $code);
    }

    public function sendError($data = null, $code = 422)
    {
        return $this->response->sendError($data, $code);
    }

    /*
     * Both failures carry 403.
     *
     * wp_send_json_error() defaults to HTTP 200, so a rejected request used to arrive
     * looking exactly like a successful one: jQuery resolved it, the frontend read the
     * error object as data, and an expired nonce was reported to the user as a green
     * success reading "Security check failed". Every other error path in the plugin already
     * carries a status - sendError() defaults to 422 and the exception handler sends
     * 403/422 - and the frontend is written against that, with two dozen handlers
     * reading responseJSON.data.message on failure. These two were the exception.
     *
     * The status is the only thing that changes: wp_send_json_error() builds the same
     * {success: false, data: {...}} body either way, so nothing reading the response
     * has to change with it.
     */
    public function verify()
    {
        $permission = fluentMailManageCapability();
        if(!current_user_can($permission)) {
            wp_send_json_error([
                'message' => __('You do not have permission to do this.', 'fluent-smtp')
            ], 403);
            die();
        }

        $nonce = $this->request->get('nonce');
        if(!wp_verify_nonce($nonce, FLUENTMAIL)) {
            wp_send_json_error([
                'message' => __('Security check failed. Please reload the page.', 'fluent-smtp')
            ], 403);
            die();
        }

        return true;
    }
}
