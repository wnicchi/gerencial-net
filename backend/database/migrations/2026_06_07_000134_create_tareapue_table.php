<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tareapue', function (Blueprint $table) {
            $table->bigInteger('TAR_COD')->nullable();
            $table->char('TAR_DES', 150)->nullable();
            $table->char('TAR_PUE', 15)->nullable();
            $table->integer('TAR_FRE')->nullable();
            $table->bigInteger('TAR_MIN')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tareapue');
    }
};
