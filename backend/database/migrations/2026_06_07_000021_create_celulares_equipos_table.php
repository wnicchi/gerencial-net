<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('celulares_equipos', function (Blueprint $table) {
            $table->integer('cel_cod')->nullable();
            $table->string('cel_imei', 30)->nullable();
            $table->string('cel_marca', 30)->nullable();
            $table->string('cel_modelo', 30)->nullable();
            $table->string('cel_color', 15)->nullable();
            $table->decimal('cel_pantalla', 7, 2)->nullable();
            $table->string('cel_sistema', 30)->nullable();
            $table->boolean('cel_cargador')->nullable();
            $table->boolean('cel_auricular')->nullable();
            $table->boolean('cel_cableusb')->nullable();
            $table->dateTime('cel_compra')->nullable();
            $table->smallInteger('cel_garantia')->nullable();
            $table->boolean('cel_baja')->nullable();
            $table->dateTime('cel_fbaja')->nullable();
            $table->string('cel_razbaj', 100)->nullable();
            $table->boolean('cel_vidrio')->nullable();
            $table->boolean('cel_carcasa')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('celulares_equipos');
    }
};
