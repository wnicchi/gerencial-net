<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cap_emp', function (Blueprint $table) {
            $table->bigInteger('CAP_COD')->nullable();
            $table->bigInteger('PER_COD')->nullable();
            $table->bigInteger('EFI_COD')->nullable();
            $table->boolean('NO_PARTICIPO')->nullable();
            $table->integer('DISER_COD')->nullable();
            $table->string('DISER_NOM', 50)->nullable();
            $table->string('DURACION', 50)->nullable();
            $table->string('MODALIDAD', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cap_emp');
    }
};
