<?php

namespace FluentMail\App\Http\Controllers;

use Exception;
use FluentMail\App\Models\Settings;
use FluentMail\Includes\Request\Request;
use FluentMail\Includes\Support\ValidationException;
use FluentMail\App\Services\Mailer\Providers\Factory;

class SettingsController extends Controller
{
    public function index(Settings $settings)
    {
        $this->verify();

        try {
            $settings = $settings->get();

            return $this->sendSuccess([
                'settings' => $settings
            ]);

        } catch (Exception $e) {
            return $this->sendError([
                'message' => $e->getMessage()
            ], $e->getCode());
        }
    }

    public function validate(Request $request, Settings $settings, Factory $factory)
    {
        $this->verify();

        try {
            $data = $request->except(['action', 'nonce']);

            $provider = $factory->make($data['provider']['key']);

            $provider->validateBasicInformation($data);

            $this->sendSuccess();

        } catch (ValidationException $e) {
            $this->sendError($e->errors(), $e->getCode());
        }
    }

    public function store(Request $request, Settings $settings, Factory $factory)
    {
        $this->verify();

        try {
            $data = $request->except(['action', 'nonce']);

            $provider = $factory->make($data['connection']['provider']);

            $connection = $data['connection'];
            $this->validateConnection($provider, $connection);
            $provider->checkConnection($connection);

            $data['valid_senders'] =  $provider->getValidSenders($connection);

            $settings->store($data);

            return $this->sendSuccess([
                'message' => 'Settings saved successfully.',
                'connections' => $settings->getConnections(),
                'mappings' => $settings->getMappings(),
                'misc' => $settings->getMisc()
            ]);

        } catch (ValidationException $e) {
            return $this->sendError($e->errors(), $e->getCode());
        } catch (Exception $e) {
            return $this->sendError([
                'message' => $e->getMessage()
            ], $e->getCode());
        }
    }

    public function storeMiscSettings(Request $request, Settings $settings)
    {
        $this->verify();

        $misc = $request->get('settings');
        $settings->updateMiscSettings($misc);
        $this->sendSuccess([
            'message' => 'General Settings has been updated'
        ]);
    }

    public function delete(Request $request, Settings $settings)
    {
        $this->verify();

        $connections = $settings->delete($request->get('key'));
        
        return $this->sendSuccess($connections);
    }

    public function storeGlobals(Request $request, Settings $settings)
    {
        $this->verify();

        $settings->saveGlobalSettings(
            $data = $request->except(['action', 'nonce'])
        );

        return $this->sendSuccess([
            'form' => $data,
            'message' => 'Settings saved successfully.'
        ]);
    }

    public function sendTestEmil(Request $request, Settings $settings)
    {
        $this->verify();

        try {

            $this->app->addAction('wp_mail_failed', [$this, 'onFail']);

            $data = $request->except(['action', 'nonce']);

            if (!isset($data['email'])) {
                return $this->sendError([
                    'email_error' => 'The email field is required.'
                ], 422);
            }

            $settings->sendTestEmail($data, $settings->get());

            return $this->sendSuccess([
                'message' => 'Email delivered successfully.'
            ]);

        } catch (Exception $e) {
            return $this->sendError([
                'message' => $e->getMessage()
            ], $e->getCode());
        }
    }

    public function onFail($response)
    {
        return $this->sendError([
            'message' => $response->get_error_message(),
            'errors' => $response->get_error_data()
        ], $response->get_error_code());
    }

    public function validateConnection($provider, $connection)
    {
        $errors = [];

        try {
            $provider->validateBasicInformation($connection);
        } catch (ValidationException $e) {
            $errors = $e->errors();
        }

        try {
            $provider->validateProviderInformation($connection);
        } catch (ValidationException $e) {
            $errors = array_merge($errors, $e->errors());
        }

        if ($errors) {
            throw new ValidationException('Unprocessable Entity', 422, null, $errors);
        }
    }

    public function getConnectionInfo(Request $request, Settings $settings, Factory $factory)
    {
        $this->verify();

        $connectionId = $request->get('connection_id');
        $connections = $settings->getConnections();

        if(!isset($connections[$connectionId]['provider_settings'])) {
            return $this->sendSuccess([
                'info' => 'Sorry no connection found. Please reload the page and try again'
            ]);
        }

        $connection = $connections[$connectionId]['provider_settings'];

        $provider = $factory->make($connection['provider']);

        return $this->sendSuccess([
            'info' => $provider->getConnectionInfo($connection)
        ]);
    }
}
