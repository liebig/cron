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
    private static $cronJobs = array();

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
     * @param  string $name The name for the cron job - must be unique
     * @param  string $expression The cron job expression (e.g. for every minute: '* * * * *')
     * @param  function $function The anonymous function which will be executed
     * @param  boolean $isEnabled optional If the cron job is enabled or not - the standard configuration is true
     * @throws InvalidArgumentException
     */
    public static function add($name, $expression, $function, $isEnabled = true) {

        // Check if the given expression is set and is correct
        if (!isset($expression) || count(explode(' ', $expression)) < 5 || count(explode(' ', $expression)) > 6) {
            throw new \InvalidArgumentException('Method argument $expression is not set or invalid.');
        }

        // Check if the given closure is callabale
        if (!is_callable($function)) {
            throw new \InvalidArgumentException('Method argument $function is not a callable closure.');
        }

        // Check if the isEnabled boolean is okay, if not use the standard 'true' configuration
        if (!is_bool($isEnabled)) {
             throw new \InvalidArgumentException('Method argument $isEnabled is not a boolean.');
        }

        // Check if the name is unique
        foreach (self::$cronJobs as $job) {
            if ($job['name'] === $name) {
                throw new \InvalidArgumentException('Cron job $name "' . $name . '" is not unique and already used.');
            }
        }

        // Create the CronExpression - all the magic goes here
        $expression = \Cron\CronExpression::factory($expression);

        // Add the new created cron job to the many other little cron jobs and return null because everything is fine
        array_push(self::$cronJobs, array('name' => $name, 'expression' => $expression, 'enabled' => $isEnabled, 'function' => $function));
    }

    /**
     * Remove a cron job from execution by name
     * 
     * @static
     * @param string $name The name of the cron job which should be removed from execution
     * @return void|false Return null if a cron job with the given name was found and was successfully removed or return false if no job with the given name was found
     */
    public static function remove($name) {

        foreach (self::$cronJobs as $jobKey => $jobValue) {
            if ($jobValue['name'] === $name) {
                unset(self::$cronJobs[$jobKey]);
                return null;
            }
        }
        return false;
    }

    /**
     * Run the cron jobs
     * This method checks and runs all the defined cron jobs at the right time
     * This method (route) should be called automatically by a server or service
     * 
     * @static
     * @return array Return an array with the rundate, runtime, errors and a result cron job array (with name, function return value, rundate and runtime)
     */
    public static function run() {
        // Get the rundate
        $runDate = new \DateTime();

        // Get the run interval from Laravel config
        $runInterval = self::getRunInterval();

        // Getting last run time only if database logging is enabled
        if (self::isDatabaseLogging()) {
            // Get the time (in seconds) between this and the last run and save this to $timeBetween
            $lastManager = \Liebig\Cron\Models\Manager::orderBy('rundate', 'DESC')->take(1)->get();
            if (!empty($lastManager[0])) {
                $lastRun = new \DateTime($lastManager[0]->rundate);
                $timeBetween = $runDate->getTimestamp() - $lastRun->getTimestamp();
            } else {
                // No previous cron job runs are found
                $timeBetween = -1;
            }
            // If database logging is disabled
        } else {
            // Cannot check if the cron run is in time
            $inTime = -1;
        }

        // Initialize the job and job error array and start the runtime calculation
        $allJobs = array();
        $errorJobs = array();
        $beforeAll = microtime(true);

        // For all defined cron jobs run this
        foreach (self::$cronJobs as $job) {

            // If the job is enabled and if the time for this job has come
            if ($job['enabled'] && $job['expression']->isDue()) {

                // Get the start time of the job runtime
                $beforeOne = microtime(true);

                // Run the function and save the return to $return - all the magic goes here
                $return = $job['function']();

                // Get the end time of the job runtime
                $afterOne = microtime(true);

                // If the function returned not null then we assume that there was an error
                if ($return !== null) {
                    // Add to error array
                    array_push($errorJobs, array('name' => $job['name'], 'return' => $return, 'runtime' => ($afterOne - $beforeOne)));
                    // Log error job
                    self::log('error', 'Job with the name ' . $job['name'] . ' was run with errors.');
                }

                // Push the information of the ran cron job to the allJobs array (including name, return value, runtime)
                array_push($allJobs, array('name' => $job['name'], 'return' => $return, 'runtime' => ($afterOne - $beforeOne)));
            }
        }

        // Get the end runtime for all the cron jobs
        $afterAll = microtime(true);

        // If database logging is enabled, save manager und jobs to db
        if (self::isDatabaseLogging()) {

            // Create a new cronmanager database object for this run and save it
            $cronmanager = new\Liebig\Cron\Models\Manager();
            $cronmanager->rundate = $runDate;
            $cronmanager->runtime = $afterAll - $beforeAll;
            $cronmanager->save();

            $inTime = false;
            // Check if the run between this run and the last run is in good time (30 seconds tolerance) or not and log this event
            if ($timeBetween === -1) {
                self::log('notice', 'Cron run with manager id ' . $cronmanager->id . ' has no previous managers.');
                $inTime = -1;
            } elseif (($runInterval * 60) - $timeBetween <= -30) {
                self::log('error', 'Cron run with manager id ' . $cronmanager->id . ' is with ' . $timeBetween . ' seconds between last run too late.');
                $inTime = false;
            } elseif (($runInterval * 60) - $timeBetween >= 30) {
                self::log('error', 'Cron run with manager id ' . $cronmanager->id . ' is with ' . $timeBetween . ' seconds between last run too fast.');
                $inTime = false;
            } else {
                self::log('info', 'Cron run with manager id ' . $cronmanager->id . ' is with ' . $timeBetween . ' seconds between last run in time.');
                $inTime = true;
            }

            if (self::isLogOnlyErrorJobsToDatabase()) {
                // Save error jobs only to database
                self::saveJobsFromArrayToDatabase($errorJobs, $cronmanager->id);
            } else {
                // Save all jobs to database
                self::saveJobsFromArrayToDatabase($allJobs, $cronmanager->id);
            }

            // Log the result of the cron run
            if (empty($errorJobs)) {
                self::log('info', 'The cron run with the manager id ' . $cronmanager->id . ' was finished without errors.');
            } else {
                self::log('error', 'The cron run with the manager id ' . $cronmanager->id . ' was finished with ' . count($errorJobs) . ' errors.');
            }
            
            // Check for old database entires and delete them
            self::deleteOldDatabaseEntries();

            // If database logging is disabled
        } else {
            // Log the status of the cron job run without the cronmanager id
            if (empty($errorJobs)) {
                self::log('info', 'Cron run was finished without errors.');
            } else {
                self::log('error', 'Cron run was finished with ' . count($errorJobs) . ' errors.');
            }
        }

        // Return the cron jobs array (including rundate, in time boolean, runtime, number of errors and an array with the cron jobs reports)
        return array('rundate' => $runDate->getTimestamp(), 'inTime' => $inTime, 'runtime' => ($afterAll - $beforeAll), 'errors' => count($errorJobs), 'crons' => $allJobs);
    }

    /**
     * Save cron jobs from an array to the database
     *
     * @static
     * @param  array $jobArray This array holds all the ran cron jobs which should be logged to database - entry structure must be job['name'], job['return'], job['runtime']
     * @param  int $managerId The id of the saved manager database object which cares about the jobs
     */
    private static function saveJobsFromArrayToDatabase($jobArray, $managerId) {

        foreach ($jobArray as $job) {
            $jobEntry = new \Liebig\Cron\Models\Job();
            $jobEntry->name = $job['name'];

            // Get the type of the returned value
            $returnType = gettype($job['return']);

            // If the type is NULL there was no error running this job - insert empty string
            if ($returnType === 'NULL') {
                $jobEntry->return = '';
                // If the type is boolean save the value as string
            } else if ($returnType === 'boolean') {
                if ($job['return']) {
                    $jobEntry->return = 'true';
                } else {
                    $jobEntry->return = 'false';
                }
                // If the type is integer, double or string we can cast it to String and save it to the error database object
            } else if ($returnType === 'integer' || $returnType === 'double' || $returnType === 'string') {
                // We cut the string at 500 characters to not overcharge the database
                $jobEntry->return = substr((string) $job['return'], 0, 500);
            } else {
                $jobEntry->return = 'Return object type is ' . $returnType;
            }

            $jobEntry->runtime = $job['runtime'];
            $jobEntry->cron_manager_id = $managerId;
            $jobEntry->save();
        }
    }

    /**
     * Add a custom Monolog logger object
     *
     * @static
     * @param  \Monolog\Logger $logger optional The Monolog logger object which will be used for cron logging - if this parameter is null the logger will be removed
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
     * Log a message with the given level to Monolog logger if one is set or to Laravels build in Logger if it is enabled
     *
     * @static
     * @param  string $level The logger level as string which can be debug, info, notice, warning, error, critival, alert, emergency
     * @param  string $message The message which will be logged to Monolog
     * @throws InvalidArgumentException if the $level string does not match with debug, info, notice, warning, error, critival, alert or emergency
     */
    private static function log($level, $message) {

        // If no Monolog logger object is set,  return false
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
                    throw new \InvalidArgumentException('Invalid log $level parameter.');
            }
        }
        
        $laravelLogging = \Config::get('cron::laravelLogging');
        if (is_bool($laravelLogging) && $laravelLogging) {
            switch (strtolower($level)) {
                case "debug":
                    \Log::debug($message);
                    break;
                case "info":
                    \Log::info($message);
                    break;
                case "notice":
                    \Log::notice($message);
                    break;
                case "warning":
                    \Log::warning($message);
                    break;
                case "error":
                    \Log::error($message);
                    break;
                case "critical":
                    \Log::critical($message);
                    break;
                case "alert":
                    \Log::alert($message);
                    break;
                case "emergency":
                    \Log::emergency($message);
                    break;
                default:
                    throw new \InvalidArgumentException('Invalid log $level parameter.');
            }
        }
        
    }
    
    /**
     * Enable or disable Laravels build in logging
     *
     * @static
     * @param  boolean $bool Set to enable or disable Laravels logging
     * @throws InvalidArgumentException if the $bool function paramter is not a boolean
     */
    public static function setLaravelLogging($bool) {
        if (is_bool($bool)) {
            \Config::set('cron::laravelLogging', $bool);
        } else {
            throw new \InvalidArgumentException('Function paramter $bool with value "' . $bool . '" is not a boolean.');
        }
    }

    /**
     * Is Laravels build in logging enabled or disabled
     * 
     * @static
     * @return boolean Return boolean which indicates if Laravels logging is enabled or disabled
     * @throws \UnexpectedValueException if the cron::laravelLogging config value is not a boolean or NULL
     */
    public static function isLaravelLogging() {
        $laravelLogging = \Config::get('cron::laravelLogging');
        if (is_bool($laravelLogging) || is_null($laravelLogging)) {
            return $laravelLogging;
        } else {
            throw new \UnexpectedValueException ('Config option "cron::laravelLogging" is not a boolean or not equals NULL.');
        }
    }

    /**
     * Enable or disable database logging
     *
     * @static
     * @param  boolean $bool Set to enable or disable database logging
     * @throws InvalidArgumentException if the $bool function paramter is not a boolean
     */
    public static function setDatabaseLogging($bool) {
        if (is_bool($bool)) {
            \Config::set('cron::databaseLogging', $bool);
        } else {
            throw new \InvalidArgumentException('Function paramter $bool with value "' . $bool . '" is not a boolean.');
        }
    }

    /**
     * Is logging to database true or false
     * 
     * @static
     * @return boolean Return boolean which indicates if database logging is true or false
     * @throws \UnexpectedValueException if the cron::databaseLogging config value is not a boolean  or NULL
     */
    public static function isDatabaseLogging() {
        $databaseLogging = \Config::get('cron::databaseLogging');
        if (is_bool($databaseLogging) || is_null($databaseLogging)) {
            return $databaseLogging;
        } else {
            throw new \UnexpectedValueException ('Config option "cron::databaseLogging" is not a boolean or not equals NULL.');
        }
    }

    /**
     * Enable or disable logging error jobs only to database 
     * NOTE: Works only if database logging is enabled
     *
     * @static
     * @param  boolean $bool Set to enable or disable logging error jobs only
     * @throws InvalidArgumentException if the $bool function paramter is not a boolean
     */
    public static function setLogOnlyErrorJobsToDatabase($bool) {
        if (is_bool($bool)) {
            \Config::set('cron::logOnlyErrorJobsToDatabase', $bool);
        } else {
            throw new \InvalidArgumentException('Function paramter $bool with value "' . $bool . '" is not a boolean.');
        }
    }

    /**
     * Is logging jobs to database only true or false
     * 
     * @return boolean Return boolean which indicates if logging only error jobs to database is true or false
     * @throws \UnexpectedValueException if the cron::logOnlyErrorJobsToDatabase config value is not a boolean or NULL
     */
    public static function isLogOnlyErrorJobsToDatabase() {
        $logOnlyErrorJobsToDatabase = \Config::get('cron::logOnlyErrorJobsToDatabase');
        if (is_bool($logOnlyErrorJobsToDatabase) || is_null($logOnlyErrorJobsToDatabase)) {
            return $logOnlyErrorJobsToDatabase;
        } else {
            throw new \UnexpectedValueException ('Config option "cron::logOnlyErrorJobsToDatabase" is not a boolean or not equals NULL.');
        }
    }

    /**
     * Reset the Cron class
     * Remove the cron jobs array and the logger object
     *
     * @static
     */
    public static function reset() {
        self::$cronJobs = array();
        self::$logger = null;
    }

    /**
     * Set the run interval - the run interval is the time between two cron job route calls
     *
     * @static
     * @param  int $minutes Set the interval in minutes
     * @throws InvalidArgumentException if the $minutes function paramter is not an integer
     */
    public static function setRunInterval($minutes) {
        if (is_int($minutes)) {
            \Config::set('cron::runInterval', $minutes);
        } else {
            throw new \InvalidArgumentException('Function paramter $minutes with value "' . $minutes . '" is not an integer.');
        }
    }

    /**
     * Get the current run interval value
     * 
     * @return int|null Return the current interval value in minutes or NULL if there is no value set
     * @throws \UnexpectedValueException if the cron::runInterval config value is not an integer or NULL
     */
    public static function getRunInterval() {
        $interval = \Config::get('cron::runInterval');
        if (is_int($interval) || is_null($interval)) {
            return $interval;
        } else {
            throw new \UnexpectedValueException ('Config option "cron::runInterval" is not an integer or not equals NULL.');
        }
    }

    /**
     * Set the delete time of old database entries in hours 
     *
     * @static
     * @param  int $hours Set the delete time in hours
     * @throws InvalidArgumentException if the $hours function paramter is not an integer
     */
    public static function setDeleteDatabaseEntriesAfter($hours = 0) {
        if (is_int($hours)) {
            \Config::set('cron::deleteDatabaseEntriesAfter', $hours);
        } else {
            throw new \InvalidArgumentException('Function paramter $hours with value "' . $hours . '" is not an integer.');
        }
    }

    /**
     * Get the current delete time value in hours for old database entries
     * 
     * @return int|null Return the current delete time value in hours or NULL if there was no value set
     * @throws \UnexpectedValueException if the cron::deleteDatabaseEntriesAfter config value is not an integer or NULL
     */
    public static function getDeleteDatabaseEntriesAfter() {
        $deleteDatabaseEntriesAfter = \Config::get('cron::deleteDatabaseEntriesAfter');
        if (is_int($deleteDatabaseEntriesAfter) || is_null($deleteDatabaseEntriesAfter)) {
            return $deleteDatabaseEntriesAfter;
        } else {
            throw new \UnexpectedValueException ('Config option "cron::deleteDatabaseEntriesAfter" is not an integer or not equals NULL.');
        }
    }

    /**
     * Delete old manager and job entries
     *
     * @static
     * @return void|false Return false if the database was not cleaned successfully or void if the database is cleaned of old entries
     */
    private static function deleteOldDatabaseEntries() {
        // Get the delete after hours value
        $deleteDatabaseEntriesAfter = self::getDeleteDatabaseEntriesAfter();
        // If the value is not set or equals 0 delete old database entries is disabled
        if (!empty($deleteDatabaseEntriesAfter)) {

            // Get the current time and subtract the hour values
            $now = new \DateTime();
            date_sub($now, date_interval_create_from_date_string($deleteDatabaseEntriesAfter . ' hours'));

            // Get the old manager entries which are expired
            $oldManagers = \Liebig\Cron\Models\Manager::where('rundate', '<=', $now->format('Y-m-d H:i:s'))->get();

            foreach ($oldManagers as $manager) {

                // Get the old job entries from thee expired manager
                $oldJobs = $manager->cronJobs()->get();

                foreach ($oldJobs as $job) {
                    // Delete old job
                    $job->delete();
                }

                // After running through the manager jobs - delete the manager entry
                $manager->delete();
            }
            // Database was cleaned successfully
            return null;
        }
        // Database clean was skipped
        return false;
    }

    /**
     * Enable a job by job name
     *
     * @static
     * @param  String $jobname The name of the job which should be enabled
     * @param  boolean $enable The trigger for enable (true) or disable (false) the job with the given name
     * @return void|false Return void if job was enabled successfully or false if no job with the $jobname parameter was found
     * @throws InvalidArgumentException if the $enable function paramter is not a boolean
     */
    public static function setEnableJob($jobname, $enable = true) {
        // Check patameter
        if (!is_bool($enable)) {
            throw new \InvalidArgumentException('Function paramter $enable with value "' . $enable . '" is not a boolean.');
        }
        
        // Walk through the cron jobs and find the job with the given name
        foreach (self::$cronJobs as $jobKey => $jobValue) {
            if ($jobValue['name'] === $jobname) {
                // If a job with the given name is found, set the enable boolean
                self::$cronJobs[$jobKey]['enabled'] = $enable;
                return null;
            }
        }
        return false;
    }
    
    /**
     * Disable a job by job name
     *
     * @static
     * @param  String $jobname The name of the job which should be disabled
     * @return void|false Return void if job was disabled successfully or false if no job with the $jobname parameter was found
     */
    public static function setDisableJob($jobname) {
        return self::setEnableJob($jobname, false);
    }
    
    /**
     * Is the given job by name enabled or disabled
     *
     * @static
     * @param  String $jobname The name of the job which should be checked
     * @return void|false Return boolean if job was enabled (true) or disabled (false) or null if no job with the given name is found
     */
    public static function isJobEnabled($jobname) {
        
        // Walk through the cron jobs and find the job with the given name
        foreach (self::$cronJobs as $jobKey => $jobValue) {
            if ($jobValue['name'] === $jobname) {
                // If a job with the given name is found, return the is enabled boolean
                return self::$cronJobs[$jobKey]['enabled'];
            }
        }
        return null;
    }

}
