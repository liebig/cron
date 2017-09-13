<?php

namespace Liebig\Cron;

use Illuminate\Console\Command;

class RunCommand extends Command {

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'cron:run';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run Cron jobs';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct() {
        parent::__construct();
    }

    /**
     * Execute the console command for Laravel > 5.5.
     *
     * @return void
     */
    public function handle() {
        $this->fire();
    }
    
    /**
     * Execute the console command.
     *
     * @return void
     */
    public function fire() {
        // Fire event before the Cron jobs will be executed
        \Event::fire('cron.collectJobs');
        $report = Cron::run();

        if($report['inTime'] === -1) {
            $inTime = -1;
        } else if ($report['inTime']) {
            $inTime = 'true';
        } else {
            $inTime = 'false';
        }

        // Get Laravel version
        $laravel = app();
        $version = $laravel::VERSION;

        if ($version < '5.2') {
          // Create table for old Laravel versions.
          $table = $this->getHelperSet()->get('table');
          $table->setHeaders(array('Run date', 'In time', 'Run time', 'Errors', 'Jobs'));
          $table->addRow(array($report['rundate'], $inTime, round($report['runtime'], 4), $report['errors'], count($report['crons'])));
        } else {
          // Create table for new Laravel versions.
          $table = new \Symfony\Component\Console\Helper\Table($this->getOutput());
          $table
            ->setHeaders(array('Run date', 'In time', 'Run time', 'Errors', 'Jobs'))
            ->setRows(array(array($report['rundate'], $inTime, round($report['runtime'], 4), $report['errors'], count($report['crons']))));
        }

        // Output table.
        $table->render($this->getOutput());
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments() {
        return array();
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions() {
        return array();
    }

}
