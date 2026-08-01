<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empresas', function (Blueprint $table) {
            $table->tinyInteger('EMP_COD')->nullable();
            $table->char('EMP_NOM', 100)->nullable();
            $table->char('EMP_DOM', 100)->nullable();
            $table->char('EMP_LOC', 30)->nullable();
            $table->char('EMP_CUI', 14)->nullable();
            $table->char('EMP_CAJ', 20)->nullable();
            $table->char('EMP_NRO', 10)->nullable();
            $table->char('EMP_GCO', 1)->nullable();
            $table->char('EMP_SER', 1)->nullable();
            $table->tinyInteger('EMP_CON')->nullable();
            $table->decimal('EMP_SEG', 6, 2)->nullable();
            $table->char('EMP_ANT', 1)->nullable();
            $table->char('EMP_RED', 1)->nullable();
            $table->char('EMP_CPO', 10)->nullable();
            $table->char('EMP_PRV', 30)->nullable();
            $table->tinyInteger('EMP_BAS')->nullable();
            $table->bigInteger('EMP_CHA')->nullable();
            $table->smallInteger('EMP_BSFEMP')->nullable();
            $table->smallInteger('EMP_BSFCON')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empresas');
    }
};
