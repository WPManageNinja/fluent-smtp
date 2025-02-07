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
use FluentSmtpLib\Monolog\ResettableInterface;
/**
 * Forwards records to multiple handlers
 *
 * @author Lenar Lõhmus <lenar@city.ee>
 *
 * @phpstan-import-type Record from \Monolog\Logger
 */
class GroupHandler extends \FluentSmtpLib\Monolog\Handler\Handler implements \FluentSmtpLib\Monolog\Handler\ProcessableHandlerInterface, \FluentSmtpLib\Monolog\ResettableInterface
{
    use ProcessableHandlerTrait;
    /** @var HandlerInterface[] */
    protected $handlers;
    /** @var bool */
    protected $bubble;
    /**
     * @param HandlerInterface[] $handlers Array of Handlers.
     * @param bool               $bubble   Whether the messages that are handled can bubble up the stack or not
     */
    public function __construct(array $handlers, bool $bubble = \true)
    {
        foreach ($handlers as $handler) {
            if (!$handler instanceof \FluentSmtpLib\Monolog\Handler\HandlerInterface) {
                throw new \InvalidArgumentException('The first argument of the GroupHandler must be an array of HandlerInterface instances.');
            }
        }
        $this->handlers = $handlers;
        $this->bubble = $bubble;
    }
    /**
     * {@inheritDoc}
     */
    public function isHandling(array $record) : bool
    {
        foreach ($this->handlers as $handler) {
            if ($handler->isHandling($record)) {
                return \true;
            }
        }
        return \false;
    }
    /**
     * {@inheritDoc}
     */
    public function handle(array $record) : bool
    {
        if ($this->processors) {
            /** @var Record $record */
            $record = $this->processRecord($record);
        }
        foreach ($this->handlers as $handler) {
            $handler->handle($record);
        }
        return \false === $this->bubble;
    }
    /**
     * {@inheritDoc}
     */
    public function handleBatch(array $records) : void
    {
        if ($this->processors) {
            $processed = [];
            foreach ($records as $record) {
                $processed[] = $this->processRecord($record);
            }
            /** @var Record[] $records */
            $records = $processed;
        }
        foreach ($this->handlers as $handler) {
            $handler->handleBatch($records);
        }
    }
    public function reset()
    {
        $this->resetProcessors();
        foreach ($this->handlers as $handler) {
            if ($handler instanceof \FluentSmtpLib\Monolog\ResettableInterface) {
                $handler->reset();
            }
        }
    }
    public function close() : void
    {
        parent::close();
        foreach ($this->handlers as $handler) {
            $handler->close();
        }
    }
    /**
     * {@inheritDoc}
     */
    public function setFormatter(\FluentSmtpLib\Monolog\Formatter\FormatterInterface $formatter) : \FluentSmtpLib\Monolog\Handler\HandlerInterface
    {
        foreach ($this->handlers as $handler) {
            if ($handler instanceof \FluentSmtpLib\Monolog\Handler\FormattableHandlerInterface) {
                $handler->setFormatter($formatter);
            }
        }
        return $this;
    }
}
