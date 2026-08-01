<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invitados', function (Blueprint $table) {
            $table->bigInteger('INV_COD')->nullable();
            $table->char('INV_NOM', 50)->nullable();
            $table->char('INV_DOM', 50)->nullable();
            $table->char('INV_TEL', 20)->nullable();
            $table->char('INV_CEL', 20)->nullable();
            $table->text('INV_NOT')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitados');
    }
};
