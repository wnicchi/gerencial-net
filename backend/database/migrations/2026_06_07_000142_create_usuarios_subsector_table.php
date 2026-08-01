<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuarios_subsector', function (Blueprint $table) {
            $table->integer('USU_COD')->nullable();
            $table->string('USU_NOM', 50)->nullable();
            $table->integer('SUB_COD')->nullable();
            $table->string('SUB_DES', 50)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuarios_subsector');
    }
};
