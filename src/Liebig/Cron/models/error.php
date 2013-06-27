<?php

namespace Liebig\Cron\models;

class Error extends \Eloquent{
    
    protected $table = 'cron_error';
    protected $fillable = array('name', 'return', 'runtime');
    
    public function manager() {
        return $this->belongsTo('\Liebig\Cron\models\Manager', 'cron_manager_id');
    }
    
    
}