<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('valores', function (Blueprint $table) {
            $table->decimal('VAL_CIE', 7, 2)->nullable();
            $table->decimal('VAL_PC1', 5, 2)->nullable();
            $table->decimal('VAL_PC2', 5, 2)->nullable();
            $table->decimal('VAL_ESP', 7, 2)->nullable();
            $table->decimal('VAL_HIJ', 7, 2)->nullable();
            $table->decimal('VAL_HIM', 7, 2)->nullable();
            $table->decimal('VAL_HID', 7, 2)->nullable();
            $table->decimal('VAL_EPR', 7, 2)->nullable();
            $table->decimal('VAL_EMS', 7, 2)->nullable();
            $table->decimal('VAL_FNU', 7, 2)->nullable();
            $table->decimal('VAL_AEP', 7, 2)->nullable();
            $table->decimal('VAL_CAS', 7, 2)->nullable();
            $table->decimal('VAL_NAC', 7, 2)->nullable();
            $table->decimal('VAL_ADO', 7, 2)->nullable();
            $table->decimal('VAL_PRE', 7, 2)->nullable();
            $table->char('VAL_DBA', 25)->nullable();
            $table->char('VAL_LAP', 12)->nullable();
            $table->dateTime('VAL_AFE')->nullable();
            $table->char('LUG_PAG', 40)->nullable();
            $table->integer('VAL_HA1')->nullable();
            $table->integer('VAL_HA2')->nullable();
            $table->integer('VAL_HA3')->nullable();
            $table->integer('VAL_HA4')->nullable();
            $table->decimal('VAL_HI1', 6, 2)->nullable();
            $table->decimal('VAL_HI2', 6, 2)->nullable();
            $table->decimal('VAL_HI3', 6, 2)->nullable();
            $table->decimal('VAL_HI4', 6, 2)->nullable();
            $table->decimal('VAL_PPR', 6, 2)->nullable();
            $table->decimal('VAL_AOS', 6, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('valores');
    }
};
