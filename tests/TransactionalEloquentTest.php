<?php

use Orchestra\Testbench\TestCase;
use Neves\Events\EventServiceProvider;

class TransactionalEloquentTest extends TestCase
{
    protected $dispatcher;

    public function setUp()
    {
        parent::setUp();

        unset($_SERVER['__events']);

        $this->dispatcher = $this->app['events'];
        $this->dispatcher->setTransactionalEvents(['*']);
    }

    /** @test */
    public function it_does_not_handle_eloquent_events_by_default()
    {
        $this->dispatcher->listen('eloquent.*', function () {
            $_SERVER['__events'] = 'bar';
        });

        DB::transaction(function () {
            $this->dispatcher->dispatch('eloquent.booted');
            $this->dispatcher->dispatch('eloquent.retrieved');
            $this->dispatcher->dispatch('eloquent.created');
            $this->dispatcher->dispatch('eloquent.saved');
            $this->dispatcher->dispatch('eloquent.updated');
            $this->dispatcher->dispatch('eloquent.created');
            $this->dispatcher->dispatch('eloquent.deleted');
            $this->dispatcher->dispatch('eloquent.restored');

            $this->assertEquals('bar', $_SERVER['__events']);
        });
    }

    /** @test */
    public function it_handles_specified_eloquent_events()
    {
        $this->dispatcher->setExcludedEvents(['eloquent.retrieved']);

        $this->dispatcher->listen('eloquent.saved', function () {
            $_SERVER['__events'] = 'bar';
        });

        DB::transaction(function () {
            $this->dispatcher->dispatch('eloquent.saved');
            $this->assertArrayNotHasKey('__events', $_SERVER);
        });

        $this->assertEquals('bar', $_SERVER['__events']);
    }

    protected function getPackageProviders($app)
    {
        return [EventServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
        ]);
    }
}
