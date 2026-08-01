<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tema', function (Blueprint $table) {
            $table->bigInteger('TEM_COD')->nullable();
            $table->char('TEM_DES', 50)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tema');
    }
};
