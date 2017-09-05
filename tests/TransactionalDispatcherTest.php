<?php

use Mockery as m;
use PHPUnit\Framework\TestCase;
use Illuminate\Events\Dispatcher;
use Neves\Events\TransactionalDispatcher;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\ConnectionResolverInterface;

class TransactionalDispatcherTest extends TestCase
{
    protected $connectionResolverMock;

    protected $dispatcher;

    public function tearDown()
    {
        m::close();
    }

    public function setUp()
    {
        unset($_SERVER['__events.test']);
        unset($_SERVER['__events.test.bar']);
        unset($_SERVER['__events.test.zen']);

        $this->connectionResolverMock = m::mock(ConnectionResolverInterface::class);
        $this->dispatcher = new TransactionalDispatcher($this->connectionResolverMock, new Dispatcher());
        $this->dispatcher->setTransactionalEvents(['*']);
    }

    /** @test */
    public function it_immediately_dispatches_event_out_of_transactions()
    {
        $this->dispatcher->listen('foo', function () {
            $_SERVER['__events.test'] = 'bar';
        });
        $this->setupTransactionLevel(0);

        $this->dispatcher->dispatch('foo');

        $this->assertFalse($this->hasCommitListeners());
        $this->assertEquals('bar', $_SERVER['__events.test']);
    }

    /** @test */
    public function it_enqueues_event_dispatched_in_transactions()
    {
        $this->dispatcher->listen('foo', function () {
            $_SERVER['__events.test'] = 'bar';
        });
        $this->setupTransactionLevel(1);

        $this->dispatcher->dispatch('foo');

        $this->assertTrue($this->hasCommitListeners());
        $this->assertArrayNotHasKey('__events.test', $_SERVER);
    }

    /** @test */
    public function it_dispatches_events_on_commit()
    {
        $this->dispatcher->listen('foo', function () {
            $_SERVER['__events.test'] = 'bar';
        });
        $this->setupTransactionLevel(1);
        $this->dispatcher->dispatch('foo');

        $this->dispatcher->commit($this->getConnection());

        $this->assertFalse($this->hasCommitListeners());
        $this->assertEquals('bar', $_SERVER['__events.test']);
    }

    /** @test */
    public function it_forgets_enqueued_events_on_rollback()
    {
        $this->dispatcher->listen('foo', function () {
            $_SERVER['__events.test'] = 'bar';
        });
        $this->setupTransactionLevel(1);
        $this->dispatcher->dispatch('foo');

        $this->dispatcher->rollback($this->getConnection());

        $this->assertFalse($this->hasCommitListeners());
        $this->assertArrayNotHasKey('__events.test', $_SERVER);
    }

    /** @test */
    public function it_immediately_dispatches_events_present_in_exceptions_list()
    {
        $this->dispatcher->listen('foo', function () {
            $_SERVER['__events.test'] = 'bar';
        });

        $this->setupTransactionLevel(1);
        $this->dispatcher->setExcludedEvents(['foo']);
        $this->dispatcher->dispatch('foo');

        $this->assertFalse($this->hasCommitListeners());
        $this->assertEquals('bar', $_SERVER['__events.test']);
    }

    /** @test */
    public function it_immediately_dispatches_events_not_present_in_enabled_list()
    {
        $this->dispatcher->listen('foo', function () {
            $_SERVER['__events.test'] = 'bar';
        });

        $this->setupTransactionLevel(1);
        $this->dispatcher->setTransactionalEvents(['bar']);
        $this->dispatcher->dispatch('foo');

        $this->assertFalse($this->hasCommitListeners());
        $this->assertEquals('bar', $_SERVER['__events.test']);
    }

    /** @test */
    public function it_immediately_dispatches_events_that_do_not_match_a_pattern()
    {
        $this->dispatcher->listen('foo', function () {
            $_SERVER['__events.test'] = 'bar';
        });

        $this->setupTransactionLevel(1);
        $this->dispatcher->setTransactionalEvents(['foo/*']);
        $this->dispatcher->dispatch('foo');

        $this->assertFalse($this->hasCommitListeners());
        $this->assertEquals('bar', $_SERVER['__events.test']);
    }

    /** @test */
    public function it_enqueues_events_that_do_match_a_pattern()
    {
        $this->dispatcher->listen('foo/bar', function () {
            $_SERVER['__events.test'] = 'bar';
        });

        $this->setupTransactionLevel(1);
        $this->dispatcher->setTransactionalEvents(['foo/*']);
        $this->dispatcher->dispatch('foo/bar');

        $this->assertTrue($this->hasCommitListeners());
        $this->assertArrayNotHasKey('__events.test', $_SERVER);
    }

    /** @test */
    public function it_immediately_dispatches_specific_events_excluded_on_a_pattern()
    {
        $this->dispatcher->listen('foo/bar', function () {
            $_SERVER['__events.test.bar'] = 'bar';
        });

        $this->dispatcher->listen('foo/zen', function () {
            $_SERVER['__events.test.zen'] = 'zen';
        });

        $this->setupTransactionLevel(1);
        $this->dispatcher->setTransactionalEvents(['foo/*']);
        $this->dispatcher->setExcludedEvents(['foo/bar']);
        $this->dispatcher->dispatch('foo/bar');
        $this->dispatcher->dispatch('foo/zen');

        $this->assertTrue($this->hasCommitListeners());
        $this->assertEquals('bar', $_SERVER['__events.test.bar']);
        $this->assertArrayNotHasKey('__env.test.zen', $_SERVER);
    }

    /** @test */
    public function it_enqueues_events_matching_a_namespace_patterns()
    {
        $event = m::mock('\\Neves\\Event');
        $this->dispatcher->listen('\\Neves\\Event', function () {
            $_SERVER['__events.test'] = 'bar';
        });

        $this->setupTransactionLevel(1);
        $this->dispatcher->dispatch($event);

        $this->assertTrue($this->hasCommitListeners());
        $this->assertArrayNotHasKey('__events.test', $_SERVER);
    }

    /** @test */
    public function it_dispatches_events_matching_a_namespace_patterns()
    {
        $event = m::mock('overload:\\App\\Neves\\Event');
        $this->dispatcher->listen(get_class($event), function () {
            $_SERVER['__events.test'] = 'bar';
        });

        $this->setupTransactionLevel(1);
        $this->dispatcher->setTransactionalEvents(['App\*']);
        $this->dispatcher->dispatch($event);
        $this->dispatcher->commit($this->getConnection());

        $this->assertFalse($this->hasCommitListeners());
        $this->assertEquals('bar', $_SERVER['__events.test']);
    }

    private function hasCommitListeners()
    {
        $connectionId = spl_object_hash($this->connectionResolverMock->connection());

        return $this->dispatcher->hasListeners($connectionId.'_commit');
    }

    private function getConnection()
    {
        return $this->connectionResolverMock->connection();
    }

    private function setupTransactionLevel($level = 1)
    {
        $connection = m::mock(ConnectionInterface::class)
            ->shouldReceive('transactionLevel')
            ->andReturn($level)
            ->mock();

        $this->connectionResolverMock = $this->connectionResolverMock
            ->shouldReceive('connection')
            ->andReturn($connection)
            ->mock();
    }
}
