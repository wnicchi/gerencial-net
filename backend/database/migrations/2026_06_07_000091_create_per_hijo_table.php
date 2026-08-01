<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('per_hijo', function (Blueprint $table) {
            $table->bigInteger('PER_COD')->nullable();
            $table->tinyInteger('PER_HIJ')->nullable();
            $table->char('PER_NOM', 30)->nullable();
            $table->dateTime('PER_NAC')->nullable();
            $table->dateTime('PER_ING')->nullable();
            $table->char('PER_SIT', 30)->nullable();
            $table->dateTime('PER_FNA')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('per_hijo');
    }
};
