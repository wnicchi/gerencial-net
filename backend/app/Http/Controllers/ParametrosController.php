<?php

namespace App\Http\Controllers;

use App\Support\Registro;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * ParametrosController — Parámetros del sistema.
 *
 * Cuatro grupos (solapas del FoxPro original):
 *   - Parámetros Varios : Costo de Almuerzos (ALM_COSTO) + Base de Datos (parbase)
 *   - Reloj de Personal : ruta de datos del reloj (parreloj)
 *   - Liquidaciones     : historial completo de conexiones a base de datos (parbase)
 *   - Email Cumpleaños  : lista de emails (email_envio_cumpleaños)
 */
class ParametrosController extends Controller
{
    private const TABLA_EMAIL = 'email_envio_cumpleaños';

    // ══════════════════════════════════════════════════════════
    // COSTO DE ALMUERZOS (ALM_COSTO)
    // ══════════════════════════════════════════════════════════

    /** @route GET /api/parametros/almuerzos */
    public function almuerzos(): JsonResponse
    {
        $lista = DB::table('ALM_COSTO')->orderByDesc('ANIO')->orderByDesc('MES')->get()
            ->map(fn ($r) => [
                'mes'     => (int) $r->MES,
                'anio'    => (int) $r->ANIO,
                'importe' => (float) $r->IMPORTE,
            ])->values();
        return response()->json($lista);
    }

    /** @route POST /api/parametros/almuerzos — alta o modificación (upsert por mes+año). */
    public function guardarAlmuerzo(Request $request): JsonResponse
    {
        $d = $request->validate([
            'mes'     => 'required|integer|min:1|max:12',
            'anio'    => 'required|integer|min:1900|max:2100',
            'importe' => 'required|numeric|min:0',
        ]);
        $existe = DB::table('ALM_COSTO')->where('MES', $d['mes'])->where('ANIO', $d['anio'])->exists();
        if ($existe) {
            DB::table('ALM_COSTO')->where('MES', $d['mes'])->where('ANIO', $d['anio'])
                ->update(['IMPORTE' => $d['importe']]);
        } else {
            DB::table('ALM_COSTO')->insert(Registro::completar('ALM_COSTO', [
                'MES' => $d['mes'], 'ANIO' => $d['anio'], 'IMPORTE' => $d['importe'],
            ]));
        }
        return response()->json(['message' => 'Costo de almuerzo guardado correctamente.']);
    }

    /** @route DELETE /api/parametros/almuerzos/{anio}/{mes} */
    public function eliminarAlmuerzo(int $anio, int $mes): JsonResponse
    {
        DB::table('ALM_COSTO')->where('ANIO', $anio)->where('MES', $mes)->delete();
        return response()->json(['message' => 'Costo eliminado.']);
    }

    // ══════════════════════════════════════════════════════════
    // BASE DE DATOS (parbase)  —  EMPRESA: 1=SILCAR, 2=LOGÍSTICA
    // ══════════════════════════════════════════════════════════

    /** @route GET /api/parametros/bases — última ruta vigente de SILCAR y LOGÍSTICA. */
    public function bases(): JsonResponse
    {
        return response()->json([
            'silcar'    => $this->ultimaBase(1),
            'logistica' => $this->ultimaBase(2),
        ]);
    }

    private function ultimaBase(int $empresa): array
    {
        $r = DB::table('parbase')->where('EMPRESA', $empresa)->orderByDesc('CODIGO')->first();
        return [
            'ruta'      => $r ? trim((string) $r->RUTA) : '',
            'usuario'   => $r ? trim((string) $r->USUARIO) : '',
            'fechor'    => $r ? (string) $r->FECHOR : '',
        ];
    }

    /** @route POST /api/parametros/bases — registra una nueva ruta (append con responsable y fecha). */
    public function guardarBase(Request $request): JsonResponse
    {
        $d = $request->validate([
            'empresa' => 'required|integer|in:1,2',
            'ruta'    => 'required|string|max:200',
        ]);
        $cod = (int) DB::table('parbase')->max('CODIGO') + 1;
        DB::table('parbase')->insert(Registro::completar('parbase', [
            'CODIGO'  => $cod,
            'RUTA'    => trim($d['ruta']),
            'USUARIO' => trim((string) ($request->user()->NOMBRE ?? '')),
            'FECHOR'  => now(),
            'EMPRESA' => $d['empresa'],
        ]));
        return response()->json(['message' => 'Ruta de base de datos registrada correctamente.'], 201);
    }

