<?php

/**
 * ============================================================
 * EmpleadoController.php
 * ============================================================
 * Controlador REST para el módulo de Empleados (ABM).
 *
 * Endpoints:
 * ──────────
 *  GET  /api/empleados                → Listar con filtros y paginación
 *  GET  /api/empleados/opciones       → Tablas de lookup para los selects
 *  GET  /api/empleados/{cod}          → Ver un empleado
 *  POST /api/empleados                → Crear nuevo empleado
 *  PUT  /api/empleados/{cod}          → Actualizar empleado
 *  PATCH /api/empleados/{cod}/estado  → Activar / dar de baja
 *
 * Filtros disponibles en GET /api/empleados:
 *   ?buscar=texto    → busca en código, legajo, nombre, domicilio, sexo,
 *                      nombre de padre/madre, hijos, DNI y CUIL (ver Personal::scopeBuscar)
 *   ?activo=1|0|all  → filtra por estado (default: all)
 *   ?empresa=1       → filtra por empresa
 *   ?sector=1        → filtra por sector
 *   ?pagina=1        → página (default: 1)
 *   ?por_pagina=50   → resultados por página (default: 50, max: 200)
 *
 * @package  App\Http\Controllers
 * @author   Sistema RRHH.NET
 * @version  1.0.0
 * @since    2026-06-08
 * ============================================================
 */

namespace App\Http\Controllers;

