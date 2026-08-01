<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('almuerzo', function (Blueprint $table) {
            $table->integer('ALM_ORD')->nullable();
            $table->integer('ALM_PER')->nullable();
            $table->char('ALM_NOM', 50)->nullable();
            $table->dateTime('ALM_FEC')->nullable();
            $table->char('ALM_DIA', 10)->nullable();
            $table->integer('ALM_CAN')->nullable();
            $table->char('ALM_OBS', 50)->nullable();
            $table->char('ALM_ALM', 1)->nullable();
            $table->char('ALM_DES', 1)->nullable();
            $table->char('ALM_TIP', 1)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('almuerzo');
    }
};
