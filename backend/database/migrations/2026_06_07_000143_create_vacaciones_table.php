<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vacaciones', function (Blueprint $table) {
            $table->bigInteger('VAC_NRO')->nullable();
            $table->dateTime('VAC_FEC')->nullable();
            $table->bigInteger('VAC_PER')->nullable();
            $table->char('VAC_NOM', 40)->nullable();
            $table->dateTime('VAC_ING')->nullable();
            $table->smallInteger('VAC_ANT')->nullable();
            $table->smallInteger('VAC_ANO')->nullable();
            $table->dateTime('VAC_FDE')->nullable();
            $table->dateTime('VAC_FHA')->nullable();
            $table->smallInteger('VAC_DIA')->nullable();
            $table->dateTime('VAC_PRE')->nullable();
            $table->boolean('VAC_LIQ')->nullable();
            $table->boolean('VAC_GOZ')->nullable();
            $table->integer('VAC_LCAN')->nullable();
            $table->text('VAC_OBS')->nullable();
            $table->boolean('VAC_VTR')->nullable();
            $table->dateTime('VAC_FCARGA')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vacaciones');
    }
};
