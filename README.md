# Transaction-aware Event Dispatcher for Laravel (and Lumen)

[![Latest Stable Version](https://poser.pugx.org/fntneves/laravel-transactional-events/v/stable)](https://packagist.org/packages/fntneves/laravel-transactional-events)
<a href="https://travis-ci.org/fntneves/laravel-transactional-events"><img src="https://travis-ci.org/fntneves/laravel-transactional-events.svg?branch=master" alt="TravisCI Status"></a>
<a href="https://scrutinizer-ci.com/g/fntneves/laravel-transactional-events/?branch=master"><img src="https://scrutinizer-ci.com/g/fntneves/laravel-transactional-events/badges/quality-score.png?b=master" alt="Scrutinizer Code Quality"></a>
[![Total Downloads](https://poser.pugx.org/fntneves/laravel-transactional-events/downloads)](https://packagist.org/packages/fntneves/laravel-transactional-events)


This package adds a transaction-aware layer on top of the Laravel Event Dispatcher.<br/>
Basically, it holds events dispatched in a database transaction until the transaction successfully commits.<br/>
In case of transaction failure, the events are discarded and never dispatched. 

* [Introduction](#introduction)
* [Installation](#installation)
    * [Laravel](#laravel)
    * [Lumen](#lumen)
* [Usage](#usage)
* [Configuration](#configuration)
* [F.A.Q.](#frequently-asked-questions)
* [Known Issues](#known-issues)

## Introduction

Consider the following example of ordering tickets that involves database changes and payment operation.
The custom event `OrderWasProcessed` is dispatched immediately after the order is processed in the database.

```php
DB::transaction(function() {
    $user = User::find(...);
    $concert = Concert::find(...);
    $order = $concert->orderTickets($user, 3);
    event(new OrderWasProcessed($order));
    PaymentService::registerOrder($order);
});
```

The transaction in the above example may fail due to several reasons. For instance, due to an exception in the `orderTickets` method or cause by the Payment Service package or simply due to a deadlock.

The failed transaction will undo the database changes performed during the transaction.
This is not true however for the `OrderWasProcessed` event, which was dispatched and eventually executed or enqueued. 
Preventing the event to be dispatch may prevent embarrassing situations like confirmation emails sent after orders failure.

The purpose of this package is to actually dispatch events **if and only if** the transaction in which they were dispatched commits. For instance, in the above example the `OrderWasProcessed` event would not be dispatched if the transaction fails.

Please note that events dispatched out of transactions will bypass the transactional layer, meaning that they will be handled by the default Event Dispatcher. This is true also for events in which the `$halt` parameter is set to `true`.

## Installation

 Laravel  | Package
:---------|:----------
 5.5.x-5.7.x   | 1.4.x
 5.8.x-7.x     | 1.8.x

* [Laravel](#laravel) (5.5+)
* [Lumen](#lumen) (5.5+)

### Laravel
The installation of this package in Laravel is automatic thanks to the _Package Auto-Discovery_ feature of Laravel 5.5+.
Just add this package to the `composer.json` file and it will be ready for your application.

```
composer require fntneves/laravel-transactional-events
```

A configuration file is also available for this package. Run the following command to copy the provided configuration file `transactional-events.php` your `config` folder.

```
php artisan vendor:publish --provider="Neves\Events\EventServiceProvider"
```

### Lumen

As Lumen is built on top of Laravel packages, this package should also work smoothly on this micro-web framework.
Run the following command to install this package:

``` bash
composer require fntneves/laravel-transactional-events
```

In order to configure the behavior of this package, copy the configuration files:

```bash
cp vendor/fntneves/laravel-transactional-events/src/config/transactional-events.php config/transactional-events.php
```

Then, in `bootstrap/app.php`, register the configuration and the service provider:<br/>
*Note:* This package must be registered _after_ the default EventServiceProvider, so your event listeners are not overriden.

```php
// The default EventServiceProvider must be registered.
$app->register(App\Providers\EventServiceProvider::class);

...

$app->configure('transactional-events');
$app->register(Neves\Events\EventServiceProvider::class);
```

## Usage

The transactional layer is enabled out of the box for the events placed under the `App\Events` namespace.

Additionally, this package offers three distinct ways to execute transactional-aware events or custom behavior:
- Implement the `Neves\Events\Contracts\TransactionalEvent` contract
- Use the `transactional` helper to pass a custom closure to be executed once transaction commits
- Change the [configuration file](#configuration) provided by this package (not recommended)

#### Use the contract, Luke:

The easiest way to make your events behave as transactional events is by implementing the contract `Neves\Events\Contracts\TransactionalEvent`.<br/>
*Note that events that implement it will behave as transactional events even when marked as excluded in config.*

```php
namespace App\Events;

use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
...
use Neves\Events\Contracts\TransactionalEvent;

class TicketsOrdered implements TransactionalEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    ...
}
```
As this package does not require any changes in your code, you are to use `Event` facade, call the `event()` or `broadcast()` helper to dispatch an event as follows:

```php
Event::dispatch(new App\Event\TicketsOrdered) // Using Event facade
event(new App\Event\TicketsOrdered) // Using event() helper method
broadcast(new App\Event\TicketsOrdered) // Using broadcast() helper method
```

Even if you are using queues, they will still work smothly because this package does not change the core behavior of the event dispatcher. They will be enqueued as soon as the active transaction succeeds. Otherwise, they will be discarded.

**Reminder:** Events are considered transactional when they are dispatched within transactions. When an event is dispatched out of transactions, it bypasses the transactional layer.

#### What about Jobs?

In version **1.8.8**, this package introduced the `transactional` helper for applying the same behavior to custom instructions without the need to create a specific event.

This helper can be used to ensure that Jobs are dispatched only after the transaction successfully commits:

```php
DB::transaction(function () {
    ...

    transactional(function () {
        // Job will be dispatched only if the transaction commits. 
        ProcessOrderShippingJob::dispatch($order);
    });

    ...
});
```

Under the hood, it creates a *TransactionalClosureEvent* event provided by this package.


## Configuration

The following keys are present in the configuration file:

Enable or disable the transactional behavior by changing the following property:
```php
'enable' => true
```

By default, the transactional behavior will be applied to events on `App\Events` namespace. Feel free to use patterns and namespaces.

```php
'transactional' => [
    'App\Events'
]
```

Choose the events that should always bypass the transactional layer, i.e., should be handled by the default event dispatcher. By default, all `*ed` Eloquent events are excluded. The main reason for this default value is to avoid interference with your already existing event listeners for Eloquent events.

```php
'excluded' => [
    // 'eloquent.*',
    'eloquent.booted',
    'eloquent.retrieved',
    'eloquent.saved',
    'eloquent.updated',
    'eloquent.created',
    'eloquent.deleted',
    'eloquent.restored',
],
```

## Frequently Asked Questions

#### Can I use it for Jobs?

Yes. From version **1.8.8**, as mentioned in [Usage](#usage) section, you can use the `transactional(Closure $callable)` helper to trigger jobs only after the transaction commits.

## Known issues

#### Transactional events are not dispatched in tests.

**This issue is fixed for Laravel 5.6.16+ (see [#23832](https://github.com/laravel/framework/pull/23832)).**
For previous versions, it is associated with the `RefreshDatabase` or `DatabaseTransactions` trait, namely when it uses database transactions to reset database after each test.
This package relies on events dispached when transactions begin/commit/rollback and as each test is executed within a transaction that is rolled back when test finishes, the dispatched application events are never actually dispatched. In order to get the expected behavior, use the `Neves\Testing\RefreshDatabase` or `Neves\Testing\DatabaseTransactions` trait in your tests instead of the ones originally provided by Laravel.

## License
This package is open-sourced software licensed under the [MIT license](http://opensource.org/licenses/MIT).
