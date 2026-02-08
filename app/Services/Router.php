<?php

namespace FluentMail\App\Services;

class Router
{
    private $namespace = '';

    public function __construct($namespace)
    {
        $this->namespace = $namespace;
    }

    public function route($method, $endpoint, $callback, $permissions = ['manage_options'])
    {
        $endpoint = str_replace('{id}', '(?P<id>[\d]+)', $endpoint);

        register_rest_route($this->namespace, $endpoint, array(
            'methods'  => $method,
            'callback' => function($request) use ($callback) {

                if (is_array($callback) && is_string($callback[0]) && class_exists($callback[0])) {
                    $callback = [new $callback[0], $callback[1]];
                }

                $result = call_user_func($callback, $request);

                if(is_wp_error($result)) {
                    return new \WP_REST_Response([
                        'code' => $result->get_error_code(),
                        'message' => $result->get_error_message(),
                        'data' => $result->get_error_data()
                    ], 422);
                }

                ob_get_clean();

                return rest_ensure_response( $result );
            },
            'permission_callback' => function($request) use ($permissions) {
                if(is_array($permissions)) {
                    if(count($permissions)) {
                        foreach ($permissions as $permission) {
                            if(current_user_can($permission)) {
                                return true;
                            }
                        }
                        return false;
                    }
                    return true;
                }

                return call_user_func($permissions, $request);
            }
        ));

        return $this;
    }

    public function get($endpoint, $callback, $permissions = [])
    {
        $this->route(\WP_REST_Server::READABLE, $endpoint, $callback, $permissions);
        return $this;
    }

    public function post($endpoint, $callback, $permissions = [])
    {
        $this->route(\WP_REST_Server::CREATABLE, $endpoint, $callback, $permissions);
        return $this;
    }
}
