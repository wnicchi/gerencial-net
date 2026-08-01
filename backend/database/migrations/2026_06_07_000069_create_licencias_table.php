<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('licencias', function (Blueprint $table) {
            $table->smallInteger('LIC_COD')->nullable();
            $table->char('LIC_DET', 100)->nullable();
            $table->boolean('LIC_NOA')->nullable();
            $table->boolean('LIC_ENF')->nullable();
            $table->boolean('LIC_PLA')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('licencias');
    }
};
