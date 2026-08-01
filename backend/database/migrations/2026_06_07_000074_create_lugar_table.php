<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lugar', function (Blueprint $table) {
            $table->bigInteger('LUG_COD')->nullable();
            $table->char('LUG_NOM', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lugar');
    }
};
