<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('liquidac', function (Blueprint $table) {
            $table->integer('LIQ_NRO')->nullable();
            $table->integer('LIQ_COD')->nullable();
            $table->char('LIQ_DEP', 30)->nullable();
            $table->char('LIQ_CUI', 13)->nullable();
            $table->dateTime('LIQ_ING')->nullable();
            $table->integer('LIQ_EMP')->nullable();
            $table->integer('LIQ_CON')->nullable();
            $table->integer('LIQ_CAT')->nullable();
            $table->tinyInteger('LIQ_QUI')->nullable();
            $table->tinyInteger('LIQ_MES')->nullable();
            $table->tinyInteger('LIQ_SEM')->nullable();
            $table->smallInteger('LIQ_ANO')->nullable();
            $table->smallInteger('LIQ_TIP')->nullable();
            $table->char('LIQ_TID', 50)->nullable();
            $table->dateTime('LIQ_FEC')->nullable();
            $table->decimal('LIQ_HRE', 8, 2)->nullable();
            $table->decimal('LIQ_HNR', 8, 2)->nullable();
            $table->decimal('LIQ_DES', 8, 2)->nullable();
            $table->decimal('LIQ_SAL', 8, 2)->nullable();
            $table->decimal('LIQ_ANT', 8, 2)->nullable();
            $table->decimal('LIQ_RED', 4, 2)->nullable();
            $table->decimal('LIQ_THN', 8, 2)->nullable();
            $table->char('LIQ_DBA', 20)->nullable();
            $table->dateTime('LIQ_AFE')->nullable();
            $table->tinyInteger('LIQ_CME')->nullable();
            $table->smallInteger('LIQ_VAC')->nullable();
            $table->char('LIQ_OB1', 12)->nullable();
            $table->char('LIQ_OB2', 12)->nullable();
            $table->char('LIQ_OB3', 12)->nullable();
            $table->dateTime('LIQ_PAG')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('liquidac');
    }
};
