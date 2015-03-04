<?php

/**
 * CronTest
 *
 * @package Cron
 * @author  Marc Liebig
 * @since   1.0.0
 */
class CronTest extends TestCase {

    /**
     * @var string Holds the path to the test logfile
     */
    private $pathToLogfile;

    /**
     * @var int Laravel version
     */
    private $laravelVersion;

    /**
     * SetUp Method which is called before the class is started
     *
     */
    public static function setUpBeforeClass() {
        parent::setUpBeforeClass();
    }

    /**
     * SetUp Method which is called before a test method is called
     *
     */
    public function setUp() {

        if (function_exists('app')) {
                $laravel = app();
                $version = substr($laravel::VERSION, 0, 1);

                if(is_int(intval($version))) {
                        $this->laravelVersion = intval($version);
                } else {
                        $this->laravelVersion = 4;
                }
        } else {
                $this->laravelVersion = 4;
        }
	
        parent::setUp();

        // Refresh the application and reset Cron
        $this->refreshApplication();
        Cron::reset();
        // Set the default configuration values to the Laravel \Config object
        $this->setDefaultConfigValues();
        
        // Migrate all database tables
        if($this->laravelVersion >= 5) {
            \Artisan::call('migrate', array('--path' => 'vendor/liebig/cron/src/migrations'));
        } else {
            \Artisan::call('migrate', array('--package' => 'liebig/cron'));
        }
		
        // Set the path to logfile to the laravel storage / logs / directory as test.txt file
        // NOTE: THIS FILE HAS TO BE DELETED EACH TIME AFTER THE UNIT TEST WAS STARTED
        $this->pathToLogfile = storage_path() . '/logs/test.txt';
    }

    /**
     * Returns a Monolog Logger instance for testing purpose
     *
     * @return  \Monolog\Logger Return a new Monolog Logger instance
     */
    private function returnLogger() {
        $logger = new \Monolog\Logger('test');
        $logger->pushHandler(new \Monolog\Handler\StreamHandler($this->pathToLogfile, \Monolog\Logger::DEBUG));
        return $logger;
    }

    /**
     * Set default (factory) config values for test cases
     *
     */
    private function setDefaultConfigValues() {
	
        \Config::set('database.connections.sqlite.database', ':memory:');
        \Config::set('database.default', 'sqlite');

        if($this->laravelVersion >= 5) {
            \Config::set('liebigCron.runInterval', 1);
            \Config::set('liebigCron.databaseLogging', true);
            \Config::set('liebigCron.laravelLogging', true);
            \Config::set('liebigCron.logOnlyErrorJobsToDatabase', true);
            \Config::set('liebigCron.deleteDatabaseEntriesAfter', 240);
            \Config::set('liebigCron.preventOverlapping', false);
            \Config::set('liebigCron.cronKey', '');
        } else {
            \Config::set('cron::runInterval', 1);
            \Config::set('cron::databaseLogging', true);
            \Config::set('cron::laravelLogging', true);
            \Config::set('cron::logOnlyErrorJobsToDatabase', true);
            \Config::set('cron::deleteDatabaseEntriesAfter', 240);
            \Config::set('cron::preventOverlapping', false);
            \Config::set('cron::cronKey', '');
        }
		
    }

    /**
     * Test method for setting the Logger
     *
     * @covers \Liebig\Cron\Cron::setLogger
     * @covers \Liebig\Cron\Cron::getLogger
     */
    public function testSetRemoveLogger() {
        $this->assertNull(Cron::getLogger());
        Cron::setLogger($this->returnLogger());
        $this->assertNotNull(Cron::getLogger());
        Cron::setLogger();
        $this->assertNull(Cron::getLogger());
    }

    /**
     * Test method for logging
     * 
     * @covers \Liebig\Cron\Cron::setLogger
     * @covers \Liebig\Cron\Cron::getLogger
     */
    public function testLogging() {
        $this->assertNull(Cron::getLogger());
        Cron::setLogger($this->returnLogger());
        $this->assertNotNull(Cron::getLogger());

        $this->assertFileNotExists($this->pathToLogfile);
        Cron::run();
        $this->assertFileExists($this->pathToLogfile);
        $filesizeBefore = filesize($this->pathToLogfile);
        Cron::run();
        $this->refreshApplication();
        $this->assertGreaterThan($filesizeBefore, filesize($this->pathToLogfile));
    }

    /**
     * Test method for activating and deactivating database logging
     *
     * @covers \Liebig\Cron\Cron::setDatabaseLogging
     */
    public function testDeactivateDatabaseLogging() {
        $i = 0;
        Cron::add('test1', '* * * * *', function() use (&$i) {
                    $i++;
                    return false;
                });
        Cron::add('test2', '* * * * *', function() use (&$i) {
                    $i++;
                    return false;
                });

        Cron::run();
        $this->assertEquals($i, 2);
        $this->assertEquals(\Liebig\Cron\Models\Manager::count(), 1);
        $this->assertEquals(\Liebig\Cron\Models\Job::count(), 2);

        Cron::setDatabaseLogging(false);

        Cron::run();
        $this->assertEquals($i, 4);
        $this->assertEquals(\Liebig\Cron\Models\Manager::count(), 1);
        $this->assertEquals(\Liebig\Cron\Models\Job::count(), 2);

        Cron::setDatabaseLogging(true);

        Cron::run();
        $this->assertEquals($i, 6);
        $this->assertEquals(\Liebig\Cron\Models\Manager::count(), 2);
        $this->assertEquals(\Liebig\Cron\Models\Job::count(), 4);
    }

    /**
     * Test method for activating and deactivating the logging of all jobs to Database
     *
     * @covers \Liebig\Cron\Cron::setLogOnlyErrorJobsToDatabase
     */
    public function testLogAllJobsToDatabase() {

        $i = 0;
        Cron::add('test1', '* * * * *', function() use (&$i) {
                    $i++;
                    return null;
                });
        Cron::add('test2', '* * * * *', function() use (&$i) {
                    $i++;
                    return true;
                });
        Cron::add('test3', '* * * * *', function() use (&$i) {
                    $i++;
                    return false;
                });
        Cron::add('test4', '* * * * *', function() use (&$i) {
                    $i++;
                    return null;
                });

        Cron::setLogOnlyErrorJobsToDatabase(false);

        Cron::run();
        $this->assertEquals(4, $i);

        $jobs = \Liebig\Cron\Models\Job::all();
        $this->assertEquals(4, count($jobs));

        $this->assertEquals('test1', $jobs[0]->name);
        $this->assertEquals('', $jobs[0]->return);

        $this->assertEquals('test2', $jobs[1]->name);
        $this->assertEquals('true', $jobs[1]->return);

        $this->assertEquals('test3', $jobs[2]->name);
        $this->assertEquals('false', $jobs[2]->return);

        $this->assertEquals('test4', $jobs[3]->name);
        $this->assertEquals('', $jobs[3]->return);

        Cron::setLogOnlyErrorJobsToDatabase(true);

        Cron::run();
        $this->assertEquals(8, $i);
        $jobs2 = \Liebig\Cron\Models\Job::all();
        $this->assertEquals(6, count($jobs2));

        $this->assertEquals('test2', $jobs2[4]->name);
        $this->assertEquals('true', $jobs2[4]->return);

        $this->assertEquals('test3', $jobs2[5]->name);
        $this->assertEquals('false', $jobs2[5]->return);
    }

