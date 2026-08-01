<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('SINIESTROS_AGENDA', function (Blueprint $table) {
            $table->integer('SIN_NUM')->nullable();
            $table->char('SIN_TIP', 1)->nullable();
            $table->dateTime('SIN_FEC')->nullable();
            $table->decimal('SIN_imp', 12, 2)->nullable();
            $table->char('SIN_DET', 250)->nullable();
            $table->char('SIN_usu', 20)->nullable();
            $table->dateTime('SIN_FCa')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('SINIESTROS_AGENDA');
    }
};
