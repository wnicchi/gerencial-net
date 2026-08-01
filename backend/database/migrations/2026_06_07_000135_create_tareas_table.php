<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tareas', function (Blueprint $table) {
            $table->bigInteger('tarea_cod')->nullable();
            $table->char('tarea_des', 150)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tareas');
    }
};
