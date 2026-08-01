<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vale_combu', function (Blueprint $table) {
            $table->bigInteger('VLE_NRO')->nullable();
            $table->integer('VLE_VEH')->nullable();
            $table->char('VLE_DOM', 10)->nullable();
            $table->char('VLE_DES', 50)->nullable();
            $table->bigInteger('VLE_PER')->nullable();
            $table->char('VLE_NOM', 50)->nullable();
            $table->bigInteger('VLE_PVR')->nullable();
            $table->char('VLE_PVD', 100)->nullable();
            $table->dateTime('VLE_FEC')->nullable();
            $table->bigInteger('VLE_COM')->nullable();
            $table->decimal('VLE_HOR', 10, 2)->nullable();
            $table->bigInteger('VLE_KMS')->nullable();
            $table->bigInteger('VLE_LTS')->nullable();
            $table->decimal('VLE_GRA', 12, 2)->nullable();
            $table->decimal('VLE_NGR', 12, 2)->nullable();
            $table->decimal('VLE_IVA', 12, 2)->nullable();
            $table->decimal('VLE_IMP', 12, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vale_combu');
    }
};
