# Transaction-aware Event Dispatcher for Laravel

<a href="https://travis-ci.org/fntneves/laravel-transactional-events"><img src="https://travis-ci.org/fntneves/laravel-transactional-events.svg?branch=master" alt="TravisCI Status"></a>
<a href="https://packagist.org/packages/fntneves/laravel-transactional-events"><img src="https://poser.pugx.org/fntneves/laravel-transactional-events/v/stable" alt="Latest Stable Version"></a>

This package introduces a transactional layer into Laravel Event Dispatcher. Its purpose is to achieve, without changing a single line of code, a better consistency on dispatched events within database transactions.

## Why transactional events?
When applications increase on size, developers may end up dispatching events on models, in favor of organization. Also, developers may rely on Model events dispatched by Laravel out of the box. Such events are `saving`, `saved`, `deleting`, `deleted` and so on.

Considering that the process of handling a request may include running several queries against the database, using database transactions becomes mandatory.

The following example represents a simple process of ordering tickets. Assume this involves database changes and a payment registration. In the meanwhile, a custom event is dispatched. This event would result in a listener execution that sends an e-mail to the user that is performing the request.

**OrdersController.php**
```php
DB::transaction(function() {
    $user = User::find(...);
    $concert = Concert::find(...);
    $tickets = $concert->orderTickets($user, 3);
    PaymentService::registerOrder($tickets);
});
```

**Concert.php**
```php
public function orderTickets($user, $amount)
{
    ...
    event(OrderWasProcessed::class);
}
```

The transaction of the above example may fail at several points due to several reasons. For instance, it may fail while calling `orderTickets` or due to an error on the payment service or simply due to another reason in the database-level, such as a deadlock.

All mentioned failures will trigger a rollback all your database changes. That is, will discard the changes performed within the transaction. **The problem is, even when transaction fails after the `OrderWasProcessed` event is dispatched, because it will eventually be executed and user will receive a notification about something that didn't happen.**

The purpose of this package is to ensure that events are dispatched if and only if the active transaction succeeds. *It also ensures that the Dispatcher's behavior fallbacks to the default behavior if there are no active transactions.*

## Installation
**Note: This package is only available for Laravel 5.5 LTS.**

The installation of this package leverages the _Package Auto-Discovery_ feature of Laravel 5.5. Just add this package to the `composer.json` file and it will be ready for your application.

```
composer require fntneves/laravel-transactional-events
```

A configuration file is also provided on this package. To customize it, run the following command to publish the provided configuration file `transactional-events.php` into your config folder.

```
php artisan vendor:publish --provider="Neves\Events\EventServiceProvider"
```


## Usage

Once the package is installed, it is ready to use and enabled and, by default, all events within the `App\Events` namespace will behave as transactional events, when dispatched on database transactions.

The current available `Event` facade and `event()` helper method can still be used to dispatch an event:

```php
Event::dispatch(...) // Using Event facade

event(...) // Using helper method
```

Even if you use queues, they just still work smoothly because this package does not change the abstract behavior of the event dispatcher. Namely, they will be enqueued as soon as the active transaction succeeds. If the transaction fails, they will be forgotten.

**Reminder:** Events are handled as transactional when they are dispatched while an active transaction exists. When an event is dispatched and there is no active transaction, the default behavior of the event dispatcher is applied.


## Configuration

This package provides a configuration file that allows some customization on what events should be transactional. The following keys are present in the configuration file:

```php
'enabled' => true
```
The transactional behavior of events can be enable or disable by setting up the `enable` property in configuration file.

```php
'events' => ['App\Events']
```
By default, the transactional behavior will be applied to events on `App\Events` namespace. Feel free to use patterns and namespaces.

```php
'exclude' => ['App\Events\DeletingAccount']
```
Applications may have specific events that should never be handled as transactional. Specify the events (including patterns) that should bypass the transactional layer and they will be handled only by the default event dispatcher.

## License
This package is open-sourced software licensed under the [MIT license](http://opensource.org/licenses/MIT).
