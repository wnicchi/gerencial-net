<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('SFHab_item', function (Blueprint $table) {
            $table->bigInteger('SFI_NRO')->nullable();
            $table->bigInteger('SFI_EMP')->nullable();
            $table->char('SFI_EMD', 30)->nullable();
            $table->smallInteger('SFI_CON')->nullable();
            $table->char('SFI_COD', 20)->nullable();
            $table->smallInteger('SFI_SUC')->nullable();
            $table->bigInteger('SFI_CTA')->nullable();
            $table->decimal('SFI_MON', 12, 2)->nullable();
            $table->dateTime('SFI_FIM')->nullable();
            $table->increments('UNICO');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('SFHab_item');
    }
};
