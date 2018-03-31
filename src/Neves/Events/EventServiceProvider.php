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
        $this->mergeConfigFrom(
            __DIR__.'/../../config/transactional-events.php',
            'transactional-events'
        );

        if (! $this->app['config']->get('transactional-events.enable')) {
            return;
        }

        $connectionResolver = $this->app->make('db');
        $eventDispatcher = $this->app->make(EventDispatcher::class);
        $this->app->extend('events', function () use ($connectionResolver, $eventDispatcher) {
            $dispatcher = new TransactionalDispatcher($connectionResolver, $eventDispatcher);
            $dispatcher->setTransactionalEvents($this->app['config']->get('transactional-events.transactional'));
            $dispatcher->setExcludedEvents($this->app['config']->get('transactional-events.excluded'));

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
        $configPath = $this->app->basePath().'/config';

        $this->publishes([
            __DIR__.'/../../config/transactional-events.php' => $configPath.'/transactional-events.php',
        ]);
    }
}
