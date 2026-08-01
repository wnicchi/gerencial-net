<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alm_costo', function (Blueprint $table) {
            $table->tinyInteger('MES')->nullable();
            $table->smallInteger('ANIO')->nullable();
            $table->decimal('IMPORTE', 10, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alm_costo');
    }
};
