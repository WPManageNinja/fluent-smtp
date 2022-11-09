<?php

namespace FluentMail\App\Services\Mailer;

use FluentMail\App\Models\Logger;
use FluentMail\App\Services\Mailer\Providers\Factory;
use FluentMail\App\Services\Mailer\Providers\DefaultMail\Handler as PHPMailer;

class FluentPHPMailer
{
    protected $app = null;

    protected $phpMailer = null;

    public function __construct($phpMailer)
    {
        $this->app = fluentMail();

        $this->phpMailer = $phpMailer;
    }

    public function send()
    {
        if ($driver = fluentMailGetProvider($this->phpMailer->From)) {
            if ($forceFromEmail = $driver->getSetting('force_from_email_id')) {
                $this->phpMailer->From = $forceFromEmail;
            }
            return $driver->setPhpMailer($this->phpMailer)->send();
        }

        return $this->phpMailer->send();
    }

    public function sendViaFallback($rowId)
    {
        $driver = fluentMailGetProvider($this->phpMailer->From);
        if($driver) {
            $driver->setRowId($rowId);
            return $driver->setPhpMailer($this->phpMailer)->send();
        }
        return false;
    }

    public function __get($key)
    {
        return $this->phpMailer->{$key};
    }

    public function __set($key, $value)
    {
        $this->phpMailer->{$key} = $value;
    }

    public function __call($method, $params)
    {
        return call_user_func_array([$this->phpMailer, $method], $params);
    }
}
