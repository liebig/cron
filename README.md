# ![alt text](https://raw.github.com/liebig/cron/master/icon.png "Cron") Cron
Job scheduling for Laravel

Cron can be used for easily performing cron jobs in Laravel. If you want to run jobs from the internet or just from the local computer, Cron can help you. For more information how Cron can simplify your job scheduling, please have a look at the [raison d'être](#raison).

* Homepage: https://liebig.github.io/cron/
* Github: https://github.com/liebig/cron/
* API: http://liebig.github.io/cron/docs/api/classes/Liebig.Cron.Cron.html

---

* [Raison d’être](#raison)
* [Installation](#installation)
* [Configuration](#configuration)
* [Example](#example)
* [API](#api)
  *  [Add a cron job](#addjob)
  *  [Remove a cron job](#removejob)
  *  [Enable / disable a cron job](#enabledisable)
  *  [Run the cron jobs](#runjob)
  *  [Enable / disable Laravel logging](#enablelarvellogging)
  *  [Set a Monolog logger](#setlogger)
  *  [Disable database logging](#disabledatabaselogging)
  *  [Log only error jobs to database](#logonlyerrorjobstodatabase)
  *  [Delete old database entries](#deleteolddatabaseentries)
  *  [Prevent overlapping](#preventoverlapping)
  *  [Events](#events)
  *  [Commands](#commands)
  *  [Reset Cron](#reset)
* [Frequently Asked Questions](#faq)
* [Changelog](#changelog)

---

<a name="raison"></a>
## Raison d’être

### Simplicity
The aim is to create a simple way to define cron jobs with Laravel. Creating cron jobs with Cron is easy because this tool provides you with a lot of events that you can use to manage all your jobs. For creating a job you only need a job name, a cron expression and a function which will be called as soon as the time has come. Of course PHP is only running if you call it, so you need something in addition which starts Cron.

### Accessibility
The Cron starting call can be executed from the same machine where your Laravel is located (for example with crontab) or from everywhere on the internet (for example from a web cron service) - it is just a command execution or route call.

### Centralization
The Cron management is centralized at a single point in your application. You define all jobs in PHP and don't have to use other tools. Deactivating or removing a Cron job is only a PHP function call away.

### Platform independency
Laravel is a great way to build small and big web applications. But not every application platform runs on a server which allows unix shell access. For these applications Cron provides the possibility to use an external web cron service to start Cron. If you have shell access - great, you can make use of Cron's command to start the job management.

### Monitoring
If something went wrong with your jobs, Cron will inform you. Next to the logging to Monolog, to the Laravel logging facility and to the database, you can add an event listener to get information about error jobs or successfully executed jobs. After execution you will receive a detailed report about the Cron run. With the power of PHP and events you can send a mail, a notification or anything else if anything happens. Cron is talkative like your grandma.

### My personal comfort zone
At last, Cron is my personal way to manage job scheduling. I am a web application developer, not an infrastructure guy. I like to handle things in PHP and not in the shell. I want to deploy my application to another server without worrying if I have access to crontab or other Linux tools. I really like Laravels event functionality but don't like Laravel commands. Cron management should be easy and powerful at the same time. And finally, I love to handle things at a single place in my application without using the shell or write a PHP file for each job. Cron is the try to manage cron jobs without headaches.

---

<a name="installation"></a>
## Installation

### Laravel 5

1.  Add `"liebig/cron": "dev-master"` to your `/path/to/laravel/composer.json` file at the `"require":` section (Find more about composer at http://getcomposer.org/)
2.  Run the `composer update liebig/cron --no-dev` command in your shell from your `/path/to/laravel/` directory
3.  Add `'Liebig\Cron\Laravel5ServiceProvider'` to your `'providers'` array in the `/path/to/laravel/config/app.php` file
4.  Migrate the database with running the command `php artisan migrate --path=vendor/liebig/cron/src/migrations`
5.  Publish the configuration file with running the command `php artisan vendor:publish` - now you find the Cron configuration file at `/path/to/laravel/config/liebigCron.php` and this file won't be overwritten at any update
6.  Now you can use `Cron` everywhere for free

### Laravel 4

1.  Add `"liebig/cron": "dev-master"` to your `/path/to/laravel/composer.json` file at the `"require":` section (Find more about composer at http://getcomposer.org/)
2.  Run the `composer update liebig/cron --no-dev` command in your shell from your `/path/to/laravel/` directory
3.  Add `'Liebig\Cron\CronServiceProvider'` to your `'providers'` array in the `/path/to/laravel/app/config/app.php` file
4.  Migrate the database with running the command `php artisan migrate --package="liebig/cron"`
5.  Publish the configuration file with running the command `php artisan config:publish liebig/cron` - now you find the Cron configuration file at `/path/to/laravel/app/config/packages/liebig/cron` and this file won't be overwritten at any update
6.  Now you can use `Cron` everywhere for free

---

<a name="configuration"></a>
## Configuration

Cron is designed to work out of the box without the need of configuration. To enable this a few default values are set. To change Cron's default settings there are two possibilities.

### Set methods
You can use the Cron set methods (e.g. `setDatabaseLogging`, `setRunInterval`) to change Cron's behaviour. This changes are temporary and the set methods have to be called every time.

### Config file
The behaviour values will be loaded from a config file. You can change this values easily by editing in Laravel 5 the `/path/to/laravel/app/config/liebigCron.php` file and in Laravel 4 the `/path/to/laravel/app/config/packages/liebig/cron/config.php` file. This is the more permanent way. If you only want to change settings for one run with conditions, we recommend to use the setter methods.

**NOTE**: All values set via method will overwrite the values loaded from config file.

---

<a name="example"></a>
## Example

### Cron
If you use Cron's integrated route or command, you only need to listen for the `cron.collectJobs` event. The best place to do this is in Laravel 5 the `/path/to/laravel5/app/Providers/AppServiceProvider.php` file at the `boot` method and in Laravel 4 the `/path/to/laravel/app/start/global.php` file.

#### Laravel 5 - AppServiceProvider.php
```php
<?php namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider {

    //...

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot() {
        // Please note the different namespace
        // and please add a \ in front of your classes in the global namespace
        \Event::listen('cron.collectJobs', function() {

            \Cron::add('example1', '* * * * *', function() {
                // Do some crazy things unsuccessfully every minute
                return 'No';
            });

            \Cron::add('example2', '*/2 * * * *', function() {
                // Do some crazy things successfully every two minute
                return null;
            });

            \Cron::add('disabled job', '0 * * * *', function() {
                // Do some crazy things successfully every hour
            }, false);
        });
    }
}
```

#### Laravel 4 - global.php
```php
Event::listen('cron.collectJobs', function() {
    Cron::add('example1', '* * * * *', function() {
                    // Do some crazy things unsuccessfully every minute
                    return 'No';
                });

    Cron::add('example2', '*/2 * * * *', function() {
        // Do some crazy things successfully every two minute
        return null;
    });

    Cron::add('disabled job', '0 * * * *', function() {
        // Do some crazy things successfully every hour
    }, false);
});
```

Inside the anonymous function you can use all the Laravel and Cron functions. In the next step you have to configure the route or command which will start Cron.

### Using Cron's integrated route
If you don't have shell access to your server, you can easily use an online cronjob service (Google knows some good provider). This provider will run Cron's route in a defined interval. The Cron route has to be protected because if someone else than the service provider invokes it, our jobs will be executed too often. For that reason we need a security key in addition to the route path. This key can be generated with the `php artisan cron:keygen` command call and has to be set in the Cron config file at the key `cronKey`.
```php
    // Cron application key for securing the integrated Cron run route - if the value is empty, the route is disabled
    'cronKey' => '1PBgabAXdoLTy3JDyi0xRpTR2qNrkkQy'
```
Now you have to configure the address and run interval at your online cronjob service provider. The address for the integrated Cron route is always `http://yourdomain.com/cron.php?key=securitykey`. For the above example this address could be `http://exampledomain.com/cron.php?key=1PBgabAXdoLTy3JDyi0xRpTR2qNrkkQy` and the run interval has to be every minute (due to the job with the name "example1"). Now the jobs were added, the route key was generated and the service provider was configured.

### Using Cron's integrated command
If your hosting provider grants you shell access or you can manage cron jobs with a control panel software (e.g. cPanel or Plesk), the best way to run Cron is to use the integrated `artisan cron:run` command. For the above example the crontab or control panel software command could be `* * * * * /usr/bin/php /var/www/laravel/artisan cron:run`.

**NOTE:** If you want to use Cron's in time check, which will test if the time between two Cron run method calls are correct, please configure the key `runInterval`. In our example we call the route every minute so the value should be `1`.

---

<a name="api"></a>
## API

<a name="addjob"></a>
### Add a cron job

Adding a cron job to Cron is very easy by using the static **add** function. As parameter the **name** of the cron job, the cron **expression** and an anonymous **function** is needed. The boolean **isEnabled** is optional and can enable or disable this cron job from execution (default is enabled).

```php
public static function add($name, $expression, $function, $isEnabled = true) {
```

The **name** is needed for identifying a cron job if an error appears and for logging.

The **expression** is a string of five or optional six subexpressions that describe details of the schedule. The syntax is based on the Linux cron daemon definition.
```
    *    *    *    *    *    *
    -    -    -    -    -    -
    |    |    |    |    |    |
    |    |    |    |    |    + year [optional]
    |    |    |    |    +----- day of week (0 - 7) (Sunday=0 or 7)
    |    |    |    +---------- month (1 - 12)
    |    |    +--------------- day of month (1 - 31)
    |    +-------------------- hour (0 - 23)
    +------------------------- min (0 - 59)
```

The given anonymous **function** will be invoked if the expression details match with the current timestamp. This function should return null in the case of success or anything else if there was an error while executing this job. By default, the error case will be logged to the database and to a Monolog logger object (if logger is enabled).

The **isEnabled** boolean parameter makes it possible to deactivate a job from execution without removing it completely. Later the job execution can be enabled very easily by giving a true boolean to the method. This parameter is optional and the default value is enabled.

#### Example

```php
\Cron::add('example1', '* * * * *', function() {
                    // Do some crazy things successfully every minute
                    return null;
                });
\Cron::add('example2', '*/2 * * * *', function() {
                    // Oh no, this job has errors and runs every two minutes
                    return false;
                }, true);
```

---

<a name="removejob"></a>
### Remove a cron job

To remove a set cron job on runtime use the **remove** method with the cron job name as string parameter.

```php
public static function remove($name) {
```

#### Example

```php
\Cron::add('example1', '* * * * *', function() {
                    // Do some crazy things successfully every minute
                    return null;
                });
\Cron::remove('example1');
```

---

<a name="enabledisable"></a>
### Enable or disable a cron job

After adding an enabled or disabled cron job ($isEnabled boolean parameter of the add method call) you can disable or enable a cron job by name. For this use the **setEnableJob** or **setDisableJob** function.

```php
public static function setEnableJob($jobname, $enable = true) {
```
```php
public static function setDisableJob($jobname) {
```

#### Example

```php
\Cron::add('example1', '* * * * *', function() {
                    // Do some crazy things successfully every minute
                    return null;
                });
\Cron::setDisableJob('example1');
// No jobs will be called
$report = \Cron::run();
\Cron::setEnableJob('example1');
// One job will be called
$report = \Cron::run();
```

#### Getter

To receive the enable status boolean of a job, use the static `isJobEnabled($jobname)` method.

---


<a name="runjob"></a>
### Run the cron jobs

Running the cron jobs is as easy as adding them. Just call the static **run** method and wait until each added cron job expression is checked. As soon as the time of the expression has come, the corresponding cron job will be invoked. That is the Cron magic. The **run** method returns a detailed report. By default Cron reckons that you call this method every minute (* * * * *) and by default the report (with their cron jobs errors) will be logged to database. You can change this interval using the `setRunInterval` function.

```php
public static function run() {
```

#### Example

```php
$report = \Cron::run();
```

**NOTE**: The **run** method call has to be the last function call after adding jobs, setting the interval, deactivating database logging and the other function calls.

---

### Set the run interval

The run interval is the time between two cron job route calls. Some cron service provider only supports calls every 15 or even 30 minutes. In this case you have to set this value to 15 or 30. This value is only important to determine if the current run call is in time. If you have disabled database logging in general, you don't have to care about this.

```php
public static function setRunInterval($minutes) {
```

**NOTE**: If the route call interval is not every minute you have to adjust your cron job expressions to fit to this interval.

#### Example

```php
// Set the run intervall to 15 minutes
\Cron::setRunInterval(15);
// Or set the run intervall to 30 minutes
\Cron::setRunInterval(30);
```

#### Getter

To recieve the current set run interval use the static `getRunInterval()` method.

---

<a name="enablelarvellogging"></a>
### Enable or disable Laravel logging

The Laravel logging facilities provide a layer on top of Monolog. By default, Laravel is configured to create daily log files for your application, and these files are stored in `app/storage/logs`. Cron will use Laravel logging facilities by default. You can disable this by setting the `laravelLogging` value to false in the config file or by calling the **setLaravelLogging** function at runtime.

```php
public static function setLaravelLogging($bool) {
```

**NOTE**: You can add a custom Monolog logger to Cron and enable Laravel logging. In this case all messages will be logged to Laravel and to your custom Monolog logger object.

#### Example

```php
// Laravel logging is enabled by default
\Cron::run();
// Disable Laravel logging
\Cron::setLaravelLogging(false);
// Laravel logging is disabled
\Cron::run();
```

#### Getter

To recieve the enabled or disabled boolean value use the static `isLaravelLogging()` method.

---


<a name="setlogger"></a>
### Set a Monolog logger

If you want to add a custom Monolog logger object to Cron use the static **setLogger** method.

```php
public static function setLogger(\Monolog\Logger $logger = null) {
```

**NOTE**: If you want to remove the logger, just call the **setLogger** method without parameters.

#### Example

```php
\Cron::setLogger(new \Monolog\Logger('cronLogger'));
// And remove the logger again
\Cron::setLogger();
```

#### Getter

To recieve the set logger object use the static `getLogger()` method. If no logger object is set, null will be returned.

---

<a name="disabledatabaselogging"></a>
### Disable database logging

By default database logging is enabled and after each cron run a manager object and job objects will be saved to database. We strongly recommend to keep the database logging activated because only with this option Cron can check if the current run is in time. It could make sense in some cases to deactivate the database logging with the **setDatabaseLogging** method.

```php
public static function setDatabaseLogging($bool) {
```

#### Example

```php
\Cron::setDatabaseLogging(false);
```

#### Getter

To receive the current boolean value of the logging to database variable, just use the static `isDatabaseLogging()` function.

---

<a name="logonlyerrorjobstodatabase"></a>
### Log only error jobs to database

By default Cron will log all jobs to database. Maybe sometimes you want to log only error jobs (which not return null) to database by using the static **setLogOnlyErrorJobsToDatabase** function.

```php
public static function setLogOnlyErrorJobsToDatabase($bool) {
```

#### Example

```php
// Log only error jobs to database
\Cron::setLogOnlyErrorJobsToDatabase(true);
```

#### Getter

To receive the current boolean value of the error job logging, use the static `isLogOnlyErrorJobsToDatabase()` function.

---

<a name="deleteolddatabaseentries"></a>
### Delete old database entries

Cron can delete old database entries for you. During each run method call, Cron checks if there are old manager and job entries in the database and if the reference value is reached, the entries will be deleted. You can change the reference value by calling the **setDeleteDatabaseEntriesAfter** function. The default value is 240 hours (10 days). To disable the deletion of old entries just set the reference value to 0.

```php
public static function setDeleteDatabaseEntriesAfter($hours) {
```

#### Example

```php
// Set the delete database entries reference value to 10 days (24 hours x 10 days)
\Cron::setDeleteDatabaseEntriesAfter(240);
```

#### Getter

To receive the current reference value just use the static `getDeleteDatabaseEntriesAfter` function.

---

<a name="preventoverlapping"></a>
### Prevent overlapping

Cron can prevent overlapping. If this is enabled, only one Cron instance can run at the same time. For example if some jobs need 5 minutes for execution but the Cron route will be called every minute, without preventing overlapping two Cron instances will execute jobs at the same time. When running a job twice at the same time, side effects can occur. Cron can avoid such overlaps by using simple locking techniques.

```php
public static function setEnablePreventOverlapping() {
```

#### Example

```php
// The configuration could be set via config file with the key 'preventOverlapping' or via method
\Cron::setEnablePreventOverlapping();
// Now the Cron run will only run once at the same time

\Cron::setDisablePreventOverlapping();
// Prevent overlapping is disabled and many Cron run executions are possible at the same time
```

#### Getter

To receive the current boolean value just use the static `isPreventOverlapping` function.

**NOTE**: To use the overlapping function, Cron needs writing access to the Laravel storage directory. On some Windows machines the lock file cannot be deleted. If you see a delete error message in your log, please disable this feature.

---

<a name="events"></a>
### Events

Cron supports Laravel events and provides many information about the run status and the job status. With this you can react to errors. Cron supports the following events.

* `cron.collectJobs` - fired before run method call to add jobs and to configure Cron. This event is only fired if you use Cron's integrated route or command.
* `cron.beforeRun` - fired before run method call to inform that Cron is about to start. Parameter: `$runDate`.
* `cron.jobError` - fired after a job was exectued and this job returned an error (return value is not equal null). Parameter: `$name`, `$return`, `$runtime`, `$rundate`.
* `cron.jobSuccess` - fired after a job was executed and this job did not return an error (return value is equal null). Parameter: `$name`, `$runtime`, `$rundate`.
* `cron.afterRun` - fired after the Cron run was finished. Parameter: `$rundate`, `$inTime`, `$runtime`, `$errors` - number of error jobs, `$crons` - array of all exectued jobs (with the keys `$name`, `$return`, `$runtime`), `$lastRun` - array with information of the last Cron run (with the keys `$rundate` and `$runtime`). The `$lastRun` parameter is an empty array, if Cron is called the first time or if database logging is disabled and therefore the `$inTime` parameter is equals `-1`.
* `cron.locked` - fired if lock file was found. Parameter: `$lockfile`.

To subscribe to an event, use Laravels `Event` facility. The best place for this is the `/path/to/laravel/app/start/global.php` file.
```php
\Event::listen('cron.jobError', function($name, $return, $runtime, $rundate){
    \Log::error('Job with the name ' . $name . ' returned an error.');
});
```

---

<a name="commands"></a>
### Commands

Cron brings you the following Laravel commands.

* `cron:run` - fires the `cron.collectJobs` event and starts Cron.
* `cron:list` - fires the `cron.collectJobs` event and lists all registered Cron jobs.
* `cron:keygen` - generates a security token with 32 characters.

---

<a name="reset"></a>
### Reset Cron

To reset the cron management, call the static **reset** method. It will reset all variables to the default values.

```php
public static function reset() {
```

#### Example

```php
\Cron::add('example1', '* * * * *', function() {
                    // Do some crazy things successfully every minute
                    return null;
                });
\Cron::setLogger(new \Monolog\Logger('cronLogger'));
\Cron::reset();
// \Cron::remove('example1') === false
// \Cron::getLogger() === NULL
```

---

<a name="faq"></a>
## Frequently Asked Questions

### Do I really need crontab or an online cronjob service
Yes, you do. In comparison to a Java application server for example, PHP only runs if it is executed. If crontab or an online cronjob service provider calls PHP and starts the application, Cron can execute the jobs and will start the work. If PHP is not started, the application sleeps and nothing happens.

### What is the best interval to call the route or command?
The best interval depends on your jobs. If one job should be executed every minute and another every five minutes, the route or command has to be called every minute. In general you have to find the greatest common divisor of your jobs. Please don't forget to change the `runInterval` config value if the route or command is not called every minute (default value) and if you want to use Cron's in time check.

### Cron is not running properly and returns `runtime` and `inTime` with value `-1`
By default Cron prevents overlapping. This means that only one Cron instance will run at the same time. If another instance is called, Cron will not run and will return the runtime and inTime parameter with the value -1. On some Windows machines the deletion of the lock file fails and you have to disable this feature. Please have a look at the [prevent overlapping section](#preventoverlapping).

---

<a name="changelog"></a>
## Changelog

### 2017/09/13 - 1.2
* Adding support for Laravel 5.5
* Adding support for Laravel 5.4

### 2016/11/15 - 1.1.2
* Adding support for Symfonys 3 Table class in Laravel 5.2

### 2016/06/07 - 1.1.1
* Fixing bug with Laravel 5.2

### 2015/03/02 - 1.1.0
* Adding Laravel 5 support
* Adding index to 'cron_job' table
* Removing Eloquent class aliases
* Changing 'cron_manager_id' column of 'cron_job' table to unsigned
* Fixing 'setDisableInTimeCheck' method

### 2015/02/02 - 1.0.2
* Adding cron.locked event
* Marking Cron as stable
* Changing $function parameter type to "callable" to fix IDE type hints

### 2014/10/13 - 1.0.1
* Adding try-catch-finally block to the run method to always remove the lock file
* Adding $lastRun parameter to the cron.afterRun event
* Adding Laravel 5 composer support
* Removing return-string truncating after 500 characters
* Fixing cron.afterRun event

### 2014/06/12 - 1.0.0
* Adding Laravel route with security token
* Adding Artisan command for generating security token
* Adding Artisan command for running Cron easily with e.g. crontab
* Adding Artisan command for list all jobs, added via event
* Adding events
* Adding overlapping protection
* Changing default value for config key 'logOnlyErrorJobsToDatabase' to false
* Fixing PHP doc markup
* Generating API
* Refurbishing this README file
* Minor bug fixes

### 2014/02/11 - v0.9.5
* Bug fixing release
* Fixing bug with PSR0 Autoloading
* Fixing time bug - if a job took more than one minute for execution the following jobs were not handled

### 2013/11/12 - v0.9.4
* Adding Laravel logging facilities - by default Cron will log to Laravel now
* Adding Exceptions - Cron will throw InvalidArgumentExceptions and UnexpectedValueExceptions now
* Minor bug fixes

### 2013/11/01 - v0.9.3
* Adding facade for Cron - you can use `Cron` instead of `\Liebig\Cron\Cron` now
* Adding facade test cases

---

## License

The MIT License (MIT)

Copyright (c) 2013 - 2016 Marc Liebig

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.

Icon Copyright (c) Timothy Miller (http://www.iconfinder.com/icondetails/171279/48/alarm_bell_clock_time_icon) under Creative Commons (Attribution-Share Alike 3.0 Unported) License - Thank you for this awesome icon you own
