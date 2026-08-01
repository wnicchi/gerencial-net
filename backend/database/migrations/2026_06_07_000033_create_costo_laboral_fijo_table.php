<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('costo_laboral_fijo', function (Blueprint $table) {
            $table->integer('COS_COD')->nullable();
            $table->string('COS_DET', 100)->nullable();
            $table->decimal('COS_IMP', 18, 2)->nullable();
            $table->tinyInteger('COS_MES')->nullable();
            $table->smallInteger('COS_ANIO')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('costo_laboral_fijo');
    }
};
