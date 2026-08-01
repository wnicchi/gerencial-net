<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('externos', function (Blueprint $table) {
            $table->bigInteger('EXT_COD')->nullable();
            $table->char('EXT_APE', 50)->nullable();
            $table->char('EXT_NOM', 50)->nullable();
            $table->tinyInteger('EXT_TDO')->nullable();
            $table->integer('EXT_NDO')->nullable();
            $table->char('EXT_EMP', 50)->nullable();
            $table->char('EXT_VMA', 30)->nullable();
            $table->char('EXT_VMO', 30)->nullable();
            $table->char('EXT_VDO', 7)->nullable();
            $table->boolean('EXT_EST')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('externos');
    }
};
