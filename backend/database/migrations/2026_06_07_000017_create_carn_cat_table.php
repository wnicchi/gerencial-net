<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carn_cat', function (Blueprint $table) {
            $table->char('CRT_COD', 4)->nullable();
            $table->char('CRT_DES', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carn_cat');
    }
};