    /**
     * Test method for return values in database
     *
     * @covers \Liebig\Cron\Cron::run
     */
    public function testJobReturnValue() {

        $i = 0;
        Cron::setLogOnlyErrorJobsToDatabase(false);

        Cron::add('test1', '* * * * *', function() use (&$i) {
                    $i++;
                    return null;
                });
        Cron::add('test2', '* * * * *', function() use (&$i) {
                    $i++;
                    return true;
                });
        Cron::add('test3', '* * * * *', function() use (&$i) {
                    $i++;
                    return false;
                });
        Cron::add('test4', '* * * * *', function() use (&$i) {
                    $i++;
                    return 12345;
                });
        Cron::add('test5', '* * * * *', function() use (&$i) {
                    $i++;
                    return 12.3456789;
                });
        Cron::add('test6', '* * * * *', function() use (&$i) {
                    $i++;
                    return 'Return text';
                });
        Cron::add('test7', '* * * * *', function() use (&$i) {
                    $i++;
                    return new ArrayObject();
                });

        Cron::run();
        $this->assertEquals(7, $i);

        $jobs = \Liebig\Cron\Models\Job::all();
        $this->assertEquals(7, count($jobs));

        $this->assertEquals('test1', $jobs[0]->name);
        $this->assertEquals('', $jobs[0]->return);

        $this->assertEquals('test2', $jobs[1]->name);
        $this->assertEquals('true', $jobs[1]->return);

        $this->assertEquals('test3', $jobs[2]->name);
        $this->assertEquals('false', $jobs[2]->return);

        $this->assertEquals('test4', $jobs[3]->name);
        $this->assertEquals(12345, $jobs[3]->return);

        $this->assertEquals('test5', $jobs[4]->name);
        $this->assertEquals(12.3456789, $jobs[4]->return);

        $this->assertEquals('test6', $jobs[5]->name);
        $this->assertEquals('Return text', $jobs[5]->return);

        $this->assertEquals('test7', $jobs[6]->name);
        $this->assertEquals('Return object type is object', $jobs[6]->return);
    }

    /**
     * Test method for running cron jobs in the right time
     *
     * @covers \Liebig\Cron\Cron::run
     */
    public function testRunWithTime() {
        $i = 0;
        Cron::add('test1', '* * * * *', function() use (&$i) {
                    $i++;
                    return null;
                });
        $runResult1 = Cron::run();
        $this->assertEquals(1, $i);
        $this->assertEquals(0, $runResult1['errors']);
        $this->assertEquals(1, count($runResult1['crons']));
        $this->assertEquals('test1', $runResult1['crons'][0]['name']);
        $this->assertEquals(null, $runResult1['crons'][0]['return']);
        $this->assertEquals(-1, $runResult1['inTime']);

        Cron::add('test2', '* * * * *', function() {
                    return 'return of test2';
                });
        $runResult2 = Cron::run();

        $this->assertEquals(2, $i);
        $this->assertEquals(1, $runResult2['errors']);
        $this->assertEquals(2, count($runResult2['crons']));
        $this->assertEquals('test1', $runResult2['crons'][0]['name']);
        $this->assertEquals(null, $runResult2['crons'][0]['return']);
        $this->assertEquals('test2', $runResult2['crons'][1]['name']);
        $this->assertEquals('return of test2', $runResult2['crons'][1]['return']);

        sleep(60);
        $runResult3 = Cron::run();
        $this->assertEquals(3, $i);
        $this->assertEquals(true, $runResult3['inTime']);

        sleep(25);
        $runResult4 = Cron::run();
        $this->assertEquals(4, $i);
        $this->assertEquals(false, $runResult4['inTime']);

        sleep(95);
        $runResult5 = Cron::run();
        $this->assertEquals($i, 5);
        $this->assertEquals(false, $runResult5['inTime']);

        Cron::setRunInterval(2);
        sleep(120);
        $runResult6 = Cron::run();
        $this->assertEquals($i, 6);
        $this->assertEquals(true, $runResult6['inTime']);
    }

    /**
     * Test method for enabling and disabling cron jobs
     *
     * @covers \Liebig\Cron\Cron::add
     */
    public function testRunEnabled() {
        $i = 0;
        Cron::add('test1', '* * * * *', function() use (&$i) {
                    $i++;
                    return null;
                }, false);

        Cron::run();
        $this->assertEquals(0, $i);
        $this->assertEquals(1, \Liebig\Cron\Models\Manager::count());
        $this->assertEquals(0, \Liebig\Cron\Models\Job::count());

        Cron::run();
        $this->assertEquals(0, $i);
        $this->assertEquals(2, \Liebig\Cron\Models\Manager::count());
        $this->assertEquals(0, \Liebig\Cron\Models\Job::count());

        Cron::add('test2', '* * * * *', function() use (&$i) {
                    $i++;
                    return false;
                }, true);

        Cron::run();
        $this->assertEquals(1, $i);
        $this->assertEquals(3, \Liebig\Cron\Models\Manager::count());
        $this->assertEquals(1, \Liebig\Cron\Models\Job::count());

        Cron::add('test3', '* * * * *', function() use (&$i) {
                    $i++;
                    return false;
                });

        Cron::run();
        $this->assertEquals(3, $i);
        $this->assertEquals(4, \Liebig\Cron\Models\Manager::count());
        $this->assertEquals(3, \Liebig\Cron\Models\Job::count());
    }

    /**
     * Test method for adding cron jobs
     *
     * @expectedException        InvalidArgumentException
     * @expectedExceptionMessage Cron job $name "test2" is not unique and already used.
     * @covers \Liebig\Cron\Cron::add
     */
    public function testAddCronJobWithSameName() {

        $i = 0;
        $this->assertEquals(null, Cron::add('test1', '* * * * *', function() use (&$i) {
                            $i++;
                            return false;
                        }));

        $this->assertEquals(null, Cron::add('test2', '* * * * * *', function() use (&$i) {
                            $i++;
                            return false;
                        }));

        Cron::run();
        $this->assertEquals(2, $i);
        $this->assertEquals(1, \Liebig\Cron\Models\Manager::count());
        $this->assertEquals(2, \Liebig\Cron\Models\Job::count());

        // Should not work - same job name
        Cron::add('test2', '* * * * *', function() {
                    return false;
                });
    }

    /**
     * Test method for adding cron jobs
     *
     * @expectedException        InvalidArgumentException
     * @expectedExceptionMessage Method argument $expression is not set or invalid.
     * @covers \Liebig\Cron\Cron::add
     */
    public function testAddCronJobWithWrongExpressionOne() {

        // Should not work - expression wrong
        Cron::add('test3', 'NOT', function() {
                    return false;
                });
    }

    /**
     * Test method for adding cron jobs
     *
     * @expectedException        InvalidArgumentException
     * @expectedExceptionMessage Method argument $expression is not set or invalid.
     * @covers \Liebig\Cron\Cron::add
     */
    public function testAddCronJobWithWrongExpressionTwo() {

        // Should not work - expression wrong (too long)
        Cron::add('test4', '* * * * * * *', function() {
                    return false;
                });
    }

