<?php

namespace App\Http\Controllers;

use App\Services\BibliotecaDigitalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * RequerimientoClienteController — Requerimientos por Cliente (requerimientos_clientes.scx).
 *
 * Asigna a un cliente (de la base de gestión) qué requerimientos de acceso debe
 * cumplir, junto con observaciones, contactos y hasta 10 emails, en la tabla
 * req_cli (clave RCL_CLI + RCL_REQ). Además administra la documentación exclusiva
 * del cliente en la tabla `documentos` (DOC_TIP='K', DOC_REF = código de cliente),
 * con archivos físicos en la biblioteca digital (proceso DOCUMENTACION).
 */
class RequerimientoClienteController extends Controller
{
    private const EXT_BLOQUEADAS = ['EXE', 'BAT', 'DLL', 'ZIP', 'RAR', 'CMD', 'CAB'];

    /** @route GET /api/requerimientos-clientes/init — requerimientos + tipos de documento. */
    public function init(): JsonResponse
    {
        return response()->json([
            'requerimientos' => DB::table('requerimientos')->orderBy('REQ_DES')->get()
                ->map(fn ($r) => [
                    'cod'         => (int) $r->REQ_COD,
                    'descripcion' => trim((string) $r->REQ_DES),
                    'dias'        => (int) $r->REQ_DIA,
                    'comun'       => (bool) $r->REQ_COM,
                ])->values(),
            'tipos' => DB::table('tipo_doc')->where('TDO_TIP', 'C')->orderBy('TDO_DES')->get(['TDO_COD', 'TDO_DES'])
                ->map(fn ($t) => ['cod' => trim((string) $t->TDO_COD), 'nombre' => trim((string) $t->TDO_DES)])->values(),
        ]);
    }

    /** @route GET /api/requerimientos-clientes/cliente/{cod} — cliente + asignaciones + documentos. */
    public function cliente(int $cod): JsonResponse
    {
        $cli = DB::connection('gestion')->table('CLIENTES')->where('CLI_COD', $cod)
            ->first(['CLI_COD', 'CLI_NOM', 'CLI_DOM', 'CLI_MAI', 'CLI_TEL', 'CLI_LOD', 'CLI_BLO']);
        if (!$cli) {
            return response()->json(['message' => 'Código de cliente inexistente.'], 404);
        }

        // Asignaciones del cliente en req_cli (clave RCL_CLI + RCL_REQ).
        $asignados = DB::table('req_cli')->where('RCL_CLI', $cod)->get()->keyBy('RCL_REQ');
        $cualquiera = $asignados->first(); // obs/contactos/emails son por cliente (iguales en todas las filas)

        $reqs = DB::table('requerimientos')->orderBy('REQ_DES')->get()->map(function ($r) use ($asignados) {
            $a = $asignados->get((int) $r->REQ_COD);
            $ult = $a && $a->RCL_FUL && substr((string) $a->RCL_FUL, 0, 4) !== '1900' ? substr((string) $a->RCL_FUL, 0, 10) : '';
            return [
                'cod'         => (int) $r->REQ_COD,
                'descripcion' => trim((string) $r->REQ_DES),
                'elegir'      => $a !== null,
                'dias'        => $a ? (int) $a->RCL_DIA : (int) $r->REQ_DIA,
                'ult_envio'   => $ult,
                'comun'       => (bool) $r->REQ_COM,
            ];
        })->values();

        return response()->json([
            'cliente' => [
                'cod'       => (int) $cli->CLI_COD,
                'nombre'    => trim((string) $cli->CLI_NOM),
                'domicilio' => trim((string) $cli->CLI_DOM),
                'email'     => mb_strtolower(trim((string) $cli->CLI_MAI)),
                'telefono'  => trim((string) $cli->CLI_TEL),
                'localidad' => trim((string) $cli->CLI_LOD),
                'bloqueado' => trim((string) $cli->CLI_BLO) === 'S',
            ],
            'datos' => [
                'observaciones' => $cualquiera ? trim((string) $cualquiera->RCL_OBS) : '',
                'contacto1'     => $cualquiera ? trim((string) $cualquiera->RCL_CO1) : '',
                'telefono1'     => $cualquiera ? trim((string) $cualquiera->RCL_TE1) : '',
                'contacto2'     => $cualquiera ? trim((string) $cualquiera->RCL_CO2) : '',
                'telefono2'     => $cualquiera ? trim((string) $cualquiera->RCL_TE2) : '',
                'emails'        => $this->emailsDe($cualquiera),
            ],
            'requerimientos' => $reqs,
            'documentos'     => $this->docs('documentos', $cod),
        ]);
    }

