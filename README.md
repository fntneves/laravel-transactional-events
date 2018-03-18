# Transaction-aware Event Dispatcher for Laravel, and Lumen

<a href="https://travis-ci.org/fntneves/laravel-transactional-events"><img src="https://travis-ci.org/fntneves/laravel-transactional-events.svg?branch=master" alt="TravisCI Status"></a>
<a href="https://packagist.org/packages/fntneves/laravel-transactional-events"><img src="https://poser.pugx.org/fntneves/laravel-transactional-events/v/stable" alt="Latest Stable Version"></a>

This package introduces a transactional layer to Laravel/Lumen Event Dispatcher. Its purpose is to achieve, without changing a single line of code, a better consistency between events dispatched during database transactions. This behavior is also applicable to Eloquent events, such as `saved` and `created`, by changing the configuration file.

## Why transactional events?

Let's start with an example representing a simple process of ordering tickets. Assume this involves database changes and a payment registration. A custom event is dispatched when the order is processed in the database.

```php
DB::transaction(function() {
    $user = User::find(...);
    $concert = Concert::find(...);
    $tickets = $concert->orderTickets($user, 3);
    event(OrderWasProcessed::class);
    PaymentService::registerOrder($tickets);
});
```

The transaction of the above example may fail at several points due to several reasons. It may fail while executing the `orderTickets` method or registering the order in the payment service or just simply due to a deadlock.

A failure will result in a discard of all database changes within the transaction, i.e. a rollback. However, the `OrderWasProcessed` event is actually dispatched and eventually will be executed.

The purpose of this package is to ensure that events are dispatched **if and only if** the transaction in which they were dispatched commits. According to the example, if the transaction fails, then the custom event is not actually executed at all.

Note that in situations where events are dispatched out of transactions, they will bypass the transactional layer, i.e. fallback to the default Event Dispatcher. This is true also for events that where the `$halt` parameter is set to `true`.

## Installation

The installation of this package is automatic when the _Package Auto-Discovery_ feature of Laravel 5.5 is leveraged. Just add this package to the `composer.json` file and it will be ready for your application.

```
composer require fntneves/laravel-transactional-events
```

A configuration file is also part of this package. To customize it, run the following command that publishes the provided configuration file `transactional-events.php` to your config folder.

```
php artisan vendor:publish --provider="Neves\Events\EventServiceProvider"
```


## Usage

The transactional layer is enabled by default for the events under the `App\Events` namespace.

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

As this package does not require any change in code, you are still able to use the `Event` facade and call the `event()` or `broadcast()` helper to dispatch an event:

```php
Event::dispatch(new App\Event\TicketsOrdered) // Using Event facade
event(new App\Event\TicketsOrdered) // Using event() helper method
broadcast(new App\Event\TicketsOrdered) // Using broadcast() helper method
```

Even if you use queues, they just still work because this package does not change the core behavior of the event dispatcher. Just take in account that they will be enqueued as soon as the active transaction succeeds. Otherwise, they will be forgotten.

**Reminder:** Events are handled as transactional when they are dispatched within transactions. When an event is dispatched out of transactions, they bypass the transactional layer.


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

## License
This package is open-sourced software licensed under the [MIT license](http://opensource.org/licenses/MIT).