    /**
     * Test method for adding cron jobs
     *
     * @expectedException        InvalidArgumentException
     * @expectedExceptionMessage Method argument $function is not set or not callable.
     * @covers \Liebig\Cron\Cron::add
     */
    public function testAddCronJobWithWrongFunction() {

        // Should not work - function is not a function
        Cron::add('test5', '* * * * *', 'This is not a function');
    }

    /**
     * Test method for testing the method for removing a single cron job by name
     *
     * @covers \Liebig\Cron\Cron::remove
     */
    public function testRemoveCronJob() {

        $i = 0;
        Cron::add('test1', '* * * * *', function() use (&$i) {
                    $i++;
                    return false;
                });

        Cron::run();
        $this->assertEquals(1, $i);
        $this->assertEquals(1, \Liebig\Cron\Models\Manager::count());
        $this->assertEquals(1, \Liebig\Cron\Models\Job::count());

        $this->assertEquals(true, Cron::remove('test1'));

        Cron::run();
        $this->assertEquals(1, $i);
        $this->assertEquals(2, \Liebig\Cron\Models\Manager::count());
        $this->assertEquals(1, \Liebig\Cron\Models\Job::count());

        Cron::add('test1', '* * * * *', function() use (&$i) {
                    $i++;
                    return false;
                });

        Cron::run();
        $this->assertEquals(2, $i);
        $this->assertEquals(3, \Liebig\Cron\Models\Manager::count());
        $this->assertEquals(2, \Liebig\Cron\Models\Job::count());

        Cron::run();
        $this->assertEquals(3, $i);
        $this->assertEquals(4, \Liebig\Cron\Models\Manager::count());
        $this->assertEquals(3, \Liebig\Cron\Models\Job::count());

        $this->assertEquals(true, Cron::remove('test1'));

        Cron::run();
        $this->assertEquals(3, $i);
        $this->assertEquals(5, \Liebig\Cron\Models\Manager::count());
        $this->assertEquals(3, \Liebig\Cron\Models\Job::count());

        $this->assertEquals(false, Cron::remove('unknown'));

        Cron::run();
        $this->assertEquals(3, $i);
        $this->assertEquals(6, \Liebig\Cron\Models\Manager::count());
        $this->assertEquals(3, \Liebig\Cron\Models\Job::count());
    }

    /**
     * Test method for heavily run 1000 cron jobs five times
     *
     * @covers \Liebig\Cron\Cron::run
     */
    public function testHeavyRunWithLongExpression() {

        $count = 0;
        for ($i = 1; $i <= 1000; $i++) {

            Cron::add('test' . $i, '* * * * * *', function() use (&$count) {
                        $count++;
                        return null;
                    });
        }

        Cron::run();
        $this->assertEquals(1000, $count);

        Cron::run();
        $this->assertEquals(2000, $count);

        Cron::run();
        $this->assertEquals(3000, $count);

        Cron::run();
        $this->assertEquals(4000, $count);

        Cron::run();
        $this->assertEquals(5000, $count);
    }

    /**
     * Test method for testing the save and load functions of the database models
     *
     * @covers \Liebig\Cron\Models\Manager
     * @covers \Liebig\Cron\Models\Job
     */
    public function testDatabaseModelsSaveLoad() {

        $newManager = new \Liebig\Cron\Models\Manager();
        $date = new \DateTime();
        $newManager->rundate = $date;
        $newManager->runtime = 0.007;
        $this->assertNotNull($newManager->save());

        $newError1 = new \Liebig\Cron\Models\Job();
        $newError1->name = "test11";
        $newError1->return = "test11 fails";
        $newError1->runtime = 0.0001;
        $newError1->cron_manager_id = $newManager->id;
        $this->assertNotNull($newError1->save());

        $newError2 = new \Liebig\Cron\Models\Job();
        $newError2->name = "test12";
        $newError2->return = "test12 fails";
        $newError2->runtime = 0.0002;
        $newError2->cron_manager_id = $newManager->id;
        $this->assertNotNull($newError2->save());

        $newError3 = new \Liebig\Cron\Models\Job();
        $newError3->name = "test13";
        $newError3->return = "test13 fails";
        $newError3->runtime = 0.0003;
        $newError3->cron_manager_id = $newManager->id;
        $this->assertNotNull($newError3->save());

        $newSuccess1 = new \Liebig\Cron\Models\Job();
        $newSuccess1->name = "test14";
        $newSuccess1->return = '';
        $newSuccess1->runtime = 0.0004;
        $newSuccess1->cron_manager_id = $newManager->id;
        $this->assertNotNull($newSuccess1->save());

        $newManagerFind = \Liebig\Cron\Models\Manager::find(1);
        $this->assertNotNull($newManagerFind);

        $this->assertEquals($date->format('Y-m-d H:i:s'), $newManagerFind->rundate);
        $this->assertEquals(0.007, $newManagerFind->runtime);

        $finder = $newManagerFind->cronJobs()->get();
        $this->assertEquals(4, count($finder));

        $this->assertEquals('test11', $finder[0]->name);
        $this->assertEquals('test11 fails', $finder[0]->return);
        $this->assertEquals('0.0001', $finder[0]->runtime);
        $this->assertEquals($newManager->id, $finder[0]->cron_manager_id);

        $this->assertEquals('test12', $finder[1]->name);
        $this->assertEquals('test12 fails', $finder[1]->return);
        $this->assertEquals('0.0002', $finder[1]->runtime);
        $this->assertEquals($newManager->id, $finder[1]->cron_manager_id);

        $this->assertEquals('test13', $finder[2]->name);
        $this->assertEquals('test13 fails', $finder[2]->return);
        $this->assertEquals('0.0003', $finder[2]->runtime);
        $this->assertEquals($newManager->id, $finder[2]->cron_manager_id);

        $this->assertEquals('test14', $finder[3]->name);
        $this->assertEquals('', $finder[3]->return);
        $this->assertEquals('0.0004', $finder[3]->runtime);
        $this->assertEquals($newManager->id, $finder[3]->cron_manager_id);
    }

    /**
     * Test method for testing the database models created after the run method
     *
     * @covers \Liebig\Cron\Models\Manager
     * @covers \Liebig\Cron\Models\Job
     */
    public function testDatabaseModelsAfterRun() {

        Cron::add('test1', '* * * * *', function() {
                    return 'test1 fails';
                });
        Cron::add('test2', '* * * * *', function() {
                    return null;
                });
        Cron::add('test3', '* * * * *', function() {
                    return 'test3 fails';
                });

        Cron::run();

        $manager = \Liebig\Cron\Models\Manager::first();

        $this->assertNotNull($manager);
        $errors = $manager->cronJobs()->get();

        $this->assertEquals(2, count($errors));

        $this->assertEquals('test1', $errors[0]->name);
        $this->assertEquals('test1 fails', $errors[0]->return);
        $this->assertEquals($manager->id, $errors[0]->cron_manager_id);

        $this->assertEquals('test3', $errors[1]->name);
        $this->assertEquals('test3 fails', $errors[1]->return);
        $this->assertEquals($manager->id, $errors[1]->cron_manager_id);
    }

