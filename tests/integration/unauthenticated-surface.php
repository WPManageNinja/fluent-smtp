<?php

return function () {
    $protectedOptions = [
        'fluentmail-settings',
        '_fluentsmtp_intended_outlook_info',
        '_fluentmail_last_generated_state',
    ];

    /** Hash exact option rows so failures never print settings or OAuth data. */
    $optionFingerprints = function () use ($protectedOptions) {
        global $wpdb;
        $fingerprints = [];
        foreach ($protectedOptions as $name) {
            $row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT option_value, autoload FROM {$wpdb->options} WHERE option_name = %s",
                    $name
                ),
                ARRAY_A
            );
            $fingerprints[$name] = $row
                ? hash('sha256', $row['option_value'] . '|' . $row['autoload'])
                : null;
        }
        return $fingerprints;
    };

    $clearOptionCaches = function () use ($protectedOptions) {
        foreach ($protectedOptions as $name) {
            wp_cache_delete($name, 'options');
        }
        wp_cache_delete('alloptions', 'options');
        wp_cache_delete('notoptions', 'options');
    };

    /** Supply one guarded state inside a rolled-back transaction. */
    $withProtectedState = function (callable $callback) use ($optionFingerprints, $clearOptionCaches) {
        global $wpdb;
        $original = $optionFingerprints();
        $state = FsmtpTest::uniq('outlook-state');
        $wpdb->query('START TRANSACTION');
        update_option('_fluentmail_last_generated_state', $state, false);
        $clearOptionCaches();
        $guarded = $optionFingerprints();

        try {
            FsmtpTest::interceptHttp();
            $callback($state);
            FsmtpTest::assertSame($guarded, $optionFingerprints(), 'OAuth protected-option fingerprints');
            FsmtpTest::assertSame(0, count(FsmtpTest::httpRequests()), 'OAuth callback outbound HTTP count');
        } finally {
            $wpdb->query('ROLLBACK');
            $clearOptionCaches();
            FsmtpTest::assertSame($original, $optionFingerprints(), 'OAuth option rollback fingerprints');
        }
    };

    /** Return the runtime-registered GET endpoint, never a copied callback. */
    $outlookEndpoint = function () {
        static $endpoint;
        if ($endpoint !== null) {
            return $endpoint;
        }

        if (!did_action('rest_api_init')) {
            do_action('rest_api_init', rest_get_server());
        }
        $routes = rest_get_server()->get_routes();
        $registered = isset($routes['/fluent-smtp/outlook_callback'])
            ? $routes['/fluent-smtp/outlook_callback']
            : [];

        foreach ($registered as $candidate) {
            if (!is_array($candidate) || empty($candidate['methods']['GET'])) {
                continue;
            }
            if (!empty($candidate['callback']) && !empty($candidate['permission_callback'])) {
                $endpoint = $candidate;
                return $endpoint;
            }
        }

        throw new RuntimeException('The Outlook callback GET route is not registered at runtime.');
    };

    /** Dispatch the registered REST route with the globals a real GET supplies. */
    $dispatchRest = function (array $params) {
        $request = new WP_REST_Request('GET', '/fluent-smtp/outlook_callback');
        $request->set_query_params($params);
        $oldRequest = $_REQUEST;
        $_REQUEST = $params;
        $handlerProvider = function () {
            return function () {
                throw new FsmtpAjaxExit('Rejected REST probe reached the callback.');
            };
        };
        $handlerFilters = [
            'wp_die_handler',
            'wp_die_ajax_handler',
            'wp_die_json_handler',
            'wp_die_jsonp_handler',
        ];
        foreach ($handlerFilters as $filter) {
            add_filter($filter, $handlerProvider, PHP_INT_MAX);
        }
        try {
            return rest_get_server()->dispatch($request);
        } catch (FsmtpAjaxExit $e) {
            return new WP_REST_Response(['code' => 'fsmtp_test_callback_reached'], 200);
        } finally {
            foreach ($handlerFilters as $filter) {
                remove_filter($filter, $handlerProvider, PHP_INT_MAX);
            }
            $_REQUEST = $oldRequest;
        }
    };

    FsmtpTest::case('Outlook callback is a runtime-registered guarded GET route', function () use ($outlookEndpoint) {
        $endpoint = $outlookEndpoint();
        FsmtpTest::assertSame(
            'FluentMail\\App\\Hooks\\Handlers\\ActionsRegistrar::handleOutlookCallback',
            get_class($endpoint['callback'][0]) . '::' . $endpoint['callback'][1],
            'Outlook REST callback'
        );
        FsmtpTest::assertSame(
            'FluentMail\\App\\Hooks\\Handlers\\ActionsRegistrar::verifyOutlookCallbackState',
            get_class($endpoint['permission_callback'][0]) . '::' . $endpoint['permission_callback'][1],
            'Outlook REST permission callback'
        );
    });

    FsmtpTest::case('Outlook callback rejects an absent state without changing OAuth data', function () use (
        $withProtectedState,
        $dispatchRest
    ) {
        $withProtectedState(function () use ($dispatchRest) {
            wp_set_current_user(0);
            $response = $dispatchRest(['code' => 'suite-code-without-state']);
            FsmtpTest::assert(in_array($response->get_status(), [401, 403], true), 'absent-state REST status was not denied');
            $data = $response->get_data();
            FsmtpTest::assertSame('rest_forbidden', isset($data['code']) ? $data['code'] : null, 'absent-state REST error code');
        });
    });

    FsmtpTest::case('Outlook callback rejects a mismatched state without changing OAuth data', function () use (
        $withProtectedState,
        $dispatchRest
    ) {
        $withProtectedState(function ($state) use ($dispatchRest) {
            wp_set_current_user(0);
            $response = $dispatchRest([
                'state' => $state . '-wrong',
                'code'  => 'suite-code-with-wrong-state',
            ]);
            FsmtpTest::assert(in_array($response->get_status(), [401, 403], true), 'mismatched-state REST status was not denied');
            $data = $response->get_data();
            FsmtpTest::assertSame('rest_forbidden', isset($data['code']) ? $data['code'] : null, 'mismatched-state REST error code');
        });
    });

    FsmtpTest::case('Outlook callback with a wrong code stores no connection or token', function () use (
        $withProtectedState,
        $outlookEndpoint
    ) {
        $withProtectedState(function ($state) use ($outlookEndpoint) {
            wp_set_current_user(0);
            $endpoint = $outlookEndpoint();
            $params = [
                'state' => $state,
                'code'  => 'suite-invalid-authorization-code',
            ];
            $request = new WP_REST_Request('GET', '/fluent-smtp/outlook_callback');
            $request->set_query_params($params);
            $oldRequest = $_REQUEST;
            $_REQUEST = $params;

            FsmtpTest::assertSame(true, (bool)call_user_func($endpoint['permission_callback'], $request), 'matching-state permission result');

            $died = false;
            $body = '';
            $handlerProvider = function () use (&$died, &$body) {
                return function ($message = '', $title = '', $args = []) use (&$died, &$body) {
                    $died = true;
                    $body = is_scalar($message) ? (string)$message : '';
                    throw new FsmtpAjaxExit('REST callback completed through wp_die.');
                };
            };
            $handlerFilters = [
                'wp_die_handler',
                'wp_die_ajax_handler',
                'wp_die_json_handler',
                'wp_die_jsonp_handler',
            ];
            foreach ($handlerFilters as $filter) {
                add_filter($filter, $handlerProvider, PHP_INT_MAX);
            }

            try {
                call_user_func($endpoint['callback'], $request);
            } catch (FsmtpAjaxExit $e) {
                // Expected: the endpoint only renders the supplied code.
            } finally {
                foreach ($handlerFilters as $filter) {
                    remove_filter($filter, $handlerProvider, PHP_INT_MAX);
                }
                $_REQUEST = $oldRequest;
            }

            FsmtpTest::assert($died, 'matching-state callback did not complete through wp_die');
        });
    });

    FsmtpTest::case('Outlook callback renders the Microsoft error and a tenant hint instead of an empty code box', function () use (
        $withProtectedState,
        $outlookEndpoint
    ) {
        $withProtectedState(function ($state) use ($outlookEndpoint) {
            wp_set_current_user(0);
            $endpoint = $outlookEndpoint();
            $description = "The request is not valid for the application's 'userAudience' configuration.";
            // WordPress unsets $_GET['error'] during parse_request, so the
            // handler reads the OAuth error code back from QUERY_STRING.
            $params = [
                'state'             => $state,
                'error_description' => $description,
            ];
            $request = new WP_REST_Request('GET', '/fluent-smtp/outlook_callback');
            $request->set_query_params($params);
            $oldRequest = $_REQUEST;
            $oldQuery = isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : null;
            $_REQUEST = $params;
            $_SERVER['QUERY_STRING'] = http_build_query(['error' => 'invalid_request'] + $params);

            $died = false;
            $body = '';
            $args = [];
            $handlerProvider = function () use (&$died, &$body, &$args) {
                return function ($message = '', $title = '', $dieArgs = []) use (&$died, &$body, &$args) {
                    $died = true;
                    $body = is_scalar($message) || (is_object($message) && method_exists($message, '__toString'))
                        ? (string)$message
                        : '';
                    $args = is_array($dieArgs) ? $dieArgs : [];
                    throw new FsmtpAjaxExit('REST callback completed through wp_die.');
                };
            };
            $handlerFilters = [
                'wp_die_handler',
                'wp_die_ajax_handler',
                'wp_die_json_handler',
                'wp_die_jsonp_handler',
            ];
            foreach ($handlerFilters as $filter) {
                add_filter($filter, $handlerProvider, PHP_INT_MAX);
            }

            try {
                call_user_func($endpoint['callback'], $request);
            } catch (FsmtpAjaxExit $e) {
                // Expected: the endpoint renders the error page.
            } finally {
                foreach ($handlerFilters as $filter) {
                    remove_filter($filter, $handlerProvider, PHP_INT_MAX);
                }
                $_REQUEST = $oldRequest;
                if ($oldQuery === null) {
                    unset($_SERVER['QUERY_STRING']);
                } else {
                    $_SERVER['QUERY_STRING'] = $oldQuery;
                }
            }

            FsmtpTest::assert($died, 'error callback did not complete through wp_die');
            FsmtpTest::assertSame(400, isset($args['response']) ? $args['response'] : null, 'error callback HTTP status');
            FsmtpTest::assert(strpos($body, '<textarea') === false, 'error callback still rendered the access code textarea');
            FsmtpTest::assert(strpos($body, 'invalid_request') !== false, 'error callback did not show the OAuth error code');
            FsmtpTest::assert(strpos($body, esc_html($description)) !== false, 'error callback did not show error_description');
            FsmtpTest::assert(strpos($body, 'consumers') !== false, 'error callback did not hint at the consumers tenant');
        });
    });

    FsmtpTest::case('router runtime inventory contains no anonymous AJAX actions', function () use ($optionFingerprints) {
        global $wp_filter;
        wp_set_current_user(0);
        $before = $optionFingerprints();
        $prefix = 'wp_ajax_nopriv_' . FLUENTMAIL . '-';
        $hooks = array_values(array_filter(array_keys($wp_filter), function ($hook) use ($prefix) {
            return strpos($hook, $prefix) === 0;
        }));
        sort($hooks);

        FsmtpTest::assertSame([], $hooks, 'runtime wp_ajax_nopriv router inventory');
        FsmtpTest::assertSame($before, $optionFingerprints(), 'anonymous AJAX inventory option fingerprints');
    });
};
