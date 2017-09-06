# Transaction-aware Event Dispatcher for Laravel <a href="https://travis-ci.org/fntneves/laravel-transactional-events"><img src="https://travis-ci.org/fntneves/laravel-transactional-events.svg?branch=master" alt="TravisCI Status"></a>

This package introduces a transactional layer into Laravel Event Dispatcher. Its purpose is to achieve, without changing a single line of code, a better consistency level to Laravel applications on dispatched events within database transactions.

## Why transactional events?
When applications increase on size, developers may end up dispatching events on models, for better organization. In fact, Laravel dispatches some events on Model creation, deletion and so on.

Considering that the process of handling a request may include running several queries against the database, using database transactions becomes mandatory.

The following is a simple example of ordering tickets. Assume this is a process that involves database a connection and a payment registration. In the meanwhile, a custom event is dispatched. This event would result in a listener execution that sends an e-mail to the user that is performing the request.

```php

// OrdersController.php
DB::transaction(function() {
    $user = User::find(...);
    $concert = Concert::find(...);
    $tickets = $concert->orderTickets($user, 3);
    PaymentService::registerOrder($tickets);
});

// Concert.php
public function orderTickets($user, $amount)
{
    ...
    event(OrderWasProcessed::class);
}
```

The transaction of the above example may fail in several points due to several reasons. For instance, it may fail in the moment of `orderTickets` call, and would not be a problem. However, it also can fail due to an error on the payment service or just due to another reason in the database-level, such as a deadlock.

All mentioned failures will trigger a rollback all your database changes. That is, will discard the changes performed within the transaction. **The problem is, even when transaction fails, the `OrderWasProcessed` event is dispatched in the meanwhile and will eventually be executed, so user will be notified about something that actually is not real**.

The purpose of this package is to ensure that events are dispatched if and only if the active transaction succeeds. *It also ensures that the behavior fallbacks to the default if there is no active transaction, leveraging the default event dispatcher.*

## Installation
**Note: This package is only available for Laravel 5.5 LTS.**

The installation of this package leverages the Package Auto-Discovery feature of Laravel 5.5. Just add this package to the `composer.json` file and it will be ready for your application.

```
composer require fntneves/laravel-transactional-events
```

A configuration file is provided on this package. To customize it, tun the following command to publish the provided configuration file `transactional-events.php` into your config folder.

```
php artisan vendor:publish --provider="Neves\Events\EventServiceProvider"
```


## Usage

Once the package is installed, it is ready to use and enabled and, by default, all events within the `App\Events` namespace will behave as transactional events, when dispatched on database transactions.

To dispatch an event, the current available `Event` facade and `event()` helper method can still be used. Concretely, dispatch events using the following statements:

```php
Event::dispatch(...) // Using Event facade

event(...) // Using helper method
```

Even if you use queues, they just still work smoothly, because this package does not change the abstract behavior of the event dispatcher.

**Reminder:** Events are handled as transactional when they are dispatched while an active transaction exists. When an event is dispatched and there is no active transaction, the default behavior of the event dispatcher is applied.


## Configuration

This package provides a configuration file that allows some customization on what events should be transactional. The following keys are present in the configuration file:

```
'enabled' => true
```
The transactional behavior of events can be enable or disable by setting up the `enable` property in configuration file.

```
'events' => ['App\Events']
```
By default, the transactional behavior will be applied to events on `App\Events` namespace. Feel free to use patterns and namespaces.

```
'exclude' => []
```
Applications may have specific events that should never be handled as transactional. Specify the events (including patterns) that should bypass the transactional layer and they will be handled only by the default event dispatcher.

## License
This package is open-sourced software licensed under the [MIT license](http://opensource.org/licenses/MIT).
