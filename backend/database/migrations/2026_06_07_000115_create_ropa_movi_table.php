<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ropa_movi', function (Blueprint $table) {
            $table->char('RST_IOE', 1)->nullable();
            $table->bigInteger('RST_ROC')->nullable();
            $table->char('RST_ROD', 50)->nullable();
            $table->bigInteger('RST_MAC')->nullable();
            $table->char('RST_MAD', 30)->nullable();
            $table->bigInteger('RST_CAN')->nullable();
            $table->dateTime('RST_FEC')->nullable();
            $table->char('RST_DET', 50)->nullable();
            $table->bigInteger('RST_DEP')->nullable();
            $table->char('RST_DES', 30)->nullable();
            $table->bigInteger('RST_TAL')->nullable();
            $table->char('RST_TAD', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ropa_movi');
    }
};
