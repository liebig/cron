<?php

use Illuminate\Database\Migrations\Migration;

class CreateCronerrorTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() {
        Schema::create('cron_error', function($table) {
                    $table->increments('id');
                    $table->string('name');
                    $table->text('return');
                    $table->float('runtime');
                    $table->integer('cron_manager_id');
                    $table->timestamps();
                });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::drop('cron_error');
    }

}