<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contratista', function (Blueprint $table) {
            $table->bigInteger('CONT_COD')->nullable();
            $table->char('CONT_DET', 100)->nullable();
            $table->bigInteger('CONT_INI')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contratista');
    }
};