    /**
     * Test method for testing the reset method
     *
     * @covers \Liebig\Cron\Cron::reset
     */
    public function testReset() {

        $i = 0;
        Cron::add('test1', '* * * * *', function() use (&$i) {
                    $i++;
                    return false;
                });
        Cron::add('test2', '* * * * *', function() use (&$i) {
                    $i++;
                    return false;
                });

        Cron::run();
        $this->assertEquals(2, $i);
        $this->assertEquals(1, \Liebig\Cron\Models\Manager::count());
        $this->assertEquals(2, \Liebig\Cron\Models\Job::count());

        Cron::setLogger($this->returnLogger());

        Cron::reset();

        Cron::run();

        $this->assertEquals(2, $i);
        $this->assertEquals(2, \Liebig\Cron\Models\Manager::count());
        $this->assertEquals(2, \Liebig\Cron\Models\Job::count());
        $this->assertEquals(null, Cron::getLogger());
    }

    /**
     * Test method for testing the delete old database entries function
     *
     * @covers \Liebig\Cron\Cron::setDeleteDatabaseEntriesAfter
     * @covers \Liebig\Cron\Cron::run
     */
    public function testDeleteOldDatabaseEntries() {

        $manager1 = new \Liebig\Cron\Models\Manager();
        $date1 = new \DateTime();
        date_sub($date1, date_interval_create_from_date_string('240 hours'));
        $manager1->rundate = $date1;
        $manager1->runtime = 0.01;
        $this->assertNotNull($manager1->save());

        $newError1 = new \Liebig\Cron\Models\Job();
        $newError1->name = "test1";
        $newError1->return = "test1 fails";
        $newError1->runtime = 0.001;
        $newError1->cron_manager_id = $manager1->id;
        $this->assertNotNull($newError1->save());

        $newError2 = new \Liebig\Cron\Models\Job();
        $newError2->name = "test2";
        $newError2->return = "test2 fails";
        $newError2->runtime = 0.002;
        $newError2->cron_manager_id = $manager1->id;
        $this->assertNotNull($newError2->save());

        $manager2 = new \Liebig\Cron\Models\Manager();
        $date2 = new \DateTime();
        date_sub($date2, date_interval_create_from_date_string('240 hours'));
        $manager2->rundate = $date2;
        $manager2->runtime = 0.02;
        $this->assertNotNull($manager2->save());

        $newError3 = new \Liebig\Cron\Models\Job();
        $newError3->name = "test3";
        $newError3->return = "tes31 fails";
        $newError3->runtime = 0.003;
        $newError3->cron_manager_id = $manager2->id;
        $this->assertNotNull($newError3->save());

        $manager3 = new \Liebig\Cron\Models\Manager();
        $date3 = new \DateTime();
        date_sub($date3, date_interval_create_from_date_string('10 hours'));
        $manager3->rundate = $date3;
        $manager3->runtime = 0.03;
        $this->assertNotNull($manager3->save());

        $newError4 = new \Liebig\Cron\Models\Job();
        $newError4->name = "test4";
        $newError4->return = "test4 fails";
        $newError4->runtime = 0.004;
        $newError4->cron_manager_id = $manager3->id;
        $this->assertNotNull($newError4->save());

        $newError5 = new \Liebig\Cron\Models\Job();
        $newError5->name = "test5";
        $newError5->return = "test5 fails";
        $newError5->runtime = 0.005;
        $newError5->cron_manager_id = $manager3->id;
        $this->assertNotNull($newError5->save());

        $this->assertEquals(3, \Liebig\Cron\Models\Manager::count());
        $this->assertEquals(5, \Liebig\Cron\Models\Job::count());

        Cron::setDeleteDatabaseEntriesAfter(240);
        Cron::run();

        $this->assertEquals(2, \Liebig\Cron\Models\Manager::count());
        $this->assertEquals(2, \Liebig\Cron\Models\Job::count());

        Cron::setDeleteDatabaseEntriesAfter(0);

        $manager4 = new \Liebig\Cron\Models\Manager();
        $date4 = new \DateTime();
        date_sub($date4, date_interval_create_from_date_string('2400 hours'));
        $manager4->rundate = $date4;
        $manager4->runtime = 0.04;
        $this->assertNotNull($manager4->save());

        $newError6 = new \Liebig\Cron\Models\Job();
        $newError6->name = "test6";
        $newError6->return = "test6 fails";
        $newError6->runtime = 0.006;
        $newError6->cron_manager_id = $manager4->id;
        $this->assertNotNull($newError6->save());

        $newError7 = new \Liebig\Cron\Models\Job();
        $newError7->name = "test7";
        $newError7->return = "test7 fails";
        $newError7->runtime = 0.007;
        $newError7->cron_manager_id = $manager3->id;
        $this->assertNotNull($newError7->save());

        Cron::run();

        $this->assertEquals(4, \Liebig\Cron\Models\Manager::count());
        $this->assertEquals(4, \Liebig\Cron\Models\Job::count());
    }

    /**
     * Test method for enable and disable jobs by using the setter methods
     *
     * @covers \Liebig\Cron\Cron::setEnableJob
     * @covers \Liebig\Cron\Cron::setDisableJob
     * @covers \Liebig\Cron\Cron::isJobEnabled
     */
    public function testEnableDisableJobsBySetter() {

        $iTest1 = 0;
        $iTest2 = 0;
        $iTest3 = 0;

        Cron::add('test1', '* * * * *', function() use (&$iTest1) {
                    $iTest1++;
                    return false;
                }, true);
        Cron::add('test2', '* * * * *', function() use (&$iTest2) {
                    $iTest2++;
                    return false;
                }, true);
        Cron::add('test3', '* * * * *', function() use (&$iTest3) {
                    $iTest3++;
                    return false;
                }, false);

        $this->assertEquals(true, Cron::isJobEnabled('test1'));
        $this->assertEquals(true, Cron::isJobEnabled('test2'));
        $this->assertEquals(false, Cron::isJobEnabled('test3'));
        $this->assertEquals(null, Cron::isJobEnabled('test4'));

        Cron::run();
        $this->assertEquals(1, $iTest1);
        $this->assertEquals(1, $iTest2);
        $this->assertEquals(0, $iTest3);
        $this->assertEquals(1, \Liebig\Cron\Models\Manager::count());
        $this->assertEquals(2, \Liebig\Cron\Models\Job::count());

        $this->assertEquals(true, Cron::setEnableJob('test3'));

        $this->assertEquals(true, Cron::isJobEnabled('test1'));
        $this->assertEquals(true, Cron::isJobEnabled('test2'));
        $this->assertEquals(true, Cron::isJobEnabled('test3'));
        $this->assertEquals(null, Cron::isJobEnabled('test4'));

        Cron::run();
        $this->assertEquals(2, $iTest1);
        $this->assertEquals(2, $iTest2);
        $this->assertEquals(1, $iTest3);
        $this->assertEquals(2, \Liebig\Cron\Models\Manager::count());
        $this->assertEquals(5, \Liebig\Cron\Models\Job::count());

        $this->assertEquals(true, Cron::setDisableJob('test1'));
        $this->assertEquals(true, Cron::setDisableJob('test3'));
        $this->assertEquals(false, Cron::setDisableJob('noSuchJob'));

        $this->assertEquals(false, Cron::isJobEnabled('test1'));
        $this->assertEquals(true, Cron::isJobEnabled('test2'));
        $this->assertEquals(false, Cron::isJobEnabled('test3'));
        $this->assertEquals(null, Cron::isJobEnabled('test4'));

        Cron::run();
        $this->assertEquals(2, $iTest1);
        $this->assertEquals(3, $iTest2);
        $this->assertEquals(1, $iTest3);
        $this->assertEquals(3, \Liebig\Cron\Models\Manager::count());
        $this->assertEquals(6, \Liebig\Cron\Models\Job::count());

        $this->assertEquals(true, Cron::setEnableJob('test2', false));

        $this->assertEquals(false, Cron::isJobEnabled('test1'));
        $this->assertEquals(false, Cron::isJobEnabled('test2'));
        $this->assertEquals(false, Cron::isJobEnabled('test3'));
        $this->assertEquals(null, Cron::isJobEnabled('test4'));

        Cron::run();
        $this->assertEquals(2, $iTest1);
        $this->assertEquals(3, $iTest2);
        $this->assertEquals(1, $iTest3);
        $this->assertEquals(4, \Liebig\Cron\Models\Manager::count());
        $this->assertEquals(6, \Liebig\Cron\Models\Job::count());

        $this->assertEquals(true, Cron::setEnableJob('test1', true));

        $this->assertEquals(true, Cron::isJobEnabled('test1'));
        $this->assertEquals(false, Cron::isJobEnabled('test2'));
        $this->assertEquals(false, Cron::isJobEnabled('test3'));
        $this->assertEquals(null, Cron::isJobEnabled('test4'));

        Cron::run();
        $this->assertEquals(3, $iTest1);
        $this->assertEquals(3, $iTest2);
        $this->assertEquals(1, $iTest3);
        $this->assertEquals(5, \Liebig\Cron\Models\Manager::count());
        $this->assertEquals(7, \Liebig\Cron\Models\Job::count());
    }

