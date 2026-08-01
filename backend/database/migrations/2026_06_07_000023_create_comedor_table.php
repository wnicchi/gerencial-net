<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comedor', function (Blueprint $table) {
            $table->tinyInteger('COME_COD')->nullable();
            $table->char('COME_DES', 50)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comedor');
    }
};
