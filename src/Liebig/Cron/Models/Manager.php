<?php

namespace Liebig\Cron\Models;

class Manager extends \Eloquent{
    
    protected $table = 'cron_manager';
    public $timestamps = false;
    protected $fillable = array('rundate', 'runtime');
    
    public function cronJobs() {
        return $this->hasMany('\Liebig\Cron\Models\Job', 'cron_manager_id');
    }
    
    
}