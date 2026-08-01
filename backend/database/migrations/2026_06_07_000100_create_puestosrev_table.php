<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('puestosrev', function (Blueprint $table) {
            $table->string('pur_cod', 15)->nullable();
            $table->dateTime('pur_fre')->nullable();
            $table->string('pur_res', 30)->nullable();
            $table->string('pur_ob1', 100)->nullable();
            $table->string('pur_ob2', 100)->nullable();
            $table->string('pur_ob3', 100)->nullable();
            $table->string('pur_ob4', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('puestosrev');
    }
};
