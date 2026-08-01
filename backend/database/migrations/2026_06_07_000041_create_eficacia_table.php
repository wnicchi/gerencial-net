<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eficacia', function (Blueprint $table) {
            $table->bigInteger('EFI_COD')->nullable();
            $table->char('EFI_DES', 50)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eficacia');
    }
};
