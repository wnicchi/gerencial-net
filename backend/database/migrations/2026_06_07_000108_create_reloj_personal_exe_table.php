<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reloj_personal_exe', function (Blueprint $table) {
            $table->bigInteger('pex_cod')->nullable();
            $table->dateTime('pex_fdes')->nullable();
            $table->dateTime('pex_fhas')->nullable();
            $table->smallInteger('pex_des')->nullable();
            $table->smallInteger('pex_has')->nullable();
            $table->boolean('pex_hab')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reloj_personal_exe');
    }
};
