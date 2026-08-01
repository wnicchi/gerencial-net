<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calificacion', function (Blueprint $table) {
            $table->dateTime('CAL_FEC')->nullable();
            $table->char('CAL_PUE', 20)->nullable();
            $table->char('CAL_CUIL', 13)->nullable();
            $table->char('CAL_RES', 100)->nullable();
            $table->char('CAL_NEG', 100)->nullable();
            $table->char('CAL_POS', 100)->nullable();
            $table->char('CAL_CUIRES', 13)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calificacion');
    }
};
