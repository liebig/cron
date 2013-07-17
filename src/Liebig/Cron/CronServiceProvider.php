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

//        $cronLogger = new \Monolog\Logger('cron');
//        $cronLogger->pushHandler(new \Monolog\Handler\StreamHandler(storage_path() . '/logs/cron-' . date('Y-m-d') . '.txt', \Monolog\Logger::DEBUG));
//        \Liebig\Cron\Cron::setLogger($cronLogger);
//        
//        \Liebig\Cron\Cron::add('test', '* * * * *', function() {
//                    echo '<h1>cron runned!</h1>';
//                    return null;
//                });
//
//        \Liebig\Cron\Cron::add('test', '*/2 * * * *', function() {
//                    echo '<h1>cron not runned!</h1>';
//                    return new \Liebig\Cron\models\Error;
//                });

//        var_dump(\Liebig\Cron\cron::run());
                

        

    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register() {
        //
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