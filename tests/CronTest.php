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
        parent::setUp();

        // Refresh the application and reset Cron
        $this->refreshApplication();
        \Liebig\Cron\Cron::reset();
        // Set the default configuration values to the Laravel \Config object
        $this->setDefaultConfigValues();

        // Migrate all database tables
        \Artisan::call('migrate', array('--package' => 'liebig/cron'));

        // Set the path to logfile to the laravel storage / logs / directory as test.txt file
        // NOTE: THIS FILE MUST BE DELETED EACH TIME AFTER THE UNIT TEST WAS STARTED
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
        \Config::set('cron::runInterval', 1);
        \Config::set('cron::databaseLogging', true);
        \Config::set('cron::logOnlyErrorJobsToDatabase', true);
        \Config::set('cron::deleteDatabaseEntriesAfter', 240);
    }

    /**
     * Test method for setting the Logger
     *
     * @covers \Liebig\Cron\Cron::setLogger
     * @covers \Liebig\Cron\Cron::getLogger
     */
    public function testSetRemoveLogger() {
        $this->assertNull(\Liebig\Cron\Cron::getLogger());
        \Liebig\Cron\Cron::setLogger($this->returnLogger());
        $this->assertNotNull(\Liebig\Cron\Cron::getLogger());
        \Liebig\Cron\Cron::setLogger();
        $this->assertNull(\Liebig\Cron\Cron::getLogger());
    }

    /**
     * Test method for logging
     * 
     * @covers \Liebig\Cron\Cron::setLogger
     * @covers \Liebig\Cron\Cron::getLogger
     */
    public function testLogging() {
        $this->assertNull(\Liebig\Cron\Cron::getLogger());
        \Liebig\Cron\Cron::setLogger($this->returnLogger());
        $this->assertNotNull(\Liebig\Cron\Cron::getLogger());

        $this->assertFileNotExists($this->pathToLogfile);
        \Liebig\Cron\Cron::run();
        $this->assertFileExists($this->pathToLogfile);
        $filesizeBefore = filesize($this->pathToLogfile);
        \Liebig\Cron\Cron::run();
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
        \Liebig\Cron\Cron::add('test1', '* * * * *', function() use (&$i) {
                    $i++;
                    return false;
                });
        \Liebig\Cron\Cron::add('test2', '* * * * *', function() use (&$i) {
                    $i++;
                    return false;
                });

        \Liebig\Cron\Cron::run();
        $this->assertEquals($i, 2);
        $this->assertEquals(\Liebig\Cron\models\Manager::count(), 1);
        $this->assertEquals(\Liebig\Cron\models\Job::count(), 2);

        \Liebig\Cron\Cron::setDatabaseLogging(false);

        \Liebig\Cron\Cron::run();
        $this->assertEquals($i, 4);
        $this->assertEquals(\Liebig\Cron\models\Manager::count(), 1);
        $this->assertEquals(\Liebig\Cron\models\Job::count(), 2);

        \Liebig\Cron\Cron::setDatabaseLogging(true);

        \Liebig\Cron\Cron::run();
        $this->assertEquals($i, 6);
        $this->assertEquals(\Liebig\Cron\models\Manager::count(), 2);
        $this->assertEquals(\Liebig\Cron\models\Job::count(), 4);
    }

    /**
     * Test method for activating and deactivating the logging of all jobs to Database
     *
     * @covers \Liebig\Cron\Cron::setLogOnlyErrorJobsToDatabase
     */
    public function testLogAllJobsToDatabase() {

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

        $jobs = \Liebig\Cron\models\Job::all();
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
        $jobs2 = \Liebig\Cron\models\Job::all();
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
        \Liebig\Cron\Cron::setLogOnlyErrorJobsToDatabase(false);

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
                    return 12345;
                });
        \Liebig\Cron\Cron::add('test5', '* * * * *', function() use (&$i) {
                    $i++;
                    return 12.3456789;
                });
        \Liebig\Cron\Cron::add('test6', '* * * * *', function() use (&$i) {
                    $i++;
                    return 'Return text';
                });
        \Liebig\Cron\Cron::add('test7', '* * * * *', function() use (&$i) {
                    $i++;
                    return new ArrayObject();
                });

        \Liebig\Cron\Cron::run();
        $this->assertEquals(7, $i);

        $jobs = \Liebig\Cron\models\Job::all();
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
        $this->assertEquals('Return value of job test7 has the type object - this type cannot be displayed as string (type error)', $jobs[6]->return);
    }

    /**
     * Test method for running cron jobs in the right time
     *
     * @covers \Liebig\Cron\Cron::run
     */
    public function testRunWithTime() {
        $i = 0;
        \Liebig\Cron\Cron::add('test1', '* * * * *', function() use (&$i) {
                    $i++;
                    return null;
                });
        $runResult1 = \Liebig\Cron\Cron::run();
        $this->assertEquals(1, $i);
        $this->assertEquals(0, $runResult1['errors']);
        $this->assertEquals(1, count($runResult1['crons']));
        $this->assertEquals('test1', $runResult1['crons'][0]['name']);
        $this->assertEquals(null, $runResult1['crons'][0]['return']);
        $this->assertEquals(-1, $runResult1['inTime']);

        \Liebig\Cron\Cron::add('test2', '* * * * *', function() {
                    return 'return of test2';
                });
        $runResult2 = \Liebig\Cron\Cron::run();

        $this->assertEquals(2, $i);
        $this->assertEquals(1, $runResult2['errors']);
        $this->assertEquals(2, count($runResult2['crons']));
        $this->assertEquals('test1', $runResult2['crons'][0]['name']);
        $this->assertEquals(null, $runResult2['crons'][0]['return']);
        $this->assertEquals('test2', $runResult2['crons'][1]['name']);
        $this->assertEquals('return of test2', $runResult2['crons'][1]['return']);

        sleep(60);
        $runResult3 = \Liebig\Cron\Cron::run();
        $this->assertEquals(3, $i);
        $this->assertEquals(true, $runResult3['inTime']);

        sleep(25);
        $runResult4 = \Liebig\Cron\Cron::run();
        $this->assertEquals(4, $i);
        $this->assertEquals(false, $runResult4['inTime']);

        sleep(90);
        $runResult5 = \Liebig\Cron\Cron::run();
        $this->assertEquals($i, 5);
        $this->assertEquals(false, $runResult5['inTime']);

        \Liebig\Cron\Cron::setRunInterval(2);
        sleep(120);
        $runResult6 = \Liebig\Cron\Cron::run();
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
        \Liebig\Cron\Cron::add('test1', '* * * * *', function() use (&$i) {
                    $i++;
                    return null;
                }, false);

        \Liebig\Cron\Cron::run();
        $this->assertEquals(0, $i);
        $this->assertEquals(1, \Liebig\Cron\models\Manager::count());
        $this->assertEquals(0, \Liebig\Cron\models\Job::count());

        \Liebig\Cron\Cron::run();
        $this->assertEquals(0, $i);
        $this->assertEquals(2, \Liebig\Cron\models\Manager::count());
        $this->assertEquals(0, \Liebig\Cron\models\Job::count());

        \Liebig\Cron\Cron::add('test2', '* * * * *', function() use (&$i) {
                    $i++;
                    return false;
                }, true);

        \Liebig\Cron\Cron::run();
        $this->assertEquals(1, $i);
        $this->assertEquals(3, \Liebig\Cron\models\Manager::count());
        $this->assertEquals(1, \Liebig\Cron\models\Job::count());

        \Liebig\Cron\Cron::add('test3', '* * * * *', function() use (&$i) {
                    $i++;
                    return false;
                });

        \Liebig\Cron\Cron::run();
        $this->assertEquals(3, $i);
        $this->assertEquals(4, \Liebig\Cron\models\Manager::count());
        $this->assertEquals(3, \Liebig\Cron\models\Job::count());
    }

    /**
     * Test method for adding cron jobs
     *
     * @covers \Liebig\Cron\Cron::add
     */
    public function testAddCronJob() {

        $i = 0;
        $this->assertEquals(null, \Liebig\Cron\Cron::add('test1', '* * * * *', function() use (&$i) {
                            $i++;
                            return false;
                        }));

        $this->assertEquals(null, \Liebig\Cron\Cron::add('test2', '* * * * * *', function() use (&$i) {
                            $i++;
                            return false;
                        }));

        \Liebig\Cron\Cron::run();
        $this->assertEquals(2, $i);
        $this->assertEquals(1, \Liebig\Cron\models\Manager::count());
        $this->assertEquals(2, \Liebig\Cron\models\Job::count());

        // Should not work - same job name
        $this->assertEquals(false, \Liebig\Cron\Cron::add('test2', '* * * * *', function() use (&$i) {
                            $i++;
                            return false;
                        }));

        // Should not work - expression wrong
        $this->assertEquals(false, \Liebig\Cron\Cron::add('test3', 'NOT', function() use (&$i) {
                            $i++;
                            return false;
                        }));

        // Should not work - expression wrong (too long)
        $this->assertEquals(false, \Liebig\Cron\Cron::add('test4', '* * * * * * *', function() use (&$i) {
                            $i++;
                            return false;
                        }));

        // Should not work - function is not a function
        $this->assertEquals(false, \Liebig\Cron\Cron::add('test5', '* * * * *', 'This is not a function'));

        \Liebig\Cron\Cron::run();
        $this->assertEquals(4, $i);
        $this->assertEquals(2, \Liebig\Cron\models\Manager::count());
        $this->assertEquals(4, \Liebig\Cron\models\Job::count());
    }

    /**
     * Test method for testing the method for removing a single cron job by name
     *
     * @covers \Liebig\Cron\Cron::remove
     */
    public function testRemoveCronJob() {

        $i = 0;
        \Liebig\Cron\Cron::add('test1', '* * * * *', function() use (&$i) {
                    $i++;
                    return false;
                });

        \Liebig\Cron\Cron::run();
        $this->assertEquals(1, $i);
        $this->assertEquals(1, \Liebig\Cron\models\Manager::count());
        $this->assertEquals(1, \Liebig\Cron\models\Job::count());

        $this->assertEquals(null, \Liebig\Cron\Cron::remove('test1'));

        \Liebig\Cron\Cron::run();
        $this->assertEquals(1, $i);
        $this->assertEquals(2, \Liebig\Cron\models\Manager::count());
        $this->assertEquals(1, \Liebig\Cron\models\Job::count());

        \Liebig\Cron\Cron::add('test1', '* * * * *', function() use (&$i) {
                    $i++;
                    return false;
                });

        \Liebig\Cron\Cron::run();
        $this->assertEquals(2, $i);
        $this->assertEquals(3, \Liebig\Cron\models\Manager::count());
        $this->assertEquals(2, \Liebig\Cron\models\Job::count());

        \Liebig\Cron\Cron::run();
        $this->assertEquals(3, $i);
        $this->assertEquals(4, \Liebig\Cron\models\Manager::count());
        $this->assertEquals(3, \Liebig\Cron\models\Job::count());

        $this->assertEquals(null, \Liebig\Cron\Cron::remove('test1'));

        \Liebig\Cron\Cron::run();
        $this->assertEquals(3, $i);
        $this->assertEquals(5, \Liebig\Cron\models\Manager::count());
        $this->assertEquals(3, \Liebig\Cron\models\Job::count());

        $this->assertEquals(false, \Liebig\Cron\Cron::remove('unknown'));

        \Liebig\Cron\Cron::run();
        $this->assertEquals(3, $i);
        $this->assertEquals(6, \Liebig\Cron\models\Manager::count());
        $this->assertEquals(3, \Liebig\Cron\models\Job::count());
    }

    /**
     * Test method for heavily run 1000 cron jobs five times
     *
     * @covers \Liebig\Cron\Cron::run
     */
    public function testHeavyRunWithLongExpression() {

        $count = 0;
        for ($i = 1; $i <= 1000; $i++) {

            \Liebig\Cron\Cron::add('test' . $i, '* * * * * *', function() use (&$count) {
                        $count++;
                        return null;
                    });
        }

        \Liebig\Cron\Cron::run();
        $this->assertEquals(1000, $count);

        \Liebig\Cron\Cron::run();
        $this->assertEquals(2000, $count);

        \Liebig\Cron\Cron::run();
        $this->assertEquals(3000, $count);

        \Liebig\Cron\Cron::run();
        $this->assertEquals(4000, $count);

        \Liebig\Cron\Cron::run();
        $this->assertEquals(5000, $count);
    }

    /**
     * Test method for testing the save and load functions of the database models
     *
     * @covers \Liebig\Cron\models\Manager
     * @covers \Liebig\Cron\models\Job
     */
    public function testDatabaseModelsSaveLoad() {

        $newManager = new \Liebig\Cron\models\Manager();
        $date = new \DateTime();
        $newManager->rundate = $date;
        $newManager->runtime = 0.007;
        $this->assertNotNull($newManager->save());

        $newError1 = new \Liebig\Cron\models\Job();
        $newError1->name = "test11";
        $newError1->return = "test11 fails";
        $newError1->runtime = 0.0001;
        $newError1->cron_manager_id = $newManager->id;
        $this->assertNotNull($newError1->save());

        $newError2 = new \Liebig\Cron\models\Job();
        $newError2->name = "test12";
        $newError2->return = "test12 fails";
        $newError2->runtime = 0.0002;
        $newError2->cron_manager_id = $newManager->id;
        $this->assertNotNull($newError2->save());

        $newError3 = new \Liebig\Cron\models\Job();
        $newError3->name = "test13";
        $newError3->return = "test13 fails";
        $newError3->runtime = 0.0003;
        $newError3->cron_manager_id = $newManager->id;
        $this->assertNotNull($newError3->save());

        $newSuccess1 = new \Liebig\Cron\models\Job();
        $newSuccess1->name = "test14";
        $newSuccess1->return = '';
        $newSuccess1->runtime = 0.0004;
        $newSuccess1->cron_manager_id = $newManager->id;
        $this->assertNotNull($newSuccess1->save());

        $newManagerFind = \Liebig\Cron\models\Manager::find(1);
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
     * @covers \Liebig\Cron\models\Manager
     * @covers \Liebig\Cron\models\Job
     */
    public function testDatabaseModelsAfterRun() {

        \Liebig\Cron\Cron::add('test1', '* * * * *', function() {
                    return 'test1 fails';
                });
        \Liebig\Cron\Cron::add('test2', '* * * * *', function() {
                    return null;
                });
        \Liebig\Cron\Cron::add('test3', '* * * * *', function() {
                    return 'test3 fails';
                });

        \Liebig\Cron\Cron::run();

        $manager = \Liebig\Cron\models\Manager::first();

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
        \Liebig\Cron\Cron::add('test1', '* * * * *', function() use (&$i) {
                    $i++;
                    return false;
                });
        \Liebig\Cron\Cron::add('test2', '* * * * *', function() use (&$i) {
                    $i++;
                    return false;
                });

        \Liebig\Cron\Cron::run();
        $this->assertEquals(2, $i);
        $this->assertEquals(1, \Liebig\Cron\models\Manager::count());
        $this->assertEquals(2, \Liebig\Cron\models\Job::count());

        \Liebig\Cron\Cron::setLogger($this->returnLogger());

        \Liebig\Cron\Cron::reset();

        \Liebig\Cron\Cron::run();

        $this->assertEquals(2, $i);
        $this->assertEquals(2, \Liebig\Cron\models\Manager::count());
        $this->assertEquals(2, \Liebig\Cron\models\Job::count());
        $this->assertEquals(null, \Liebig\Cron\Cron::getLogger());
    }

    /**
     * Test method for testing the delete old database entries function
     *
     * @covers \Liebig\Cron\Cron::setDeleteDatabaseEntriesAfter
     * @covers \Liebig\Cron\Cron::run
     */
    public function testDeleteOldDatabaseEntries() {

        $manager1 = new \Liebig\Cron\models\Manager();
        $date1 = new \DateTime();
        date_sub($date1, date_interval_create_from_date_string('240 hours'));
        $manager1->rundate = $date1;
        $manager1->runtime = 0.01;
        $this->assertNotNull($manager1->save());

        $newError1 = new \Liebig\Cron\models\Job();
        $newError1->name = "test1";
        $newError1->return = "test1 fails";
        $newError1->runtime = 0.001;
        $newError1->cron_manager_id = $manager1->id;
        $this->assertNotNull($newError1->save());

        $newError2 = new \Liebig\Cron\models\Job();
        $newError2->name = "test2";
        $newError2->return = "test2 fails";
        $newError2->runtime = 0.002;
        $newError2->cron_manager_id = $manager1->id;
        $this->assertNotNull($newError2->save());

        $manager2 = new \Liebig\Cron\models\Manager();
        $date2 = new \DateTime();
        date_sub($date2, date_interval_create_from_date_string('240 hours'));
        $manager2->rundate = $date2;
        $manager2->runtime = 0.02;
        $this->assertNotNull($manager2->save());

        $newError3 = new \Liebig\Cron\models\Job();
        $newError3->name = "test3";
        $newError3->return = "tes31 fails";
        $newError3->runtime = 0.003;
        $newError3->cron_manager_id = $manager2->id;
        $this->assertNotNull($newError3->save());

        $manager3 = new \Liebig\Cron\models\Manager();
        $date3 = new \DateTime();
        date_sub($date3, date_interval_create_from_date_string('10 hours'));
        $manager3->rundate = $date3;
        $manager3->runtime = 0.03;
        $this->assertNotNull($manager3->save());

        $newError4 = new \Liebig\Cron\models\Job();
        $newError4->name = "test4";
        $newError4->return = "test4 fails";
        $newError4->runtime = 0.004;
        $newError4->cron_manager_id = $manager3->id;
        $this->assertNotNull($newError4->save());

        $newError5 = new \Liebig\Cron\models\Job();
        $newError5->name = "test5";
        $newError5->return = "test5 fails";
        $newError5->runtime = 0.005;
        $newError5->cron_manager_id = $manager3->id;
        $this->assertNotNull($newError5->save());

        $this->assertEquals(3, \Liebig\Cron\models\Manager::count());
        $this->assertEquals(5, \Liebig\Cron\models\Job::count());

        \Liebig\Cron\Cron::setDeleteDatabaseEntriesAfter(240);
        \Liebig\Cron\Cron::run();

        $this->assertEquals(2, \Liebig\Cron\models\Manager::count());
        $this->assertEquals(2, \Liebig\Cron\models\Job::count());

        \Liebig\Cron\Cron::setDeleteDatabaseEntriesAfter(0);

        $manager4 = new \Liebig\Cron\models\Manager();
        $date4 = new \DateTime();
        date_sub($date4, date_interval_create_from_date_string('2400 hours'));
        $manager4->rundate = $date4;
        $manager4->runtime = 0.04;
        $this->assertNotNull($manager4->save());

        $newError6 = new \Liebig\Cron\models\Job();
        $newError6->name = "test6";
        $newError6->return = "test6 fails";
        $newError6->runtime = 0.006;
        $newError6->cron_manager_id = $manager4->id;
        $this->assertNotNull($newError6->save());

        $newError7 = new \Liebig\Cron\models\Job();
        $newError7->name = "test7";
        $newError7->return = "test7 fails";
        $newError7->runtime = 0.007;
        $newError7->cron_manager_id = $manager3->id;
        $this->assertNotNull($newError7->save());

        \Liebig\Cron\Cron::run();

        $this->assertEquals(4, \Liebig\Cron\models\Manager::count());
        $this->assertEquals(4, \Liebig\Cron\models\Job::count());
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
        
        \Liebig\Cron\Cron::add('test1', '* * * * *', function() use (&$iTest1) {
                    $iTest1++;
                    return false;
                }, true);
        \Liebig\Cron\Cron::add('test2', '* * * * *', function() use (&$iTest2) {
                    $iTest2++;
                    return false;
                }, true);
        \Liebig\Cron\Cron::add('test3', '* * * * *', function() use (&$iTest3) {
                    $iTest3++;
                    return false;
                }, false);

        $this->assertEquals(true, \Liebig\Cron\Cron::isJobEnabled('test1'));
        $this->assertEquals(true, \Liebig\Cron\Cron::isJobEnabled('test2'));
        $this->assertEquals(false, \Liebig\Cron\Cron::isJobEnabled('test3'));
        $this->assertEquals(null, \Liebig\Cron\Cron::isJobEnabled('test4'));

        \Liebig\Cron\Cron::run();
        $this->assertEquals(1, $iTest1);
        $this->assertEquals(1, $iTest2);
        $this->assertEquals(0, $iTest3);
        $this->assertEquals(1, \Liebig\Cron\models\Manager::count());
        $this->assertEquals(2, \Liebig\Cron\models\Job::count());

        $this->assertEquals(null, \Liebig\Cron\Cron::setEnableJob('test3'));

        $this->assertEquals(true, \Liebig\Cron\Cron::isJobEnabled('test1'));
        $this->assertEquals(true, \Liebig\Cron\Cron::isJobEnabled('test2'));
        $this->assertEquals(true, \Liebig\Cron\Cron::isJobEnabled('test3'));
        $this->assertEquals(null, \Liebig\Cron\Cron::isJobEnabled('test4'));
        
        \Liebig\Cron\Cron::run();
        $this->assertEquals(2, $iTest1);
        $this->assertEquals(2, $iTest2);
        $this->assertEquals(1, $iTest3);
        $this->assertEquals(2, \Liebig\Cron\models\Manager::count());
        $this->assertEquals(5, \Liebig\Cron\models\Job::count());

        $this->assertEquals(null, \Liebig\Cron\Cron::setDisableJob('test1'));
        $this->assertEquals(null, \Liebig\Cron\Cron::setDisableJob('test3'));
        $this->assertEquals(false, \Liebig\Cron\Cron::setDisableJob('noSuchJob'));

        $this->assertEquals(false, \Liebig\Cron\Cron::isJobEnabled('test1'));
        $this->assertEquals(true, \Liebig\Cron\Cron::isJobEnabled('test2'));
        $this->assertEquals(false, \Liebig\Cron\Cron::isJobEnabled('test3'));
        $this->assertEquals(null, \Liebig\Cron\Cron::isJobEnabled('test4'));
        
        \Liebig\Cron\Cron::run();
        $this->assertEquals(2, $iTest1);
        $this->assertEquals(3, $iTest2);
        $this->assertEquals(1, $iTest3);
        $this->assertEquals(3, \Liebig\Cron\models\Manager::count());
        $this->assertEquals(6, \Liebig\Cron\models\Job::count());

        $this->assertEquals(null, \Liebig\Cron\Cron::setEnableJob('test2', false));

        $this->assertEquals(false, \Liebig\Cron\Cron::isJobEnabled('test1'));
        $this->assertEquals(false, \Liebig\Cron\Cron::isJobEnabled('test2'));
        $this->assertEquals(false, \Liebig\Cron\Cron::isJobEnabled('test3'));
        $this->assertEquals(null, \Liebig\Cron\Cron::isJobEnabled('test4'));
        
        \Liebig\Cron\Cron::run();
        $this->assertEquals(2, $iTest1);
        $this->assertEquals(3, $iTest2);
        $this->assertEquals(1, $iTest3);
        $this->assertEquals(4, \Liebig\Cron\models\Manager::count());
        $this->assertEquals(6, \Liebig\Cron\models\Job::count());

        $this->assertEquals(null, \Liebig\Cron\Cron::setEnableJob('test1', true));

        $this->assertEquals(true, \Liebig\Cron\Cron::isJobEnabled('test1'));
        $this->assertEquals(false, \Liebig\Cron\Cron::isJobEnabled('test2'));
        $this->assertEquals(false, \Liebig\Cron\Cron::isJobEnabled('test3'));
        $this->assertEquals(null, \Liebig\Cron\Cron::isJobEnabled('test4'));
        
        \Liebig\Cron\Cron::run();
        $this->assertEquals(3, $iTest1);
        $this->assertEquals(3, $iTest2);
        $this->assertEquals(1, $iTest3);
        $this->assertEquals(5, \Liebig\Cron\models\Manager::count());
        $this->assertEquals(7, \Liebig\Cron\models\Job::count());
    }

}

