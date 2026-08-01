<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medicos', function (Blueprint $table) {
            $table->smallInteger('MED_COD')->nullable();
            $table->char('MED_NOM', 50)->nullable();
            $table->char('MED_DOM', 30)->nullable();
            $table->char('MED_TEL', 30)->nullable();
            $table->text('MED_NOT')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medicos');
    }
};
