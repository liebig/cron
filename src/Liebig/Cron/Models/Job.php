<?php

namespace Liebig\Cron\Models;

class Job extends \Illuminate\Database\Eloquent\Model{
    
    protected $table = 'cron_job';
    public $timestamps = false;
    
    public function manager() {
        return $this->belongsTo('\Liebig\Cron\Models\Manager', 'cron_manager_id');
    }
    
    
}
