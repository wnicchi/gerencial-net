<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ropacambio', function (Blueprint $table) {
            $table->dateTime('roc_fec')->nullable();
            $table->bigInteger('roc_cod')->nullable();
            $table->char('roc_des', 100)->nullable();
            $table->char('roc_cuil', 13)->nullable();
            $table->boolean('roc_pen')->nullable();
            $table->dateTime('roc_caf')->nullable();
            $table->bigInteger('roc_car')->nullable();
            $table->char('roc_cad', 100)->nullable();
            $table->char('roc_usu', 30)->nullable();
            $table->char('roc_ter', 50)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ropacambio');
    }
};
