<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accesos_mov', function (Blueprint $table) {
            $table->bigInteger('AMO_EXT')->nullable();
            $table->dateTime('AMO_ING')->nullable();
            $table->dateTime('AMO_EGR')->nullable();
            $table->char('AMO_GUA', 30)->nullable();
            $table->char('AMO_AUT', 30)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accesos_mov');
    }
};
