<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('educacion', function (Blueprint $table) {
            $table->integer('EDU_COD')->nullable();
            $table->char('EDU_DES', 50)->nullable();
            $table->char('EDU_PUE', 15)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('educacion');
    }
};
