<?php

namespace Liebig\Cron\Http;

use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Input;

/**
 * Controller for Cron
 */
class CronController extends BaseController
{
    /**
     * Run Cron
     */
    public function run()
    {
        // Get security key from config
        $cronkeyConfig = \Config::get('liebigCron.cronKey');

        // If no security key is set in the config, this route is disabled
        if (empty($cronkeyConfig)) {
            \Log::error('Cron route call with no configured security key');
            \App::abort(404);
        }

        // Get security key from request
        $cronkeyRequest = Input::get('key');
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
    }
}