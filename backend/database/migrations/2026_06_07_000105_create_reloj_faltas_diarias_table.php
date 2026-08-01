<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reloj_faltas_diarias', function (Blueprint $table) {
            $table->bigInteger('AFD_PER')->nullable();
            $table->char('AFD_NOM', 50)->nullable();
            $table->smallInteger('AFD_LIC')->nullable();
            $table->char('AFD_LID', 100)->nullable();
            $table->dateTime('AFD_FE1')->nullable();
            $table->dateTime('AFD_FE2')->nullable();
            $table->char('AFD_OBS', 150)->nullable();
            $table->increments('UNICO');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reloj_faltas_diarias');
    }
};
