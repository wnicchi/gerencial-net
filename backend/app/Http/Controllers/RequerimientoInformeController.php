<?php

namespace App\Http\Controllers;

use App\Services\BibliotecaDigitalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mime\Email;

/**
 * RequerimientoInformeController — Requerimientos Informes (requerimientos_informes.scx).
 *
 * Lista los clientes que tienen requerimientos asignados, arma el correo con los
 * documentos a enviar (requerimientos comunes con doc DOC_TIP='C' + documentación
 * exclusiva del cliente DOC_TIP='K') y genera un archivo .eml (X-Unsent:1) que el
 * usuario abre en su cliente de correo local para enviarlo. Registra cada envío en
 * req_enviados / req_enviados_adjuntos (histórico) y actualiza RCL_FUL.
 *
 * No usa SMTP de servidor: el correo sale por la cuenta del operador (ver memoria
 * "Email sin SMTP").
 */
class RequerimientoInformeController extends Controller
{
    private const CC_FIJO  = 'aperez@gruposilcar.com';
    private const ASUNTO   = 'Adjunto Requerimientos de Accesos a vuestra empresa';
    // From cosmético: con X-Unsent:1 Outlook ignora este remitente y usa la cuenta del operador.
    private const FROM_PLACEHOLDER = 'requerimientos@gruposilcar.com';

    /** @route GET /api/requerimientos-informes/clientes — clientes con requerimientos asignados. */
    public function clientes(): JsonResponse
    {
        // Una fila por cliente (los contactos/emails se repiten en req_cli).
        $rows = DB::table('req_cli')->select('RCL_CLI', 'RCL_CLD', 'RCL_CO1', 'RCL_TE1', 'RCL_CO2', 'RCL_TE2')
            ->orderBy('RCL_CLD')->get()->unique('RCL_CLI')->values();

        return response()->json($rows->map(fn ($r) => [
            'cod'       => (int) $r->RCL_CLI,
            'nombre'    => trim((string) $r->RCL_CLD),
            'contacto1' => trim((string) $r->RCL_CO1),
            'telefono1' => trim((string) $r->RCL_TE1),
            'contacto2' => trim((string) $r->RCL_CO2),
            'telefono2' => trim((string) $r->RCL_TE2),
        ])->values());
    }

    /** @route GET /api/requerimientos-informes/cliente/{cod}/preview — adjuntos y emails a enviar. */
    public function preview(int $cod): JsonResponse
    {
        $docs = $this->documentosAEnviar($cod);
        return response()->json([
            'adjuntos' => array_map(fn ($d) => ['nombre' => $d['nombre'], 'id' => $d['id']], $docs),
            'emails'   => $this->emailsCliente($cod),
        ]);
    }

    /** @route GET /api/requerimientos-informes/documento/{id}/ver — visualiza un documento a adjuntar (C o K). */
    public function verDocumento(int $id)
    {
        $doc = DB::table('documentos')->where('UNICO', $id)->whereIn('DOC_TIP', ['C', 'K'])->first();
        if (!$doc) {
            return response()->json(['error' => 'Documento no encontrado'], 404);
        }
        $ref = trim((string) $doc->DOC_TDO)
            . str_pad((string) (int) $doc->DOC_NRO, 6, '0', STR_PAD_LEFT)
            . str_pad((string) (int) $doc->DOC_REF, 6, '0', STR_PAD_LEFT)
            . '.' . mb_strtoupper(trim((string) $doc->DOC_EXT));
        $resp = (new BibliotecaDigitalService())->archivoDigitalVisualizar(config('rrhh.docs_sistema'), 'DOCUMENTACION', $ref);
        return $resp ?? response()->json(['error' => 'El archivo no está en la biblioteca digital'], 404);
    }

