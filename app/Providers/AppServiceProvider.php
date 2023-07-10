<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Routing\RouteCollectionInterface;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
       $this->app->singleton('Illuminate\Contracts\Routing\ResponseFactory', function ($app) {
    	   /*return new \Illuminate\Routing\ResponseFactory(
	    	   $app['Illuminate\Contracts\View\Factory'],
               $app['Illuminate\Routing\Redirector'],
               $app['Illuminate\Routing\RouteCollectionInterface']
           );*/
           return new \Laravel\Lumen\Http\ResponseFactory();
       });
       
       /*$this->app->bind(\Illuminate\Routing\RouteCollectionInterface::class, function ($app) {
           return new \Illuminate\Routing\RouteCollectionInterface($app);
       });*/
       
       $this->app->bind(
       	RouteCollectionInterface::class
       );
    }
}
