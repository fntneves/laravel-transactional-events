<?php

namespace Neves\Events;

use Illuminate\Support\Str;
use Illuminate\Database\ConnectionInterface;
use Neves\Events\Contracts\TransactionalEvent;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Events\Dispatcher as EventDispatcher;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Contracts\Events\Dispatcher as DispatcherContract;

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
    private $excluded = [
        'Illuminate\Database\Events',
    ];

    /**
     * The current transaction level.
     *
     * @var array
     */
    private $transactionLevel = 0;

    /**
     * The current pending events per transaction level of connections.
     *
     * @var array
     */
    private $pendingEvents = [];

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

        // If halt is specified, then automatically dispatches the event
        // to the original dispatcher. This happens because the caller
        // is waiting for the result of the listeners of this event.
        if ($halt || ! $this->isTransactionalEvent($connection, $event)) {
            return $this->dispatcher->dispatch($event, $payload, $halt);
        }

        $this->addPendingEvent($connection, $event, $payload);
    }

    /**
     * Flush all pending events.
     *
     * @param  \Illuminate\Database\ConnectionInterface  $connection
     * @return void
     */
    public function commit(ConnectionInterface $connection)
    {
        $connectionId = $connection->getName();

        // We decrement the transaction level and prevent events to be dispatched
        // while comitting nested transactions, so no transient state is saved.
        // Only dispatch events right after the outer transaction commits.
        $this->transactionLevel--;
        if ($this->transactionLevel > 0 || ! isset($this->pendingEvents[$connectionId])) {
            return;
        }

        $this->dispatchPendingEvents($connection);
    }

    /**
     * Prepare to store events for the current transaction.
     *
     * @param  \Illuminate\Database\ConnectionInterface  $connection
     * @return void
     */
    protected function prepareTransaction($connection)
    {
        $connectionId = $connection->getName();
        $this->transactionLevel = $this->transactionLevel > 0 ? $this->transactionLevel + 1 : 1;

        // Now we prepare a new array level for this transaction in the current
        // transaction level. It allows nested transactions to rollback and
        // their events discarded without affecting previous transactions.
        $this->pendingEvents[$connectionId][$this->transactionLevel][] = [];
    }

    /**
     * Add a transactional event waiting for transaction to commit.
     *
     * @param  \Illuminate\Database\ConnectionInterface  $connection
     * @param  string|object $event
     * @param  mixed $payload
     * @return void
     */
    protected function addPendingEvent($connection, $event, $payload)
    {
        $connectionId = $connection->getName();
        $transactionLevel = $this->transactionLevel;

        $eventData = compact('event', 'payload');
        $transactionLevelEvents = &$this->pendingEvents[$connectionId][$transactionLevel];
        $transactionLevelEvents[count($transactionLevelEvents) - 1][] = $eventData;
    }

    /**
     * Flush all pending events for the given connection.
     *
     * @param  \Illuminate\Database\ConnectionInterface  $connection
     * @return void
     */
    protected function dispatchPendingEvents(ConnectionInterface $connection)
    {
        $connectionId = $connection->getName();
        foreach ($this->pendingEvents[$connectionId] as $transactionsEvents) {
            foreach ($transactionsEvents as $transactionEvents) {
                foreach ($transactionEvents as $event) {
                    $this->dispatcher->dispatch($event['event'], $event['payload']);
                }
            }
        }

        unset($this->pendingEvents[$connectionId]);
    }

    /**
     * Clear enqueued events.
     *
     * @param  \Illuminate\Database\ConnectionInterface  $connection
     * @return void
     */
    public function rollback(ConnectionInterface $connection)
    {
        $connectionId = $connection->getName();

        if ($this->transactionLevel > 1) {
            array_pop($this->pendingEvents[$connectionId][$this->transactionLevel]);
        } else {
            unset($this->pendingEvents[$connectionId]);
        }

        $this->transactionLevel--;
    }

    /**
     * Set list of events that should be handled by transactional layer.
     *
     * @param  array|null  $transactional
     * @return void
     */
    public function setTransactionalEvents(array $transactional)
    {
        $this->transactional = $transactional;
    }

    /**
     * Set exceptions list.
     *
     * @param  array  $excluded
     * @return void
     */
    public function setExcludedEvents(array $excluded = [])
    {
        $this->excluded = array_merge(['Illuminate\Database\Events'], $excluded);
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
        if ($this->transactionLevel > 0) {
            return $this->shouldHandle($event);
        }

        return false;
    }

    /**
     * Check whether an event should be handled by this layer or not.
     *
     * @param  string|object  $event
     * @return bool
     */
    private function shouldHandle($event)
    {
        if ($event instanceof TransactionalEvent) {
            return true;
        }

        $event = is_string($event) ? $event : get_class($event);

        foreach ($this->excluded as $excluded) {
            if ($this->matches($excluded, $event)) {
                return false;
            }
        }

        foreach ($this->transactional as $transactionalEvent) {
            if ($this->matches($transactionalEvent, $event)) {
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
     * Fire an event and call the listeners.
     *
     * @param  string|object  $event
     * @param  mixed  $payload
     * @param  bool  $halt
     * @return array|null
     */
    public function fire($event, $payload = [], $halt = false)
    {
        return $this->dispatch($event, $payload, $halt);
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

    protected function setUpListeners()
    {
        $this->dispatcher->listen(TransactionBeginning::class, function ($event) {
            $this->prepareTransaction($event->connection);
        });

        $this->dispatcher->listen(TransactionCommitted::class, function ($event) {
            $this->commit($event->connection);
        });

        $this->dispatcher->listen(TransactionRolledBack::class, function ($event) {
            $this->rollback($event->connection);
        });
    }
}
