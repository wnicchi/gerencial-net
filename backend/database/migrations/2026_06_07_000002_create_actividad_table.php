<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('actividad', function (Blueprint $table) {
            $table->integer('USUARIOS')->nullable();
            $table->dateTime('DIAHORA')->nullable();
            $table->char('DETALLE', 100)->nullable();
            $table->char('NOMUSU', 20)->nullable();
            $table->char('TERMINAL', 30)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('actividad');
    }
};
