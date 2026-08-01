<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('obras', function (Blueprint $table) {
            $table->bigInteger('OBR_COD')->nullable();
            $table->char('OBR_DET', 100)->nullable();
            $table->text('OBR_NOT')->nullable();
            $table->dateTime('OBR_FIN')->nullable();
            $table->dateTime('OBR_FCI')->nullable();
            $table->bigInteger('OBR_CON')->nullable();
            $table->char('OBR_COND', 50)->nullable();
            $table->char('OBR_USU', 30)->nullable();
            $table->char('OBR_TER', 30)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('obras');
    }
};
