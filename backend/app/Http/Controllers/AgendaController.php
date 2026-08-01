<?php

namespace App\Http\Controllers;

use App\Services\BibliotecaDigitalService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * AgendaController — Agenda de mensajes entre usuarios (tablas nuevas en RRHH/RRHHlog).
 *
 * Este archivo cubre por ahora los CATÁLOGOS de la Agenda:
 *   - TEMAS     (TEM_COD, TEM_DES)                → asuntos de los mensajes
 *   - GRUPOS    (GRU_COD, GRU_DES)                → grupos de usuarios (destinatarios masivos)
 *   - GRUP_USU  (GUS_COD, GUS_USU, GUS_NOM)       → usuarios de cada grupo (N:N con `usuarios`)
 *
 * Códigos SIN identity → se numeran con max+1 (convención del proyecto).
 * Los usuarios se toman de la tabla `usuarios` de RRHH (CODIGO + NOMBRE). El núcleo
 * de mensajería (MENSAJES: enviar / bandeja / leer / responder) se agrega luego.
 */
class AgendaController extends Controller
{
    // ─────────────────────────── USUARIOS (para elegir destinatarios/miembros) ───────────────────────────

    /** @route GET /api/agenda/usuarios — usuarios (código, nombre, email, logueado hoy) para armar grupos y enviar. */
    public function usuarios(): JsonResponse
    {
        $hoy = now()->toDateString();
        // Solo usuarios "activos": como no hay flag de baja, se toman los que tienen
        // email_verified_at cargado (los dados de baja no lo tienen).
        $rows = DB::table('usuarios')->where('CODIGO', '>', 0)
            ->whereNotNull('email_verified_at')
            ->orderBy('NOMBRE')
            ->get(['CODIGO', 'NOMBRE', 'email', 'INICIO'])
            ->map(fn ($u) => [
                'codigo'     => (int) $u->CODIGO,
                'nombre'     => trim((string) $u->NOMBRE),
                'email'      => trim((string) ($u->email ?? '')),
                'activo_hoy' => $u->INICIO ? Carbon::parse($u->INICIO)->toDateString() === $hoy : false,
            ])
            ->filter(fn ($u) => $u['nombre'] !== '')->values();
        return response()->json($rows);
    }

    // ─────────────────────────── ENVIAR MENSAJE (agenda_crear_mensaje.scx) ───────────────────────────

