<?php
// diagnostico_hed.php — Reproduce el "confirmar" de Horas Extras Diarias por CLI,
// sin pasar por IIS, para ver el error real en pantalla.
// Uso (en el servidor):  php C:\inetpub\rrhh-net\backend\scripts\diagnostico_hed.php [fecha] [empresa] [contratista]
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;

$fecha = $argv[1] ?? '2026-07-13';
$empresa = (int) ($argv[2] ?? 2);
$contratista = (int) ($argv[3] ?? 2);
echo "base: " . DB::connection()->getDatabaseName() . " | fecha $fecha | empresa $empresa | contratista $contratista\n";

$c = new App\Http\Controllers\HoraExtraDiariaController();
try {
    $reqIdx = Illuminate\Http\Request::create('/x', 'GET', ['empresa' => $empresa, 'contratista' => $contratista, 'fecha' => $fecha]);
    $rows = json_decode($c->index($reqIdx)->getContent(), true)['rows'] ?? [];
    echo "filas de la planilla: " . count($rows) . "\n";
    if (!$rows) { echo "No hay filas para confirmar: nada que probar.\n"; exit; }

    $payload = ['fecha' => $fecha, 'rows' => array_map(fn ($r) => [
        'codigo' => $r['codigo'], 'nombre' => $r['nombre'],
        'reloj_entrada' => $r['reloj_entrada'], 'reloj_salida' => $r['reloj_salida'],
        'm_calculada' => $r['m_calculada'], 'm_est50' => $r['m_est50'],
        'm_est100' => $r['m_est100'], 'm_estnoc50' => $r['m_estnoc50'],
    ], $rows)];
    $reqCon = Illuminate\Http\Request::create('/x', 'POST', $payload);
    $u = new stdClass(); $u->NOMBRE = 'DIAGNOSTICO';
    $reqCon->setUserResolver(fn () => $u);
    $res = $c->confirmar($reqCon);
    echo "RESULTADO status " . $res->getStatusCode() . ": " . $res->getContent() . "\n";
} catch (\Throwable $e) {
    echo "\nERROR " . get_class($e) . ":\n" . $e->getMessage() . "\n\n" . $e->getTraceAsString() . "\n";
}
