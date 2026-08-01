<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parametro', function (Blueprint $table) {
            $table->char('PATHLOGO', 150)->nullable();
            $table->decimal('IMP_ALM', 10, 2)->nullable();
            $table->tinyInteger('PAR_NCUI')->nullable();
            $table->tinyInteger('PAR_NCOD')->nullable();
            $table->tinyInteger('PAR_NDET')->nullable();
            $table->tinyInteger('PAR_NCAN')->nullable();
            $table->tinyInteger('PAR_NHAB')->nullable();
            $table->tinyInteger('PAR_NDED')->nullable();
            $table->tinyInteger('PAR_PUE')->nullable();
            $table->boolean('PAR_ALA')->nullable();
            $table->decimal('PAR_D30', 10, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parametro');
    }
};
