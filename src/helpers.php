<?php

use Illuminate\Contracts\Events\Dispatcher as DispatcherContract;
use Neves\Events\TransactionalClosureEvent;

if (! function_exists('transactional')) {
    /**
     * Add an element to an array using "dot" notation if it doesn't exist.
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
