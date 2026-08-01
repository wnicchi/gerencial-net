<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * ClienteController — consulta de clientes contra la base de gestión
 * (conexión 'gestion' = sqlSILCAR/sqlLOGIST). Equivale a la rutina FoxPro
 * LEE_CLIENTE() y a la búsqueda por nombre (clientes_buscar_x_nombre.scx).
 *
 * Sólo lectura: la tabla CLIENTES no se modifica desde RRHH.
 */
class ClienteController extends Controller
{
    /** @route GET /api/clientes/buscar?nombre= — búsqueda por nombre (lupa). */
    public function buscar(Request $request): JsonResponse
    {
        $nombre = mb_strtoupper(trim((string) $request->query('nombre', '')));
        if (mb_strlen($nombre) < 2) {
            return response()->json([]);
        }
        $rows = DB::connection('gestion')->table('CLIENTES')
            ->where('CLI_NOM', 'like', "%{$nombre}%")
            ->orderBy('CLI_NOM')
            ->limit(200)
            ->get(['CLI_COD', 'CLI_NOM', 'CLI_BLO']);

        return response()->json($rows->map(fn ($c) => [
            'cod'       => (int) $c->CLI_COD,
            'nombre'    => trim((string) $c->CLI_NOM),
            'bloqueado' => trim((string) $c->CLI_BLO) === 'S',
        ])->values());
    }

    /** @route GET /api/clientes/{cod} — datos de un cliente (LEE_CLIENTE). */
    public function show(int $cod): JsonResponse
    {
        $c = DB::connection('gestion')->table('CLIENTES')->where('CLI_COD', $cod)
            ->first(['CLI_COD', 'CLI_NOM', 'CLI_DOM', 'CLI_MAI', 'CLI_TEL', 'CLI_LOD', 'CLI_CON', 'CLI_BLO']);
        if (!$c) {
            return response()->json(['message' => 'Código de cliente inexistente.'], 404);
        }
        return response()->json([
            'cod'       => (int) $c->CLI_COD,
            'nombre'    => trim((string) $c->CLI_NOM),
            'domicilio' => trim((string) $c->CLI_DOM),
            'email'     => mb_strtolower(trim((string) $c->CLI_MAI)),
            'telefono'  => trim((string) $c->CLI_TEL),
            'localidad' => trim((string) $c->CLI_LOD),
            'contacto'  => trim((string) $c->CLI_CON),
            'bloqueado' => trim((string) $c->CLI_BLO) === 'S',
        ]);
    }
}
