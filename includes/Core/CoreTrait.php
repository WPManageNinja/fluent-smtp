<?php

namespace FluentMail\Includes\Core;

use FluentMail\Includes\Support\ForbiddenException;

trait CoreTrait
{
    public function get($action, $handler, $isAdmin = true)
    {
        $action = $this->getAjaxAction($action, 'get', $isAdmin);

        return add_action($action, $this->parseAjaxHandler($handler));        
    }

    public function getPublic($action, $handler)
    {
        $this->get($action, $handler, false);
    }

    public function post($action, $handler, $isAdmin = true)
    {
        $action = $this->getAjaxAction($action, 'post', $isAdmin);

        return add_action($action, function() use ($handler) {
            try {
                $slug = FLUENTMAIL;

                if (check_ajax_referer($slug, 'nonce', false)) {
                    $method = $this->parseAjaxHandler($handler);
                    return $method();
                }

                throw new ForbiddenException('Forbidden!', 401);
                
            } catch (ForbiddenException $e) {
                return $this->docustomAction('handle_exception', $e);
            }
        });
    }

    public function postPublic($action, $handler)
    {
        $this->post($action, $handler, false);
    }

    public function getAjaxAction($action, $method, $isAdmin)
    {
        $context = $isAdmin ? 'wp_ajax_' : 'wp_ajax_nopriv_';
        $action = $action == '/' ? $action : ltrim($action, '/');
        return $context.$this->hook($method.'-'.$action);
    }

    public function hook($hook)
    {
        return FLUENTMAIL . '-' . $hook;
    }

    public function parseAjaxHandler($handler)
    {
        if (!$handler) return;

        if (is_string($handler)) {
            $handler = $this->controllerNamespace . '\\' . $handler;
        } else if (is_array($handler)) {
            list($class, $method) = $handler;
            if (is_string($class)) {
                $handler = $this->controllerNamespace . '\\' . $class . '::' . $method;
            }
        }

        return function() use ($handler) {
            return $this->call($handler);
        };
    }

    public function addAction($action, $handler, $priority = 10, $numOfArgs = 1)
    {
        return add_action(
            $action,
            $this->parseHookHandler($handler),
            $priority,
            $numOfArgs
        );
    }

    public function addCustomAction($action, $handler, $priority = 10, $numOfArgs = 1)
    {
        return $this->addAction($this->hook($action), $handler, $priority, $numOfArgs);
    }

    public function doAction()
    {
        return call_user_func_array('do_action', func_get_args());
    }

    public function doCustomAction()
    {
        $args = func_get_args();
        $args[0] = $this->hook($args[0]);
        return call_user_func_array('do_action', $args);
    }

    public function addFilter($action, $handler, $priority = 10, $numOfArgs = 1)
    {
        return add_filter(
            $action,
            $this->parseHookHandler($handler),
            $priority,
            $numOfArgs
        );
    }

    public function addCustomFilter($action, $handler, $priority = 10, $numOfArgs = 1)
    {
        return $this->addFilter($this->hook($action), $handler, $priority, $numOfArgs);
    }

    public function applyFilters()
    {
        return call_user_func_array('apply_filters', func_get_args());
    }

    public function applyCustomFilters()
    {
        $args = func_get_args();
        $args[0] = $this->hook($args[0]);
        return call_user_func_array('apply_filters', $args);
    }

    public function parseHookHandler($handler)
    {
        if (is_string($handler)) {
            list($class, $method) = preg_split('/::|@/', $handler);

            if ($this->hasNamespace($handler)) {
                $class = $this->make($class);
            } else {
                $class = $this->make($this->handlerNamespace . '\\' . $class);
            }
            return [$class, $method];

        } else if (is_array($handler)) {
            list($class, $method) = $handler;
            if (is_string($class)) {
                if ($this->hasNamespace($handler)) {
                    $class = $this->make($class);
                } else {
                    $class = $this->make($this->handlerNamespace . '\\' . $class);
                }
            }

            return [$class, $method];
        }

        return $handler;
    }

    public function hasNamespace($handler)
    {
        $parts = explode('\\', $handler);
        return count($parts) > 1;
    }
}