    /** @route POST /api/requerimientos-informes/cliente/{cod}/email — genera el .eml y registra el envío. */
    public function email(int $cod)
    {
        $cli = DB::table('req_cli')->where('RCL_CLI', $cod)->first(['RCL_CLD']);
        if (!$cli) {
            return response()->json(['message' => 'El cliente no tiene requerimientos asignados.'], 404);
        }
        $emails = $this->emailsCliente($cod);
        if (!count($emails)) {
            return response()->json(['message' => 'El cliente no posee emails donde enviar los requerimientos.'], 422);
        }
        $docs  = $this->documentosAEnviar($cod);
        $svc   = new BibliotecaDigitalService();
        $nombre = trim((string) $cli->RCL_CLD);
        $cuerpo = $this->cuerpo($nombre, $docs);

        $mail = (new Email())
            ->from(self::FROM_PLACEHOLDER)
            ->subject(self::ASUNTO)
            ->text($cuerpo);
        foreach ($emails as $e) {
            $mail->addTo($e);
        }
        $mail->addCc(self::CC_FIJO);

        $adjuntados = [];
        foreach ($docs as $d) {
            $rec = $svc->archivoDigitalRecuperar(config('rrhh.docs_sistema'), 'DOCUMENTACION', $d['referencia']);
            if ($rec === null) {
                Log::warning("[reqInforme] cli {$cod}: no se recuperó {$d['referencia']}");
                continue;
            }
            $mail->attach($rec[0], $d['nombre']);
            $adjuntados[] = $d['nombre'];
        }

        // X-Unsent:1 → Outlook lo abre en modo redacción (listo para enviar).
        $mail->getHeaders()->addTextHeader('X-Unsent', '1');
        $eml = $mail->toString();

        // Registrar en el histórico y actualizar la fecha de último envío.
        $this->registrar($cod, $nombre, $cuerpo, $emails, $adjuntados);

        $archivo = 'Requerimientos_' . preg_replace('/[^A-Za-z0-9]+/', '_', $nombre) . '.eml';
        return response($eml, 200)
            ->header('Content-Type', 'message/rfc822')
            ->header('Content-Disposition', 'attachment; filename="' . $archivo . '"');
    }

    /** @route GET /api/requerimientos-informes/general — datos para el PDF INFORME GENERAL. */
    public function general(): JsonResponse
    {
        $rows = DB::table('req_cli as rc')
            ->join('requerimientos as r', 'rc.RCL_REQ', '=', 'r.REQ_COD')
            ->orderBy('rc.RCL_CLD')->orderBy('rc.RCL_RED')
            ->get(['rc.RCL_CLI', 'rc.RCL_CLD', 'rc.RCL_REQ', 'rc.RCL_RED', 'rc.RCL_DIA', 'rc.RCL_FUL',
                'rc.RCL_OBS', 'rc.RCL_CO1', 'rc.RCL_TE1', 'rc.RCL_CO2', 'rc.RCL_TE2',
                'rc.RCL_EM1', 'rc.RCL_EM2', 'rc.RCL_EM3', 'rc.RCL_EM4', 'rc.RCL_EM5', 'r.REQ_COM']);

        return response()->json($rows->map(fn ($r) => [
            'cliente'     => (int) $r->RCL_CLI,
            'nombre'      => trim((string) $r->RCL_CLD),
            'req'         => (int) $r->RCL_REQ,
            'requerimiento' => trim((string) $r->RCL_RED),
            'dias'        => (int) $r->RCL_DIA,
            'ult_envio'   => $r->RCL_FUL && substr((string) $r->RCL_FUL, 0, 4) !== '1900' ? substr((string) $r->RCL_FUL, 0, 10) : '',
            'comun'       => (bool) $r->REQ_COM,
            'observaciones' => trim((string) $r->RCL_OBS),
            'contacto1'   => trim((string) $r->RCL_CO1), 'telefono1' => trim((string) $r->RCL_TE1),
            'contacto2'   => trim((string) $r->RCL_CO2), 'telefono2' => trim((string) $r->RCL_TE2),
            'emails'      => array_values(array_filter([
                trim((string) $r->RCL_EM1), trim((string) $r->RCL_EM2), trim((string) $r->RCL_EM3),
                trim((string) $r->RCL_EM4), trim((string) $r->RCL_EM5),
            ])),
        ])->values());
    }

    // ───────────────────────── helpers ─────────────────────────

    /** Documentos a enviar: de requerimientos comunes (DOC_TIP='C') + del cliente (DOC_TIP='K'), sin duplicar por nombre. */
    private function documentosAEnviar(int $cod): array
    {
        $vistos = [];
        $out = [];

        // Requerimientos comunes asignados al cliente.
        $reqsComunes = DB::table('req_cli as rc')->join('requerimientos as r', 'rc.RCL_REQ', '=', 'r.REQ_COD')
            ->where('rc.RCL_CLI', $cod)->where('r.REQ_COM', 1)->pluck('rc.RCL_REQ')->all();

        if ($reqsComunes) {
            $docsC = DB::table('documentos')->where('DOC_TIP', 'C')->whereIn('DOC_REF', $reqsComunes)->orderBy('DOC_CRE')->get();
            foreach ($docsC as $d) {
                $this->acumular($out, $vistos, $d);
            }
        }
        // Documentación exclusiva del cliente.
        $docsK = DB::table('documentos')->where('DOC_TIP', 'K')->where('DOC_REF', $cod)->orderBy('DOC_CRE')->get();
        foreach ($docsK as $d) {
            $this->acumular($out, $vistos, $d);
        }
        return $out;
    }

