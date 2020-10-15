<?php

namespace hlaCk\Promise;

/**
 * A promise that has been fulfilled.
 *
 * Thenning off of this promise will invoke the onFulfilled callback
 * immediately and ignore other callbacks.
 */
class FulfilledPromise implements PromiseInterface
{
    /**
     * @var
     */
    private $value;

    /**
     * FulfilledPromise constructor.
     *
     * @param $value
     */
    public function __construct($value)
    {
        if (is_object($value) && method_exists($value, 'then')) {
            throw new \InvalidArgumentException(
                'You cannot create a FulfilledPromise with a promise.'
            );
        }

        $this->value = $value;
    }

    public function then(
        callable $onFulfilled = null,
        callable $onRejected = null
    ): PromiseInterface {
        // Return itself if there is no onFulfilled function.
        if (!$onFulfilled) {
            return $this;
        }

        $queue = Utils::queue();
        $p = new Promise([$queue, 'run']);
        $value = $this->value;
        $queue->add(static function () use ($p, $value, $onFulfilled) {
            if (Is::pending($p)) {
                try {
                    $p->resolve($onFulfilled($value));
                } catch (\Throwable|\Exception $e) {
                    $p->reject($e);
                }
            }
        });

        return $p;
    }

    public function otherwise(callable $onRejected): PromiseInterface
    {
        return $this->then(null, $onRejected);
    }

    /**
     * Waits until the promise completes if possible.
     *
     * Pass $unwrap as true to unwrap the result of the promise, either
     * returning the resolved value or throwing the rejected exception.
     *
     * If the promise cannot be waited on, then the promise will be rejected.
     *
     * @param bool $unwrap
     *
     * @param null $defaultDelivery
     *
     * @return PromiseInterface|mixed
     *
     */
    public function wait($unwrap = true, $defaultDelivery = null): PromiseInterface
    {
        return $unwrap ? $this->value : $this;
    }

    /**
     * Get the state of the promise ("pending", "rejected", or "fulfilled").
     *
     * The three states can be checked against the constants defined on
     * PromiseInterface: PENDING, FULFILLED, and REJECTED.
     *
     * @return string
     */
    public function getState(): string
    {
        return self::FULFILLED;
    }

    /**
     * Resolve the promise with the given value.
     *
     * @param mixed $value
     *
     * @return PromiseInterface
     * @throws \RuntimeException if the promise is already resolved.
     */
    public function resolve($value): PromiseInterface
    {
        if ($value !== $this->value) {
            throw new \LogicException("Cannot resolve a fulfilled promise");
        }

        return $this;
    }

    /**
     * Reject the promise with the given reason.
     *
     * @param mixed $reason
     *
     * @return PromiseInterface
     * @throws \RuntimeException if the promise is already resolved.
     */
    public function reject($reason): PromiseInterface
    {
        throw new \LogicException("Cannot reject a fulfilled promise");
    }

    public function cancel(): PromiseInterface
    {
        // pass
        return $this;
    }

    /**
     * @param callable $wfn
     * @param null     $newThis
     *
     * @return \Closure
     */
    public function parseClosure(callable $wfn, $newThis = null): \Closure
    {
        return Utils::parseClosure($wfn, $newThis = $newThis ?? $this);
    }
}
