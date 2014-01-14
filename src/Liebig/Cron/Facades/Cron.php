<?php

 namespace Liebig\Cron\Facades;
 
 use Illuminate\Support\Facades\Facade;
 
 class Cron extends Facade {
 
  /**
   * Get the registered name of the component.
   *
   * @return string
   */
  protected static function getFacadeAccessor() { return 'cron'; }
 
}