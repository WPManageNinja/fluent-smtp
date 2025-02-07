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

use FluentSmtpLib\Monolog\Formatter\FormatterInterface;
use FluentSmtpLib\Monolog\Formatter\HtmlFormatter;
/**
 * Base class for all mail handlers
 *
 * @author Gyula Sallai
 *
 * @phpstan-import-type Record from \Monolog\Logger
 */
abstract class MailHandler extends \FluentSmtpLib\Monolog\Handler\AbstractProcessingHandler
{
    /**
     * {@inheritDoc}
     */
    public function handleBatch(array $records) : void
    {
        $messages = [];
        foreach ($records as $record) {
            if ($record['level'] < $this->level) {
                continue;
            }
            /** @var Record $message */
            $message = $this->processRecord($record);
            $messages[] = $message;
        }
        if (!empty($messages)) {
            $this->send((string) $this->getFormatter()->formatBatch($messages), $messages);
        }
    }
    /**
     * Send a mail with the given content
     *
     * @param string $content formatted email body to be sent
     * @param array  $records the array of log records that formed this content
     *
     * @phpstan-param Record[] $records
     */
    protected abstract function send(string $content, array $records) : void;
    /**
     * {@inheritDoc}
     */
    protected function write(array $record) : void
    {
        $this->send((string) $record['formatted'], [$record]);
    }
    /**
     * @phpstan-param non-empty-array<Record> $records
     * @phpstan-return Record
     */
    protected function getHighestRecord(array $records) : array
    {
        $highestRecord = null;
        foreach ($records as $record) {
            if ($highestRecord === null || $highestRecord['level'] < $record['level']) {
                $highestRecord = $record;
            }
        }
        return $highestRecord;
    }
    protected function isHtmlBody(string $body) : bool
    {
        return ($body[0] ?? null) === '<';
    }
    /**
     * Gets the default formatter.
     *
     * @return FormatterInterface
     */
    protected function getDefaultFormatter() : \FluentSmtpLib\Monolog\Formatter\FormatterInterface
    {
        return new \FluentSmtpLib\Monolog\Formatter\HtmlFormatter();
    }
}
