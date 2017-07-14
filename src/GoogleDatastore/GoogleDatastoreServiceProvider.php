<?php

namespace Mahadirz\GoogleDatastore;

use Mahadirz\GoogleDatastore\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class GoogleDatastoreServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        Model::setConnectionResolver($this->app['db']);
        Model::setEventDispatcher($this->app['events']);
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        // Add database driver.
        $this->app->resolving('db', function ($db) {
            $db->extend('gdatastore', function ($config) {
                return new Connection($config);
            });
        });
    }
}
