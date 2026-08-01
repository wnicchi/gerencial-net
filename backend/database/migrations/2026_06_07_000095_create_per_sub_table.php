<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('per_sub', function (Blueprint $table) {
            $table->integer('PSU_COD')->nullable();
            $table->integer('PSU_SUB')->nullable();
            $table->char('PSU_SUN', 50)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('per_sub');
    }
};
