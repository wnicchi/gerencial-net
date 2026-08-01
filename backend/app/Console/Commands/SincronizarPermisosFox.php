<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * SincronizarPermisosFox — Compatibiliza los permisos por rol con la config FoxPro.
 *
 * En FoxPro cada usuario tenía un NIVEL (hoy "Rol"), y la tabla NIVFORM definía
 * qué formularios podía usar (BLOQUEO = 0 → habilitado, 1 → bloqueado). Cada
 * formulario (FORMULARIOS.ELSCX) es el nombre del módulo de Fox.
 *
 * Este comando recorre nivform, traduce cada ELSCX habilitado a la clave del
 * menú nuevo (id de menu.ts, que es lo que usa nivel_permisos) y reescribe
 * nivel_permisos para cada rol, dejando tildados solo los módulos permitidos.
 *
 * Uso:
 *   php artisan permisos:sincronizar-fox            (INFORME, no escribe nada)
 *   php artisan permisos:sincronizar-fox --aplicar  (escribe los cambios)
 */
class SincronizarPermisosFox extends Command
{
    protected $signature = 'permisos:sincronizar-fox {--aplicar : Escribe los cambios (sin esto solo muestra un informe)}';

    protected $description = 'Tilda en nivel_permisos los módulos que cada rol podía usar según la config FoxPro (nivform + formularios).';

