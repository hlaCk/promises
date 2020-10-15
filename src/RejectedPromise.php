<?php

namespace hlaCk\Promise;

/**
 * A promise that has been rejected.
 *
 * Thenning off of this promise will invoke the onRejected callback
 * immediately and ignore other callbacks.
 */
class RejectedPromise implements PromiseInterface
{
    /**
     * @var
     */
    private $reason;

    /**
     * RejectedPromise constructor.
     *
     * @param $reason
     */
    public function __construct($reason)
    {
        if (is_object($reason) && method_exists($reason, 'then')) {
            throw new \InvalidArgumentException(
                'You cannot create a RejectedPromise with a promise.'
            );
        }

        $this->reason = $reason;
    }

    public function then(
        callable $onFulfilled = null,
        callable $onRejected = null
    ): PromiseInterface {
        // If there's no onRejected callback then just return self.
        if (!$onRejected) {
            return $this;
        }

        $queue = Utils::queue();
        $reason = $this->reason;
        $p = new Promise([$queue, 'run']);
        $queue->add(static function () use ($p, $reason, $onRejected) {
            if (Is::pending($p)) {
                try {
                    // Return a resolved promise if onRejected does not throw.
                    $p->resolve($onRejected($reason));
                } catch (\Throwable|\Exception $e) {
                    // onRejected threw, so return a rejected promise.
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
     * @throws \Throwable if the promise has no wait function or if the
     *                         promise does not settle after waiting.
     */
    public function wait($unwrap = true, $defaultDelivery = null): PromiseInterface
    {
        if ($unwrap) {
            throw Create::exceptionFor($this->reason);
        }

        return $this;
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
        return self::REJECTED;
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
        throw new \LogicException("Cannot resolve a rejected promise");
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
        if ($reason !== $this->reason) {
            throw new \LogicException("Cannot reject a rejected promise");
        }

        return $this;
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
