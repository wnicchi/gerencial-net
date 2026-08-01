<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reloj_grupos', function (Blueprint $table) {
            $table->smallInteger('rgr_cod')->nullable();
            $table->string('rgr_des', 100)->nullable();
            $table->smallInteger('rgr_t1d1d')->nullable();
            $table->smallInteger('rgr_t1d1h')->nullable();
            $table->smallInteger('rgr_t1d2d')->nullable();
            $table->smallInteger('rgr_t1d2h')->nullable();
            $table->smallInteger('rgr_t1d3d')->nullable();
            $table->smallInteger('rgr_t1d3h')->nullable();
            $table->smallInteger('rgr_t1d4d')->nullable();
            $table->smallInteger('rgr_t1d4h')->nullable();
            $table->smallInteger('rgr_t1d5d')->nullable();
            $table->smallInteger('rgr_t1d5h')->nullable();
            $table->smallInteger('rgr_t1d6d')->nullable();
            $table->smallInteger('rgr_t1d6h')->nullable();
            $table->smallInteger('rgr_t1d7d')->nullable();
            $table->smallInteger('rgr_t1d7h')->nullable();
            $table->tinyInteger('rgr_t1cam')->nullable();
            $table->tinyInteger('rgr_t1pas')->nullable();
            $table->smallInteger('rgr_t2d1d')->nullable();
            $table->smallInteger('rgr_t2d1h')->nullable();
            $table->smallInteger('rgr_t2d2d')->nullable();
            $table->smallInteger('rgr_t2d2h')->nullable();
            $table->smallInteger('rgr_t2d3d')->nullable();
            $table->smallInteger('rgr_t2d3h')->nullable();
            $table->smallInteger('rgr_t2d4d')->nullable();
            $table->smallInteger('rgr_t2d4h')->nullable();
            $table->smallInteger('rgr_t2d5d')->nullable();
            $table->smallInteger('rgr_t2d5h')->nullable();
            $table->smallInteger('rgr_t2d6d')->nullable();
            $table->smallInteger('rgr_t2d6h')->nullable();
            $table->smallInteger('rgr_t2d7d')->nullable();
            $table->smallInteger('rgr_t2d7h')->nullable();
            $table->tinyInteger('rgr_t2cam')->nullable();
            $table->tinyInteger('rgr_t2pas')->nullable();
            $table->smallInteger('rgr_t3d1d')->nullable();
            $table->smallInteger('rgr_t3d1h')->nullable();
            $table->smallInteger('rgr_t3d2d')->nullable();
            $table->smallInteger('rgr_t3d2h')->nullable();
            $table->smallInteger('rgr_t3d3d')->nullable();
            $table->smallInteger('rgr_t3d3h')->nullable();
            $table->smallInteger('rgr_t3d4d')->nullable();
            $table->smallInteger('rgr_t3d4h')->nullable();
            $table->smallInteger('rgr_t3d5d')->nullable();
            $table->smallInteger('rgr_t3d5h')->nullable();
            $table->smallInteger('rgr_t3d6d')->nullable();
            $table->smallInteger('rgr_t3d6h')->nullable();
            $table->smallInteger('rgr_t3d7d')->nullable();
            $table->smallInteger('rgr_t3d7h')->nullable();
            $table->tinyInteger('rgr_t3cam')->nullable();
            $table->tinyInteger('rgr_t3pas')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reloj_grupos');
    }
};