use App\Models\Personal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmpleadoController extends Controller
{
    // ══════════════════════════════════════════════════════════
    // LISTADO Y OPCIONES
    // ══════════════════════════════════════════════════════════

    /**
     * Lista empleados con filtros y paginación.
     *
     * Retorna solo los campos necesarios para la grilla (no todos los 177).
     * El detalle completo se obtiene con show().
     *
     * @route  GET /api/empleados
     * @auth   Bearer token requerido
     * @return JsonResponse  { data: [], total, pagina, por_pagina, paginas }
     */
    public function index(Request $request): JsonResponse
    {
        $porPagina = min((int)($request->get('por_pagina', 50)), 200);
        $pagina    = max((int)($request->get('pagina', 1)), 1);

        $query = Personal::orderBy('PER_NOM');

        // Filtro por estado activo
        // PER_AOP = "A" → activo (Alta en FoxPro), resto → baja
        $activo = $request->get('activo', 'all');
        if ($activo === '1' || $activo === 'true') {
            $query->where('PER_AOP', 'A');
        } elseif ($activo === '0' || $activo === 'false') {
            $query->where('PER_AOP', '!=', 'A');
        }

        // Filtro por empresa
        if ($request->filled('empresa')) {
            $query->where('PER_EMP', $request->empresa);
        }

        // Filtro por sector
        if ($request->filled('sector')) {
            $query->where('PER_SEC', $request->sector);
        }

        // Búsqueda por texto
        if ($request->filled('buscar')) {
            $query->buscar($request->buscar);
        }

        $total = $query->count();

        // Columnas para la grilla (no traer los 177 campos)
        $empleados = $query
            ->select([
                'PER_COD', 'PER_NOM', 'PER_LEG', 'PER_NDO', 'PER_CUI',
                'ACTIVO', 'PER_AOP', 'PER_ING', 'PER_BAJ',
                'PER_EMP', 'PER_EMD', 'PER_SEC', 'PER_SED',
                'PER_CAT', 'PER_CAD', 'PER_CON',
                'PER_TEL', 'PER_CEL', 'PER_SEX',
            ])
            ->skip(($pagina - 1) * $porPagina)
            ->take($porPagina)
            ->get();

        return response()->json([
            'data'       => $empleados,
            'total'      => $total,
            'pagina'     => $pagina,
            'por_pagina' => $porPagina,
            'paginas'    => (int)ceil($total / $porPagina),
        ]);
    }

    /**
     * Búsqueda liviana de empleados para la lupa reutilizable (EmpleadoBuscar.vue).
     * Devuelve un arreglo plano con lo mínimo para elegir un empleado por su código.
     *
     * @route GET /api/empleados/buscar?q=texto&activo=1
     */
    public function buscar(Request $request): JsonResponse
    {
        $query = Personal::query();

        // Por defecto sólo activos (PER_AOP = 'A'); activo=0 => todos.
        if ($request->get('activo', '1') !== '0') {
            $query->where('PER_AOP', 'A');
        }
        if ($request->filled('q')) {
            $query->buscar($request->q);
        }

        $rows = $query->orderBy('PER_NOM')
            ->select(['PER_COD', 'PER_NOM', 'PER_LEG', 'PER_NDO', 'PER_SED', 'PER_EMD', 'PER_AOP'])
            ->take(300)
            ->get()
            ->map(fn ($e) => [
                // Claves PER_* para ser drop-in de los handlers existentes (seleccionar(r)).
                'PER_COD'   => (int) $e->PER_COD,
                'PER_NOM'   => trim((string) $e->PER_NOM),
                'PER_LEG'   => trim((string) ($e->PER_LEG ?? '')),
                'PER_NDO'   => trim((string) ($e->PER_NDO ?? '')),
                'PER_SED'   => trim((string) ($e->PER_SED ?? '')),
                'PER_EMD'   => trim((string) ($e->PER_EMD ?? '')),
                'PER_AOP'   => trim((string) $e->PER_AOP),
                // Alias en minúscula para usos nuevos.
                'cod'       => (int) $e->PER_COD,
                'nombre'    => trim((string) $e->PER_NOM),
                'legajo'    => trim((string) ($e->PER_LEG ?? '')),
                'documento' => trim((string) ($e->PER_NDO ?? '')),
                'sector'    => trim((string) ($e->PER_SED ?? '')),
                'activo'    => trim((string) $e->PER_AOP) === 'A',
            ])->values();

        return response()->json($rows);
    }

    /**
     * Retorna todas las tablas de lookup necesarias para los selects del formulario.
     *
     * Se llama una sola vez al cargar el módulo. Incluye:
     *   - empresas, sectores, categorías, convenios, estados civiles
     *
     * @route  GET /api/empleados/opciones
     * @auth   Bearer token requerido
     * @return JsonResponse
     */
    public function opciones(): JsonResponse
    {
        // Helper: ejecuta una query y devuelve [] si falla, nunca corta toda la respuesta
        $q = function (callable $fn) {
            try {
                return $fn();
            } catch (\Throwable $e) {
                \Log::warning('[opciones] ' . $e->getMessage());
                return [];
            }
        };

        $parametro = $q(fn () => DB::table('parametro')->first());

        return response()->json([
            'empresas' => $q(fn () => DB::table('empresas')
                ->orderBy('EMP_NOM')->get(['EMP_COD', 'EMP_NOM'])),

            'sectores' => $q(fn () => DB::table('sector')
                ->orderBy('SEC_DES')->get(['SEC_COD', 'SEC_DES'])),

            'categorias' => $q(fn () => DB::table('categori')
                ->orderBy('CAT_DES')->get(['CAT_COD', 'CAT_DES', 'CAT_CON'])),

            'convenios' => $q(fn () => DB::table('convenio')
                ->orderBy('CON_DES')->get(['CON_COD', 'CON_DES'])),

            'estadosciviles' => $q(fn () => DB::table('estadocivil')
                ->orderBy('ECI_DES')->get(['ECI_COD', 'ECI_DES'])),

            'obrassociales' => $q(fn () => DB::table('obras_sociales')
                ->orderBy('OBR_NOM')->get(['OBR_COD', 'OBR_NOM'])),

            'contratistas' => $q(fn () => DB::table('contratista')
                ->orderBy('CONT_DET')->get(['CONT_COD', 'CONT_DET'])),


            'comedores' => $q(fn () => DB::table('comedor')
                ->orderBy('COME_DES')->get(['COME_COD', 'COME_DES'])),

            'lugares' => $q(fn () => DB::table('lugar')
                ->orderBy('LUG_NOM')->get(['LUG_COD', 'LUG_NOM'])),

            'subsectores' => $q(fn () => DB::table('subsector')
                ->orderBy('sub_des')->get(['sub_cod', 'sub_des'])),

            'reloj_grupos' => $q(fn () => DB::table('reloj_grupos')
                ->orderBy('rgr_des')->get(['rgr_cod', 'rgr_des'])),

            // Categorías del carnet de conducir (tab Licencias)
            'carnetcategorias' => $q(fn () => DB::table('carn_cat')
                ->orderBy('CRT_COD')->get([
                    DB::raw('LTRIM(RTRIM(CRT_COD)) as cod'),
                    DB::raw('LTRIM(RTRIM(CRT_DES)) as det'),
                ])),

            // Tipos de documento del empleado (tab Documentación) — TDO_TIP='E'
            'tiposdocumento' => $q(fn () => DB::table('tipo_doc')
                ->where('TDO_TIP', 'E')
                ->orderBy('TDO_DES')
                ->get([
                    DB::raw('LTRIM(RTRIM(TDO_COD)) as cod'),
                    DB::raw('LTRIM(RTRIM(TDO_DES)) as det'),
                ])),

            'reloj_envios' => $q(fn () => DB::table('reloj_envios')
                ->where('rdp_act', 1)
                ->orderBy('rdp_des')->get(['rdp_cod', 'rdp_des'])),

            'bancos' => $q(fn () => DB::table('ctas_ban')
                ->orderBy('CBA_DES')->get(['CBA_COD', 'CBA_DES'])),

            'viaticos_dia' => $parametro->par_d30 ?? 0,
        ]);
    }

    // ══════════════════════════════════════════════════════════
    // DETALLE
    // ══════════════════════════════════════════════════════════

    /**
     * Retorna el detalle completo de un empleado.
     *
     * @route  GET /api/empleados/{codigo}
     * @auth   Bearer token requerido
     * @param  int $codigo  PER_COD del empleado
     * @return JsonResponse
     */
    public function show(int $codigo): JsonResponse
    {
        $empleado = Personal::where('PER_COD', $codigo)->firstOrFail();
        return response()->json($this->normalizarNulos($empleado));
    }

    // ══════════════════════════════════════════════════════════
    // ALTA
    // ══════════════════════════════════════════════════════════

    /**
     * Crea un nuevo empleado en la tabla personal.
     *
     * El PER_COD se genera automáticamente como MAX(PER_COD) + 1
     * ya que la tabla no usa auto_increment (herencia FoxPro).
     *
     * Validaciones:
     *   · validar()              → tipos/formatos de cada campo (422 con errores).
     *   · validarReglasNegocio() → reglas FoxPro (legajo, nombre, documento, CUIL único).
     *
     * @route  POST /api/empleados
     * @auth   Bearer token requerido
     * @return JsonResponse  Empleado creado con HTTP 201
     */
    public function store(Request $request): JsonResponse
    {
        $datos = $this->validar($request);
        $this->validarReglasNegocio($datos, null);   // null = alta (no excluye ningún código)

        // Normalizar nombre en mayúsculas
        if (isset($datos['PER_NOM'])) {
            $datos['PER_NOM'] = strtoupper(trim($datos['PER_NOM']));
        }

        // Estado activo (PER_AOP = "A" es el campo FoxPro; ACTIVO = 1 es el campo MySQL)
        $datos['PER_AOP'] = 'A';
        $datos['ACTIVO']  = 1;

        // Reemplazar nulls por defaults antes de insertar.
        // Los campos no enviados por el frontend vienen como null desde validar().
        // La convención FoxPro no admite NULLs: '' para texto, 0 para números,
        // false para booleanos, '1900-01-01' para fechas sin valor.
        $datos = $this->sanitizarParaGuardar($datos);

        // ── Asignación de PER_COD con garantía de unicidad absoluta ──
        // El código (PAR) se comparte con otra empresa (RRHH=pares, Logística=impares),
        // por eso no se usa MAX+1. La unicidad está garantizada por el índice UNIQUE
        // de BD (UX_personal_PER_COD): si dos terminales insertan el mismo código a la
        // vez, una falla y se reintenta con el siguiente par libre. Es IMPOSIBLE duplicar.
        $reservado = (int) $request->input('PER_COD', 0);
        $codigo = ($reservado > 0 && !Personal::where('PER_COD', $reservado)->exists())
            ? $reservado                       // respeta el código reservado por el front
            : $this->generarCodigoPar();

        $empleado = null;
        for ($intento = 0; $intento < 50; $intento++) {
            $datos['PER_COD'] = $codigo;
            try {
                $empleado = Personal::create($datos);
                break;
            } catch (\Illuminate\Database\QueryException $e) {
                // 2627/2601 = violación de UNIQUE en SQL Server → código tomado por
                // otra terminal en la milésima entre el cálculo y el INSERT.
                if (in_array((string) ($e->errorInfo[1] ?? ''), ['2627', '2601'], true)) {
                    $codigo = $this->generarCodigoPar();   // siguiente par libre
                    continue;
                }
                throw $e;
            }
        }

        if ($empleado === null) {
            return response()->json(['message' => 'No se pudo asignar un código único, reintente.'], 409);
        }

        // Consumir la reserva (si existía) — el código ya quedó en personal.
        DB::table('codigo_reserva')->where('CODIGO', $codigo)->delete();

        return response()->json([
            'message'  => "Empleado {$empleado->PER_NOM} creado correctamente.",
            'empleado' => $empleado,
        ], 201);
    }

    // ══════════════════════════════════════════════════════════
    // MODIFICACIÓN
    // ══════════════════════════════════════════════════════════

    /**
     * Actualiza los datos de un empleado existente.
     *
     * @route  PUT /api/empleados/{codigo}
     * @auth   Bearer token requerido
     * @param  int $codigo  PER_COD del empleado
     * @return JsonResponse
     */
    public function update(Request $request, int $codigo): JsonResponse
    {
        $empleado = Personal::where('PER_COD', $codigo)->firstOrFail();
        $datos    = $this->validar($request, $codigo);
        // Mismas reglas de negocio que el alta; se pasa $codigo para que la
        // unicidad de CUIL excluya al propio empleado que se está editando.
        $this->validarReglasNegocio($datos, $codigo);

        if (isset($datos['PER_NOM'])) {
            $datos['PER_NOM'] = strtoupper(trim($datos['PER_NOM']));
        }

        if (empty($datos['PER_BAJ'])) {
            $datos['PER_BAJ'] = '1900-01-01';
        }

        // Eliminar campos null del array de actualización.
        // Si el frontend no cargó un campo (lo envía como null), no debe
        // pisar el valor existente en la BD (herencia FoxPro: los campos
        // vacíos son '' o 1900-01-01, no NULL).
        $datos = array_filter($datos, fn($v) => !is_null($v));

        // Snapshot de los valores ANTES de actualizar (para el historial de cambios).
        $antes = $empleado->getAttributes();

        $empleado->update($datos);
        $empleado->refresh();

        // Registrar en per_hist los campos que cambiaron (réplica FoxPro).
        $this->registrarHistorialCambios($antes, $empleado->getAttributes(), $codigo, $request);

        return response()->json([
            'message'  => "Empleado {$empleado->PER_NOM} actualizado correctamente.",
            'empleado' => $this->normalizarNulos($empleado),
        ]);
    }

    /**
     * Registra en per_hist los campos que cambiaron al editar un empleado.
     *
     * Replica el bloque FoxPro que compara valor anterior vs nuevo y arma un
     * texto "CAMPO: viejo x nuevo" por cada diferencia. Si hubo cambios, inserta
     * una fila en per_hist (hla_cod, hla_fec, hla_usu, hla_ter, hla_cam).
     *
     * Para EMPRESA/SECTOR/CATEGORIA/BANCO/COMEDOR se comparan las columnas de
     * descripción denormalizadas (PER_EMD/PER_SED/PER_CAD/PER_BAD/PER_COMD), igual
     * que FoxPro, que comparaba la descripción y no el código.
     *
     * @param array<string,mixed> $antes    Valores previos a la actualización
     * @param array<string,mixed> $despues  Valores posteriores (desde BD)
     */
    private function registrarHistorialCambios(array $antes, array $despues, int $codigo, Request $request): void
    {
        // [columna, etiqueta, tipo]  — tipo: text | num | date | time
        $campos = [
            ['PER_LEG',    'LEGAJO',            'num'],
            ['PER_JSN',    'JUBILADO',          'text'],
            ['PER_NOM',    'NOMBRE',            'text'],
            ['PER_DOM',    'DOMICILIO',         'text'],
            ['PER_LOC',    'LOCALIDAD',         'text'],
            ['PER_CPA',    'COD.POSTAL',        'text'],
            ['PER_ING',    'F.INGRESO',         'date'],
            ['PER_FNA',    'F.NACIMIENTO',      'date'],
            ['PER_BAJ',    'F.BAJA',            'date'],
            ['PER_TDO',    'TIP.DOCUMENTO',     'text'],
            ['PER_NDO',    'NRO.DOCUMENTO',     'num'],
            ['PER_CUI',    'CUIL',              'text'],
            ['PER_ALM',    'ALMUERZA',          'text'],
            ['PER_COMD',   'COMEDOR',           'text'],
            ['PER_CAR',    'COMEDOR CON CARGO', 'text'],
            ['PER_AOP',    'ESTADO',            'text'],
            ['PER_TEL',    'TELEFONO',          'text'],
            ['PER_CEL',    'CELULAR',           'text'],
            ['PER_EMD',    'EMPRESA',           'text'],
            ['PER_SED',    'SECTOR',            'text'],
            ['PER_CBU',    'CBU',               'text'],
            ['PER_CAD',    'CATEGORIA',         'text'],
            ['PER_REMU',   'BASICO',            'num'],
            ['PER_HORAS',  'HORA',              'num'],
            ['PER_NREM',   'NO REMUNERADO',     'num'],
            ['PER_ANTI',   'ANTICIPOS',         'num'],
            ['PER_FUTAUT', 'FUTURO AUMENTO',    'num'],
            ['PER_SUE',    'SUELDO NETO',       'num'],
            ['PER_CHE',    'TIENE HORA EXTRA',  'text'],
            ['PER_BAD',    'BANCO',             'text'],
            ['PER_HOB',    'OBSERVACIONES',     'text'],
            ['PER_HEN',    'HORA ENTRADA',      'time'],
            ['PER_HSA',    'HORA SALIDA',       'time'],
        ];

        $texto = '';
        foreach ($campos as [$col, $label, $tipo]) {
            $viejo = $this->formatHist($antes[$col]   ?? null, $tipo);
            $nuevo = $this->formatHist($despues[$col] ?? null, $tipo);
            if ($viejo !== $nuevo) {
                $texto .= "{$label}: {$viejo} x {$nuevo}\n";
            }
        }

        // Campos que el FoxPro audita por su DESCRIPCIÓN (lookup), no por el código.
        // En personal sólo se guarda el código; se resuelve la descripción de cada
        // tabla para que el historial sea legible (igual que FoxPro).
        // [columna_codigo, etiqueta, tabla, col_clave, col_desc]
        $lookups = [
            ['PER_CON',    'CONVENIO',      'convenio',     'CON_COD',  'CON_DES'],
            ['PER_ECI',    'ESTADO CIVIL',  'estadocivil',  'ECI_COD',  'ECI_DES'],
            ['PER_LUGAR',  'LUGAR',         'lugar',        'LUG_COD',  'LUG_NOM'],
            ['PER_CONTRA', 'CONTRATISTA',   'contratista',  'CONT_COD', 'CONT_DET'],
            ['PER_GRU',    'GRUPO LABORAL', 'reloj_grupos', 'rgr_cod',  'rgr_des'],
        ];
        foreach ($lookups as [$col, $label, $tabla, $keyCol, $descCol]) {
            $codViejo = (int) ($antes[$col]   ?? 0);
            $codNuevo = (int) ($despues[$col] ?? 0);
            if ($codViejo === $codNuevo) {
                continue;
            }
            $viejo = $this->descLookup($tabla, $keyCol, $descCol, $codViejo);
            $nuevo = $this->descLookup($tabla, $keyCol, $descCol, $codNuevo);
            $texto .= "{$label}: {$viejo} x {$nuevo}\n";
        }

        if ($texto === '') {
            return; // sin cambios relevantes → no se registra historial
        }

        // Usuario logueado: NOMBRE completo (modelo Usuario, tabla usuarios).
        $u       = $request->user();
        $usuario = substr(trim((string) ($u->NOMBRE ?? $u->DATO1 ?? 'RRHH.NET')), 0, 30);

        // Terminal: nombre de la PC cliente. Como el deploy es IIS central
        // (todos acceden por navegador), NO se usa gethostname() —eso daría el
        // servidor—. Se resuelve por DNS inverso la IP del cliente.
        $terminal = substr($this->nombreTerminal($request), 0, 30);

        DB::table('per_hist')->insert([
            'hla_cod' => $codigo,
            'hla_fec' => now(),
            'hla_usu' => $usuario,
            'hla_ter' => $terminal,
            'hla_cam' => $texto,
        ]);
    }

    /**
     * Determina el nombre de la PC/terminal cliente para el historial.
     *
     * Deploy previsto: IIS en un servidor central, terminales por navegador.
     * El navegador NO expone el nombre de la PC, así que se resuelve server-side:
     *   1. Header opcional 'X-Terminal' (si una terminal lo configura explícitamente).
     *   2. DNS inverso de la IP del cliente (red Windows con registros PTR).
     *   3. Fallback: la IP tal cual.
     *
     * @return string  Nombre de la terminal (en mayúsculas) o la IP.
     */
    private function nombreTerminal(Request $request): string
    {
        return \App\Support\Terminal::nombre($request);   // DNS inverso cacheado
    }

    /**
     * Resuelve la descripción de una tabla de lookup a partir de un código.
     * Devuelve '' si el código es 0/vacío o no existe en la tabla.
     *
     * @param  string $tabla    Tabla de lookup (ej: 'convenio')
     * @param  string $keyCol   Columna clave (ej: 'CON_COD')
     * @param  string $descCol  Columna descripción (ej: 'CON_DES')
     * @param  int    $codigo   Código a resolver
     * @return string
     */
    private function descLookup(string $tabla, string $keyCol, string $descCol, int $codigo): string
    {
        if ($codigo === 0) {
            return '';
        }
        $val = DB::table($tabla)->where($keyCol, $codigo)->value($descCol);
        return trim((string) ($val ?? ''));
    }

    /**
     * Normaliza un valor para comparar/mostrar en el historial de cambios.
     *
     * @param  mixed  $v
     * @param  string $tipo  text | num | date | time
     * @return string
     */
    private function formatHist($v, string $tipo): string
    {
        $s = trim((string) ($v ?? ''));

        return match ($tipo) {
            // Número: "1234.0000" → "1234"; conserva decimales si los tiene.
            'num' => $s === '' ? '0' : (
                ((float) $s == (int) (float) $s)
                    ? (string) (int) (float) $s
                    : rtrim(rtrim(number_format((float) $s, 4, '.', ''), '0'), '.')
            ),
            // Fecha: dd/mm/yyyy; vacía o año <= 1900 → ''.
            'date' => (function () use ($s) {
                if ($s === '' || str_starts_with($s, '1900-')) return '';
                $fecha = strtotime($s);
                if ($fecha === false || (int) date('Y', $fecha) <= 1900) return '';
                return date('d/m/Y', $fecha);
            })(),
            // Hora: 4 dígitos → HH:MM; vacía/cero → ''.
            'time' => (function () use ($s) {
                $d = preg_replace('/\D/', '', $s);
                if ($d === '' || (int) $d === 0) return '';
                $d = str_pad($d, 4, '0', STR_PAD_LEFT);
                return substr($d, 0, 2) . ':' . substr($d, 2, 2);
            })(),
            // Texto: tal cual, sin espacios sobrantes.
            default => $s,
        };
    }

    /**
     * Activa o da de baja a un empleado.
     *
     * Al dar de baja (ACTIVO=0) se puede registrar la fecha y motivo.
     * Al reactivar (ACTIVO=1) se limpian esos campos.
     *
     * @route  PATCH /api/empleados/{codigo}/estado
     * @auth   Bearer token requerido
     * @body   { ACTIVO: 0|1, PER_BAJ?: date, PER_BAJ_RAZON?: string }
     * @param  int $codigo
     * @return JsonResponse
     */
    public function cambiarEstado(Request $request, int $codigo): JsonResponse
    {
        $request->validate([
            'ACTIVO'        => 'required|boolean',
            'PER_BAJ'       => 'nullable|date',
            'PER_BAJ_RAZON' => 'nullable|string|max:100',
        ]);

        $empleado = Personal::where('PER_COD', $codigo)->firstOrFail();
        $activo   = (int)$request->ACTIVO;

        if ($activo === 0) {
            // ── Validaciones antes de dar de baja (réplica FoxPro) ──

            // 1. Fecha de baja obligatoria
            $perBaj = $request->PER_BAJ;
            if (empty($perBaj) || str_starts_with((string) $perBaj, '1900-')) {
                return response()->json(['message' =>
                    'Debe colocar la FECHA DE BAJA antes de pasar el empleado a pasivo.'], 422);
            }

            // 2. Celular de la empresa sin devolver (cem_devolucion vacía/1900)
            $cel = DB::table('celular_empleados as a')
                ->join('celulares_equipos as b', 'a.cem_equipo', '=', 'b.cel_cod')
                ->where('a.cem_emp', $codigo)
                ->whereRaw("(a.cem_devolucion IS NULL OR a.cem_devolucion < '1901-01-01')")
                ->select(DB::raw('LTRIM(RTRIM(b.cel_marca)) as marca'), DB::raw('LTRIM(RTRIM(b.cel_modelo)) as modelo'))
                ->first();
            if ($cel) {
                return response()->json(['message' =>
                    "El empleado tiene un CELULAR de la empresa (" . trim("{$cel->marca} {$cel->modelo}") . "). " .
                    "Tramitar la devolución del mismo antes de dar la baja."], 422);
            }

            // 3. Tarjeta de crédito activa en el sistema de Gestión (sqlSILCAR)
            try {
                $tieneTarjeta = DB::connection('gestion')->table('TARJETAS')
                    ->where('TAR_PER', $codigo)->where('TAR_EST', 'A')->exists();
                if ($tieneTarjeta) {
                    return response()->json(['message' =>
                        "El empleado tiene una TARJETA DE CRÉDITO de SILCAR en su poder. " .
                        "Hasta que no se pase a pasivo en el sistema de Gestión, no puede darse la baja."], 422);
                }
            } catch (\Throwable $e) {
                // Igual que FoxPro: si no se puede consultar la base de Gestión, se omite el chequeo.
                \Log::warning("[cambiarEstado] tarjetas {$codigo}: " . $e->getMessage());
            }

            // Dar de baja: PER_AOP != "A" y ACTIVO = 0
            $empleado->update([
                'ACTIVO'        => 0,
                'PER_AOP'       => 'P',
                'PER_BAJ'       => $perBaj,
                'PER_BAJ_RAZON' => $request->PER_BAJ_RAZON ?? null,
            ]);
            $msg = "Empleado {$empleado->PER_NOM} dado de baja.";
        } else {
            // Reactivar: PER_AOP = "A" y ACTIVO = 1
            $empleado->update([
                'ACTIVO'        => 1,
                'PER_AOP'       => 'A',
                'PER_BAJ'       => '1900-01-01',
                'PER_BAJ_RAZON' => null,
            ]);
            $msg = "Empleado {$empleado->PER_NOM} reactivado.";
        }

        return response()->json(['message' => $msg, 'ACTIVO' => $activo]);
    }

    // ══════════════════════════════════════════════════════════
    // PESTAÑAS DE DETALLE — datos de tablas relacionadas
    // ══════════════════════════════════════════════════════════

    // ══════════════════════════════════════════════════════════
    // FOTO — SQL Server DOCUMENTOS_DIGITALES
    // ══════════════════════════════════════════════════════════

    /**
     * Devuelve la foto del empleado desde la BD DOCUMENTOS_DIGITALES (SQL Server).
     *
     * Equivalente FoxPro: Thisformset.MISrutinas.FOTO_EMPLEADO(CodEmpleado)
     *   → llama a Archivo_Digital_Recuperar("RRHH","PERSONAL_FOTO","E{cod}.JPG",tmpFile)
     *   → consulta tabla BIBLIOTECA_DIGITAL filtrando por nombre_sistema/nombre_proceso/identificacion
     *   → campo archivo_binario contiene el contenido (base64 o raw)
     *
     * Retorna: { foto: "data:image/jpeg;base64,..." } o { foto: null }
     *
     * @route GET /api/empleados/{codigo}/foto
     */
    public function foto(int $codigo): JsonResponse
    {
        // FoxPro: Thisformset.MISrutinas.FOTO_EMPLEADO(CodEmpleado)
        //   → Archivo_Digital_Recuperar("RRHH","PERSONAL_FOTO","E{cod}.JPG", tmpFile)
        try {
            $svc = new \App\Services\BibliotecaDigitalService();
            $url = $svc->archivoDigitalRecuperarDataUrl(config('rrhh.docs_sistema'), 'PERSONAL_FOTO', 'E' . $codigo . '.JPG');
            return response()->json(['foto' => $url]);
        } catch (\Throwable $e) {
            \Log::warning("[foto] empleado {$codigo}: " . $e->getMessage());
            return response()->json(['foto' => null]);
        }
    }

    /**
     * Guarda o reemplaza la foto del empleado.
     * FoxPro: Archivo_Digital_Guardar("RRHH","PERSONAL_FOTO","E{cod}.JPG", ruta, "JPG")
     * @route POST /api/empleados/{codigo}/foto
     */
    public function fotoStore(int $codigo, \Illuminate\Http\Request $request): JsonResponse
    {
        $request->validate(['imagen' => 'required|image|max:10240']);
        try {
            $file    = $request->file('imagen');
            $ext     = strtoupper($file->getClientOriginalExtension() ?: 'JPG');
            $usuario = strtoupper(auth()->user()->DATO1 ?? 'RRHH.NET');

            $svc = new \App\Services\BibliotecaDigitalService();
            $svc->archivoDigitalGuardar(config('rrhh.docs_sistema'), 'PERSONAL_FOTO', 'E' . $codigo . '.JPG', $ext,
                                        file_get_contents($file->getRealPath()), $usuario);

            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            \Log::error("[fotoStore] empleado {$codigo}: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Elimina la foto del empleado.
     * FoxPro: Archivo_Digital_Eliminar("RRHH","PERSONAL_FOTO","E{cod}.JPG")
     * @route DELETE /api/empleados/{codigo}/foto
     */
    public function fotoDestroy(int $codigo): JsonResponse
    {
        try {
            $svc = new \App\Services\BibliotecaDigitalService();
            $svc->archivoDigitalEliminar(config('rrhh.docs_sistema'), 'PERSONAL_FOTO', 'E' . $codigo . '.JPG');
            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            \Log::error("[fotoDestroy] empleado {$codigo}: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /** Tab 2: Hijos — GET */
    public function hijos(int $codigo): JsonResponse
    {
        return response()->json(
            DB::table('per_hijo')->where('PER_COD', $codigo)->orderBy('PER_HIJ')->get()
        );
    }

    /**
     * Tab 2: Hijos — GUARDAR (equivalente FoxPro: DELETE all + INSERT non-empty)
     * Body: { hijos: [{ PER_NOM, PER_FNA, PER_SIT }], PER_PADRE, PER_MADRE, PER_CONYUGE, PER_EMBSN }
     * @route PUT /api/empleados/{codigo}/hijos
     */
    public function hijosGuardar(int $codigo, \Illuminate\Http\Request $request): JsonResponse
    {
        $emp = DB::table('personal')->where('PER_COD', $codigo)->first(['PER_ING']);
        if (!$emp) return response()->json(['error' => 'Empleado no encontrado'], 404);

        DB::beginTransaction();
        try {
            // Guardar campos de familia en personal
            $camposPersonal = array_filter([
                'PER_NES'     => $request->PER_NES,
                'PER_PADRE'   => $request->PER_PADRE,
                'PER_MADRE'   => $request->PER_MADRE,
                'PER_EMBSN'   => $request->PER_EMBSN,
                'PER_EMBNOM'  => $request->PER_EMBNOM,
                'PER_EMBCBU'  => $request->PER_EMBCBU,
            ], fn($v) => $v !== null);

            if (!empty($camposPersonal)) {
                DB::table('personal')->where('PER_COD', $codigo)->update($camposPersonal);
            }

            // DELETE ALL existentes — igual que FoxPro: Scan/Delete + re-insert
            DB::table('per_hijo')->where('PER_COD', $codigo)->delete();

            // INSERT solo los que tienen nombre no vacío, con PER_HIJ secuencial
            $i = 0;
            foreach (($request->hijos ?? []) as $h) {
                $nombre = trim($h['PER_NOM'] ?? '');
                if ($nombre === '') continue;
                $i++;
                DB::table('per_hijo')->insert([
                    'PER_COD' => $codigo,
                    'PER_HIJ' => $i,
                    'PER_NOM' => $nombre,
                    'PER_FNA' => $h['PER_FNA'] ?: '1900-01-01',
                    'PER_ING' => $emp->PER_ING,   // FoxPro: Replace PER_ING With PERSONAL.PER_ING
                    'PER_SIT' => $h['PER_SIT'] ?? '',
                    'PER_NAC' => '1900-01-01',
                ]);
            }

            DB::commit();
            return response()->json(['ok' => true, 'total' => $i]);

        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error("[hijosGuardar] empleado {$codigo}: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /** Tab 5: Puestos y calificaciones (puestoempleado + puestos) */
    public function puestos(int $codigo): JsonResponse
    {
        $emp = Personal::where('PER_COD', $codigo)->firstOrFail();
        $cuil = trim((string)$emp->PER_CUI);

        $puestos = DB::table('puestoempleado')
            ->join('puestos', DB::raw('UPPER(LTRIM(RTRIM(puestos.PUE_COD)))'), '=', DB::raw('UPPER(LTRIM(RTRIM(puestoempleado.PEM_PUE)))'))
            ->leftJoin('departam', 'puestos.PUE_DEP', '=', 'departam.DEP_COD')
            ->where(DB::raw('LTRIM(RTRIM(puestoempleado.PEM_CUIL))'), $cuil)
            // Se muestran activos e inactivos (el usuario puede alternar el estado);
            // el orden deja los activos arriba para que la lógica de "puesto actual" tome uno activo.
            ->select(
                'puestos.PUE_DES as puesto',
                DB::raw('CAST(puestoempleado.PEM_FDES AS DATE) as desde'),
                DB::raw('CAST(puestoempleado.PEM_FHAS AS DATE) as hasta'),
                // PEM_ACT se guarda como char '0'/'1'; se castea a int para que el front lo lea como booleano (el string '0' es truthy en JS).
                DB::raw('CAST(puestoempleado.PEM_ACT AS INT) as activo'),
                'puestos.PUE_REP as reporta',
                'puestos.PUE_COD as codigo',
                'puestos.PUE_OBJ as objetivo',
                DB::raw('CAST(puestos.PUE_FEC AS DATE) as fecha'),
                'departam.DEP_DES as departamento'
            )
            ->orderByDesc('puestoempleado.PEM_ACT')
            ->orderByDesc('puestoempleado.PEM_FDES')
            ->get()
            // La data heredada (o joins que se multiplican) puede repetir el mismo
            // puesto; se deja una sola fila por código de puesto (la más reciente).
            ->unique(fn ($r) => strtoupper(trim((string) $r->codigo)))
            // El driver sqlsrv devuelve PEM_ACT como string '0'/'1' (aun casteado a int en SQL);
            // se fuerza a int en PHP para que el front lo lea como booleano ('0' es truthy en JS).
            ->map(function ($r) { $r->activo = (int) $r->activo; return $r; })
            ->values();

        // Tareas del puesto activo
        $codigoPuesto = $puestos->first()?->codigo ?? '';
        $tareas = collect();
        if ($codigoPuesto) {
            $tareas = DB::table('tareapue')
                ->leftJoin('frecuencia', 'tareapue.TAR_FRE', '=', 'frecuencia.FRE_COD')
                ->whereRaw('UPPER(LTRIM(RTRIM(tareapue.TAR_PUE))) = ?', [strtoupper(trim($codigoPuesto))])
                ->select('tareapue.TAR_DES as tarea', 'frecuencia.FRE_COD as frecuencia')
                ->orderBy('tareapue.TAR_DES')
                ->get();
        }

        // Subordinados directos — normalizar CUIL quitando guiones/espacios para comparar
        $subordinados = DB::table('per_sub')
            ->join('personal', 'per_sub.PSU_SUB', '=', 'personal.PER_COD')
            ->leftJoin('puestoempleado as pe2', function($j) {
                $j->whereRaw(
                    "REPLACE(REPLACE(LTRIM(RTRIM(pe2.PEM_CUIL)),'-',''),' ','') = REPLACE(REPLACE(LTRIM(RTRIM(personal.PER_CUI)),'-',''),' ','')"
                )->where('pe2.PEM_ACT', 1);
            })
            ->leftJoin('puestos as pu2', DB::raw('UPPER(LTRIM(RTRIM(pu2.PUE_COD)))'), '=', DB::raw('UPPER(LTRIM(RTRIM(pe2.PEM_PUE)))'))
            ->where('per_sub.PSU_COD', $emp->PER_COD)
            ->where(function($q) {
                $q->whereNull('personal.PER_BAJ')
                  ->orWhereRaw("CAST(personal.PER_BAJ AS DATE) = '1900-01-01'");
            })
            ->select(
                'personal.PER_NOM as nombre',
                'personal.PER_CUI as cuil',
                DB::raw("(SELECT TOP 1 pu3.PUE_DES FROM puestoempleado pe3 JOIN puestos pu3 ON UPPER(LTRIM(RTRIM(pu3.PUE_COD))) = UPPER(LTRIM(RTRIM(pe3.PEM_PUE))) WHERE REPLACE(REPLACE(LTRIM(RTRIM(pe3.PEM_CUIL)),'-',''),' ','') = REPLACE(REPLACE(LTRIM(RTRIM(personal.PER_CUI)),'-',''),' ','') AND pe3.PEM_ACT = 1 ORDER BY pe3.PEM_FDES DESC) as puesto")
            )
            ->orderBy('personal.PER_NOM')
            ->distinct()
            ->get();

        $calificaciones = DB::table('calificacion')
            ->select(DB::raw('CAST(CAL_FEC AS DATE) as CAL_FEC'), 'CAL_RES', 'CAL_NEG', 'CAL_POS', 'CAL_CUIRES', 'CAL_PUE', 'CAL_CUIL')
            ->whereRaw('UPPER(LTRIM(RTRIM(CAL_CUIL))) = ?', [strtoupper($cuil)])
            ->whereIn(DB::raw('UPPER(LTRIM(RTRIM(CAL_PUE)))'), function ($q) use ($cuil) {
                $q->select(DB::raw('UPPER(LTRIM(RTRIM(puestoempleado.PEM_PUE)))'))
                  ->from('puestoempleado')
                  ->where('puestoempleado.PEM_ACT', 1)
                  ->whereRaw('LTRIM(RTRIM(puestoempleado.PEM_CUIL)) = ?', [$cuil]);
            })
            ->orderByDesc('CAL_FEC')
            ->get();

        return response()->json([
            'puestos'       => $puestos,
            'calificaciones'=> $calificaciones,
            'tareas'        => $tareas,
            'subordinados'  => $subordinados,
        ]);
    }

    /** Eliminar calificación por clave compuesta (CAL_FEC + CAL_PUE + CAL_CUIL) */
    public function calificacionEliminar(Request $request, int $codigo): JsonResponse
    {
        $request->validate([
            'CAL_FEC'  => 'required|string',
            'CAL_PUE'  => 'required|string',
            'CAL_CUIL' => 'required|string',
        ]);

        $deleted = DB::table('calificacion')
            ->whereRaw('CAST(CAL_FEC AS DATE) = ?', [$request->CAL_FEC])
            ->whereRaw('UPPER(LTRIM(RTRIM(CAL_PUE)))  = ?', [strtoupper(trim($request->CAL_PUE))])
            ->whereRaw('UPPER(LTRIM(RTRIM(CAL_CUIL))) = ?', [strtoupper(trim($request->CAL_CUIL))])
            ->delete();

        if ($deleted === 0) {
            return response()->json(['error' => 'Calificación no encontrada'], 404);
        }

        return response()->json(['ok' => true]);
    }

    /** Tab 7: Capacitación recibida (cap_emp + capacitacion) */
    public function capacitaciones(int $codigo): JsonResponse
    {
        $items = DB::table('cap_emp')
            ->join('capacitacion', 'cap_emp.CAP_COD', '=', 'capacitacion.CAP_COD')
            ->leftJoin('eficacia', 'cap_emp.EFI_COD', '=', 'eficacia.EFI_COD')
            ->where('cap_emp.PER_COD', $codigo)
            ->select(
                'capacitacion.CAP_COD as codigo',
                'capacitacion.CAP_FEC as fecha',
                'capacitacion.CAP_CAPA as nombre',
                'cap_emp.DISER_NOM as disertante',
                'cap_emp.DURACION as duracion',
                'capacitacion.CAP_OBJE as objetivo',
                'eficacia.EFI_DES as resultado',
                'cap_emp.NO_PARTICIPO as no_participo'
            )
            ->orderByDesc('capacitacion.CAP_FEC')
            ->get();

        return response()->json($items);
    }

    /**
     * Tab 7: Documentos digitales de una capacitación (master-detail).
     * Replica FoxPro: cap_documentacion A INNER JOIN cap_empdoc B ON A.UNICO = B.cap_doc
     *   WHERE B.cap_nro = capCod AND B.cap_emp = perCod AND A.DOC_TIP='K' AND A.DOC_REF = capCod
     */
    public function capacitacionDocumentos(int $codigo, int $capCod): JsonResponse
    {
        $items = DB::table('cap_documentacion as A')
            ->join('cap_empdoc as B', 'A.UNICO', '=', 'B.cap_doc')
            ->where('B.cap_nro', $capCod)
            ->where('B.cap_emp', $codigo)
            ->where('A.DOC_TIP', 'K')
            ->where('A.DOC_REF', $capCod)
            ->orderByDesc('A.DOC_ORD')
            ->select(
                'A.DOC_ORD as nro',
                'A.DOC_TDO as tipo',
                'A.DOC_TDD as detalle',
                'A.DOC_NOM as nombre',
                'A.DOC_EXT as ext',
                'A.DOC_FEC as fecha',
                'A.DOC_CRE as creado',
                'A.DOC_OBS as observaciones',
                'A.UNICO as unico'
            )
            ->get();

        return response()->json($items);
    }

    /**
     * Tab 9: Exámenes y certificados médicos (master-detail, réplica FoxPro).
     * Grilla: Control, Tipo de Control, Fecha, Próximo, Médico, Responsable, Notas.
     * Detalle: Tipo, Enfermedad (cód + detalle) y Notas Médicas (memo EXA_NOT).
     * Los campos de descripción ya están denormalizados en la tabla examenes.
     */
    public function examenes(int $codigo): JsonResponse
    {
        $items = DB::table('examenes')
            ->where('examenes.EXA_EMP', $codigo)
            ->select(
                'examenes.UNICO as id',
                'examenes.EXA_TIP as control',                          // Control
                DB::raw('LTRIM(RTRIM(examenes.EXA_TID)) as tipo'),      // Tipo de Control
                'examenes.EXA_FEC as fecha',
                'examenes.EXA_VEN as proximo',                          // Próximo examen
                'examenes.EXA_MED as medico_cod',                       // Médico (código)
                DB::raw('LTRIM(RTRIM(examenes.EXA_MDD)) as responsable'),
                DB::raw('LTRIM(RTRIM(examenes.EXA_ENF)) as enf_cod'),   // Enfermedad (código CIE)
                DB::raw('LTRIM(RTRIM(examenes.EXA_END)) as enf_det'),   // Enfermedad (detalle)
                'examenes.EXA_NOT as notas',                            // Notas médicas (memo)
                DB::raw('LTRIM(RTRIM(examenes.EXA_COE)) as certificado') // 'C' = certificado, 'E' = examen
            )
            ->orderByDesc('examenes.EXA_FEC')
            ->get();

        return response()->json($items);
    }

    /**
     * Visualiza un documento digital de un examen (DOC_TIP='X').
     * Identificación: "M" + DOC_TDO + RIGHT("0000000000"+DOC_REF,10) + "." + EXT
     * (DOC_REF = UNICO del examen). Réplica del FoxPro examenes_agregar.
     *
     * @route GET /api/empleados/{codigo}/examenes/documentos/{id}/ver
     */
    public function examenDocumentoVisualizar(int $codigo, int $id)
    {
        $doc = DB::table('documentos')->where('UNICO', $id)->where('DOC_TIP', 'X')->first();
        if (!$doc) {
            return response()->json(['error' => 'Documento no encontrado'], 404);
        }
        $ext   = strtoupper(trim((string) $doc->DOC_EXT));
        $ident = 'M' . trim((string) $doc->DOC_TDO)
            . str_pad((string) (int) $doc->DOC_REF, 10, '0', STR_PAD_LEFT) . '.' . $ext;

        $resp = (new \App\Services\BibliotecaDigitalService())
            ->archivoDigitalVisualizar(config('rrhh.docs_sistema'), 'DOCUMENTACION', $ident);
        if ($resp === null) {
            return response()->json(['error' => 'El archivo no está en la biblioteca digital'], 404);
        }
        return $resp;
    }

    /**
     * Tab 9: Documentos digitales de un examen (master-detail).
     * Réplica FoxPro: SELECT ... FROM documentos
     *   WHERE DOC_REF = {examen.UNICO} AND DOC_TIP = 'X' ORDER BY DOC_FEC DESC
     */
    public function examenDocumentos(int $codigo, int $examId): JsonResponse
    {
        $items = DB::table('documentos')
            ->where('DOC_REF', $examId)
            ->where('DOC_TIP', 'X')
            ->orderByDesc('DOC_FEC')
            ->select(
                'DOC_ORD as nro',
                'DOC_TDO as tipo',
                'DOC_TDD as detalle',
                'DOC_NOM as nombre',
                'DOC_EXT as ext',
                'DOC_FEC as fecha',
                'DOC_CRE as creado',
                'DOC_OBS as observaciones',
                'DOC_USU as usuario',
                'UNICO as id'
            )
            ->get();

        return response()->json($items);
    }

    /** Tab 10: Historial de cambios (per_hist) */
    public function historial(int $codigo): JsonResponse
    {
        return response()->json(
            DB::table('per_hist')
                ->where('hla_cod', $codigo)
                ->orderByDesc('hla_fec')
                ->limit(200)
                ->get()
        );
    }

    /**
     * Tab Historial — elimina filas de historial seleccionadas.
     * per_hist no tiene clave única; igual que el FoxPro se identifica cada fila
     * por la combinación de hla_cod + hla_fec + hla_usu + hla_ter + hla_cam.
     * Body: { registros: [{ hla_fec, hla_usu, hla_ter, hla_cam }, ...] }
     *
     * @route DELETE /api/empleados/{codigo}/historial
     */
    public function historialEliminar(int $codigo, \Illuminate\Http\Request $request): JsonResponse
    {
        $request->validate([
            'registros'          => 'required|array|min:1',
            'registros.*.hla_fec' => 'required',
        ]);

        $borrados = 0;
        foreach ($request->registros as $r) {
            $borrados += DB::table('per_hist')
                ->where('hla_cod', $codigo)
                ->where('hla_fec', $r['hla_fec'])
                ->whereRaw('LTRIM(RTRIM(hla_usu)) = ?', [trim((string) ($r['hla_usu'] ?? ''))])
                ->whereRaw('LTRIM(RTRIM(hla_ter)) = ?', [trim((string) ($r['hla_ter'] ?? ''))])
                ->whereRaw('LTRIM(RTRIM(hla_cam)) = ?', [trim((string) ($r['hla_cam'] ?? ''))])
                ->delete();
        }

        return response()->json(['ok' => true, 'borrados' => $borrados]);
    }

    /**
     * Tab Documentación — documentos digitales del empleado (documentos DOC_TIP='E').
     * Mismas columnas que las grillas de documentos de capacitación/exámenes.
     */
    public function documentosEmp(int $codigo): JsonResponse
    {
        $items = DB::table('documentos')
            ->where('DOC_REF', $codigo)
            ->where('DOC_TIP', 'E')
            ->orderByDesc('DOC_FEC')
            ->select(
                'DOC_ORD as nro',
                'DOC_TDO as tipo',
                'DOC_TDD as detalle',
                'DOC_NOM as nombre',
                'DOC_EXT as ext',
                'DOC_FEC as fecha',
                'DOC_CRE as creado',
                'DOC_OBS as observaciones',
                'DOC_USU as usuario',
                'UNICO as id'
            )
            ->get();

        return response()->json($items);
    }

    /**
     * Tab Documentación — agrega un documento nuevo (réplica del ACEPTAR FoxPro).
     *   · DOC_ORD = MAX(DOC_ORD) global + 1
     *   · DOC_NRO = MAX(DOC_NRO del mismo DOC_TDO) + 1
     *   · Guarda el archivo en DOCUMENTOS_DIGITALES (sistema RRHH, proceso DOCUMENTACION)
     *     con identificación DOC_TDO + DOC_NRO(6) + DOC_REF(6) + .EXT
     *
     * @route POST /api/empleados/{codigo}/documentos
     */
    public function documentoAgregar(int $codigo, \Illuminate\Http\Request $request): JsonResponse
    {
        $request->validate([
            'tipo'          => 'required|string|max:2',
            'fecha'         => 'required|date',
            'observaciones' => 'nullable|string|max:60',
            'archivo'       => 'required|file|max:51200',   // 50 MB
        ]);

        Personal::where('PER_COD', $codigo)->firstOrFail();

        $tipo = strtoupper(trim($request->tipo));
        $tdd  = trim((string) DB::table('tipo_doc')->where('TDO_COD', $tipo)->value('TDO_DES'));

        $file = $request->file('archivo');
        $ext  = strtoupper($file->getClientOriginalExtension());
        // Extensiones bloqueadas (igual que FoxPro)
        if (in_array($ext, ['EXE', 'BAT', 'DLL', 'ZIP', 'RAR', 'CMD', 'CAB'], true)) {
            return response()->json(['message' => 'Extensión de archivo no válida.'], 422);
        }

        $nombre = strtoupper(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)); // sin extensión
        $tam    = $file->getSize();

        // DOC_ORD global, DOC_NRO por tipo (lógica FoxPro)
        $docOrd = (int) DB::table('documentos')->max('DOC_ORD') + 1;
        $docNro = (int) DB::table('documentos')->where('DOC_TDO', $tipo)->max('DOC_NRO') + 1;

        $ahora    = now();
        $usuario  = substr(trim((string) ($request->user()->NOMBRE ?? 'RRHH.NET')), 0, 20);
        $terminal = substr($this->nombreTerminal($request), 0, 20);

        // 1. Insertar metadata
        $unico = DB::table('documentos')->insertGetId([
            'DOC_ORD' => $docOrd,
            'DOC_TDO' => $tipo,
            'DOC_NRO' => $docNro,
            'DOC_FEC' => $request->fecha,
            'DOC_TDD' => substr($tdd, 0, 30),
            'DOC_TIP' => 'E',
            'DOC_REF' => $codigo,
            'DOC_DET' => substr('Empleado ' . $codigo, 0, 60),
            'DOC_FUL' => substr($file->getClientOriginalName(), 0, 120),
            'DOC_DIR' => '',
            'DOC_NOM' => substr($nombre, 0, 120),
            'DOC_CRE' => $ahora->format('d/m/Y H:i'),
            'DOC_TAM' => $tam,
            'DOC_KB'  => (int) round($tam / 1024),
            'DOC_EXT' => substr($ext, 0, 5),
            'DOC_UBI' => 'en SQL DOCUMENTOS DIGITALES',
            'DOC_OBS' => substr(trim((string) $request->observaciones), 0, 60),
            'DOC_TER' => $terminal,
            'DOC_USU' => $usuario,
            'DOC_GRA' => $ahora->format('d/m/y H:i'),
        ], 'UNICO');

        // 2. Guardar el archivo en la biblioteca digital
        $referencia = $tipo
            . str_pad((string) $docNro, 6, '0', STR_PAD_LEFT)
            . str_pad((string) $codigo, 6, '0', STR_PAD_LEFT)
            . '.' . $ext;
        try {
            (new \App\Services\BibliotecaDigitalService())->archivoDigitalGuardar(
                config('rrhh.docs_sistema'), 'DOCUMENTACION', $referencia, $ext,
                file_get_contents($file->getRealPath()), $usuario
            );
        } catch (\Throwable $e) {
            // Si falla el guardado físico, revertir la metadata
            DB::table('documentos')->where('UNICO', $unico)->delete();
            \Log::error("[documentoAgregar] {$codigo}: " . $e->getMessage());
            return response()->json(['message' => 'No se pudo guardar el archivo en la biblioteca digital.'], 500);
        }

        return response()->json(['ok' => true, 'id' => $unico]);
    }

    /**
     * Tab Documentación — actualiza la FECHA de los documentos editados.
     * En la grilla sólo se puede editar la fecha; "Confirmar Cambio" persiste.
     * Body: { documentos: [{ id, fecha }] }
     *
     * @route PUT /api/empleados/{codigo}/documentos
     */
    public function documentosActualizar(int $codigo, \Illuminate\Http\Request $request): JsonResponse
    {
        $request->validate([
            'documentos'          => 'array',
            'documentos.*.id'     => 'required|integer',
            'documentos.*.fecha'  => 'nullable|date',
        ]);

        $actualizados = 0;
        foreach (($request->documentos ?? []) as $d) {
            $fecha = !empty($d['fecha']) ? $d['fecha'] : '1900-01-01';
            $actualizados += DB::table('documentos')
                ->where('UNICO', $d['id'])
                ->where('DOC_REF', $codigo)
                ->where('DOC_TIP', 'E')
                ->update(['DOC_FEC' => $fecha]);
        }

        return response()->json(['ok' => true, 'actualizados' => $actualizados]);
    }

    /**
     * Construye la "Identificacion_del_Archivo" de la biblioteca digital, igual
     * que el FoxPro:
     *   ALLTRIM(DOC_TDO) + RIGHT("00000"+DOC_NRO,6) + RIGHT("00000"+DOC_REF,6) + "." + DOC_EXT
     * Ej: DOC_TDO=SA, DOC_NRO=2, DOC_REF=30022, EXT=PDF → "SA000002030022.PDF"
     */
    private function identificacionDoc(object $doc): string
    {
        $tdo = strtoupper(trim((string) $doc->DOC_TDO));
        $nro = str_pad((string) (int) $doc->DOC_NRO, 6, '0', STR_PAD_LEFT);
        $ref = str_pad((string) (int) $doc->DOC_REF, 6, '0', STR_PAD_LEFT);
        $ext = strtoupper(trim((string) $doc->DOC_EXT));
        return $tdo . $nro . $ref . '.' . $ext;
    }

    /**
     * Tab Documentación — visualiza un documento (PDF/imagen inline, resto descarga).
     * El archivo vive en DOCUMENTOS_DIGITALES (sistema RRHH, proceso DOCUMENTACION).
     *
     * @route GET /api/empleados/{codigo}/documentos/{id}/ver
     */
    public function documentoVisualizar(int $codigo, int $id)
    {
        $doc = DB::table('documentos')
            ->where('UNICO', $id)->where('DOC_REF', $codigo)->where('DOC_TIP', 'E')
            ->first();
        if (!$doc) {
            return response()->json(['error' => 'Documento no encontrado'], 404);
        }

        $svc  = new \App\Services\BibliotecaDigitalService();
        $resp = $svc->archivoDigitalVisualizar(config('rrhh.docs_sistema'), 'DOCUMENTACION', $this->identificacionDoc($doc));

        if ($resp === null) {
            return response()->json(['error' => 'El archivo no está en la biblioteca digital'], 404);
        }
        return $resp;
    }

    /**
     * Tab Documentación — elimina un documento: primero el archivo digital de la
     * biblioteca (Archivo_Digital_Eliminar) y luego el registro de la tabla
     * documentos. Réplica del FoxPro.
     *
     * @route DELETE /api/empleados/{codigo}/documentos/{id}
     */
    public function documentoEliminar(int $codigo, int $id): JsonResponse
    {
        $doc = DB::table('documentos')
            ->where('UNICO', $id)->where('DOC_REF', $codigo)->where('DOC_TIP', 'E')
            ->first();
        if (!$doc) {
            return response()->json(['ok' => false], 404);
        }

        // 1. Eliminar el archivo de la biblioteca digital (si existe).
        try {
            (new \App\Services\BibliotecaDigitalService())
                ->archivoDigitalEliminar(config('rrhh.docs_sistema'), 'DOCUMENTACION', $this->identificacionDoc($doc));
        } catch (\Throwable $e) {
            \Log::warning("[documentoEliminar] archivo digital {$id}: " . $e->getMessage());
        }

        // 2. Eliminar la metadata.
        DB::table('documentos')->where('UNICO', $id)->delete();

        return response()->json(['ok' => true]);
    }

    /** Tab 12: Centro de costo (per_costo) */
    public function centrosCosto(int $codigo): JsonResponse
    {
        $items = DB::table('per_costo')->where('PER_COD', $codigo)->orderBy('PER_DES')->get();
        return response()->json([
            'items'            => $items,
            'total_porcentaje' => $items->sum('PER_POR'),
        ]);
    }

    /**
     * Centro de Costo — períodos de distribución del empleado (grilla izquierda).
     * Distinct (PER_MES, PER_ANO) de per_costo, ordenado por período desc.
     * @route GET /api/empleados/{codigo}/centros-costo/periodos
     */
    public function centrosCostoPeriodos(int $codigo): JsonResponse
    {
        $periodos = DB::table('per_costo')
            ->where('PER_COD', $codigo)
            ->select('PER_MES as mes', 'PER_ANO as ano', 'PER_MYA as mya')
            ->distinct()
            ->orderByDesc('PER_MYA')
            ->get();

        return response()->json($periodos);
    }

    /**
     * Centro de Costo — desagregado por centros de un período (grilla derecha).
     * @route GET /api/empleados/{codigo}/centros-costo/detalle/{mya}
     */
    public function centrosCostoDetalle(int $codigo, string $mya): JsonResponse
    {
        // Agrupado por centro de costo: si en la base quedaron filas duplicadas
        // (de cargas anteriores), se consolidan en una sola sumando el porcentaje,
        // para no mostrar centros repetidos.
        $items = DB::table('per_costo')
            ->where('PER_COD', $codigo)
            ->where('PER_MYA', $mya)
            ->groupBy('PER_CCO', DB::raw('LTRIM(RTRIM(PER_DES))'))
            ->orderBy(DB::raw('LTRIM(RTRIM(PER_DES))'))
            ->get([
                'PER_CCO as cod',
                DB::raw('LTRIM(RTRIM(PER_DES)) as descripcion'),
                DB::raw('SUM(PER_POR) as porcentaje'),
            ]);

        return response()->json($items);
    }

    /**
     * Centro de Costo — catálogo de centros activos (base GESTION = sqlSILCAR).
     * Réplica: tabla CCOSTO (CCO_COD, CCO_DES) WHERE CCO_EST='A'.
     * @route GET /api/empleados/centros-costo-catalogo
     */
    public function centrosCostoCatalogo(): JsonResponse
    {
        try {
            $items = DB::connection('gestion')->table('CCOSTO')
                ->where('CCO_EST', 'A')
                ->orderBy('CCO_DES')
                ->get([
                    'CCO_COD as cod',
                    DB::raw('LTRIM(RTRIM(CCO_DES)) as descripcion'),
                ]);
            return response()->json($items);
        } catch (\Throwable $e) {
            \Log::warning('[ccosto-catalogo] ' . $e->getMessage());
            return response()->json([]);
        }
    }

    /**
     * Centro de Costo — CONFIRMAR período (réplica FoxPro).
     * Borra los per_costo de (empleado, mes, año) y reinserta los ítems enviados.
     * Body: { mes, ano, items: [{ cod, descripcion, porcentaje }] }
     * @route PUT /api/empleados/{codigo}/centros-costo
     */
    public function centrosCostoGuardar(int $codigo, Request $request): JsonResponse
    {
        $datos = $request->validate([
            'mes'               => 'required|integer|min:1|max:12',
            'ano'               => 'required|integer|min:1900|max:2999',
            'items'             => 'present|array',
            'items.*.cod'       => 'required',
            'items.*.descripcion' => 'nullable|string',
            'items.*.porcentaje'  => 'required|numeric|min:0',
        ]);

        $emp = DB::table('personal')->where('PER_COD', $codigo)->first(['PER_NOM']);
        if (!$emp) return response()->json(['error' => 'El código del empleado no es válido.'], 404);

        // No permitir centros de costo duplicados en el mismo período
        $cods = array_map(fn ($i) => (string) $i['cod'], $datos['items']);
        if (count($cods) !== count(array_unique($cods))) {
            return response()->json(['error' => 'Hay centros de costo duplicados en el período.'], 422);
        }

        // La suma de porcentajes no puede superar el 100%
        $totalPor = array_sum(array_map(fn ($i) => (float) $i['porcentaje'], $datos['items']));
        if ($totalPor > 100.001) {
            return response()->json(['error' => 'La suma de porcentajes no puede superar el 100%.'], 422);
        }

        $mes = (int) $datos['mes'];
        $ano = (int) $datos['ano'];
        $mya = $ano . str_pad((string) $mes, 2, '0', STR_PAD_LEFT);
        $nom = substr(trim((string) $emp->PER_NOM), 0, 50);

        DB::beginTransaction();
        try {
            DB::table('per_costo')
                ->where('PER_COD', $codigo)
                ->where('PER_MES', $mes)
                ->where('PER_ANO', $ano)
                ->delete();

            foreach ($datos['items'] as $it) {
                DB::table('per_costo')->insert([
                    'PER_COD' => $codigo,
                    'PER_NOM' => $nom,
                    'PER_TIP' => ' ',
                    'PER_MES' => $mes,
                    'PER_ANO' => $ano,
                    'PER_MYA' => $mya,
                    'PER_CCO' => $it['cod'],
                    'PER_DES' => substr(trim((string) ($it['descripcion'] ?? '')), 0, 50),
                    'PER_POR' => $it['porcentaje'],
                ]);
            }

            DB::commit();
            return response()->json(['ok' => true, 'mya' => $mya, 'total' => count($datos['items'])]);
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error("[centrosCostoGuardar] {$codigo}: " . $e->getMessage());
            return response()->json(['error' => 'No se pudo confirmar el período: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Tab Personal a Cargo — selector de empleados (réplica FoxPro).
     * Lista TODOS los empleados activos (PER_AOP='A') por nombre y marca los que
     * ya son personal a cargo del empleado actual (per_sub.PSU_COD = codigo).
     */
    public function subordinados(int $codigo): JsonResponse
    {
        $empleados = DB::table('personal')
            ->where('PER_AOP', 'A')
            ->orderBy('PER_NOM')
            ->get([
                'PER_COD as cod',
                DB::raw('LTRIM(RTRIM(PER_NOM)) as nom'),
                'PER_LEG as leg',
            ]);

        $asignados = DB::table('per_sub')
            ->where('PSU_COD', $codigo)
            ->pluck('PSU_SUB')
            ->map(fn ($v) => (int) $v)
            ->all();

        return response()->json(['empleados' => $empleados, 'asignados' => $asignados]);
    }

    /**
     * Informe general de personal a cargo (toda la empresa). Réplica FoxPro:
     *   SELECT A.PER_COD,A.PER_LEG,A.PER_NOM,B.PSU_SUB,B.PSU_SUN
     *   FROM personal A INNER JOIN per_sub B ON A.PER_COD = B.PSU_COD
     *   WHERE A.PER_AOP='A' ORDER BY A.PER_NOM, B.PSU_SUN
     *
     * @route GET /api/empleados/personal-a-cargo-informe
     */
    public function personalACargoInforme(): JsonResponse
    {
        $rows = DB::table('personal as A')
            ->join('per_sub as B', 'A.PER_COD', '=', 'B.PSU_COD')
            ->where('A.PER_AOP', 'A')
            ->orderBy('A.PER_NOM')->orderBy('B.PSU_SUN')
            ->get([
                'A.PER_COD as jefe_cod',
                'A.PER_LEG as jefe_leg',
                DB::raw('LTRIM(RTRIM(A.PER_NOM)) as jefe_nom'),
                'B.PSU_SUB as sub_cod',
                DB::raw('LTRIM(RTRIM(B.PSU_SUN)) as sub_nom'),
            ]);

        return response()->json($rows);
    }

    /**
     * Listado de empleados — réplica del listado FoxPro (LISTA DE EMPLEADOS).
     *
     * Filtros (todos opcionales; ausencia = "todos"):
     *   estado       1=activos (default) | 2=dados de baja
     *   orden        1=alfabético (default) | 2=código | 3=ingreso | 4=día/mes nac. | 5=fecha baja
     *   empresa, contratista, lugar, convenio, categoria, sector, subsector  → código a filtrar
     *   sexo         F | M | N
     *   prueba       1=sí (en período de prueba: sin contratista y < 90 días)
     *   prestaciones 1=sí (con contratista y > 90 días)
     *
     * @route GET /api/empleados/listado
     */
    public function listado(Request $request): JsonResponse
    {
        $estado = (int) $request->input('estado', 1);
        $orden  = (int) $request->input('orden', 1);

        $q = DB::table('personal as p')
            ->leftJoin('convenio as c', 'c.CON_COD', '=', 'p.PER_CON')
            ->leftJoin('categori as t', 't.CAT_COD', '=', 'p.PER_CAT')
            ->where('p.PER_AOP', $estado === 2 ? 'P' : 'A');

        // Filtros por código (solo si vienen con valor)
        $filtros = [
            'empresa'     => 'p.PER_EMP',
            'contratista' => 'p.PER_CONTRA',
            'lugar'       => 'p.PER_LUGAR',
            'convenio'    => 'p.PER_CON',
            'categoria'   => 'p.PER_CAT',
            'sector'      => 'p.PER_SEC',
            'subsector'   => 'p.PER_SUC',
        ];
        foreach ($filtros as $param => $col) {
            $val = $request->input($param);
            if ($val !== null && $val !== '' && $val !== 'todos') {
                $q->where($col, $val);
            }
        }

        // Sexo
        $sexo = $request->input('sexo');
        if (in_array($sexo, ['F', 'M', 'N'], true)) {
            $q->where('p.PER_SEX', $sexo);
        }

        // Período de prueba / prestaciones (ING + 90 días vs hoy)
        if ((int) $request->input('prueba', 0) === 1) {
            $q->where('p.PER_CONTRA', 0)
              ->whereRaw('DATEADD(DAY, 90, p.PER_ING) > CAST(GETDATE() AS DATE)');
        }
        if ((int) $request->input('prestaciones', 0) === 1) {
            $q->where('p.PER_CONTRA', '<>', 0)
              ->whereRaw('DATEADD(DAY, 90, p.PER_ING) < CAST(GETDATE() AS DATE)');
        }

        // Empleados sin puestos asignados: no tienen un puesto activo en puestoempleado
        // (la asignación se vincula por CUIL = PER_CUI, que puede venir con guiones o espacios).
        // PEM_ACT es char '0'/'1': se compara como texto, porque contra un int SQL Server intenta
        // convertir la columna y falla si algún registro quedó en blanco.
        if ((int) $request->input('sin_puesto', 0) === 1) {
            $q->whereNotExists(function ($sub) {
                $sub->select(DB::raw('1'))->from('puestoempleado as pe')
                    ->whereRaw("LTRIM(RTRIM(CAST(pe.PEM_ACT AS VARCHAR(5)))) = '1'")
                    ->whereRaw("REPLACE(REPLACE(LTRIM(RTRIM(pe.PEM_CUIL)),'-',''),' ','') = REPLACE(REPLACE(LTRIM(RTRIM(p.PER_CUI)),'-',''),' ','')");
            });
        }

        // Orden
        switch ($orden) {
            case 2: $q->orderBy('p.PER_COD'); break;
            case 3: $q->orderBy('p.PER_ING'); break;
            case 4: $q->orderByRaw('MONTH(p.PER_FNA), DAY(p.PER_FNA)'); break;
            case 5: $q->orderBy('p.PER_BAJ')->orderBy('p.PER_NOM'); break;
            default: $q->orderBy('p.PER_NOM');
        }

        $rows = $q->get([
            'p.PER_COD as cod',
            DB::raw('LTRIM(RTRIM(p.PER_NOM)) as nombre'),
            'p.PER_LEG as legajo',
            'p.PER_FNA as nacimiento',
            'p.PER_ING as ingreso',
            'p.PER_FEFECTIVO as efectivo',
            'p.PER_BAJ as baja',
            DB::raw('LTRIM(RTRIM(p.PER_NDO)) as dni'),
            DB::raw('LTRIM(RTRIM(p.PER_CUI)) as cuit'),
            DB::raw('LTRIM(RTRIM(p.PER_DOM)) as domicilio'),
            DB::raw('LTRIM(RTRIM(p.PER_TEL)) as telefono'),
            DB::raw('LTRIM(RTRIM(p.PER_CEL)) as celular'),
            'p.PER_SEX as sexo',
            DB::raw('LTRIM(RTRIM(p.PER_EMD)) as empresa'),
            DB::raw('LTRIM(RTRIM(COALESCE(c.CON_DES, p.PER_CDE))) as convenio'),
            DB::raw('LTRIM(RTRIM(COALESCE(t.CAT_DES, p.PER_CAD))) as categoria'),
            DB::raw('LTRIM(RTRIM(p.PER_SED)) as sector'),
            DB::raw('LTRIM(RTRIM(p.PER_SUD)) as subsector'),
            DB::raw('LTRIM(RTRIM(p.PER_MSD)) as msi'),
        ]);

        return response()->json([
            'titulo' => 'LISTA DE EMPLEADOS' . ($estado === 2 ? ' Dados de Baja' : ''),
            'total'  => $rows->count(),
            'rows'   => $rows,
        ]);
    }

    /**
     * Exportar a Excel — arma el dataset completo de empleados (réplica FoxPro
     * "exportar a excel"). Filtra por empresa y estado laboral, resuelve todas las
     * descripciones (empresa, sector, convenio, etc.) y calcula los campos
     * derivados (hijos, valor hora, sueldo bruto, puestos activos, etc.).
     * El frontend elige qué columnas exportar, su orden y los índices de ordenamiento.
     *
     * @route GET /api/empleados/exportar?empresa=&estado=
     */
    public function exportarDatos(Request $request): JsonResponse
    {
        $empresa = $request->input('empresa');
        $estado  = (int) $request->input('estado', 1);   // 1 todos · 2 activos · 3 pasivos

        $q = DB::table('personal as A')
            ->leftJoin('empresas',     'empresas.EMP_COD',     '=', 'A.PER_EMP')
            ->leftJoin('contratista',  'contratista.CONT_COD', '=', 'A.PER_CONTRA')
            ->leftJoin('lugar',        'lugar.LUG_COD',        '=', 'A.PER_LUGAR')
            ->leftJoin('sector',       'sector.SEC_COD',       '=', 'A.PER_SEC')
            ->leftJoin('subsector',    'subsector.SUB_COD',    '=', 'A.PER_SUC')
            ->leftJoin('convenio',     'convenio.CON_COD',     '=', 'A.PER_CON')
            ->leftJoin('categori',     'categori.CAT_COD',     '=', 'A.PER_CAT')
            ->leftJoin('comedor',      'comedor.COME_COD',     '=', 'A.PER_COMN')
            ->leftJoin('estadocivil',  'estadocivil.ECI_COD',  '=', 'A.PER_ECI')
            ->leftJoin('ctas_ban',     'ctas_ban.CBA_COD',     '=', 'A.PER_BAN')
            ->leftJoin('reloj_grupos', 'reloj_grupos.RGR_COD', '=', 'A.PER_GRU');

        if ($empresa !== null && $empresa !== '' && $empresa !== 'todos') {
            $q->where('A.PER_EMP', $empresa);
        }
        if ($estado === 2)      $q->where('A.PER_AOP', 'A');
        elseif ($estado === 3)  $q->where('A.PER_AOP', 'P');

        $rows = $q->orderBy('A.PER_NOM')->get([
            'A.PER_COD', 'A.PER_LEG', 'A.PER_JSN', 'A.PER_NOM', 'A.PER_DOM', 'A.PER_LOC', 'A.PER_CPA',
            'A.PER_ING', 'A.PER_FNA', 'A.PER_BAJ', 'A.PER_FEFECTIVO', 'A.PER_TDO', 'A.PER_NDO', 'A.PER_CUI',
            'A.PER_ALM', 'A.PER_CAR', 'A.PER_AOP', 'A.PER_TEL', 'A.PER_CEL', 'A.PER_CONTACTO', 'A.PER_CBU',
            'A.PER_REMU', 'A.PER_HORAS', 'A.PER_NREM', 'A.PER_ANTI', 'A.PER_FUTAUT', 'A.PER_SUE', 'A.PER_CHE',
            'A.PER_HEN', 'A.PER_HSA', 'A.PER_HOB', 'A.per_descuento', 'A.PER_SEX', 'A.PER_NES', 'A.PER_PADRE',
            'A.PER_MADRE', 'A.PER_FACAD',
            DB::raw('LTRIM(RTRIM(comedor.COME_DES)) as COME_DES'),
            DB::raw('LTRIM(RTRIM(estadocivil.ECI_DES)) as ECI_DES'),
            DB::raw('LTRIM(RTRIM(empresas.EMP_NOM)) as EMP_NOM'),
            DB::raw('LTRIM(RTRIM(contratista.CONT_DET)) as CONT_DET'),
            DB::raw('LTRIM(RTRIM(lugar.LUG_NOM)) as LUG_NOM'),
            DB::raw('LTRIM(RTRIM(sector.SEC_DES)) as SEC_DES'),
            DB::raw('LTRIM(RTRIM(subsector.SUB_DES)) as SUB_DES'),
            DB::raw('LTRIM(RTRIM(convenio.CON_DES)) as CON_DES'),
            DB::raw('LTRIM(RTRIM(categori.CAT_DES)) as CAT_DES'),
            DB::raw('LTRIM(RTRIM(ctas_ban.CBA_DES)) as CBA_DES'),
            DB::raw('LTRIM(RTRIM(reloj_grupos.RGR_DES)) as RGR_DES'),
        ]);

        if ($rows->isEmpty()) {
            return response()->json([]);
        }

        // ── Hijos (concatenados) por empleado ──
        $codigos = $rows->pluck('PER_COD')->all();
        $hijosMap = [];
        foreach (DB::table('per_hijo')->whereIn('PER_COD', $codigos)
                    ->orderBy('PER_COD')->orderBy('PER_HIJ')
                    ->get(['PER_COD', 'PER_NOM', 'PER_FNA', 'PER_SIT']) as $h) {
            $nom = trim((string) $h->PER_NOM);
            if ($nom === '') continue;
            $fec = $this->fechaDdmmyyyy($h->PER_FNA);
            $txt = strtoupper($nom) . '(' . $fec . ') ' . trim((string) $h->PER_SIT);
            $hijosMap[$h->PER_COD] = isset($hijosMap[$h->PER_COD])
                ? $hijosMap[$h->PER_COD] . ',' . trim($txt)
                : trim($txt);
        }

        // ── Puestos activos (concatenados) por CUIL ──
        $cuils = $rows->map(fn ($r) => trim((string) $r->PER_CUI))->filter()->unique()->values()->all();
        $puestosMap = [];
        if (!empty($cuils)) {
            $puestos = DB::table('puestoempleado as pe')
                ->join('puestos as pu', DB::raw('UPPER(LTRIM(RTRIM(pu.PUE_COD)))'), '=', DB::raw('UPPER(LTRIM(RTRIM(pe.PEM_PUE)))'))
                ->where('pe.PEM_ACT', 1)
                ->whereIn(DB::raw('LTRIM(RTRIM(pe.PEM_CUIL))'), $cuils)
                ->orderByDesc('pe.PEM_ACT')->orderByDesc('pe.PEM_FDES')
                ->get([DB::raw('LTRIM(RTRIM(pe.PEM_CUIL)) as cuil'), DB::raw('LTRIM(RTRIM(pu.PUE_DES)) as puesto')]);
            foreach ($puestos as $p) {
                $des = trim((string) $p->puesto);
                if ($des === '') continue;
                $puestosMap[$p->cuil] = isset($puestosMap[$p->cuil])
                    ? $puestosMap[$p->cuil] . ' / ' . $des
                    : $des;
            }
        }

        // ── Armado final de cada fila (estructura DATOS_EMPLEADOS) ──
        $out = $rows->map(function ($r) use ($hijosMap, $puestosMap) {
            $horas = (float) $r->PER_HORAS;
            $sue   = (float) $r->PER_SUE;
            $desc  = (float) $r->per_descuento;
            $cuil  = trim((string) $r->PER_CUI);

            return [
                'CODIGO'         => (int) $r->PER_COD,
                'LEGAJO'         => trim((string) $r->PER_LEG),
                'CONYUGE'        => trim((string) $r->PER_NES),
                'MADRE'          => trim((string) $r->PER_MADRE),
                'PADRE'          => trim((string) $r->PER_PADRE),
                'HIJOS'          => $hijosMap[$r->PER_COD] ?? '',
                'JUBILADO'       => $this->siNo($r->PER_JSN),
                'NOMBRE'         => trim((string) $r->PER_NOM),
                'SEXO'           => trim((string) $r->PER_SEX),
                'DOMICILIO'      => trim((string) $r->PER_DOM),
                'LOCALIDAD'      => trim((string) $r->PER_LOC),
                'POSTAL'         => trim((string) $r->PER_CPA),
                'F_INGRESO'      => $this->fechaDdmmyyyy($r->PER_ING),
                'F_EFECTIVO'     => $this->fechaDdmmyyyy($r->PER_FEFECTIVO),
                'F_NACIMIENTO'   => $this->fechaDdmmyyyy($r->PER_FNA),
                'F_BAJA'         => $this->fechaDdmmyyyy($r->PER_BAJ),
                'TIP_DOC'        => trim((string) $r->PER_TDO),
                'NRO_DOCUMENTO'  => trim((string) $r->PER_NDO),
                'CUIL'           => $cuil,
                'ALMUERZA'       => $this->siNo($r->PER_ALM),
                'CCOMEDOR'       => trim((string) $r->COME_DES),
                'CON_CARGO'      => $this->siNo($r->PER_CAR),
                'ESTADO'         => ($r->PER_AOP === 'A') ? 'ACTIVO' : 'PASIVO',
                'TELEFONOS'      => trim((string) $r->PER_TEL),
                'CELULAR'        => trim((string) $r->PER_CEL),
                'CONTACTO'       => trim((string) $r->PER_CONTACTO),
                'EST_CIVIL'      => trim((string) $r->ECI_DES),
                'EMPRESA'        => trim((string) $r->EMP_NOM),
                'CONTRATA'       => trim((string) $r->CONT_DET),
                'LUGAR'          => trim((string) $r->LUG_NOM),
                'SECTOR'         => trim((string) $r->SEC_DES),
                'SUBSECTOR'      => trim((string) $r->SUB_DES),
                'CBU'            => trim((string) $r->PER_CBU),
                'CONVENIO'       => trim((string) $r->CON_DES),
                'CATEGORIA'      => trim((string) $r->CAT_DES),
                'BASICO'         => (float) $r->PER_REMU,
                'HORAS'          => (float) $horas,
                'VALOR_HORA'     => $horas > 0 ? round($sue / $horas, 2) : 0,
                'NOREMU'         => (float) $r->PER_NREM,
                'ANTICIPOS'      => (float) $r->PER_ANTI,
                'FUTURO_AUMENTO' => (float) $r->PER_FUTAUT,
                'SUELDO_NETO'    => $sue,
                'SUELDO_BRUTO'   => (100 - $desc) != 0 ? round($sue / ((100 - $desc) / 100), 2) : 0,
                'HORAS_EXTRAS'   => $this->siNo($r->PER_CHE),
                'VALOR_H_EXTRA'  => 0,
                'BANCO'          => trim((string) $r->CBA_DES),
                'GRUPO_LABORAL'  => trim((string) $r->RGR_DES),
                'H_ENTRADA'      => $this->horaHhmm($r->PER_HEN),
                'H_SALIDA'       => $this->horaHhmm($r->PER_HSA),
                'OBSERVACIONES'  => trim((string) $r->PER_HOB),
                'PUESTOS'        => $puestosMap[$cuil] ?? '',
                'FACADEMICA'     => trim((string) $r->PER_FACAD),
            ];
        });

        return response()->json($out->values());
    }

    /**
     * Importar datos Convenio — actualiza empleados desde un Excel mapeado.
     * El frontend lee el Excel, el usuario indica en qué columna está cada dato
     * y envía las filas ya mapeadas. Acá se actualiza cada empleado por su código.
     *
     * Body: { items: [{ codigo, convenio?, categoria?, afiliado? }] }
     *   Solo se actualizan los campos presentes en cada ítem (los que se mapearon).
     *
     * @route POST /api/empleados/importar-convenio
     */
    public function importarConvenio(Request $request): JsonResponse
    {
        $request->validate([
            'items'            => 'required|array|min:1',
            'items.*.codigo'   => 'required',
        ]);

        // Catálogos para resolver la descripción denormalizada (cód → desc)
        $convMap = [];
        foreach (DB::table('convenio')->get(['CON_COD', 'CON_DES']) as $r) {
            $convMap[trim((string) $r->CON_COD)] = trim((string) $r->CON_DES);
        }
        $catMap = [];
        foreach (DB::table('categori')->get(['CAT_COD', 'CAT_DES']) as $r) {
            $catMap[trim((string) $r->CAT_COD)] = trim((string) $r->CAT_DES);
        }

        // Datos para el historial (usuario + terminal), una sola vez
        $u        = $request->user();
        $usuario  = substr(trim((string) ($u->NOMBRE ?? $u->DATO1 ?? 'RRHH.NET')), 0, 30);
        $terminal = substr($this->nombreTerminal($request), 0, 30);

        $actualizados = 0;
        $noEncontrados = [];

        DB::beginTransaction();
        try {
            foreach ($request->items as $it) {
                $cod = (int) ($it['codigo'] ?? 0);
                if ($cod <= 0) continue;   // fila sin código válido (ej. encabezado)

                $emp = DB::table('personal')->where('PER_COD', $cod)
                    ->first(['PER_NOM', 'PER_CON', 'PER_CAT', 'PER_AFILIADO']);
                if (!$emp) { $noEncontrados[] = $cod; continue; }

                // Valores efectivos: el importado si vino mapeado, si no el actual
                $convMapeado = isset($it['convenio'])  && $it['convenio']  !== '';
                $catMapeado  = isset($it['categoria']) && $it['categoria'] !== '';
                $afiMapeado  = array_key_exists('afiliado', $it) && $it['afiliado'] !== null && $it['afiliado'] !== '';

                $nuevoCon = $convMapeado ? (int) $it['convenio']  : (int) $emp->PER_CON;
                $nuevoCat = $catMapeado  ? (int) $it['categoria'] : (int) $emp->PER_CAT;
                $nuevoAfi = $afiMapeado  ? $this->normalizarAfiliado($it['afiliado']) : (int) $emp->PER_AFILIADO;

                // PER_DESCUENTO (regla FoxPro): convenio 3 → 17; afiliado → 21; resto → 19
                $descuento = $nuevoCon === 3 ? 17 : ($nuevoAfi === 1 ? 21 : 19);

                $upd = [
                    'PER_CON'       => $nuevoCon,
                    'PER_CAT'       => $nuevoCat,
                    'PER_AFILIADO'  => $nuevoAfi,
                    'PER_DESCUENTO' => $descuento,
                ];
                // Mantener descripciones denormalizadas consistentes
                if ($convMapeado) $upd['PER_CDE'] = $convMap[(string) $nuevoCon] ?? '';
                if ($catMapeado)  $upd['PER_CAD'] = $catMap[(string) $nuevoCat] ?? '';

                // Historial (réplica del texto FoxPro: antes → después)
                $txt = 'Importación Convenio, Categoria y Afiliacion : '
                     . "Convenio {$nuevoCon},Categoria {$nuevoCat},Afiliado " . ($nuevoAfi === 1 ? 'SI' : 'NO')
                     . ' (antes era Convenio ' . (int) $emp->PER_CON . ',Categoria ' . (int) $emp->PER_CAT
                     . ',Afiliado ' . (((int) $emp->PER_AFILIADO) === 1 ? 'SI' : 'NO') . ')';

                DB::table('per_hist')->insert([
                    'hla_cod' => $cod,
                    'hla_fec' => now(),
                    'hla_usu' => $usuario,
                    'hla_ter' => $terminal,
                    'hla_cam' => $txt,
                ]);

                DB::table('personal')->where('PER_COD', $cod)->update($upd);
                $actualizados++;
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('[importarConvenio] ' . $e->getMessage());
            return response()->json(['error' => 'No se pudo completar la importación: ' . $e->getMessage()], 500);
        }

        return response()->json([
            'actualizados'           => $actualizados,
            'no_encontrados'         => count($noEncontrados),
            'codigos_no_encontrados' => array_slice($noEncontrados, 0, 100),
            'total'                  => count($request->items),
        ]);
    }

    /**
     * Exportar datos específicos (solapa 2 del módulo Importar/Exportar Convenio).
     * Empleados ACTIVOS con Código, Nombre, Código de Convenio, Código de Categoría
     * y Afiliado (SI/NO). Filtros opcionales por empresa, contratista, lugar,
     * sector, sub-sector, convenio y categoría.
     *
     * @route GET /api/empleados/exportar-convenio
     */
    public function exportarConvenio(Request $request): JsonResponse
    {
        $q = DB::table('personal')->where('PER_AOP', 'A');

        $filtros = [
            'empresa'     => 'PER_EMP',
            'contratista' => 'PER_CONTRA',
            'lugar'       => 'PER_LUGAR',
            'sector'      => 'PER_SEC',
            'subsector'   => 'PER_SUC',
            'convenio'    => 'PER_CON',
            'categoria'   => 'PER_CAT',
        ];
        foreach ($filtros as $param => $col) {
            $val = $request->input($param);
            if ($val !== null && $val !== '' && $val !== 'todos') {
                $q->where($col, $val);
            }
        }

        $rows = $q->orderBy('PER_NOM')->get([
            'PER_COD as codigo',
            DB::raw('LTRIM(RTRIM(PER_NOM)) as nombre'),
            'PER_CON as codigo_convenio',
            'PER_CAT as codigo_categoria',
            DB::raw("CASE WHEN PER_AFILIADO = 1 THEN 'SI' ELSE 'NO' END as afiliado"),
        ]);

        return response()->json($rows);
    }

    // ══════════════════════════════════════════════════════════
    //  IMPORTAR COSTOS OBRAS SOCIALES (3 solapas)
    // ══════════════════════════════════════════════════════════

    /**
     * Solapa 1 — Importar costo de obra social desde Excel (por CUIL o por DNI).
     * Por cada fila busca al empleado (CUIL/DNI) y hace UPSERT en per_obrasocial
     * para el período (mes/año): PLAN→PEO_NET, APORTE→PEO_DEB, PEO_DIF=NET-DEB.
     * Registra historial por empleado.
     *
     * Body: { mes, anio, obra (nombre), planilla (1 PLAN | 2 APORTE),
     *         por ('cuil'|'dni'), items: [{ clave, importe }] }
     * @route POST /api/empleados/obrasocial/importar
     */
    public function obraSocialImportar(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'mes'             => 'required|integer|min:1|max:12',
            'anio'            => 'required|integer|min:1900|max:2999',
            'obra'            => 'required|string',
            'planilla'        => 'required|integer|in:1,2',
            'por'             => 'required|in:cuil,dni',
            'items'           => 'required|array|min:1',
            'items.*.clave'   => 'required',
            'items.*.importe' => 'required|numeric',
        ]);

        $mes = (int) $datos['mes']; $anio = (int) $datos['anio'];
        $obra = substr(strtoupper(trim($datos['obra'])), 0, 50);
        $planilla = (int) $datos['planilla'];
        $por = $datos['por'];

        $u        = $request->user();
        $usuario  = substr(trim((string) ($u->NOMBRE ?? $u->DATO1 ?? 'RRHH.NET')), 0, 30);
        $terminal = substr($this->nombreTerminal($request), 0, 30);

        // Preindexar empleados por CUIL (solo dígitos) y por DNI
        $byCuil = []; $byDni = [];
        foreach (DB::table('personal')->get(['PER_COD', 'PER_NOM', 'PER_CUI', 'PER_NDO']) as $p) {
            $cd = preg_replace('/\D/', '', (string) $p->PER_CUI);
            if ($cd !== '' && (int) $cd > 0) $byCuil[(string) (int) $cd][] = $p;
            $dn = (int) $p->PER_NDO;
            if ($dn > 0) $byDni[(string) $dn][] = $p;
        }

        $total = 0; $encontrados = 0;
        DB::beginTransaction();
        try {
            foreach ($datos['items'] as $it) {
                $total++;
                $importe = (float) $it['importe'];
                $k = preg_replace('/\D/', '', (string) $it['clave']);
                if ($k === '' || (int) $k <= 0) continue;
                $matches = $por === 'cuil' ? ($byCuil[(string) (int) $k] ?? []) : ($byDni[(string) (int) $k] ?? []);

                foreach ($matches as $emp) {
                    $encontrados++;
                    $cod = (int) $emp->PER_COD;
                    $existe = DB::table('per_obrasocial')
                        ->where('PEO_COD', $cod)->where('PEO_MES', $mes)->where('PEO_ANO', $anio)
                        ->first(['UNICO', 'PEO_NET', 'PEO_DEB']);

                    DB::table('per_hist')->insert([
                        'hla_cod' => $cod, 'hla_fec' => now(), 'hla_usu' => $usuario, 'hla_ter' => $terminal,
                        'hla_cam' => 'Importación Costo Obra Social : ' . ($planilla === 1 ? 'PLAN' : 'APORTE'),
                    ]);

                    if ($existe) {
                        $net = $planilla === 1 ? $importe : (float) $existe->PEO_NET;
                        $deb = $planilla === 2 ? $importe : (float) $existe->PEO_DEB;
                        DB::table('per_obrasocial')->where('UNICO', $existe->UNICO)->update([
                            'PEO_OBRA' => $obra, 'PEO_NET' => $net, 'PEO_DEB' => $deb, 'PEO_DIF' => $net - $deb,
                        ]);
                    } else {
                        $net = $planilla === 1 ? $importe : 0;
                        $deb = $planilla === 2 ? $importe : 0;
                        DB::table('per_obrasocial')->insert([
                            'PEO_COD' => $cod, 'PEO_NOM' => substr(trim((string) $emp->PER_NOM), 0, 50),
                            'PEO_CUI' => substr(trim((string) $emp->PER_CUI), 0, 13), 'PEO_OBRA' => $obra,
                            'PEO_MES' => $mes, 'PEO_ANO' => $anio,
                            'PEO_NET' => $net, 'PEO_DEB' => $deb, 'PEO_DIF' => $net - $deb,
                        ]);
                    }
                }
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('[obraSocialImportar] ' . $e->getMessage());
            return response()->json(['error' => 'No se pudo importar: ' . $e->getMessage()], 500);
        }

        return response()->json(['total' => $total, 'encontrados' => $encontrados]);
    }

    /**
     * Solapa 2 — Historial de costos de obra social (todo per_obrasocial) + totales.
     * @route GET /api/empleados/obrasocial/historial
     */
    public function obrasSocialesHistorial(): JsonResponse
    {
        $rows = DB::table('per_obrasocial')
            ->orderByDesc('PEO_ANO')->orderByDesc('PEO_MES')->orderBy('PEO_NOM')
            ->get([
                'PEO_COD as cod',
                DB::raw('LTRIM(RTRIM(PEO_NOM)) as nombre'),
                DB::raw('LTRIM(RTRIM(PEO_CUI)) as cuil'),
                DB::raw('LTRIM(RTRIM(PEO_OBRA)) as obra'),
                'PEO_MES as mes', 'PEO_ANO as anio',
                'PEO_NET as neto', 'PEO_DEB as aporte', 'PEO_DIF as diferencia',
            ]);

        $neto = $rows->sum('neto'); $aporte = $rows->sum('aporte');
        return response()->json([
            'rows'     => $rows,
            'totales'  => ['neto' => $neto, 'aporte' => $aporte, 'diferencia' => $neto - $aporte],
        ]);
    }

    /**
     * Solapa 3 — Informe de obras sociales del período.
     * Empleados activos (opcional por convenio) con su dato de obra social del mes
     * (PLAN/APORTE/DIFERENCIA), el promedio de horas extras del último semestre y
     * el costo real estimado.
     *
     * @route GET /api/empleados/obrasocial/informe?mes=&anio=&obra=&convenio=
     */
    public function obrasSocialesInforme(Request $request): JsonResponse
    {
        $mes = (int) $request->input('mes'); $anio = (int) $request->input('anio');
        $obra     = strtoupper(trim((string) $request->input('obra', '')));
        $convenio = strtoupper(trim((string) $request->input('convenio', '')));

        // Personal activo (opcional filtrado por convenio = descripción PER_CDE)
        $persQ = DB::table('personal')->where('PER_AOP', 'A');
        if ($convenio !== '') $persQ->whereRaw('UPPER(LTRIM(RTRIM(PER_CDE))) = ?', [$convenio]);
        $pers = $persQ->orderBy('PER_NOM')->get(['PER_COD', 'PER_NOM', 'PER_SUE', 'PER_CDE', 'PER_CUI']);

        // Datos de obra social del período (opcional filtrado por obra)
        $osQ = DB::table('per_obrasocial')->where('PEO_MES', $mes)->where('PEO_ANO', $anio);
        if ($obra !== '') $osQ->whereRaw('UPPER(LTRIM(RTRIM(PEO_OBRA))) = ?', [$obra]);
        $osByCod = [];
        foreach ($osQ->get(['PEO_COD', 'PEO_OBRA', 'PEO_NET', 'PEO_DEB', 'PEO_DIF']) as $r) {
            $osByCod[(int) $r->PEO_COD] = $r;
        }

        // Promedio de horas extras del último semestre (liquidaciones tipo 4)
        $codes = $pers->pluck('PER_COD')->map(fn ($v) => (int) $v)->all();
        $promByCod = [];
        if (!empty($codes)) {
            $f2 = sprintf('%04d-%02d-01', $anio, $mes);
            $f1 = now()->subDays(180)->format('Y-m-d');
            $monthly = DB::table('liquidac as l')
                ->join('liq_ite as li', 'l.LIQ_NRO', '=', 'li.LIT_NRO')
                ->where('l.LIQ_TIP', 4)
                ->whereBetween('l.LIQ_FEC', [$f1, $f2 . ' 23:59:59'])
                ->whereIn('l.LIQ_COD', $codes)
                ->groupBy('l.LIQ_COD', 'li.LIT_PER')
                ->get(['l.LIQ_COD as cod', DB::raw('SUM(li.LIT_HAB - li.LIT_DED) as monto')]);
            $acc = [];
            foreach ($monthly as $m) $acc[(int) $m->cod][] = (float) $m->monto;
            foreach ($acc as $cod => $arr) $promByCod[$cod] = count($arr) ? array_sum($arr) / count($arr) : 0;
        }

        $out = [];
        foreach ($pers as $p) {
            $cuil = trim((string) $p->PER_CUI);
            if ($cuil === '') continue;
            $cod = (int) $p->PER_COD;
            $o = $osByCod[$cod] ?? null;
            $net = $o ? (float) $o->PEO_NET : 0;
            $deb = $o ? (float) $o->PEO_DEB : 0;
            $dif = $o ? (float) $o->PEO_DIF : 0;
            $sueldo = (float) $p->PER_SUE;
            $out[] = [
                'cuil'        => $cuil,
                'nombre'      => trim((string) $p->PER_NOM),
                'convenio'    => trim((string) $p->PER_CDE),
                'obra'        => $o ? trim((string) $o->PEO_OBRA) : '',
                'sueldo_neto' => $sueldo,
                'prom_extra'  => round($promByCod[$cod] ?? 0, 2),
                'plan'        => $net,
                'aporte'      => $deb,
                'diferencia'  => $dif,
                'costo_real'  => round($sueldo + ($dif > 0 ? $dif : 0), 2),
            ];
        }

        return response()->json($out);
    }

    /** Normaliza un valor de "afiliado" del Excel a 0/1. */
    private function normalizarAfiliado($v): int
    {
        $s = strtoupper(trim((string) $v));
        return in_array($s, ['1', 'S', 'SI', 'SÍ', 'X', 'TRUE', 'VERDADERO', 'AFILIADO', 'Y', 'YES'], true) ? 1 : 0;
    }

    /** Convierte 'S'/'N' (FoxPro) a SI/NO. */
    private function siNo($v): string
    {
        return strtoupper(trim((string) $v)) === 'S' ? 'SI' : 'NO';
    }

    /** Formatea una fecha a dd/mm/yyyy; vacía si es nula o 1900-01-01. */
    private function fechaDdmmyyyy($v): string
    {
        $s = substr((string) $v, 0, 10);
        if ($s === '' || $s === '1900-01-01') return '';
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $s, $m)) return "{$m[3]}/{$m[2]}/{$m[1]}";
        return $s;
    }

    /** Formatea una hora "HHMM" (FoxPro Transform 99:99) a HH:MM. */
    private function horaHhmm($v): string
    {
        $n = preg_replace('/\D/', '', (string) $v);
        if ($n === '' || (int) $n === 0) return '';
        $n = str_pad($n, 4, '0', STR_PAD_LEFT);
        return substr($n, 0, 2) . ':' . substr($n, 2, 2);
    }

    /**
     * Legajo Histórico — réplica del reporte FoxPro "legajo_historico".
     * Reúne, para UN empleado, todos los datasets del legajo: datos personales,
     * hijos, puestos asignados, perfil del puesto actual (cualidades, educación,
     * tareas, competencias), entregas de ropa, capacitación recibida,
     * apercibimientos y el celular asignado vigente.
     *
     * @route GET /api/empleados/{codigo}/legajo
     */
    public function legajoHistorico(int $codigo): JsonResponse
    {
        $emp = Personal::where('PER_COD', $codigo)->first();
        if (!$emp) {
            return response()->json(['error' => 'El código del empleado no es válido.'], 404);
        }
        $cuil = trim((string) $emp->PER_CUI);

        // Ejecuta cada bloque de forma aislada: si una tabla/columna no existe,
        // devuelve [] en vez de romper todo el legajo.
        $safe = function (callable $fn) use ($codigo) {
            try { return $fn(); }
            catch (\Throwable $e) { \Log::warning("[legajo] {$codigo}: " . $e->getMessage()); return collect(); }
        };

        // Estado civil (descripción)
        $estadoCivil = $safe(fn () => optional(
            DB::table('estadocivil')->where('ECI_COD', $emp->PER_ECI)->first(['ECI_DES'])
        )->ECI_DES) ?: '';
        if ($estadoCivil instanceof \Illuminate\Support\Collection) $estadoCivil = '';

        // Hijos
        $hijos = $safe(fn () => DB::table('per_hijo')->where('PER_COD', $codigo)
            ->orderBy('PER_HIJ')
            ->get([
                DB::raw('LTRIM(RTRIM(PER_NOM)) as nombre'),
                'PER_FNA as nacimiento',
                DB::raw('LTRIM(RTRIM(PER_SIT)) as situacion'),
            ]));

        // Puestos asignados
        $puestos = $safe(fn () => DB::table('puestoempleado')
            ->join('puestos', DB::raw('UPPER(LTRIM(RTRIM(puestos.PUE_COD)))'), '=', DB::raw('UPPER(LTRIM(RTRIM(puestoempleado.PEM_PUE)))'))
            ->leftJoin('departam', 'puestos.PUE_DEP', '=', 'departam.DEP_COD')
            ->where(DB::raw('LTRIM(RTRIM(puestoempleado.PEM_CUIL))'), $cuil)
            ->orderByDesc('puestoempleado.PEM_ACT')
            ->orderByDesc('puestoempleado.PEM_FDES')
            ->get([
                DB::raw('LTRIM(RTRIM(puestos.PUE_DES)) as puesto'),
                DB::raw('CAST(puestoempleado.PEM_FDES AS DATE) as desde'),
                DB::raw('CAST(puestoempleado.PEM_FHAS AS DATE) as hasta'),
                // PEM_ACT es char '0'/'1'; se castea a int (el string '0' es truthy en JS).
                DB::raw('CAST(puestoempleado.PEM_ACT AS INT) as activo'),
                DB::raw('LTRIM(RTRIM(puestos.PUE_REP)) as reporta'),
                DB::raw('LTRIM(RTRIM(puestos.PUE_COD)) as codigo'),
                DB::raw('LTRIM(RTRIM(puestos.PUE_OBJ)) as objetivo',),
                DB::raw('LTRIM(RTRIM(departam.DEP_DES)) as departamento'),
            ])->map(function ($r) { $r->activo = (int) $r->activo; return $r; }));

        $actual = strtoupper(trim((string) ($puestos->first()->codigo ?? '')));

        // Perfil del puesto actual
        $cualidades = $actual === '' ? collect() : $safe(fn () => DB::table('cualidad')
            ->whereRaw('UPPER(LTRIM(RTRIM(cua_pue))) = ?', [$actual])
            ->orderBy('cua_des')->get([DB::raw('LTRIM(RTRIM(cua_des)) as descripcion')]));

        $educacion = $actual === '' ? collect() : $safe(fn () => DB::table('educacion')
            ->whereRaw('UPPER(LTRIM(RTRIM(edu_pue))) = ?', [$actual])
            ->orderBy('edu_des')->get([DB::raw('LTRIM(RTRIM(edu_des)) as descripcion')]));

        $tareas = $actual === '' ? collect() : $safe(fn () => DB::table('tareapue')
            ->leftJoin('frecuencia', 'tareapue.TAR_FRE', '=', 'frecuencia.FRE_COD')
            ->whereRaw('UPPER(LTRIM(RTRIM(tareapue.tar_pue))) = ?', [$actual])
            ->orderBy('tareapue.tar_des')
            ->get([
                DB::raw('LTRIM(RTRIM(tareapue.tar_des)) as descripcion'),
                DB::raw('LTRIM(RTRIM(frecuencia.FRE_DES)) as frecuencia'),
            ]));

        $competencias = $actual === '' ? collect() : $safe(fn () => DB::table('competencia')
            ->whereRaw('UPPER(LTRIM(RTRIM(comp_pue))) = ?', [$actual])
            ->orderBy('comp_des')->get([DB::raw('LTRIM(RTRIM(comp_des)) as descripcion')]));

        // Entregas de ropa
        $ropas = $safe(fn () => DB::table('entregaropa')
            ->where(DB::raw('LTRIM(RTRIM(ENR_CUIL))'), $cuil)
            ->orderByDesc('ENR_FEC')
            ->get([
                DB::raw('CAST(ENR_FEC AS DATE) as fecha'),
                'ENR_CAN as cantidad',
                DB::raw('LTRIM(RTRIM(ENR_DES)) as ropa'),
                DB::raw('LTRIM(RTRIM(ENR_MOT)) as motivo'),
            ]));

        // Capacitación recibida
        $capacitaciones = $safe(fn () => DB::table('cap_emp')
            ->join('capacitacion', 'cap_emp.CAP_COD', '=', 'capacitacion.CAP_COD')
            ->leftJoin('tema', 'capacitacion.CAP_TEM_COD', '=', 'tema.TEM_COD')
            ->leftJoin('eficacia', 'cap_emp.EFI_COD', '=', 'eficacia.EFI_COD')
            ->where('cap_emp.PER_COD', $codigo)
            ->orderByDesc('capacitacion.CAP_FEC')
            ->get([
                'capacitacion.CAP_COD as codigo',
                'capacitacion.CAP_FEC as fecha',
                DB::raw('LTRIM(RTRIM(capacitacion.CAP_CAPA)) as capacitacion'),
                DB::raw('LTRIM(RTRIM(cap_emp.DISER_NOM)) as disertante'),
                'cap_emp.DURACION as duracion',
                DB::raw('LTRIM(RTRIM(capacitacion.CAP_OBJE)) as objetivo'),
                DB::raw('LTRIM(RTRIM(tema.TEM_DES)) as temario'),
                DB::raw('LTRIM(RTRIM(eficacia.EFI_DES)) as resultado'),
            ]));

        // Apercibimientos
        $apercibimientos = $safe(fn () => DB::table('per_apercibimientos')
            ->where('APE_COD', $codigo)->orderBy('APE_FEC')->get());

        // Celular asignado vigente (no devuelto)
        $celular = $safe(fn () => DB::table('celular_empleados as a')
            ->join('celulares_equipos as b', 'a.cem_equipo', '=', 'b.cel_cod')
            ->where('a.cem_emp', $codigo)
            ->where(function ($q) {
                $q->whereNull('a.cem_devolucion')
                  ->orWhereRaw("CAST(a.cem_devolucion AS DATE) = '1900-01-01'");
            })
            ->orderByDesc('a.cem_entrega')
            ->first([
                'a.cem_nrocelular as numero',
                DB::raw('LTRIM(RTRIM(b.cel_marca)) as marca'),
                DB::raw('LTRIM(RTRIM(b.cel_modelo)) as modelo'),
            ]));

        return response()->json([
            'empleado'        => $this->normalizarNulos($emp),
            'estado_civil'    => $estadoCivil,
            'hijos'           => $hijos,
            'puestos'         => $puestos,
            'puesto_actual'   => $puestos->first(),
            'cualidades'      => $cualidades,
            'educacion'       => $educacion,
            'tareas'          => $tareas,
            'competencias'    => $competencias,
            'ropas'           => $ropas,
            'capacitaciones'  => $capacitaciones,
            'apercibimientos' => $apercibimientos,
            'celular'         => $celular,
        ]);
    }

    /**
     * Tab Personal a Cargo — guarda el set de personal a cargo (GRABAR).
     * Réplica FoxPro: borra los per_sub del jefe e inserta los marcados.
     * Body: { asignados: [PER_COD, ...] }
     *
     * @route PUT /api/empleados/{codigo}/subordinados
     */
    public function subordinadosGuardar(int $codigo, \Illuminate\Http\Request $request): JsonResponse
    {
        $request->validate([
            'asignados'   => 'array',
            'asignados.*' => 'integer',
        ]);

        $ids = $request->asignados ?? [];

        DB::beginTransaction();
        try {
            DB::table('per_sub')->where('PSU_COD', $codigo)->delete();

            if (!empty($ids)) {
                $nombres = DB::table('personal')->whereIn('PER_COD', $ids)->pluck('PER_NOM', 'PER_COD');
                $rows = [];
                foreach ($ids as $sub) {
                    $rows[] = [
                        'PSU_COD' => $codigo,
                        'PSU_SUB' => $sub,
                        'PSU_SUN' => substr(trim((string) ($nombres[$sub] ?? '')), 0, 50),
                    ];
                }
                DB::table('per_sub')->insert($rows);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error("[subordinadosGuardar] {$codigo}: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }

        return response()->json(['ok' => true, 'total' => count($ids)]);
    }

    /**
     * Tab Faltas — master grid (reloj_faltas_diarias + licencias).
     * con_doc = 1 si la falta tiene documentación respaldatoria (verde), 0 si no (rojo).
     */
    public function faltas(int $codigo): JsonResponse
    {
        $items = DB::table('reloj_faltas_diarias')
            ->where('AFD_PER', $codigo)
            ->select(
                'AFD_LIC as lic',
                DB::raw('LTRIM(RTRIM(AFD_LID)) as detalle'),   // descripción denormalizada (FoxPro)
                'AFD_FE1 as desde',
                'AFD_FE2 as hasta',
                'AFD_OBS as observaciones',
                'UNICO as id',
                DB::raw("(CASE WHEN EXISTS (SELECT 1 FROM documentos d WHERE d.DOC_TIP='L' AND d.DOC_REF = reloj_faltas_diarias.UNICO) THEN 1 ELSE 0 END) as con_doc")
            )
            ->orderByDesc('AFD_FE1')
            ->get();

        return response()->json($items);
    }

    /**
     * Tab Faltas — documentación respaldatoria de una falta.
     * documentos WHERE DOC_TIP='L' AND DOC_REF = {falta.UNICO}.
     *
     * @route GET /api/empleados/{codigo}/faltas/{faltaId}/documentos
     */
    public function faltaDocumentos(int $codigo, int $faltaId): JsonResponse
    {
        $items = DB::table('documentos')
            ->where('DOC_REF', $faltaId)
            ->where('DOC_TIP', 'L')
            ->orderByDesc('DOC_FEC')
            ->select(
                'DOC_ORD as nro',
                'DOC_TDO as tipo',
                'DOC_TDD as detalle',
                'DOC_NOM as nombre',
                'DOC_EXT as ext',
                'DOC_FEC as fecha',
                'DOC_CRE as creado',
                'DOC_OBS as observaciones',
                'UNICO as id'
            )
            ->get();

        return response()->json($items);
    }

    /**
     * Tab Faltas — visualiza un documento respaldatorio (proceso LICENCIAS).
     * Identificación: DOC_ORD + DOC_NRO(5) + DOC_REF(6) + EXT  (sin punto).
     *
     * @route GET /api/empleados/{codigo}/faltas/documentos/{id}/ver
     */
    public function faltaDocumentoVisualizar(int $codigo, int $id)
    {
        $doc = DB::table('documentos')->where('UNICO', $id)->where('DOC_TIP', 'L')->first();
        if (!$doc) {
            return response()->json(['error' => 'Documento no encontrado'], 404);
        }

        $ext   = strtoupper(trim((string) $doc->DOC_EXT));
        $ident = (string) (int) $doc->DOC_ORD
            . str_pad((string) (int) $doc->DOC_NRO, 5, '0', STR_PAD_LEFT)
            . str_pad((string) (int) $doc->DOC_REF, 6, '0', STR_PAD_LEFT)
            . $ext;

        $svc  = new \App\Services\BibliotecaDigitalService();
        $resp = $svc->archivoDigitalVisualizar(config('rrhh.docs_sistema'), 'LICENCIAS', $ident);

        if ($resp === null) {
            return response()->json(['error' => 'El archivo no está en la biblioteca digital'], 404);
        }
        return $resp;
    }

    /**
     * Tab Celular — equipos del empleado (réplica FoxPro).
     * Rojo + texto blanco si tiene fecha de devolución (equipo devuelto).
     */
    public function celulares(int $codigo): JsonResponse
    {
        $items = DB::table('celular_empleados as a')
            ->join('celulares_equipos as b', 'a.cem_equipo', '=', 'b.cel_cod')
            ->where('a.cem_emp', $codigo)
            ->orderByDesc('a.cem_equipo')
            ->select(
                'a.cem_nrocelular as numero',                         // # Línea
                'b.cel_cod as cod',
                DB::raw('LTRIM(RTRIM(b.cel_imei)) as imei'),
                DB::raw('LTRIM(RTRIM(b.cel_marca)) as marca'),
                DB::raw('LTRIM(RTRIM(b.cel_modelo)) as modelo'),
                DB::raw('LTRIM(RTRIM(b.cel_color)) as color'),
                'b.cel_pantalla as pulgadas',
                DB::raw('LTRIM(RTRIM(b.cel_sistema)) as sistema'),
                DB::raw("CASE WHEN b.cel_cargador = 1 THEN 'SI' ELSE '' END as cargador"),
                DB::raw("CASE WHEN b.cel_auricular = 1 THEN 'SI' ELSE '' END as auricular"),
                DB::raw("CASE WHEN b.cel_cableusb = 1 THEN 'SI' ELSE '' END as cableusb"),
                'a.cem_entrega as fecha_entrega',
                DB::raw('LTRIM(RTRIM(a.cem_obsentrega)) as obs_entrega'),
                'a.cem_devolucion as fecha_devolucion',
                DB::raw('LTRIM(RTRIM(a.cem_obsdevolu)) as obs_devolucion'),
                'a.unico as id'
            )
            ->get();

        return response()->json($items);
    }

    /**
     * Tab Tarjetas — tarjetas del empleado (base GESTION = sqlSILCAR).
     * Réplica FoxPro:
     *   SELECT TAR_DES,TAR_NT1,TAR_NT2,TAR_NT3,TAR_NTA,TAR_CBD FROM TARJETAS
     *   WHERE TAR_PER={cod} AND TAR_EST='A'
     */
    public function tarjetas(int $codigo): JsonResponse
    {
        try {
            $items = DB::connection('gestion')->table('TARJETAS')
                ->where('TAR_PER', $codigo)
                ->where('TAR_EST', 'A')
                ->select(
                    DB::raw('LTRIM(RTRIM(TAR_DES)) as descripcion'),
                    'TAR_NT1 as nt1',
                    'TAR_NT2 as nt2',
                    'TAR_NT3 as nt3',
                    'TAR_NTA as nt4',
                    DB::raw('LTRIM(RTRIM(TAR_CBD)) as cuenta')
                )
                ->get();
            return response()->json($items);
        } catch (\Throwable $e) {
            \Log::warning("[tarjetas] empleado {$codigo}: " . $e->getMessage());
            return response()->json([]);
        }
    }

    /** Tab 6: Gestión de ropa/uniforme (entregaropa) */
    public function ropa(int $codigo): JsonResponse
    {
        $emp  = Personal::where('PER_COD', $codigo)->firstOrFail();
        $cuil = trim((string)$emp->PER_CUI);

        $items = DB::table('entregaropa as a')
            ->leftJoin('ropa as b', DB::raw('LTRIM(RTRIM(a.ENR_ROP))'), '=', DB::raw('LTRIM(RTRIM(b.ROP_COD))'))
            ->where(DB::raw('LTRIM(RTRIM(a.ENR_CUIL))'), $cuil)
            ->select(
                DB::raw('CAST(a.ENR_FEC AS DATE) as fecha'),
                'a.ENR_CAN as cantidad',
                'a.ENR_DES as descripcion',
                'a.ENR_MOT as motivo',
                'a.ENR_MAD as marca',
                'a.ENR_TAD as talle',
                'a.ENR_STK as stk',
                'a.ENR_ROP as rop_cod',
                'a.ENR_CER as certificado',
                'a.ENR_MAC as marca2',
                'a.ENR_TAL as talle2',
                DB::raw("CASE WHEN b.ROP_OBSEQUIO = 1 OR UPPER(LTRIM(RTRIM(CAST(b.ROP_OBSEQUIO AS VARCHAR)))) = 'T' THEN 1 ELSE 0 END as obsequio")
            )
            ->orderByDesc('a.ENR_FEC')
            ->get();

        return response()->json($items);
    }

    /** Datos adicionales para constancia Resolución 299/2011 */
    public function constanciaRopa(int $codigo): JsonResponse
    {
        $emp  = Personal::where('PER_COD', $codigo)->firstOrFail();
        $cuil = trim((string)$emp->PER_CUI);

        // Empresa
        $empresa = DB::table('empresas')
            ->where('EMP_COD', $emp->PER_EMP)
            ->select(
                DB::raw('LTRIM(RTRIM(EMP_NOM)) as nombre'),
                DB::raw('LTRIM(RTRIM(EMP_CUI)) as cuit'),
                DB::raw('LTRIM(RTRIM(EMP_DOM)) as domicilio'),
                DB::raw('LTRIM(RTRIM(EMP_LOC)) as localidad'),
                DB::raw('LTRIM(RTRIM(EMP_CPO)) as cp'),
                DB::raw('LTRIM(RTRIM(EMP_PRV)) as provincia')
            )
            ->first();

        // Puestos actuales del empleado
        $puestos = DB::table('puestoempleado')
            ->join('puestos', DB::raw('UPPER(LTRIM(RTRIM(puestos.PUE_COD)))'), '=', DB::raw('UPPER(LTRIM(RTRIM(puestoempleado.PEM_PUE)))'))
            ->where(DB::raw('LTRIM(RTRIM(puestoempleado.PEM_CUIL))'), $cuil)
            ->orderByDesc('puestoempleado.PEM_ACT')
            ->orderByDesc('puestoempleado.PEM_FDES')
            ->select('puestos.PUE_DES as puesto', DB::raw('CAST(puestoempleado.PEM_ACT AS INT) as activo'))
            ->get()
            ->map(function ($r) { $r->activo = (int) $r->activo; return $r; });

        // Elementos de protección requeridos por los puestos del empleado
        $elementos = DB::table('puestoempleado')
            ->join('puestos', DB::raw('UPPER(LTRIM(RTRIM(puestos.PUE_COD)))'), '=', DB::raw('UPPER(LTRIM(RTRIM(puestoempleado.PEM_PUE)))'))
            ->join('elementos', DB::raw('UPPER(LTRIM(RTRIM(elementos.ELE_PUE)))'), '=', DB::raw('UPPER(LTRIM(RTRIM(puestos.PUE_COD)))'))
            ->where(DB::raw('LTRIM(RTRIM(puestoempleado.PEM_CUIL))'), $cuil)
            ->orderBy('elementos.ELE_COD')
            ->select('elementos.ELE_DES as descripcion', 'elementos.ELE_COD as codigo')
            ->distinct()
            ->get();

        return response()->json([
            'empresa'   => $empresa,
            'puestos'   => $puestos,
            'elementos' => $elementos,
        ]);
    }

    /**
     * Tab EPP Asignada — stock actual de EPP del empleado (tabla PER_ROPA).
     * Réplica FoxPro:
     *   SELECT PERO_RCOD, PERO_RDES, PERO_RTAD FROM PER_ROPA
     *   WHERE PERO_COD = {PER_COD} ORDER BY PERO_RDES
     */
    public function eppAsignada(int $codigo): JsonResponse
    {
        $items = DB::table('PER_ROPA')
            ->where('PERO_COD', $codigo)
            ->orderBy('PERO_RDES')
            ->select(
                'PERO_RCOD as codigo',
                DB::raw('LTRIM(RTRIM(PERO_RDES)) as descripcion'),
                DB::raw('LTRIM(RTRIM(PERO_RTAD)) as talle')
            )
            ->get();

        return response()->json($items);
    }

    /** Tab 3: Historial de obra social (per_obrasocial) */
    public function obraSocialHistorial(int $codigo): JsonResponse
    {
        return response()->json(
            DB::table('per_obrasocial')
                ->select('PEO_OBRA', 'PEO_MES', 'PEO_ANO', 'PEO_NET', 'PEO_DEB', 'PEO_DIF')
                ->where('PEO_COD', $codigo)
                ->orderByDesc('PEO_ANO')
                ->orderByDesc('PEO_MES')
                ->get()
        );
    }

    // ══════════════════════════════════════════════════════════
    // HELPERS
    // ══════════════════════════════════════════════════════════

    /**
     * Convierte los campos NULL de un empleado a valores neutros según su tipo.
     *
     * Convención heredada de FoxPro:
     *   - Char / texto   → '' (string vacío)
     *   - Numérico       → 0
     *   - Boolean        → false
     *   - Fecha          → '1900-01-01'  (fecha "vacía" en FoxPro)
     *
     * De este modo el frontend nunca recibe null y puede mostrar los campos
     * correctamente sin tener que manejar nulos por todas partes.
     *
     * @param  Personal $empleado
     * @return array<string, mixed>
     */
    private function normalizarNulos(Personal $empleado): array
    {
        $data  = $empleado->toArray();
        $casts = $empleado->getCasts();

        foreach ($data as $campo => $valor) {
            if (!is_null($valor)) {
                continue;
            }

            $tipo = strtolower($casts[$campo] ?? 'string');

            $data[$campo] = match (true) {
                str_starts_with($tipo, 'int')                             => 0,
                str_starts_with($tipo, 'decimal') || $tipo === 'float'
                    || $tipo === 'double' || $tipo === 'real'             => 0,
                $tipo === 'boolean' || $tipo === 'bool'                   => false,
                str_contains($tipo, 'date') || str_contains($tipo, 'time') => '1900-01-01',
                default                                                    => '',
            };
        }

        return $data;
    }

    /**
     * Reemplaza valores null en un array de datos por los defaults FoxPro
     * correspondientes al tipo de cada campo del modelo Personal.
     *
     * Se usa en store() para que un INSERT nunca escriba NULLs.
     * Para update() se usa array_filter() porque preferimos omitir campos
     * no enviados en lugar de pisarlos con defaults.
     *
     * @param  array<string, mixed> $datos
     * @return array<string, mixed>
     */
    private function sanitizarParaGuardar(array $datos): array
    {
        $casts = (new Personal())->getCasts();
        // Tipos reales de las columnas en SQL Server. Muchas columnas numéricas
        // (PER_SUC, PER_EPR, etc.) no están declaradas en $casts; sin esto, un
        // campo numérico vacío se rellenaba con '' y SQL Server fallaba al insertar
        // ("converting nvarchar to numeric"). Con el tipo real defaulteamos a 0.
        $sqlTipos = $this->tiposColumnasPersonal();

        foreach ($datos as $campo => &$valor) {
            if (!is_null($valor)) {
                continue;
            }
            $tipo    = strtolower($casts[$campo] ?? 'string');
            $sqlTipo = $sqlTipos[strtoupper($campo)] ?? '';

            $esNumero = str_starts_with($tipo, 'int')
                || str_starts_with($tipo, 'decimal')
                || in_array($tipo, ['float', 'double', 'real'], true)
                || in_array($sqlTipo, ['int', 'bigint', 'smallint', 'tinyint', 'numeric', 'decimal', 'money', 'smallmoney', 'float', 'real'], true);
            $esBool  = $tipo === 'boolean' || $tipo === 'bool' || $sqlTipo === 'bit';
            $esFecha = str_contains($tipo, 'date') || str_contains($tipo, 'time')
                || str_contains($sqlTipo, 'date') || str_contains($sqlTipo, 'time');

            $valor = match (true) {
                $esNumero => 0,
                $esBool   => false,
                $esFecha  => '1900-01-01',
                default   => '',
            };
        }
        unset($valor);

        return $datos;
    }

    /**
     * Mapa COLUMNA => tipo SQL real de la tabla personal (cacheado por request).
     * Se usa para saber qué default aplicar a los campos no enviados por el front,
     * ya que el modelo no declara $casts para todas las columnas numéricas.
     *
     * @return array<string, string>
     */
    private function tiposColumnasPersonal(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $cache = [];
        try {
            $rows = DB::select(
                "SELECT COLUMN_NAME, DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'personal'"
            );
            foreach ($rows as $r) {
                $cache[strtoupper($r->COLUMN_NAME)] = strtolower($r->DATA_TYPE);
            }
        } catch (\Throwable $e) {
            $cache = [];
        }
        return $cache;
    }

    /**
     * Valida y retorna los datos del request.
     * Se usa tanto en store() como en update().
     *
     * @param  Request $request
     * @param  int|null $codigoActual  Para ignorar duplicados en edición
     * @return array<string, mixed>
     */
    private function validar(Request $request, ?int $codigoActual = null): array
    {
        return $request->validate([
            // ── Datos personales ──────────────────────────────
            'PER_NOM'      => 'required|string|max:50',
            'PER_LEG'      => 'nullable|integer',
            'PER_SEX'      => 'nullable|string|max:1',
            'PER_FNA'      => 'nullable|date',
            'PER_LNA'      => 'nullable|string|max:50',
            'PER_TDO'      => 'nullable|string|max:3',
            'PER_NDO'      => 'nullable|integer',
            'PER_CUI'      => 'nullable|string|max:13',
            'PER_ECI'      => 'nullable|integer',
            'PER_HIJ'      => 'nullable|integer|min:0',
            'PER_HIM'      => 'nullable|integer|min:0',
            'PER_HID'      => 'nullable|integer|min:0',
            'PER_DOM'      => 'nullable|string|max:50',
            'PER_LOC'      => 'nullable|string|max:50',
            'PER_CPA'      => 'nullable|string|max:15',
            'PER_TEL'      => 'nullable|string|max:50',
            'PER_CEL'      => 'nullable|string|max:50',
            'PER_CONTACTO' => 'nullable|string|max:50',
            'PER_EME'      => 'nullable|string|max:50',
            'PER_EMA'      => 'nullable|string|max:30',
            'PER_PADRE'    => 'nullable|string|max:30',
            'PER_MADRE'    => 'nullable|string|max:30',
            'PER_HOB'      => 'nullable|string|max:100',
            'PER_FACAD'    => 'nullable|string|max:100',
            'PER_OBS'      => 'nullable|string',
            // ── Datos laborales ──────────────────────────────
            'ACTIVO'          => 'nullable|boolean',
            'PER_AOP'         => 'nullable|string|max:1',
            'PER_ING'         => 'nullable|date',
            'PER_BAJ'         => 'nullable|date',
            'PER_BAJ_RAZON'   => 'nullable|string|max:100',
            'PER_FEFECTIVO'   => 'nullable|date',
            'PER_EMP'         => 'nullable|integer',
            'PER_EMD'         => 'nullable|string|max:30',
            'PER_CONTRA'      => 'nullable|integer',
            'PER_LUGAR'       => 'nullable|integer',
            'PER_SEC'         => 'nullable|integer',
            'PER_SED'         => 'nullable|string|max:50',
            'PER_CAT'         => 'nullable|integer',
            'PER_CAD'         => 'nullable|string|max:20',
            'PER_CON'         => 'nullable|integer',
            'PER_JOM'         => 'nullable|string|max:1',
            'PER_HORAS'       => 'nullable|integer',
            'PER_HNORMAL'     => 'nullable|numeric',
            'PER_HSABADO'     => 'nullable|numeric',
            'PER_ALM'         => 'nullable|string|max:1',
            'PER_COMN'        => 'nullable|integer',
            'PER_COMD'        => 'nullable|string|max:50',
            'PER_GRU'         => 'nullable|integer',
            'PER_COS'         => 'nullable|integer',
            'PER_NOMARCA'     => 'nullable|boolean',
            'PER_NMRAZON'     => 'nullable|string|max:100',
            'PER_RENTACAR'    => 'nullable|boolean',
            'PER_TIENEPC'     => 'nullable|boolean',
            'PER_ADI_ENC'     => 'nullable|boolean',
            'PER_ADI_SUS'     => 'nullable|boolean',
            'PARTE_ENV'       => 'nullable|boolean',
            'PARTE_PRG'       => 'nullable|integer',
            'PENDIENTE_SISTEMA' => 'nullable|boolean',
            'REQUIERE_SISTEMA'  => 'nullable|string',
            // ── Remuneración ─────────────────────────────────
            'PER_SUE'         => 'nullable|numeric|min:0',
            'PER_REMU'        => 'nullable|numeric|min:0',
            'PER_NREM'        => 'nullable|numeric|min:0',
            'PER_DESCUENTO'   => 'nullable|numeric|min:0|max:100',
            'PER_CBU'         => 'nullable|string|max:30',
            'PER_BAN'         => 'nullable|integer',
            'PER_BAD'         => 'nullable|string|max:50',
            'PER_SUC'         => 'nullable|integer',
            'PER_SUD'         => 'nullable|string|max:30',
            'OBRASOCIAL'      => 'nullable|string|max:50',
            'PER_AOS'         => 'nullable|integer',
            'PER_COS'         => 'nullable|integer',
            // ── Obra Social y Otros ───────────────────────────
            'PER_JRE'         => 'nullable|string|max:1',
            'PER_JRC'         => 'nullable|numeric',
            'PER_SIN'         => 'nullable|string|max:1',
            'PER_AFILIADO'    => 'nullable|boolean',
            'PER_PSN'         => 'nullable|string|max:1',
            'PER_JSN'         => 'nullable|string|max:1',
            'PER_CAR'         => 'nullable|string|max:1',
            'PER_CHE'         => 'nullable|string|max:1',
            'PER_PMP'         => 'nullable|string|max:50',
            'PER_FAS'         => 'nullable|date',
            'PER_PPR'         => 'nullable|integer',
            'PER_PRO'         => 'nullable|numeric',
            'PER_PRM'         => 'nullable|numeric',
            'PER_EMBSN'       => 'nullable|string|max:1',
            'PER_EMBNOM'      => 'nullable|string|max:30',
            'PER_EMBCBU'      => 'nullable|string|max:22',
            // ── Licencia de conducir ──────────────────────────
            'PER_LIC'         => 'nullable|string|max:1',
            'PER_LCA'         => 'nullable|string|max:15',
            'PER_LVE'         => 'nullable|date',
            'PER_LN1'         => 'nullable|string|max:12',
            'PER_LC1'         => 'nullable|string|max:4',
            'PER_LF1'         => 'nullable|date',
            'PER_LN2'         => 'nullable|string|max:12',
            'PER_LC2'         => 'nullable|string|max:4',
            'PER_LF2'         => 'nullable|date',
            'PER_LN3'         => 'nullable|string|max:12',
            'PER_LC3'         => 'nullable|string|max:4',
            'PER_LF3'         => 'nullable|date',
            'PER_LSF'         => 'nullable|string|max:1',
            'PER_LSG'         => 'nullable|string|max:1',
            'PER_LDO'         => 'nullable|string|max:1',
            'PER_LOB'         => 'nullable|string|max:40',
            'PER_LRE'         => 'nullable|string|max:40',
            'PER_LCD'         => 'nullable|string|max:40',
        ]);
    }

    /**
     * Genera el próximo PER_COD para un alta, asignando siempre un código PAR.
     *
     * Motivo (herencia FoxPro): el código se comparte entre dos sistemas sobre
     * la misma base — RRHH usa códigos PARES y Logística usa IMPARES — por lo que
     * NO se puede usar MAX(PER_COD)+1. Este proyecto es RRHH ⇒ siempre PAR.
     *
     * Estrategia (igual que el FoxPro):
     *   1. Buscar el menor código PAR libre dentro del rango [min, max] existente
     *      (reutiliza huecos dejados por bajas/depuraciones).
     *   2. Si no hay ningún hueco par libre, usar el primer PAR mayor al máximo.
     *
     * Considera ocupados tanto los PER_COD existentes como los códigos
     * reservados vigentes (tabla codigo_reserva), para no entregar el mismo
     * código a dos terminales que dan de alta en simultáneo.
     *
     * @return int  Código par disponible
     */
    private function generarCodigoPar(): int
    {
        $set = $this->codigosOcupados();

        // Base vacía → primer código par.
        if (empty($set)) {
            return 2;
        }

        $cods = array_keys($set);
        $min  = min($cods);
        $max  = max($cods);

        // 1. Menor PAR libre en el rango existente.
        $inicio = ($min % 2 === 0) ? $min : $min + 1;
        for ($c = $inicio; $c <= $max; $c += 2) {
            if (!isset($set[$c])) {
                return $c;
            }
        }

        // 2. Sin huecos: primer PAR estrictamente mayor al máximo.
        $sig = $max + 1;
        if ($sig % 2 !== 0) {
            $sig++;
        }
        return $sig;
    }

    /** Minutos que una reserva de código permanece vigente antes de expirar. */
    private const RESERVA_TTL_MIN = 30;

    /**
     * Set (flip) de códigos ocupados = PER_COD existentes + reservas vigentes.
     * De paso purga las reservas vencidas (más viejas que RESERVA_TTL_MIN).
     *
     * @return array<int,int>  array_flip: clave = código ocupado
     */
    private function codigosOcupados(): array
    {
        // Purga de reservas vencidas (altas abandonadas en otra terminal).
        DB::table('codigo_reserva')
            ->where('CREADO', '<', now()->subMinutes(self::RESERVA_TTL_MIN))
            ->delete();

        $personal = Personal::pluck('PER_COD')->map(fn($v) => (int) $v)->all();
        $reservas = DB::table('codigo_reserva')->pluck('CODIGO')->map(fn($v) => (int) $v)->all();

        return array_flip(array_merge($personal, $reservas));
    }

    /**
     * Reserva el próximo código PAR libre para un alta en curso y lo devuelve.
     *
     * Permite mostrar el código al usuario apenas crea el empleado, garantizando
     * que ninguna otra terminal reciba el mismo. La reserva se consume al guardar
     * (store) o expira sola tras RESERVA_TTL_MIN minutos.
     *
     * @route  POST /api/empleados/reservar-codigo
     * @return JsonResponse  { codigo: int }
     */
    public function reservarCodigo(Request $request): JsonResponse
    {
        $usuario = substr((string) ($request->user()->name ?? $request->input('usuario') ?? ''), 0, 50);

        // El PK de codigo_reserva garantiza unicidad; si dos terminales eligen
        // el mismo código a la vez, una falla el INSERT y reintenta con el siguiente.
        for ($intento = 0; $intento < 50; $intento++) {
            $codigo = $this->generarCodigoPar();
            try {
                DB::table('codigo_reserva')->insert([
                    'CODIGO'  => $codigo,
                    'USUARIO' => $usuario,
                    'CREADO'  => now(),
                ]);
                return response()->json(['codigo' => $codigo]);
            } catch (\Illuminate\Database\QueryException $e) {
                continue; // colisión de PK → otro usuario lo tomó; probar el siguiente
            }
        }

        return response()->json(['message' => 'No se pudo reservar un código, reintente.'], 409);
    }

    /**
     * Libera una reserva de código (al cancelar un alta sin guardar).
     *
     * @route  DELETE /api/empleados/reservar-codigo/{codigo}
     * @return JsonResponse
     */
    public function liberarCodigo(int $codigo): JsonResponse
    {
        DB::table('codigo_reserva')->where('CODIGO', $codigo)->delete();
        return response()->json(['ok' => true]);
    }

    /**
     * Reglas de negocio al guardar (replica las validaciones del FoxPro).
     *   1. Legajo obligatorio y distinto de 0
     *   2. Nombre no vacío
     *   3. Documento (DNI) distinto de 0
     *   4. CUIL no utilizado por otro empleado
     *   5. Código no utilizado por otro empleado
     *
     * @param  array<string,mixed> $datos
     * @param  int|null $codigoActual  PER_COD a excluir en chequeos de unicidad (edición)
     * @throws \Illuminate\Validation\ValidationException
     */
    private function validarReglasNegocio(array $datos, ?int $codigoActual = null): void
    {
        $errores = [];

        // 1. Legajo obligatorio y != 0
        if (empty($datos['PER_LEG'])) {
            $errores['PER_LEG'] = ['Falta el número de legajo.'];
        }

        // 2. Nombre no vacío
        if (trim((string)($datos['PER_NOM'] ?? '')) === '') {
            $errores['PER_NOM'] = ['No puede ingresar un nombre vacío de empleado.'];
        }

        // 3. Documento != 0
        if (empty($datos['PER_NDO'])) {
            $errores['PER_NDO'] = ['El número de documento no puede ser cero.'];
        }

        // 4. CUIL único
        $cuil = trim((string)($datos['PER_CUI'] ?? ''));
        if ($cuil !== '') {
            $q = Personal::whereRaw('LTRIM(RTRIM(PER_CUI)) = ?', [$cuil]);
            if ($codigoActual !== null) {
                $q->where('PER_COD', '!=', $codigoActual);
            }
            $otro = $q->first(['PER_NOM']);
            if ($otro) {
                $errores['PER_CUI'] = [
                    'El CUIL ya se encuentra utilizado por el empleado ' .
                    strtoupper(trim($otro->PER_NOM)) . '.',
                ];
            }
        }

        // Nota: la unicidad del CÓDIGO (PER_COD) no se valida porque en esta
        // aplicación se genera automáticamente (max+1) y no es editable, por lo
        // que no puede colisionar. La regla existe en FoxPro porque allí el
        // código se ingresa a mano.

        if ($errores) {
            throw \Illuminate\Validation\ValidationException::withMessages($errores);
        }
    }
}
