<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disertantes', function (Blueprint $table) {
            $table->integer('dis_cod')->nullable();
            $table->string('dis_nom', 50)->nullable();
            $table->string('dis_dom', 50)->nullable();
            $table->string('dis_tel', 50)->nullable();
            $table->string('dis_ema', 50)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disertantes');
    }
};
