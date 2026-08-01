<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('SINI_FOTOS', function (Blueprint $table) {
            $table->bigInteger('SIF_NRO')->nullable();
            $table->bigInteger('SIF_UNI')->nullable();
            $table->text('SIF_COM')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('SINI_FOTOS');
    }
};
