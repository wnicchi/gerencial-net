<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('novedades', function (Blueprint $table) {
            $table->tinyInteger('NOV_MES')->nullable();
            $table->smallInteger('NOV_ANO')->nullable();
            $table->tinyInteger('NOV_QUI')->nullable();
            $table->integer('NOV_PER')->nullable();
            $table->char('NOV_NOM', 50)->nullable();
            $table->decimal('NOV_DTR', 4, 1)->nullable();
            $table->decimal('NOV_CVE', 10, 2)->nullable();
            $table->decimal('NOV_CCO', 10, 2)->nullable();
            $table->decimal('NOV_ADE', 10, 2)->nullable();
            $table->decimal('NOV_ALM', 10, 2)->nullable();
            $table->decimal('NOV_OD1', 10, 2)->nullable();
            $table->decimal('NOV_NJ1', 10, 2)->nullable();
            $table->decimal('NOV_AN1', 10, 2)->nullable();
            $table->char('NOV_OB1', 50)->nullable();
            $table->decimal('NOV_HE5', 10, 2)->nullable();
            $table->decimal('NOV_HE1', 10, 2)->nullable();
            $table->decimal('NOV_AHN', 10, 2)->nullable();
            $table->decimal('NOV_CN5', 10, 2)->nullable();
            $table->decimal('NOV_CN1', 10, 2)->nullable();
            $table->decimal('NOV_AVI', 10, 2)->nullable();
            $table->decimal('NOV_DPP', 6, 2)->nullable();
            $table->decimal('NOV_AR5', 10, 2)->nullable();
            $table->decimal('NOV_AR3', 10, 2)->nullable();
            $table->decimal('NOV_NJ2', 10, 2)->nullable();
            $table->decimal('NOV_BAS', 10, 2)->nullable();
            $table->smallInteger('NOV_DVA')->nullable();
            $table->smallInteger('NOV_DEN')->nullable();
            $table->smallInteger('NOV_DLI')->nullable();
            $table->decimal('NOV_ANT', 10, 2)->nullable();
            $table->decimal('NOV_NOR', 10, 2)->nullable();
            $table->decimal('NOV_C100', 10, 2)->nullable();
            $table->decimal('NOV_C050', 10, 2)->nullable();
            $table->decimal('NOV_CNOC', 10, 2)->nullable();
            $table->decimal('NOV_CVIA', 10, 2)->nullable();
            $table->smallInteger('NOV_DMES')->nullable();
            $table->string('NOV_DETALLE', 200)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('novedades');
    }
};
