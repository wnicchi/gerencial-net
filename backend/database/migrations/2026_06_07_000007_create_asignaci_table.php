<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asignaci', function (Blueprint $table) {
            $table->smallInteger('ASI_COD')->nullable();
            $table->char('ASI_DES', 30)->nullable();
            $table->decimal('ASI_IMP', 9, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asignaci');
    }
};
