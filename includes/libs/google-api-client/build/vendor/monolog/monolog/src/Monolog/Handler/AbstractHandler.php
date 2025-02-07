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
use FluentSmtpLib\Monolog\ResettableInterface;
use FluentSmtpLib\Psr\Log\LogLevel;
/**
 * Base Handler class providing basic level/bubble support
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 *
 * @phpstan-import-type Level from \Monolog\Logger
 * @phpstan-import-type LevelName from \Monolog\Logger
 */
abstract class AbstractHandler extends \FluentSmtpLib\Monolog\Handler\Handler implements \FluentSmtpLib\Monolog\ResettableInterface
{
    /**
     * @var int
     * @phpstan-var Level
     */
    protected $level = \FluentSmtpLib\Monolog\Logger::DEBUG;
    /** @var bool */
    protected $bubble = \true;
    /**
     * @param int|string $level  The minimum logging level at which this handler will be triggered
     * @param bool       $bubble Whether the messages that are handled can bubble up the stack or not
     *
     * @phpstan-param Level|LevelName|LogLevel::* $level
     */
    public function __construct($level = \FluentSmtpLib\Monolog\Logger::DEBUG, bool $bubble = \true)
    {
        $this->setLevel($level);
        $this->bubble = $bubble;
    }
    /**
     * {@inheritDoc}
     */
    public function isHandling(array $record) : bool
    {
        return $record['level'] >= $this->level;
    }
    /**
     * Sets minimum logging level at which this handler will be triggered.
     *
     * @param  Level|LevelName|LogLevel::* $level Level or level name
     * @return self
     */
    public function setLevel($level) : self
    {
        $this->level = \FluentSmtpLib\Monolog\Logger::toMonologLevel($level);
        return $this;
    }
    /**
     * Gets minimum logging level at which this handler will be triggered.
     *
     * @return int
     *
     * @phpstan-return Level
     */
    public function getLevel() : int
    {
        return $this->level;
    }
    /**
     * Sets the bubbling behavior.
     *
     * @param  bool $bubble true means that this handler allows bubbling.
     *                      false means that bubbling is not permitted.
     * @return self
     */
    public function setBubble(bool $bubble) : self
    {
        $this->bubble = $bubble;
        return $this;
    }
    /**
     * Gets the bubbling behavior.
     *
     * @return bool true means that this handler allows bubbling.
     *              false means that bubbling is not permitted.
     */
    public function getBubble() : bool
    {
        return $this->bubble;
    }
    /**
     * {@inheritDoc}
     */
    public function reset()
    {
    }
}
