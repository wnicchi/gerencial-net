<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departam', function (Blueprint $table) {
            $table->bigInteger('DEP_COD')->nullable();
            $table->char('DEP_DES', 50)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departam');
    }
};
