<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('examtipo', function (Blueprint $table) {
            $table->tinyInteger('EXT_COD')->nullable();
            $table->char('EXT_DET', 50)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('examtipo');
    }
};
