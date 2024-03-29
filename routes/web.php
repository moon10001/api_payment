<?php

/** @var \Laravel\Lumen\Routing\Router $router */

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/', function () use ($router) {
    return $router->app->version();
});


$router->get('/import', 'ImportController@import');
$router->post('/report', 'ReportController@get');
$router->post('/recap', 'ReportController@recap');
$router->post('/options/{type}', 'OptionsController@get');
$router->post('/supervision/report', 'SupervisionsController@post');
$router->post('/supervision/options/{type}', 'SupervisionsController@options');
