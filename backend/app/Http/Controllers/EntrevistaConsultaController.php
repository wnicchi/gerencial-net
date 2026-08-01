<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * EntrevistaConsultaController — Consulta General de Entrevistas
 * (entrevistas_consulta_general.scx).
 *
 * Consulta de sólo lectura que une los entrevistados de AMBAS empresas: la base
 * local (conexión rrhh) + la base RRHH de la otra empresa (conexión rrhh_otra,
 * configurada por .env; si no está configurada o falla la red, se muestra sólo la
 * local). Se marca el origen, se ordena por nombre+fecha y se deduplica por
 * nombre+fecha (réplica del FoxPro).
 */
class EntrevistaConsultaController extends Controller
{
    /** @route GET /api/entrevistas-consulta — todos los entrevistados de ambas empresas. */
    public function index(): JsonResponse
    {
        // Etiquetas de origen según la empresa local (autoelevadores=ROSARIO, logística=ALVEAR).
        $esSilcar   = config('rrhh.empresa') !== 'logist';
        $origenLocal = $esSilcar ? 'RRHH ROSARIO' : 'RRHH ALVEAR';
        $origenOtra  = $esSilcar ? 'RRHH ALVEAR' : 'RRHH ROSARIO';

        $filas = $this->leer(null, $origenLocal); // null = conexión por defecto (RRHH local)

        $otraOk = true;
        if (trim((string) config('database.connections.rrhh_otra.host')) !== '') {
            try {
                $filas = array_merge($filas, $this->leer('rrhh_otra', $origenOtra));
            } catch (\Throwable $e) {
                $otraOk = false;
                Log::warning('[entrevistasConsulta] otra empresa: ' . $e->getMessage());
            }
        } else {
            $otraOk = null; // no configurada
        }

        // Ordenar por nombre, fecha y deduplicar por nombre+fecha.
        usort($filas, function ($a, $b) {
            return [$a['nombre'], $a['fecha']] <=> [$b['nombre'], $b['fecha']];
        });
        $vistos = [];
        $out = [];
        foreach ($filas as $f) {
            $k = $f['nombre'] . '|' . $f['fecha'];
            if (isset($vistos[$k])) {
                continue;
            }
            $vistos[$k] = true;
            $out[] = $f;
        }

        return response()->json([
            'entrevistas' => $out,
            'otra_empresa' => $otraOk, // true=ok, false=error de conexión, null=no configurada
        ]);
    }

    /** Lee los entrevistados de una conexión y les asigna el origen. */
    private function leer(?string $conexion, string $origen): array
    {
        return DB::connection($conexion)->table('entrevistas')
            ->orderBy('ETV_NOM')->orderBy('ETV_FEC')->get()
            ->map(fn ($e) => [
                'origen'    => $origen,
                'cod'       => (int) $e->ETV_COD,
                'nombre'    => trim((string) $e->ETV_NOM),
                'domicilio' => trim((string) $e->ETV_DOM),
                'telefono'  => trim((string) $e->ETV_TEL),
                'fecha'     => ($e->ETV_FEC && substr((string) $e->ETV_FEC, 0, 4) !== '1900') ? substr((string) $e->ETV_FEC, 0, 10) : '',
                'sector'    => trim((string) $e->ETV_SED),
                'subsector' => trim((string) $e->ETV_SUD),
                'formacion' => trim((string) $e->ETV_FOR_ACA),
                'lugar'     => trim((string) $e->ETV_LUG),
                'email'     => mb_strtolower(trim((string) $e->ETV_EMA)),
                'tipo_doc'  => trim((string) $e->ETV_TDO),
                'numero_doc' => (int) $e->ETV_DOC,
                'nota'      => trim((string) $e->ETV_NOT),
            ])->all();
    }
}
