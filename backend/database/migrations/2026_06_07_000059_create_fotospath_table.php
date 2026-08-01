<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fotospath', function (Blueprint $table) {
            $table->char('TERMINAL', 100)->nullable();
            $table->char('CARPETA', 200)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fotospath');
    }
};
