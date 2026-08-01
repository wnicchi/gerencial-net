<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parimpre', function (Blueprint $table) {
            $table->bigInteger('CODIGO')->nullable();
            $table->char('NOMBRE', 100)->nullable();
            $table->char('PUERTO', 100)->nullable();
            $table->char('TERMINAL', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parimpre');
    }
};
