<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entrevistas', function (Blueprint $table) {
            $table->integer('ETV_COD')->nullable();
            $table->char('ETV_NOM', 50)->nullable();
            $table->char('ETV_DOM', 50)->nullable();
            $table->char('ETV_TEL', 30)->nullable();
            $table->char('ETV_EMA', 30)->nullable();
            $table->dateTime('ETV_FEC')->nullable();
            $table->char('ETV_LUG', 30)->nullable();
            $table->smallInteger('ETV_SEC')->nullable();
            $table->char('ETV_SED', 30)->nullable();
            $table->smallInteger('ETV_SUC')->nullable();
            $table->char('ETV_SUD', 30)->nullable();
            $table->text('ETV_NOT')->nullable();
            $table->char('ETV_TDO', 3)->nullable();
            $table->bigInteger('ETV_DOC')->nullable();
            $table->string('ETV_FOR_ACA', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entrevistas');
    }
};
