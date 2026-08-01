<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * CelularController — Telefonía Celular: Asignar Teléfonos / Empleados (celulares_asignar.scx).
 *
 * Asigna un equipo celular (celulares_equipos) a un empleado (tabla de asignaciones
 * celular_empleados, PK `unico` IDENTITY, cem_devolucion=1900-01-01 => asignación activa).
 * Réplica del botón AGREGAR TELEFONO CELULAR del FoxPro.
 */
class CelularController extends Controller
{
    /** Agrega el filtro "asignación activa" (no devuelta): cem_devolucion nula o año 1900. */
    private function scopeActivo($q)
    {
        return $q->where(function ($w) {
            $w->whereNull('cem_devolucion')->orWhereRaw('YEAR(cem_devolucion) = 1900');
        });
    }

    private function fecha($v): string
    {
        return ($v && substr((string) $v, 0, 4) !== '1900') ? substr((string) $v, 0, 10) : '';
    }

    /** @route GET /api/celulares/equipos — lupa de equipos (buscar_celular.scx). */
    public function equiposBuscar(Request $request): JsonResponse
    {
        $q          = trim((string) $request->query('q', ''));
        $soloActivos = $request->boolean('solo_activos');

        $query = DB::table('celulares_equipos');
        if ($soloActivos) {
            $query->where(fn ($w) => $w->where('cel_baja', 0)->orWhereNull('cel_baja'));
        }
        if ($q !== '') {
            $qu = mb_strtoupper($q);
            $query->where(function ($w) use ($qu) {
                $w->whereRaw('UPPER(cel_imei) LIKE ?', ["%{$qu}%"])
                  ->orWhereRaw('UPPER(cel_marca) LIKE ?', ["%{$qu}%"])
                  ->orWhereRaw('UPPER(cel_modelo) LIKE ?', ["%{$qu}%"])
                  ->orWhereRaw('UPPER(cel_color) LIKE ?', ["%{$qu}%"]);
            });
        }

        $rows = $query->orderBy('cel_cod')->get()->map(fn ($e) => [
            'cod'       => (int) $e->cel_cod,
            'imei'      => trim((string) $e->cel_imei),
            'marca'     => trim((string) $e->cel_marca),
            'modelo'    => trim((string) $e->cel_modelo),
            'color'     => trim((string) $e->cel_color),
            'pantalla'  => (float) $e->cel_pantalla,
            'sistema'   => trim((string) $e->cel_sistema),
            'cargador'  => (bool) $e->cel_cargador,
            'auricular' => (bool) $e->cel_auricular,
            'cableusb'  => (bool) $e->cel_cableusb,
            'baja'      => (bool) $e->cel_baja,
        ])->values();

        return response()->json($rows);
    }

    /** Serializa un equipo (celulares_equipos) con todos sus campos. */
    private function equipoArray($e): array
    {
        return [
            'cod'        => (int) $e->cel_cod,
            'imei'       => trim((string) $e->cel_imei),
            'marca'      => trim((string) $e->cel_marca),
            'modelo'     => trim((string) $e->cel_modelo),
            'color'      => trim((string) $e->cel_color),
            'pantalla'   => (float) $e->cel_pantalla,
            'sistema'    => trim((string) $e->cel_sistema),
            'cargador'   => (bool) $e->cel_cargador,
            'auricular'  => (bool) $e->cel_auricular,
            'cableusb'   => (bool) $e->cel_cableusb,
            'vidrio'     => (bool) $e->cel_vidrio,
            'carcasa'    => (bool) $e->cel_carcasa,
            'compra'     => $this->fecha($e->cel_compra),
            'garantia'   => (int) $e->cel_garantia,
            'baja'       => (bool) $e->cel_baja,
            'fecha_baja' => $this->fecha($e->cel_fbaja),
            'razon_baja' => trim((string) $e->cel_razbaj),
        ];
    }

    /** @route GET /api/celulares/equipos/{cod} — datos de un equipo (poblar el formulario / ABM). */
    public function equipo(int $cod): JsonResponse
    {
        $e = DB::table('celulares_equipos')->where('cel_cod', $cod)->first();
        if (!$e) {
            return response()->json(['message' => 'No existe el equipo celular.'], 404);
        }
        return response()->json($this->equipoArray($e));
    }

