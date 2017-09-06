<?php

namespace Neves\Events;

use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Database\Events\TransactionRolledBack;

class EventServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        if (config('transactional-events.enable', true)) {
            $this->app->extend('events', function () {
                $dispatcher = new TransactionalDispatcher(
                    $this->app->make('db'),
                    $this->app->make(EventDispatcher::class)
                );

                $dispatcher->setTransactionalEvents(config('transactional-events.events', []));
                $dispatcher->setExcludedEvents(config('transactional-events.exclude', []));

                $dispatcher->listen(TransactionCommitted::class, function ($event) use ($dispatcher) {
                    $dispatcher->commit($event->connection);
                });

                $dispatcher->listen(TransactionRolledBack::class, function ($event) use ($dispatcher) {
                    $dispatcher->rollback($event->connection);
                });

                return $dispatcher;
            });
        }
    }

    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../../config/transactional-events.php' => config_path('transactional-events.php'),
        ]);

        $this->mergeConfigFrom(
            __DIR__.'/../../config/transactional-events.php', 'transactional-events'
        );
    }
}