    private function acumular(array &$out, array &$vistos, object $d): void
    {
        $nombre = mb_strtoupper(trim((string) $d->DOC_NOM)) . '.' . mb_strtoupper(trim((string) $d->DOC_EXT));
        if (isset($vistos[$nombre])) {
            return;
        }
        $vistos[$nombre] = true;
        $out[] = [
            'id'         => (int) $d->UNICO,
            'nombre'     => trim((string) $d->DOC_NOM) . '.' . mb_strtolower(trim((string) $d->DOC_EXT)),
            'tdo'        => trim((string) $d->DOC_TDO),
            'referencia' => trim((string) $d->DOC_TDO)
                . str_pad((string) (int) $d->DOC_NRO, 6, '0', STR_PAD_LEFT)
                . str_pad((string) (int) $d->DOC_REF, 6, '0', STR_PAD_LEFT)
                . '.' . mb_strtoupper(trim((string) $d->DOC_EXT)),
        ];
    }

    /** Emails del cliente (de cualquier fila req_cli; en minúscula, sin vacíos). */
    private function emailsCliente(int $cod): array
    {
        $row = DB::table('req_cli')->where('RCL_CLI', $cod)->first();
        if (!$row) {
            return [];
        }
        $out = [];
        foreach (range(1, 10) as $i) {
            $e = mb_strtolower(trim((string) $row->{"RCL_EM{$i}"}));
            if ($e !== '') {
                $out[] = $e;
            }
        }
        return $out;
    }

    /** Cuerpo del correo (réplica del texto FoxPro), con firmas según los tipos de documento adjuntos. */
    private function cuerpo(string $nombre, array $docs): string
    {
        $meses = [1 => 'ENERO', 'FEBRERO', 'MARZO', 'ABRIL', 'MAYO', 'JUNIO', 'JULIO', 'AGOSTO', 'SEPTIEMBRE', 'OCTUBRE', 'NOVIEMBRE', 'DICIEMBRE'];
        $hoy = now();
        $periodo = $meses[(int) $hoy->format('n')] . ' ' . $hoy->format('Y');

        $tdos = array_map(fn ($d) => mb_strtoupper($d['tdo']), $docs);
        $firmas = '';
        if (in_array('AU', $tdos, true)) {
            $firmas = 'Autoelevadores Silcar S.R.L.';
        }
        if (in_array('LO', $tdos, true)) {
            $firmas .= ($firmas !== '' ? ' / ' : '') . 'Silcar Logistica y Representaciones S.A.';
        }

        $c  = "Estimado {$nombre},\r\n\r\n";
        $c .= "Por medio de la presente le hacemos llegar los requerimientos solicitados.\r\n";
        $c .= "Adjuntamos Documentacion Ingreso Mensual Periodo {$periodo}\r\n\r\n";
        $c .= "- ART\r\n\r\n- CNR\r\n\r\n- SVO\r\n\r\n- F.931\r\n\r\n- SINDICATO\r\n\r\n";
        $c .= "Saludos cordiales.-\r\n\r\n";
        if ($firmas !== '') {
            $c .= $firmas . "\r\n";
        }
        $c .= "Recursos Humanos\r\nGRUPO SILCAR\r\n";
        $c .= "Tel. (0341) 4653737 Int 133\r\nCel. (0341) 2826180\r\nwww.gruposilcar.com\r\n";
        return $c;
    }

    /** Registra el envío en req_enviados + req_enviados_adjuntos y actualiza RCL_FUL. */
    private function registrar(int $cod, string $nombre, string $cuerpo, array $emails, array $adjuntos): void
    {
        $em = array_pad(array_slice($emails, 0, 10), 10, '');
        $fila = [
            'RCL_CLI' => $cod,
            'RCL_CLD' => mb_substr($nombre, 0, 100),
            'RCL_ENVIADO' => now()->format('Y-m-d H:i:s'),
            'RCL_CUERPO' => mb_substr($cuerpo, 0, 4000),
        ];
        foreach (range(1, 10) as $i) {
            $fila["RCL_EM{$i}"] = mb_substr((string) $em[$i - 1], 0, 100);
        }
        $unico = DB::table('req_enviados')->insertGetId($fila, 'unico');

        foreach ($adjuntos as $nom) {
            DB::table('req_enviados_adjuntos')->insert([
                'unico' => $unico,
                'documento' => mb_substr($nom, 0, 100),
                'ubicacion' => mb_substr($nom, 0, 100),
            ]);
        }
        // Fecha de último envío en los requerimientos del cliente.
        DB::table('req_cli')->where('RCL_CLI', $cod)->update(['RCL_FUL' => now()->format('Y-m-d')]);
    }
}
