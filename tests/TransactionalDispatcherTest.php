<?php

use Orchestra\Testbench\TestCase;
use Neves\Events\EventServiceProvider;
use Neves\Events\TransactionalDispatcher;
use Neves\Events\Contracts\TransactionalEvent;

class TransactionalDispatcherTest extends TestCase
{
    protected $dispatcher;

    public function setUp(): void
    {
        parent::setUp();

        unset($_SERVER['__events']);

        $this->dispatcher = $this->app['events'];
        $this->dispatcher->setTransactionalEvents(['*']);
    }

    /** @test */
    public function it_is_enabled_by_default()
    {
        $this->assertEquals(TransactionalDispatcher::class, get_class($this->dispatcher));
    }

    /** @test */
    public function it_does_not_override_previous_dispatcher_instance()
    {
        // Trigger listeners of a previous registered event.
        $this->dispatcher->dispatch('previous_bound_event');

        $this->assertEquals('OK', $_SERVER['__previous_listener']);
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
    public function it_dispatches_events_only_after_transaction_commits()
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
    public function it_handles_events_that_implement_the_transactional_contract_without_explicit_configuration()
    {
        $this->dispatcher->setTransactionalEvents(['foo/']);
        $this->dispatcher->listen(CustomEvent::class, function () {
            $_SERVER['__events'] = 'bar';
        });

        DB::transaction(function () {
            $this->dispatcher->dispatch(new CustomEvent());
            $this->assertArrayNotHasKey('__events', $_SERVER);
        });

        $this->assertEquals('bar', $_SERVER['__events']);
    }

    /** @test */
    public function it_forgets_dispatched_events_after_transaction_commits()
    {
        $this->dispatcher->listen('foo', function () {
            $_SERVER['__events'] = 'bar';
        });
        $this->dispatcher->listen('bar', function () {
            $_SERVER['__events'] = 'zen';
        });

        DB::transaction(function () {
            $this->dispatcher->dispatch('foo');
            $this->dispatcher->dispatch('bar');
        });

        DB::transaction(function () {
            unset($_SERVER['__events']);
        });

        $this->assertArrayNotHasKey('__events', $_SERVER);
    }

    /** @test */
    public function it_does_not_forget_dispatched_events_on_the_same_transaction_level_after_a_rollback()
    {
        $this->dispatcher->listen('foo', function ($payload) {
            $_SERVER['__events'] = $_SERVER['__events'] ?? [];
            $_SERVER['__events'][] = $payload;
        });

        DB::transaction(function () {
            $this->dispatcher->dispatch('foo', 'first');

            DB::transaction(function () {
                $this->dispatcher->dispatch('foo', 'second');
            });

            try {
                DB::transaction(function () {
                    $this->dispatcher->dispatch('foo', 'third');
                    throw new \Exception;
                });
            } catch (\Exception $e) {
                //
            }
            $this->dispatcher->dispatch('foo', 'fourth');
        });

        $this->assertEquals(3, count($_SERVER['__events']));
        $this->assertEquals('first', $_SERVER['__events'][0]);
        $this->assertEquals('second', $_SERVER['__events'][1]);
        $this->assertEquals('fourth', $_SERVER['__events'][2]);
    }

    /** @test */
    public function it_does_not_dispatch_events_after_nested_transaction_commits()
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
    public function it_does_not_dispatch_events_after_nested_transaction_rollbacks()
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
    public function it_enqueues_events_that_do_match_an_pattern()
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

    /**
     * Regression test: Fix infinite loop caused by TransactionCommitted (#12).
     * @test
     */
    public function nested_transactions_on_dispatch_does_not_cause_infinite_loop()
    {
        $count = 0;
        $this->dispatcher->listen('foo', function () use (&$count) {
            DB::transaction(function () use (&$count) {
                if ($count > 1) {
                    $this->fail('Infinite loop while dispatching events (See #12).');
                }

                $count++;
            });
        });

        DB::transaction(function () {
            $this->dispatcher->dispatch('foo');
        });

        $this->assertEquals(1, $count);
    }

    /**
     * Regression test: Nested multiple connections problems (#23).
     * @test
     */
    public function it_works_with_non_default_connections()
    {
        $this->dispatcher->listen('foo', function ($payload) {
            $_SERVER['__events'] = $_SERVER['__events'] ?? [];
            $_SERVER['__events'][] = $payload;
        });

        DB::transaction(function () {
            $this->dispatcher->dispatch('foo', 'first');

            DB::connection('other')->transaction(function () {
                $this->dispatcher->dispatch('foo', 'second');
            });
        });

        $this->assertEquals(2, count($_SERVER['__events']));
        $this->assertEquals('first', $_SERVER['__events'][0]);
        $this->assertEquals('second', $_SERVER['__events'][1]);
    }

    /**
     * Regression test: Call to a member function getParent() on null (#27).
     * This reproduces the use of DatabaseTransactions and RefreshDatabase traits.
     * @test
     */
    public function it_ignores_commits_or_rollbacks_when_transactions_are_not_running()
    {
        $this->withoutEvents(function () {
            DB::beginTransaction();
        });

        $this->dispatcher->listen('foo', function () {
            throw new \Exception();
        });

        try {
            DB::beginTransaction();
            $this->dispatcher->dispatch('foo');
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
        }

        $this->withoutEvents(function () {
            DB::rollBack();
        });

        $this->assertTrue(true);
    }

    protected function getPackageProviders($app)
    {
        // Add an event listener to the previous event dispatcher.
        $app['events']->listen('previous_bound_event', function () {
            $_SERVER['__previous_listener'] = 'OK';
        });

        return [EventServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
        ]);
        $app['config']->set('database.connections.other', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
        ]);
    }

    protected function withoutEvents(Closure $callback)
    {
        // Disable Transactional dispatcher.
        DB::connection()->unsetEventDispatcher();

        try {
            $callback();
        } catch (\Exception $e) {
            throw $e;
        } finally {
            // Restore the Transactional dispatcher.
            DB::connection()->setEventDispatcher($this->dispatcher);
        }
    }
}

class CustomEvent implements TransactionalEvent
{
    //
}
