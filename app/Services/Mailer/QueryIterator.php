<?php

namespace FluentMail\App\Services\Mailer;

use FluentMail\App\Models\Logger;

class QueryIterator implements \Iterator
{
    protected $key = 0;
    protected $limit = 0;
    protected $offset = 0;
    protected $emails = null;
    protected $logging = null;
    protected $excludeAbles = [];

    public function __construct(Logger $logger, $limit = 100)
    {
        $this->logger = $logger;
        $this->limit = $limit ? $limit : 10;
    }

    public function current()
    {
        return $this->emails;
    }

    public function key()
    {
        return $this->key++;
    }

    public function next()
    {
        $this->offset = $this->offset;
    }

    public function rewind()
    {
        $this->offset = 0;
    }

    public function valid()
    {
        $this->emails = $this->logger->getPendingEmails(
            $this->limit, 'DESC', $this->excludeAbles
        );

        return count($this->emails);
    }

    public function exclude($id)
    {
        $this->excludeAbles[] = $id;
    }
}
