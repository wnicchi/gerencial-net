<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('viajes', function (Blueprint $table) {
            $table->bigInteger('PVI_PER')->nullable();
            $table->char('PVI_NOM', 50)->nullable();
            $table->dateTime('PVI_FEC')->nullable();
            $table->dateTime('PVI_FSA')->nullable();
            $table->dateTime('PVI_FEN')->nullable();
            $table->tinyInteger('PVI_DIA')->nullable();
            $table->char('PVI_DES', 150)->nullable();
            $table->integer('PVI_KMS')->nullable();
            $table->char('PVI_OBS', 150)->nullable();
            $table->char('PVI_VEH', 7)->nullable();
            $table->increments('UNICO');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('viajes');
    }
};
