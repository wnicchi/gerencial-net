<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('puestoempleado', function (Blueprint $table) {
            $table->char('PEM_CUIL', 13)->nullable();
            $table->char('PEM_PUE', 15)->nullable();
            $table->dateTime('PEM_FDES')->nullable();
            $table->dateTime('PEM_FHAS')->nullable();
            $table->boolean('PEM_ACT')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('puestoempleado');
    }
};