    /** @route GET /api/celulares/empleado/{emp} — nombre del empleado + sus asignaciones (grilla). */
    public function empleado(int $emp): JsonResponse
    {
        $per = DB::table('personal')->where('PER_COD', $emp)->first(['PER_NOM']);
        if (!$per) {
            return response()->json(['message' => 'Código de empleado erróneo.'], 404);
        }

        $rows = DB::table('celular_empleados as a')
            ->join('celulares_equipos as b', 'a.cem_equipo', '=', 'b.cel_cod')
            ->where('a.cem_emp', $emp)
            ->orderByDesc('a.unico')
            ->get()
            ->map(fn ($r) => [
                'id'          => (int) $r->unico,
                'cod'         => (int) $r->cem_equipo,
                'imei'        => trim((string) $r->cem_imei),
                'marca'       => trim((string) $r->cel_marca),
                'modelo'      => trim((string) $r->cel_modelo),
                'color'       => trim((string) $r->cel_color),
                'pantalla'    => (float) $r->cel_pantalla,
                'sistema'     => trim((string) $r->cel_sistema),
                'nro_celular' => trim((string) $r->cem_nrocelular),
                'entrega'     => $this->fecha($r->cem_entrega),
                'devolucion'  => $this->fecha($r->cem_devolucion),
                'obs_entrega' => trim((string) $r->cem_obsentrega),
                'activo'      => $this->fecha($r->cem_devolucion) === '',
            ])->values();

        return response()->json([
            'empleado' => ['cod' => $emp, 'nombre' => trim((string) $per->PER_NOM)],
            'asignados' => $rows,
        ]);
    }

    /** @route POST /api/celulares/asignar — asigna un equipo a un empleado (AGREGAR TELEFONO CELULAR). */
    public function asignar(Request $request): JsonResponse
    {
        $d = $request->validate([
            'empleado'    => 'required|integer|min:1',
            'equipo'      => 'required|integer|min:1',
            'fecha'       => 'nullable|date',
            'observacion' => 'nullable|string|max:100',
            'nro_celular' => 'nullable|string|max:15',
        ], [
            'empleado.required' => 'Debe ingresar el empleado.',
            'empleado.min'      => 'Debe ingresar el empleado.',
            'equipo.required'   => 'Debe ingresar el celular a entregar.',
            'equipo.min'        => 'Debe ingresar el celular a entregar.',
        ]);

        $per = DB::table('personal')->where('PER_COD', $d['empleado'])->first(['PER_NOM']);
        if (!$per) {
            return response()->json(['message' => 'Código de empleado erróneo.'], 422);
        }
        if (!\App\Support\Empleado::activo((int) $d['empleado'])) {
            return response()->json(['message' => 'El empleado está dado de baja: no se pueden cargar registros.'], 422);
        }
        if (!DB::table('celulares_equipos')->where('cel_cod', $d['equipo'])->exists()) {
            return response()->json(['message' => 'No existe el equipo celular.'], 422);
        }

        // Ya lo tiene asignado (activo) este mismo empleado.
        $yaEste = $this->scopeActivo(
            DB::table('celular_empleados')->where('cem_emp', $d['empleado'])->where('cem_equipo', $d['equipo'])
        )->exists();
        if ($yaEste) {
            return response()->json(['message' => 'Este celular ya lo tiene asignado el presente empleado.'], 422);
        }

        // Ya lo tiene asignado (activo) otro empleado (no devuelto).
        $otro = $this->scopeActivo(
            DB::table('celular_empleados')->where('cem_equipo', $d['equipo'])
        )->first();
        if ($otro) {
            $nom = trim((string) $otro->cem_emd);
            return response()->json(['message' => "El celular seleccionado ya lo tiene asignado el empleado {$nom}."], 422);
        }

        $fecha0 = fn ($v) => ($v ?? '') !== '' ? substr((string) $v, 0, 10) : '1900-01-01';

        DB::table('celular_empleados')->insert([
            'cem_emp'        => (int) $d['empleado'],
            'cem_emd'        => mb_substr(mb_strtoupper(trim((string) $per->PER_NOM)), 0, 50),
            'cem_equipo'     => (int) $d['equipo'],
            'cem_imei'       => mb_substr(trim((string) DB::table('celulares_equipos')->where('cel_cod', $d['equipo'])->value('cel_imei')), 0, 30),
            'cem_entrega'    => $fecha0($d['fecha'] ?? ''),
            'cem_obsentrega' => mb_substr(trim((string) ($d['observacion'] ?? '')), 0, 100),
            'cem_devolucion' => '1900-01-01',
            'cem_obsdevolu'  => '',
            'cem_nrocelular' => mb_substr(trim((string) ($d['nro_celular'] ?? '')), 0, 15),
        ]);

        return response()->json(['ok' => true], 201);
    }

