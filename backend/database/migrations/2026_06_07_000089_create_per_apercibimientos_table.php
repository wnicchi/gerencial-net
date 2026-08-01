<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('per_apercibimientos', function (Blueprint $table) {
            $table->integer('ape_cod')->nullable();
            $table->char('ape_nom', 50)->nullable();
            $table->dateTime('ape_fec')->nullable();
            $table->text('ape_obs')->nullable();
            $table->char('ape_usu', 30)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('per_apercibimientos');
    }
};
