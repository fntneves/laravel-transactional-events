<?php

namespace Neves\Events;

use Closure;
use Illuminate\Contracts\Events\Dispatcher as DispatcherContract;

if (! function_exists('Neves\Events\transactional')) {
    /**
     * Build a transactional event for the given closure.
     *
     * @param  \Closure  $callable
     * @return void
     */
    function transactional(Closure $callable)
    {
        $dispatcher = app(DispatcherContract::class);

        $dispatcher->dispatch(new TransactionalClosureEvent($callable));
    }
}
