<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('informes', function (Blueprint $table) {
            $table->bigInteger('CODINFOR')->nullable();
            $table->char('NOMBRE', 50)->nullable();
            $table->char('REAL', 100)->nullable();
            $table->bigInteger('CODIMPRE')->nullable();
            $table->char('TERMINAL', 30)->nullable();
            $table->dateTime('CAMBIO')->nullable();
            $table->char('USUARIO', 40)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('informes');
    }
};
