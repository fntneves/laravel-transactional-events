# Transaction-aware Event Dispatcher for Laravel (and Lumen)

<a href="https://travis-ci.org/fntneves/laravel-transactional-events"><img src="https://travis-ci.org/fntneves/laravel-transactional-events.svg?branch=master" alt="TravisCI Status"></a>
<a href="https://packagist.org/packages/fntneves/laravel-transactional-events"><img src="https://poser.pugx.org/fntneves/laravel-transactional-events/v/stable" alt="Latest Stable Version"></a>

This package introduces a transactional layer to the Laravel Event Dispatcher. Its purpose is to achieve, without changing a single line of code, a better consistency between events dispatched during database transactions. This behavior is also applicable to Eloquent events, such as `saved` and `created`, by changing the configuration file.

* [Introduction](#introduction)
* [Installation](#installation)
    * [Laravel](#laravel) (5.5+)
    * [Lumen](#lumen) (5.5+)
* [Usage](#usage)
* [Configuration](#configuration)

## Introduction

Let's start with an example representing a simple process of ordering tickets. Assume this involves database changes and a payment registration. A custom event is dispatched when the order is processed in the database.

```php
DB::transaction(function() {
    $user = User::find(...);
    $concert = Concert::find(...);
    $order = $concert->orderTickets($user, 3);
    event(new OrderWasProcessed($order));
    PaymentService::registerOrder($order);
});
```

The transaction in the above example may fail for several reasons. For instance, it may fail in the `orderTickets` method or in the payment service or just simply due to a deadlock.

A failure will rollback database changes made during the transactionk. However, the `OrderWasProcessed` event is actually dispatched and eventually will be executed.

The purpose of this package is to ensure that events are dispatched **if and only if** the transaction in which they were dispatched commits. According to the example, this package guarantees that the `OrderWasProcessed` event is not dispatched if the transaction does not commit.

However, when events are dispatched out of transactions, they will bypass the transactional layer, meaning that it will be handled by the default Event Dispatcher. This is true also for events that where the `$halt` parameter is set to `true`.

## Installation

* [Laravel](#laravel) (5.5+)
* [Lumen](#lumen) (5.5+)

### Laravel
The installation of this package in Laravel is automatic thanks to the _Package Auto-Discovery_ feature of Laravel 5.5.
Just add this package to the `composer.json` file and it will be ready for your application.

```
composer require fntneves/laravel-transactional-events
```

A configuration file is also part of this package. Run the following command to copy the provided configuration file `transactional-events.php` to your `config` folder.

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
// The default EventServiceProvider must be registered before.
$app->register(App\Providers\EventServiceProvider::class);

...

$app->configure('transactional-events');
$app->register(Neves\Events\EventServiceProvider::class);
```

## Usage

The transactional layer is enabled by default for the events placed under the `App\Events` namespace.

However, the easiest way to enable transactional behavior on your events is to implement the contract `Neves\Events\Contracts\TransactionalEvent`.<br/>
*Note that events that implement it will become transactional even when excluded in config.*

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

As this package does not require any changes in code, you are still able to use the `Event` facade and call the `event()` or `broadcast()` helper to dispatch an event:

```php
Event::dispatch(new App\Event\TicketsOrdered) // Using Event facade
event(new App\Event\TicketsOrdered) // Using event() helper method
broadcast(new App\Event\TicketsOrdered) // Using broadcast() helper method
```

Even if you are using queues, they will still work because this package does not change the core behavior of the event dispatcher. However, they will be enqueued as soon as the active transaction succeeds. Otherwise, they will be discarded.

**Reminder:** Events are considered as transactional when they are dispatched within transactions. When an event is dispatched out of transactions, they bypass the transactional layer.


## Configuration

The following keys are present in the configuration file:

The transactional behavior of events can be enabled or disabled by changing the following property:
```php
'enable' => true
```

By default, the transactional behavior will be applied to events on `App\Events` namespace. Feel free to use patterns and namespaces.

```php
'transactional' => ['App\Events']
```

Choose specific events that should always bypass the transactional layer, i.e., should be handled by the default event dispatcher. By default, all `*ed` Eloquent events are excluded.

```php
'excluded' => [
    // 'eloquent.*',
    'eloquent.booted',
    'eloquent.retrieved',
    'eloquent.created',
    'eloquent.saved',
    'eloquent.updated',
    'eloquent.created',
    'eloquent.deleted',
    'eloquent.restored',
],
```

## Known issues

> Events are not dispatched when tests use transactions to reset database.

It is related to the dispatched events related to transactions. I introduced an issue in Laravel internals (https://github.com/laravel/ideas/issues/1094) to discuss the possibility to disable transaction events, so they do not interfer with the application. For now, you can use the following solution: https://gist.github.com/fntneves/7f0b99767fce369919211148942eb297

## License
This package is open-sourced software licensed under the [MIT license](http://opensource.org/licenses/MIT).
