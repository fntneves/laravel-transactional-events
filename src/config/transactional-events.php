<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Transactional Events Configuration
    |--------------------------------------------------------------------------
    |
    | This file is for configuring the transactional events of your application
    | that must be dispatched only if an active transaction successful commit.
    | You can enable event namespaces using prefixes such as 'App\\' as well
    | as configuring the exceptions that must be kept out of this approach.
    |
    */

    'enable' => true,

    'transactional' => [
        'App\Events',
    ],

    'excluded' => [
        //
    ],
];
