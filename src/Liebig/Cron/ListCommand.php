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
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * Execute the console command.
	 *
	 * @return void
	 */
	public function fire()
	{
                // Get the current timestamp and fire the collect event
		$runDate = new \DateTime();
                \Event::fire('cron.collectJobs', array($runDate->getTimestamp()));
                // Get all registered Cron jobs
                $jobs = Cron::getCronJobs();
                
                // Echo the headline
                echo('|Jobname|Expression|Activated|' . "\n");
                
                // Run through all registered jobs
                for ($i = 0; $i < count($jobs); $i++) {
                    
                    // Get current job entry
                    $job = $jobs[$i];
                    
                    // If job is enabled or disable use the defined string instead of 1 or 0
                    $enabled = '';
                    if($job['enabled']) {
                        $enabled = 'enabled ';
                    } else {
                        $enabled = 'disabled';
                    }
                    
                    // Add new line to all jobs but not the last one
                    $newline = "\n";
                    if($i + 1 === count($jobs)) {
                        $newline = '';
                    }
                    
                    // Echo the current job entry
                    echo('|'.$job['name'].'|'.$job['expression']->getExpression().'|'.$enabled.'|'.$newline);
                }
                
	}

	/**
	 * Get the console command arguments.
	 *
	 * @return array
	 */
	protected function getArguments()
	{
		return array();
	}

	/**
	 * Get the console command options.
	 *
	 * @return array
	 */
	protected function getOptions()
	{
		return array();
	}

}