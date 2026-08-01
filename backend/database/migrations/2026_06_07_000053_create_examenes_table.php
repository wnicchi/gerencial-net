<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('examenes', function (Blueprint $table) {
            $table->bigInteger('EXA_EMP')->nullable();
            $table->char('EXA_EMD', 50)->nullable();
            $table->bigInteger('EXA_TIP')->nullable();
            $table->char('EXA_TID', 50)->nullable();
            $table->dateTime('EXA_FEC')->nullable();
            $table->dateTime('EXA_VEN')->nullable();
            $table->bigInteger('EXA_MED')->nullable();
            $table->char('EXA_MDD', 30)->nullable();
            $table->text('EXA_NOT')->nullable();
            $table->increments('UNICO');
            $table->string('EXA_COE', 1)->nullable();
            $table->string('EXA_ENF', 5)->nullable();
            $table->string('EXA_END', 50)->nullable();
            $table->integer('EXA_ENU')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('examenes');
    }
};
