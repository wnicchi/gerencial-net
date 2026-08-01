<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('per_hist', function (Blueprint $table) {
            $table->integer('hla_cod')->nullable();
            $table->dateTime('hla_fec')->nullable();
            $table->char('hla_ter', 30)->nullable();
            $table->char('hla_usu', 30)->nullable();
            $table->text('hla_cam')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('per_hist');
    }
};
