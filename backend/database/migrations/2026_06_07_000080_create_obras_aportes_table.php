<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('obras_aportes', function (Blueprint $table) {
            $table->smallInteger('AYC_ANIO')->nullable();
            $table->tinyInteger('AYC_MES')->nullable();
            $table->bigInteger('AYC_CUI')->nullable();
            $table->bigInteger('AYC_EMP')->nullable();
            $table->char('AYC_NOM', 50)->nullable();
            $table->bigInteger('AYC_OSO')->nullable();
            $table->decimal('AYC_TCON', 14, 2)->nullable();
            $table->decimal('AYC_TAPO', 14, 2)->nullable();
            $table->decimal('AYC_TOTAL', 14, 2)->nullable();
            $table->decimal('AYC_MEDIFE', 14, 2)->nullable();
            $table->decimal('AYC_DMEDIFE', 14, 2)->nullable();
            $table->decimal('AYC_MEDICUS', 14, 2)->nullable();
            $table->decimal('AYC_DMEDICUS', 14, 2)->nullable();
            $table->dateTime('AYC_FIMP')->nullable();
            $table->dateTime('AYC_FACT')->nullable();
            $table->char('AYC_UIMP', 30)->nullable();
            $table->char('AYC_UACT', 30)->nullable();
            $table->increments('UNICO');
            $table->decimal('AYC_RECON', 14, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('obras_aportes');
    }
};
