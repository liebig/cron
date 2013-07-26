<?php

namespace Liebig\Cron\models;

class Job extends \Eloquent{
    
    protected $table = 'cron_job';
    protected $fillable = array('name', 'return', 'runtime');
    
    public function manager() {
        return $this->belongsTo('\Liebig\Cron\models\Manager', 'cron_manager_id');
    }
    
    
}