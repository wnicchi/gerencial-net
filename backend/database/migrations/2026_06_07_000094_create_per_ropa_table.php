<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('per_ropa', function (Blueprint $table) {
            $table->bigInteger('PERO_COD')->nullable();
            $table->bigInteger('PERO_RCOD')->nullable();
            $table->char('PERO_RDES', 100)->nullable();
            $table->integer('PERO_RTAL')->nullable();
            $table->string('PERO_RTAD', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('per_ropa');
    }
};