    /**
     * @route POST /api/agenda/mensajes — envía un mensaje a uno o varios usuarios (o a un grupo ya expandido).
     *
     * Réplica del botón ENVIAR: un `MEN_NRO` = max+1 compartido por todos los destinatarios; una fila por usuario.
     * INMEDIATO = fecha hoy + hora ahora; PROGRAMADO = fecha/hora elegidas (MEN_FEC + MEN_HOR/MEN_MIN = aparición).
     * MEN_FEN = timestamp de creación. MEN_RET = pide aviso de retorno. Adjunto: próximo micro-paso.
     */
    public function enviarMensaje(Request $request): JsonResponse
    {
        $d = $request->validate([
            'usuarios'      => 'required|array|min:1',
            'usuarios.*'    => 'integer',
            'tema'          => 'required|string|max:50',
            'mensaje'       => 'required|string|max:140',
            'programado'    => 'nullable|boolean',
            'fecha'         => 'nullable|date',
            'hora'          => 'nullable|integer|min:0|max:2359',  // HHMM
            'aviso_retorno' => 'nullable|boolean',
            'archivo'       => 'nullable|file|max:51200',           // adjunto opcional (50 MB)
        ], [
            'usuarios.required' => 'Debe elegir al menos un destinatario.',
            'usuarios.min'      => 'Debe elegir al menos un destinatario.',
            'tema.required'     => 'Falta el tema del mensaje.',
            'mensaje.required'  => 'Debe escribir un mensaje.',
        ]);

        $u   = $request->user();
        $ous = (int) ($u->CODIGO ?? 0);
        $ono = mb_substr(trim((string) ($u->NOMBRE ?? 'RRHH.NET')), 0, 30);

        // ── Adjunto opcional: un único archivo por envío (todos los destinatarios comparten el MEN_ADJ). ──
        $asn = 'N'; $adjNro = 0; $arc = '';
        if ($request->hasFile('archivo')) {
            $file = $request->file('archivo');
            $ext  = mb_strtoupper((string) $file->getClientOriginalExtension());
            if ($ext === '' || in_array($ext, ['EXE', 'BAT', 'DLL', 'CMD', 'COM', 'ZIP', 'RAR', 'CAB'], true)) {
                return response()->json(['message' => 'Extensión de archivo no válida por seguridad del sistema.'], 422);
            }
            $adjNro = (int) DB::table('MENSAJES')->max('MEN_ADJ') + 1;
            $arc    = mb_substr((string) $file->getClientOriginalName(), 0, 100);
            try {
                (new BibliotecaDigitalService())->archivoDigitalGuardar(
                    config('rrhh.docs_sistema'), 'ADJUNTO_MENSAJE', 'ADJ' . $adjNro, $ext,
                    file_get_contents($file->getRealPath()), mb_substr($ono, 0, 20)
                );
                $asn = 'S';
            } catch (\Throwable $e) {
                Log::error('[agendaAdjunto] ' . $e->getMessage());
                return response()->json(['message' => 'No se pudo guardar el adjunto en la biblioteca digital.'], 500);
            }
        }

        $programado = (bool) ($d['programado'] ?? false);
        $ahora = now();
        if ($programado && !empty($d['fecha'])) {
            $fechaAp = Carbon::parse($d['fecha'])->format('Y-m-d');
            $hhmm    = (int) ($d['hora'] ?? 0);
        } else {
            $fechaAp = $ahora->format('Y-m-d');            // INMEDIATO
            $hhmm    = (int) $ahora->format('H') * 100 + (int) $ahora->format('i');
        }
        $hor = intdiv($hhmm, 100); $min = $hhmm % 100;

        $tema     = mb_substr(trim($d['tema']), 0, 50);
        $texto    = mb_substr(trim($d['mensaje']), 0, 140);
        $ret      = !empty($d['aviso_retorno']) ? 'S' : 'N';
        $terminal = mb_substr(mb_strtoupper(gethostname() ?: 'RRHH.NET'), 0, 30);
        $creado   = $ahora->format('Y-m-d H:i:s');

        $dest = array_values(array_unique(array_map('intval', $d['usuarios'])));
        $nro = 0; $enviados = 0;
        DB::transaction(function () use ($dest, $ous, $ono, $fechaAp, $hor, $min, $creado, $tema, $texto, $ret, $terminal, $asn, $adjNro, $arc, &$nro, &$enviados) {
            $nro = (int) DB::table('MENSAJES')->max('MEN_NRO') + 1;
            foreach ($dest as $cod) {
                if ($cod <= 0 || $cod === $ous) continue;   // no a sí mismo
                $ux = DB::table('usuarios')->where('CODIGO', $cod)->first(['NOMBRE', 'email']);
                if (!$ux) continue;
                $nom = mb_substr(trim((string) $ux->NOMBRE), 0, 30);
                if ($nom === '') continue;
                $email = trim((string) ($ux->email ?? ''));
                DB::table('MENSAJES')->insert([
                    'MEN_NRO' => $nro, 'MEN_USU' => $cod, 'MEN_NOM' => $nom, 'MEN_OUS' => $ous, 'MEN_ONO' => $ono,
                    'MEN_FEC' => $fechaAp, 'MEN_HOR' => $hor, 'MEN_MIN' => $min, 'MEN_FEN' => $creado,
                    'MEN_TEM' => $tema, 'MEN_DAT' => $texto, 'MEN_ASN' => $asn, 'MEN_ADJ' => $adjNro, 'MEN_ARC' => $arc,
                    'MEN_LEI' => 'N', 'MEN_FLE' => '1900-01-01', 'MEN_RET' => $ret, 'MEN_FRE' => '1900-01-01',
                    'MEN_TER' => $terminal, 'MEN_ENV' => $email !== '' ? 1 : 0, 'MEN_EMA' => mb_substr($email, 0, 100),
                ]);
                $enviados++;
            }
        });

        if ($enviados === 0) {
            return response()->json(['message' => 'No se pudo enviar: revise los destinatarios.'], 422);
        }
        return response()->json(['ok' => true, 'nro' => $nro, 'enviados' => $enviados]);
    }

