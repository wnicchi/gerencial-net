<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('talles', function (Blueprint $table) {
            $table->integer('tal_cod')->nullable();
            $table->string('tal_des', 30)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('talles');
    }
};
