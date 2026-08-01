<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('niveles', function (Blueprint $table) {
            $table->bigInteger('CODNIV')->nullable();
            $table->char('DESCRIBE', 100)->nullable();
            $table->boolean('MODIFICAR')->nullable();
            $table->boolean('ELIMINAR')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('niveles');
    }
};
