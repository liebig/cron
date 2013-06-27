<?php
/**
 * Cron - Job scheduling for Laravel
 *
 * @author      Marc Liebig
 * @copyright   2013 Marc Liebig
 * @link
 * @license     http://opensource.org/licenses/MIT
 * @version     1.0.0
 * @package     Cron
 *
 * MIT LICENSE
 *
 * Permission is hereby granted, free of charge, to any person obtaining
 * a copy of this software and associated documentation files (the
 * "Software"), to deal in the Software without restriction, including
 * without limitation the rights to use, copy, modify, merge, publish,
 * distribute, sublicense, and/or sell copies of the Software, and to
 * permit persons to whom the Software is furnished to do so, subject to
 * the following conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
 * LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
 * OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
 * WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
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
        array_push(self::$crons , array('name' => $name, 'expression' => $expression, 'enabled' => $isEnabled, 'function' => $function));
        return null;
    }

    /**
     * Run the cron jobs
     * This method runs all the defined cron jobs and should be called each minute (* * * * *)
     *
     * @static
     * @return array Return an array with the rundate, runtime, errors and a result cron job array (with name, function return values, rundate and runtime)
     */
    public static function run() {
        // Get the rundate
        $runDate = new \DateTime();

        // Initialize the crons array, errors count and start runtime time
        $cronsEvaluation = array();
        $errors = 0;
        $beforeAll = microtime(true);

        // For all defined crons run this
        foreach (self::$crons as $cron) {

            // If the cron is enabled and if the time for this job has come
            if ($cron['enabled'] === true && $cron['expression']->isDue()) {

                // Get the start time of the job runtime
                $beforeOne = microtime(true);

                // Run the function and save the return to $return - all the magic goes here
                $return = $cron['function']();

                // If the function returned 'false' then we assume that there was an error
                if ($return === false) {
                    // Errors count plus one
                    $errors++;
                }

                // Get the end time of the job runtime
                $afterOne = microtime(true);

                // Push the information of the run cron job to the crons array (including name, return value, rundate, runtime)
                array_push($cronsEvaluation, array('name' => $cron['name'], 'return' => $return, 'rundate' => $runDate->getTimestamp(), 'runtime' => ($afterOne - $beforeOne)));
            }
        }

        // Get the end runtime for all the cron jobs
        $afterAll = microtime(true);

        // Return the cron jobs array (including rundate, runtime, errors and an other array with the cron jobs information)
        return array('rundate' => $runDate->getTimestamp(), 'runtime' => ($afterAll - $beforeAll), 'errors' => $errors, 'crons' => $cronsEvaluation);
    }

}