    /** @route POST /api/requerimientos-clientes/cliente/{cod} — confirma requerimientos de acceso. */
    public function guardar(Request $request, int $cod): JsonResponse
    {
        $d = $request->validate([
            'nombre'              => 'required|string|max:100',
            'observaciones'       => 'nullable|string|max:200',
            'contacto1'           => 'nullable|string|max:50',
            'telefono1'           => 'nullable|string|max:50',
            'contacto2'           => 'nullable|string|max:50',
            'telefono2'           => 'nullable|string|max:50',
            'emails'              => 'array',
            'emails.*'            => 'nullable|string|max:100',
            'requerimientos'      => 'required|array',
            'requerimientos.*'    => 'integer', // códigos de requerimiento elegidos
        ]);
        $elegidos = array_map('intval', $d['requerimientos']);
        $emails = array_pad(array_slice($d['emails'] ?? [], 0, 10), 10, '');

        $base = [
            'RCL_CLD' => mb_substr(mb_strtoupper(trim($d['nombre'])), 0, 100),
            'RCL_OBS' => mb_substr(mb_strtoupper(trim((string) ($d['observaciones'] ?? ''))), 0, 200),
            'RCL_CO1' => mb_substr(mb_strtoupper(trim((string) ($d['contacto1'] ?? ''))), 0, 50),
            'RCL_TE1' => mb_substr(mb_strtoupper(trim((string) ($d['telefono1'] ?? ''))), 0, 50),
            'RCL_CO2' => mb_substr(mb_strtoupper(trim((string) ($d['contacto2'] ?? ''))), 0, 50),
            'RCL_TE2' => mb_substr(mb_strtoupper(trim((string) ($d['telefono2'] ?? ''))), 0, 50),
        ];
        foreach (range(1, 10) as $i) {
            $base["RCL_EM{$i}"] = mb_substr(mb_strtoupper(trim((string) ($emails[$i - 1] ?? ''))), 0, 100);
        }

        $reqs = DB::table('requerimientos')->get(['REQ_COD', 'REQ_DES', 'REQ_DIA'])->keyBy('REQ_COD');

        DB::transaction(function () use ($cod, $elegidos, $base, $reqs) {
            foreach ($reqs as $r) {
                $rcod = (int) $r->REQ_COD;
                $existe = DB::table('req_cli')->where('RCL_CLI', $cod)->where('RCL_REQ', $rcod)->exists();
                if (!in_array($rcod, $elegidos, true)) {
                    if ($existe) {
                        DB::table('req_cli')->where('RCL_CLI', $cod)->where('RCL_REQ', $rcod)->delete();
                    }
                    continue;
                }
                $fila = $base + [
                    'RCL_RED' => mb_substr(mb_strtoupper(trim((string) $r->REQ_DES)), 0, 100),
                    'RCL_DIA' => (int) $r->REQ_DIA,
                ];
                if ($existe) {
                    DB::table('req_cli')->where('RCL_CLI', $cod)->where('RCL_REQ', $rcod)->update($fila);
                } else {
                    DB::table('req_cli')->insert($fila + ['RCL_CLI' => $cod, 'RCL_REQ' => $rcod, 'RCL_FUL' => '1900-01-01']);
                }
            }
        });

        return response()->json(['ok' => true]);
    }

