<?php

namespace Neves\Testing;

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
        $database = $this->app->make('db');

        foreach ($this->connectionsToTransact() as $name) {
            $connection = $database->connection($name);

            // Disable event dispatcher in order to avoid event dispatching
            // that could interfer with the behavior of the application
            // under test. Enable it as soon as transaction begins.
            $this->disableEventDispatcher($connection);
            $connection->beginTransaction();
            $this->enableEventDispatcher($connection);
        }

        $this->beforeApplicationDestroyed(function () use ($database) {
            foreach ($this->connectionsToTransact() as $name) {
                $connection = $database->connection($name);

                // Disable event dispatcher in order to avoid event dispatching
                // that could interfer with the behavior of the application
                // under test. Enable as soon as transaction rollbacks.
                $this->disableEventDispatcher($connection);
                $connection->rollBack();
                $this->enableEventDispatcher($connection);

                $connection->disconnect();
            }
        });
    }

    /**
     * Disable the event dispatcher of the given connection.
     *
     * @param  \Illuminate\Database\ConnectionInterface  $connection
     * @return void
     */
    protected function disableEventDispatcher(ConnectionInterface $connection)
    {
        static $emptyDispatcher;

        $emptyDispatcher = new \Illuminate\Events\Dispatcher;

        $this->currentDisabledDispatcher = $connection->getEventDispatcher();
        $connection->setEventDispatcher($emptyDispatcher);
    }

    /**
     * Enable the event dispatcher of the given connection.
     *
     * @param  \Illuminate\Database\ConnectionInterface  $connection
     * @return void
     */
    protected function enableEventDispatcher(ConnectionInterface $connection)
    {
        $connection->setEventDispatcher($this->currentDisabledDispatcher);
        $this->currentDisabledDispatcher = null;
    }
}
