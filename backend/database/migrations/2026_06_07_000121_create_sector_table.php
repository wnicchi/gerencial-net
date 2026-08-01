<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sector', function (Blueprint $table) {
            $table->smallInteger('SEC_COD')->nullable();
            $table->string('SEC_DES', 50)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sector');
    }
};
