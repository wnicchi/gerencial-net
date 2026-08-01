<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bloqueos', function (Blueprint $table) {
            $table->char('BLO_TIP', 1)->nullable();
            $table->tinyInteger('BLO_MES')->nullable();
            $table->smallInteger('BLO_ANO')->nullable();
            $table->char('BLO_EST', 1)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bloqueos');
    }
};
