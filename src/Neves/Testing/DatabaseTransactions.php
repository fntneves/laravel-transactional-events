<?php

namespace Neves\Testing;

use Illuminate\Foundation\Testing\DatabaseTransactions as BaseDatabaseTransactions;

trait DatabaseTransactions
{
    use BaseDatabaseTransactions;

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
