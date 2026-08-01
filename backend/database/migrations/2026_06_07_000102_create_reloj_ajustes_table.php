<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reloj_ajustes', function (Blueprint $table) {
            $table->bigInteger('AJR_PER')->nullable();
            $table->char('AJR_NOM', 50)->nullable();
            $table->dateTime('AJR_FEC')->nullable();
            $table->integer('AJR_HEN1')->nullable();
            $table->integer('AJR_HSA1')->nullable();
            $table->integer('AJR_HEN2')->nullable();
            $table->integer('AJR_HSA2')->nullable();
            $table->boolean('AJR_VE1')->nullable();
            $table->boolean('AJR_VS1')->nullable();
            $table->boolean('AJR_VE2')->nullable();
            $table->boolean('AJR_VS2')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reloj_ajustes');
    }
};
