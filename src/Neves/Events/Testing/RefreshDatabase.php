<?php

namespace Neves\Events\Testing;

use Illuminate\Foundation\Testing\RefreshDatabase as BaseRefreshDatabase;

trait RefreshDatabase
{
    use BaseRefreshDatabase;

    /**
     * Begin a database transaction on the testing database.
     *
     * @return void
     */
    public function beginDatabaseTransaction()
    {
        $emptyDispatcher = new \Illuminate\Events\Dispatcher;
        $database = $this->app->make('db');

        foreach ($this->connectionsToTransact() as $name) {
            $connection = $database->connection($name);

            $currentDispatcher = $connection->getEventDispatcher();
            $connection->setEventDispatcher($emptyDispatcher);
            $connection->beginTransaction();
            $connection->setEventDispatcher($currentDispatcher);
        }

        $this->beforeApplicationDestroyed(function () use ($database, $emptyDispatcher) {
            foreach ($this->connectionsToTransact() as $name) {
                $connection = $database->connection($name);

                $currentDispatcher = $connection->getEventDispatcher();
                $connection->setEventDispatcher($emptyDispatcher);
                $connection->rollBack();
                $connection->setEventDispatcher($currentDispatcher);

                $connection->disconnect();
            }
        });
    }
}
