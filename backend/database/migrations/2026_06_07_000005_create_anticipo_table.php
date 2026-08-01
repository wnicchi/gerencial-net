<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anticipo', function (Blueprint $table) {
            $table->integer('ANT_NRO')->nullable();
            $table->integer('ANT_PER')->nullable();
            $table->char('ANT_DEP', 20)->nullable();
            $table->dateTime('ANT_FEC')->nullable();
            $table->tinyInteger('ANT_QUI')->nullable();
            $table->tinyInteger('ANT_MES')->nullable();
            $table->smallInteger('ANT_ANO')->nullable();
            $table->char('ANT_DET', 100)->nullable();
            $table->decimal('ANT_IMP', 9, 2)->nullable();
            $table->bigInteger('ANT_LIQ')->nullable();
            $table->tinyInteger('ANT_TIP')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anticipo');
    }
};
