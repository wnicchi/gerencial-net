<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('valeefectivo', function (Blueprint $table) {
            $table->bigInteger('VLE_NRO')->nullable();
            $table->dateTime('VLE_FEC')->nullable();
            $table->bigInteger('VLE_EMC')->nullable();
            $table->char('VLE_EMD', 50)->nullable();
            $table->decimal('VLE_IMP', 12, 2)->nullable();
            $table->char('VLE_RAZ', 100)->nullable();
            $table->char('VLE_AUT', 30)->nullable();
            $table->char('VLE_TER', 20)->nullable();
            $table->char('VLE_USU', 20)->nullable();
            $table->dateTime('VLE_FCI')->nullable();
            $table->decimal('VLE_REP', 12, 2)->nullable();
            $table->decimal('VLE_GAS', 12, 2)->nullable();
            $table->decimal('VLE_CBU', 12, 2)->nullable();
            $table->decimal('VLE_AJU', 12, 2)->nullable();
            $table->decimal('VLE_VUE', 12, 2)->nullable();
            $table->decimal('VLE_COM', 12, 2)->nullable();
            $table->tinyInteger('VLE_FON')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('valeefectivo');
    }
};
