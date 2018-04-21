<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Transactional Events Configuration
    |--------------------------------------------------------------------------
    |
    | This file is for configuring the transactional events of your application
    | that should be dispatched if and only if the outer transaction commits.
    | You can enable event namespaces using prefixes such as App\\ as well
    | as setting up events that should not have a transactional behavior.
    |
    */

    'enable' => true,

    'transactional' => [
        'App\Events',
    ],

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
];
