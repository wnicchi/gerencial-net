<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ctas_ban', function (Blueprint $table) {
            $table->smallInteger('CBA_COD')->nullable();
            $table->char('CBA_DES', 30)->nullable();
            $table->bigInteger('CBA_CSIL')->nullable();
            $table->bigInteger('CBA_CLOG')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ctas_ban');
    }
};
