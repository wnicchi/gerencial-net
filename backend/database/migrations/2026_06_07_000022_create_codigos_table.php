<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('codigos', function (Blueprint $table) {
            $table->char('TABLA', 20)->nullable();
            $table->bigInteger('CODIGO')->nullable();
            $table->bigInteger('SALTO')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('codigos');
    }
};
