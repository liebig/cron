<?php

namespace Liebig\Cron\models;

class Manager extends \Eloquent{
    
    protected $table = 'cron_manager';
    public $timestamps = false;
    protected $fillable = array('rundate', 'runtime');
    
    public function cronJobs() {
        return $this->hasMany('\Liebig\Cron\models\Job', 'cron_manager_id');
    }
    
    
}