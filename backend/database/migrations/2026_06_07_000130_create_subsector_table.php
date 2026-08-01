<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subsector', function (Blueprint $table) {
            $table->smallInteger('sub_cod')->nullable();
            $table->char('sub_des', 30)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subsector');
    }
};
