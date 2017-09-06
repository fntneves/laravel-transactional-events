<?php

namespace Neves\Events;

use Illuminate\Support\Str;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Events\Dispatcher as EventDispatcher;
use Illuminate\Contracts\Events\Dispatcher as DispatcherContract;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Database\Events\TransactionRolledBack;

class TransactionalDispatcher implements DispatcherContract
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
     * The events that must have transactional behavior.
     *
     * @var array
     */
    private $transactional = [
        'App\Events',
    ];

    /**
     * The events that are not considered on transactional layer.
     *
     * @var array
     */
    private $exclude = [
        'Illuminate\Database\Events',
    ];

    /**
     * Create a new transactional event dispatcher instance.
     *
     * @param  \Illuminate\Database\ConnectionResolverInterface  $connectionResolver
     * @param  \Illuminate\Contracts\Events\Dispatcher  $eventDispatcher
     */
    public function __construct(ConnectionResolverInterface $connectionResolver, EventDispatcher $eventDispatcher)
    {
        $this->connectionResolver = $connectionResolver;
        $this->dispatcher = $eventDispatcher;
        $this->setUpListeners();
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
     * @param  \Illuminate\Database\ConnectionInterface  $connection
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
    public function setTransactionalEvents(array $enabled)
    {
        $this->transactional = $enabled;
    }

    /**
     * Set exceptions list.
     *
     * @param  array  $except
     * @return void
     */
    public function setExcludedEvents(array $except)
    {
        $this->exclude = array_merge(['Illuminate\Database\Events'], $except);
    }

    /**
     * Clear enqueued events.
     *
     * @param  \Illuminate\Database\ConnectionInterface  $connection
     * @return void
     */
    public function rollback(ConnectionInterface $connection)
    {
        $connectionId = spl_object_hash($connection);
        $this->dispatcher->forget($connectionId.'_commit');
    }

    /**
     * Check whether an event is a transactional event or not.
     *
     * @param  \Illuminate\Database\ConnectionInterface  $connection
     * @param  string|object $event
     * @return bool
     */
    private function isTransactionalEvent(ConnectionInterface $connection, $event)
    {
        if ($connection->transactionLevel() < 1) {
            return false;
        }

        return $this->shouldHandle($event);
    }

    /**
     * Check whether an event should be handled by this layer or not.
     *
     * @param  string|object  $event
     * @return bool
     */
    private function shouldHandle($event)
    {
        $event = is_string($event) ? $event : get_class($event);

        foreach ($this->exclude as $exception) {
            if ($this->matches($exception, $event)) {
                return false;
            }
        }

        foreach ($this->transactional as $enabled) {
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
    private function matches($pattern, $event)
    {
        return (Str::contains($pattern, '*') && Str::is($pattern, $event))
            || Str::startsWith($event, $pattern);
    }

    /**
     * Register an event listener with the dispatcher.
     *
     * @param  string|array $events
     * @param  mixed $listener
     * @return void
     */
    public function listen($events, $listener)
    {
        $this->dispatcher->listen($events, $listener);
    }

    /**
     * Determine if a given event has listeners.
     *
     * @param  string $eventName
     * @return bool
     */
    public function hasListeners($eventName)
    {
        return $this->dispatcher->hasListeners($eventName);
    }

    /**
     * Register an event subscriber with the dispatcher.
     *
     * @param  object|string $subscriber
     * @return void
     */
    public function subscribe($subscriber)
    {
        $this->dispatcher->subscribe($subscriber);
    }

    /**
     * Dispatch an event until the first non-null response is returned.
     *
     * @param  string|object $event
     * @param  mixed $payload
     * @return array|null
     */
    public function until($event, $payload = [])
    {
        return $this->dispatcher->until($event, $payload);
    }

    /**
     * Register an event and payload to be fired later.
     *
     * @param  string $event
     * @param  array $payload
     * @return void
     */
    public function push($event, $payload = [])
    {
        $this->dispatcher->push($event, $payload);
    }

    /**
     * Flush a set of pushed events.
     *
     * @param  string $event
     * @return void
     */
    public function flush($event)
    {
        $this->dispatcher->flush($event);
    }

    /**
     * Remove a set of listeners from the dispatcher.
     *
     * @param  string $event
     * @return void
     */
    public function forget($event)
    {
        $this->dispatcher->forget($event);
    }

    /**
     * Forget all of the queued listeners.
     *
     * @return void
     */
    public function forgetPushed()
    {
        $this->dispatcher->forgetPushed();
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

    private function setUpListeners() {
        $this->dispatcher->listen(TransactionCommitted::class, function ($event) {
            $this->commit($event->connection);
        });

        $this->dispatcher->listen(TransactionRolledBack::class, function ($event) {
            $this->rollback($event->connection);
        });
    }
}