    /**
     * Test method for activating and deactivating the logging of all jobs to 
     * Database and testing with full namespace declaration
     *
     * @covers \Liebig\Cron\Cron::setLogOnlyErrorJobsToDatabase
     */
    public function testLogAllJobsToDatabaseWithFullNamespaceDeclaration() {

        $i = 0;
        \Liebig\Cron\Cron::add('test1', '* * * * *', function() use (&$i) {
                    $i++;
                    return null;
                });
        \Liebig\Cron\Cron::add('test2', '* * * * *', function() use (&$i) {
                    $i++;
                    return true;
                });
        \Liebig\Cron\Cron::add('test3', '* * * * *', function() use (&$i) {
                    $i++;
                    return false;
                });
        \Liebig\Cron\Cron::add('test4', '* * * * *', function() use (&$i) {
                    $i++;
                    return null;
                });

        \Liebig\Cron\Cron::setLogOnlyErrorJobsToDatabase(false);

        \Liebig\Cron\Cron::run();
        $this->assertEquals(4, $i);

        $jobs = \Liebig\Cron\Models\Job::all();
        $this->assertEquals(4, count($jobs));

        $this->assertEquals('test1', $jobs[0]->name);
        $this->assertEquals('', $jobs[0]->return);

        $this->assertEquals('test2', $jobs[1]->name);
        $this->assertEquals('true', $jobs[1]->return);

        $this->assertEquals('test3', $jobs[2]->name);
        $this->assertEquals('false', $jobs[2]->return);

        $this->assertEquals('test4', $jobs[3]->name);
        $this->assertEquals('', $jobs[3]->return);

        \Liebig\Cron\Cron::setLogOnlyErrorJobsToDatabase(true);

        \Liebig\Cron\Cron::run();
        $this->assertEquals(8, $i);
        $jobs2 = \Liebig\Cron\Models\Job::all();
        $this->assertEquals(6, count($jobs2));

        $this->assertEquals('test2', $jobs2[4]->name);
        $this->assertEquals('true', $jobs2[4]->return);

        $this->assertEquals('test3', $jobs2[5]->name);
        $this->assertEquals('false', $jobs2[5]->return);
    }

    /**
     * Test method for changing config to an invalid value
     *
     * @expectedException        UnexpectedValueException
     * @expectedExceptionMessage Config option "databaseLogging" is not a boolean or not equals NULL.
     * @covers \Liebig\Cron\Cron::isDatabaseLogging
     */
    public function testWrongConfigValueOne() {

        $this->assertEquals(true, Cron::isDatabaseLogging());
        
        if($this->laravelVersion >=5) {
            Config::set('liebigCron.databaseLogging', '');
            $this->assertEquals(null, Cron::isDatabaseLogging());

            Config::set('liebigCron.databaseLogging', 'true');
            $this->assertEquals(true, Cron::isDatabaseLogging());

            Config::set('liebigCron.databaseLogging', 'false');
            $this->assertEquals(false, Cron::isDatabaseLogging());

            Config::set('liebigCron.databaseLogging', 'Not-a-boolean-and-not-null');
            Cron::isDatabaseLogging();
        } else {
            Config::set('cron::databaseLogging', '');
            $this->assertEquals(null, Cron::isDatabaseLogging());

            Config::set('cron::databaseLogging', 'true');
            $this->assertEquals(true, Cron::isDatabaseLogging());

            Config::set('cron::databaseLogging', 'false');
            $this->assertEquals(false, Cron::isDatabaseLogging());

            Config::set('cron::databaseLogging', 'Not-a-boolean-and-not-null');
            Cron::isDatabaseLogging();
        }
    }

    /**
     * Test method for changing config to an invalid value
     *
     * @expectedException        UnexpectedValueException
     * @expectedExceptionMessage Config option "laravelLogging" is not a boolean or not equals NULL.
     * @covers \Liebig\Cron\Cron::isLaravelLogging
     */
    public function testWrongConfigValueTwo() {

        $this->assertEquals(true, Cron::isLaravelLogging());

        if($this->laravelVersion >=5) {
             Config::set('liebigCron.laravelLogging', '');
            $this->assertEquals(null, Cron::isLaravelLogging());

            Config::set('liebigCron.laravelLogging', 'true');
            $this->assertEquals(true, Cron::isLaravelLogging());

            Config::set('liebigCron.laravelLogging', 'false');
            $this->assertEquals(false, Cron::isLaravelLogging());

            Config::set('liebigCron.laravelLogging', 12345);
            Cron::isDatabaseLogging();
        } else {
            Config::set('cron::laravelLogging', '');
            $this->assertEquals(null, Cron::isLaravelLogging());

            Config::set('cron::laravelLogging', 'true');
            $this->assertEquals(true, Cron::isLaravelLogging());

            Config::set('cron::laravelLogging', 'false');
            $this->assertEquals(false, Cron::isLaravelLogging());

            Config::set('cron::laravelLogging', 12345);
            Cron::isDatabaseLogging();
        }
    }

    /**
     * Test method for setting Laravals build in logging value
     *
     * @expectedException        InvalidArgumentException
     * @expectedExceptionMessage Function paramter $bool with value "not a boolean!" is not a boolean.
     * @covers \Liebig\Cron\Cron::setLaravelLogging
     * @covers \Liebig\Cron\Cron::isLaravelLogging
     */
    public function testSetLaravelLogging() {

        $this->assertEquals(true, Cron::isLaravelLogging());
        Cron::setLaravelLogging(false);
        $this->assertEquals(false, Cron::isLaravelLogging());
        if($this->laravelVersion >=5) {
            Config::set('liebigCron.laravelLogging', null);
        } else {
            Config::set('cron::laravelLogging', null);
        }
        $this->assertEquals(null, Cron::isLaravelLogging());

        Cron::setLaravelLogging('not a boolean!');
    }

