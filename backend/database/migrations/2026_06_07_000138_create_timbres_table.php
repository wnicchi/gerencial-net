<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timbres', function (Blueprint $table) {
            $table->tinyInteger('tim_dia')->nullable();
            $table->tinyInteger('tim_hor')->nullable();
            $table->tinyInteger('tim_min')->nullable();
            $table->smallInteger('tim_seg')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timbres');
    }
};