    /** @route POST /api/celulares/devolver — registra la devolución de uno o más equipos (celulares_devolver.scx). */
    public function devolver(Request $request): JsonResponse
    {
        $d = $request->validate([
            'unicos'      => 'required|array|min:1',
            'unicos.*'    => 'integer',
            'fecha'       => 'required|date',
            'observacion' => 'required|string|max:100',
        ], [
            'unicos.required'      => 'Debe seleccionar qué teléfono devolver.',
            'unicos.min'           => 'Debe seleccionar qué teléfono devolver.',
            'fecha.required'       => 'Debe ingresar la fecha que fue devuelto.',
            'observacion.required' => 'Ingresar una observación del estado en que es devuelto el teléfono.',
        ]);

        DB::table('celular_empleados')->whereIn('unico', $d['unicos'])->update([
            'cem_devolucion' => substr((string) $d['fecha'], 0, 10),
            'cem_obsdevolu'  => mb_substr(trim($d['observacion']), 0, 100),
        ]);
        return response()->json(['ok' => true]);
    }

    /** @route GET /api/celulares/informe — datos del informe de celulares (celulares_informes.scx). */
    public function informe(Request $request): JsonResponse
    {
        $d = $request->validate([
            'empleado'  => 'nullable|integer',   // null/0 = todos
            'desde'     => 'nullable|date',
            'hasta'     => 'nullable|date',
            'activos'   => 'boolean',            // sólo entregados activos
            'bajas'     => 'boolean',            // incluir dados de baja
        ]);

        $q = DB::table('celular_empleados as a')
            ->join('celulares_equipos as b', 'a.cem_equipo', '=', 'b.cel_cod');

        if (!empty($d['empleado'])) {
            $q->where('a.cem_emp', (int) $d['empleado']);
        }
        if (!empty($d['desde']) && !empty($d['hasta'])) {
            $q->whereBetween('a.cem_entrega', [substr($d['desde'], 0, 10) . ' 00:00:00', substr($d['hasta'], 0, 10) . ' 23:59:59']);
        }
        if (!empty($d['activos'])) {
            $q->where(fn ($w) => $w->whereNull('a.cem_devolucion')->orWhereRaw('YEAR(a.cem_devolucion) = 1900'));
        }
        if (empty($d['bajas'])) {
            $q->where(fn ($w) => $w->where('b.cel_baja', 0)->orWhereNull('b.cel_baja'));
        }

        $rows = $q->orderBy('a.cem_emd')->orderBy('a.cem_equipo')->get()->map(fn ($r) => [
            'empleado'       => trim((string) $r->cem_emd),
            'nro_celular'    => trim((string) $r->cem_nrocelular),
            'equipo'         => (int) $r->cem_equipo,
            'imei'           => trim((string) $r->cem_imei),
            'entrega'        => $this->fecha($r->cem_entrega),
            'obs_entrega'    => trim((string) $r->cem_obsentrega),
            'devolucion'     => $this->fecha($r->cem_devolucion),
            'obs_devolucion' => trim((string) $r->cem_obsdevolu),
            'marca'          => trim((string) $r->cel_marca),
            'modelo'         => trim((string) $r->cel_modelo),
            'color'          => trim((string) $r->cel_color),
            'pantalla'       => (float) $r->cel_pantalla,
            'sistema'        => trim((string) $r->cel_sistema),
            'cargador'       => (bool) $r->cel_cargador,
            'auricular'      => (bool) $r->cel_auricular,
            'cableusb'       => (bool) $r->cel_cableusb,
            'compra'         => $this->fecha($r->cel_compra),
            'garantia'       => (int) $r->cel_garantia,
            'baja'           => (bool) $r->cel_baja,
            'fecha_baja'     => $this->fecha($r->cel_fbaja),
            'razon_baja'     => trim((string) $r->cel_razbaj),
        ])->values();

        return response()->json(['celulares' => $rows]);
    }

    // ─────────────────────────── EQUIPOS (ABM, celulares.scx) ───────────────────────────

    /** @route GET /api/celulares/equipos-lista — todos los equipos (navegación del ABM). */
    public function equiposLista(): JsonResponse
    {
        $rows = DB::table('celulares_equipos')->orderBy('cel_cod')->get()
            ->map(fn ($e) => $this->equipoArray($e))->values();
        return response()->json($rows);
    }

