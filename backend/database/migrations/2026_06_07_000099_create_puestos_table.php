<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('puestos', function (Blueprint $table) {
            $table->char('PUE_COD', 15)->nullable();
            $table->char('PUE_DES', 50)->nullable();
            $table->bigInteger('PUE_DEP')->nullable();
            $table->char('PUE_REP', 50)->nullable();
            $table->text('PUE_OBJ')->nullable();
            $table->char('PUE_MOD', 100)->nullable();
            $table->char('PUE_REQ', 100)->nullable();
            $table->dateTime('PUE_FEC')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('puestos');
    }
};
