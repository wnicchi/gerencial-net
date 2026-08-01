<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planilla_sueldo', function (Blueprint $table) {
            $table->integer('PSUE_NRO')->nullable();
            $table->string('PSUE_DET', 100)->nullable();
            $table->dateTime('PSUE_FEC')->nullable();
            $table->string('PSUE_USU', 30)->nullable();
            $table->string('PSUE_TER', 20)->nullable();
            $table->bigInteger('PSUE_LEG')->nullable();
            $table->string('PSUE_NOM', 50)->nullable();
            $table->string('PSUE_BAN', 30)->nullable();
            $table->decimal('PSUE_NET', 14, 2)->nullable();
            $table->decimal('PSUE_SUE', 14, 2)->nullable();
            $table->decimal('PSUE_EXT', 14, 2)->nullable();
            $table->decimal('PSUE_STO', 14, 2)->nullable();
            $table->decimal('PSUE_ANT', 14, 2)->nullable();
            $table->decimal('PSUE_PRE', 14, 2)->nullable();
            $table->decimal('PSUE_VAC', 14, 2)->nullable();
            $table->decimal('PSUE_GAN', 14, 2)->nullable();
            $table->decimal('PSUE_ALM', 14, 2)->nullable();
            $table->decimal('PSUE_SAC', 14, 2)->nullable();
            $table->decimal('PSUE_OTR', 14, 2)->nullable();
            $table->decimal('PSUE_DEP', 14, 2)->nullable();
            $table->decimal('PSUE_DIF', 14, 2)->nullable();
            $table->bigInteger('PSUE_COD')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planilla_sueldo');
    }
};
