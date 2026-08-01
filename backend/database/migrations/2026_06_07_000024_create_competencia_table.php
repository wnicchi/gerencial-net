<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competencia', function (Blueprint $table) {
            $table->integer('COMP_COD')->nullable();
            $table->char('COMP_DES', 50)->nullable();
            $table->char('COMP_PUE', 15)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competencia');
    }
};
