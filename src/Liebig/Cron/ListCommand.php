<?php

namespace Liebig\Cron;

use Illuminate\Console\Command;

class ListCommand extends Command {

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'cron:list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List Cron jobs';

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
        // Get the current timestamp and fire the collect event
        $runDate = new \DateTime();
        \Event::fire('cron.collectJobs', array($runDate->getTimestamp()));
        // Get all registered Cron jobs
        $jobs = Cron::getCronJobs();

        // Get Laravel version
        $laravel = app();
        $version = $laravel::VERSION;

        if ($version < '5.2') {
            // Create the table helper with headers.
            $table = $this->getHelperSet()->get('table');
            $table->setHeaders(array('Jobname', 'Expression', 'Activated'));

            // Run through all registered jobs
            for ($i = 0; $i < count($jobs); $i++) {

                // Get current job entry
                $job = $jobs[$i];

                // If job is enabled or disable use the defined string instead of 1 or 0
                $enabled = $job['enabled'] ? 'Enabled' : 'Disabled';

                // Add this job to the table.
                $table->addRow(array($job['name'], $job['expression']->getExpression(), $enabled));
            }
        } else {
            // Create table for new Laravel versions.
          $table = new \Symfony\Component\Console\Helper\Table($this->getOutput());
          $table->setHeaders(array('Jobname', 'Expression', 'Activated'));

          $rows = [];
          // Run through all registered jobs
          for ($i = 0; $i < count($jobs); $i++) {
             // Get current job entry
                $job = $jobs[$i];

                // If job is enabled or disable use the defined string instead of 1 or 0
                $enabled = $job['enabled'] ? 'Enabled' : 'Disabled';
                
                array_push($rows, array($job['name'], $job['expression']->getExpression(), $enabled));
               
          }

           $table->setRows($rows);
        }


        // Render and output the table.
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
