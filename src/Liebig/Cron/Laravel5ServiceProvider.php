<?php 

namespace Liebig\Cron;

use Illuminate\Support\ServiceProvider;

class Laravel5ServiceProvider extends ServiceProvider {

    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

	/**
	 * Bootstrap the application services.
	 *
	 * @return void
	 */
	public function boot() {
		
            // Publish config
            $configPath = __DIR__ . '/../../config/config.php';
            $this->publishes([$configPath => config_path('liebigCron.php')], 'config');
	
            // Regsiter build in Cron route
            \Route::get('cron.php', 'Liebig\Cron\Http\CronController@run');
	}

	/**
	 * Register the application services.
	 *
	 * @return void
	 */
	public function register() {

            $this->app->singleton('cron', function () {
                return new Cron;
            });

            $this->app->booting(function() {
                        $loader = \Illuminate\Foundation\AliasLoader::getInstance();
                        $loader->alias('Cron', 'Liebig\Cron\Facades\Cron');
                    });

            $this->app->singleton('cron::command.run', function () {
                        return new RunCommand;
                    });
            $this->commands('cron::command.run');

            $this->app->singleton('cron::command.list', function () {
                        return new ListCommand;
                    });
            $this->commands('cron::command.list');

            $this->app->singleton('cron::command.keygen', function () {
                        return new KeygenCommand;
                    });
            $this->commands('cron::command.keygen');
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