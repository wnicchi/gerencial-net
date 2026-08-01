<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('VARHab_item', function (Blueprint $table) {
            $table->bigInteger('VRI_NRO')->nullable();
            $table->bigInteger('VRI_EMP')->nullable();
            $table->char('VRI_EMD', 30)->nullable();
            $table->smallInteger('VRI_CON')->nullable();
            $table->char('VRI_COD', 20)->nullable();
            $table->smallInteger('VRI_SUC')->nullable();
            $table->bigInteger('VRI_CTA')->nullable();
            $table->decimal('VRI_MON', 12, 2)->nullable();
            $table->dateTime('VRI_FIM')->nullable();
            $table->increments('UNICO');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('VARHab_item');
    }
};
