cron ![project status](http://stillmaintained.com/liebig/cron.png)
====

Job scheduling for Laravel

Cron can be used for easily manage cron jobs in laravel without using Artisan commands. The Cron way is to define a route which is called a variable number of minutes (default is every minute - * * * * *). In this route definition add your functions with their cron expressions. Each time the cron route is called, all cron jobs with a suitable cron expression will be called. And that is the Cron magic! Additionally Cron logs every run whith the run error jobs (which not returned null) for you into the database and if you whish to a Monolog logger instance. This cron package is a holistic cron manager four your Laravel website.  

You need
*   this package
*   to define a route with all cron job definations and closing with the run() method call
*   a server or service which call the defined cron route every defined number of minutes (default is every minute) as normal web request (e.g. with wget)

You don't need
*   to create Artisan commands
*   console access to your server
*   to run the regular cron route request on the same machine where your laravel site is located
*   to worry about (cron) job management anymore

*NOTE*: If you have any trouble, questions or suggestions just open an issue. It would be nice to hear from you.

Installation
===

*NOTE*: At the moment, this release is a workbench one. That means you need to copy it in your laravel workbench directory, not in the vendor folder. Besides there is no entry on packagist to install this package automatically with composer. With the first official release 1.0.0 we will change this. Thank you for your patience.

1.  Download the master branch (e.g. as ZIP or clone it)
2.  Extract all the files in YOURLARAVELFOLDER\workbench\liebig\cron\ directory (yes, you have to create the folders liebig\cron)
3.  Run the "composer update" command in your shell from YOURLARAVELFOLDER\workbench\liebig\cron\ (we need some additional libraries - find more about composer at http://getcomposer.org/)
4.  Add 'Liebig\Cron\CronServiceProvider' to your 'providers' array in the app\config\app.php file
5.  Migrate the database with running the command 'php artisan migrate --bench="Liebig/Cron"'
6.  Now you can use \Liebig\Cron\Cron everywhere for free


Usage
===

Add a cron job
==
Adding a cron job to Cron is very easy by using the static *add* function. As parameter the *name* of the cron job, the cron *expression* and an anonymous *function* is needed. The boolean *isEnabled* is optional and can enable or disable this cron job execution (default is enabled).

'''
public static function add($name, $expression, $function, $isEnabled = true) {
'''

The *name* is needed for identify a cron job if an error appears and for logging.

The *expression* is a string of five or optional six subexpressions that describe details of the schedule.
'''
     *       *    *    *    *    *    *
     *       -    -    -    -    -    -
     *       |    |    |    |    |    |
     *       |    |    |    |    |    + year [optional]
     *       |    |    |    |    +----- day of week (0 - 7) (Sunday=0 or 7)
     *       |    |    |    +---------- month (1 - 12)
     *       |    |    +--------------- day of month (1 - 31)
     *       |    +-------------------- hour (0 - 23)
     *       +------------------------- min (0 - 59)
'''

The given anonymous *function* will be invoked if the expression details match with the current timestamp. This function should return null in success case or anything else in if there was an error while executing this job. The error case will be logged to database and to a Monolog logger object (if logger is enabled). 

The *isEnabled* boolean parameter makes it possible to deactivate a job from execution without removing it completely. Later the job execution can be enabled very easy by given a true boolean to the method. This parameter is optional and the default falue is enabled.

Example
=
'''
\Liebig\Cron\Cron::add('test1', '* * * * *', function() use (&$i) {
                    $i++;
                    return null;
                });
'''

