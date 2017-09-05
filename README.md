# Transactional Events on Laravel <a href="https://travis-ci.org/fntneves/laravel-transactional-events"><img src="https://travis-ci.org/fntneves/laravel-transactional-events.svg?branch=master" alt="TravisCI Status"></a>

This package brings transactional events to your Laravel application, allowing to achieve consistency between dispatched events and active database transactions.
<br>
Without changing a line of code your application will be able to take advantage from transactional events, out of the box.

## Why transactional events?
When your application hits a size that requires some effort to organize things, you may want to dispatch events on models to represent changes on their state. Let's say, for instance, that a ordering tickets is a complex process that calls an external payment service and triggers a notification that will be sent by e-mail, SMS, ...

```php

// OrdersController.php
DB::transaction(function() {
    $user = User::find(...);
    $concert = Concert::find(...);
    $tickets = $concert->orderTickets($user, 3);
    PaymentService::createOrder($tickets);
});

// Concert.php
public function orderTickets($user, $amount)
{
    ...
    event(UserDisabledAccount::class);
}
```

In case the transaction of the example fails due to an error on external payment service or due to other reason in the database-level, such as deadlocks, will rollback all your database changes. **However, the event was actually dispatched and it will be executed, even the whole transaction failed**.

Here is the purpose of this package: if an event is dispatched within a transaction, it will be executed if and only if the transaction succeeds. If the transaction fails, they will never execute.

However, if have parts on your code that do not leverage transactions, **events will be dispatched using the default Laravel Event Dispatcher**.

## Installation
**Note:** This package is only available for Laravel 5.5 LTS.

The installation of this package leverages the Package Auto-Discovery feature enabled in Laravel 5.5. Therefore, you just need to add this package to `composer.json` file.

```php
composer require "fntneves/laravel-transactional-events"
```

The configuration file can be customized, just publish the provided one `transactional-events.php` to your config folder.

```php
php artisan vendor:publish --provider="Neves\Events\EventServiceProvider"
```


## Usage

Once the package is installed, it is ready to use out-of-the-box. By default, it will start to handle all events of `App\Events` namespace as transactional events.

The `Event::dispatch(...)` facade or the `event(new UserRegistered::class)` helper method can still be used to dispatch events. If you use queues, they will still work smoothly, since this package only adds a transactional layer over the event dispatcher.

**Note:** This package only applies the transactional behavior to events dispatched within a database transaction. Otherwise, it will perform the same as the default Laravel Event Dispatcher.


## Configuration

**enabled**: The transactional behavior of events can be enable or disable by setting up the `enable` property in configuration file.

**events**: By default, the transactional behavior will be applied to events on `App\Events` namespace. This is configurable to patterns and full namespaces.

**exclude**: Apart of the transactional events, you can choose specific events to be handled with the default Laravel Event Dispatcher, i.e., without the transactional behavior.

## License
This package is open-sourced software licensed under the [MIT license](http://opensource.org/licenses/MIT).
