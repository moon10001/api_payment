<?php
return [

   'default' => 'mysql',
   
   'migrations' => 'migrations',

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
        
        'report_db' => [
        	'driver'    => 'mysql',
            'host'	=> env('REPORT_DB_HOST'),
            'port'	=> env('REPORT_DB_PORT'),
            'database'  => env('REPORT_DB_DATABASE'),
            'username'  => env('REPORT_DB_USERNAME'),
            'password'  => env('REPORT_DB_PASSWORD'),
            'charset'   => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix'    => '',
            'strict'    => false,                                                                                                        
        ],
        
        'academics' => [
        	'driver' => 'mysql',
        	'host' => env('ACADEMICS_DB_HOST', '127.0.0.1'),
        	'port' => env('ACADEMICS__DB_PORT', '3306'),
        	'database' => env('ACADEMICS_DB_DATABASE', 'forge'),
        	'username' => env('ACADEMICS_DB_USERNAME', 'forge'),
        	'password' => env('ACADEMICS_DB_PASSWORD', ''),
 	       	'charset' => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix' => '',
            'strict' => false,
		],
		
		'auth' => [
		   	'driver' => 'mysql',
            'host' => env('AUTH_DB_HOST', '127.0.0.1'),
            'port' => env('AUTH_DB_PORT', '3306'),
            'database' => env('AUTH_DB_DATABASE', 'forge'),
            'username' => env('AUTH_DB_USERNAME', 'forge'),
            'password' => env('AUTH_DB_PASSWORD', ''),
            'charset' => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix' => '',
            'strict' => false,
        ],
    ],
];
