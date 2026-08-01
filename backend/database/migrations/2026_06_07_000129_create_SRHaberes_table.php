<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('SRHaberes', function (Blueprint $table) {
            $table->bigInteger('SRH_NRO')->nullable();
            $table->smallInteger('SRH_EMP')->nullable();
            $table->char('SRH_EMD', 30)->nullable();
            $table->smallInteger('SRH_CON')->nullable();
            $table->char('SRH_COD', 30)->nullable();
            $table->dateTime('SRH_FEC')->nullable();
            $table->decimal('SRH_MON', 14, 2)->nullable();
            $table->char('SRH_USU', 20)->nullable();
            $table->char('SRH_TER', 10)->nullable();
            $table->boolean('SRH_ORD')->nullable();
            $table->string('SRH_SUE', 50)->nullable();
            $table->boolean('SRH_PAG')->nullable();
            $table->string('SRH_ANU', 1)->nullable();
            $table->tinyInteger('SRH_MESLIQ')->nullable();
            $table->smallInteger('SRH_ANOLIQ')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('SRHaberes');
    }
};
