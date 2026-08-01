<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contemp', function (Blueprint $table) {
            $table->integer('CEM_COD')->nullable();
            $table->char('CEM_NOM', 50)->nullable();
            $table->char('CEM_DNI', 15)->nullable();
            $table->bigInteger('CEM_CUI')->nullable();
            $table->char('CEM_TEL', 30)->nullable();
            $table->char('CEM_CEL', 30)->nullable();
            $table->bigInteger('CEM_CON')->nullable();
            $table->dateTime('CEM_VART')->nullable();
            $table->char('CEM_EST', 1)->nullable();
            $table->char('CEM_ACC', 1)->nullable();
            $table->string('CEM_OBS', 200)->nullable();
            $table->increments('unico');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contemp');
    }
};
