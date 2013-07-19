<?php

/**
 * Cron - Job scheduling for Laravel
 *
 * @author      Marc Liebig
 * @copyright   2013 Marc Liebig
 * @link        https://github.com/liebig/cron/
 * @license     http://opensource.org/licenses/MIT
 * @version     1.0.0
 * @package     Cron
 *
 * Please find more copyright information in the LICENSE file
 */

namespace Liebig\Cron;

/**
 * Cron
 *
 * Cron job management
 * NOTE: The excellent library mtdowling/cron-expression (https://github.com/mtdowling/cron-expression) is required.
 *
 * @package Cron
 * @author  Marc Liebig
 * @since   1.0.0
 */
class Cron {

    /**
     * @static
     * @var array Saves all the cron jobs
     */
    private static $crons = array();

    /**
     * @static
     * @var \Monolog\Logger Logger object if logging is requested or null if nothing should be logged.
     */
    private static $logger;

    /**
     * Add a cron job
     *
     * Expression definition:
     *
     *       *    *    *    *    *    *
     *       -    -    -    -    -    -
     *       |    |    |    |    |    |
     *       |    |    |    |    |    + year [optional]
     *       |    |    |    |    +----- day of week (0 - 7) (Sunday=0 or 7)
     *       |    |    |    +---------- month (1 - 12)
     *       |    |    +--------------- day of month (1 - 31)
     *       |    +-------------------- hour (0 - 23)
     *       +------------------------- min (0 - 59)
     *
     * @static
     * @param  string $name The name for the cron job
     * @param  string $expression The cron job expression (e.g. for every minute: '* * * * *')
     * @param  function $function The anonymous function which will be executed
     * @param  boolean $isEnabled optional If the cron job is enabled or not - the standard configuration is true
     * @return boolean Return true if everything worked and false if there is any error
     */
    public static function add($name, $expression, $function, $isEnabled = true) {

        // Check if the given expression is set and is correct
        if (!isset($expression) || count(explode(' ', $expression)) < 5) {
            return false;
        }

        // Check if the given closure is callabale
        if (!is_callable($function)) {
            return false;
        }

        // Check if the isEnabled boolean is okay, if not use the standard 'true' configuration
        if (!is_bool($isEnabled)) {
            $isEnabled = true;
        }

        // Create the CronExpression - all the magic goes here
        $expression = \Cron\CronExpression::factory($expression);

        // Add the new created cron job to the many other little cron jobs and return null because everything is fine
        array_push(self::$crons, array('name' => $name, 'expression' => $expression, 'enabled' => $isEnabled, 'function' => $function));
        return null;
    }

