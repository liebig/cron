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
	
	
            // Build in Cron run route
            \Route::get('cron.php', function() {
            
                // Get security key from config
                $cronkeyConfig = \Config::get('liebigCron.cronKey');

                // If no security key is set in the config, this route is disabled
                if (empty($cronkeyConfig)) {
                    \Log::error('Cron route call with no configured security key');
                    \App::abort(404);
                }

                // Get security key from request
                $cronkeyRequest = \Input::get('key');
                // Create validator for security key
                $validator = \Validator::make(
                    array('cronkey' => $cronkeyRequest),
                    array('cronkey' => 'required|alpha_num')
                );
            
                if ($validator->passes()) {
                    if ($cronkeyConfig === $cronkeyRequest) {
                        \Artisan::call('cron:run', array());
                    } else {
                        // Configured security key is not equals the sent security key
                        \Log::error('Cron route call with wrong security key');
                        \App::abort(404);
                    }
                } else {
                    // Validation not passed
                    \Log::error('Cron route call with missing or no alphanumeric security key');
                    \App::abort(404);
                }
            });
	}

	/**
	 * Register the application services.
	 *
	 * @return void
	 */
	public function register() {

            $this->app['cron'] = $this->app->share(function($app) {
                return new Cron;
            });
            
            $this->app->booting(function() {
                        $loader = \Illuminate\Foundation\AliasLoader::getInstance();
                        $loader->alias('Cron', 'Liebig\Cron\Facades\Cron');
                    });
            $this->app['cron::command.run'] = $this->app->share(function($app) {
                        return new RunCommand;
                    });
            $this->commands('cron::command.run');
            $this->app['cron::command.list'] = $this->app->share(function($app) {
                        return new ListCommand;
                    });
            $this->commands('cron::command.list');
            $this->app['cron::command.keygen'] = $this->app->share(function($app) {
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