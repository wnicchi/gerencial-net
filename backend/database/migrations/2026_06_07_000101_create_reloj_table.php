<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reloj', function (Blueprint $table) {
            $table->bigInteger('rel_cod')->nullable();
            $table->dateTime('rel_fec')->nullable();
            $table->tinyInteger('rel_id')->nullable();
            $table->boolean('ignorar')->nullable();
            $table->increments('UNICO');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reloj');
    }
};
