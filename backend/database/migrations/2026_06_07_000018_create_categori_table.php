<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categori', function (Blueprint $table) {
            $table->integer('CAT_COD')->nullable();
            $table->char('CAT_DES', 20)->nullable();
            $table->char('CAT_JOM', 1)->nullable();
            $table->decimal('CAT_HOR', 5, 2)->nullable();
            $table->decimal('CAT_BAS', 14, 2)->nullable();
            $table->tinyInteger('CAT_CON')->nullable();
            $table->char('CAT_CDE', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categori');
    }
};