    /**
     * Run the cron jobs
     * This method checks and runs all the defined cron jobs at the right time
     * This method (route) should be called automatically by a server or service
     * 
     * @static
     * @param  int $repeatTime optional The time in minutes between two run method calls (default is every minute - * * * * *)
     * @return array Return an array with the rundate, runtime, errors and a result cron job array (with name, function return values, rundate and runtime)
     */
    public static function run($repeatTime = 1) {
        // Get the rundate
        $runDate = new \DateTime();

        // Checking the repetTime parameter
        if(!is_int($repeatTime)) {
            $repeatTime = 1;
        }

        // Get the time (in seconds) between this and the last run and save this to $timeBetween
        $lastManager = \Liebig\Cron\models\Manager::orderBy('created_at', 'DESC')->take(1)->get();
        if (!empty($lastManager[0])) {
            $lastRun = new \DateTime($lastManager[0]->rundate);
            $timeBetween = $runDate->getTimestamp() - $lastRun->getTimestamp();
        } else {
            // No previous cron job runs are found
            $timeBetween = -1;
        }

        // Initialize the crons array, errors count and start the runtime calculation
        $cronsEvaluation = array();
        $cronErrors = array();
        $beforeAll = microtime(true);

        // For all defined crons run this
        foreach (self::$crons as $cron) {

            // If the cron is enabled and if the time for this job has come
            if ($cron['enabled'] === true && $cron['expression']->isDue()) {

                // Get the start time of the job runtime
                $beforeOne = microtime(true);

                // Run the function and save the return to $return - all the magic goes here
                $return = $cron['function']();

                // Get the end time of the job runtime
                $afterOne = microtime(true);

                // If the function returned not null then we assume that there was an error
                if ($return !== null) {
                    // Add to error array
                    array_push($cronErrors, array('name' => $cron['name'], 'return' => $return, 'runtime' => ($afterOne - $beforeOne)));
                }

                // Push the information of the run cron job to the crons array (including name, return value, rundate, runtime)
                array_push($cronsEvaluation, array('name' => $cron['name'], 'return' => $return, 'runtime' => ($afterOne - $beforeOne)));
            }
        }

        // Get the end runtime for all the cron jobs
        $afterAll = microtime(true);

        // Create a new cronmanager database object for this run and save it
        $cronmanager = new\Liebig\Cron\models\Manager;
        $cronmanager->rundate = $runDate;
        $cronmanager->runtime = $afterAll - $beforeAll;
        $cronmanager->save();

        $inTime = false;
        // Check if the run between this run and the last run is in time or not and log this event
        if ($timeBetween === -1) {
            self::log('warning', 'Cron run with manager id ' . $cronmanager->id . ' has no previous ran jobs.');
            $inTime = null;
        } elseif (($repeatTime * 60) - $timeBetween <= -30) {
            self::log('error', 'Cron run with manager id ' . $cronmanager->id . ' is with ' . $timeBetween . ' seconds between last run too late.');
            $inTime = false;
        } elseif (($repeatTime * 60) - $timeBetween >= 30) {
            self::log('error', 'Cron run with manager id ' . $cronmanager->id . ' is with ' . $timeBetween . ' seconds between last run too fast.');
            $inTime = false;
        } else {
            self::log('info', 'Cron run with manager id ' . $cronmanager->id . ' is with ' . $timeBetween . ' seconds between last run in time.');
            $inTime = true;
        }

        // Walk over the cron error jobs (which returned not null) and save them to the database as object
        foreach ($cronErrors as $cronError) {
            $errorEntry = new \Liebig\Cron\models\Error;
            $errorEntry->name = $cronError['name'];

            // Get the type of the returned value
            $returnType = gettype($cronError['return']);
            // If this type is a boolean, integer, double or string we can cast it to String and save it to the error database object
            if ($returnType === 'boolean' || $returnType === 'integer' || $returnType === 'double' || $returnType === 'string') {
                // We cut the string at 5000 characters to not carried away and to stay the database healthy
                $errorEntry->return = substr((string) $cronError['return'], 0, 5000);
            } else {
                $errorEntry->return = 'Return value of type ' . $returnType . ' cannot be displayed as string (type error)';
            }

            $errorEntry->runtime = $cronError['runtime'];
            $errorEntry->cron_manager_id = $cronmanager->id;
            $errorEntry->save();
        }

        // Log the result of the cron run
        if (empty($cronErrors)) {
            self::log('info', 'The cron run with the manager id ' . $cronmanager->id . ' was finished without errors.');
        } else {
            self::log('error', 'The cron run with the manager id ' . $cronmanager->id . ' was finished with ' . count($cronErrors) . ' errors.');
        }

        // Return the cron jobs array (including rundate, runtime, errors and an other array with the cron jobs information)
        return array('rundate' => $runDate->getTimestamp(), 'inTime' => $inTime, 'runtime' => ($afterAll - $beforeAll), 'errors' => count($cronErrors), 'crons' => $cronsEvaluation);
    }

    /**
     * Add a Monolog logger object and activate logging - if no parameter is given, the logger will be removed and cron logging is disabled
     *
     * @static
     * @param  \Monolog\Logger $logger optional The Monolog logger object which will be used for cron logging - if no parameter is given the logger will be removed
     */
    public static function setLogger(\Monolog\Logger $logger = null) {
        self::$logger = $logger;
    }

    /**
     * Get the Monolog logger object
     *
     * @static
     * @return  \Monolog\Logger Return the set logger object - return null if no logger is set
     */
    public static function getLogger() {
        return self::$logger;
    }

    /**
     * Log a message with the given level to the Monolog logger object if one is set
     *
     * @static
     * @param  string $level The logger level as string which can be debug, info, notice, warning, error, critival, alert, emergency
     * @param  string $message The message which will be logged to Monolog
     */
    private static function log($level, $message) {

        // If no Monolog logger object is set jus retun false
        if (!empty(self::$logger)) {
            // Switch the lower case level string and log the message with the given level
            switch (strtolower($level)) {
                case "debug":
                    self::$logger->addDebug($message);
                    break;
                case "info":
                    self::$logger->addInfo($message);
                    break;
                case "notice":
                    self::$logger->addNotice($message);
                    break;
                case "warning":
                    self::$logger->addWarning($message);
                    break;
                case "error":
                    self::$logger->addError($message);
                    break;
                case "critical":
                    self::$logger->addCritical($message);
                    break;
                case "alert":
                    self::$logger->addAlert($message);
                    break;
                case "emergency":
                    self::$logger->addEmergency($message);
                    break;
                default:
                    return false;
            }
        } else {
            return false;
        }
    }
    
    /**
     * Reset the Cron class
     * Remove the cons array and the logger
     *
     * @static
     */
    public static function reset() {
        
        self::$crons = array();
        self::$logger = null;
    }

}