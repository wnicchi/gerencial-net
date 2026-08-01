<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('capacitacion', function (Blueprint $table) {
            $table->bigInteger('CAP_COD')->nullable();
            $table->integer('CAP_DISER_COD')->nullable();
            $table->char('CAP_DISER_NOM', 100)->nullable();
            $table->char('CAP_DURA', 50)->nullable();
            $table->char('CAP_OBJE', 100)->nullable();
            $table->dateTime('CAP_FEC')->nullable();
            $table->char('CAP_CAPA', 100)->nullable();
            $table->bigInteger('CAP_TEM_COD')->nullable();
            $table->char('CAP_TEM_DET', 100)->nullable();
            $table->dateTime('CAP_PER_INI')->nullable();
            $table->dateTime('CAP_PER_FIN')->nullable();
            $table->boolean('CAP_CERRADA')->nullable();
            $table->string('CAP_MODALIDAD', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capacitacion');
    }
};
