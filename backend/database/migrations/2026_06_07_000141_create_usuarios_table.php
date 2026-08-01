<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuarios', function (Blueprint $table) {
            $table->integer('CODIGO')->nullable();
            $table->char('NOMBRE', 50)->nullable();
            $table->char('DOMICILIO', 50)->nullable();
            $table->char('TELEFONO', 30)->nullable();
            $table->char('DNI', 20)->nullable();
            $table->text('NOTAS')->nullable();
            $table->char('DATO1', 20)->nullable();
            $table->text('DATO2')->nullable();
            $table->bigInteger('TLEFT')->nullable();
            $table->bigInteger('TTOP')->nullable();
            $table->bigInteger('TWIDTH')->nullable();
            $table->bigInteger('THEIGHT')->nullable();
            $table->boolean('ESTADO')->nullable();
            $table->char('TERMINAL', 100)->nullable();
            $table->dateTime('INICIO')->nullable();
            $table->bigInteger('NIVEL')->nullable();
            $table->boolean('RENOVAR')->nullable();
            $table->bigInteger('CADACUANTO')->nullable();
            $table->bigInteger('CONTADOR')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuarios');
    }
};
