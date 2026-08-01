<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('formularios', function (Blueprint $table) {
            $table->char('ELCAPTION', 150)->nullable();
            $table->char('ELSCX', 50)->nullable();
            $table->bigInteger('APERTURAS')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('formularios');
    }
};
