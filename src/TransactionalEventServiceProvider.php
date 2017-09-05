<?php

namespace Neves\TransactionalEvents;

use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;

class TransactionalEventServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->extend('events', function () {
            return new TransactionalDispatcher(
                $this->app->make('db'),
                $this->app->make(EventDispatcher::class)
            );
        });
    }

    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
