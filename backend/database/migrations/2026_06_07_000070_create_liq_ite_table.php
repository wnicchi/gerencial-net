<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('liq_ite', function (Blueprint $table) {
            $table->integer('LIT_NRO')->nullable();
            $table->integer('LIT_PER')->nullable();
            $table->char('LIT_PED', 50)->nullable();
            $table->tinyInteger('LIT_MES')->nullable();
            $table->smallInteger('LIT_ANO')->nullable();
            $table->dateTime('LIT_FEC')->nullable();
            $table->smallInteger('LIT_TIP')->nullable();
            $table->bigInteger('LIT_COD')->nullable();
            $table->char('LIT_DES', 100)->nullable();
            $table->decimal('LIT_CAN', 7, 2)->nullable();
            $table->decimal('LIT_HAB', 12, 2)->nullable();
            $table->decimal('LIT_DED', 12, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('liq_ite');
    }
};
