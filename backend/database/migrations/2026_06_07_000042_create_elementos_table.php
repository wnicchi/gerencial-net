<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('elementos', function (Blueprint $table) {
            $table->integer('ele_cod')->nullable();
            $table->string('ele_des', 50)->nullable();
            $table->string('ele_pue', 15)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('elementos');
    }
};
