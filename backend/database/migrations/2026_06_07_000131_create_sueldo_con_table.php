<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sueldo_con', function (Blueprint $table) {
            $table->bigInteger('SCO_COD')->nullable();
            $table->char('SCO_DES', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sueldo_con');
    }
};
