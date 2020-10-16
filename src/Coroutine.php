<?php

namespace hlaCk\Promise;

use Exception;
use Generator;
use Throwable;

/**
 * Creates a promise that is resolved using a generator that yields values or
 * promises (somewhat similar to C#'s async keyword).
 *
 * When called, the Coroutine::of method will start an instance of the generator
 * and returns a promise that is fulfilled with its final yielded value.
 *
 * Control is returned back to the generator when the yielded promise settles.
 * This can lead to less verbose code when doing lots of sequential async calls
 * with minimal processing in between.
 *
 *     use hlaCk\Promise;
 *
 *     function createPromise($value) {
 *         return new Promise\FulfilledPromise($value);
 *     }
 *
 *     $promise = Promise\Coroutine::of(function () {
 *         $value = (yield createPromise('a'));
 *         try {
 *             $value = (yield createPromise($value . 'b'));
 *         } catch (\Exception $e) {
 *             // The promise was rejected.
 *         }
 *         yield $value . 'c';
 *     });
 *
 *     // Outputs "abc"
 *     $promise->then(function ($v) { echo $v; });
 *
 * @param callable $generatorFn Generator function to wrap into a promise.
 *
 * @return Promise
 *
 * @link https://github.com/petkaantonov/bluebird/blob/master/API.md#generators inspiration
 */
final class Coroutine implements PromiseInterface
{
    use \mPhpMaster\Support\Traits\TMacroable;

    /**
     * @var PromiseInterface|null
     */
    private $currentPromise;

    /**
     * @var Generator
     */
    private $generator;

    /**
     * @var Promise
     */
    private $result;

    public function __construct(callable $generatorFn)
    {
        $this->generator = $generatorFn();
        $this->result = new Promise(function () {
            while (isset($this->currentPromise)) {
                $this->currentPromise->wait();
            }
        });
        try {
            $this->nextCoroutine($this->generator->current());
        } catch (Throwable|Exception $exception) {
            $this->result->reject($exception);
        }
    }

    /**
     * Create a new coroutine.
     *
     * @return self
     */
    public static function of(callable $generatorFn): self
    {
        return new self($generatorFn);
    }

    public function then(
        callable $onFulfilled = null,
        callable $onRejected = null
    ): PromiseInterface
    {
        return $this->result->then($onFulfilled, $onRejected);
    }

    public function otherwise(callable $onRejected): PromiseInterface
    {
        return $this->result->otherwise($onRejected);
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
     * @return PromiseInterface|mixed
     *
     * @throws \LogicException|\Throwable if the promise has no wait function or if the
     *                         promise does not settle after waiting.
     */
    public function wait($unwrap = true): PromiseInterface
    {
        return $this->result->wait($unwrap);
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
        return $this->result->getState();
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
        $this->result->resolve($value);
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
        $this->result->reject($reason);
        return $this;
    }

    public function cancel(): PromiseInterface
    {
        $this->currentPromise->cancel();
        $this->result->cancel();
        return $this;
    }

    /**
     * @param $yielded
     */
    private function nextCoroutine($yielded): void
    {
        $this->currentPromise = Create::promiseFor($yielded)
            ->then([$this, '_handleSuccess'], [$this, '_handleFailure']);
    }

    /**
     * @param $value
     *
     * @internal
     */
    public function _handleSuccess($value): void
    {
        unset($this->currentPromise);
        try {
            $next = $this->generator->send($value);
            if ($this->generator->valid()) {
                $this->nextCoroutine($next);
            } else {
                $this->result->resolve($value);
            }
        } catch (Exception $exception) {
            $this->result->reject($exception);
        } catch (Throwable $throwable) {
            $this->result->reject($throwable);
        }
    }

    /**
     * @internal
     * @param $reason
     */
    public function _handleFailure($reason): void
    {
        unset($this->currentPromise);
        try {
            $nextYield = $this->generator->throw(Create::exceptionFor($reason));
            // The throw was caught, so keep iterating on the coroutine
            $this->nextCoroutine($nextYield);
        } catch (Exception $exception) {
            $this->result->reject($exception);
        } catch (Throwable $throwable) {
            $this->result->reject($throwable);
        }
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
