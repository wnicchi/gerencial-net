<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estadocivil', function (Blueprint $table) {
            $table->integer('ECI_COD')->nullable();
            $table->char('ECI_DES', 50)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estadocivil');
    }
};
