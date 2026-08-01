<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('descuent', function (Blueprint $table) {
            $table->integer('DES_COD')->nullable();
            $table->char('DES_DES', 20)->nullable();
            $table->char('DES_VOF', 1)->nullable();
            $table->decimal('DES_ALI', 5, 2)->nullable();
            $table->decimal('DES_IMP', 6, 2)->nullable();
            $table->char('DES_NOR', 1)->nullable();
            $table->char('DES_1QU', 1)->nullable();
            $table->char('DES_2QU', 1)->nullable();
            $table->char('DES_SAC', 1)->nullable();
            $table->tinyInteger('DES_CON')->nullable();
            $table->char('DES_CDE', 20)->nullable();
            $table->char('DES_JOM', 1)->nullable();
            $table->char('DES_SIN', 1)->nullable();
            $table->char('DES_SOC', 1)->nullable();
            $table->char('DES_ANS', 1)->nullable();
            $table->char('DES_JRE', 1)->nullable();
            $table->smallInteger('DES_RUB')->nullable();
            $table->char('DES_RUD', 30)->nullable();
            $table->char('DES_067', 1)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('descuent');
    }
};
