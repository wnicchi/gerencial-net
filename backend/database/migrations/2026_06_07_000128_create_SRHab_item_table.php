<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('SRHab_item', function (Blueprint $table) {
            $table->bigInteger('SRI_NRO')->nullable();
            $table->bigInteger('SRI_EMP')->nullable();
            $table->char('SRI_EMD', 30)->nullable();
            $table->smallInteger('SRI_CON')->nullable();
            $table->char('SRI_COD', 20)->nullable();
            $table->smallInteger('SRI_SUC')->nullable();
            $table->bigInteger('SRI_CTA')->nullable();
            $table->decimal('SRI_MON', 12, 2)->nullable();
            $table->dateTime('SRI_FIM')->nullable();
            $table->increments('UNICO');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('SRHab_item');
    }
};
