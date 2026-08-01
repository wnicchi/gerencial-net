<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feriados', function (Blueprint $table) {
            $table->tinyInteger('FER_MES')->nullable();
            $table->smallInteger('FER_ANO')->nullable();
            $table->dateTime('FER_FEC')->nullable();
            $table->char('FER_DIA', 9)->nullable();
            $table->char('FER_OBS', 30)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feriados');
    }
};
