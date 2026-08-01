<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cont_oblig', function (Blueprint $table) {
            $table->bigInteger('COE_CON')->nullable();
            $table->bigInteger('COE_EXI')->nullable();
            $table->dateTime('COE_VIG')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cont_oblig');
    }
};
