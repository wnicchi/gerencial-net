<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vacadias', function (Blueprint $table) {
            $table->tinyInteger('vre_mini')->nullable();
            $table->tinyInteger('vre_aini')->nullable();
            $table->tinyInteger('vre_mfin')->nullable();
            $table->tinyInteger('vre_afin')->nullable();
            $table->tinyInteger('vre_dias')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vacadias');
    }
};