    /**
     * Test method for Laravals build in logging value
     *
     * @covers \Liebig\Cron\Cron::setLaravelLogging
     * @covers \Liebig\Cron\Cron::isLaravelLogging
     */
    public function testLaravelLogging() {

        $this->assertEquals(true, Cron::isLaravelLogging());
        $this->assertEquals(null, Cron::getLogger());

        $i = 0;
        $tester = $this;
        Log::listen(function($level, $message) use (&$i, $tester) {

                    switch ($i) {
                        case 0:
                            $tester->assertEquals('notice', $level);
                            $tester->assertEquals('Cron run with manager id 1 has no previous managers.', $message);
                            break;
                        case 1:
                            $tester->assertEquals('info', $level);
                            $tester->assertEquals('The cron run with the manager id 1 was finished without errors.', $message);
                            break;
                        case 2:
                            $tester->assertEquals('error', $level);
                            $tester->assertEquals('Job with the name test1 was run with errors.', $message);
                            break;
                        case 3:
                            $tester->assertEquals('error', $level);
                            break;
                        case 4:
                            $tester->assertEquals('error', $level);
                            $tester->assertEquals('The cron run with the manager id 2 was finished with 1 errors.', $message);
                            break;
                        default:
                            throw new \UnexpectedValueException('Log listener is called to often - test case failed');
                    }

                    $i++;
                });

        Cron::run();

        Cron::add('test1', '* * * * *', function() {
                    return 'test error';
                });

        Cron::run();

        Cron::setLaravelLogging(false);

        Cron::run();

        // Check if the Log is called 5 times
        $this->assertEquals(5, $i);
    }

    /**
     *  Test the run method with $checkRunTime = true
     *
     *  @covers \Liebig\Cron\Cron::run
     */
    public function testRunMethodWithCheckRuntimeDefaultValue() {

        $i = 0;
        $minute = date("i");

        Cron::add('test1', "$minute * * * *", function() use (&$i) {
                    $i++;
                    sleep(60);
                    return null;
                });
        Cron::add('test2', "$minute * * * *", function() use (&$i) {
                    $i++;
                    return null;
                });
        Cron::add('test3', "$minute * * * *", function() use (&$i) {
                    $i++;
                    return false;
                });

        Cron::run(true);
        $this->assertEquals(3, $i);
        $this->assertEquals(1, \Liebig\Cron\Models\Manager::count());
        $this->assertEquals(1, \Liebig\Cron\Models\Job::count());
    }

    /**
     *  Test the run method with $checkRunTime = false
     *
     *  @covers \Liebig\Cron\Cron::run
     */
    public function testRunMethodWithCheckRuntimeSetToFalse() {

        $i = 0;
        $minute = date("i");

        Cron::add('test1', "$minute * * * *", function() use (&$i) {
                    $i++;
                    sleep(60);
                    return null;
                });
        Cron::add('test2', "$minute * * * *", function() use (&$i) {
                    $i++;
                    return null;
                });
        Cron::add('test3', "$minute * * * *", function() use (&$i) {
                    $i++;
                    return false;
                });

        Cron::run(false);
        $this->assertEquals(1, $i);
        $this->assertEquals(1, \Liebig\Cron\Models\Manager::count());
        $this->assertEquals(0, \Liebig\Cron\Models\Job::count());
    }

    /**
     *  Test the prevent job overlapping functionality
     *
     *  @covers \Liebig\Cron\Cron::run
     * @covers \Liebig\Cron\Cron::setEnablePreventOverlapping
     * @covers \Liebig\Cron\Cron::setDisablePreventOverlapping
     * @covers \Liebig\Cron\Cron::isPreventOverlapping
     */
    public function testPreventOverlapping() {
        
        // Disable database logging because we need this later
        Cron::setDatabaseLogging(false);

        // Getting storage path with Laravel3 fallback
        $storagePath = "";
        if (function_exists('storage_path')) {
            $storagePath = storage_path();
        } else if (function_exists('path')) {
            $storagePath = path('storage');
        } else {
            throw new RuntimeException('Could not find the path to Laravels storage directory.');
        }

        // We disabled this at startup -> have a look at the setDefaultConfigValues method
        $this->assertEquals(false, Cron::isPreventOverlapping());

        // Enabling prevention
        Cron::setEnablePreventOverlapping();
        $this->assertEquals(true, Cron::isPreventOverlapping());

        // Disabling prevention
        Cron::setDisablePreventOverlapping();
        $this->assertEquals(false, Cron::isPreventOverlapping());
        
        // Sorry, for windows 7 servers unlink and touch does not work
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            return;
        }
        
        // Create a example job which writes to the $lockfileExists variable if a lock file exists
        $lockfileExists;
        Cron::add('testlockfile', "* * * * *", function() use (&$lockfileExists, $storagePath) {
                    if (file_exists($storagePath . DIRECTORY_SEPARATOR . 'cron.lock')) {
                        $lockfileExists = true;
                    } else {
                        $lockfileExists = false;
                    }
                });
                
        Cron::run();
        $this->assertEquals(false, $lockfileExists);
        $this->assertEquals(false, file_exists($storagePath . DIRECTORY_SEPARATOR . 'cron.lock'));
        $lockfileExists = "";

        Cron::setEnablePreventOverlapping();
        $this->assertEquals(true, Cron::isPreventOverlapping());
        
        Cron::run();
        $this->assertEquals(true, $lockfileExists);
        $this->assertEquals(false, file_exists($storagePath . DIRECTORY_SEPARATOR . 'cron.lock'));
        
        // Simulate two run calls at the same time
        touch($storagePath . DIRECTORY_SEPARATOR . 'cron.lock');
        $this->assertEquals(true, file_exists($storagePath . DIRECTORY_SEPARATOR . 'cron.lock'));
        
        Cron::setDatabaseLogging(true);
        
        $lockfileExists = "Not called";
        $report = Cron::run();
        $this->assertEquals("Not called", $lockfileExists);
        $this->assertEquals(-1, $report['runtime']);
        
        $newManagerFind = \Liebig\Cron\Models\Manager::find(1);
        $this->assertNotNull($newManagerFind);
        $this->assertEquals(-1, $newManagerFind->runtime);
        
