# Transaction-aware Event Dispatcher for Laravel

[![Latest Stable Version](https://poser.pugx.org/fntneves/laravel-transactional-events/v/stable)](https://packagist.org/packages/fntneves/laravel-transactional-events)
<a href="https://travis-ci.org/fntneves/laravel-transactional-events"><img src="https://travis-ci.org/fntneves/laravel-transactional-events.svg?branch=master" alt="TravisCI Status"></a>
<a href="https://scrutinizer-ci.com/g/fntneves/laravel-transactional-events/?branch=master"><img src="https://scrutinizer-ci.com/g/fntneves/laravel-transactional-events/badges/quality-score.png?b=master" alt="Scrutinizer Code Quality"></a>
[![Total Downloads](https://poser.pugx.org/fntneves/laravel-transactional-events/downloads)](https://packagist.org/packages/fntneves/laravel-transactional-events)

This Laravel package introduces Transaction-aware Event Dispatcher.<br>
It ensures the events dispatched within a database transaction are dispatched only if the outer transaction successfully commits. Otherwise, the events are discarded and never dispatched.

*Note: [Laravel 8.17](https://laravel-news.com/laravel-8-17-0) introduced a new method `DB::afterCommit` that allows one to achieve the same of this package. Yet, it falls short of providing the transaction-aware behavior on Eloquent events.*

## Table of Contents

* [Motivation](#motivation)
* [Installation](#installation)
    * [Laravel](#laravel)
    * [Lumen](#lumen)
* [Usage](#usage)
* [Configuration](#configuration)
* [F.A.Q.](#frequently-asked-questions)

## Motivation

Consider the following example of ordering tickets that involves changes to the database.<br/>
The `orderTickets` dispatches the custom `OrderCreated` event.
In turn, its listener sends an email to the user with the order details.

```php
DB::transaction(function() {
    ...
    $order = $concert->orderTickets($user, 3); // internally dispatches 'OrderCreated' event
    PaymentService::registerOrder($order);
});
```

In the case of transaction failure, due to an exception in the `orderTickets` method or even a deadlock, the database changes are completely discarded.

Unfortunately, this is not true for the already dispatched `OrderCreated` event.
This results in sending the order confirmation email to the user, even after the order failure.

The purpose of this package is thus to hold events dispatched within a database transaction until it successfully commits.
In the above example the `OrderCreated` event would never be dispatched in the case of transaction failure.

## Installation

 Laravel  | Package
:---------|:----------
 5.8.x-7.x     | 1.8.x
 8.x           | 2.x

### Laravel
- Install this package via `composer`:

```
composer require fntneves/laravel-transactional-events
```

- Publish the provided `transactional-events.php` configuration file:

```
php artisan vendor:publish --provider="Neves\Events\EventServiceProvider"
```

### Lumen

- Install this package via `composer`:

``` bash
composer require fntneves/laravel-transactional-events
```

- Manually copy the provided `transactional-events.php` configuration file to the `config` folder:

```bash
cp vendor/fntneves/laravel-transactional-events/src/config/transactional-events.php config/transactional-events.php
```

- Register the configuration file and the service provider in `bootstrap/app.php`:<br/>

```php
// Ensure the original EventServiceProvider is registered first, otherwise your event listeners are overriden.
$app->register(App\Providers\EventServiceProvider::class);

$app->configure('transactional-events');
$app->register(Neves\Events\EventServiceProvider::class);
```

## Usage

The transaction-aware layer is enabled out of the box for the events under the `App\Events` namespace.

This package offers three distinct ways to dispatch transaction-aware events:
- Implement the `Neves\Events\Contracts\TransactionalEvent` contract;
- Use the generic `TransactionalClosureEvent` event;
- Use the `Neves\Events\transactional` helper;
- Change the [configuration file](#configuration).

#### Use the contract, Luke:

The simplest way to mark events as transaction-aware events is implementing the `Neves\Events\Contracts\TransactionalEvent` contract:<br/>

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

And that's it. There are no further changes required.

#### What about Jobs?

This package provides a generic `TransactionalClosureEvent` event for bringing the transaction-aware behavior to custom behavior without requiring specific events.

One relevant use case is to ensure that Jobs are dispatched only after the transaction successfully commits:

```php
DB::transaction(function () {
    ...
    Event::dispatch(new TransactionalClosureEvent(function () {
        // Job will be dispatched only if the transaction commits.
        ProcessOrderShippingJob::dispatch($order);
    });
    ...
});
```

And that's it. There are no further changes required.

## Configuration

The configuration file includes the following parameters:

Enable or disable the transaction-aware behavior:
```php
'enable' => true
```

By default, the transaction-aware behavior will be applied to all events under the `App\Events` namespace.
<br/>Feel free to use patterns and namespaces.

```php
'transactional' => [
    'App\Events'
]
```

Choose the events that should always bypass the transaction-aware layer, i.e., should be handled by the original event dispatcher. By default, all `*ed` Eloquent events are excluded. The main reason for this default value is to avoid interference with your already existing event listeners for Eloquent events.

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

Yes. As mentioned in [Usage](#usage), you can use the generic `TransactionalClosureEvent(Closure $callable)` event to trigger jobs only after the transaction commits.

## License
This package is open-sourced software licensed under the [MIT license](http://opensource.org/licenses/MIT).