    /** @route POST /api/requerimientos-clientes/cliente/{cod}/documento — adjunta documento exclusivo del cliente. */
    public function subirDocumento(Request $request, int $cod): JsonResponse
    {
        $d = $request->validate([
            'tipo'    => 'required|string|max:2',
            'archivo' => 'required|file|max:51200',
        ], ['tipo.required' => 'Debe ingresar el tipo de documento.', 'archivo.required' => 'Debe seleccionar el documento.']);

        $tipo = mb_strtoupper(trim($d['tipo']));
        $file = $request->file('archivo');
        $ext  = mb_strtoupper((string) $file->getClientOriginalExtension());
        if ($ext === '' || in_array($ext, self::EXT_BLOQUEADAS, true)) {
            return response()->json(['message' => 'Extensión de archivo no válida.'], 422);
        }
        $tdd      = trim((string) DB::table('tipo_doc')->where('TDO_COD', $tipo)->value('TDO_DES'));
        $nombre   = mb_strtoupper(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        $tam      = (int) $file->getSize();
        $ahora    = now();
        $usuario  = mb_substr(trim((string) ($request->user()->NOMBRE ?? 'RRHH.NET')), 0, 20);
        $terminal = mb_substr(gethostname() ?: 'RRHH.NET', 0, 20);

        $docOrd = (int) DB::table('documentos')->max('DOC_ORD') + 1;
        $docNro = (int) DB::table('documentos')->where('DOC_TDO', $tipo)->max('DOC_NRO') + 1;

        $unico = DB::table('documentos')->insertGetId([
            'DOC_ORD' => $docOrd, 'DOC_TDO' => $tipo, 'DOC_NRO' => $docNro, 'DOC_TIP' => 'K', 'DOC_REF' => $cod,
            'DOC_DET' => mb_substr('Requerimiento Cliente ' . $cod, 0, 60), 'DOC_UBI' => 'en SQL DOCUMENTOS DIGITALES',
            'DOC_TDD' => mb_substr($tdd, 0, 30), 'DOC_FUL' => mb_substr((string) $file->getClientOriginalName(), 0, 120),
            'DOC_DIR' => '', 'DOC_NOM' => mb_substr($nombre, 0, 120), 'DOC_EXT' => mb_substr($ext, 0, 5),
            'DOC_CRE' => $ahora->format('d/m/Y H:i'), 'DOC_TAM' => $tam, 'DOC_KB' => (int) round($tam / 1024),
            'DOC_OBS' => '', 'DOC_USU' => $usuario, 'DOC_TER' => $terminal, 'DOC_GRA' => $ahora->format('d/m/y H:i'),
        ], 'UNICO');

        try {
            (new BibliotecaDigitalService())->archivoDigitalGuardar(
                config('rrhh.docs_sistema'), 'DOCUMENTACION', $this->referencia($tipo, $docNro, $cod, $ext), $ext,
                file_get_contents($file->getRealPath()), $usuario
            );
        } catch (\Throwable $e) {
            DB::table('documentos')->where('UNICO', $unico)->delete();
            Log::error("[reqCliAddDoc] cli {$cod}: " . $e->getMessage());
            return response()->json(['message' => 'No se pudo guardar el archivo en la biblioteca digital.'], 500);
        }
        return response()->json(['ok' => true, 'documentos' => $this->docs('documentos', $cod)]);
    }

    /** @route GET /api/requerimientos-clientes/documento/{id}/ver — visualiza un documento del cliente. */
    public function verDocumento(int $id)
    {
        $doc = DB::table('documentos')->where('UNICO', $id)->where('DOC_TIP', 'K')->first();
        if (!$doc) {
            return response()->json(['error' => 'Documento no encontrado'], 404);
        }
        $ref  = $this->referencia(trim((string) $doc->DOC_TDO), (int) $doc->DOC_NRO, (int) $doc->DOC_REF, mb_strtoupper(trim((string) $doc->DOC_EXT)));
        $resp = (new BibliotecaDigitalService())->archivoDigitalVisualizar(config('rrhh.docs_sistema'), 'DOCUMENTACION', $ref);
        return $resp ?? response()->json(['error' => 'El archivo no está en la biblioteca digital'], 404);
    }

    /** @route DELETE /api/requerimientos-clientes/documento/{id} — mueve a historial y borra el activo. */
    public function eliminarDocumento(Request $request, int $id): JsonResponse
    {
        $doc = DB::table('documentos')->where('UNICO', $id)->where('DOC_TIP', 'K')->first();
        if (!$doc) {
            return response()->json(['message' => 'Documento no encontrado.'], 404);
        }
        $usuario = mb_substr(trim((string) ($request->user()->NOMBRE ?? 'RRHH.NET')), 0, 20);

        $fila = (array) $doc;
        unset($fila['UNICO']);
        $fila['DOC_FEL'] = now()->format('Y-m-d');
        $fila['DOC_UEL'] = $usuario;
        DB::table('hdocumentos')->insert($fila);

        try { (new BibliotecaDigitalService())->archivoDigitalEliminar(config('rrhh.docs_sistema'), 'DOCUMENTACION', $this->referencia(trim((string) $doc->DOC_TDO), (int) $doc->DOC_NRO, (int) $doc->DOC_REF, mb_strtoupper(trim((string) $doc->DOC_EXT)))); }
        catch (\Throwable $e) { Log::warning("[reqCliDelDoc] {$id}: " . $e->getMessage()); }

        DB::table('documentos')->where('UNICO', $id)->delete();
        return response()->json(['ok' => true, 'documentos' => $this->docs('documentos', (int) $doc->DOC_REF)]);
    }

    /** Documentos del cliente (DOC_TIP='K', DOC_REF=cliente). */
    private function docs(string $tabla, int $cod): array
    {
        return DB::table($tabla)->where('DOC_TIP', 'K')->where('DOC_REF', $cod)->orderByDesc('DOC_ORD')->get()
            ->map(fn ($r) => [
                'id' => (int) $r->UNICO, 'nro' => (int) $r->DOC_ORD, 'tipo' => trim((string) $r->DOC_TDO),
                'detalle' => trim((string) $r->DOC_TDD), 'nombre' => trim((string) $r->DOC_NOM),
                'ext' => trim((string) $r->DOC_EXT), 'creado' => trim((string) $r->DOC_CRE),
                'observaciones' => trim((string) $r->DOC_OBS), 'usuario' => trim((string) $r->DOC_USU),
            ])->values()->all();
    }

    /** Los 10 emails de una fila req_cli (en minúscula). */
    private function emailsDe(?object $row): array
    {
        $out = [];
        foreach (range(1, 10) as $i) {
            $out[] = $row ? mb_strtolower(trim((string) $row->{"RCL_EM{$i}"})) : '';
        }
        return $out;
    }

    /** Identificación del archivo en la biblioteca: TDO + NRO(6) + REF(6) + .EXT */
    private function referencia(string $tipo, int $nro, int $ref, string $ext): string
    {
        return $tipo . str_pad((string) $nro, 6, '0', STR_PAD_LEFT)
            . str_pad((string) $ref, 6, '0', STR_PAD_LEFT) . '.' . $ext;
    }
}
