<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entregaropa', function (Blueprint $table) {
            $table->bigInteger('ENR_ROP')->nullable();
            $table->char('ENR_DES', 100)->nullable();
            $table->char('ENR_CUIL', 13)->nullable();
            $table->dateTime('ENR_FEC')->nullable();
            $table->decimal('ENR_CAN', 10, 2)->nullable();
            $table->char('ENR_MOT', 100)->nullable();
            $table->char('ENR_CER', 1)->nullable();
            $table->boolean('ENR_STK')->nullable();
            $table->bigInteger('ENR_MAC')->nullable();
            $table->string('ENR_MAD', 30)->nullable();
            $table->bigInteger('ENR_TAL')->nullable();
            $table->string('ENR_TAD', 20)->nullable();
            $table->integer('ENR_DEP')->nullable();
            $table->string('ENR_DED', 30)->nullable();
            $table->boolean('ENR_OBSEQUIO')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entregaropa');
    }
};
