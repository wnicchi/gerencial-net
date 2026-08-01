<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('celular_empleados', function (Blueprint $table) {
            $table->bigInteger('cem_emp')->nullable();
            $table->string('cem_emd', 50)->nullable();
            $table->integer('cem_equipo')->nullable();
            $table->string('cem_imei', 30)->nullable();
            $table->dateTime('cem_entrega')->nullable();
            $table->string('cem_obsentrega', 100)->nullable();
            $table->dateTime('cem_devolucion')->nullable();
            $table->string('cem_obsdevolu', 100)->nullable();
            $table->string('cem_nrocelular', 15)->nullable();
            $table->increments('unico');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('celular_empleados');
    }
};
