<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('FRHaberes', function (Blueprint $table) {
            $table->bigInteger('FRH_NRO')->nullable();
            $table->smallInteger('FRH_EMP')->nullable();
            $table->char('FRH_EMD', 30)->nullable();
            $table->smallInteger('FRH_CON')->nullable();
            $table->char('FRH_COD', 30)->nullable();
            $table->dateTime('FRH_FEC')->nullable();
            $table->decimal('FRH_MON', 14, 2)->nullable();
            $table->char('FRH_USU', 20)->nullable();
            $table->char('FRH_TER', 10)->nullable();
            $table->string('FRH_SUE', 50)->nullable();
            $table->boolean('FRH_PAG')->nullable();
            $table->string('FRH_ANU', 1)->nullable();
            $table->tinyInteger('FRH_MESLIQ')->nullable();
            $table->smallInteger('FRH_ANOLIQ')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('FRHaberes');
    }
};