    // ─────────────────────────── ALERTA / BANDEJA (agenda_alerta.scx) ───────────────────────────

    /**
     * @route GET /api/agenda/pendientes — mensajes NO leídos del usuario actual cuya fecha/hora de aparición ya llegó.
     *
     * Réplica del timer de la alerta: MEN_USU=yo, MEN_LEI='N' y (MEN_FEC<hoy Ó (MEN_FEC=hoy y ahora>=MEN_HOR:MEN_MIN)).
     */
    public function pendientes(Request $request): JsonResponse
    {
        $yo  = (int) ($request->user()->CODIGO ?? 0);
        $hoy = now()->toDateString();
        $hhmm = (int) now()->format('H') * 100 + (int) now()->format('i');

        $rows = DB::table('MENSAJES')
            ->where('MEN_USU', $yo)
            ->whereRaw("LTRIM(RTRIM(MEN_LEI)) = 'N'")
            ->where(function ($w) use ($hoy, $hhmm) {
                $w->whereRaw('CAST(MEN_FEC AS DATE) < ?', [$hoy])
                  ->orWhere(function ($x) use ($hoy, $hhmm) {
                      $x->whereRaw('CAST(MEN_FEC AS DATE) = ?', [$hoy])
                        ->whereRaw('(MEN_HOR * 100 + MEN_MIN) <= ?', [$hhmm]);
                  });
            })
            ->orderByDesc('MEN_FEN')
            ->get(['UNICO', 'MEN_FEN', 'MEN_OUS', 'MEN_ONO', 'MEN_TEM', 'MEN_DAT', 'MEN_RET', 'MEN_ASN', 'MEN_ARC'])
            ->map(fn ($m) => [
                'unico'         => (int) $m->UNICO,
                'fecha_hora'    => $m->MEN_FEN ? \Carbon\Carbon::parse($m->MEN_FEN)->format('d/m/Y H:i') : '',
                'origen'        => trim((string) $m->MEN_ONO),
                'origen_cod'    => (int) $m->MEN_OUS,
                'tema'          => trim((string) $m->MEN_TEM),
                'texto'         => trim((string) $m->MEN_DAT),
                'aviso_retorno' => trim((string) $m->MEN_RET) === 'S',
                'adjunto'       => trim((string) $m->MEN_ASN) === 'S',
                'archivo'       => trim((string) $m->MEN_ARC),
            ])->values();

        return response()->json(['total' => $rows->count(), 'mensajes' => $rows]);
    }

