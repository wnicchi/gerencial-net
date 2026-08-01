<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdfimpre', function (Blueprint $table) {
            $table->char('NOMBRE', 150)->nullable();
            $table->char('PUERTO', 150)->nullable();
            $table->char('TERMINAL', 100)->nullable();
            $table->char('PATHDATO', 150)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdfimpre');
    }
};
