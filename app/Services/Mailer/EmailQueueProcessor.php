<?php

namespace FluentMail\App\Services\Mailer;

use InvalidArgumentException;
use FluentMail\App\Services\Mailer\QueryIterator;
use FluentMail\App\Services\Mailer\Providers\Factory;

class EmailQueueProcessor
{
    protected $factory = null;
    protected $iterator = null;

    public function __construct()
    {
        $this->factory = fluentMail(Factory::class);
        $this->iterator = fluentMail(QueryIterator::class);
    }

    public static function process()
    {
        return (new static)->sendEmails();
    }

    public function sendEmails()
    {
        foreach ($this->iterator as $key => $emails) {

            foreach ($emails as $email) {
                try {
                    $this->sendEmail(
                        $email = $this->marshalData((array) $email)
                    );
                } catch (InvalidArgumentException $e) {
                    $this->iterator->exclude($email['id']);
                    continue;
                }
            }
        }

        if (!defined('FLUENTMAIL_TESTING_EMAIL')) {
            wp_die('End of iterations.');
        }
    }

    public function marshalData($data)
    {       
        $data['to'] = maybe_unserialize($data['to']);
        $data['from'] = maybe_unserialize($data['from']);
        $data['body'] = maybe_unserialize($data['body']);
        $data['headers'] = maybe_unserialize($data['headers']);
        $data['attachments'] = maybe_unserialize($data['attachments']);
        $data['extra'] = maybe_unserialize($data['extra']);

        return $data;
    }

    public function sendEmail($data)
    {
        $from = reset($data['from']);

        if ($provider = $this->factory->get($from['email'])) {
            return $provider->send($data);
        }
    }
}
