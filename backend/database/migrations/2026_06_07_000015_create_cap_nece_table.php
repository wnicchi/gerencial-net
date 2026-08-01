<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cap_nece', function (Blueprint $table) {
            $table->bigInteger('CNE_EMP')->nullable();
            $table->bigInteger('CNE_TEM')->nullable();
            $table->dateTime('CNE_FEC')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cap_nece');
    }
};