        unlink($storagePath . DIRECTORY_SEPARATOR . 'cron.lock');
        $this->assertEquals(false, file_exists($storagePath . DIRECTORY_SEPARATOR . 'cron.lock'));
    }
    
    /**
     *  Test the try-catch-block of the job execution call
     *
     *  @covers \Liebig\Cron\Cron::run
     */
    public function testCatchJobException() {
        
        Cron::add('exception1', "* * * * *", function() {
                    throw new \Exception('Test Exception.');
                    return false;
                });

        Cron::run();
        
        $this->assertEquals(1, \Liebig\Cron\Models\Manager::count());
        
        $jobs = \Liebig\Cron\Models\Job::all();
        $this->assertEquals(1, count($jobs));

        $this->assertEquals('exception1', $jobs[0]->name);
        $this->assertEquals('Exception in job exception1: Test Exception.', $jobs[0]->return);
        
    }
    
    /**
     *  Tests the Cron run events
     *
     *  @covers \Liebig\Cron\Cron::run
     */
    public function testRunEvents() {
        
        $result = array();
        
        \Event::listen('cron.afterRun', function($rundate, $inTime, $runtime, $errors, $crons) use (&$result) {

            array_push($result, array($rundate, $inTime, $runtime, $errors, $crons));
        });
        
        \Event::listen('cron.collectJobs', function() use (&$result) {

            array_push($result, "Collect");
            
            Cron::add('test1', "* * * * *", function() use (&$result) {
                    array_push($result, 'Job');
                    return 'No';
                });
                
            Cron::add('test2', "* * * * *", function() use (&$result) {
                array_push($result, 'Job');
            });
        });
        
        \Event::listen('cron.beforeRun', function($runDate) use (&$result) {
            $now = new \DateTime();
            
            if(empty($runDate) || !is_int($runDate) || $runDate > $now->getTimestamp()) {
                throw new \Exception('$runDate with value ' . $runDate . ' should not be empty, has to be an integer value and has to be lower then the current timestamp');
            }
            array_push($result, 'Before');
        });
        
        $successArray = array();
        \Event::Listen('cron.jobSuccess', function($name, $runtime, $rundate) use (&$successArray) {
            array_push($successArray, $name); 
        });
        
        $errorArray = array();
        \Event::Listen('cron.jobError', function($name, $runtime, $rundate) use (&$errorArray) {
            array_push($errorArray, $name); 
        });
        
        \Artisan::call('cron:run', array());
        $this->assertEquals(1, \Liebig\Cron\Models\Manager::count());
        $this->assertEquals(1, \Liebig\Cron\Models\Job::count());
        
        $this->assertEquals('Collect', $result[0]);
        $this->assertEquals('Before', $result[1]);
        $this->assertEquals('Job', $result[2]);
        $this->assertEquals('Job', $result[3]);
        
        // [0]$rundate, [1]$inTime, [2]$runtime, [3]$errors, [4]$crons
        $this->assertEquals(true, is_array($result[4]));
        // Errors
        $this->assertEquals(1, $result[4][3]);
        // Crons
        $this->assertEquals(2, count($result[4][4]));
        // Crons -> first -> name
        $this->assertEquals('test1', $result[4][4][0]['name']);
        // Crons -> first -> return
        $this->assertEquals('No', $result[4][4][0]['return']);
        
        $this->assertEquals(1, count($successArray));
        $this->assertEquals('test2', $successArray[0]);
        
        $this->assertEquals(1, count($errorArray));
        $this->assertEquals('test1', $errorArray[0]);
    }
    
    /**
     *  Tests the Cron run events
     *
     *  @covers \Liebig\Cron\Cron::run
     */
    public function testAfterRunEvent() {
        
        $result = array();
        \Event::listen('cron.afterRun', function($rundate, $inTime, $runtime, $errors, $crons, $lastRun) use (&$result) {

            array_push($result, array($rundate, $inTime, $runtime, $errors, $crons, $lastRun));
        });
        
        Cron::add('test1', "* * * * *", function() {
                return 'Test 1 done';
            });

        Cron::add('test2', "* * * * *", function() {
        });

        $firstRun = Cron::run();
        $this->assertEquals(-1, $firstRun['inTime']);
        $this->assertEquals(1, $firstRun['errors']);
        
        sleep(5);
        
        $secondRun = Cron::run();
        $this->assertEquals(false, $secondRun['inTime']);
        $this->assertEquals(1, $secondRun['errors']);
        
        // inTime
        $this->assertEquals(-1, $result[0][1]);
        $this->assertEquals(false, $result[1][1]);
        
        // errors
        $this->assertEquals(1, $result[0][3]);
        $this->assertEquals(1, $result[1][3]);
        
        // lastRun
        $this->assertEquals($result[0][5], array());
        $secondRundate = new \DateTime($result[1][5]['rundate']);
        $this->assertEquals($firstRun['rundate'], $secondRundate->getTimestamp());
    }
    
    
    /**
     *  Tests the Cron run command
     *
     *  @covers \Liebig\Cron\RunCommand
     */
    public function testRunCommand() {
        
        $result = array();
        \Event::listen('cron.collectJobs', function() use (&$result)  {
            
            Cron::add('test1', "* * * * *", function() use (&$result)  {
                        array_push($result, 'test1');
                });
                
            Cron::add('test2', "* * * * *", function() use (&$result)  {
                array_push($result, 'test2');
                return 'No';
            });
        });
        
        \Artisan::call('cron:run', array());
        $this->assertEquals(1, \Liebig\Cron\Models\Manager::count());
        $this->assertEquals(1, \Liebig\Cron\Models\Job::count());
        
        $this->assertEquals(2, count($result));
        $this->assertEquals('test1', $result[0]);
        $this->assertEquals('test2', $result[1]);
    }
    
    /**
     *  Tests the Cron list command
     *
     *  @covers \Liebig\Cron\ListCommand
     */
    public function testListCommand() {
       
        $commandOutput = '';
	
        if($this->laravelVersion >= 5) {
            \Artisan::call('cron:list', array());
            $commandOutput = \Artisan::output();
        } else {
            $outputStream = new \Symfony\Component\Console\Output\StreamOutput(
                fopen('php://output', 'w')
            );
            ob_start();
            \Artisan::call('cron:list', array(), $outputStream);
            $commandOutput = ob_get_clean();
        }
	
        $this->assertTrue(is_int(strpos($commandOutput, 'Jobname')));
        $this->assertTrue(is_int(strpos($commandOutput, 'Expression')));
        $this->assertTrue(is_int(strpos($commandOutput, 'Activated')));
        
        \Event::listen('cron.collectJobs', function()  {
            
            Cron::add('test1', "* * * * *", function()  {
                });
                
            Cron::add('test2', "* * * * *", function() {
                return 'No';
            }, false);
        });
        
        if($this->laravelVersion >= 5) {
            \Artisan::call('cron:list', array());
            $commandOutput = \Artisan::output();
        } else {
            $outputStream = new \Symfony\Component\Console\Output\StreamOutput(
                fopen('php://output', 'w')
            );
            ob_start();
            \Artisan::call('cron:list', array(), $outputStream);
            $commandOutput = ob_get_clean();
        }
        
        $this->assertTrue(is_int(strpos($commandOutput, 'Jobname')));
        $this->assertTrue(is_int(strpos($commandOutput, 'Expression')));
        $this->assertTrue(is_int(strpos($commandOutput, 'Activated')));
        $this->assertTrue(is_int(strpos($commandOutput, 'test1')));
        $this->assertTrue(is_int(strpos($commandOutput, 'test2')));
        $this->assertTrue(is_int(strpos($commandOutput, '* * * * *')));
        $this->assertTrue(is_int(strpos($commandOutput, 'Enabled')));
        $this->assertTrue(is_int(strpos($commandOutput, 'Disabled')));
    }
    
    /**
     *  Tests the Cron keygen command
     *
     *  @covers \Liebig\Cron\KeygenCommand
     */
    public function testKeygenCommand() {
	
        $commandOutput = '';
        
	if($this->laravelVersion >= 5) {
            \Artisan::call('cron:keygen', array());
            $commandOutput = \Artisan::output();
        } else {
            $outputStream = new \Symfony\Component\Console\Output\StreamOutput(
                fopen('php://output', 'w')
            );
            ob_start();
            \Artisan::call('cron:keygen', array(), $outputStream);
            $commandOutput = ob_get_clean();
        }
        
        $commandOutput = str_replace("\r",'', $commandOutput);
        $commandOutput = str_replace("\n",'', $commandOutput);

        $this->assertEquals(32, strlen($commandOutput));
        $this->assertTrue(ctype_alnum($commandOutput));
  
        if($this->laravelVersion >= 5) {
            \Artisan::call('cron:keygen', array('length' => 5));
            $commandOutput = \Artisan::output();
        } else {
            $outputStream = new \Symfony\Component\Console\Output\StreamOutput(
                fopen('php://output', 'w')
            );
            ob_start();
            \Artisan::call('cron:keygen', array('length' => 5), $outputStream);
            $commandOutput = ob_get_clean();
        }
        
        $commandOutput = str_replace("\r",'', $commandOutput);
        $commandOutput = str_replace("\n",'', $commandOutput);
        
        $this->assertEquals(5, strlen($commandOutput));
        $this->assertTrue(ctype_alnum($commandOutput));
    }
    
    /**
     *  Tests the Cron run route without setting up the security key
     *
     *  @covers \Liebig\Cron\CronServiceProvider
     */
    public function testRunRouteWithoutKey() {
        
        \Event::listen('cron.collectJobs', function()  {
            
            Cron::add('test1', "* * * * *", function()  {
                });
                
            Cron::add('test2', "* * * * *", function() {
                return 'No';
            });
        });
        
        if($this->laravelVersion >= 5) {
            $response = $this->call('GET', 'cron.php');
            $this->assertEquals(404, $response->getStatusCode());
        } else {
            try { 
                $this->call('GET', 'cron.php');
                $this->fail('The Symfony\Component\HttpKernel\Exception\NotFoundHttpException has not been raised.');
            } catch (\Exception $e) { 
                $this->assertEquals('Symfony\Component\HttpKernel\Exception\NotFoundHttpException', get_class($e));
            }
        }
    }
    
    /**
     *  Tests the Cron run route with setting up the security key but without sending a key
     *
     *  @covers \Liebig\Cron\CronServiceProvider
     */
    public function testRunRouteWithKeyWithNoSendKey() {
        
        \Event::listen('cron.collectJobs', function()  {
            
            Cron::add('test1', "* * * * *", function()  {
                });
                
            Cron::add('test2', "* * * * *", function() {
                return 'No';
            });
        });
        
        if($this->laravelVersion >= 5) {
                \Config::set('liebigCron.cronKey', 'yT7yt3sa4tg5vtlLWbofF95v65FSWWZ8');
                $response = $this->call('GET', 'cron.php');
                $this->assertEquals(404, $response->getStatusCode());
        } else {
                \Config::set('cron::cronKey', 'yT7yt3sa4tg5vtlLWbofF95v65FSWWZ8');
                try { 
                    $this->call('GET', 'cron.php');
                    $this->fail('The Symfony\Component\HttpKernel\Exception\NotFoundHttpException has not been raised.');
                } catch (\Exception $e) { 
                    $this->assertEquals('Symfony\Component\HttpKernel\Exception\NotFoundHttpException', get_class($e));
                }
        }
    }
    
    /**
     *  Tests the Cron run route with setting up the security key but with sending a wrong key
     *
     *  @covers \Liebig\Cron\CronServiceProvider
     */
    public function testRunRouteWithKeyWithWrongSendKey() {
        
        \Event::listen('cron.collectJobs', function()  {
            
            Cron::add('test1', "* * * * *", function()  {
                });
                
            Cron::add('test2', "* * * * *", function() {
                return 'No';
            });
        });
        
        if($this->laravelVersion >= 5) {
            \Config::set('liebigCron.cronKey', 'yT7yt3sa4tg5vtlLWbofF95v65FSWWZ8');
                
            $response = $this->call('GET', 'cron.php', array('key'=>'yT7yt3sa4tg5vtlLWbofF95v65FSWWZ9'));
            $this->assertEquals(404, $response->getStatusCode());
        } else {
            \Config::set('cron::cronKey', 'yT7yt3sa4tg5vtlLWbofF95v65FSWWZ8');
                
            try { 
                $this->call('GET', 'cron.php', array('key'=>'yT7yt3sa4tg5vtlLWbofF95v65FSWWZ9'));
                $this->fail('The Symfony\Component\HttpKernel\Exception\NotFoundHttpException has not been raised.');
            } catch (\Exception $e) { 
                $this->assertEquals('Symfony\Component\HttpKernel\Exception\NotFoundHttpException', get_class($e));
            }
        }
		
        
    }
    
    /**
     *  Tests the Cron run route with setting up the security key but with sending a key with not alphanumeric characters
     *
     *  @covers \Liebig\Cron\CronServiceProvider
     */
    public function testRunRouteWithKeyWithNotAlphanumericSendKey() {
        
        \Event::listen('cron.collectJobs', function()  {
            
            Cron::add('test1', "* * * * *", function()  {
                });
                
            Cron::add('test2', "* * * * *", function() {
                return 'No';
            });
        });
        
        if($this->laravelVersion >= 5) {
            \Config::set('liebigCron.cronKey', 'yT7yt3sa4tg5vtlLWbofF95v65FSWWZ8');

            $response = $this->call('GET', 'cron.php', array('key'=>'&!€%<>'));
            $this->assertEquals(404, $response->getStatusCode());
        } else {
            \Config::set('cron::cronKey', 'yT7yt3sa4tg5vtlLWbofF95v65FSWWZ8');
            
             try { 
                $this->call('GET', 'cron.php', array('key'=>'&!€%<>'));
                $this->fail('The Symfony\Component\HttpKernel\Exception\NotFoundHttpException has not been raised.');
            } catch (\Exception $e) { 
                $this->assertEquals('Symfony\Component\HttpKernel\Exception\NotFoundHttpException', get_class($e));
            }
        }
		
        
    }
    
    /**
     *  Tests the Cron run route with setting up the security key and with sending the right key
     *
     *  @covers \Liebig\Cron\CronServiceProvider
     */
    public function testRunRouteWithKeyWithCorrectSendKey() {
        
        \Event::listen('cron.collectJobs', function()  {
            
            Cron::add('test1', "* * * * *", function()  {
                });
                
            Cron::add('test2', "* * * * *", function() {
                return 'No';
            });
        });
        
        if($this->laravelVersion >= 5) {
                \Config::set('liebigCron.cronKey', 'yT7yt3sa4tg5vtlLWbofF95v65FSWWZ8');
                \Config::set('liebigCron.logOnlyErrorJobsToDatabase', false);
        } else {
                \Config::set('cron::cronKey', 'yT7yt3sa4tg5vtlLWbofF95v65FSWWZ8');
                \Config::set('cron::logOnlyErrorJobsToDatabase', false);
        }
		
        $response = $this->call('GET', 'cron.php', array('key'=>'yT7yt3sa4tg5vtlLWbofF95v65FSWWZ8'));
        
        $this->assertEquals(200, $response->getStatusCode());
        
        $this->assertEquals(1, \Liebig\Cron\Models\Manager::count());
        $this->assertEquals(2, \Liebig\Cron\Models\Job::count());

        $jobs = \Liebig\Cron\Models\Job::all();
        $this->assertEquals(2, count($jobs));

        $this->assertEquals('test1', $jobs[0]->name);
        $this->assertEquals('', $jobs[0]->return);
        
        $this->assertEquals('test2', $jobs[1]->name);
        $this->assertEquals('No', $jobs[1]->return);
    }
    
}

