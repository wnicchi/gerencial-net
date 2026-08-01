<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ccfondo', function (Blueprint $table) {
            $table->dateTime('CCF_FEC')->nullable();
            $table->char('CCF_DET', 100)->nullable();
            $table->decimal('CCF_DEB', 12, 2)->nullable();
            $table->decimal('CCF_HAB', 12, 2)->nullable();
            $table->char('CCF_TER', 20)->nullable();
            $table->char('CCF_USU', 20)->nullable();
            $table->bigInteger('CCF_VAL')->nullable();
            $table->tinyInteger('CCF_EMP')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ccfondo');
    }
};
