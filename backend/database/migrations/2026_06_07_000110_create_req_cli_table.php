<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('req_cli', function (Blueprint $table) {
            $table->bigInteger('RCL_CLI')->nullable();
            $table->char('RCL_CLD', 100)->nullable();
            $table->integer('RCL_REQ')->nullable();
            $table->char('RCL_RED', 100)->nullable();
            $table->smallInteger('RCL_DIA')->nullable();
            $table->dateTime('RCL_FUL')->nullable();
            $table->text('RCL_OBS')->nullable();
            $table->char('RCL_CO1', 100)->nullable();
            $table->char('RCL_TE1', 30)->nullable();
            $table->char('RCL_CO2', 100)->nullable();
            $table->char('RCL_TE2', 30)->nullable();
            $table->char('RCL_EM1', 100)->nullable();
            $table->char('RCL_EM2', 100)->nullable();
            $table->char('RCL_EM3', 100)->nullable();
            $table->char('RCL_EM4', 100)->nullable();
            $table->char('RCL_EM5', 100)->nullable();
            $table->char('RCL_EM6', 100)->nullable();
            $table->char('RCL_EM7', 100)->nullable();
            $table->char('RCL_EM8', 100)->nullable();
            $table->char('RCL_EM9', 100)->nullable();
            $table->char('RCL_EM10', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('req_cli');
    }
};
