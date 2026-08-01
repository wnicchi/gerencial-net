<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('errores', function (Blueprint $table) {
            $table->dateTime('ERROR_FEC')->nullable();
            $table->char('ERROR_TER', 30)->nullable();
            $table->char('ERROR_USU', 30)->nullable();
            $table->text('ERROR_TEX')->nullable();
            $table->char('ERROR_FOR', 30)->nullable();
            $table->char('ERROR_MET', 30)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('errores');
    }
};
