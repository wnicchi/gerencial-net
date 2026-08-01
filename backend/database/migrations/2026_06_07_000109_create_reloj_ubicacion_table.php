<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reloj_ubicacion', function (Blueprint $table) {
            $table->tinyInteger('ure_cod')->nullable();
            $table->string('ure_des', 50)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reloj_ubicacion');
    }
};
