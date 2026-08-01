<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('convenio', function (Blueprint $table) {
            $table->integer('CON_COD')->nullable();
            $table->char('CON_DES', 20)->nullable();
            $table->char('CON_JOM', 1)->nullable();
            $table->smallInteger('CON_MIN')->nullable();
            $table->decimal('CON_PNE', 7, 2)->nullable();
            $table->tinyInteger('CON_LUN')->nullable();
            $table->tinyInteger('CON_MAR')->nullable();
            $table->tinyInteger('CON_MIE')->nullable();
            $table->tinyInteger('CON_JUE')->nullable();
            $table->tinyInteger('CON_VIE')->nullable();
            $table->tinyInteger('CON_SAB')->nullable();
            $table->tinyInteger('CON_DOM')->nullable();
            $table->boolean('CON_DTR')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('convenio');
    }
};
