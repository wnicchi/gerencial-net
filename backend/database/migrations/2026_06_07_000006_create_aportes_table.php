<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aportes', function (Blueprint $table) {
            $table->integer('APO_COD')->nullable();
            $table->char('APO_DES', 20)->nullable();
            $table->decimal('APO_ALI', 5, 2)->nullable();
            $table->tinyInteger('APO_ADE')->nullable();
            $table->tinyInteger('APO_AHA')->nullable();
            $table->integer('APO_CON')->nullable();
            $table->char('APO_CDE', 20)->nullable();
            $table->char('APO_SIN', 1)->nullable();
            $table->char('APO_OSC', 1)->nullable();
            $table->integer('APO_DTO')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aportes');
    }
};
