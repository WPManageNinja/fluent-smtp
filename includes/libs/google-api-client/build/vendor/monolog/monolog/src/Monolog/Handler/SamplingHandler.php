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
/**
 * Sampling handler
 *
 * A sampled event stream can be useful for logging high frequency events in
 * a production environment where you only need an idea of what is happening
 * and are not concerned with capturing every occurrence. Since the decision to
 * handle or not handle a particular event is determined randomly, the
 * resulting sampled log is not guaranteed to contain 1/N of the events that
 * occurred in the application, but based on the Law of large numbers, it will
 * tend to be close to this ratio with a large number of attempts.
 *
 * @author Bryan Davis <bd808@wikimedia.org>
 * @author Kunal Mehta <legoktm@gmail.com>
 *
 * @phpstan-import-type Record from \Monolog\Logger
 * @phpstan-import-type Level from \Monolog\Logger
 */
class SamplingHandler extends \FluentSmtpLib\Monolog\Handler\AbstractHandler implements \FluentSmtpLib\Monolog\Handler\ProcessableHandlerInterface, \FluentSmtpLib\Monolog\Handler\FormattableHandlerInterface
{
    use ProcessableHandlerTrait;
    /**
     * @var HandlerInterface|callable
     * @phpstan-var HandlerInterface|callable(Record|array{level: Level}|null, HandlerInterface): HandlerInterface
     */
    protected $handler;
    /**
     * @var int $factor
     */
    protected $factor;
    /**
     * @psalm-param HandlerInterface|callable(Record|array{level: Level}|null, HandlerInterface): HandlerInterface $handler
     *
     * @param callable|HandlerInterface $handler Handler or factory callable($record|null, $samplingHandler).
     * @param int                       $factor  Sample factor (e.g. 10 means every ~10th record is sampled)
     */
    public function __construct($handler, int $factor)
    {
        parent::__construct();
        $this->handler = $handler;
        $this->factor = $factor;
        if (!$this->handler instanceof \FluentSmtpLib\Monolog\Handler\HandlerInterface && !\is_callable($this->handler)) {
            throw new \RuntimeException("The given handler (" . \json_encode($this->handler) . ") is not a callable nor a Monolog\\Handler\\HandlerInterface object");
        }
    }
    public function isHandling(array $record) : bool
    {
        return $this->getHandler($record)->isHandling($record);
    }
    public function handle(array $record) : bool
    {
        if ($this->isHandling($record) && \mt_rand(1, $this->factor) === 1) {
            if ($this->processors) {
                /** @var Record $record */
                $record = $this->processRecord($record);
            }
            $this->getHandler($record)->handle($record);
        }
        return \false === $this->bubble;
    }
    /**
     * Return the nested handler
     *
     * If the handler was provided as a factory callable, this will trigger the handler's instantiation.
     *
     * @phpstan-param Record|array{level: Level}|null $record
     *
     * @return HandlerInterface
     */
    public function getHandler(?array $record = null)
    {
        if (!$this->handler instanceof \FluentSmtpLib\Monolog\Handler\HandlerInterface) {
            $this->handler = ($this->handler)($record, $this);
            if (!$this->handler instanceof \FluentSmtpLib\Monolog\Handler\HandlerInterface) {
                throw new \RuntimeException("The factory callable should return a HandlerInterface");
            }
        }
        return $this->handler;
    }
    /**
     * {@inheritDoc}
     */
    public function setFormatter(\FluentSmtpLib\Monolog\Formatter\FormatterInterface $formatter) : \FluentSmtpLib\Monolog\Handler\HandlerInterface
    {
        $handler = $this->getHandler();
        if ($handler instanceof \FluentSmtpLib\Monolog\Handler\FormattableHandlerInterface) {
            $handler->setFormatter($formatter);
            return $this;
        }
        throw new \UnexpectedValueException('The nested handler of type ' . \get_class($handler) . ' does not support formatters.');
    }
    /**
     * {@inheritDoc}
     */
    public function getFormatter() : \FluentSmtpLib\Monolog\Formatter\FormatterInterface
    {
        $handler = $this->getHandler();
        if ($handler instanceof \FluentSmtpLib\Monolog\Handler\FormattableHandlerInterface) {
            return $handler->getFormatter();
        }
        throw new \UnexpectedValueException('The nested handler of type ' . \get_class($handler) . ' does not support formatters.');
    }
}
