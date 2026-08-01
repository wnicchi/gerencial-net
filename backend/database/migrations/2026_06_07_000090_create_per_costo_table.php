<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('per_costo', function (Blueprint $table) {
            $table->integer('PER_COD')->nullable();
            $table->char('PER_NOM', 50)->nullable();
            $table->smallInteger('PER_CCO')->nullable();
            $table->char('PER_DES', 30)->nullable();
            $table->char('PER_TIP', 2)->nullable();
            $table->decimal('PER_POR', 6, 2)->nullable();
            $table->tinyInteger('PER_MES')->nullable();
            $table->smallInteger('PER_ANO')->nullable();
            $table->char('PER_MYA', 6)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('per_costo');
    }
};
