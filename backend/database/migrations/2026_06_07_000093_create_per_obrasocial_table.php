<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('per_obrasocial', function (Blueprint $table) {
            $table->integer('PEO_COD')->nullable();
            $table->string('PEO_NOM', 50)->nullable();
            $table->char('PEO_CUI', 13)->nullable();
            $table->string('PEO_OBRA', 50)->nullable();
            $table->tinyInteger('PEO_MES')->nullable();
            $table->smallInteger('PEO_ANO')->nullable();
            $table->decimal('PEO_NET', 18, 2)->nullable();
            $table->decimal('PEO_DEB', 18, 2)->nullable();
            $table->decimal('PEO_DIF', 18, 2)->nullable();
            $table->increments('UNICO');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('per_obrasocial');
    }
};
