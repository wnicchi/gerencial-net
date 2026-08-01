<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipohora', function (Blueprint $table) {
            $table->integer('THO_COD')->nullable();
            $table->string('THO_DES', 30)->nullable();
            $table->boolean('THO_MOD')->nullable();
            $table->decimal('THO_IMP', 12, 2)->nullable();
            $table->string('THO_FOM', 50)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipohora');
    }
};
