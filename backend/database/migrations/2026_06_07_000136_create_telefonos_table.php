<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telefonos', function (Blueprint $table) {
            $table->string('DETALLE', 50)->nullable();
            $table->string('TEL_INTERNO', 20)->nullable();
            $table->string('TEL_CELULAR', 30)->nullable();
            $table->text('OTROS')->nullable(); // VARCHAR(1000)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telefonos');
    }
};
