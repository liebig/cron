# ![alt text](https://raw.github.com/liebig/cron/master/icon.png "Cron") Cron ![project status](http://stillmaintained.com/liebig/cron.png)
Job scheduling for Laravel

Cron can be used for easily performing cron jobs in Laravel without using Artisan commands. The Cron way is to define a route which is called a variable number of minutes (default is every minute - * * * * *). To this route definition add your functions with their cron expressions. Each time the cron route is called, all cron jobs with a suitable cron expression will be called as well. And that is the Cron magic! Additionally Cron logs every run with the error jobs into the database for you and if you wish into a Monolog logger instance. This cron package is a holistic cron manager for your Laravel website.  


- [Overview](#overview)
- [Installation](#installation)
- [Usage](#usage)
- [|--Add a cron job](#addjob)
- [|--Remove a cron job](#removejob)
- [|--Enable / disable a cron job](#enabledisable)
- [|--Run the cron jobs](#runjob)
- [|--Enable / Laravel logging](#enablelarvellogging)
- [|--Set a Monolog logger](#setlogger)
- [|--Disable database logging](#disabledatabaselogging)
- [|--Log only error jobs to database](#logonlyerrorjobstodatabase)
- [|--Delete old database entries](#deleteolddatabaseentries)
- [|--Reset Cron](#reset)
- [|--Changing default values](#defaultvalues)
- [Full example](#fullexample)

---

<a name="overview"></a>
## Overview

You have to
*   ... download this package
*   ... define a route with all cron job definitions, closing with the `run()` method call
*   ... buy or rent a server or service which call the defined cron route every predefined number of minutes (default is every minute) as normal a web request (e.g. with wget)

You don't have to
*   ... create Artisan commands
*   ... own shell access to your server
*   ... run the regular cron route requests on the same machine where your Laravel site is located
*   ... worry about (cron) job management anymore

**NOTE**: If you have any trouble, questions or suggestions just open an issue. It would be nice to hear from you.

---

<a name="installation"></a>
## Installation

1.  Add `"liebig/cron": "dev-master"` to your `/laravel/composer.json` file at the `"require":` section (Find more about composer at http://getcomposer.org/)
2.  Run the `composer update --no-dev` command in your shell from your `/laravel/` directory 
3.  Add `'Liebig\Cron\CronServiceProvider'` to your `'providers'` array in the `app\config\app.php` file
4.  Migrate the database with running the command `php artisan migrate --package="liebig/cron"`
5.  Publish the configuration file with running the command `php artisan config:publish liebig/cron` - now you find the Cron configuration file in `/laravel/app/config/packages/liebig/cron` and this file won't be overwritten at any update
6.  Now you can use `Cron` everywhere for free

**NOTE**: Since version v0.9.3 you can use `Cron` instead of `\Liebig\Cron\Cron`

---

<a name="usage"></a>
## Usage

<a name="addjob"></a>
### Add a cron job

Adding a cron job to Cron is very easy by using the static **add** function. As parameter the **name** of the cron job, the cron **expression** and an anonymous **function** is needed. The boolean **isEnabled** is optional and can enable or disable this cron job execution (default is enabled).

```
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

```
Cron::add('example1', '* * * * *', function() {
                    // Do some crazy things successfully every minute
                    return null;
                });
Cron::add('example2', '*/2 * * * *', function() {
                    // Oh no, this job has errors and runs every two minutes
                    return false;
                }, true);
```

---

<a name="removejob"></a>
### Remove a cron job

To remove a set cron job on runtime use the **remove** method with the cron job name as string parameter.

```
public static function remove($name) {
```

#### Example

```
Cron::add('example1', '* * * * *', function() {
                    // Do some crazy things successfully every minute
                    return null;
                });
Cron::remove('example1');
```

---

<a name="enabledisable"></a>
### Enable or disable a cron job 

After adding an enabled or disabled cron job ($isEnabled boolean parameter of the add method call) you can disable or enable a cron job by name. For this use the **setEnableJob** or **setDisableJob** function.

```
public static function setEnableJob($jobname, $enable = true) {
```
```
public static function setDisableJob($jobname) {
```

#### Example

```
Cron::add('example1', '* * * * *', function() {
                    // Do some crazy things successfully every minute
                    return null;
                });
Cron::setDisableJob('example1');
// No jobs will be called
$report = Cron::run();
Cron::setEnableJob('example1');
// One job will be called
$report = Cron::run();
```

#### Getter

To receive the enable status boolean of a job, use the static `isJobEnabled($jobname)` method.

---


<a name="runjob"></a>
### Run the cron jobs

Running the cron jobs is as easy as adding them. Just call the static **run** method and wait until each added cron job expression is checked. As soon as the time of the expression has come, the corresponding cron job will be invoked. That is the Cron magic. The **run** method returns a detailed report. By default Cron reckons that you call this method every minute (* * * * *) and by default the report (with their cron jobs errors) will be logged to database. You can change this interval using the `setRunInterval` function.

```
public static function run() {
```

#### Example

```
$report = Cron::run();
```

**NOTE**: The **run** method call must be the last function call after adding jobs, setting the interval and database logging and the other function calls.

---

### Set the run interval

The run interval is the time between two cron job route calls. Some cron service provider only supports calls every 15 or even 30 minutes. In this case you have to set this value to 15 or 30. This value is only important to determine if the current run call is in time. If you have disabled database logging in general, you don't have to care about this.

```
public static function setRunInterval($minutes) {
```

**NOTE**: If the route call interval is not every minute you have to adjust your cron job expressions to fit to this interval.

#### Example

```
// Set the run intervall to 15 minutes
Cron::setRunInterval(15);
// Or set the run intervall to 30 minutes
Cron::setRunInterval(30);
```

#### Getter

To recieve the current set run interval use the static `getRunInterval()` method.

---

TODO
<a name="enablelarvellogging"></a>
### Enable or disable Laravel logging

The Laravel logging facilities provide a layer on top of Monolog. By default, Laravel is configured to create daily log files for your application, and these files are stored in `app/storage/logs`. Cron will use Laravel logging facilities by default. You can disable this by setting the `laravelLogging` config.php value to false or call at runtime the **setLaravelLogging** function.

```
public static function setLaravelLogging($bool) {
```

**NOTE**: You can add a custom Monolog logger to Cron and enable Laravel logging. In this case all messages will be logged to Laravel and to your custom Monolog logger object.

#### Example

```
// Laravel logging is enabled by default
Cron::run();
// Disable Laravel logging
Cron::setLaravelLogging(false);
// Laravel logging is disabled
Cron::run();
```

#### Getter

To recieve the enabled or disabled boolean value use the static `isLaravelLogging()` method.

---


<a name="setlogger"></a>
### Set a Monolog logger

If you want to add a custom Monolog logger object to Cron use the static **setLogger** method.

```
public static function setLogger(\Monolog\Logger $logger = null) {
```

**NOTE**: If you want to remove the logger, just call the **setLogger** method without parameters.

#### Example

```
Cron::setLogger(new \Monolog\Logger('cronLogger'));
// And remove the logger again
Cron::setLogger();
```

#### Getter

To recieve the set logger object use the static `getLogger()` method. If no logger object is set, null will be returned. 

---

<a name="disabledatabaselogging"></a>
### Disable database logging

By default database logging is enabled and after each cron run a manager object and job objects will be saved to database. We strongly recommend to keep the database logging activated because only with this option Cron can check if the current run is in time. It could make sense in some cases to deactivate the database logging with the **setDatabaseLogging** method.

```
public static function setDatabaseLogging($bool) {
```

#### Example

```
Cron::setDatabaseLogging(false);
```

#### Getter

To receive the current boolean value of the logging to database variable, just use the static `isDatabaseLogging()` function.

---

<a name="logonlyerrorjobstodatabase"></a>
### Log only error jobs to database

By default Cron will only log error jobs (which not return null) to database. Maybe you want to log all run jobs to database by using the static **setLogOnlyErrorJobsToDatabase** function. 

```
public static function setLogOnlyErrorJobsToDatabase($bool) {
```

#### Example

```
// Log all jobs (not only the error jobs) to database
Cron::setLogOnlyErrorJobsToDatabase(false);
```

#### Getter

To receive the current boolean value of the logging only error jobs to database variable, just use the static `isLogOnlyErrorJobsToDatabase()` function.

---

<a name="deleteolddatabaseentries"></a>
### Delete old database entries

Cron can delete old database entries for you. During each run method call, Cron checks if there are old manager and job entries in the database and if the reference value is reached, the entries will be deleted. You can change the reference value by calling the **setDeleteDatabaseEntriesAfter** function. The default value is 240 hours (10 days). To disable the deletion of old entries just set the reference value to 0.

```
public static function setDeleteDatabaseEntriesAfter($hours) {
```

#### Example

```
// Set the delete database entries reference value to 10 days (24 hours x 10 days)
Cron::setDeleteDatabaseEntriesAfter(240);
```

#### Getter

To receive the current reference value just use the static `getDeleteDatabaseEntriesAfter` function.

---

<a name="reset"></a>
### Reset Cron

To reset the cron management, call the static **reset** method. It will reset all variables to the default values.

```
public static function reset() {
```

#### Example

```
Cron::add('example1', '* * * * *', function() {
                    // Do some crazy things successfully every minute
                    return null;
                });
Cron::setLogger(new \Monolog\Logger('cronLogger'));
Cron::reset();
// Cron::remove('example1') === false
// Cron::getLogger() === NULL
```

---

<a name="defaultvalues"></a>
### Changing default values

Cron is designed to work out of the box without configuration. To enable this behaviour a few default values are set. To change Crons default settings there are two possibilities.

#### Set methods

You can use the Cron set methods (e.g. setDatabaseLogging, setRunInterval) to change the behaviour. This changes are temporary and the set methods must be called every time before running the **run** method. 

#### Config file

The behaviour values will be loaded from a Cron config file. You can change this values easily by editing the `src/config/config.php` file. This is the more permanent way. If you only want to change settings for one run, we recommend to use the setter methods.

---

<a name="fullexample"></a>
## Full example

At first we create a route which should be called in a defined interval.

**NOTE**: We have to protect this route because if someone calls this uncontrolled, our cron management doesn't work. A possibility is to set the route path to a long value. Another good alternative is (if you know the IP address of the calling server) to check if the IP address matchs.

```
Route::get('/Cron/run/c68pd2s4e363221a3064e8807da20s1sf', function () {

});
```

Now we can add our cron jobs to this route and of course call the run method. At the end we print out the report.

```
Route::get('/cron/run/c68pd2s4e363221a3064e8807da20s1sf', function () {
    Cron::add('example1', '* * * * *', function() {
                        // Do some crazy things every minute
                        return null;
                    });
    Cron::add('example2', '*/2 * * * *', function() {
                        // Do some crazy things every two minutes
                        return null;
                    });
    $report = Cron::run();
    print_r ($report);
});
```

And that is the Cron magic. Now we have to ensure that this route is called in an interval. This can be done with renting an own (virtual) server or with an online cronjob service. In both cases Google knows many good providers.

To configure a `wget` web request by using `crontab -e` or a control panel software (e.g. cPanel or Plesk) on an own (virtual) server use the following code:

```
* * * * * wget -O - http://yoursite.com/cron/run/c68pd2s4e363221a3064e8807da20s1sf >/dev/null 2>&1
```

For using `cURL` instead of wget on your server use this code:

```
* * * * * curl "http://yoursite.com/cron/run/c68pd2s4e363221a3064e8807da20s1sf" >/dev/null 2>&1
```

The starting five asterisks are the cron expressions. We want to start our Cron management every minute in this example. The tool `wget` retrieves files using HTTP, HTTPS and FTP. Using the parameter `-O -` causes that the output of the web request will be sent to STDOUT (standard output). `cURL` is a command line tool for getting or sending files using URL syntax. By adding `>/dev/null` we instruct standard output to be redirect to a black hole (/dev/null). By adding `2>&1` we instruct STDERR (standard errors) to also be sent to STDOUT (in this example this is /dev/null). So it will load our website at the Cron route every minute, but never write a file anywhere.

---

## License

The MIT License (MIT)

Copyright (c) 2013 Marc Liebig

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

Icon Copyright (c) Timothy Miller (http://www.iconfinder.com/icondetails/171279/48/alarm_bell_clock_time_icon) under Creative Commons (Attribution-Share Alike 3.0 Unported) License - Thank you for this awesome icon