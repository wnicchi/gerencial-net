<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('haberes', function (Blueprint $table) {
            $table->integer('HAB_COD')->nullable();
            $table->char('HAB_DES', 20)->nullable();
            $table->decimal('HAB_ALI', 6, 2)->nullable();
            $table->decimal('HAB_IMP', 7, 2)->nullable();
            $table->char('HAB_ANT', 1)->nullable();
            $table->char('HAB_HOR', 1)->nullable();
            $table->char('HAB_CAN', 1)->nullable();
            $table->char('HAB_RET', 1)->nullable();
            $table->char('HAB_TXT', 1)->nullable();
            $table->tinyInteger('HAB_CON')->nullable();
            $table->char('HAB_CDE', 20)->nullable();
            $table->smallInteger('HAB_TOM')->nullable();
            $table->char('HAB_B30', 1)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('haberes');
    }
};
