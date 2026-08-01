<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reloj_grupos_exe', function (Blueprint $table) {
            $table->smallInteger('rgx_cod')->nullable();
            $table->dateTime('rgx_fdes')->nullable();
            $table->dateTime('rgx_fhas')->nullable();
            $table->tinyInteger('rgx_tiem')->nullable();
            $table->smallInteger('rgx_des')->nullable();
            $table->smallInteger('rgx_has')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reloj_grupos_exe');
    }
};
