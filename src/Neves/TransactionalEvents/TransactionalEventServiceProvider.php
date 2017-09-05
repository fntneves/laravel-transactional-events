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
        if (config('transactional-events.enable', false)) {
            $this->app->extend('events', function () {
                $dispatcher = new TransactionalDispatcher(
                    $this->app->make('db'),
                    $this->app->make(EventDispatcher::class)
                );

                $dispatcher->setEnabledEvents(config('transactional-events.events'));
                $dispatcher->setEnabledEvents(config('transactional-events.except'));

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
