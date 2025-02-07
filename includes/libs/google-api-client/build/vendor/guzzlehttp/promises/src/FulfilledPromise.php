<?php

declare (strict_types=1);
namespace FluentSmtpLib\GuzzleHttp\Promise;

/**
 * A promise that has been fulfilled.
 *
 * Thenning off of this promise will invoke the onFulfilled callback
 * immediately and ignore other callbacks.
 *
 * @final
 */
class FulfilledPromise implements \FluentSmtpLib\GuzzleHttp\Promise\PromiseInterface
{
    private $value;
    /**
     * @param mixed $value
     */
    public function __construct($value)
    {
        if (\is_object($value) && \method_exists($value, 'then')) {
            throw new \InvalidArgumentException('You cannot create a FulfilledPromise with a promise.');
        }
        $this->value = $value;
    }
    public function then(?callable $onFulfilled = null, ?callable $onRejected = null) : \FluentSmtpLib\GuzzleHttp\Promise\PromiseInterface
    {
        // Return itself if there is no onFulfilled function.
        if (!$onFulfilled) {
            return $this;
        }
        $queue = \FluentSmtpLib\GuzzleHttp\Promise\Utils::queue();
        $p = new \FluentSmtpLib\GuzzleHttp\Promise\Promise([$queue, 'run']);
        $value = $this->value;
        $queue->add(static function () use($p, $value, $onFulfilled) : void {
            if (\FluentSmtpLib\GuzzleHttp\Promise\Is::pending($p)) {
                try {
                    $p->resolve($onFulfilled($value));
                } catch (\Throwable $e) {
                    $p->reject($e);
                }
            }
        });
        return $p;
    }
    public function otherwise(callable $onRejected) : \FluentSmtpLib\GuzzleHttp\Promise\PromiseInterface
    {
        return $this->then(null, $onRejected);
    }
    public function wait(bool $unwrap = \true)
    {
        return $unwrap ? $this->value : null;
    }
    public function getState() : string
    {
        return self::FULFILLED;
    }
    public function resolve($value) : void
    {
        if ($value !== $this->value) {
            throw new \LogicException('Cannot resolve a fulfilled promise');
        }
    }
    public function reject($reason) : void
    {
        throw new \LogicException('Cannot reject a fulfilled promise');
    }
    public function cancel() : void
    {
        // pass
    }
}