    /** ELSCX (formulario Fox) => clave de menú RRHH.NET (id de menu.ts). */
    private const MAP = [
        'ADELANTOS_AGREGAR' => 'ade-agregar',
        'ADELANTOS_BORRAR' => 'ade-borrar',
        'ADELANTOS_CONSULTAR' => 'ade-consultar',
        'ADELANTOS_IMPRIMIR' => 'ade-imprimir',
        'ADELANTOS_LISTADOS' => 'ade-listados',
        'ALERTAS' => 'alertas',
        'ALMUERZOS' => 'alm-editar',
        'ALMUERZOS_LISTADOS' => 'alm-listados',
        'ALTAACCESOS' => 'alta-accesos',
        'APERCIBIMIENTO_AGREGAR' => 'aper-crear',
        'APERCIBIMIENTO_CONSULTAR' => 'aper-consulta',
        'AREAS_TEMATICAS' => 'areas-tematicas',
        'ART_SINIESTROS_AGREGAR' => 'sin-agregar',
        'ART_SINIESTROS_CONSULTAR' => 'sin-consultar',
        'ART_SINIESTROS_ELIMINAR' => 'sin-eliminar',
        'ART_SINIESTROS_IMPRESION' => 'sin-impresion',
        'ART_SINIESTROS_LISTADOS' => 'sin-listados',
        'ART_SINIESTROS_MODIFICAR' => 'sin-modificar',
        'ART_SINIESTROS_SEGUIMIENTO' => 'sin-seguimiento',
        'ASIGNACIONES' => 'asignaciones',
        'BLOQUEOS' => 'bloqueos',
        'CALIFICACION' => 'calif-emp',
        'CALIFICACION_CONSULTA' => 'calif-consulta',
        'CALIFICACION_HOJA_INFORME' => 'calif-hoja',
        'CAMBIACLAVE' => 'cambio-clave',
        'CAPACITACION' => 'cap-jornadas',
        'CAPACITACION_ASIGNAR' => 'cap-asignar',
        'CAPACITACION_DOCUMENTACION' => 'cap-doc',
        'CAPACITACION_INFORMES' => 'cap-informes',
        'CAPACITACION_RESULTADOS' => 'cap-resultados',
        'CARNET_CATEGORIA' => 'carnet-cat',
        'CATEGORIAS' => 'categorias',
        'CELULARES' => 'cel-equipos',
        'CELULARES_ASIGNAR' => 'cel-asignar',
        'CELULARES_DEVOLVER' => 'cel-devolver',
        'CELULARES_INFORMES' => 'cel-informes',
        'COMEDORES' => 'comedores',
        'COMPRAS_CONSULTAR' => 'compras-cons',
        'CONTRATISTAS' => 'contratistas-arch',
        'CONTRATISTAS_TIPO' => 'contra-tipo',
        'CONTRA_ACCESOS' => 'contra-accesos',
        'CONTRA_EMPLEA' => 'contra-emplea',
        'CONTRA_EMPLEA_IMPORTAR' => 'contra-importar',
        'CONTRA_LISTA_FALTAS' => 'contra-faltas',
        'CONTRA_NOMBRES' => 'contra-nombres',
        'CONTRA_OBLIGACIONES' => 'contra-oblig',
        'CONVENIOS' => 'convenios',
        'CTAS_BANCARIAS' => 'ctas-bancarias',
        'DEFINICION_DE_FERIADOS' => 'feriados',
        'DEPARTAMENTOS' => 'departamentos',
        'DESCUENTOS' => 'descuentos',
        'DISERTANTES' => 'disertantes',
        'EMPLEADOS' => 'empleados-abm',
        'EMPLEADOS_ASIGNAR_TURNO_LABORAL' => 'rel-turnos',
        'EMPLEADOS_CONSULTAR_BCO_FRANCES' => 'bco-fra-con',
        'EMPLEADOS_CONSULTAR_BCO_NACION' => 'bco-nac-con',
        'EMPLEADOS_CONSULTAR_BCO_SANTANDER_RIO' => 'bco-san-con',
        'EMPLEADOS_CONSULTAR_BCO_SF' => 'bco-sf-con',
        'EMPLEADOS_CONSULTAR_BCO_VARIOS' => 'bco-var-con',
        'EMPLEADOS_COSTOS_FIJOS' => 'costos-fijos',
        'EMPLEADOS_COSTOS_GRUPALES' => 'costos-grupal',
        'EMPLEADOS_COSTOS_INDIVIDUAL' => 'costos-ind',
        'EMPLEADOS_COSTOS_INFORME_GRAL' => 'costos-informe',
        'EMPLEADOS_EXPOIMPO' => 'empleados-importar',
        'EMPLEADOS_EXPORTAR' => 'empleados-exportar',
        'EMPLEADOS_EXPORTAR_BCO_FRANCES' => 'bco-fra-exp',
        'EMPLEADOS_EXPORTAR_BCO_NACION' => 'bco-nac-exp',
        'EMPLEADOS_EXPORTAR_BCO_SANTANDER_RIO' => 'bco-san-exp',
        'EMPLEADOS_EXPORTAR_BCO_SF' => 'bco-sf-exp',
        'EMPLEADOS_EXPORTAR_BCO_VARIOS' => 'bco-var-exp',
        'EMPLEADOS_IMPO_OBRASOCIAL' => 'empleados-impo-os',
        'EMPLEADOS_PUESTOS' => 'emp-puestos',
        'EMPLEADO_ASIGNAR_EPP_UTILIZADO' => 'emp-epp',
        'EMPLEADO_ASIGNAR_TURNO_LABORAL' => 'rel-turnos',
        'EMPLEADO_CARNET' => 'empleados-carnet',
        'EMPLEADO_CENTRO_COSTO' => 'empleados-cc',
        'EMPLEADO_EXPORTAR' => 'empleados-exportar',
        'EMPRESAS' => 'empresas',
        'ENTREGA_ROPA' => 'ropa-entrega',
        'ENTREVISTAS' => 'ent-abm',
        'ENTREVISTAS_CONSULTA_GENERAL' => 'ent-grupo',
        'ENTREVISTAS_LISTADOS' => 'ent-listados',
        'ESTADO_CIVIL' => 'estado-civil',
        'EXAMENES_AGREGAR' => 'exam-agregar',
        'EXAMENES_ELIMINAR' => 'exam-eliminar',
        'EXAMENES_EMPLEADOS' => 'exam-empleados',
        'EXAMENES_LISTADOS' => 'exam-listados',
        'EXAMENES_MEDICOS_AGREGAR' => 'exam-med',
        'EXAMENES_MODIFICAR' => 'exam-modificar',
        'EXAMENES_PROXIMOS' => 'exam-proximos',
        'EXAMENES_TIPO' => 'examenes-tipo',
        'EXIGENCIAS' => 'exigencias',
        'FRECUENCIAS' => 'frecuencias',
        'HABERES' => 'haberes',
        'HORAS_EXTRAS' => 'planilla-hs',
        'HORAS_EXTRAS_DIARIAS' => 'hs-diarias',
        'HORAS_EXTRAS_DIARIAS_EMPLEADO' => 'hs-empleado',
        'INVITADOS' => 'invitados',
        'LICENCIAS_LABORALES' => 'licencias',
        'LICENCIA_GOZADA' => 'licencias',
        'LICENCIA_GOZADA_BORRAR' => 'licencias',
        'LICENCIA_GOZADA_CONSULTAR' => 'licencias',
        'LICENCIA_GOZADA_IMPRIMIR' => 'licencias',
        'LICENCIA_NO_GOZADA' => 'licencias',
        'LUGARES' => 'lugares',
        'MEDICOS' => 'medicos',
        'MULTAS_LISTADOS' => 'multas',
        'NIVELES' => 'roles',
        'NOVEDADES' => 'nov-editar',
        'NOVEDADES_EXPORTAR' => 'nov-exportar',
        'OBRAS_ACCESOS' => 'obras-ingresar',
        'OBRAS_INICIAR' => 'obras-habilitar',
        'OBRAS_LISTADOS' => 'obras-listados',
        'OBRAS_MODIFICAR' => 'obras-modificar',
        'OBRAS_SOCIALES' => 'os-abm',
        'OBRAS_SOCIALES_APORTES' => 'os-aportes',
        'OBRAS_SOCIALES_IMPORTAR' => 'os-importar',
        'PARAMETROS' => 'parametros',
        'PERMISOS_LABORALES' => 'permisos',
        'PLANILLAS_LIBRO_VIAJANTES' => 'plan-viajantes',
        'PLANILLA_CONTROL_SUELDOS' => 'plan-sueldos',
        'PLANILLA_HORAS_EXTRAS' => 'planilla-hs',
        'PLANILLA_HORAS_EXTRAS_EDITAR' => 'planilla-hs-edit',
        'PLANILLA_LIBRO_VIAJANTES' => 'plan-viajantes',
        'PUESTOS' => 'puestos-abm',
        'PUESTOS_ASIGNAR' => 'puestos-asignar',
        'PUESTOS_CONSULTA' => 'puestos-listados',
        'PUESTOS_HOJA_EVALUACION' => 'hoja-eval',
        'RELOJ_AJUSTES_HORARIOS' => 'rel-ajustes',
        'RELOJ_AJUSTES_HORARIOS_BORRAR' => 'rel-ajustes-borr',
        'RELOJ_CAPTURAS' => 'rel-capturas',
        'RELOJ_CAPTURAS_EDICION' => 'rel-ajustes-per',
        'RELOJ_ENVIOS' => 'rel-envios',
        'RELOJ_FALTAS_DIARIAS' => 'rel-faltas',
        'RELOJ_FALTAS_DIARIAS_EDITAR' => 'rel-faltas-edit',
        'RELOJ_FALTAS_DIARIAS_LISTADOS' => 'rel-faltas-list',
        'RELOJ_GRUPOS' => 'rel-grupos',
        'RELOJ_HORARIOS_PARTE_DIARIO' => 'rel-parte',
        'RELOJ_HORARIOS_SECRETARIA_TRABAJO' => 'rel-secretaria',
        'RELOJ_HORARIOS_TRABAJADOS' => 'rel-horas',
        'RELOJ_LLEGADAS_TARDES' => 'rel-tardes',
        'RELOJ_UBICACIONES' => 'rel-ubicacion',
        'REQUERIMIENTOS' => 'req-abm',
        'REQUERIMIENTOS_CLIENTES' => 'req-clientes',
        'REQUERIMIENTOS_EMAIL_ENVIADOS' => 'req-email',
        'REQUERIMIENTOS_INFORMES' => 'req-informes',
        'REQUERMIENTOS' => 'req-abm',
        'ROPA_AGREGAR' => 'ropa-abm',
        'ROPA_BORRAR' => 'ropa-borrar',
        'ROPA_DEPOSITOS' => 'ropa-depositos',
        'ROPA_ENTREGA' => 'ropa-entrega',
        'ROPA_ENTREGA_HISTORICA' => 'ropa-hist',
        'ROPA_ESTADISTICA' => 'ropa-estadistica',
        'ROPA_INGRESO' => 'ropa-ingreso',
        'ROPA_INVENTARIO' => 'ropa-inventario',
        'ROPA_MARCAS' => 'ropa-marcas',
        'ROPA_RUBRO' => 'ropa-rubro',
        'ROPA_STOCK' => 'ropa-stock',
        'ROPA_TRANSFERENCIAS' => 'ropa-transfer',
        'SECTORES' => 'sectores',
        'SUBSECTORES' => 'subsectores',
        'SUELDOS_BORRAR' => 'liq-borrar',
        'SUELDOS_COMPARAR_LIQ_NETO' => 'liq-comparar',
        'SUELDOS_CONCEPTOS' => 'sueldos-conceptos',
        'SUELDOS_CONSULTAR' => 'liq-consultar',
        'SUELDOS_IMPORTAR' => 'liq-importar',
        'SUELDOS_IMPORTAR_FORMATO_ESTUDIO_2' => 'liq-importar2',
        'SUELDOS_INFORMES' => 'liq-informes',
        'SUELDOS_LISTADOS' => 'liq-listados',
        'SUELDOS_MEJOR_PRECIOS' => 'liq-mejor',
        'SUELDOS_NETOS' => 'liq-netos',
        'SUELDOS_PAGOS' => 'liq-pagos',
        'SUELDOS_PAGOS_CONSULTAS' => 'liq-pagos',
        'SUELDOS_TIPOS' => 'sueldos-tipos',
        'TALLES' => 'talles',
        'TAREAS' => 'tareas-puesto',
        'TAREAS_DETALLE' => 'tareas-def',
        'TELEFONOS_AGENDA' => 'agenda-telefonos',
        'TIPO_DOCUMENTACION' => 'tipo-doc',
        'USUARIOS_ACTIVOS' => 'usuarios-activos',
        'VACACIONES_ACCIONES_VARIAS' => 'vac-acciones',
        'VACACIONES_AGREGAR' => 'vac-agregar',
        'VACACIONES_CONSULTAR' => 'vac-acciones',
        'VACACIONES_DEFINICION' => 'vac-definicion',
        'VACACIONES_ELIMINAR' => 'vac-acciones',
        'VACACIONES_INFORME' => 'vac-informe',
        'VACACIONES_MODIFICAR' => 'vac-acciones',
        'VACACIONES_PENDIENTES' => 'vac-pendientes',
        'VACACIONES_PLANILLA' => 'vac-planilla',
        'VACACIONES_PROGRAMADAS' => 'vac-programadas',
        'VALES_ABIERTOS' => 'vales-pend',
        'VALES_AGREGAR' => 'vales-agregar',
        'VALES_BORRAR' => 'vales-borrar',
        'VALES_CERRAR' => 'vales-cerrar',
        'VALES_CONSULTAR' => 'vales-cons',
        'VALES_CONSULTAR_BORRADOS' => 'vales-borrados',
        'VALES_FONDO_FIJO' => 'vales-fondo',
        'VALES_IMPRIMIR' => 'vales-imprimir',
        'VALES_TESORERIA' => 'vales-tesoreria',
        'VALORES' => 'valores',
        'VIAJES' => 'viajes-agregar',
        'VIAJES_EDITAR' => 'viajes-editar',
    ];

