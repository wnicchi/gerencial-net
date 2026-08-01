<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sueldo_tip', function (Blueprint $table) {
            $table->integer('STI_COD')->nullable();
            $table->char('STI_DES', 50)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sueldo_tip');
    }
};
