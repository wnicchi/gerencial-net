<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contnom', function (Blueprint $table) {
            $table->integer('CTR_COD')->nullable();
            $table->char('CTR_NOM', 50)->nullable();
            $table->char('CTR_DOM', 50)->nullable();
            $table->char('CTR_TEL', 30)->nullable();
            $table->char('CTR_EMA', 30)->nullable();
            $table->char('CTR_AOP', 1)->nullable();
            $table->char('CTR_ART', 100)->nullable();
            $table->tinyInteger('CTR_TIP')->nullable();
            $table->string('CTR_TID', 50)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contnom');
    }
};
