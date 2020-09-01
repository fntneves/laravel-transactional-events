<?php

namespace Neves\Events\Concerns;

trait DelegatesToDispatcher
{
    /**
     * Register an event listener with the dispatcher.
     *
     * @param  \Closure|string|array  $events
     * @param  \Closure|string|null  $listener
     * @return void
     */
    public function listen($events, $listener = null)
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
}
