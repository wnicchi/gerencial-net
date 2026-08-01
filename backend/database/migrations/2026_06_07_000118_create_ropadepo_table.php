<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ropadepo', function (Blueprint $table) {
            $table->tinyInteger('RDE_COD')->nullable();
            $table->char('RDE_DES', 50)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ropadepo');
    }
};
