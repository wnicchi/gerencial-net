<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enfermedades', function (Blueprint $table) {
            $table->string('enf_cod', 10)->nullable();
            $table->string('enf_des', 50)->nullable();
            $table->increments('unico');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enfermedades');
    }
};
