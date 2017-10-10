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
        if (! $this->app['config']->get('transactional-events.enable', true)) {
            return;
        }

        $connectionResolver = $this->app->make('db');
        $eventDispatcher = $this->app->make(EventDispatcher::class);
        $this->app->extend('events', function () use ($connectionResolver, $eventDispatcher) {
            $dispatcher = new TransactionalDispatcher($connectionResolver, $eventDispatcher);

            if (is_array($transactional = $this->app['config']->get('transactional-events.transactional'))) {
                $dispatcher->setTransactionalEvents($transactional);
            }

            $dispatcher->setExcludedEvents($this->app['config']->get('transactional-events.excluded', []));

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
            __DIR__.'/../../config/transactional-events.php',
            'transactional-events'
        );
    }
}
