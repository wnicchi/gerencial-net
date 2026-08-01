<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('SFHaberes', function (Blueprint $table) {
            $table->bigInteger('SFH_NRO')->nullable();
            $table->smallInteger('SFH_EMP')->nullable();
            $table->char('SFH_EMD', 30)->nullable();
            $table->smallInteger('SFH_CON')->nullable();
            $table->char('SFH_COD', 30)->nullable();
            $table->dateTime('SFH_FEC')->nullable();
            $table->decimal('SFH_MON', 14, 2)->nullable();
            $table->char('SFH_USU', 20)->nullable();
            $table->char('SFH_TER', 10)->nullable();
            $table->string('SFH_SUE', 50)->nullable();
            $table->boolean('SFH_PAG')->nullable();
            $table->string('SFH_ANU', 1)->nullable();
            $table->tinyInteger('SFH_MESLIQ')->nullable();
            $table->smallInteger('SFH_ANOLIQ')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('SFHaberes');
    }
};
