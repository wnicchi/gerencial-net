<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parreloj', function (Blueprint $table) {
            $table->bigInteger('CODIGO')->nullable();
            $table->char('RUTA', 100)->nullable();
            $table->char('TERMINAL', 100)->nullable();
            $table->char('USUARIO', 20)->nullable();
            $table->dateTime('FECHOR')->nullable();
            $table->tinyInteger('EMPRESA')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parreloj');
    }
};
