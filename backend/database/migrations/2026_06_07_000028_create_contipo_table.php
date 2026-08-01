<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contipo', function (Blueprint $table) {
            $table->integer('cot_con')->nullable();
            $table->string('cot_det', 100)->nullable();
            $table->string('cot_foe', 1)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contipo');
    }
};
