<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('convenio_epp', function (Blueprint $table) {
            $table->integer('conrop_con')->nullable();
            $table->string('conrop_condes', 20)->nullable();
            $table->bigInteger('conrop_rop')->nullable();
            $table->string('conrop_ropdes', 100)->nullable();
            $table->smallInteger('conrop_dias')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('convenio_epp');
    }
};
