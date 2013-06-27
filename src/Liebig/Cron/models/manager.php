<?php

namespace Liebig\Cron\models;

class Manager extends \Eloquent{
    
    protected $table = 'cron_manager';
    protected $fillable = array('rundate', 'runtime', 'errors');
    
    public function cronErrors() {
        return $this->hasMany('\Liebig\Cron\models\Error', 'cron_manager_id');
    }
    
    
}