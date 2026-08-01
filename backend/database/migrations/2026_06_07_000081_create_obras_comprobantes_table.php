<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('obras_comprobantes', function (Blueprint $table) {
            $table->integer('OCOM_OBR')->nullable();
            $table->char('OCOM_OBD', 100)->nullable();
            $table->smallInteger('OCOM_SUC')->nullable();
            $table->integer('OCOM_NRO')->nullable();
            $table->dateTime('OCOM_FEC')->nullable();
            $table->char('OCOM_TDO', 3)->nullable();
            $table->decimal('OCOM_IMP', 14, 2)->nullable();
            $table->increments('UNICO');
            $table->tinyInteger('OCOM_EMP')->nullable();
            $table->char('OCOM_EMD', 50)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('obras_comprobantes');
    }
};
