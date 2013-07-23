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

        // Migrate all database tables
        \Artisan::call('migrate', array('--package' => 'Liebig/Cron'));

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
     * Test method for setting the Logger
     *
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
     * Test method for running cron jobs
     *
     */
    public function testRun() {
        $i = 0;
        \Liebig\Cron\Cron::add('test1', '* * * * *', function() use (&$i) {
                    $i++;
                    return null;
                });
        $runResult1 = \Liebig\Cron\Cron::run();
        $this->assertEquals($i, 1);
        $this->assertEquals($runResult1['errors'], 0);
        $this->assertEquals(count($runResult1['crons']), 1);
        $this->assertEquals($runResult1['crons'][0]['name'], 'test1');
        $this->assertEquals($runResult1['crons'][0]['return'], null);
        $this->assertEquals($runResult1['inTime'], null);

        \Liebig\Cron\Cron::add('test2', '* * * * *', function() {
                    return 'return of test2';
                });
        $runResult2 = \Liebig\Cron\Cron::run();
        $this->assertEquals($i, 2);
        $this->assertEquals($runResult2['errors'], 1);
        $this->assertEquals(count($runResult2['crons']), 2);
        $this->assertEquals($runResult2['crons'][0]['name'], 'test1');
        $this->assertEquals($runResult2['crons'][0]['return'], null);
        $this->assertEquals($runResult2['crons'][1]['name'], 'test2');
        $this->assertEquals($runResult2['crons'][1]['return'], 'return of test2');

        sleep(60);
        $runResult3 = \Liebig\Cron\Cron::run();
        $this->assertEquals($i, 3);
        $this->assertEquals($runResult3['inTime'], true);

        sleep(25);
        $runResult4 = \Liebig\Cron\Cron::run();
        $this->assertEquals($i, 4);
        $this->assertEquals($runResult4['inTime'], false);

        sleep(90);
        $runResult5 = \Liebig\Cron\Cron::run();
        $this->assertEquals($i, 5);
        $this->assertEquals($runResult5['inTime'], false);
    }

    /**
     * Test method for enabling and disabling cron jobs
     *
     */
    public function testRunEnabled() {
        $i = 0;
        \Liebig\Cron\Cron::add('test1', '* * * * *', function() use (&$i) {
                    $i++;
                    return null;
                }, false);

        \Liebig\Cron\Cron::run();
        $this->assertEquals($i, 0);
        $this->assertEquals(\Liebig\Cron\models\Manager::count(), 1);
        $this->assertEquals(\Liebig\Cron\models\Error::count(), 0);

        \Liebig\Cron\Cron::run();
        $this->assertEquals($i, 0);
        $this->assertEquals(\Liebig\Cron\models\Manager::count(), 2);
        $this->assertEquals(\Liebig\Cron\models\Error::count(), 0);

        \Liebig\Cron\Cron::add('test2', '* * * * *', function() use (&$i) {
                    $i++;
                    return false;
                }, true);

        \Liebig\Cron\Cron::run();
        $this->assertEquals($i, 1);
        $this->assertEquals(\Liebig\Cron\models\Manager::count(), 3);
        $this->assertEquals(\Liebig\Cron\models\Error::count(), 1);

        \Liebig\Cron\Cron::run();
        $this->assertEquals($i, 2);
        $this->assertEquals(\Liebig\Cron\models\Manager::count(), 4);
        $this->assertEquals(\Liebig\Cron\models\Error::count(), 2);
    }

    /**
     * Test method for adding cron jobs
     *
     */
    public function testAddCronJob() {

        $i = 0;
        $this->assertEquals(\Liebig\Cron\Cron::add('test1', '* * * * *', function() use (&$i) {
                            $i++;
                            return false;
                        }), null);

        \Liebig\Cron\Cron::run();
        $this->assertEquals($i, 1);
        $this->assertEquals(\Liebig\Cron\models\Manager::count(), 1);
        $this->assertEquals(\Liebig\Cron\models\Error::count(), 1);

        $this->assertEquals(\Liebig\Cron\Cron::add('test1', '* * * * *', function() use (&$i) {
                            $i++;
                            return false;
                        }), false);

        $this->assertEquals(\Liebig\Cron\Cron::add('test2', 'NOT', function() use (&$i) {
                            $i++;
                            return false;
                        }), false);

        $this->assertEquals(\Liebig\Cron\Cron::add('test3', '* * * * * * *', function() use (&$i) {
                            $i++;
                            return false;
                        }), false);

        $this->assertEquals(\Liebig\Cron\Cron::add('test4', '* * * * * * *', 'This is not a function'), false);

        \Liebig\Cron\Cron::run();
        $this->assertEquals($i, 2);
        $this->assertEquals(\Liebig\Cron\models\Manager::count(), 2);
        $this->assertEquals(\Liebig\Cron\models\Error::count(), 2);
    }

    /**
     * Test method for testing the method for removing a single cron job by name
     *
     */
    public function testRemoveCronJob() {

        $i = 0;
        \Liebig\Cron\Cron::add('test1', '* * * * *', function() use (&$i) {
                    $i++;
                    return false;
                });

        \Liebig\Cron\Cron::run();
        $this->assertEquals($i, 1);
        $this->assertEquals(\Liebig\Cron\models\Manager::count(), 1);
        $this->assertEquals(\Liebig\Cron\models\Error::count(), 1);

        $this->assertEquals(\Liebig\Cron\Cron::remove('test1'), null);

        \Liebig\Cron\Cron::run();
        $this->assertEquals($i, 1);
        $this->assertEquals(\Liebig\Cron\models\Manager::count(), 2);
        $this->assertEquals(\Liebig\Cron\models\Error::count(), 1);

        \Liebig\Cron\Cron::add('test2', '* * * * *', function() use (&$i) {
                    $i++;
                    return false;
                });

        \Liebig\Cron\Cron::run();
        $this->assertEquals($i, 2);
        $this->assertEquals(\Liebig\Cron\models\Manager::count(), 3);
        $this->assertEquals(\Liebig\Cron\models\Error::count(), 2);

        \Liebig\Cron\Cron::run();
        $this->assertEquals($i, 3);
        $this->assertEquals(\Liebig\Cron\models\Manager::count(), 4);
        $this->assertEquals(\Liebig\Cron\models\Error::count(), 3);

        $this->assertEquals(\Liebig\Cron\Cron::remove('test2'), null);

        \Liebig\Cron\Cron::run();
        $this->assertEquals($i, 3);
        $this->assertEquals(\Liebig\Cron\models\Manager::count(), 5);
        $this->assertEquals(\Liebig\Cron\models\Error::count(), 3);

        $this->assertEquals(\Liebig\Cron\Cron::remove('unknown'), false);
    }

    /**
     * Test method for heavily run 1000 cron jobs five times
     *
     */
    public function testHeavyRun() {

        $count = 0;
        for ($i = 1; $i <= 1000; $i++) {

            \Liebig\Cron\Cron::add('test' . $i, '* * * * *', function() use (&$count) {
                        $count++;
                        return null;
                    });
        }
        \Liebig\Cron\Cron::run();
        $this->assertEquals($count, 1000);

        \Liebig\Cron\Cron::run();
        $this->assertEquals($count, 2000);

        \Liebig\Cron\Cron::run();
        $this->assertEquals($count, 3000);

        \Liebig\Cron\Cron::run();
        $this->assertEquals($count, 4000);

        \Liebig\Cron\Cron::run();
        $this->assertEquals($count, 5000);
    }

    /**
     * Test method for testing the save and load functions of the database models
     *
     */
    public function testDatabaseModelsSaveLoad() {

        $newManager = new \Liebig\Cron\models\Manager();
        $date = new \DateTime();
        $newManager->rundate = $date;
        $newManager->runtime = 0.007;
        $this->assertNotNull($newManager->save());

        $newError1 = new \Liebig\Cron\models\Error();
        $newError1->name = "test11";
        $newError1->return = "test11 fails";
        $newError1->cron_manager_id = $newManager->id;
        $this->assertNotNull($newError1->save());

        $newError2 = new \Liebig\Cron\models\Error();
        $newError2->name = "test12";
        $newError2->return = "test12 fails";
        $newError2->cron_manager_id = $newManager->id;
        $this->assertNotNull($newError2->save());

        $newError3 = new \Liebig\Cron\models\Error();
        $newError3->name = "test13";
        $newError3->return = "test13 fails";
        $newError3->cron_manager_id = $newManager->id;
        $this->assertNotNull($newError3->save());

        $newManagerFind = \Liebig\Cron\models\Manager::find(1);
        $this->assertNotNull($newManagerFind);

        $this->assertEquals($newManagerFind->rundate, $date->format('Y-m-d H:i:s'));
        $this->assertEquals($newManagerFind->runtime, 0.007);

        $newErrorsFind = $newManagerFind->cronErrors()->get();
        $this->assertEquals(count($newErrorsFind), 3);

        $this->assertEquals($newErrorsFind[0]->name, 'test11');
        $this->assertEquals($newErrorsFind[0]->return, 'test11 fails');
        $this->assertEquals($newErrorsFind[0]->cron_manager_id, $newManager->id);

        $this->assertEquals($newErrorsFind[1]->name, 'test12');
        $this->assertEquals($newErrorsFind[1]->return, 'test12 fails');
        $this->assertEquals($newErrorsFind[1]->cron_manager_id, $newManager->id);

        $this->assertEquals($newErrorsFind[2]->name, 'test13');
        $this->assertEquals($newErrorsFind[2]->return, 'test13 fails');
        $this->assertEquals($newErrorsFind[2]->cron_manager_id, $newManager->id);
    }

    /**
     * Test method for testing the database models created after the run method
     *
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
        $errors = $manager->cronErrors()->get();

        $this->assertEquals(count($errors), 2);

        $this->assertEquals($errors[0]->name, 'test1');
        $this->assertEquals($errors[0]->return, 'test1 fails');
        $this->assertEquals($errors[0]->cron_manager_id, $manager->id);

        $this->assertEquals($errors[1]->name, 'test3');
        $this->assertEquals($errors[1]->return, 'test3 fails');
        $this->assertEquals($errors[1]->cron_manager_id, $manager->id);
    }

    /**
     * Test method for testing the reset method
     *
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
        $this->assertEquals($i, 2);
        $this->assertEquals(\Liebig\Cron\models\Manager::count(), 1);
        $this->assertEquals(\Liebig\Cron\models\Error::count(), 2);

        \Liebig\Cron\Cron::setLogger($this->returnLogger());

        \Liebig\Cron\Cron::reset();

        \Liebig\Cron\Cron::run();
        $this->assertEquals($i, 2);
        $this->assertEquals(\Liebig\Cron\models\Manager::count(), 2);
        $this->assertEquals(\Liebig\Cron\models\Error::count(), 2);
        $this->assertEquals(\Liebig\Cron\Cron::getLogger(), null);
    }

}

