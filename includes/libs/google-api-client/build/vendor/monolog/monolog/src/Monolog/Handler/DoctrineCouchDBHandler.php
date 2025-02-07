<?php

declare (strict_types=1);
/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace FluentSmtpLib\Monolog\Handler;

use FluentSmtpLib\Monolog\Logger;
use FluentSmtpLib\Monolog\Formatter\NormalizerFormatter;
use FluentSmtpLib\Monolog\Formatter\FormatterInterface;
use FluentSmtpLib\Doctrine\CouchDB\CouchDBClient;
/**
 * CouchDB handler for Doctrine CouchDB ODM
 *
 * @author Markus Bachmann <markus.bachmann@bachi.biz>
 */
class DoctrineCouchDBHandler extends \FluentSmtpLib\Monolog\Handler\AbstractProcessingHandler
{
    /** @var CouchDBClient */
    private $client;
    public function __construct(\FluentSmtpLib\Doctrine\CouchDB\CouchDBClient $client, $level = \FluentSmtpLib\Monolog\Logger::DEBUG, bool $bubble = \true)
    {
        $this->client = $client;
        parent::__construct($level, $bubble);
    }
    /**
     * {@inheritDoc}
     */
    protected function write(array $record) : void
    {
        $this->client->postDocument($record['formatted']);
    }
    protected function getDefaultFormatter() : \FluentSmtpLib\Monolog\Formatter\FormatterInterface
    {
        return new \FluentSmtpLib\Monolog\Formatter\NormalizerFormatter();
    }
}
