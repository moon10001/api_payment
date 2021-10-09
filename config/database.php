<?php
return [

   'default' => 'mysql',

   'connections' => [
        'mysql' => [
            'driver'    => 'mysql',
            'host'      => env('DB_HOST'),
            'port'      => env('DB_PORT'),
            'database'  => env('DB_DATABASE'),
            'username'  => env('DB_USERNAME'),
            'password'  => env('DB_PASSWORD'),
            'charset'   => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix'    => '',
            'strict'    => false,
         ],

        'finance_db' => [
            'driver'    => 'mysql',
            'host'      => env('FINANCE_DB_HOST'),
            'port'      => env('FINANCE_DB_PORT'),
            'database'  => env('FINANCE_DB_DATABASE'),
            'username'  => env('FINANCE_DB_USERNAME'),
            'password'  => env('FINANCE_DB_PASSWORD'),
            'charset'   => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix'    => '',
            'strict'    => false,
        ],
    ],
];