    public function handle(): int
    {
        $aplicar = (bool) $this->option('aplicar');

        // Habilitados en Fox: BLOQUEO = 0 (0 = habilitado, 1 = bloqueado).
        $filas = DB::table('nivform')->where('BLOQUEO', '0')->get(['CODNIV', 'ELSCX']);

        $porNivel = [];    // codniv => [clave => true]
        $sinMapear = [];   // ELSCX habilitados que no tienen equivalente nuevo
        foreach ($filas as $f) {
            $cod   = trim((string) $f->CODNIV);
            $elscx = trim((string) $f->ELSCX);
            if ($elscx === '') continue;
            if (isset(self::MAP[$elscx])) {
                $porNivel[$cod][self::MAP[$elscx]] = true;
            } else {
                $sinMapear[$elscx] = true;
            }
        }

        // Informe por rol
        $this->info('=== Sincronización de permisos desde FoxPro (nivform + formularios) ===');
        foreach ($this->descripcionNiveles() as $cod => $desc) {
            $n = isset($porNivel[$cod]) ? count($porNivel[$cod]) : 0;
            $this->line(sprintf('  Rol %-3s  %-38s -> %3d módulos', $cod, $desc, $n));
        }

        if (!empty($sinMapear)) {
            ksort($sinMapear);
            $this->newLine();
            $this->warn('Formularios Fox habilitados SIN equivalente en el menú nuevo (se ignoran):');
            $this->line('  ' . implode(', ', array_keys($sinMapear)));
        }

        // ── Usuarios: alinear cada uno a los permisos de su nivel (rol) asignado ──
        // El login arma los permisos desde usuario_permisos (por usuario), no desde
        // el nivel; por eso, además de nivel_permisos, hay que reescribir usuario_permisos.
        $usuarios = DB::table('usuarios')->get(['CODIGO', 'NIVEL', 'ES_ADMIN']);
        $usuariosPorNivel = [];   // nivel(int) => [CODIGO, ...]
        $admins = 0;
        foreach ($usuarios as $u) {
            if ((int) $u->ES_ADMIN === 1) { $admins++; continue; }
            $usuariosPorNivel[(int) trim((string) $u->NIVEL)][] = (int) $u->CODIGO;
        }
        ksort($usuariosPorNivel);

        $this->newLine();
        $this->info('=== Usuarios a alinear con su rol (los ES_ADMIN ven todo y se omiten) ===');
        $descr = $this->descripcionNiveles();
        $usuariosAActualizar = 0;
        foreach ($usuariosPorNivel as $nivel => $codigos) {
            $claves = array_keys($porNivel[(string) $nivel] ?? []);
            $n = count($codigos);
            if (empty($claves)) {
                $this->line(sprintf('  Rol %-3s %-30s -> %d usuario(s)  [rol sin módulos: se omiten]', $nivel, $descr[(string) $nivel] ?? '?', $n));
                continue;
            }
            $usuariosAActualizar += $n;
            $this->line(sprintf('  Rol %-3s %-30s -> %d usuario(s), %d módulos c/u', $nivel, $descr[(string) $nivel] ?? '?', $n, count($claves)));
        }
        $this->line(sprintf('  Administradores (ES_ADMIN, sin cambios): %d', $admins));

        if (!$aplicar) {
            $this->newLine();
            $this->comment('MODO INFORME (no se escribió nada). Corré con --aplicar para guardar los cambios.');
            return self::SUCCESS;
        }

        // Escritura transaccional: reemplaza los permisos de cada rol presente en nivform.
        DB::transaction(function () use ($porNivel) {
            foreach ($porNivel as $cod => $claves) {
                // Se graba nivel_codigo como entero, igual que la pantalla de Roles,
                // para que los tildes se muestren correctamente al leer.
                $nivel = (int) $cod;
                DB::table('nivel_permisos')->where('nivel_codigo', $nivel)->delete();
                $rows = array_map(
                    fn ($clave) => ['nivel_codigo' => $nivel, 'permiso_clave' => $clave],
                    array_keys($claves)
                );
                foreach (array_chunk($rows, 500) as $chunk) {
                    DB::table('nivel_permisos')->insert($chunk);
                }
            }
        });

        // Alinear cada usuario no-admin a los permisos de su nivel asignado.
        // Solo se tocan usuarios cuyo nivel tiene módulos (si el rol quedara vacío,
        // reemplazar([]) los dejaría "sin restricciones" = ver todo, lo contrario a lo buscado).
        $usuariosTocados = 0;
        DB::transaction(function () use ($usuariosPorNivel, $porNivel, &$usuariosTocados) {
            foreach ($usuariosPorNivel as $nivel => $codigos) {
                $claves = array_keys($porNivel[(string) $nivel] ?? []);
                if (empty($claves)) continue;
                foreach ($codigos as $codUsuario) {
                    \App\Models\UsuarioPermiso::reemplazar($codUsuario, $claves);
                    $usuariosTocados++;
                }
            }
        });

        $this->newLine();
        $this->info('Listo: nivel_permisos y usuario_permisos actualizados según la configuración FoxPro.');
        $this->line("  Usuarios alineados a su rol: {$usuariosTocados}");
        $this->comment('Los usuarios deben cerrar sesión y volver a entrar para tomar los permisos.');
        return self::SUCCESS;
    }

    /** CODNIV => descripción, para el informe. */
    private function descripcionNiveles(): array
    {
        $out = [];
        foreach (DB::table('niveles')->orderBy('CODNIV')->get() as $n) {
            $out[trim((string) $n->CODNIV)] = trim((string) $n->DESCRIBE);
        }
        return $out;
    }
}
