# ![alt text](https://raw.github.com/liebig/cron/master/icon.png "Cron") Cron ![project status](http://stillmaintained.com/liebig/cron.png)
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

**NOTE**: If you have any trouble, questions or suggestions just open an issue. It would be nice to hear from you.

---

## Installation

**NOTE**: At the moment, this release is a workbench one. That means you need to copy it in your laravel workbench directory, not in the vendor folder. Besides there is no entry on packagist to install this package automatically with composer. With the first official release 1.0.0 we will change this. Thank you for your patience.

1.  Download the master branch (e.g. as ZIP or clone it)
2.  Extract all the files in YOURLARAVELFOLDER\workbench\liebig\cron\ directory (yes, you have to create the folders liebig\cron)
3.  Run the "composer update" command in your shell from YOURLARAVELFOLDER\workbench\liebig\cron\ (we need some additional libraries - find more about composer at http://getcomposer.org/)
4.  Add 'Liebig\Cron\CronServiceProvider' to your 'providers' array in the app\config\app.php file
5.  Migrate the database with running the command 'php artisan migrate --bench="Liebig/Cron"'
6.  Now you can use \Liebig\Cron\Cron everywhere for free

---

## Usage

### Add a cron job

Adding a cron job to Cron is very easy by using the static **add** function. As parameter the **name** of the cron job, the cron **expression** and an anonymous **function** is needed. The boolean **isEnabled** is optional and can enable or disable this cron job execution (default is enabled).

```
public static function add($name, $expression, $function, $isEnabled = true) {
```

The **name** is needed for identify a cron job if an error appears and for logging.

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

The given anonymous **function** will be invoked if the expression details match with the current timestamp. This function should return null in success case or anything else in if there was an error while executing this job. The error case will be logged to database and to a Monolog logger object (if logger is enabled). 

The **isEnabled** boolean parameter makes it possible to deactivate a job from execution without removing it completely. Later the job execution can be enabled very easy by giving a true boolean to the method. This parameter is optional and the default falue is enabled.

#### Example

```
\Liebig\Cron\Cron::add('example1', '* * * * *', function() {
                    // Do some crazy things successfully every minute
                    return null;
                });
\Liebig\Cron\Cron::add('example2', '*/2 * * * *', function() {
                    // Oh no, this job has errors and runs every two minutes
                    return false;
                }, true);
```

---

### Remove a cron job

To remove a set cron job on runtime use the **remove** method with the cron job name as string parameter.

```
public static function remove($name) {
```

#### Example

```
\Liebig\Cron\Cron::add('example1', '* * * * *', function() {
                    // Do some crazy things successfully every minute
                    return null;
                });
\Liebig\Cron\Cron::remove('example1');
```

---

### Run the cron jobs

Running the cron jobs is as easy as adding them. Just call the static **run** method and wait until each added cron job expression is checked and if the time has come, the corresponding cron job will be invoked. That is the Cron magic. The **run** method returns a detailed Cron report. Additionally the report (with their cron jobs errors) will be logged to database. You have the control over your jobs.

```
public static function run($repeatTime = 1) {
```

The optinal **repeatTime** parameter define the time in minutes between two run method calls. In other words, the time between the cron job route will be called. If you call this route every minute (* * * * *) you do not need to define this parameter. But some cron servcie provider only support calls only every 15 or even 30 minutes. In this case you have to set this parameter to 15 or 30. This parameter is only important to determine if the current run call is in time.

**NOTE**: If the route call is not every minute, you have to adjust your cron job expressions to fit with this interval.

#### Example

```
$report = \Liebig\Cron\Cron::run();
// And for a interval of 15 minutes
// $report = \Liebig\Cron\Cron::run(15);
```

---

### Set the Monolog logger

If logging should be activated just add a Monolog logger object to Crons static **setLogger** method. Only Monolog is supported at the moment.

```
public static function setLogger(\Monolog\Logger $logger = null) {
```

**NOTE**: If you want to remove the logger, just call the **setLogger** method without parameters.

#### Example

```
\Liebig\Cron\Cron::setLogger(new \Monolog\Logger('cronLogger'));
// And remove the logger again
// \Liebig\Cron\Cron::setLogger();
```

---

### Get the Monolog logger

To recieve the set logger object use the static **getLogger** method. If no logger object is set, null will be returned. 

```
public static function getLogger() {
```

---

### Reset Cron

To reset the cron management call the static **setLogger** method. It removes all added cron jobs and the Monolog logger object, if one is set.

```
public static function reset() {
```

---

#### Example

```
\Liebig\Cron\Cron::add('example1', '* * * * *', function() {
                    // Do some crazy things successfully every minute
                    return null;
                });
\Liebig\Cron\Cron::setLogger(new \Monolog\Logger('cronLogger'));
\Liebig\Cron\Cron::reset();
// \Liebig\Cron\Cron::getLogger() === NULL
// \Liebig\Cron\Cron::remove('example1') === false
```

---

## Full example

First we create a route which should be called in an defined interval.

**NOTE**: We have to protect this route because if someone call this uncontrolled our cron management doesn't work. A possibility is to set the route path to a long value. Another good alternative is (if you know the IP address of the calling server) to check if the IP address matchs.

```
Route::get('/Cron/run/c68pd2s4e363221a3064e8807da20s1sf', function () {

});
```

Now we can add our cron jobs to this route and of course call the run method. At the end we print the report out.

```
Route::get('/cron/run/c68pd2s4e363221a3064e8807da20s1sf', function () {
    \Liebig\Cron\Cron::add('example1', '* * * * *', function() {
                        // Do some crazy things every minute
                        return null;
                    });
    \Liebig\Cron\Cron::add('example2', '*/2 * * * *', function() {
                        // Do some crazy things every two minutes
                        return null;
                    });
    $report = \Liebig\Cron\Cron::run(1);
    print_r ($report);
});
```

And that is the Cron magic. Now we have to ensure that this route is called in an interval. This can be done with renting an own (virtual) server or with an online cronjob service. In both cases Google know many good provider

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