<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('liq_prov', function (Blueprint $table) {
            $table->integer('LIT_NRO')->nullable();
            $table->integer('LIT_PER')->nullable();
            $table->char('LIT_TIP', 1)->nullable();
            $table->smallInteger('LIT_COD')->nullable();
            $table->char('LIT_DES', 30)->nullable();
            $table->decimal('LIT_CAN', 9, 2)->nullable();
            $table->decimal('LIT_ALI', 9, 2)->nullable();
            $table->decimal('LIT_IMP', 9, 2)->nullable();
            $table->decimal('LIT_TOT', 9, 2)->nullable();
            $table->char('LIT_RET', 1)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('liq_prov');
    }
};
