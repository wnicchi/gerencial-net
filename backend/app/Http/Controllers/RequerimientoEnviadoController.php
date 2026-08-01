<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * RequerimientoEnviadoController — Requerimientos Emails Enviados
 * (requerimientos_email_enviados.scx).
 *
 * Histórico de correos de requerimientos enviados a clientes (req_enviados) con sus
 * adjuntos (req_enviados_adjuntos). Permite reenviar: regenera el .eml para el
 * cliente con sus datos actuales (emails y documentos vigentes) reutilizando la
 * lógica de RequerimientoInformeController, y registra un nuevo envío.
 */
class RequerimientoEnviadoController extends Controller
{
    /** @route GET /api/requerimientos-enviados — histórico de envíos. */
    public function index(): JsonResponse
    {
        $rows = DB::table('req_enviados')->orderByDesc('RCL_ENVIADO')->get();
        return response()->json($rows->map(function ($r) {
            $emails = [];
            foreach (range(1, 10) as $i) {
                $e = mb_strtolower(trim((string) $r->{"RCL_EM{$i}"}));
                if ($e !== '') {
                    $emails[] = $e;
                }
            }
            return [
                'unico'    => (int) $r->unico,
                'cliente'  => (int) $r->RCL_CLI,
                'nombre'   => trim((string) $r->RCL_CLD),
                'enviado'  => $r->RCL_ENVIADO ? substr((string) $r->RCL_ENVIADO, 0, 19) : '',
                'emails'   => implode('; ', $emails),
            ];
        })->values());
    }

    /** @route GET /api/requerimientos-enviados/{unico}/adjuntos — adjuntos de un envío histórico. */
    public function adjuntos(int $unico): JsonResponse
    {
        if (!DB::table('req_enviados')->where('unico', $unico)->exists()) {
            return response()->json(['message' => 'Envío no encontrado.'], 404);
        }
        return response()->json([
            'adjuntos' => DB::table('req_enviados_adjuntos')->where('unico', $unico)->orderBy('documento')
                ->pluck('documento')->map(fn ($d) => trim((string) $d))->values(),
        ]);
    }

    /** @route POST /api/requerimientos-enviados/{unico}/reenviar — regenera el .eml del cliente. */
    public function reenviar(int $unico)
    {
        $row = DB::table('req_enviados')->where('unico', $unico)->first(['RCL_CLI']);
        if (!$row) {
            return response()->json(['message' => 'Envío no encontrado.'], 404);
        }
        // Regenera con datos actuales del cliente y registra un nuevo envío.
        return (new RequerimientoInformeController())->email((int) $row->RCL_CLI);
    }
}
