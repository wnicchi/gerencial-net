<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cap_empdoc', function (Blueprint $table) {
            $table->integer('cap_nro')->nullable();
            $table->integer('cap_emp')->nullable();
            $table->integer('cap_doc')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cap_empdoc');
    }
};
