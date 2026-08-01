<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('obras_emp', function (Blueprint $table) {
            $table->bigInteger('OEM_OBR')->nullable();
            $table->bigInteger('OEM_EMP')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('obras_emp');
    }
};
