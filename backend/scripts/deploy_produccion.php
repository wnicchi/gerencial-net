<?php
/**
 * Script de migración a PRODUCCIÓN
 * Ejecutar UNA SOLA VEZ al hacer el deploy inicial.
 *
 * Uso: php scripts/deploy_produccion.php
 *
 * Este script:
 *  1. Crea las tablas nuevas del sistema (cache, jobs, personal_access_tokens)
 *  2. Agrega los campos de autenticación a la tabla usuarios
 *  3. Es SEGURO: verifica si cada tabla/columna ya existe antes de crearla
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

$errores = [];
$ok      = [];

function tableExists(string $table): bool
{
    return Schema::hasTable($table);
}

function columnExists(string $table, string $column): bool
{
    return Schema::hasColumn($table, $column);
}

echo "\n=== DEPLOY A PRODUCCIÓN — Sistema RRHH ===\n\n";

// ──────────────────────────────────────────────
// 1. TABLA: cache
// ──────────────────────────────────────────────
echo "[1/4] Tabla 'cache'... ";
if (tableExists('cache')) {
    echo "YA EXISTE, omitida.\n";
} else {
    try {
        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration');
        });
        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration');
        });
        echo "CREADA OK.\n";
        $ok[] = 'cache';
    } catch (Throwable $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
        $errores[] = 'cache: ' . $e->getMessage();
    }
}

// ──────────────────────────────────────────────
// 2. TABLA: jobs (colas de email)
// ──────────────────────────────────────────────
echo "[2/4] Tabla 'jobs'... ";
if (tableExists('jobs')) {
    echo "YA EXISTE, omitida.\n";
} else {
    try {
        Schema::create('jobs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });
        Schema::create('job_batches', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->longText('failed_job_ids');
            $table->mediumText('options')->nullable();
            $table->integer('cancelled_at')->nullable();
            $table->integer('created_at');
            $table->integer('finished_at')->nullable();
        });
        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });
        echo "CREADA OK.\n";
        $ok[] = 'jobs';
    } catch (Throwable $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
        $errores[] = 'jobs: ' . $e->getMessage();
    }
}

// ──────────────────────────────────────────────
// 3. TABLA: personal_access_tokens (Sanctum)
// ──────────────────────────────────────────────
echo "[3/4] Tabla 'personal_access_tokens' (Sanctum)... ";
if (tableExists('personal_access_tokens')) {
    echo "YA EXISTE, omitida.\n";
} else {
    try {
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
        echo "CREADA OK.\n";
        $ok[] = 'personal_access_tokens';
    } catch (Throwable $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
        $errores[] = 'personal_access_tokens: ' . $e->getMessage();
    }
}

// ──────────────────────────────────────────────
// 4. CAMPOS nuevos en tabla 'usuarios'
// ──────────────────────────────────────────────
echo "[4/4] Campos nuevos en tabla 'usuarios'...\n";

$camposUsuarios = [
    'email'             => fn(Blueprint $t) => $t->string('email')->nullable()->unique(),
    'password'          => fn(Blueprint $t) => $t->string('password')->nullable(),
    'email_verified_at' => fn(Blueprint $t) => $t->timestamp('email_verified_at')->nullable(),
    'primer_acceso'     => fn(Blueprint $t) => $t->boolean('primer_acceso')->default(true),
    'remember_token'    => fn(Blueprint $t) => $t->string('remember_token', 100)->nullable(),
];

foreach ($camposUsuarios as $campo => $definicion) {
    echo "  - Campo '{$campo}'... ";
    if (columnExists('usuarios', $campo)) {
        echo "YA EXISTE, omitido.\n";
    } else {
        try {
            Schema::table('usuarios', function (Blueprint $table) use ($definicion) {
                $definicion($table);
            });
            echo "AGREGADO OK.\n";
            $ok[] = "usuarios.{$campo}";
        } catch (Throwable $e) {
            echo "ERROR: " . $e->getMessage() . "\n";
            $errores[] = "usuarios.{$campo}: " . $e->getMessage();
        }
    }
}

// ──────────────────────────────────────────────
// RESUMEN
// ──────────────────────────────────────────────
echo "\n" . str_repeat('=', 50) . "\n";
echo "RESUMEN:\n";
echo "  Completados: " . count($ok)      . "\n";
echo "  Errores:     " . count($errores) . "\n";

if (!empty($errores)) {
    echo "\nERRORES ENCONTRADOS:\n";
    foreach ($errores as $e) {
        echo "  - {$e}\n";
    }
    exit(1);
} else {
    echo "\n✓ Deploy completado exitosamente.\n";
    echo "  El sistema está listo para usar en producción.\n";
}
