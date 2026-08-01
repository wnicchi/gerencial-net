<?php

namespace App\Http\Controllers;

use App\Support\Registro;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * AgendaTelefonoController — Agenda de Teléfonos (tabla telefonos).
 *
 * Lista simple de contactos internos: detalle (área/persona), interno, celular y otros.
 * No tiene clave incremental; al confirmar se reemplaza toda la tabla (borra todo y reinserta).
 */
class AgendaTelefonoController extends Controller
{
    /** @route GET /api/telefonos */
    public function index(): JsonResponse
    {
        $rows = DB::table('telefonos')->orderBy('DETALLE')->get()->map(fn ($t) => [
            'detalle'  => trim((string) $t->DETALLE),
            'interno'  => trim((string) $t->TEL_INTERNO),
            'celular'  => trim((string) $t->TEL_CELULAR),
            'otros'    => trim((string) $t->OTROS),
        ]);
        return response()->json($rows);
    }

    /**
     * Confirma todos los cambios: reemplaza la tabla completa.
     * @route POST /api/telefonos  body: { rows:[{detalle, interno, celular, otros}] }
     */
    public function guardar(Request $request): JsonResponse
    {
        $request->validate(['rows' => 'present|array', 'rows.*.detalle' => 'nullable|string']);
        $rows = $request->input('rows', []);

        DB::transaction(function () use ($rows) {
            DB::table('telefonos')->delete();
            foreach ($rows as $r) {
                $detalle = mb_strtoupper(trim((string) ($r['detalle'] ?? '')));
                if ($detalle === '') continue;
                DB::table('telefonos')->insert(Registro::completar('telefonos', [
                    'DETALLE'     => $detalle,
                    'TEL_INTERNO' => mb_strtoupper(trim((string) ($r['interno'] ?? ''))),
                    'TEL_CELULAR' => mb_strtoupper(trim((string) ($r['celular'] ?? ''))),
                    'OTROS'       => mb_strtoupper(trim((string) ($r['otros'] ?? ''))),
                ]));
            }
        });

        return response()->json(['ok' => true, 'total' => count($rows)]);
    }
}
