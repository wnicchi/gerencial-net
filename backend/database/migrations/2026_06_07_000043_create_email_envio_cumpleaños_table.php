<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_envio_cumpleaños', function (Blueprint $table) {
            $table->char('EMAIL', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_envio_cumpleaños');
    }
};
