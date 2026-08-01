<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('encargados', function (Blueprint $table) {
            $table->integer('ENCA_NRO')->nullable();
            $table->char('ENCA_NOM', 50)->nullable();
            $table->integer('ENCA_COD')->nullable();
            $table->integer('ENCA_LEG')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('encargados');
    }
};