    /**
     * @route GET /api/agenda/mensajes/{unico}/adjunto — visualiza/descarga el archivo adjunto del mensaje.
     * Accesible por el destinatario o el remitente. El binario vive en la biblioteca digital (proceso ADJUNTO_MENSAJE, ref ADJ{n}).
     */
    public function adjunto(Request $request, int $unico)
    {
        $yo = (int) ($request->user()->CODIGO ?? 0);
        $m = DB::table('MENSAJES')->where('UNICO', $unico)->first(['MEN_ADJ', 'MEN_ASN', 'MEN_ARC', 'MEN_USU', 'MEN_OUS']);
        if (!$m) return response()->json(['message' => 'Mensaje inexistente.'], 404);
        if ((int) $m->MEN_USU !== $yo && (int) $m->MEN_OUS !== $yo) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }
        if (trim((string) $m->MEN_ASN) !== 'S' || (int) $m->MEN_ADJ <= 0) {
            return response()->json(['message' => 'El mensaje no tiene adjunto.'], 404);
        }
        $resp = (new BibliotecaDigitalService())->archivoDigitalVisualizar(
            config('rrhh.docs_sistema'), 'ADJUNTO_MENSAJE', 'ADJ' . (int) $m->MEN_ADJ);
        return $resp ?? response()->json(['message' => 'El adjunto no está en la biblioteca digital.'], 404);
    }

    /**
     * @route POST /api/agenda/mensajes/{unico}/leer — marca un mensaje como leído (con acuse si pedía retorno).
     */
    public function leer(Request $request, int $unico): JsonResponse
    {
        $yo    = (int) ($request->user()->CODIGO ?? 0);
        $yoNom = mb_substr(trim((string) ($request->user()->NOMBRE ?? 'RRHH.NET')), 0, 30);

        $m = DB::table('MENSAJES')->where('UNICO', $unico)->first();
        if (!$m) return response()->json(['message' => 'Mensaje inexistente.'], 404);
        if ((int) $m->MEN_USU !== $yo) return response()->json(['message' => 'El mensaje no es suyo.'], 403);

        DB::transaction(fn () => $this->aplicarLeido($m, $yo, $yoNom));
        return response()->json(['ok' => true]);
    }

    /**
     * @route POST /api/agenda/mensajes/{unico}/responder — responde al remitente y marca el original como leído.
     *
     * Réplica del botón ENVIAR RESPUESTA: crea un mensaje de vuelta (destino=remitente original, tema vacío,
     * sin aviso de retorno) y luego ejecuta el "marcar leído" del original (que a su vez acusa si pedía retorno).
     */
    public function responder(Request $request, int $unico): JsonResponse
    {
        $d = $request->validate(['texto' => 'required|string|max:140'], ['texto.required' => 'Escribí la respuesta.']);
        $yo    = (int) ($request->user()->CODIGO ?? 0);
        $yoNom = mb_substr(trim((string) ($request->user()->NOMBRE ?? 'RRHH.NET')), 0, 30);

        $m = DB::table('MENSAJES')->where('UNICO', $unico)->first();
        if (!$m) return response()->json(['message' => 'Mensaje inexistente.'], 404);
        if ((int) $m->MEN_USU !== $yo) return response()->json(['message' => 'El mensaje no es suyo.'], 403);
        if ((int) $m->MEN_OUS <= 0) return response()->json(['message' => 'El mensaje no tiene remitente.'], 422);

        $ahora = now();
        DB::transaction(function () use ($m, $yo, $yoNom, $ahora, $d) {
            $nro = (int) DB::table('MENSAJES')->max('MEN_NRO') + 1;
            DB::table('MENSAJES')->insert([
                'MEN_NRO' => $nro, 'MEN_USU' => (int) $m->MEN_OUS, 'MEN_NOM' => mb_substr(trim((string) $m->MEN_ONO), 0, 30),
                'MEN_OUS' => $yo, 'MEN_ONO' => $yoNom,
                'MEN_FEC' => $ahora->format('Y-m-d'), 'MEN_HOR' => (int) $ahora->format('H'), 'MEN_MIN' => (int) $ahora->format('i'),
                'MEN_FEN' => $ahora->format('Y-m-d H:i:s'), 'MEN_TEM' => '',
                'MEN_DAT' => mb_substr(trim($d['texto']), 0, 140),
                'MEN_ASN' => 'N', 'MEN_ADJ' => 0, 'MEN_ARC' => '', 'MEN_LEI' => 'N', 'MEN_FLE' => '1900-01-01',
                'MEN_RET' => 'N', 'MEN_FRE' => '1900-01-01', 'MEN_TER' => mb_substr(mb_strtoupper(gethostname() ?: 'RRHH.NET'), 0, 30),
                'MEN_ENV' => 0, 'MEN_EMA' => '',
            ]);
            // El original queda RESPONDIDO (MEN_FRE) y leído (aplicarLeido acusa si pedía retorno).
            DB::table('MENSAJES')->where('UNICO', (int) $m->UNICO)->update(['MEN_FRE' => $ahora->format('Y-m-d H:i:s')]);
            $this->aplicarLeido($m, $yo, $yoNom);
        });
        return response()->json(['ok' => true]);
    }

    /**
     * Marca el mensaje $m como leído (MEN_LEI='S' + MEN_FLE). Si pedía aviso de retorno (MEN_RET='S') y aún no
     * se acusó, registra MEN_FRE y crea el mensaje de acuse "Mensaje recibido y leido por {usuario}" al remitente.
     * Debe llamarse dentro de una transacción.
     */
    private function aplicarLeido(object $m, int $yo, string $yoNom): void
    {
        $ahora = now();
        DB::table('MENSAJES')->where('UNICO', (int) $m->UNICO)
            ->update(['MEN_LEI' => 'S', 'MEN_FLE' => $ahora->format('Y-m-d H:i:s')]);

        $yaAcuso = $m->MEN_FRE && \Carbon\Carbon::parse($m->MEN_FRE)->year > 1900;
        if (trim((string) $m->MEN_RET) === 'S' && !$yaAcuso && (int) $m->MEN_OUS > 0) {
            DB::table('MENSAJES')->where('UNICO', (int) $m->UNICO)->update(['MEN_FRE' => $ahora->format('Y-m-d H:i:s')]);
            $nro = (int) DB::table('MENSAJES')->max('MEN_NRO') + 1;
            DB::table('MENSAJES')->insert([
                'MEN_NRO' => $nro, 'MEN_USU' => (int) $m->MEN_OUS, 'MEN_NOM' => mb_substr(trim((string) $m->MEN_ONO), 0, 30),
                'MEN_OUS' => $yo, 'MEN_ONO' => $yoNom,
                'MEN_FEC' => $ahora->format('Y-m-d'), 'MEN_HOR' => (int) $ahora->format('H'), 'MEN_MIN' => (int) $ahora->format('i'),
                'MEN_FEN' => $ahora->format('Y-m-d H:i:s'), 'MEN_TEM' => '',
                'MEN_DAT' => mb_substr('Mensaje recibido y leido por ' . $yoNom, 0, 140),
                'MEN_ASN' => 'N', 'MEN_ADJ' => 0, 'MEN_ARC' => '', 'MEN_LEI' => 'N', 'MEN_FLE' => '1900-01-01',
                'MEN_RET' => 'N', 'MEN_FRE' => '1900-01-01', 'MEN_TER' => mb_substr(mb_strtoupper(gethostname() ?: 'RRHH.NET'), 0, 30),
                'MEN_ENV' => 0, 'MEN_EMA' => '',
            ]);
        }
    }

    /**
     * @route GET /api/agenda/historial?tipo=todos|recibidos|enviados — historial de mensajes del usuario actual.
     *
     * Recibidos = MEN_USU=yo · Enviados = MEN_OUS=yo. En enviados, MEN_LEI indica si el DESTINATARIO ya lo leyó.
     */
    public function historial(Request $request): JsonResponse
    {
        $yo   = (int) ($request->user()->CODIGO ?? 0);
        $tipo = $request->query('tipo', 'todos');

        $q = DB::table('MENSAJES');
        if ($tipo === 'recibidos')      $q->where('MEN_USU', $yo);
        elseif ($tipo === 'enviados')   $q->where('MEN_OUS', $yo);
        else $q->where(fn ($w) => $w->where('MEN_USU', $yo)->orWhere('MEN_OUS', $yo));

        $rows = $q->orderByDesc('MEN_FEN')->limit(500)
            ->get(['UNICO', 'MEN_USU', 'MEN_NOM', 'MEN_OUS', 'MEN_ONO', 'MEN_FEN', 'MEN_FEC', 'MEN_HOR', 'MEN_MIN',
                'MEN_TEM', 'MEN_DAT', 'MEN_LEI', 'MEN_FLE', 'MEN_RET', 'MEN_FRE', 'MEN_ASN', 'MEN_ARC'])
            ->map(function ($m) use ($yo) {
                $recibido = (int) $m->MEN_USU === $yo;
                return [
                    'unico'         => (int) $m->UNICO,
                    'direccion'     => $recibido ? 'recibido' : 'enviado',
                    'contraparte'   => trim((string) ($recibido ? $m->MEN_ONO : $m->MEN_NOM)),
                    // Fecha/hora de APARICIÓN (cuándo se muestra el mensaje) = MEN_FEC + MEN_HOR:MEN_MIN.
                    'fecha_hora'    => $m->MEN_FEC
                        ? \Carbon\Carbon::parse($m->MEN_FEC)->format('d/m/Y') . ' ' . sprintf('%02d:%02d', (int) $m->MEN_HOR, (int) $m->MEN_MIN)
                        : '',
                    // Cuándo se creó/envió el mensaje (por si se necesita distinguir del de aparición).
                    'fecha_creado'  => $m->MEN_FEN ? \Carbon\Carbon::parse($m->MEN_FEN)->format('d/m/Y H:i') : '',
                    'tema'          => trim((string) $m->MEN_TEM),
                    'texto'         => trim((string) $m->MEN_DAT),
                    'leido'         => trim((string) $m->MEN_LEI) === 'S',
                    'respondido'    => $m->MEN_FRE && \Carbon\Carbon::parse($m->MEN_FRE)->year > 1900,
                    'aviso_retorno' => trim((string) $m->MEN_RET) === 'S',
                    'adjunto'       => trim((string) $m->MEN_ASN) === 'S',
                    'archivo'       => trim((string) $m->MEN_ARC),
                ];
            })->values();

        return response()->json($rows);
    }

    // ─────────────────────────── TEMAS ───────────────────────────

    /** @route GET /api/agenda/temas */
    public function temasIndex(): JsonResponse
    {
        $rows = DB::table('TEMAS')->orderBy('TEM_DES')->get(['TEM_COD', 'TEM_DES'])
            ->map(fn ($t) => ['cod' => (int) $t->TEM_COD, 'descripcion' => trim((string) $t->TEM_DES)]);
        return response()->json($rows);
    }

    /** @route POST /api/agenda/temas */
    public function temasStore(Request $request): JsonResponse
    {
        $d = $request->validate(['descripcion' => 'required|string|max:50'],
            ['descripcion.required' => 'Debe ingresar la descripción del tema.']);
        $des = mb_strtoupper(mb_substr(trim($d['descripcion']), 0, 50));
        if (DB::table('TEMAS')->whereRaw('UPPER(LTRIM(RTRIM(TEM_DES))) = ?', [$des])->exists()) {
            return response()->json(['message' => 'Ya existe un tema con esa descripción.'], 422);
        }
        $cod = (int) DB::table('TEMAS')->max('TEM_COD') + 1;
        DB::table('TEMAS')->insert(['TEM_COD' => $cod, 'TEM_DES' => $des]);
        return response()->json(['ok' => true, 'cod' => $cod], 201);
    }

    /** @route PUT /api/agenda/temas/{cod} */
    public function temasUpdate(Request $request, int $cod): JsonResponse
    {
        $d = $request->validate(['descripcion' => 'required|string|max:50']);
        if (!DB::table('TEMAS')->where('TEM_COD', $cod)->exists()) {
            return response()->json(['message' => 'Tema inexistente.'], 404);
        }
        DB::table('TEMAS')->where('TEM_COD', $cod)
            ->update(['TEM_DES' => mb_strtoupper(mb_substr(trim($d['descripcion']), 0, 50))]);
        return response()->json(['ok' => true]);
    }

    /** @route DELETE /api/agenda/temas/{cod} */
    public function temasDestroy(int $cod): JsonResponse
    {
        DB::table('TEMAS')->where('TEM_COD', $cod)->delete();
        return response()->json(['ok' => true]);
    }

    // ─────────────────────────── GRUPOS (+ usuarios) ───────────────────────────

    /** @route GET /api/agenda/grupos — grupos con cantidad de usuarios. */
    public function gruposIndex(): JsonResponse
    {
        $cant = DB::table('GRUP_USU')->select('GUS_COD', DB::raw('COUNT(*) as c'))
            ->groupBy('GUS_COD')->pluck('c', 'GUS_COD');
        $rows = DB::table('GRUPOS')->orderBy('GRU_DES')->get(['GRU_COD', 'GRU_DES'])
            ->map(fn ($g) => [
                'cod'         => (int) $g->GRU_COD,
                'descripcion' => trim((string) $g->GRU_DES),
                'cant'        => (int) ($cant[$g->GRU_COD] ?? 0),
            ]);
        return response()->json($rows);
    }

    /** @route GET /api/agenda/grupos/{cod} — grupo con sus usuarios. */
    public function grupoShow(int $cod): JsonResponse
    {
        $g = DB::table('GRUPOS')->where('GRU_COD', $cod)->first(['GRU_COD', 'GRU_DES']);
        if (!$g) return response()->json(['message' => 'Grupo inexistente.'], 404);
        $usuarios = DB::table('GRUP_USU')->where('GUS_COD', $cod)->orderBy('GUS_NOM')
            ->get(['GUS_USU', 'GUS_NOM'])
            ->map(fn ($u) => ['codigo' => (int) $u->GUS_USU, 'nombre' => trim((string) $u->GUS_NOM)]);
        return response()->json([
            'cod' => (int) $g->GRU_COD, 'descripcion' => trim((string) $g->GRU_DES), 'usuarios' => $usuarios,
        ]);
    }

    /** @route POST /api/agenda/grupos */
    public function gruposStore(Request $request): JsonResponse
    {
        $d = $this->validarGrupo($request);
        $des = mb_strtoupper(mb_substr(trim($d['descripcion']), 0, 50));
        if (DB::table('GRUPOS')->whereRaw('UPPER(LTRIM(RTRIM(GRU_DES))) = ?', [$des])->exists()) {
            return response()->json(['message' => 'Ya existe un grupo con ese nombre.'], 422);
        }
        $cod = (int) DB::table('GRUPOS')->max('GRU_COD') + 1;
        DB::transaction(function () use ($cod, $des, $d) {
            DB::table('GRUPOS')->insert(['GRU_COD' => $cod, 'GRU_DES' => $des]);
            $this->guardarUsuarios($cod, $d['usuarios'] ?? []);
        });
        return response()->json(['ok' => true, 'cod' => $cod], 201);
    }

    /** @route PUT /api/agenda/grupos/{cod} */
    public function gruposUpdate(Request $request, int $cod): JsonResponse
    {
        $d = $this->validarGrupo($request);
        if (!DB::table('GRUPOS')->where('GRU_COD', $cod)->exists()) {
            return response()->json(['message' => 'Grupo inexistente.'], 404);
        }
        DB::transaction(function () use ($cod, $d) {
            DB::table('GRUPOS')->where('GRU_COD', $cod)
                ->update(['GRU_DES' => mb_strtoupper(mb_substr(trim($d['descripcion']), 0, 50))]);
            DB::table('GRUP_USU')->where('GUS_COD', $cod)->delete();
            $this->guardarUsuarios($cod, $d['usuarios'] ?? []);
        });
        return response()->json(['ok' => true]);
    }

    /** @route DELETE /api/agenda/grupos/{cod} */
    public function gruposDestroy(int $cod): JsonResponse
    {
        DB::transaction(function () use ($cod) {
            DB::table('GRUP_USU')->where('GUS_COD', $cod)->delete();
            DB::table('GRUPOS')->where('GRU_COD', $cod)->delete();
        });
        return response()->json(['ok' => true]);
    }

    /** Reglas de validación de un grupo. */
    private function validarGrupo(Request $request): array
    {
        return $request->validate([
            'descripcion' => 'required|string|max:50',
            'usuarios'    => 'nullable|array',
            'usuarios.*'  => 'integer',
        ], ['descripcion.required' => 'Debe ingresar el nombre del grupo.']);
    }

    /** Inserta los usuarios de un grupo (denormaliza el nombre desde `usuarios`). */
    private function guardarUsuarios(int $cod, array $codigos): void
    {
        foreach (array_unique(array_map('intval', $codigos)) as $u) {
            if ($u <= 0) continue;
            $nom = (string) DB::table('usuarios')->where('CODIGO', $u)->value('NOMBRE');
            DB::table('GRUP_USU')->insert([
                'GUS_COD' => $cod, 'GUS_USU' => $u, 'GUS_NOM' => mb_substr(trim($nom), 0, 30),
            ]);
        }
    }
}
