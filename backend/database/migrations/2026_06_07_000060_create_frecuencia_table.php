<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('frecuencia', function (Blueprint $table) {
            $table->integer('FRE_COD')->nullable();
            $table->char('FRE_DES', 50)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('frecuencia');
    }
};
