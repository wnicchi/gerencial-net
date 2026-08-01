<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('VARHaberes', function (Blueprint $table) {
            $table->bigInteger('VR_NRO')->nullable();
            $table->smallInteger('VR_EMP')->nullable();
            $table->char('VR_EMD', 30)->nullable();
            $table->smallInteger('VR_CON')->nullable();
            $table->char('VR_COD', 30)->nullable();
            $table->dateTime('VR_FEC')->nullable();
            $table->decimal('VR_MON', 14, 2)->nullable();
            $table->char('VR_USU', 20)->nullable();
            $table->char('VR_TER', 10)->nullable();
            $table->boolean('VR_ORD')->nullable();
            $table->string('VR_SUE', 50)->nullable();
            $table->boolean('VR_PAG')->nullable();
            $table->string('VR_ANU', 1)->nullable();
            $table->tinyInteger('VR_MESLIQ')->nullable();
            $table->smallInteger('VR_ANOLIQ')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('VARHaberes');
    }
};