    /** @route GET /api/celulares/equipos/{cod}/historial — a quiénes se asignó el equipo. */
    public function equipoHistorial(int $cod): JsonResponse
    {
        $rows = DB::table('celular_empleados')->where('cem_equipo', $cod)->orderByDesc('cem_entrega')->get()
            ->map(fn ($r) => [
                'empleado'    => (int) $r->cem_emp,
                'nombre'      => trim((string) $r->cem_emd),
                'nro_celular' => trim((string) $r->cem_nrocelular),
                'cod'         => (int) $r->cem_equipo,
                'imei'        => trim((string) $r->cem_imei),
                'entrega'     => $this->fecha($r->cem_entrega),
                'devolucion'  => $this->fecha($r->cem_devolucion),
                'devuelto'    => $this->fecha($r->cem_devolucion) !== '',
            ])->values();
        return response()->json($rows);
    }

    /** Reglas de validación de un equipo (celulares.scx). */
    private function reglasEquipo(): array
    {
        return [
            'imei'       => 'required|string|max:30',
            'marca'      => 'nullable|string|max:30',
            'modelo'     => 'nullable|string|max:30',
            'color'      => 'nullable|string|max:15',
            'pantalla'   => 'nullable|numeric',
            'sistema'    => 'nullable|string|max:30',
            'cargador'   => 'boolean', 'auricular' => 'boolean', 'cableusb' => 'boolean',
            'vidrio'     => 'boolean', 'carcasa' => 'boolean',
            'compra'     => 'nullable|date',
            'garantia'   => 'nullable|integer',
            'baja'       => 'boolean',
            'fecha_baja' => 'nullable|date',
            'razon_baja' => 'nullable|string|max:100',
        ];
    }

    /** Arma la fila para insert/update de celulares_equipos a partir de los datos validados. */
    private function equipoFila(array $d): array
    {
        $up = fn ($v) => mb_strtoupper(trim((string) ($v ?? '')));
        $f0 = fn ($v) => ($v ?? '') !== '' ? substr((string) $v, 0, 10) : '1900-01-01';
        return [
            'cel_imei'     => mb_substr($up($d['imei']), 0, 30),
            'cel_marca'    => mb_substr($up($d['marca'] ?? ''), 0, 30),
            'cel_modelo'   => mb_substr($up($d['modelo'] ?? ''), 0, 30),
            'cel_color'    => mb_substr($up($d['color'] ?? ''), 0, 15),
            'cel_pantalla' => (float) ($d['pantalla'] ?? 0),
            'cel_sistema'  => mb_substr($up($d['sistema'] ?? ''), 0, 30),
            'cel_cargador' => !empty($d['cargador']) ? 1 : 0,
            'cel_auricular'=> !empty($d['auricular']) ? 1 : 0,
            'cel_cableusb' => !empty($d['cableusb']) ? 1 : 0,
            'cel_vidrio'   => !empty($d['vidrio']) ? 1 : 0,
            'cel_carcasa'  => !empty($d['carcasa']) ? 1 : 0,
            'cel_compra'   => $f0($d['compra'] ?? ''),
            'cel_garantia' => (int) ($d['garantia'] ?? 0),
            'cel_baja'     => !empty($d['baja']) ? 1 : 0,
            'cel_fbaja'    => !empty($d['baja']) ? $f0($d['fecha_baja'] ?? '') : '1900-01-01',
            'cel_razbaj'   => !empty($d['baja']) ? mb_substr($up($d['razon_baja'] ?? ''), 0, 100) : '',
        ];
    }

    /** @route POST /api/celulares/equipos — alta de un equipo (cel_cod = max+1). */
    public function equipoGuardar(Request $request): JsonResponse
    {
        $d = $request->validate($this->reglasEquipo(), ['imei.required' => 'Debe ingresar el IMEI del celular.']);
        $cod = (int) DB::table('celulares_equipos')->max('cel_cod') + 1;
        DB::table('celulares_equipos')->insert(array_merge(['cel_cod' => $cod], $this->equipoFila($d)));
        return response()->json(['ok' => true, 'cod' => $cod], 201);
    }

    /** @route PUT /api/celulares/equipos/{cod} — modifica un equipo. */
    public function equipoActualizar(Request $request, int $cod): JsonResponse
    {
        if (!DB::table('celulares_equipos')->where('cel_cod', $cod)->exists()) {
            return response()->json(['message' => 'No existe el equipo celular.'], 404);
        }
        $d = $request->validate($this->reglasEquipo(), ['imei.required' => 'Debe ingresar el IMEI del celular.']);
        DB::table('celulares_equipos')->where('cel_cod', $cod)->update($this->equipoFila($d));
        return response()->json(['ok' => true]);
    }
}
