<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('encargados_personal', function (Blueprint $table) {
            $table->integer('ENPE_NRO')->nullable();
            $table->integer('ENPE_COD')->nullable();
            $table->char('ENPE_NOM', 50)->nullable();
            $table->integer('ENPE_LEG')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('encargados_personal');
    }
};
