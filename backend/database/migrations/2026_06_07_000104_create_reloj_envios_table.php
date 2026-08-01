<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reloj_envios', function (Blueprint $table) {
            $table->tinyInteger('rdp_cod')->nullable();
            $table->char('rdp_des', 50)->nullable();
            $table->boolean('rdp_d1')->nullable();
            $table->boolean('rdp_d2')->nullable();
            $table->boolean('rdp_d3')->nullable();
            $table->boolean('rdp_d4')->nullable();
            $table->boolean('rdp_d5')->nullable();
            $table->boolean('rdp_d6')->nullable();
            $table->boolean('rdp_d7')->nullable();
            $table->smallInteger('rdp_h1')->nullable();
            $table->smallInteger('rdp_h2')->nullable();
            $table->smallInteger('rdp_h3')->nullable();
            $table->smallInteger('rdp_h4')->nullable();
            $table->smallInteger('rdp_h5')->nullable();
            $table->smallInteger('rdp_h6')->nullable();
            $table->smallInteger('rdp_h7')->nullable();
            $table->char('rdp_em1', 50)->nullable();
            $table->char('rdp_em2', 50)->nullable();
            $table->char('rdp_em3', 50)->nullable();
            $table->char('rdp_em4', 50)->nullable();
            $table->char('rdp_em5', 50)->nullable();
            $table->boolean('rdp_act')->nullable();
            $table->smallInteger('rdp_h1b')->nullable();
            $table->smallInteger('rdp_h2b')->nullable();
            $table->smallInteger('rdp_h3b')->nullable();
            $table->smallInteger('rdp_h4b')->nullable();
            $table->smallInteger('rdp_h5b')->nullable();
            $table->smallInteger('rdp_h6b')->nullable();
            $table->smallInteger('rdp_h7b')->nullable();
            $table->smallInteger('rdp_h1c')->nullable();
            $table->smallInteger('rdp_h2c')->nullable();
            $table->smallInteger('rdp_h3c')->nullable();
            $table->smallInteger('rdp_h4c')->nullable();
            $table->smallInteger('rdp_h5c')->nullable();
            $table->smallInteger('rdp_h6c')->nullable();
            $table->smallInteger('rdp_h7c')->nullable();
            $table->dateTime('rdp_dt1')->nullable();
            $table->dateTime('rdp_dt2')->nullable();
            $table->dateTime('rdp_dt3')->nullable();
            $table->dateTime('rdp_dt4')->nullable();
            $table->dateTime('rdp_dt5')->nullable();
            $table->dateTime('rdp_dt6')->nullable();
            $table->dateTime('rdp_dt7')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reloj_envios');
    }
};
