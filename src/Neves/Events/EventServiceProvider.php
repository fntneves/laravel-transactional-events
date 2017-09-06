<?php

namespace Neves\Events;

use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;

class EventServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        if (! config('transactional-events.enable', true)) {
            return;
        }

        $this->app->extend('events', function () {
            $dispatcher = new TransactionalDispatcher(
                $this->app->make('db'),
                $this->app->make(EventDispatcher::class)
            );

            if (is_array($transactional = config('transactional-events.transactional'))) {
                $dispatcher->setTransactionalEvents($transactional);
            }

            $dispatcher->setExcludedEvents(config('transactional-events.excluded', []));

            return $dispatcher;
        });
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
