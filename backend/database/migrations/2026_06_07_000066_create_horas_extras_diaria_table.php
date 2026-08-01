<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('horas_extras_diaria', function (Blueprint $table) {
            $table->dateTime('HRE_FECHA')->nullable();
            $table->char('HRE_EDITADA', 30)->nullable();
            $table->bigInteger('HRE_PER_COD')->nullable();
            $table->char('HRE_NOMBRE', 50)->nullable();
            $table->dateTime('HRE_ENTRA')->nullable();
            $table->dateTime('HRE_SALE')->nullable();
            $table->decimal('HRE_CALCULADA', 10, 2)->nullable();
            $table->decimal('HRE_EST50', 10, 2)->nullable();
            $table->decimal('HRE_EST100', 10, 2)->nullable();
            $table->decimal('HRE_ESTNOC50', 10, 2)->nullable();
            $table->char('HRE_MODUSU', 30)->nullable();
            $table->dateTime('HRE_MODFEC')->nullable();
            $table->increments('UNICO');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('horas_extras_diaria');
    }
};
