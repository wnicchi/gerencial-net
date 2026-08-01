<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ropa', function (Blueprint $table) {
            $table->bigInteger('ROP_COD')->nullable();
            $table->char('ROP_DES', 100)->nullable();
            $table->bigInteger('ROP_RUC')->nullable();
            $table->char('ROP_RUD', 50)->nullable();
            $table->text('ROP_COM')->nullable();
            $table->boolean('ROP_INA')->nullable();
            $table->boolean('ROP_OBSEQUIO')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ropa');
    }
};
