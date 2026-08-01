<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nivform', function (Blueprint $table) {
            $table->bigInteger('CODNIV')->nullable();
            $table->char('ELSCX', 50)->nullable();
            $table->boolean('BLOQUEO')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nivform');
    }
};
