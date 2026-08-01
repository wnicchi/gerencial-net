<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entrevistas_doc', function (Blueprint $table) {
            $table->bigInteger('ETV_COD')->nullable();
            $table->smallInteger('ETV_ORD')->nullable();
            $table->char('ETV_COM', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entrevistas_doc');
    }
};
