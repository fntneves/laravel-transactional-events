<?php

use Illuminate\Contracts\Events\Dispatcher as DispatcherContract;
use Neves\Events\TransactionalClosureEvent;

if (! function_exists('transactional')) {
    /**
     * Build a transactional event for the given closure.
     *
     * @param \Closure $callable
     * @return void
     */
    function transactional(Closure $callable)
    {
        $dispatcher = app(DispatcherContract::class);

        $dispatcher->dispatch(new TransactionalClosureEvent($callable));
    }
}
