<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requerimientos', function (Blueprint $table) {
            $table->integer('REQ_COD')->nullable();
            $table->char('REQ_DES', 100)->nullable();
            $table->smallInteger('REQ_DIA')->nullable();
            $table->text('REQ_OBS')->nullable();
            $table->boolean('REQ_COM')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requerimientos');
    }
};
