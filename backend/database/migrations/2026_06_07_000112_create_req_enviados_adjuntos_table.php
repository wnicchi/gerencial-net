<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('req_enviados_adjuntos', function (Blueprint $table) {
            $table->integer('unico')->nullable();
            $table->char('documento', 100)->nullable();
            $table->char('ubicacion', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('req_enviados_adjuntos');
    }
};
