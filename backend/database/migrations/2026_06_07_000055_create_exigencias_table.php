<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exigencias', function (Blueprint $table) {
            $table->bigInteger('EXI_COD')->nullable();
            $table->char('EXI_DET', 150)->nullable();
            $table->char('EXI_UNI', 1)->nullable();
            $table->tinyInteger('EXI_MES')->nullable();
            $table->char('EXI_NOT', 200)->nullable();
            $table->boolean('EXI_ART')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exigencias');
    }
};
