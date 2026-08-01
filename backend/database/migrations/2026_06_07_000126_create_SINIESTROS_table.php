<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('SINIESTROS', function (Blueprint $table) {
            $table->bigInteger('SIN_NRO')->nullable();
            $table->integer('SIN_VCO')->nullable();
            $table->char('SIN_DOM', 8)->nullable();
            $table->bigInteger('SIN_EMP')->nullable();
            $table->char('SIN_EMN', 50)->nullable();
            $table->bigInteger('SIN_EMD')->nullable();
            $table->char('SIN_EMC', 14)->nullable();
            $table->text('SIN_DET')->nullable();
            $table->text('SIN_DIC')->nullable();
            $table->boolean('SIN_PAG')->nullable();
            $table->boolean('SIN_COB')->nullable();
            $table->dateTime('SIN_FEC')->nullable();
            $table->integer('SIN_HOR')->nullable();
            $table->integer('SIN_AUT')->nullable();
            $table->char('SIN_MAR', 15)->nullable();
            $table->char('SIN_MOD', 30)->nullable();
            $table->char('SIN_MOT', 15)->nullable();
            $table->char('SIN_SER', 30)->nullable();
            $table->smallInteger('SIN_ANO')->nullable();
            $table->char('SIN_VOA', 1)->nullable();
            $table->boolean('SIN_CER')->nullable();
            $table->decimal('SIN_MRE', 14, 2)->nullable();
            $table->decimal('SIN_OFR', 14, 2)->nullable();
            $table->decimal('SIN_MCO', 14, 2)->nullable();
            $table->char('SIN_JUD', 1)->nullable();
            $table->char('SIN_JTI', 1)->nullable();
            $table->boolean('SIN_DPR')->nullable();
            $table->char('SIN_TIP', 1)->nullable();
            $table->dateTime('SIN_ALT')->nullable();
            $table->smallInteger('SIN_DS')->nullable();
            $table->dateTime('SIN_FCA')->nullable();
            $table->dateTime('SIN_FMO')->nullable();
            $table->decimal('SIN_CUL', 6, 2)->nullable();
            $table->dateTime('SIN_FCO')->nullable();
            $table->string('SIN_BAN', 40)->nullable();
            $table->string('SIN_NSIN', 30)->nullable();
            $table->dateTime('SIN_FPC')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('SINIESTROS');
    }
};
