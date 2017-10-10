<?php

use Orchestra\Testbench\TestCase;
use Neves\Events\EventServiceProvider;

class TransactionalDispatcherTest extends TestCase
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
    public function it_immediately_dispatches_event_out_of_transactions()
    {
        $this->dispatcher->listen('foo', function () {
            $_SERVER['__events'] = 'bar';
        });

        $this->dispatcher->dispatch('foo');

        $this->assertEquals('bar', $_SERVER['__events']);
    }

    /** @test */
    public function it_dispatches_events_only_after_transaction_commit()
    {
        $this->dispatcher->listen('foo', function () {
            $_SERVER['__events'] = 'bar';
        });

        DB::transaction(function () {
            $this->dispatcher->dispatch('foo');
            $this->assertArrayNotHasKey('__events', $_SERVER);
        });

        $this->assertEquals('bar', $_SERVER['__events']);
    }

    /** @test */
    public function it_does_not_dispatch_events_after_nested_transaction_commit()
    {
        $this->dispatcher->listen('foo', function () {
            $_SERVER['__events'] = 'bar';
        });

        DB::transaction(function () {
            DB::transaction(function () {
                $this->dispatcher->dispatch('foo');
            });
            $this->assertArrayNotHasKey('__events', $_SERVER);
        });

        $this->assertEquals('bar', $_SERVER['__events']);
    }

    /** @test */
    public function it_does_not_dispatch_events_after_nested_transaction_rollback()
    {
        $this->dispatcher->listen('foo', function () {
            $_SERVER['__events'] = 'bar';
        });

        try {
            DB::transaction(function () {
                DB::transaction(function () {
                    $this->dispatcher->dispatch('foo');
                    throw new \Exception;
                });
            });
        } catch (\Exception $e) {
            //
        }

        $this->assertArrayNotHasKey('__events', $_SERVER);
    }

    /** @test */
    public function it_does_not_dispatch_events_after_outer_transaction_rollback()
    {
        $this->dispatcher->listen('foo', function () {
            $_SERVER['__events'] = 'bar';
        });

        try {
            DB::transaction(function () {
                DB::transaction(function () {
                    $this->dispatcher->dispatch('foo');
                });
                throw new \Exception;
            });
        } catch (\Exception $e) {
            //
        }

        $this->assertArrayNotHasKey('__events', $_SERVER);
    }

    /** @test */
    public function it_immediately_dispatches_events_present_in_exceptions_list()
    {
        $this->dispatcher->setExcludedEvents(['foo']);
        $this->dispatcher->listen('foo', function () {
            $_SERVER['__events'] = 'bar';
        });

        DB::transaction(function () {
            $this->dispatcher->dispatch('foo');
            $this->assertEquals('bar', $_SERVER['__events']);
        });
    }

    /** @test */
    public function it_immediately_dispatches_events_not_present_in_enabled_list()
    {
        $this->dispatcher->setTransactionalEvents(['bar']);
        $this->dispatcher->listen('foo', function () {
            $_SERVER['__events'] = 'bar';
        });

        DB::transaction(function () {
            $this->dispatcher->dispatch('foo');
            $this->assertEquals('bar', $_SERVER['__events']);
        });
    }

    /** @test */
    public function it_immediately_dispatches_events_that_do_not_match_a_pattern()
    {
        $this->dispatcher->setTransactionalEvents(['foo/*']);
        $this->dispatcher->listen('foo', function () {
            $_SERVER['__events'] = 'bar';
        });

        DB::transaction(function () {
            $this->dispatcher->dispatch('foo');
            $this->assertEquals('bar', $_SERVER['__events']);
        });
    }

    /** @test */
    public function it_enqueues_events_that_do_match_a_pattern()
    {
        $this->dispatcher->setTransactionalEvents(['foo/*']);
        $this->dispatcher->listen('foo/bar', function () {
            $_SERVER['__events'] = 'bar';
        });

        DB::transaction(function () {
            $this->dispatcher->dispatch('foo/bar');
            $this->assertArrayNotHasKey('__events', $_SERVER);
        });

        $this->assertEquals('bar', $_SERVER['__events']);
    }

    /** @test */
    public function it_immediately_dispatches_specific_events_excluded_on_a_pattern()
    {
        $this->dispatcher->setTransactionalEvents(['foo/*']);
        $this->dispatcher->setExcludedEvents(['foo/bar']);
        $this->dispatcher->listen('foo/bar', function () {
            $_SERVER['__events.bar'] = 'bar';
        });
        $this->dispatcher->listen('foo/zen', function () {
            $_SERVER['__events.zen'] = 'zen';
        });

        DB::transaction(function () {
            $this->dispatcher->dispatch('foo/bar');
            $this->dispatcher->dispatch('foo/zen');
            $this->assertEquals('bar', $_SERVER['__events.bar']);
            $this->assertArrayNotHasKey('__env.test.zen', $_SERVER);
        });
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
