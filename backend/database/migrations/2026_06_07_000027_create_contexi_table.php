<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contexi', function (Blueprint $table) {
            $table->bigInteger('CEX_CON')->nullable();
            $table->bigInteger('CEX_EXI')->nullable();
            $table->bigInteger('CEX_ORD')->nullable();
            $table->char('CEX_TIP', 1)->nullable();
            $table->bigInteger('CEX_REL')->nullable();
            $table->char('CEX_PRE', 1)->nullable();
            $table->dateTime('CEX_FPR')->nullable();
            $table->char('CEX_OBS', 150)->nullable();
            $table->dateTime('CEX_VEN')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contexi');
    }
};
