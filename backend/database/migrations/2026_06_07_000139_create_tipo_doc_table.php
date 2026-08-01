<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipo_doc', function (Blueprint $table) {
            $table->char('TDO_COD', 2)->nullable();
            $table->char('TDO_DES', 50)->nullable();
            $table->char('TDO_TIP', 1)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipo_doc');
    }
};
