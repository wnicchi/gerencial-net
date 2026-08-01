<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ropa_stock', function (Blueprint $table) {
            $table->integer('SEPP_COD')->nullable();
            $table->string('SEPP_DES', 50)->nullable();
            $table->integer('SEPP_MAR')->nullable();
            $table->string('SEPP_MAD', 30)->nullable();
            $table->integer('SEPP_TAL')->nullable();
            $table->string('SEPP_TAD', 20)->nullable();
            $table->integer('SEPP_DEP')->nullable();
            $table->string('SEPP_DED', 30)->nullable();
            $table->integer('SEPP_STOCK')->nullable();
            $table->increments('unico');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ropa_stock');
    }
};
