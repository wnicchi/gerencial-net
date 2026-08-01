<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hdocumentos', function (Blueprint $table) {
            $table->integer('DOC_ORD')->nullable();
            $table->char('DOC_TDO', 2)->nullable();
            $table->integer('DOC_NRO')->nullable();
            $table->char('DOC_TIP', 1)->nullable();
            $table->integer('DOC_REF')->nullable();
            $table->char('DOC_DET', 60)->nullable();
            $table->char('DOC_UBI', 120)->nullable();
            $table->char('DOC_TDD', 30)->nullable();
            $table->char('DOC_FUL', 120)->nullable();
            $table->char('DOC_DIR', 120)->nullable();
            $table->char('DOC_NOM', 120)->nullable();
            $table->char('DOC_EXT', 5)->nullable();
            $table->char('DOC_CRE', 20)->nullable();
            $table->bigInteger('DOC_TAM')->nullable();
            $table->bigInteger('DOC_KB')->nullable();
            $table->char('DOC_OBS', 60)->nullable();
            $table->char('DOC_USU', 20)->nullable();
            $table->char('DOC_TER', 20)->nullable();
            $table->char('DOC_GRA', 20)->nullable();
            $table->dateTime('DOC_FEL')->nullable();
            $table->char('DOC_UEL', 20)->nullable();
            $table->integer('UNICO')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hdocumentos');
    }
};
