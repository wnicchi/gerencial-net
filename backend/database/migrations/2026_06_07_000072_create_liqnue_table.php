<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('liqnue', function (Blueprint $table) {
            $table->integer('LIQ_NRO')->nullable();
            $table->integer('LIQ_COD')->nullable();
            $table->char('LIQ_DEP', 30)->nullable();
            $table->bigInteger('LIQ_CUI')->nullable();
            $table->dateTime('LIQ_ING')->nullable();
            $table->tinyInteger('LIQ_EMP')->nullable();
            $table->integer('LIQ_CON')->nullable();
            $table->integer('LIQ_CAT')->nullable();
            $table->tinyInteger('LIQ_QUI')->nullable();
            $table->tinyInteger('LIQ_MES')->nullable();
            $table->tinyInteger('LIQ_SEM')->nullable();
            $table->smallInteger('LIQ_ANO')->nullable();
            $table->tinyInteger('LIQ_TIP')->nullable();
            $table->dateTime('LIQ_FEC')->nullable();
            $table->decimal('LIQ_HRE', 8, 2)->nullable();
            $table->decimal('LIQ_HRN', 8, 2)->nullable();
            $table->decimal('LIQ_DES', 8, 2)->nullable();
            $table->decimal('LIQ_SAL', 8, 2)->nullable();
            $table->decimal('LIQ_ANT', 8, 2)->nullable();
            $table->decimal('LIQ_RED', 4, 2)->nullable();
            $table->decimal('LIQ_THN', 8, 2)->nullable();
            $table->char('LIQ_DBA', 20)->nullable();
            $table->dateTime('LIQ_AFE')->nullable();
            $table->tinyInteger('LIQ_CME')->nullable();
            $table->smallInteger('LIQ_VAC')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('liqnue');
    }
};
