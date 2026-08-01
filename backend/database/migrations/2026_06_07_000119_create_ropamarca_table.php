<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ropamarca', function (Blueprint $table) {
            $table->bigInteger('RMA_COD')->nullable();
            $table->char('RMA_DES', 30)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ropamarca');
    }
};
