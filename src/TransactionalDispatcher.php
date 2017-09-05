<?php

namespace Neves\TransactionalEvents;

use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Events\Dispatcher as EventDispatcher;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class TransactionalDispatcher
{
    /**
     * The connection resolver.
     *
     * @var \Illuminate\Database\ConnectionResolverInterface
     */
    private $connectionResolver;

    /**
     * The event dispatcher.
     *
     * @var \Illuminate\Contracts\Events\Dispatcher
     */
    private $dispatcher;

    /**
     * The events that must be enqueued on transactions.
     *
     * @var array
     */
    private $only = ['*'];

    /**
     * The events that are not enqueued on transactions.
     *
     * @var array
     */
    private $except = [];

    /**
     * Create a new transactional event dispatcher instance.
     *
     * @param \Illuminate\Database\ConnectionResolverInterface $connectionResolver
     * @param \Illuminate\Contracts\Events\Dispatcher $eventDispatcher
     */
    public function __construct(ConnectionResolverInterface $connectionResolver, EventDispatcher $eventDispatcher)
    {
        $this->connectionResolver = $connectionResolver;
        $this->dispatcher = $eventDispatcher;
    }

    /**
     * Dispatch an event and call the listeners.
     *
     * @param  string|object $event
     * @param  mixed $payload
     * @param  bool $halt
     * @return array|null
     */
    public function dispatch($event, $payload = [], $halt = false)
    {
        $connection = $this->connectionResolver->connection();
        $connectionId = spl_object_hash($connection);

        if (! $this->isTransactionalEvent($connection, $event)) {
            return $this->dispatcher->dispatch($event, $payload, $halt);
        }

        $this->dispatcher->listen($connectionId.'_commit', function () use ($event, $payload) {
            $this->dispatcher->dispatch($event, $payload);
        });
    }

    /**
     * Flush all enqueued events.
     *
     * @param \Illuminate\Database\ConnectionInterface  $connection
     * @return void
     */
    public function commit(ConnectionInterface $connection)
    {
        $connectionId = spl_object_hash($connection);

        $this->dispatcher->dispatch($connectionId.'_commit');
        $this->dispatcher->forget($connectionId.'_commit');
    }

    /**
     * Set list of events that should be handled by transactional layer.
     *
     * @param  array  $enabled
     * @return void
     */
    public function setEnabledEvents(array $enabled)
    {
        $this->only = $enabled;
    }

    /**
     * Set exceptions list.
     *
     * @param  array  $except
     * @return void
     */
    public function setExceptEvents(array $except)
    {
        $this->except = $except;
    }

    /**
     * Clear enqueued events.
     *
     * @param \Illuminate\Database\ConnectionInterface  $connection
     * @return void
     */
    public function rollback(ConnectionInterface $connection)
    {
        $connectionId = spl_object_hash($connection);
        $this->dispatcher->forget($connectionId.'_commit');
    }

    /**
     * Dynamically pass methods to the default dispatcher.
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return $this->dispatcher->$method(...$parameters);
    }

    /**
     * Check whether an event is a transactional event or not.
     *
     * @param \Illuminate\Database\ConnectionInterface $connection
     * @param string|object $event
     * @return bool
     */
    private function isTransactionalEvent(ConnectionInterface $connection, $event)
    {
        if ($connection->transactionLevel() < 1)
            return false;

        return $this->shouldHandle($event);
    }

    /**
     * Check whether an event should be handled by this layer or not.
     *
     * @param string|object $event
     * @return bool
     */
    private function shouldHandle($event)
    {
        $event = is_string($event) ? $event : get_class($event);

        foreach($this->except as $exception) {
            if ($this->matches($exception, $event)) {
                return false;
            }
        }

        foreach($this->only as $enabled) {
            if ($this->matches($enabled, $event)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check whether an event name matches a pattern or not.
     *
     * @param  string  $pattern
     * @param  string  $event
     * @return bool
     */
    private function matches($pattern, $event) {
        return (Str::contains($pattern, '*') && Str::is($pattern, $event))
            || Str::startsWith($event, $pattern);
    }
}