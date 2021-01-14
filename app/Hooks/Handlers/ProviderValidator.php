<?php

namespace FluentMail\App\Hooks\Handlers;

class ProviderValidator
{
    public function handle($provider, $errors = [])
    {
        if ($validator = $this->getProviderValidator($provider, $errors)) {
            return $validator->validate();
        }
        
        return $errors;
    }

    protected function getProviderValidator($provider, $errors)
    {
        $key = $provider['provider'];

        $path = FluentMail('path.app') . 'Services/Mailer/Providers/' . $key;

        $file = $path . '/' . 'Validator.php';


        if (file_exists($file)) {
            $ns = 'FluentMail\App\Services\Mailer\Providers\\' . $key;

            $class = $ns . '\Validator';

            if (class_exists($class)) {
                return new $class($provider, $errors);
            }
        }
    }
}