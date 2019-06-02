<?php

namespace Neves\Events;

use Illuminate\Support\Str;
use drupol\phptree\Node\ValueNode;
use Illuminate\Support\Collection;
use drupol\phptree\Node\ValueNodeInterface;
use Neves\Events\Contracts\TransactionalEvent;
use Neves\Events\Concerns\DelegatesToDispatcher;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Events\Dispatcher as EventDispatcher;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Contracts\Events\Dispatcher as DispatcherContract;

final class TransactionalDispatcher implements DispatcherContract
{
    use DelegatesToDispatcher;

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
     * The current prepared transaction.
     *
     * @var \drupol\phptree\Node\ValueNodeInterface
     */
    private $currentTransaction;

    /**
     * All pending events in order.
     *
     * @var array
     */
    private $events = [];

    /**
     * Next position for event storing.
     *
     * @var int
     */
    private $nextEventIndex = 0;

    /**
     * Create a new transactional event dispatcher instance.
     *
     * @param  \Illuminate\Contracts\Events\Dispatcher  $eventDispatcher
     */
    public function __construct(EventDispatcher $eventDispatcher)
    {
        $this->dispatcher = $eventDispatcher;
        $this->setUpListeners();
    }

    /**
     * Set list of events that should be handled by transactional layer.
     *
     * @param  array|null  $transactional
     * @return void
     */
    public function setTransactionalEvents(array $transactional) : void
    {
        $this->transactional = $transactional;
    }

    /**
     * Set exceptions list.
     *
     * @param  array  $excluded
     * @return void
     */
    public function setExcludedEvents(array $excluded = []) : void
    {
        $this->excluded = array_merge(['Illuminate\Database\Events'], $excluded);
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
        // If halt is specified, then automatically dispatches the event
        // to the original dispatcher. This happens because the caller
        // is waiting for the result of the listeners of this event.
        if ($halt || ! $this->isTransactionalEvent($event)) {
            return $this->dispatcher->dispatch($event, $payload, $halt);
        }

        $this->addPendingEvent($event, $payload);
    }

    /**
     * Prepare a new transaction.
     *
     * @return void
     */
    protected function onTransactionBegin() : void
    {
        $transactionNode = new ValueNode(new Collection());

        $this->currentTransaction = is_null($this->currentTransaction)
            ? $transactionNode
            : $this->currentTransaction->add($transactionNode);

        $this->currentTransaction = $transactionNode;
    }

    /**
     * Add a pending transactional event to the current transaction.
     *
     * @param  string|object $event
     * @param  mixed $payload
     * @return void
     */
    protected function addPendingEvent($event, $payload) : void
    {
        $eventData = [
            'event' => $event,
            'payload' => is_object($payload) ? clone $payload : $payload,
        ];

        $this->currentTransaction->getValue()->push($eventData);
        $this->events[$this->nextEventIndex++] = $eventData;
    }

    /**
     * Handle transaction commit.
     *
     * @return void
     */
    private function onTransactionCommit() : void
    {
        $committedTransaction = $this->finishTransaction();

        if (! $committedTransaction->isRoot()) {
            return;
        }

        $this->dispatchPendingEvents();
    }

    /**
     * Clear enqueued events for the rollbacked transaction.
     *
     * @return void
     */
    private function onTransactionRollback() : void
    {
        $rolledBackTransaction = $this->finishTransaction();

        if ($rolledBackTransaction->isRoot()) {
            $this->resetEvents();

            return;
        }

        $this->nextEventIndex -= $rolledBackTransaction->getValue()->count();
    }

    /**
     * Flush all pending events.
     *
     * @return void
     */
    private function dispatchPendingEvents() : void
    {
        // Prevent loops on event dispacthing. (See #12)
        $events = $this->events;
        $eventsCount = $this->nextEventIndex;
        $this->resetEvents();

        for ($i = 0; $i < $eventsCount; $i++) {
            $event = $events[$i];
            $this->dispatcher->dispatch($event['event'], $event['payload']);
        }
    }

    /**
     * Check whether an event is a transactional event or not.
     *
     * @param  string|object $event
     * @return bool
     */
    private function isTransactionalEvent($event) : bool
    {
        if (is_null($this->currentTransaction)) {
            return false;
        }

        return $this->shouldHandle($event);
    }

    /**
     * Finish current transaction.
     *
     * @return \drupol\phptree\Node\ValueNodeInterface
     */
    private function finishTransaction() : ValueNodeInterface
    {
        $finished = $this->currentTransaction;
        $this->currentTransaction = $finished->getParent();

        return $finished;
    }

    /**
     * Reset events list.
     *
     * @return void
     */
    private function resetEvents() : void
    {
        $this->events = [];
        $this->nextEventIndex = 0;
    }

    /**
     * Check whether an event should be handled by this layer or not.
     *
     * @param  string|object  $event
     * @return bool
     */
    private function shouldHandle($event) : bool
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
    private function matches($pattern, $event) : bool
    {
        return (Str::contains($pattern, '*') && Str::is($pattern, $event))
            || Str::startsWith($event, $pattern);
    }

    /**
     * Setup listeners for transaction events.
     *
     * @return void
     */
    private function setUpListeners() : void
    {
        $this->dispatcher->listen(TransactionBeginning::class, function () {
            $this->onTransactionBegin();
        });

        $this->dispatcher->listen(TransactionCommitted::class, function () {
            $this->onTransactionCommit();
        });

        $this->dispatcher->listen(TransactionRolledBack::class, function () {
            $this->onTransactionRollback();
        });
    }
}
