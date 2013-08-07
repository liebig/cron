<?php

use Illuminate\Database\Migrations\Migration;

class CreateCronmanagerTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() {
        Schema::create('cron_manager', function($table) {
                    $table->increments('id');
                    $table->dateTime('rundate');
                    $table->float('runtime');
                });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::drop('cron_manager');
    }

}