<?php /** @noinspection PhpPrivateFieldCanBeLocalVariableInspection */

namespace hlaCk\Promise;

/**
 * A task queue that executes tasks in a FIFO order.
 *
 * This task queue class is used to settle promises asynchronously and
 * maintains a constant stack size. You can use the task queue asynchronously
 * by calling the `run()` function of the global task queue in an event loop.
 *
 *     hlaCk\Promise\Utils::queue()->run();
 */
class TaskQueue implements TaskQueueInterface
{
    use \mPhpMaster\Support\Traits\TMacroable;

    /**
     * @var bool
     */
    private bool $enableShutdown = true;
    /**
     * @var array
     */
    private array $queue = [];

    /**
     * TaskQueue constructor.
     *
     * @param bool $withShutdown
     */
    public function __construct($withShutdown = true)
    {
        if ($withShutdown) {
            register_shutdown_function(function () {
                if ($this->enableShutdown) {
                    // Only run the tasks if an E_ERROR didn't occur.
                    $err = error_get_last();
                    if (!$err || ($err['type'] ^ E_ERROR)) {
                        $this->run();
                    }
                }
            });
        }
    }

    /**
     * @return bool
     */
    public function isEmpty(): bool
    {
        return !$this->queue;
    }

    /**
     * Adds a task to the queue that will be executed the next time run is
     * called.
     * @return void
     */
    public function add(callable $task): void
    {
        $this->queue[] = $task;
    }

    /**
     * Execute all of the pending task in the queue.
     * @return void
     */
    public function run(): void
    {
        while ($task = array_shift($this->queue)) {
            /** @var callable $task */
            $task();
        }
    }

    /**
     * The task queue will be run and exhausted by default when the process
     * exits IFF the exit is not the result of a PHP E_ERROR error.
     *
     * You can disable running the automatic shutdown of the queue by calling
     * this function. If you disable the task queue shutdown process, then you
     * MUST either run the task queue (as a result of running your event loop
     * or manually using the run() method) or wait on each outstanding promise.
     *
     * Note: This shutdown will occur before any destructors are triggered.
     */
    public function disableShutdown(): void
    {
        $this->enableShutdown = false;
    }
}
