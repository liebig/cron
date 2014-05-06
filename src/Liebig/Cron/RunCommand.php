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
            $report = Cron::run();
            
            $inTime = '';
            if($report['inTime']) {
                $inTime = 'true';
            } else {
                $inTime = 'false';
            }
            
            echo('|Run date|In time|Run time|Errors|Jobs|' . "\n");
            echo('|'.$report['rundate'].'|'.$inTime.'|'.round($report['runtime'], 4).'|'.$report['errors'].'|'.count($report['crons']).'|');
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