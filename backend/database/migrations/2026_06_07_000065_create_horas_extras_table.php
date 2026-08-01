<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('horas_extras', function (Blueprint $table) {
            $table->bigInteger('HRE_NUMERO')->nullable();
            $table->char('HRE_PLANILLA', 50)->nullable();
            $table->dateTime('HRE_FECHA')->nullable();
            $table->char('HRE_CREADA', 30)->nullable();
            $table->char('HRE_SECTOR', 10)->nullable();
            $table->char('HRE_NOMBRE', 50)->nullable();
            $table->decimal('HRE_HS_50', 10, 2)->nullable();
            $table->decimal('HRE_HS_100', 10, 2)->nullable();
            $table->decimal('HRE_HS_NOC', 10, 2)->nullable();
            $table->decimal('HRE_HS_NOC50', 10, 2)->nullable();
            $table->decimal('HRE_HS_DIA30', 10, 2)->nullable();
            $table->decimal('HRE_VALOR_NOR', 10, 2)->nullable();
            $table->decimal('HRE_VALOR_EXTRA50', 10, 2)->nullable();
            $table->decimal('HRE_VALOR_EXTRA100', 10, 2)->nullable();
            $table->decimal('HRE_VALOR_DIA30', 10, 2)->nullable();
            $table->decimal('HRE_VALOR_NOC', 10, 2)->nullable();
            $table->decimal('HRE_VALOR_NOC50', 10, 2)->nullable();
            $table->decimal('HRE_BRUTO_EXTRA50', 10, 2)->nullable();
            $table->decimal('HRE_BRUTO_EXTRA100', 10, 2)->nullable();
            $table->decimal('HRE_BRUTO_NOC', 10, 2)->nullable();
            $table->decimal('HRE_BRUTO_NOC50', 10, 2)->nullable();
            $table->decimal('HRE_BRUTO_DIA30', 10, 2)->nullable();
            $table->decimal('HRE_TOTAL', 10, 2)->nullable();
            $table->bigInteger('HRE_PER_COD')->nullable();
            $table->char('HRE_MODUSU', 30)->nullable();
            $table->dateTime('HRE_MODFEC')->nullable();
            $table->increments('UNICO');
            $table->decimal('HRE_EST50', 10, 2)->nullable();
            $table->decimal('HRE_EST100', 10, 2)->nullable();
            $table->decimal('HRE_ESTNOC50', 10, 2)->nullable();
            $table->dateTime('HRE_FECHA1')->nullable();
            $table->dateTime('HRE_FECHA2')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('horas_extras');
    }
};
