<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('obras_sociales', function (Blueprint $table) {
            $table->integer('OBR_COD')->nullable();
            $table->char('OBR_NOM', 50)->nullable();
            $table->char('OBR_DOM', 50)->nullable();
            $table->char('OBR_TEL', 30)->nullable();
            $table->char('OBR_EMA', 30)->nullable();
            $table->char('OBR_AOP', 1)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('obras_sociales');
    }
};
