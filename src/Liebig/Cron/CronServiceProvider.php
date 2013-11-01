<?php

namespace Liebig\Cron;

use Illuminate\Support\ServiceProvider;

class CronServiceProvider extends ServiceProvider {

    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot() {
        $this->package('liebig/cron');
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register() {
        $this->app['cron'] = $this->app->share(function($app) {
            return  new Cron;
        });
        
        $this->app->booting(function() {
            $loader = \Illuminate\Foundation\AliasLoader::getInstance();
            $loader->alias('Cron', 'Liebig\Cron\Facades\Cron');
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides() {
        return array();
    }

}