    /** @route GET /api/parametros/bases/historial — historial completo (solapa Liquidaciones). */
    public function basesHistorial(): JsonResponse
    {
        $lista = DB::table('parbase')->orderByDesc('CODIGO')->get()
            ->map(fn ($r) => [
                'codigo'  => (int) $r->CODIGO,
                'empresa' => (int) $r->EMPRESA,
                'ruta'    => trim((string) $r->RUTA),
                'usuario' => trim((string) $r->USUARIO),
                'fechor'  => (string) $r->FECHOR,
            ])->values();
        return response()->json($lista);
    }

    // ══════════════════════════════════════════════════════════
    // RELOJ DE PERSONAL (parreloj)
    // ══════════════════════════════════════════════════════════

    /** @route GET /api/parametros/reloj — ruta vigente de datos del reloj. */
    public function reloj(): JsonResponse
    {
        $r = DB::table('parreloj')->orderByDesc('CODIGO')->first();
        return response()->json([
            'ruta'    => $r ? trim((string) $r->RUTA) : '',
            'usuario' => $r ? trim((string) $r->USUARIO) : '',
            'fechor'  => $r ? (string) $r->FECHOR : '',
        ]);
    }

    /** @route POST /api/parametros/reloj — actualiza la ruta de datos del reloj. */
    public function guardarReloj(Request $request): JsonResponse
    {
        $d = $request->validate(['ruta' => 'required|string|max:200']);
        $r = DB::table('parreloj')->orderByDesc('CODIGO')->first();
        $datos = [
            'RUTA'    => trim($d['ruta']),
            'USUARIO' => trim((string) ($request->user()->NOMBRE ?? '')),
            'FECHOR'  => now(),
        ];
        if ($r) {
            DB::table('parreloj')->where('CODIGO', $r->CODIGO)->update($datos);
        } else {
            DB::table('parreloj')->insert(Registro::completar('parreloj', array_merge(['CODIGO' => 1, 'EMPRESA' => 0], $datos)));
        }
        return response()->json(['message' => 'Ruta del reloj guardada correctamente.']);
    }

    // ══════════════════════════════════════════════════════════
    // EMAIL CUMPLEAÑOS (email_envio_cumpleaños)
    // ══════════════════════════════════════════════════════════

    /** @route GET /api/parametros/emails */
    public function emails(): JsonResponse
    {
        $lista = DB::table(self::TABLA_EMAIL)->orderBy('EMAIL')->pluck('EMAIL')
            ->map(fn ($e) => trim((string) $e))->filter()->values();
        return response()->json($lista);
    }

    /** @route POST /api/parametros/emails — agrega un email (sin duplicar). */
    public function agregarEmail(Request $request): JsonResponse
    {
        $d = $request->validate(['email' => 'required|email|max:100']);
        $email = strtolower(trim($d['email']));
        $existe = DB::table(self::TABLA_EMAIL)->whereRaw('LOWER(RTRIM(EMAIL)) = ?', [$email])->exists();
        if ($existe) {
            return response()->json(['message' => 'El email ya está en la lista.'], 422);
        }
        DB::table(self::TABLA_EMAIL)->insert(['EMAIL' => $email]);
        return response()->json(['message' => 'Email agregado correctamente.'], 201);
    }

    /** @route POST /api/parametros/emails/eliminar — elimina los emails indicados (o todos si vacío con flag). */
    public function eliminarEmails(Request $request): JsonResponse
    {
        $d = $request->validate([
            'emails'  => 'nullable|array',
            'emails.*'=> 'string|max:100',
            'todos'   => 'nullable|boolean',
        ]);
        if ($request->boolean('todos')) {
            DB::table(self::TABLA_EMAIL)->delete();
            return response()->json(['message' => 'Se eliminaron todos los emails.']);
        }
        $emails = array_map(fn ($e) => strtolower(trim((string) $e)), $d['emails'] ?? []);
        if (!$emails) {
            return response()->json(['message' => 'No se indicó ningún email a eliminar.'], 422);
        }
        foreach ($emails as $e) {
            DB::table(self::TABLA_EMAIL)->whereRaw('LOWER(RTRIM(EMAIL)) = ?', [$e])->delete();
        }
        return response()->json(['message' => 'Emails eliminados correctamente.']);
    }
}
