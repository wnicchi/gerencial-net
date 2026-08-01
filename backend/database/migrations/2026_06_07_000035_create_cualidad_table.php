<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cualidad', function (Blueprint $table) {
            $table->integer('CUA_COD')->nullable();
            $table->char('CUA_DES', 50)->nullable();
            $table->char('CUA_PUE', 15)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cualidad');
    }
};
