<?php

namespace Liebig\Cron\models;

class Job extends \Eloquent{
    
    protected $table = 'cron_job';
    public $timestamps = false;
    protected $fillable = array('name', 'return', 'runtime');
    
    public function manager() {
        return $this->belongsTo('\Liebig\Cron\Models\Manager', 'cron_manager_id');
    }
    
    
}