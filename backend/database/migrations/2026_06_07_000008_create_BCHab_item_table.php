<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('BCHab_item', function (Blueprint $table) {
            $table->bigInteger('FRI_NRO')->nullable();
            $table->bigInteger('FRI_EMP')->nullable();
            $table->char('FRI_EMD', 30)->nullable();
            $table->smallInteger('FRI_CON')->nullable();
            $table->char('FRI_COD', 20)->nullable();
            $table->smallInteger('FRI_SUC')->nullable();
            $table->bigInteger('FRI_CTA')->nullable();
            $table->decimal('FRI_MON', 12, 2)->nullable();
            $table->dateTime('FRI_FIM')->nullable();
            $table->increments('UNICO');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('BCHab_item');
    }
};
