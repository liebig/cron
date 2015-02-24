<?php

namespace Liebig\Cron\Models;

class Manager extends \Illuminate\Database\Eloquent\Model{
    
    protected $table = 'cron_manager';
    public $timestamps = false;
    
    public function cronJobs() {
        return $this->hasMany('\Liebig\Cron\Models\Job', 'cron_manager_id');
    }
    
    
}
