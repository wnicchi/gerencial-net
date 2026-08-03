<?php

/**
 * ============================================================
 * api.php — Rutas de la API REST
 * ============================================================
 * Define todas las rutas del backend del Sistema RRHH.NET.
 * Prefijo automático: /api  (configurado en bootstrap/app.php)
 *
 * Estructura de rutas:
 *
 *   PÚBLICAS (sin autenticación):
 *     POST /api/auth/login               → Login con email + contraseña
 *     POST /api/auth/verificar-usuario   → Paso 1 primer acceso
 *     POST /api/auth/enviar-codigo       → Paso 2 primer acceso
 *     POST /api/auth/validar-codigo      → Paso 3 primer acceso
 *     POST /api/auth/crear-password      → Paso 4 primer acceso
 *     POST /api/auth/forgot-password     → Solicitar reset de contraseña
 *     POST /api/auth/reset-password      → Confirmar nueva contraseña
 *
 *   PROTEGIDAS (requieren Bearer token Sanctum):
 *     POST /api/auth/logout              → Cerrar sesión
 *     GET  /api/auth/me                  → Datos del usuario autenticado
 *
 *   ADMINISTRACIÓN (solo NIVEL = 1):
 *     GET  /api/admin/usuarios
 *     GET  /api/admin/usuarios/{codigo}/permisos
 *     PUT  /api/admin/usuarios/{codigo}/permisos
 *
 *   MÓDULOS (se agregarán a medida que se desarrollen):
 *     /api/empleados, /api/vacaciones, /api/liquidaciones, etc.
 *
 * @package  Routes
 * @author   Sistema RRHH.NET
 * @version  1.0.0
 * @since    2026-06-08
 * ============================================================
 */

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\LogErrorController;
use App\Http\Controllers\LogActividadController;
use App\Http\Controllers\EmpleadoController;
use App\Http\Controllers\EstadoCivilController;
use App\Http\Controllers\InvitadoController;
use App\Http\Controllers\TipoDocController;
use App\Http\Controllers\EmpresaController;
use App\Http\Controllers\ContratistaController;
use App\Http\Controllers\LugarController;
use App\Http\Controllers\ConvenioController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\SectorController;
use App\Http\Controllers\SubsectorController;
use App\Http\Controllers\AsignacionController;
use App\Http\Controllers\HaberController;
use App\Http\Controllers\DescuentoController;
use App\Http\Controllers\ValorController;
use App\Http\Controllers\CarnetCategoriaController;
use App\Http\Controllers\CtaBancariaController;
use App\Http\Controllers\ComedorController;
use App\Http\Controllers\BloqueoController;
use App\Http\Controllers\AlertaController;
use App\Http\Controllers\VencimientoController;
use App\Http\Controllers\ViajeController;
use App\Http\Controllers\SugerenciaPuestoController;
use App\Http\Controllers\CompraController;
use App\Http\Controllers\NovedadController;
use App\Http\Controllers\HoraExtraDiariaController;
use App\Http\Controllers\HoraExtraPlanillaController;
use App\Http\Controllers\AlmuerzoController;
use App\Http\Controllers\ValeController;
use App\Http\Controllers\ContratistaTipoController;
use App\Http\Controllers\ExigenciaController;
use App\Http\Controllers\ContratistaExternoController;
use App\Http\Controllers\AccesoEmpresaController;
use App\Http\Controllers\ContratistaObligacionController;
use App\Http\Controllers\ContratistaFaltaController;
use App\Http\Controllers\ObraController;
use App\Http\Controllers\ObraSocialController;
use App\Http\Controllers\ViajanteController;
use App\Http\Controllers\ControlSueldoController;
use App\Http\Controllers\AgendaTelefonoController;
use App\Http\Controllers\LiquidacionController;
use App\Http\Controllers\ComparativaLiquidacionController;
use App\Http\Controllers\SueldosPagosController;
use App\Http\Controllers\SueldosImportarController;
use App\Http\Controllers\MultasController;
use App\Http\Controllers\BancoSantaFeController;
use App\Http\Controllers\BancoSantaFeConsultarController;
use App\Http\Controllers\BancoSantanderRioController;
use App\Http\Controllers\BancoSantanderRioConsultarController;
use App\Http\Controllers\BancoFrancesController;
use App\Http\Controllers\BancoFrancesConsultarController;
use App\Http\Controllers\BancoNacionController;
use App\Http\Controllers\BancoNacionConsultarController;
use App\Http\Controllers\BancoVariosController;
use App\Http\Controllers\BancoVariosConsultarController;
use App\Http\Controllers\VacacionesController;
use App\Http\Controllers\VacacionesConsultaController;
use App\Http\Controllers\RelojCapturasController;
use App\Http\Controllers\HorasTrabajadasController;
use App\Http\Controllers\RelojAjustesController;
use App\Http\Controllers\RelojPeriodosController;
use App\Http\Controllers\RelojFaltasController;
use App\Http\Controllers\RelojTurnosController;
use App\Http\Controllers\RelojEnviosController;
use App\Http\Controllers\RelojUbicacionController;
use App\Http\Controllers\RelojGruposController;
use App\Http\Controllers\LicenciaController;
use App\Http\Controllers\FeriadoController;
use App\Http\Controllers\SueldoTipoController;
use App\Http\Controllers\SueldoConceptoController;
use App\Http\Controllers\SueldoNetoController;
use App\Http\Controllers\SueldosListadosController;
use App\Http\Controllers\MejoresSueldosController;
use App\Http\Controllers\ResumenLiquidacionController;
use App\Http\Controllers\IaController;
use App\Http\Controllers\ParametrosController;
use App\Http\Controllers\UsuariosActivosController;
use App\Http\Controllers\AdelantosController;
use App\Http\Controllers\PuestoController;
use App\Http\Controllers\TareaController;
use App\Http\Controllers\TareaPuestoController;
use App\Http\Controllers\AsignarPuestoController;
use App\Http\Controllers\CalificacionController;
use Illuminate\Support\Facades\Route;

// ══════════════════════════════════════════════════════════════
// RUTAS PÚBLICAS — No requieren autenticación
// ══════════════════════════════════════════════════════════════

// throttle: limita fuerza bruta en login y recuperación de clave (20 req/min por IP).
Route::prefix('auth')->middleware('throttle:20,1')->group(function () {

    // ── Login normal ──────────────────────────────────────────
    Route::post('login', [AuthController::class, 'login']);

    // ── Primer acceso (activación de cuenta en 4 pasos) ──────
    // Paso 1: Verificar que el usuario (DATO1) existe y está activo
    Route::post('verificar-usuario', [AuthController::class, 'verificarUsuario']);

    // Paso 2: Registrar email y enviar código de verificación de 6 dígitos
    Route::post('enviar-codigo', [AuthController::class, 'enviarCodigo']);

    // Paso 3: Validar el código recibido por email
    Route::post('validar-codigo', [AuthController::class, 'validarCodigo']);

    // Paso 4: Establecer la contraseña definitiva y activar la cuenta
    Route::post('crear-password', [AuthController::class, 'crearPassword']);

    // ── Recuperación de contraseña ────────────────────────────
    // Por código de 6 dígitos al email registrado (reusa validar-codigo + crear-password)
    Route::post('recuperar', [AuthController::class, 'recuperarClave']);

    // (legacy) Link de reseteo por email — se mantiene por compatibilidad
    Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('reset-password', [AuthController::class, 'resetPassword']);
});

// Configuración pública (sin token): empresa del deploy, para que el login
// muestre el fondo/logo correcto antes de iniciar sesión.
Route::get('config', fn () => response()->json([
    'empresa' => config('rrhh.empresa'),
]));

// ══════════════════════════════════════════════════════════════
// RUTAS PROTEGIDAS — Requieren token Sanctum válido
// Header: Authorization: Bearer {token}
// ══════════════════════════════════════════════════════════════

Route::middleware(['auth:sanctum', \App\Http\Middleware\VerificarPermisoModulo::class])->group(function () {

    // ── Sesión ────────────────────────────────────────────────
    Route::post('auth/logout', [AuthController::class, 'logout']); // Cerrar sesión
    Route::get('auth/me',      [AuthController::class, 'me']);     // Datos del usuario actual
    Route::post('auth/cambiar-password', [AuthController::class, 'cambiarPassword']); // Cambiar la propia clave

    // ══════════════════════════════════════════════════════════
    // ADMINISTRACIÓN — Solo accesible para NIVEL = 1
    // El middleware 'admin' verifica que el usuario sea admin.
    // ══════════════════════════════════════════════════════════
    Route::middleware('admin')->prefix('admin')->group(function () {

        // ── Niveles de acceso ─────────────────────────────────
        // GET /api/admin/niveles
        Route::get('niveles', [AdminController::class, 'listarNiveles']);

        // ── Roles / Plantillas de permisos ────────────────────
        Route::get   ('roles',        [AdminController::class, 'roles']);
        Route::post  ('roles',        [AdminController::class, 'crearRol']);
        Route::get   ('roles/{cod}',  [AdminController::class, 'rolDetalle'])->whereNumber('cod');
        Route::put   ('roles/{cod}',  [AdminController::class, 'actualizarRol'])->whereNumber('cod');
        Route::delete('roles/{cod}',  [AdminController::class, 'eliminarRol'])->whereNumber('cod');

        // ── CRUD de usuarios ──────────────────────────────────
        // GET    /api/admin/usuarios           → listar todos
        Route::get ('usuarios',         [AdminController::class, 'listarUsuarios']);
        // POST   /api/admin/usuarios           → crear nuevo usuario
        Route::post('usuarios',         [AdminController::class, 'crearUsuario']);
        // PUT    /api/admin/usuarios/{codigo}  → editar datos del usuario
        Route::put ('usuarios/{codigo}',[AdminController::class, 'actualizarUsuario']);
        // PATCH  /api/admin/usuarios/{codigo}/estado → activar/desactivar
        Route::patch('usuarios/{codigo}/estado',       [AdminController::class, 'cambiarEstado']);
        // POST   /api/admin/usuarios/{codigo}/reset-acceso → forzar reactivación
        Route::post('usuarios/{codigo}/reset-acceso',  [AdminController::class, 'resetAcceso']);
        // POST   /api/admin/usuarios/{codigo}/establecer-password → el admin fija una clave
        Route::post('usuarios/{codigo}/establecer-password', [AdminController::class, 'establecerPassword'])->whereNumber('codigo');
        // GET    /api/admin/usuarios/{codigo}/ver-clave → el admin ve la clave actual (si hay copia visible)
        Route::get ('usuarios/{codigo}/ver-clave',           [AdminController::class, 'verClave'])->whereNumber('codigo');
        // GET    /api/admin/usuarios/{codigo}/detalle → datos completos para el ABM
        Route::get ('usuarios/{codigo}/detalle',       [AdminController::class, 'detalleUsuario'])->whereNumber('codigo');

        // ── Sub-Sectores asociados ────────────────────────────
        Route::get   ('subsectores',                           [AdminController::class, 'subsectores']);
        Route::get   ('usuarios/{codigo}/subsectores',         [AdminController::class, 'usuarioSubsectores'])->whereNumber('codigo');
        Route::post  ('usuarios/{codigo}/subsectores',         [AdminController::class, 'agregarSubsector'])->whereNumber('codigo');
        Route::delete('usuarios/{codigo}/subsectores/{subCod}',[AdminController::class, 'eliminarSubsector'])->whereNumber('codigo')->whereNumber('subCod');

        // ── Permisos de menú ──────────────────────────────────
        // GET /api/admin/usuarios/{codigo}/permisos
        Route::get('usuarios/{codigo}/permisos', [AdminController::class, 'getPermisos']);
        // PUT /api/admin/usuarios/{codigo}/permisos  Body: { permisos: string[] }
        Route::put('usuarios/{codigo}/permisos', [AdminController::class, 'setPermisos']);

        // Clonar permisos de un usuario a otro(s). Body: { origen, destinos[] }
        Route::post('permisos/clonar', [AdminController::class, 'clonarPermisos']);

        // Log de errores SQL (contiene comandos SQL → solo admin)
        Route::get   ('log-errores', [LogErrorController::class, 'index']);
        Route::delete('log-errores', [LogErrorController::class, 'purgar']);

        // Log de actividad / auditoría (contiene comandos SQL → solo admin)
        Route::get   ('log-actividad', [LogActividadController::class, 'index']);
        Route::delete('log-actividad', [LogActividadController::class, 'purgar']);
    });

    // ══════════════════════════════════════════════════════════
    // ASISTENTE IA — módulo Empleados
    // ══════════════════════════════════════════════════════════
    Route::post('ia/ayuda',        [IaController::class, 'ayuda']);
    Route::post('ia/empleados',    [IaController::class, 'empleados']);
    Route::post('ia/estado-civil', [IaController::class, 'estadoCivil']);
    Route::post('ia/listados',     [IaController::class, 'listados']);
    Route::post('ia/carnet',       [IaController::class, 'carnet']);
    Route::post('ia/centro-costo', [IaController::class, 'centroCosto']);
    Route::post('ia/exportar',     [IaController::class, 'exportar']);
    Route::post('ia/importar',     [IaController::class, 'importar']);
    Route::post('ia/obra-social',  [IaController::class, 'obraSocial']);
    Route::post('ia/invitados',    [IaController::class, 'invitados']);
    Route::post('ia/tipo-doc',     [IaController::class, 'tipoDoc']);
    Route::post('ia/empresas',     [IaController::class, 'empresas']);
    Route::post('ia/contratistas', [IaController::class, 'contratistas']);
    Route::post('ia/lugares',      [IaController::class, 'lugares']);
    Route::post('ia/convenios',    [IaController::class, 'convenios']);
    Route::post('ia/categorias',   [IaController::class, 'categorias']);
    Route::post('ia/sectores',     [IaController::class, 'sectores']);
    Route::post('ia/subsectores',  [IaController::class, 'subsectores']);
    Route::post('ia/departamentos',[IaController::class, 'departamentos']);
    Route::post('ia/frecuencias',  [IaController::class, 'frecuencias']);
    Route::post('ia/talles',       [IaController::class, 'talles']);
    Route::post('ia/rubros-ropa',  [IaController::class, 'rubrosRopa']);
    Route::post('ia/marcas-ropa',  [IaController::class, 'marcasRopa']);
    Route::post('ia/depositos-ropa',[IaController::class, 'depositosRopa']);
    Route::post('ia/ropa-epp',     [IaController::class, 'ropaEpp']);
    Route::post('ia/asignaciones', [IaController::class, 'asignaciones']);
    Route::post('ia/haberes',      [IaController::class, 'haberes']);
    Route::post('ia/descuentos',   [IaController::class, 'descuentos']);
    Route::post('ia/valores',      [IaController::class, 'valores']);
    Route::post('ia/carnet-categorias', [IaController::class, 'carnetCategorias']);
    Route::post('ia/ctas-bancarias',    [IaController::class, 'ctasBancarias']);
    Route::post('ia/comedores',    [IaController::class, 'comedores']);
    Route::post('ia/bloqueos',     [IaController::class, 'bloqueos']);
    Route::post('ia/alertas',      [IaController::class, 'alertas']);
    Route::post('ia/compras',      [IaController::class, 'compras']);
    Route::post('ia/novedades',    [IaController::class, 'novedades']);
    Route::post('ia/exportar-novedades', [IaController::class, 'exportarNovedades']);
    Route::post('ia/horas-extras-diarias', [IaController::class, 'horasExtrasDiarias']);
    Route::post('ia/planillas-hs-extras', [IaController::class, 'planillasHsExtras']);
    Route::post('ia/almuerzos', [IaController::class, 'almuerzos']);
    Route::post('ia/almuerzos-listados', [IaController::class, 'almuerzosListados']);
    Route::post('ia/vales', [IaController::class, 'vales']);
    Route::post('ia/contratista-tipos', [IaController::class, 'contratistaTipos']);
    Route::post('ia/exigencias', [IaController::class, 'exigencias']);
    Route::post('ia/contratistas-externos', [IaController::class, 'contratistasExternos']);
    Route::post('ia/accesos-empresa', [IaController::class, 'accesosEmpresa']);
    Route::post('ia/empleados-contratista', [IaController::class, 'empleadosContratista']);
    Route::post('ia/importar-empleados-contratista', [IaController::class, 'importarEmpleadosContratista']);
    Route::post('ia/obligaciones-contratista', [IaController::class, 'obligacionesContratista']);
    Route::post('ia/contratistas-faltas', [IaController::class, 'contratistasFaltas']);
    Route::post('ia/obras-habilitar', [IaController::class, 'obrasHabilitar']);
    Route::post('ia/obras-listados', [IaController::class, 'obrasListados']);
    Route::post('ia/obras-accesos', [IaController::class, 'obrasAccesos']);
    Route::post('ia/obras-modificar', [IaController::class, 'obrasModificar']);
    Route::post('ia/obras-sociales', [IaController::class, 'obrasSociales']);
    Route::post('ia/obras-sociales-importar', [IaController::class, 'obrasSocialesImportar']);
    Route::post('ia/obras-sociales-aportes', [IaController::class, 'obrasSocialesAportes']);
    Route::post('ia/viajantes', [IaController::class, 'viajantes']);
    Route::post('ia/control-sueldos', [IaController::class, 'controlSueldos']);
    Route::post('ia/telefonos', [IaController::class, 'telefonos']);
    Route::post('ia/liquidaciones', [IaController::class, 'liquidaciones']);
    Route::post('ia/comparativa-liquidaciones', [IaController::class, 'comparativaLiquidaciones']);
    Route::post('ia/sueldos-netos', [IaController::class, 'sueldosNetos']);
    Route::post('ia/sueldos-listados', [IaController::class, 'sueldosListados']);
    Route::post('ia/mejores-sueldos', [IaController::class, 'mejoresSueldos']);
    Route::post('ia/resumen-liquidaciones', [IaController::class, 'resumenLiquidaciones']);
    Route::post('ia/sueldos-pagos', [IaController::class, 'sueldosPagos']);
    Route::post('ia/sueldos-importar', [IaController::class, 'sueldosImportar']);
    Route::post('ia/multas', [IaController::class, 'multas']);
    Route::post('ia/bco-santa-fe', [IaController::class, 'bcoSantaFe']);
    Route::post('ia/bco-santa-fe-consultar', [IaController::class, 'bcoSantaFeConsultar']);
    Route::post('ia/bco-santander-rio', [IaController::class, 'bcoSantanderRio']);
    Route::post('ia/bco-santander-rio-consultar', [IaController::class, 'bcoSantanderRioConsultar']);
    Route::post('ia/bco-frances', [IaController::class, 'bcoFrances']);
    Route::post('ia/bco-frances-consultar', [IaController::class, 'bcoFrancesConsultar']);
    Route::post('ia/bco-nacion', [IaController::class, 'bcoNacion']);
    Route::post('ia/bco-nacion-consultar', [IaController::class, 'bcoNacionConsultar']);
    Route::post('ia/bco-varios', [IaController::class, 'bcoVarios']);
    Route::post('ia/bco-varios-consultar', [IaController::class, 'bcoVariosConsultar']);
    Route::post('ia/vacaciones-agregar', [IaController::class, 'vacacionesAgregar']);
    Route::post('ia/vacaciones-acciones', [IaController::class, 'vacacionesAcciones']);
    Route::post('ia/vacaciones-programadas', [IaController::class, 'vacacionesProgramadas']);
    Route::post('ia/vacaciones-planilla', [IaController::class, 'vacacionesPlanilla']);
    Route::post('ia/vacaciones-definicion', [IaController::class, 'vacacionesDefinicion']);
    Route::post('ia/vacaciones-informe', [IaController::class, 'vacacionesInforme']);
    Route::post('ia/vacaciones-pendientes', [IaController::class, 'vacacionesPendientes']);
    Route::post('ia/reloj-capturas', [IaController::class, 'relojCapturas']);
    Route::post('ia/horas-trabajadas', [IaController::class, 'horasTrabajadas']);
    Route::post('ia/parte-diario', [IaController::class, 'parteDiario']);
    Route::post('ia/llegadas-tarde', [IaController::class, 'llegadasTarde']);
    Route::post('ia/secretaria-trabajo', [IaController::class, 'secretariaTrabajo']);
    Route::post('ia/reloj-ajustes', [IaController::class, 'relojAjustes']);
    Route::post('ia/reloj-ajustes-borrar', [IaController::class, 'relojAjustesBorrar']);
    Route::post('ia/reloj-periodos', [IaController::class, 'relojPeriodos']);
    Route::post('ia/reloj-faltas', [IaController::class, 'relojFaltas']);
    Route::post('ia/reloj-faltas-edicion', [IaController::class, 'relojFaltasEdicion']);
    Route::post('ia/reloj-faltas-listados', [IaController::class, 'relojFaltasListados']);
    Route::post('ia/reloj-turnos', [IaController::class, 'relojTurnos']);
    Route::post('ia/reloj-envios', [IaController::class, 'relojEnvios']);
    Route::post('ia/reloj-ubicaciones', [IaController::class, 'relojUbicaciones']);
    Route::post('ia/reloj-grupos', [IaController::class, 'relojGrupos']);
    Route::post('ia/licencias', [IaController::class, 'licencias']);
    Route::post('ia/feriados', [IaController::class, 'feriados']);
    Route::post('ia/sueldos-tipos', [IaController::class, 'sueldosTipos']);
    Route::post('ia/sueldos-conceptos', [IaController::class, 'sueldosConceptos']);

    // ══════════════════════════════════════════════════════════
    // MÓDULO: ESTADO CIVIL (ABM)
    // ══════════════════════════════════════════════════════════
    Route::get   ('estado-civil',        [EstadoCivilController::class, 'index']);
    Route::post  ('estado-civil',        [EstadoCivilController::class, 'store']);
    Route::put   ('estado-civil/{cod}',  [EstadoCivilController::class, 'update']);
    Route::delete('estado-civil/{cod}',  [EstadoCivilController::class, 'destroy']);

    // ══════════════════════════════════════════════════════════
    // MÓDULO: INVITADOS (ABM)
    // ══════════════════════════════════════════════════════════
    Route::get   ('invitados',        [InvitadoController::class, 'index']);
    Route::post  ('invitados',        [InvitadoController::class, 'store']);
    Route::put   ('invitados/{cod}',  [InvitadoController::class, 'update']);
    Route::delete('invitados/{cod}',  [InvitadoController::class, 'destroy']);

    // ══════════════════════════════════════════════════════════
    // MÓDULO: TIPO DE DOCUMENTACIÓN (ABM)
    // ══════════════════════════════════════════════════════════
    Route::get   ('tipo-doc',        [TipoDocController::class, 'index']);
    Route::post  ('tipo-doc',        [TipoDocController::class, 'store']);
    Route::put   ('tipo-doc/{cod}',  [TipoDocController::class, 'update']);
    Route::delete('tipo-doc/{cod}',  [TipoDocController::class, 'destroy']);

    // ══════════════════════════════════════════════════════════
    // MÓDULO: EMPRESAS (ABM)
    // ══════════════════════════════════════════════════════════
    Route::get   ('empresas',        [EmpresaController::class, 'index']);
    Route::post  ('empresas',        [EmpresaController::class, 'store']);
    Route::put   ('empresas/{cod}',  [EmpresaController::class, 'update']);
    Route::delete('empresas/{cod}',  [EmpresaController::class, 'destroy']);

    // ══════════════════════════════════════════════════════════
    // MÓDULO: CONTRATISTAS (ABM)
    // ══════════════════════════════════════════════════════════
    Route::get   ('contratistas',        [ContratistaController::class, 'index']);
    Route::post  ('contratistas',        [ContratistaController::class, 'store']);
    Route::put   ('contratistas/{cod}',  [ContratistaController::class, 'update']);
    Route::delete('contratistas/{cod}',  [ContratistaController::class, 'destroy']);

    // ══════════════════════════════════════════════════════════
    // MÓDULO: LUGARES (ABM)
    // ══════════════════════════════════════════════════════════
    Route::get   ('lugares',        [LugarController::class, 'index']);
    Route::post  ('lugares',        [LugarController::class, 'store']);
    Route::put   ('lugares/{cod}',  [LugarController::class, 'update']);
    Route::delete('lugares/{cod}',  [LugarController::class, 'destroy']);

    // ══════════════════════════════════════════════════════════
    // MÓDULO: CONVENIOS (ABM + obligaciones EPP)
    // ══════════════════════════════════════════════════════════
    Route::get   ('convenios-ropa',      [ConvenioController::class, 'ropaCatalogo']);
    Route::get   ('convenios',           [ConvenioController::class, 'index']);
    Route::post  ('convenios',           [ConvenioController::class, 'store']);
    Route::get   ('convenios/{cod}',     [ConvenioController::class, 'show']);
    Route::put   ('convenios/{cod}',     [ConvenioController::class, 'update']);
    Route::delete('convenios/{cod}',     [ConvenioController::class, 'destroy']);
    Route::get   ('convenios/{cod}/epp', [ConvenioController::class, 'eppList']);
    Route::post  ('convenios/{cod}/epp', [ConvenioController::class, 'eppAdd']);
    Route::delete('convenios/{cod}/epp', [ConvenioController::class, 'eppDelete']);

    // ══════════════════════════════════════════════════════════
    // MÓDULO: CATEGORÍAS (ABM)
    // ══════════════════════════════════════════════════════════
    Route::get   ('categorias',        [CategoriaController::class, 'index']);
    Route::post  ('categorias',        [CategoriaController::class, 'store']);
    Route::put   ('categorias/{cod}',  [CategoriaController::class, 'update']);
    Route::delete('categorias/{cod}',  [CategoriaController::class, 'destroy']);

    // ══════════════════════════════════════════════════════════
    // MÓDULO: SECTORES LABORALES (ABM)
    // ══════════════════════════════════════════════════════════
    Route::get   ('sectores',        [SectorController::class, 'index']);
    Route::post  ('sectores',        [SectorController::class, 'store']);
    Route::put   ('sectores/{cod}',  [SectorController::class, 'update']);
    Route::delete('sectores/{cod}',  [SectorController::class, 'destroy']);

    // ── Departamentos (ABM) ───────────────────────────────────
    Route::get   ('departamentos',        [\App\Http\Controllers\DepartamController::class, 'index']);
    Route::post  ('departamentos',        [\App\Http\Controllers\DepartamController::class, 'store']);
    Route::put   ('departamentos/{cod}',  [\App\Http\Controllers\DepartamController::class, 'update'])->whereNumber('cod');
    Route::delete('departamentos/{cod}',  [\App\Http\Controllers\DepartamController::class, 'destroy'])->whereNumber('cod');

    // ── Asignar EPP utilizada por empleado (per_ropa) ─────────
    Route::get ('asignar-epp/empleado/{cod}', [\App\Http\Controllers\AsignarEppController::class, 'empleado'])->whereNumber('cod');
    Route::get ('asignar-epp/ropa/{cod}',     [\App\Http\Controllers\AsignarEppController::class, 'ropa'])->whereNumber('cod');
    Route::post('asignar-epp/{cod}',          [\App\Http\Controllers\AsignarEppController::class, 'guardar'])->whereNumber('cod');

    // ── Uniforme/EPP — Entrega de Ropa ────────────────────────
    Route::get ('entrega-ropa/init',           [\App\Http\Controllers\EntregaRopaController::class, 'init']);
    Route::get ('entrega-ropa/empleado/{cod}', [\App\Http\Controllers\EntregaRopaController::class, 'empleado'])->whereNumber('cod');
    Route::get ('entrega-ropa/ropa/{cod}',     [\App\Http\Controllers\EntregaRopaController::class, 'ropa'])->whereNumber('cod');
    Route::post('entrega-ropa',                [\App\Http\Controllers\EntregaRopaController::class, 'entregar']);
    Route::post('ia/entrega-ropa',             [IaController::class, 'entregaRopa']);

    // ── Uniforme/EPP — Borrar Ropa entregada ──────────────────
    Route::get ('borrar-ropa',          [\App\Http\Controllers\BorrarRopaController::class, 'index']);
    Route::post('borrar-ropa/devolver', [\App\Http\Controllers\BorrarRopaController::class, 'devolver']);
    Route::post('ia/borrar-ropa',       [IaController::class, 'borrarRopa']);

    // ── Uniforme/EPP — Ingreso de Ropa a stock ────────────────
    Route::get ('ingreso-ropa/init',       [\App\Http\Controllers\IngresoRopaController::class, 'init']);
    Route::get ('ingreso-ropa/ropa/{cod}', [\App\Http\Controllers\IngresoRopaController::class, 'ropa'])->whereNumber('cod');
    Route::post('ingreso-ropa',            [\App\Http\Controllers\IngresoRopaController::class, 'ingresar']);
    Route::post('ia/ingreso-ropa',         [IaController::class, 'ingresoRopa']);

    // ── Uniforme/EPP — Transferencia entre depósitos ──────────
    Route::get ('transferencia-ropa/init',       [\App\Http\Controllers\TransferenciaRopaController::class, 'init']);
    Route::get ('transferencia-ropa/ropa/{cod}', [\App\Http\Controllers\TransferenciaRopaController::class, 'ropa'])->whereNumber('cod');
    Route::post('transferencia-ropa',            [\App\Http\Controllers\TransferenciaRopaController::class, 'transferir']);
    Route::post('ia/transferencia-ropa',         [IaController::class, 'transferenciaRopa']);

    // ── Uniforme/EPP — Estadística de entregas ────────────────
    Route::get ('estadistica-ropa/init', [\App\Http\Controllers\EstadisticaRopaController::class, 'init']);
    Route::get ('estadistica-ropa',      [\App\Http\Controllers\EstadisticaRopaController::class, 'consultar']);
    Route::post('ia/estadistica-ropa',   [IaController::class, 'estadisticaRopa']);

    // ── Uniforme/EPP — Entrega histórica por empleado ─────────
    Route::get ('entrega-historica/empleado/{cod}', [\App\Http\Controllers\EntregaHistoricaController::class, 'empleado'])->whereNumber('cod');
    Route::post('entrega-historica/recibo/{cod}',   [\App\Http\Controllers\EntregaHistoricaController::class, 'recibo'])->whereNumber('cod');
    Route::post('ia/entrega-historica',             [IaController::class, 'entregaHistorica']);

    // ── Capacitación — Áreas Temáticas (ABM tema) ─────────────
    Route::get   ('areas-tematicas',       [\App\Http\Controllers\TemaController::class, 'index']);
    Route::post  ('areas-tematicas',       [\App\Http\Controllers\TemaController::class, 'store']);
    Route::put   ('areas-tematicas/{cod}', [\App\Http\Controllers\TemaController::class, 'update'])->whereNumber('cod');
    Route::delete('areas-tematicas/{cod}', [\App\Http\Controllers\TemaController::class, 'destroy'])->whereNumber('cod');
    Route::post  ('ia/areas-tematicas',    [IaController::class, 'areasTematicas']);

    // ── Capacitación — Disertantes (ABM) ──────────────────────
    Route::get   ('disertantes',       [\App\Http\Controllers\DisertanteController::class, 'index']);
    Route::post  ('disertantes',       [\App\Http\Controllers\DisertanteController::class, 'store']);
    Route::put   ('disertantes/{cod}', [\App\Http\Controllers\DisertanteController::class, 'update'])->whereNumber('cod');
    Route::delete('disertantes/{cod}', [\App\Http\Controllers\DisertanteController::class, 'destroy'])->whereNumber('cod');
    Route::post  ('ia/disertantes',    [IaController::class, 'disertantes']);

    // ── Control de Salud — Médicos (ABM) ──────────────────────
    Route::get   ('medicos',       [\App\Http\Controllers\MedicoController::class, 'index']);
    Route::post  ('medicos',       [\App\Http\Controllers\MedicoController::class, 'store']);
    Route::put   ('medicos/{cod}', [\App\Http\Controllers\MedicoController::class, 'update'])->whereNumber('cod');
    Route::delete('medicos/{cod}', [\App\Http\Controllers\MedicoController::class, 'destroy'])->whereNumber('cod');
    Route::post  ('ia/medicos',    [IaController::class, 'medicos']);

    // ── Control de Salud — Tipo de Exámenes (ABM) ─────────────
    Route::get   ('examenes-tipo',       [\App\Http\Controllers\ExamenTipoController::class, 'index']);
    Route::post  ('examenes-tipo',       [\App\Http\Controllers\ExamenTipoController::class, 'store']);
    Route::put   ('examenes-tipo/{cod}', [\App\Http\Controllers\ExamenTipoController::class, 'update'])->whereNumber('cod');
    Route::delete('examenes-tipo/{cod}', [\App\Http\Controllers\ExamenTipoController::class, 'destroy'])->whereNumber('cod');
    Route::post  ('ia/examenes-tipo',    [IaController::class, 'examenesTipo']);

    // ── Requerimientos (ABM + documentación) ──────────────────
    Route::get   ('requerimientos/init',              [\App\Http\Controllers\RequerimientoController::class, 'init']);
    Route::get   ('requerimientos',                   [\App\Http\Controllers\RequerimientoController::class, 'index']);
    Route::post  ('requerimientos',                   [\App\Http\Controllers\RequerimientoController::class, 'store']);
    Route::get   ('requerimientos/{cod}/documentos',  [\App\Http\Controllers\RequerimientoController::class, 'documentos'])->whereNumber('cod');
    Route::post  ('requerimientos/{cod}/documento',   [\App\Http\Controllers\RequerimientoController::class, 'subirDocumento'])->whereNumber('cod');
    Route::get   ('requerimientos/documento/{id}/ver',[\App\Http\Controllers\RequerimientoController::class, 'verDocumento'])->whereNumber('id');
    Route::delete('requerimientos/documento/{id}',    [\App\Http\Controllers\RequerimientoController::class, 'eliminarDocumento'])->whereNumber('id');
    Route::put   ('requerimientos/{cod}',             [\App\Http\Controllers\RequerimientoController::class, 'update'])->whereNumber('cod');
    Route::delete('requerimientos/{cod}',             [\App\Http\Controllers\RequerimientoController::class, 'destroy'])->whereNumber('cod');
    Route::post  ('ia/requerimientos',                [IaController::class, 'requerimientos']);

    // ── Clientes (consulta a la base de gestión) ──────────────
    Route::get   ('clientes/buscar', [\App\Http\Controllers\ClienteController::class, 'buscar']);
    Route::get   ('clientes/{cod}',  [\App\Http\Controllers\ClienteController::class, 'show'])->whereNumber('cod');

    // ── Requerimientos por Cliente ────────────────────────────
    Route::get   ('requerimientos-clientes/init',                 [\App\Http\Controllers\RequerimientoClienteController::class, 'init']);
    Route::get   ('requerimientos-clientes/cliente/{cod}',        [\App\Http\Controllers\RequerimientoClienteController::class, 'cliente'])->whereNumber('cod');
    Route::post  ('requerimientos-clientes/cliente/{cod}',        [\App\Http\Controllers\RequerimientoClienteController::class, 'guardar'])->whereNumber('cod');
    Route::post  ('requerimientos-clientes/cliente/{cod}/documento', [\App\Http\Controllers\RequerimientoClienteController::class, 'subirDocumento'])->whereNumber('cod');
    Route::get   ('requerimientos-clientes/documento/{id}/ver',   [\App\Http\Controllers\RequerimientoClienteController::class, 'verDocumento'])->whereNumber('id');
    Route::delete('requerimientos-clientes/documento/{id}',       [\App\Http\Controllers\RequerimientoClienteController::class, 'eliminarDocumento'])->whereNumber('id');
    Route::post  ('ia/requerimientos-clientes',                   [IaController::class, 'requerimientosClientes']);

    // ── Requerimientos Informes (envío .eml + PDFs) ───────────
    Route::get   ('requerimientos-informes/clientes',              [\App\Http\Controllers\RequerimientoInformeController::class, 'clientes']);
    Route::get   ('requerimientos-informes/cliente/{cod}/preview', [\App\Http\Controllers\RequerimientoInformeController::class, 'preview'])->whereNumber('cod');
    Route::post  ('requerimientos-informes/cliente/{cod}/email',   [\App\Http\Controllers\RequerimientoInformeController::class, 'email'])->whereNumber('cod');
    Route::get   ('requerimientos-informes/general',               [\App\Http\Controllers\RequerimientoInformeController::class, 'general']);
    Route::get   ('requerimientos-informes/documento/{id}/ver',    [\App\Http\Controllers\RequerimientoInformeController::class, 'verDocumento'])->whereNumber('id');
    Route::post  ('ia/requerimientos-informes',                    [IaController::class, 'requerimientosInformes']);

    // ── Requerimientos Emails Enviados (histórico + reenvío) ──
    Route::get   ('requerimientos-enviados',                    [\App\Http\Controllers\RequerimientoEnviadoController::class, 'index']);
    Route::get   ('requerimientos-enviados/{unico}/adjuntos',   [\App\Http\Controllers\RequerimientoEnviadoController::class, 'adjuntos'])->whereNumber('unico');
    Route::post  ('requerimientos-enviados/{unico}/reenviar',   [\App\Http\Controllers\RequerimientoEnviadoController::class, 'reenviar'])->whereNumber('unico');
    Route::post  ('ia/requerimientos-enviados',                 [IaController::class, 'requerimientosEnviados']);

    // ── Permisos Laborales (base de gestión) ──────────────────
    Route::get   ('permisos-laborales',          [\App\Http\Controllers\PermisoLaboralController::class, 'index']);
    Route::get   ('permisos-laborales/pendientes/{empleado}', [\App\Http\Controllers\PermisoLaboralController::class, 'pendientesEmpleado'])->whereNumber('empleado');
    Route::post  ('permisos-laborales/confirmar', [\App\Http\Controllers\PermisoLaboralController::class, 'confirmar']);
    Route::post  ('ia/permisos-laborales',       [IaController::class, 'permisosLaborales']);

    // ── Portal del Encargado: solicitar permisos para el personal a cargo ──
    Route::get   ('mis-permisos/equipo', [\App\Http\Controllers\PermisoLaboralController::class, 'miEquipo']);
    Route::get   ('mis-permisos/tipos',  [\App\Http\Controllers\PermisoLaboralController::class, 'tipos']);
    Route::get   ('mis-permisos',        [\App\Http\Controllers\PermisoLaboralController::class, 'mias']);
    Route::post  ('mis-permisos',        [\App\Http\Controllers\PermisoLaboralController::class, 'solicitar']);

    // ── Entrevistas (ABM + foto + documentación) ──────────────
    Route::get   ('entrevistas/init',              [\App\Http\Controllers\EntrevistaController::class, 'init']);
    Route::get   ('entrevistas/listado',           [\App\Http\Controllers\EntrevistaController::class, 'listado']);
    Route::get   ('entrevistas',                   [\App\Http\Controllers\EntrevistaController::class, 'index']);
    Route::post  ('entrevistas',                   [\App\Http\Controllers\EntrevistaController::class, 'store']);
    Route::get   ('entrevistas/documento/{id}/ver',[\App\Http\Controllers\EntrevistaController::class, 'verDocumento'])->whereNumber('id');
    Route::delete('entrevistas/documento/{id}',    [\App\Http\Controllers\EntrevistaController::class, 'eliminarDocumento'])->whereNumber('id');
    Route::get   ('entrevistas/{cod}',             [\App\Http\Controllers\EntrevistaController::class, 'show'])->whereNumber('cod');
    Route::put   ('entrevistas/{cod}',             [\App\Http\Controllers\EntrevistaController::class, 'update'])->whereNumber('cod');
    Route::get   ('entrevistas/{cod}/foto',        [\App\Http\Controllers\EntrevistaController::class, 'foto'])->whereNumber('cod');
    Route::post  ('entrevistas/{cod}/foto',        [\App\Http\Controllers\EntrevistaController::class, 'subirFoto'])->whereNumber('cod');
    Route::delete('entrevistas/{cod}/foto',        [\App\Http\Controllers\EntrevistaController::class, 'eliminarFoto'])->whereNumber('cod');
    Route::post  ('entrevistas/{cod}/documento',   [\App\Http\Controllers\EntrevistaController::class, 'subirDocumento'])->whereNumber('cod');
    Route::post  ('ia/entrevistas',                [IaController::class, 'entrevistas']);
    Route::get   ('entrevistas-consulta',          [\App\Http\Controllers\EntrevistaConsultaController::class, 'index']);
    Route::post  ('ia/entrevistas-consulta',       [IaController::class, 'entrevistasConsulta']);

    // ── ART Siniestros — Agregar ──────────────────────────────
    Route::get   ('siniestros/init',               [\App\Http\Controllers\SiniestroController::class, 'init']);
    Route::get   ('siniestros/buscar',             [\App\Http\Controllers\SiniestroController::class, 'buscar']);
    Route::get   ('siniestros/listado-rango',      [\App\Http\Controllers\SiniestroController::class, 'listadoRango']);
    Route::get   ('siniestros/listado',            [\App\Http\Controllers\SiniestroController::class, 'listado']);
    Route::get   ('siniestros/grilla',             [\App\Http\Controllers\SiniestroController::class, 'grilla']);
    Route::get   ('siniestros/{nro}/agenda',       [\App\Http\Controllers\SiniestroController::class, 'agenda'])->whereNumber('nro');
    Route::post  ('siniestros/{nro}/agenda',       [\App\Http\Controllers\SiniestroController::class, 'agendaAgregar'])->whereNumber('nro');
    Route::put   ('siniestros/agenda/{imp}',       [\App\Http\Controllers\SiniestroController::class, 'agendaActualizar'])->whereNumber('imp');
    Route::delete('siniestros/agenda/{imp}',       [\App\Http\Controllers\SiniestroController::class, 'agendaEliminar'])->whereNumber('imp');
    Route::post  ('ia/siniestros-listados',        [IaController::class, 'siniestrosListados']);
    Route::post  ('ia/siniestros-seguimiento',     [IaController::class, 'siniestrosSeguimiento']);
    Route::get   ('siniestros/{nro}',              [\App\Http\Controllers\SiniestroController::class, 'show'])->whereNumber('nro');
    Route::get   ('siniestros/{nro}/documento/{orden}/ver', [\App\Http\Controllers\SiniestroController::class, 'verDocumento'])->whereNumber('nro')->whereNumber('orden');
    Route::post  ('siniestros',                     [\App\Http\Controllers\SiniestroController::class, 'agregar']);
    Route::post  ('ia/siniestros-agregar',         [IaController::class, 'siniestrosAgregar']);
    Route::post  ('ia/siniestros-consultar',       [IaController::class, 'siniestrosConsultar']);
    Route::delete('siniestros/{nro}',              [\App\Http\Controllers\SiniestroController::class, 'eliminar'])->whereNumber('nro');
    Route::post  ('ia/siniestros-eliminar',        [IaController::class, 'siniestrosEliminar']);
    Route::put   ('siniestros/{nro}',              [\App\Http\Controllers\SiniestroController::class, 'actualizar'])->whereNumber('nro');
    Route::post  ('siniestros/{nro}/reintegro',    [\App\Http\Controllers\SiniestroController::class, 'reintegro'])->whereNumber('nro');
    Route::post  ('ia/siniestros-modificar',       [IaController::class, 'siniestrosModificar']);
    Route::post  ('ia/siniestros-impresion',       [IaController::class, 'siniestrosImpresion']);

    // ── Telefonía Celular ─────────────────────────────────────
    Route::get ('celulares/equipos/buscar',        [\App\Http\Controllers\CelularController::class, 'equiposBuscar']);
    Route::get ('celulares/equipos-lista',         [\App\Http\Controllers\CelularController::class, 'equiposLista']);
    Route::get ('celulares/equipos/{cod}/historial', [\App\Http\Controllers\CelularController::class, 'equipoHistorial'])->whereNumber('cod');
    Route::get ('celulares/equipos/{cod}',         [\App\Http\Controllers\CelularController::class, 'equipo'])->whereNumber('cod');
    Route::post('celulares/equipos',               [\App\Http\Controllers\CelularController::class, 'equipoGuardar']);
    Route::put ('celulares/equipos/{cod}',         [\App\Http\Controllers\CelularController::class, 'equipoActualizar'])->whereNumber('cod');
    Route::get ('celulares/empleado/{emp}',        [\App\Http\Controllers\CelularController::class, 'empleado'])->whereNumber('emp');
    Route::post('celulares/asignar',               [\App\Http\Controllers\CelularController::class, 'asignar']);
    Route::post('celulares/devolver',              [\App\Http\Controllers\CelularController::class, 'devolver']);
    Route::get ('celulares/informe',               [\App\Http\Controllers\CelularController::class, 'informe']);
    Route::post('ia/celulares-asignar',            [IaController::class, 'celularesAsignar']);
    Route::post('ia/celulares-devolver',           [IaController::class, 'celularesDevolver']);
    Route::post('ia/celulares-informes',           [IaController::class, 'celularesInformes']);
    Route::post('ia/celulares-equipos',            [IaController::class, 'celularesEquipos']);

    // ── Costos Laborales — Editar Costos Fijos ────────────────
    Route::get   ('costos-laborales',       [\App\Http\Controllers\CostoLaboralController::class, 'index']);
    Route::post  ('costos-laborales',       [\App\Http\Controllers\CostoLaboralController::class, 'agregar']);
    Route::put   ('costos-laborales/{cod}', [\App\Http\Controllers\CostoLaboralController::class, 'actualizar'])->whereNumber('cod');
    Route::delete('costos-laborales/{cod}', [\App\Http\Controllers\CostoLaboralController::class, 'eliminar'])->whereNumber('cod');
    Route::post  ('ia/costos-fijos',        [IaController::class, 'costosFijos']);
    Route::get   ('costos-individual',       [\App\Http\Controllers\CostoIndividualController::class, 'calcular']);
    Route::post  ('ia/costos-individual',    [IaController::class, 'costosIndividual']);
    Route::get   ('costos-grupales/sectores',     [\App\Http\Controllers\CostoGrupalController::class, 'sectores']);
    Route::get   ('costos-grupales/buscar',       [\App\Http\Controllers\CostoGrupalController::class, 'buscar']);
    Route::post  ('costos-grupales/calcular',     [\App\Http\Controllers\CostoGrupalController::class, 'calcular']);
    Route::get   ('costos-grupales/empleado/{cod}',[\App\Http\Controllers\CostoGrupalController::class, 'empleado'])->whereNumber('cod');
    Route::post  ('ia/costos-grupales',      [IaController::class, 'costosGrupales']);
    Route::get   ('costos-informe',          [\App\Http\Controllers\CostoInformeController::class, 'informe']);
    Route::post  ('ia/costos-informe',       [IaController::class, 'costosInforme']);

    // ── Agenda de mensajes: catálogos (Temas / Grupos) ────────
    Route::get   ('agenda/usuarios',       [\App\Http\Controllers\AgendaController::class, 'usuarios']);
    Route::post  ('agenda/mensajes',       [\App\Http\Controllers\AgendaController::class, 'enviarMensaje']);
    Route::get   ('agenda/pendientes',     [\App\Http\Controllers\AgendaController::class, 'pendientes']);
    Route::get   ('agenda/historial',      [\App\Http\Controllers\AgendaController::class, 'historial']);
    Route::post  ('agenda/mensajes/{unico}/leer',      [\App\Http\Controllers\AgendaController::class, 'leer'])->whereNumber('unico');
    Route::post  ('agenda/mensajes/{unico}/responder', [\App\Http\Controllers\AgendaController::class, 'responder'])->whereNumber('unico');
    Route::get   ('agenda/mensajes/{unico}/adjunto',    [\App\Http\Controllers\AgendaController::class, 'adjunto'])->whereNumber('unico');
    Route::get   ('agenda/temas',          [\App\Http\Controllers\AgendaController::class, 'temasIndex']);
    Route::post  ('agenda/temas',          [\App\Http\Controllers\AgendaController::class, 'temasStore']);
    Route::put   ('agenda/temas/{cod}',    [\App\Http\Controllers\AgendaController::class, 'temasUpdate'])->whereNumber('cod');
    Route::delete('agenda/temas/{cod}',    [\App\Http\Controllers\AgendaController::class, 'temasDestroy'])->whereNumber('cod');
    Route::get   ('agenda/grupos',         [\App\Http\Controllers\AgendaController::class, 'gruposIndex']);
    Route::get   ('agenda/grupos/{cod}',   [\App\Http\Controllers\AgendaController::class, 'grupoShow'])->whereNumber('cod');
    Route::post  ('agenda/grupos',         [\App\Http\Controllers\AgendaController::class, 'gruposStore']);
    Route::put   ('agenda/grupos/{cod}',   [\App\Http\Controllers\AgendaController::class, 'gruposUpdate'])->whereNumber('cod');
    Route::delete('agenda/grupos/{cod}',   [\App\Http\Controllers\AgendaController::class, 'gruposDestroy'])->whereNumber('cod');

    // ── Apercibimientos ───────────────────────────────────────
    Route::get   ('apercibimientos/empleado/{emp}',       [\App\Http\Controllers\ApercibimientoController::class, 'empleado'])->whereNumber('emp');
    Route::get   ('apercibimientos/empleado/{emp}/lista', [\App\Http\Controllers\ApercibimientoController::class, 'lista'])->whereNumber('emp');
    Route::get   ('apercibimientos/grilla',               [\App\Http\Controllers\ApercibimientoController::class, 'grilla']);
    Route::post  ('apercibimientos',                      [\App\Http\Controllers\ApercibimientoController::class, 'agregar']);
    Route::delete('apercibimientos',                      [\App\Http\Controllers\ApercibimientoController::class, 'eliminar']);
    Route::post  ('ia/apercibimiento-agregar',            [IaController::class, 'apercibimientoAgregar']);
    Route::post  ('ia/apercibimiento-consultar',          [IaController::class, 'apercibimientoConsultar']);

    // ── Capacitación — Jornadas (ABM) ─────────────────────────
    Route::get   ('capacitaciones/init',  [\App\Http\Controllers\CapacitacionController::class, 'init']);
    Route::get   ('capacitaciones',       [\App\Http\Controllers\CapacitacionController::class, 'index']);
    Route::post  ('capacitaciones',       [\App\Http\Controllers\CapacitacionController::class, 'store']);
    Route::put   ('capacitaciones/{cod}', [\App\Http\Controllers\CapacitacionController::class, 'update'])->whereNumber('cod');
    Route::delete('capacitaciones/{cod}', [\App\Http\Controllers\CapacitacionController::class, 'destroy'])->whereNumber('cod');
    Route::post  ('ia/capacitaciones',    [IaController::class, 'capacitaciones']);

    // ── Capacitación — Asignar empleados a una jornada ────────
    Route::get ('asignacion-cap/capacitacion/{cod}', [\App\Http\Controllers\AsignarCapacitacionController::class, 'capacitacion'])->whereNumber('cod');
    Route::post('asignacion-cap/{cod}/agregar',      [\App\Http\Controllers\AsignarCapacitacionController::class, 'agregar'])->whereNumber('cod');
    Route::post('asignacion-cap/{cod}/necesitados',  [\App\Http\Controllers\AsignarCapacitacionController::class, 'necesitados'])->whereNumber('cod');
    Route::post('asignacion-cap/{cod}/eliminar',     [\App\Http\Controllers\AsignarCapacitacionController::class, 'eliminar'])->whereNumber('cod');
    Route::post('ia/asignacion-cap',                 [IaController::class, 'asignacionCap']);

    // ── Capacitación — Resultados ─────────────────────────────
    Route::get ('cap-resultado/init',              [\App\Http\Controllers\CapacitacionResultadoController::class, 'init']);
    Route::get ('cap-resultado/capacitacion/{cod}', [\App\Http\Controllers\CapacitacionResultadoController::class, 'capacitacion'])->whereNumber('cod');
    Route::post('cap-resultado/{cod}/resetear',    [\App\Http\Controllers\CapacitacionResultadoController::class, 'resetear'])->whereNumber('cod');
    Route::post('cap-resultado/{cod}/confirmar',   [\App\Http\Controllers\CapacitacionResultadoController::class, 'confirmar'])->whereNumber('cod');
    Route::post('cap-resultado/{cod}/finalizar',   [\App\Http\Controllers\CapacitacionResultadoController::class, 'finalizar'])->whereNumber('cod');
    Route::post('ia/cap-resultado',                [IaController::class, 'capResultado']);

    // ── Control de Salud — Exámenes (Agregar) ─────────────────
    Route::get ('examenes/init',           [\App\Http\Controllers\ExamenController::class, 'init']);
    Route::get ('examenes/empleado/{cod}', [\App\Http\Controllers\ExamenController::class, 'empleado'])->whereNumber('cod');
    Route::post('examenes',                [\App\Http\Controllers\ExamenController::class, 'agregar']);
    Route::get ('enfermedades',            [\App\Http\Controllers\ExamenController::class, 'enfermedades']);
    Route::post('examenes-medicos',        [\App\Http\Controllers\ExamenController::class, 'agregarMedico']);
    Route::get   ('examenes/listados',             [\App\Http\Controllers\ExamenController::class, 'listados']);
    Route::get   ('examenes/grilla',               [\App\Http\Controllers\ExamenController::class, 'grilla']);
    Route::get   ('examenes/proximos',             [\App\Http\Controllers\ExamenController::class, 'proximos']);
    Route::post  ('examenes/eliminar',             [\App\Http\Controllers\ExamenController::class, 'eliminar']);
    // Modificar
    Route::get   ('examenes/empleado/{cod}/lista', [\App\Http\Controllers\ExamenController::class, 'examenesEmpleado'])->whereNumber('cod');
    Route::put   ('examenes/{unico}',              [\App\Http\Controllers\ExamenController::class, 'actualizar'])->whereNumber('unico');
    Route::get   ('examenes/{unico}/documentos',   [\App\Http\Controllers\ExamenController::class, 'documentos'])->whereNumber('unico');
    Route::post  ('examenes/{unico}/documento',    [\App\Http\Controllers\ExamenController::class, 'agregarDocumento'])->whereNumber('unico');
    Route::get   ('examenes/documento/{id}/ver',   [\App\Http\Controllers\ExamenController::class, 'verDocumento'])->whereNumber('id');
    Route::delete('examenes/documento/{id}',       [\App\Http\Controllers\ExamenController::class, 'eliminarDocumento'])->whereNumber('id');
    Route::post('ia/examenes',             [IaController::class, 'examenes']);

    // ── Capacitación — Asociar Documentación ──────────────────
    Route::get   ('cap-doc/init',                 [\App\Http\Controllers\CapacitacionDocController::class, 'init']);
    Route::get   ('cap-doc/capacitacion/{cod}',   [\App\Http\Controllers\CapacitacionDocController::class, 'capacitacion'])->whereNumber('cod');
    Route::post  ('cap-doc/{cod}',                [\App\Http\Controllers\CapacitacionDocController::class, 'subir'])->whereNumber('cod');
    Route::get   ('cap-doc/documento/{unico}/ver', [\App\Http\Controllers\CapacitacionDocController::class, 'ver'])->whereNumber('unico');
    Route::delete('cap-doc/documento/{unico}',    [\App\Http\Controllers\CapacitacionDocController::class, 'eliminar'])->whereNumber('unico');
    Route::post  ('ia/cap-doc',                   [IaController::class, 'capDoc']);

    // ── Capacitación — Informes ───────────────────────────────
    Route::get ('informe-cap/init', [\App\Http\Controllers\InformeCapacitacionController::class, 'init']);
    Route::get ('informe-cap',      [\App\Http\Controllers\InformeCapacitacionController::class, 'consultar']);
    Route::post('ia/informe-cap',   [IaController::class, 'informeCap']);

    // ── Uniforme/EPP — Consulta de Stock ──────────────────────
    Route::get ('consulta-stock/init', [\App\Http\Controllers\ConsultaStockController::class, 'init']);
    Route::get ('consulta-stock',      [\App\Http\Controllers\ConsultaStockController::class, 'consultar']);
    Route::post('ia/consulta-stock',   [IaController::class, 'consultaStock']);

    // ── Uniforme/EPP — Carga de Inventario ────────────────────
    Route::get ('inventario-ropa/depositos',  [\App\Http\Controllers\InventarioRopaController::class, 'depositos']);
    Route::get ('inventario-ropa/stock/{dep}', [\App\Http\Controllers\InventarioRopaController::class, 'stock'])->whereNumber('dep');
    Route::post('inventario-ropa/{dep}',       [\App\Http\Controllers\InventarioRopaController::class, 'confirmar'])->whereNumber('dep');
    Route::post('ia/inventario-ropa',          [IaController::class, 'inventarioRopa']);

    // ── Uniforme/EPP — catálogo de prendas (ABM) ──────────────
    Route::get   ('ropa-epp',        [\App\Http\Controllers\RopaEppController::class, 'index']);
    Route::post  ('ropa-epp',        [\App\Http\Controllers\RopaEppController::class, 'store']);
    Route::put   ('ropa-epp/{cod}',  [\App\Http\Controllers\RopaEppController::class, 'update'])->whereNumber('cod');
    Route::delete('ropa-epp/{cod}',  [\App\Http\Controllers\RopaEppController::class, 'destroy'])->whereNumber('cod');

    // ── Marcas de Ropa/EPP (ABM) ──────────────────────────────
    Route::get   ('marcas-ropa',        [\App\Http\Controllers\MarcaRopaController::class, 'index']);
    Route::post  ('marcas-ropa',        [\App\Http\Controllers\MarcaRopaController::class, 'store']);
    Route::put   ('marcas-ropa/{cod}',  [\App\Http\Controllers\MarcaRopaController::class, 'update'])->whereNumber('cod');
    Route::delete('marcas-ropa/{cod}',  [\App\Http\Controllers\MarcaRopaController::class, 'destroy'])->whereNumber('cod');

    // ── Depósitos de Ropa/EPP (ABM) ───────────────────────────
    Route::get   ('depositos-ropa',        [\App\Http\Controllers\DepositoRopaController::class, 'index']);
    Route::post  ('depositos-ropa',        [\App\Http\Controllers\DepositoRopaController::class, 'store']);
    Route::put   ('depositos-ropa/{cod}',  [\App\Http\Controllers\DepositoRopaController::class, 'update'])->whereNumber('cod');
    Route::delete('depositos-ropa/{cod}',  [\App\Http\Controllers\DepositoRopaController::class, 'destroy'])->whereNumber('cod');

    // ── Talles (ABM, sin baja) ────────────────────────────────
    Route::get   ('talles',        [\App\Http\Controllers\TalleController::class, 'index']);
    Route::post  ('talles',        [\App\Http\Controllers\TalleController::class, 'store']);
    Route::put   ('talles/{cod}',  [\App\Http\Controllers\TalleController::class, 'update'])->whereNumber('cod');

    // ── Rubros de Ropa/EPP (ABM) ──────────────────────────────
    Route::get   ('rubros-ropa',        [\App\Http\Controllers\RubroRopaController::class, 'index']);
    Route::post  ('rubros-ropa',        [\App\Http\Controllers\RubroRopaController::class, 'store']);
    Route::put   ('rubros-ropa/{cod}',  [\App\Http\Controllers\RubroRopaController::class, 'update'])->whereNumber('cod');
    Route::delete('rubros-ropa/{cod}',  [\App\Http\Controllers\RubroRopaController::class, 'destroy'])->whereNumber('cod');

    // ── Frecuencias (ABM) ─────────────────────────────────────
    Route::get   ('frecuencias',        [\App\Http\Controllers\FrecuenciaController::class, 'index']);
    Route::post  ('frecuencias',        [\App\Http\Controllers\FrecuenciaController::class, 'store']);
    Route::put   ('frecuencias/{cod}',  [\App\Http\Controllers\FrecuenciaController::class, 'update'])->whereNumber('cod');
    Route::delete('frecuencias/{cod}',  [\App\Http\Controllers\FrecuenciaController::class, 'destroy'])->whereNumber('cod');

    // ══════════════════════════════════════════════════════════
    // MÓDULO: SUB-SECTORES LABORALES (ABM)
    // ══════════════════════════════════════════════════════════
    Route::get   ('subsectores',        [SubsectorController::class, 'index']);
    Route::post  ('subsectores',        [SubsectorController::class, 'store']);
    Route::put   ('subsectores/{cod}',  [SubsectorController::class, 'update']);
    Route::delete('subsectores/{cod}',  [SubsectorController::class, 'destroy']);

    // ══════════════════════════════════════════════════════════
    // MÓDULO: ASIGNACIONES FAMILIARES (ABM)
    // ══════════════════════════════════════════════════════════
    Route::get   ('asignaciones',        [AsignacionController::class, 'index']);
    Route::post  ('asignaciones',        [AsignacionController::class, 'store']);
    Route::put   ('asignaciones/{cod}',  [AsignacionController::class, 'update']);
    Route::delete('asignaciones/{cod}',  [AsignacionController::class, 'destroy']);

    // ══════════════════════════════════════════════════════════
    // MÓDULO: HABERES (ABM)
    // ══════════════════════════════════════════════════════════
    Route::get   ('haberes',        [HaberController::class, 'index']);
    Route::post  ('haberes',        [HaberController::class, 'store']);
    Route::put   ('haberes/{cod}',  [HaberController::class, 'update']);
    Route::delete('haberes/{cod}',  [HaberController::class, 'destroy']);

    // ══════════════════════════════════════════════════════════
    // MÓDULO: DESCUENTOS (ABM)
    // ══════════════════════════════════════════════════════════
    Route::get   ('descuentos',        [DescuentoController::class, 'index']);
    Route::post  ('descuentos',        [DescuentoController::class, 'store']);
    Route::put   ('descuentos/{cod}',  [DescuentoController::class, 'update']);
    Route::delete('descuentos/{cod}',  [DescuentoController::class, 'destroy']);

    // ══════════════════════════════════════════════════════════
    // MÓDULO: VALORES (parámetros, registro único)
    // ══════════════════════════════════════════════════════════
    Route::get('valores', [ValorController::class, 'show']);
    Route::put('valores', [ValorController::class, 'update']);

    // ══════════════════════════════════════════════════════════
    // MÓDULO: CATEGORÍAS DE CARNET (ABM)
    // ══════════════════════════════════════════════════════════
    Route::get   ('carnet-categorias',        [CarnetCategoriaController::class, 'index']);
    Route::post  ('carnet-categorias',        [CarnetCategoriaController::class, 'store']);
    Route::put   ('carnet-categorias/{cod}',  [CarnetCategoriaController::class, 'update']);
    Route::delete('carnet-categorias/{cod}',  [CarnetCategoriaController::class, 'destroy']);

    // ══════════════════════════════════════════════════════════
    // MÓDULO: CUENTAS BANCARIAS (ABM)
    // ══════════════════════════════════════════════════════════
    Route::get   ('ctas-bancarias',        [CtaBancariaController::class, 'index']);
    Route::post  ('ctas-bancarias',        [CtaBancariaController::class, 'store']);
    Route::put   ('ctas-bancarias/{cod}',  [CtaBancariaController::class, 'update']);
    Route::delete('ctas-bancarias/{cod}',  [CtaBancariaController::class, 'destroy']);

    // ══════════════════════════════════════════════════════════
    // MÓDULO: COMEDORES (ABM)
    // ══════════════════════════════════════════════════════════
    Route::get   ('comedores',        [ComedorController::class, 'index']);
    Route::post  ('comedores',        [ComedorController::class, 'store']);
    Route::put   ('comedores/{cod}',  [ComedorController::class, 'update']);
    Route::delete('comedores/{cod}',  [ComedorController::class, 'destroy']);

    // ══════════════════════════════════════════════════════════
    // MÓDULO: BLOQUEOS VARIOS (toggle por tipo/mes/año)
    // ══════════════════════════════════════════════════════════
    Route::get ('bloqueos/estado', [BloqueoController::class, 'estado']);
    Route::post('bloqueos',        [BloqueoController::class, 'grabar']);

    // ══════════════════════════════════════════════════════════
    // MÓDULO: ALERTAS (análisis varios; se muestra al iniciar sesión)
    // ══════════════════════════════════════════════════════════
    Route::get('alertas', [AlertaController::class, 'index']);
    Route::get('vencimientos', [VencimientoController::class, 'index']);
    Route::get('viajes/recientes', [ViajeController::class, 'recientes']);
    Route::get('viajes', [ViajeController::class, 'index']);
    Route::post('viajes', [ViajeController::class, 'store']);
    Route::post('viajes/eliminar', [ViajeController::class, 'eliminar']);
    Route::put('viajes/{unico}', [ViajeController::class, 'update'])->whereNumber('unico');
    Route::get('puestos-sugerencias', [SugerenciaPuestoController::class, 'index']);

    // ══════════════════════════════════════════════════════════
    // MÓDULO: CONSULTAR COMPRAS (lee de la base de Gestión)
    // ══════════════════════════════════════════════════════════
    Route::get('compras',                 [CompraController::class, 'index']);
    Route::get('compras/{nro}/detalle',   [CompraController::class, 'detalle']);
    Route::get('compras/{nro}/documento', [CompraController::class, 'documento']);

    // ══════════════════════════════════════════════════════════
    // MÓDULO: PLANILLA DE NOVEDADES DE SUELDOS
    // ══════════════════════════════════════════════════════════
    Route::get('novedades/planilla', [NovedadController::class, 'planilla']);
    Route::get('novedades/planillas-hs-extras', [NovedadController::class, 'planillasHsExtras']);
    Route::post('novedades/confirmar', [NovedadController::class, 'confirmar']);
    Route::get('novedades/exportar-datos', [NovedadController::class, 'exportarDatos']);

    // ══════════════════════════════════════════════════════════
    // MÓDULO: HORAS EXTRAS — EDICIÓN DIARIA
    // ══════════════════════════════════════════════════════════
    Route::get('horas-extras-diarias', [HoraExtraDiariaController::class, 'index']);
    Route::get('horas-extras-diarias/empleado', [HoraExtraDiariaController::class, 'porEmpleado']);
    Route::post('horas-extras-diarias/confirmar', [HoraExtraDiariaController::class, 'confirmar']);

    // ══════════════════════════════════════════════════════════
    // MÓDULO: PLANILLAS DE HORAS EXTRAS (crear / editar)
    // ══════════════════════════════════════════════════════════
    Route::get('planillas-hs-extras/empleados', [HoraExtraPlanillaController::class, 'empleados']);
    Route::post('planillas-hs-extras/generar',  [HoraExtraPlanillaController::class, 'generar']);
    Route::post('planillas-hs-extras/crear',    [HoraExtraPlanillaController::class, 'crear']);
    Route::post('planillas-hs-extras/guardar',  [HoraExtraPlanillaController::class, 'guardarPlanilla']);
    Route::get('planillas-hs-extras/{nro}',     [HoraExtraPlanillaController::class, 'cargarPlanilla'])->whereNumber('nro');

    // ══════════════════════════════════════════════════════════
    // MÓDULO: ALMUERZOS DEL PERSONAL (editar)
    // ══════════════════════════════════════════════════════════
    Route::get('almuerzos',            [AlmuerzoController::class, 'index']);
    Route::post('almuerzos/confirmar', [AlmuerzoController::class, 'confirmar']);
    Route::get('almuerzos/listado',    [AlmuerzoController::class, 'listado']);

    // ══════════════════════════════════════════════════════════
    // MÓDULO: VALES DE EFECTIVO (adelantos)
    // ══════════════════════════════════════════════════════════
    Route::post('vales', [ValeController::class, 'crear']);
    Route::get('vales/lista',        [ValeController::class, 'lista']);
    Route::get('vales/borrados',     [ValeController::class, 'listaBorrados']);
    Route::post('vales/borrar',      [ValeController::class, 'borrar']);
    Route::post('vales/cerrar',      [ValeController::class, 'cerrar']);
    Route::post('vales/tesoreria',   [ValeController::class, 'tesoreria']);
    Route::get('vales/pendientes',   [ValeController::class, 'pendientes']);
    Route::get('vales/fondo-fijo',   [ValeController::class, 'fondoFijo']);
    Route::get('vales/{nro}/impresion', [ValeController::class, 'impresion'])->whereNumber('nro');

    // ══════════════════════════════════════════════════════════
    // MÓDULO: TIPOS DE CONTRATISTA (ABM)
    // ══════════════════════════════════════════════════════════
    Route::get   ('contratista-tipos',        [ContratistaTipoController::class, 'index']);
    Route::post  ('contratista-tipos',        [ContratistaTipoController::class, 'store']);
    Route::put   ('contratista-tipos/{cod}',  [ContratistaTipoController::class, 'update']);
    Route::delete('contratista-tipos/{cod}',  [ContratistaTipoController::class, 'destroy']);

    // ══════════════════════════════════════════════════════════
    // MÓDULO: EXIGENCIAS LEGALES A CONTRATADOS (ABM)
    // ══════════════════════════════════════════════════════════
    Route::get   ('exigencias',        [ExigenciaController::class, 'index']);
    Route::post  ('exigencias',        [ExigenciaController::class, 'store']);
    Route::put   ('exigencias/{cod}',  [ExigenciaController::class, 'update']);
    Route::delete('exigencias/{cod}',  [ExigenciaController::class, 'destroy']);

    // ══════════════════════════════════════════════════════════
    // MÓDULO: CONTRATISTAS EXTERNOS (ABM 3 solapas)
    // ══════════════════════════════════════════════════════════
    Route::get   ('contratistas-externos',                 [ContratistaExternoController::class, 'index']);
    Route::post  ('contratistas-externos',                 [ContratistaExternoController::class, 'store']);
    Route::get   ('contratistas-externos/{cod}',           [ContratistaExternoController::class, 'show'])->whereNumber('cod');
    Route::put   ('contratistas-externos/{cod}',           [ContratistaExternoController::class, 'update'])->whereNumber('cod');
    Route::delete('contratistas-externos/{cod}',           [ContratistaExternoController::class, 'destroy'])->whereNumber('cod');
    Route::get   ('contratistas-externos/{cod}/exigencias', [ContratistaExternoController::class, 'exigencias'])->whereNumber('cod');
    Route::post  ('contratistas-externos/{cod}/exigencias', [ContratistaExternoController::class, 'guardarExigencias'])->whereNumber('cod');
    Route::get   ('contratistas-externos/{cod}/empleados',  [ContratistaExternoController::class, 'empleados'])->whereNumber('cod');
    Route::post  ('contratistas-externos/{cod}/empleados',  [ContratistaExternoController::class, 'guardarEmpleados'])->whereNumber('cod');
    Route::post  ('contratistas-externos/{cod}/empleados/importar', [ContratistaExternoController::class, 'importarEmpleados'])->whereNumber('cod');
    Route::get   ('contratistas-externos/{cod}/obligaciones', [ContratistaObligacionController::class, 'index'])->whereNumber('cod');
    Route::post  ('contratistas-externos/{cod}/obligaciones', [ContratistaObligacionController::class, 'guardar'])->whereNumber('cod');

    // ══════════════════════════════════════════════════════════
    // MÓDULO: LISTADO DE ACCESOS A LA EMPRESA
    // ══════════════════════════════════════════════════════════
    Route::get('accesos-empresa', [AccesoEmpresaController::class, 'index']);
    Route::get('contratistas-faltas', [ContratistaFaltaController::class, 'index']);

    // ══════════════════════════════════════════════════════════
    // MÓDULO: OBRAS de contratistas
    // ══════════════════════════════════════════════════════════
    Route::get ('obras', [ObraController::class, 'index']);
    Route::post('obras', [ObraController::class, 'store']);
    Route::get ('obras/listado', [ObraController::class, 'listado']);
    Route::get ('obras/accesos', [ObraController::class, 'accesos']);
    Route::get ('obras/{cod}', [ObraController::class, 'show'])->whereNumber('cod');
    Route::put ('obras/{cod}', [ObraController::class, 'update'])->whereNumber('cod');

    // ══════════════════════════════════════════════════════════
    // MÓDULO: OBRAS SOCIALES (ABM 2 solapas)
    // ══════════════════════════════════════════════════════════
    Route::get   ('obras-sociales',                       [ObraSocialController::class, 'index']);
    Route::post  ('obras-sociales',                       [ObraSocialController::class, 'store']);
    Route::put   ('obras-sociales/{cod}',                 [ObraSocialController::class, 'update'])->whereNumber('cod');
    Route::delete('obras-sociales/{cod}',                 [ObraSocialController::class, 'destroy'])->whereNumber('cod');
    Route::get   ('obras-sociales/{cod}/comprobantes',    [ObraSocialController::class, 'comprobantes'])->whereNumber('cod');
    Route::post  ('obras-sociales/{cod}/comprobantes',    [ObraSocialController::class, 'guardarComprobante'])->whereNumber('cod');
    Route::delete('obras-sociales/{cod}/comprobantes',    [ObraSocialController::class, 'eliminarComprobantes'])->whereNumber('cod');
    Route::post  ('obras-sociales/importar-aportes',      [ObraSocialController::class, 'importarAportes']);
    Route::get   ('obras-sociales/aportes',               [ObraSocialController::class, 'aportesIndex']);
    Route::post  ('obras-sociales/aportes',               [ObraSocialController::class, 'aportesGuardar']);

    // ══════════════════════════════════════════════════════════
    // MÓDULO: PLANILLA LIBRO DE VIAJANTES (comisiones, lee de GESTIÓN)
    // ══════════════════════════════════════════════════════════
    // Planilla Control de Sueldos
    Route::get ('control-sueldos/generar',   [ControlSueldoController::class, 'generar']);
    Route::get ('control-sueldos/guardadas', [ControlSueldoController::class, 'guardadas']);
    Route::get ('control-sueldos/{nro}',     [ControlSueldoController::class, 'show'])->whereNumber('nro');
    Route::post('control-sueldos',           [ControlSueldoController::class, 'grabar']);

    // Agenda de Teléfonos
    Route::get ('telefonos', [AgendaTelefonoController::class, 'index']);
    Route::post('telefonos', [AgendaTelefonoController::class, 'guardar']);

    // Liquidaciones (consultar / borrar) + tipos
    Route::get   ('liquidaciones/tipos',     [LiquidacionController::class, 'tipos']);
    Route::get   ('liquidaciones/conceptos', [LiquidacionController::class, 'conceptos']);
    Route::get   ('sueldos-listados',        [SueldosListadosController::class, 'index']);
    Route::get   ('mejores-sueldos/bruto',    [MejoresSueldosController::class, 'mejorBruto']);
    Route::get   ('mejores-sueldos/sueldo',   [MejoresSueldosController::class, 'mejorSueldo']);
    Route::get   ('mejores-sueldos/promedio', [MejoresSueldosController::class, 'promedio']);
    Route::get   ('resumen-liquidaciones',    [ResumenLiquidacionController::class, 'index']);
    Route::get   ('estadisticas',             [\App\Http\Controllers\EstadisticaController::class, 'index']);
    Route::get   ('estadisticas/composicion', [\App\Http\Controllers\EstadisticaController::class, 'composicionEndpoint']);
    Route::get   ('estadisticas/composicion-empleados', [\App\Http\Controllers\EstadisticaController::class, 'composicionEmpleados']);
    Route::get   ('estadisticas/dotacion-movimientos', [\App\Http\Controllers\EstadisticaController::class, 'dotacionMovimientos']);
    Route::get   ('estadisticas/horas-extras-empleados', [\App\Http\Controllers\EstadisticaController::class, 'horasExtrasEmpleados']);
    Route::get   ('estadisticas/ausentismo-empleados', [\App\Http\Controllers\EstadisticaController::class, 'ausentismoEmpleados']);
    Route::get   ('estadisticas/liq-finales-empleados', [\App\Http\Controllers\EstadisticaController::class, 'liqFinalesEmpleados']);
    Route::get   ('estadisticas/faltas-empleado-detalle', [\App\Http\Controllers\EstadisticaController::class, 'faltasEmpleadoDetalle']);
    Route::get   ('estadisticas/tipos-sueldo', [\App\Http\Controllers\EstadisticaController::class, 'tiposSueldo']);
    Route::get   ('estadisticas/detalle-sueldos', [\App\Http\Controllers\EstadisticaController::class, 'detalleSueldos']);
    Route::get   ('estadisticas/puntualidad', [\App\Http\Controllers\EstadisticaController::class, 'puntualidad']);
    Route::get   ('estadisticas/puntualidad-empleado', [\App\Http\Controllers\EstadisticaController::class, 'puntualidadEmpleado']);

    // ── Tablero Gerencial · Stock/Logística (WMS, base LOGIST_UNIVERSAL) ──
    Route::get   ('tablero/wms/empresas', [\App\Http\Controllers\TableroController::class, 'empresas']);
    Route::get   ('tablero/wms/antiguedad-detalle', [\App\Http\Controllers\TableroController::class, 'antiguedadDetalle']);
    Route::get   ('tablero/wms',          [\App\Http\Controllers\TableroController::class, 'wms']);
    Route::get   ('liquidaciones/consultar', [LiquidacionController::class, 'consultar']);
    Route::delete('liquidaciones',           [LiquidacionController::class, 'borrar']);
    Route::get   ('comparativa-liquidaciones', [ComparativaLiquidacionController::class, 'index']);
    Route::get   ('sueldos-pagos',             [SueldosPagosController::class, 'index']);
    Route::post  ('sueldos-importar',          [SueldosImportarController::class, 'importar']);
    Route::get   ('multas-listados',           [MultasController::class, 'index']);
    Route::get   ('bco-santa-fe/consultar',    [BancoSantaFeController::class, 'consultar']);
    Route::post  ('bco-santa-fe/generar',      [BancoSantaFeController::class, 'generar']);
    Route::get   ('bco-santa-fe/lotes',         [BancoSantaFeConsultarController::class, 'lotes']);
    Route::get   ('bco-santa-fe/beneficiarios', [BancoSantaFeConsultarController::class, 'beneficiarios']);
    Route::post  ('bco-santa-fe/generar-movimiento', [BancoSantaFeConsultarController::class, 'generarMovimiento']);
    Route::post  ('bco-santa-fe/eliminar',      [BancoSantaFeConsultarController::class, 'eliminar']);
    Route::post  ('bco-santa-fe/generar-nuevo-txt', [BancoSantaFeConsultarController::class, 'generarNuevoTxt']);
    Route::get   ('bco-santander-rio/consultar', [BancoSantanderRioController::class, 'consultar']);
    Route::post  ('bco-santander-rio/generar',   [BancoSantanderRioController::class, 'generar']);
    Route::get   ('bco-santander-rio/lotes',         [BancoSantanderRioConsultarController::class, 'lotes']);
    Route::get   ('bco-santander-rio/beneficiarios', [BancoSantanderRioConsultarController::class, 'beneficiarios']);
    Route::post  ('bco-santander-rio/generar-movimiento', [BancoSantanderRioConsultarController::class, 'generarMovimiento']);
    Route::post  ('bco-santander-rio/eliminar',      [BancoSantanderRioConsultarController::class, 'eliminar']);
    Route::post  ('bco-santander-rio/generar-nuevo', [BancoSantanderRioConsultarController::class, 'generarNuevo']);
    Route::get   ('bco-frances/consultar',     [BancoFrancesController::class, 'consultar']);
    Route::post  ('bco-frances/generar',       [BancoFrancesController::class, 'generar']);
    Route::get   ('bco-frances/lotes',         [BancoFrancesConsultarController::class, 'lotes']);
    Route::get   ('bco-frances/beneficiarios', [BancoFrancesConsultarController::class, 'beneficiarios']);
    Route::post  ('bco-frances/generar-movimiento', [BancoFrancesConsultarController::class, 'generarMovimiento']);
    Route::post  ('bco-frances/eliminar',      [BancoFrancesConsultarController::class, 'eliminar']);
    Route::post  ('bco-frances/generar-nuevo', [BancoFrancesConsultarController::class, 'generarNuevo']);
    Route::get   ('bco-nacion/consultar',      [BancoNacionController::class, 'consultar']);
    Route::post  ('bco-nacion/generar',        [BancoNacionController::class, 'generar']);
    Route::get   ('bco-nacion/lotes',          [BancoNacionConsultarController::class, 'lotes']);
    Route::get   ('bco-nacion/beneficiarios',  [BancoNacionConsultarController::class, 'beneficiarios']);
    Route::post  ('bco-nacion/generar-movimiento', [BancoNacionConsultarController::class, 'generarMovimiento']);
    Route::post  ('bco-nacion/eliminar',       [BancoNacionConsultarController::class, 'eliminar']);
    Route::post  ('bco-nacion/generar-nuevo',  [BancoNacionConsultarController::class, 'generarNuevo']);
    Route::get   ('bco-varios/consultar',      [BancoVariosController::class, 'consultar']);
    Route::post  ('bco-varios/generar',        [BancoVariosController::class, 'generar']);
    Route::get   ('bco-varios/lotes',          [BancoVariosConsultarController::class, 'lotes']);
    Route::get   ('bco-varios/beneficiarios',  [BancoVariosConsultarController::class, 'beneficiarios']);
    Route::post  ('bco-varios/generar-movimiento', [BancoVariosConsultarController::class, 'generarMovimiento']);
    Route::post  ('bco-varios/eliminar',       [BancoVariosConsultarController::class, 'eliminar']);
    Route::post  ('bco-varios/generar-nuevo',  [BancoVariosConsultarController::class, 'generarNuevo']);
    Route::get   ('vacaciones/empleado',       [VacacionesController::class, 'empleado']);
    Route::post  ('vacaciones',                [VacacionesController::class, 'agregar']);
    Route::get   ('vacaciones/acciones',       [VacacionesConsultaController::class, 'acciones']);
    Route::post  ('vacaciones/modificar',      [VacacionesConsultaController::class, 'modificar']);
    Route::post  ('vacaciones/eliminar',       [VacacionesConsultaController::class, 'eliminar']);
    Route::get   ('vacaciones/programadas',    [VacacionesConsultaController::class, 'programadas']);
    Route::get   ('vacaciones/planilla',       [VacacionesConsultaController::class, 'planilla']);
    Route::get   ('vacaciones/informe',        [VacacionesConsultaController::class, 'informe']);
    Route::get   ('vacaciones/pendientes',     [VacacionesConsultaController::class, 'pendientes']);
    Route::get   ('vacaciones/definicion',     [VacacionesConsultaController::class, 'definicionList']);
    Route::post  ('vacaciones/definicion',     [VacacionesConsultaController::class, 'definicionAgregar']);
    Route::post  ('vacaciones/definicion/eliminar', [VacacionesConsultaController::class, 'definicionEliminar']);
    Route::get   ('reloj/capturas',            [RelojCapturasController::class, 'capturas']);
    Route::get   ('reloj/horas-trabajadas/empleados', [HorasTrabajadasController::class, 'empleados']);
    Route::post  ('reloj/horas-trabajadas/generar',   [HorasTrabajadasController::class, 'generar']);
    Route::post  ('reloj/parte-diario',        [HorasTrabajadasController::class, 'parteDiario']);
    Route::post  ('reloj/llegadas-tarde',      [HorasTrabajadasController::class, 'llegadasTarde']);
    Route::post  ('reloj/secretaria-trabajo',  [HorasTrabajadasController::class, 'secretaria']);
    Route::get   ('reloj/ajustes/dia',         [RelojAjustesController::class, 'dia']);
    Route::post  ('reloj/ajustes',             [RelojAjustesController::class, 'confirmar']);
    Route::post  ('reloj/ajustes/eliminar',    [RelojAjustesController::class, 'eliminar']);
    Route::get   ('reloj/ajustes/lista',       [RelojAjustesController::class, 'lista']);
    Route::post  ('reloj/ajustes/borrar-lote', [RelojAjustesController::class, 'borrarLote']);
    Route::get   ('reloj/periodos/recuperar',  [RelojPeriodosController::class, 'recuperar']);
    Route::post  ('reloj/periodos/confirmar',  [RelojPeriodosController::class, 'confirmar']);
    Route::get   ('reloj/faltas/licencias',    [RelojFaltasController::class, 'licencias']);
    Route::post  ('reloj/faltas',              [RelojFaltasController::class, 'confirmar']);
    Route::put   ('reloj/faltas/{unico}',      [RelojFaltasController::class, 'actualizar'])->whereNumber('unico');
    Route::get   ('reloj/faltas/edicion',      [RelojFaltasController::class, 'edicion']);
    Route::post  ('reloj/faltas/edicion/eliminar', [RelojFaltasController::class, 'edicionEliminar']);
    Route::get   ('reloj/faltas/listados',     [RelojFaltasController::class, 'listados']);
    // Documentación de la falta (biblioteca digital, DOC_TIP='L')
    Route::get   ('reloj/faltas/{unico}/documentos', [RelojFaltasController::class, 'documentos'])->whereNumber('unico');
    Route::post  ('reloj/faltas/{unico}/documento',  [RelojFaltasController::class, 'agregarDocumento'])->whereNumber('unico');
    Route::get   ('reloj/faltas/documento/{id}/ver', [RelojFaltasController::class, 'verDocumento'])->whereNumber('id');
    Route::delete('reloj/faltas/documento/{id}',     [RelojFaltasController::class, 'eliminarDocumento'])->whereNumber('id');
    Route::get   ('reloj/turnos/grupos',       [RelojTurnosController::class, 'grupos']);
    Route::get   ('reloj/turnos/personal',     [RelojTurnosController::class, 'personal']);
    Route::post  ('reloj/turnos/confirmar',    [RelojTurnosController::class, 'confirmar']);
    Route::get   ('reloj/envios',              [RelojEnviosController::class, 'index']);
    Route::post  ('reloj/envios',              [RelojEnviosController::class, 'store']);
    Route::get   ('reloj/envios/{cod}',        [RelojEnviosController::class, 'show'])->whereNumber('cod');
    Route::put   ('reloj/envios/{cod}',        [RelojEnviosController::class, 'update'])->whereNumber('cod');
    Route::delete('reloj/envios/{cod}',        [RelojEnviosController::class, 'destroy'])->whereNumber('cod');
    Route::get   ('reloj/envios/{cod}/asociados', [RelojEnviosController::class, 'asociados'])->whereNumber('cod');
    Route::post  ('reloj/envios/{cod}/asociados', [RelojEnviosController::class, 'guardarAsociados'])->whereNumber('cod');
    Route::get   ('reloj/ubicaciones',         [RelojUbicacionController::class, 'index']);
    Route::post  ('reloj/ubicaciones',         [RelojUbicacionController::class, 'store']);
    Route::put   ('reloj/ubicaciones/{cod}',   [RelojUbicacionController::class, 'update'])->whereNumber('cod');
    Route::delete('reloj/ubicaciones/{cod}',   [RelojUbicacionController::class, 'destroy'])->whereNumber('cod');
    Route::get   ('reloj/grupos',              [RelojGruposController::class, 'index']);
    Route::post  ('reloj/grupos',              [RelojGruposController::class, 'store']);
    Route::get   ('reloj/grupos/{cod}',        [RelojGruposController::class, 'show'])->whereNumber('cod');
    Route::put   ('reloj/grupos/{cod}',        [RelojGruposController::class, 'update'])->whereNumber('cod');
    Route::delete('reloj/grupos/{cod}',        [RelojGruposController::class, 'destroy'])->whereNumber('cod');
    Route::get   ('licencias',                 [LicenciaController::class, 'index']);
    Route::post  ('licencias',                 [LicenciaController::class, 'store']);
    Route::put   ('licencias/{cod}',           [LicenciaController::class, 'update'])->whereNumber('cod');
    Route::delete('licencias/{cod}',           [LicenciaController::class, 'destroy'])->whereNumber('cod');
    Route::get   ('feriados',                  [FeriadoController::class, 'index']);
    Route::get   ('feriados/rango',            [FeriadoController::class, 'rango']);
    Route::post  ('feriados',                  [FeriadoController::class, 'guardar']);
    Route::post  ('feriados/eliminar',         [FeriadoController::class, 'eliminar']);
    Route::get   ('sueldos-tipos',             [SueldoTipoController::class, 'index']);
    Route::post  ('sueldos-tipos',             [SueldoTipoController::class, 'store']);
    Route::put   ('sueldos-tipos/{cod}',       [SueldoTipoController::class, 'update'])->whereNumber('cod');
    Route::delete('sueldos-tipos/{cod}',       [SueldoTipoController::class, 'destroy'])->whereNumber('cod');
    Route::get   ('sueldos-conceptos',         [SueldoConceptoController::class, 'index']);
    Route::post  ('sueldos-conceptos',         [SueldoConceptoController::class, 'store']);
    Route::put   ('sueldos-conceptos/{cod}',   [SueldoConceptoController::class, 'update'])->whereNumber('cod');
    Route::delete('sueldos-conceptos/{cod}',   [SueldoConceptoController::class, 'destroy'])->whereNumber('cod');
    Route::post  ('sueldos-netos/importar', [SueldoNetoController::class, 'importar']);
    Route::get   ('sueldos-netos/exportar', [SueldoNetoController::class, 'exportar']);

    Route::get('viajantes/vendedores', [ViajanteController::class, 'vendedores']);
    Route::get('viajantes/planilla', [ViajanteController::class, 'planilla']);

    // ══════════════════════════════════════════════════════════
    // MÓDULO: EMPLEADOS
    // ══════════════════════════════════════════════════════════
    Route::prefix('empleados')->group(function () {
        Route::get('opciones',                  [EmpleadoController::class, 'opciones']);
        Route::get('buscar',                    [EmpleadoController::class, 'buscar']);
        Route::get('listado',                   [EmpleadoController::class, 'listado']);
        Route::get('centros-costo-catalogo',    [EmpleadoController::class, 'centrosCostoCatalogo']);
        Route::get('exportar',                  [EmpleadoController::class, 'exportarDatos']);
        Route::post('importar-convenio',        [EmpleadoController::class, 'importarConvenio']);
        Route::get('exportar-convenio',         [EmpleadoController::class, 'exportarConvenio']);
        Route::post('obrasocial/importar',      [EmpleadoController::class, 'obraSocialImportar']);
        Route::get('obrasocial/historial',      [EmpleadoController::class, 'obrasSocialesHistorial']);
        Route::get('obrasocial/informe',        [EmpleadoController::class, 'obrasSocialesInforme']);
        Route::get('personal-a-cargo-informe',  [EmpleadoController::class, 'personalACargoInforme']);
        // Reserva de código PAR para altas concurrentes (antes de las rutas {codigo})
        Route::post('reservar-codigo',           [EmpleadoController::class, 'reservarCodigo']);
        Route::delete('reservar-codigo/{codigo}',[EmpleadoController::class, 'liberarCodigo']);
        Route::get('/',                         [EmpleadoController::class, 'index']);
        Route::post('/',                        [EmpleadoController::class, 'store']);
        Route::get('{codigo}',                  [EmpleadoController::class, 'show']);
        Route::put('{codigo}',                  [EmpleadoController::class, 'update']);
        Route::patch('{codigo}/estado',         [EmpleadoController::class, 'cambiarEstado']);
        // ── Pestañas de detalle ────────────────────────────────
        Route::get   ('{codigo}/foto',            [EmpleadoController::class, 'foto']);
        Route::post  ('{codigo}/foto',            [EmpleadoController::class, 'fotoStore']);
        Route::delete('{codigo}/foto',            [EmpleadoController::class, 'fotoDestroy']);
        Route::get('{codigo}/hijos',            [EmpleadoController::class, 'hijos']);
        Route::put('{codigo}/hijos',            [EmpleadoController::class, 'hijosGuardar']);
        Route::get('{codigo}/puestos',          [EmpleadoController::class, 'puestos']);
        Route::delete('{codigo}/calificacion',  [EmpleadoController::class, 'calificacionEliminar']);
        Route::get('{codigo}/capacitaciones',   [EmpleadoController::class, 'capacitaciones']);
        // Documentos digitales de una capacitación (grilla detail master-detail)
        Route::get('{codigo}/capacitaciones/{capCod}/documentos', [EmpleadoController::class, 'capacitacionDocumentos']);
        Route::get('{codigo}/examenes',         [EmpleadoController::class, 'examenes']);
        // Documentos digitales de un examen (grilla detail master-detail)
        Route::get('{codigo}/examenes/{examId}/documentos', [EmpleadoController::class, 'examenDocumentos']);
        Route::get('{codigo}/examenes/documentos/{id}/ver',  [EmpleadoController::class, 'examenDocumentoVisualizar'])->whereNumber('id');
        Route::get('{codigo}/legajo',           [EmpleadoController::class, 'legajoHistorico']);
        Route::get('{codigo}/historial',        [EmpleadoController::class, 'historial']);
        Route::delete('{codigo}/historial',     [EmpleadoController::class, 'historialEliminar']);
        Route::get('{codigo}/documentos',       [EmpleadoController::class, 'documentosEmp']);
        Route::post('{codigo}/documentos',      [EmpleadoController::class, 'documentoAgregar']);
        Route::put('{codigo}/documentos',       [EmpleadoController::class, 'documentosActualizar']);
        Route::get('{codigo}/documentos/{id}/ver', [EmpleadoController::class, 'documentoVisualizar']);
        Route::delete('{codigo}/documentos/{id}', [EmpleadoController::class, 'documentoEliminar']);
        Route::get('{codigo}/centros-costo',            [EmpleadoController::class, 'centrosCosto']);
        Route::get('{codigo}/centros-costo/periodos',   [EmpleadoController::class, 'centrosCostoPeriodos']);
        Route::get('{codigo}/centros-costo/detalle/{mya}', [EmpleadoController::class, 'centrosCostoDetalle']);
        Route::put('{codigo}/centros-costo',            [EmpleadoController::class, 'centrosCostoGuardar']);
        Route::get('{codigo}/subordinados',     [EmpleadoController::class, 'subordinados']);
        Route::put('{codigo}/subordinados',     [EmpleadoController::class, 'subordinadosGuardar']);
        Route::get('{codigo}/faltas',           [EmpleadoController::class, 'faltas']);
        Route::get('{codigo}/faltas/{faltaId}/documentos', [EmpleadoController::class, 'faltaDocumentos']);
        Route::get('{codigo}/faltas/documentos/{id}/ver',  [EmpleadoController::class, 'faltaDocumentoVisualizar']);
        Route::get('{codigo}/celulares',        [EmpleadoController::class, 'celulares']);
        Route::get('{codigo}/tarjetas',         [EmpleadoController::class, 'tarjetas']);
        Route::get('{codigo}/ropa',             [EmpleadoController::class, 'ropa']);
        Route::get('{codigo}/constancia-ropa',  [EmpleadoController::class, 'constanciaRopa']);
        Route::get('{codigo}/epp',              [EmpleadoController::class, 'eppAsignada']);
        Route::get('{codigo}/obrasocial',       [EmpleadoController::class, 'obraSocialHistorial']);
    });

    // ── Usuarios logueados en el sistema (en vivo) ────────────
    Route::get('usuarios-activos', [UsuariosActivosController::class, 'index']);

    // ── Adelantos / Anticipos al personal ─────────────────────
    Route::prefix('adelantos')->group(function () {
        Route::get   ('empleado/{cod}',       [AdelantosController::class, 'empleado'])->whereNumber('cod');
        Route::get   ('empleado/{cod}/lista', [AdelantosController::class, 'lista'])->whereNumber('cod');
        Route::get   ('grilla',               [AdelantosController::class, 'grilla']);
        Route::get   ('listado',              [AdelantosController::class, 'listado']);
        Route::post  ('',                     [AdelantosController::class, 'crear']);
        Route::get   ('{nro}/comprobante',    [AdelantosController::class, 'comprobanteEndpoint'])->whereNumber('nro');
        Route::delete('{nro}',                [AdelantosController::class, 'borrar'])->whereNumber('nro');
    });

    // ── Puestos de Trabajo (ABM 7 solapas) ────────────────────
    Route::prefix('puestos')->group(function () {
        Route::get   ('catalogos',          [PuestoController::class, 'catalogos']);
        Route::get   ('informe',            [PuestoController::class, 'informe']);
        Route::get   ('empleados-por-puesto', [PuestoController::class, 'empleadosPorPuesto']);
        Route::get   ('hojas-evaluacion',   [PuestoController::class, 'hojasEvaluacion']);
        Route::get   ('',                   [PuestoController::class, 'index']);
        Route::post  ('',                   [PuestoController::class, 'store']);
        Route::get   ('{cod}',              [PuestoController::class, 'show']);
        Route::put   ('{cod}',              [PuestoController::class, 'update']);
        Route::delete('{cod}',              [PuestoController::class, 'destroy']);
        Route::put   ('{cod}/tareas',       [PuestoController::class, 'guardarTareas']);
        Route::put   ('{cod}/educacion',    [PuestoController::class, 'guardarEducacion']);
        Route::put   ('{cod}/cualidades',   [PuestoController::class, 'guardarCualidades']);
        Route::put   ('{cod}/competencias', [PuestoController::class, 'guardarCompetencias']);
        Route::put   ('{cod}/elementos',    [PuestoController::class, 'guardarElementos']);
        Route::put   ('{cod}/revisiones',   [PuestoController::class, 'guardarRevisiones']);
    });

    // ── Calificación del empleado ─────────────────────────────
    Route::prefix('calificaciones')->group(function () {
        Route::get ('empleado/{cod}',   [CalificacionController::class, 'empleado'])->whereNumber('cod');
        Route::get ('hoja-puestos/{cod}', [CalificacionController::class, 'hojaPuestos'])->whereNumber('cod');
        Route::get ('consulta',         [CalificacionController::class, 'consulta']);
        Route::get ('persona/{cod}',    [CalificacionController::class, 'persona'])->whereNumber('cod');
        Route::get ('puesto/{cod}',     [CalificacionController::class, 'puesto']);
        Route::get ('temas',            [CalificacionController::class, 'temas']);
        Route::get ('pendientes/{cod}', [CalificacionController::class, 'pendientes'])->whereNumber('cod');
        Route::post('necesidad',        [CalificacionController::class, 'asignarNecesidad']);
        Route::post('',                 [CalificacionController::class, 'guardar']);
    });

    // ── Tareas (catálogo + asignación a puestos) ──────────────
    Route::prefix('tareas')->group(function () {
        Route::get   ('puestos',        [TareaController::class, 'puestos']);
        Route::get   ('',               [TareaController::class, 'index']);
        Route::post  ('',               [TareaController::class, 'store']);
        Route::get   ('{cod}',          [TareaController::class, 'show'])->whereNumber('cod');
        Route::put   ('{cod}',          [TareaController::class, 'update'])->whereNumber('cod');
        Route::delete('{cod}',          [TareaController::class, 'destroy'])->whereNumber('cod');
        Route::post  ('{cod}/asignar',  [TareaController::class, 'asignar'])->whereNumber('cod');
        Route::post  ('{cod}/baja',     [TareaController::class, 'darBaja'])->whereNumber('cod');
    });

    // ── Asignar Puesto de Trabajo (puestoempleado, por CUIL) ──
    Route::prefix('asignar-puesto')->group(function () {
        Route::get ('empleado/{cod}', [AsignarPuestoController::class, 'empleado'])->whereNumber('cod');
        Route::post('',               [AsignarPuestoController::class, 'asignar']);
        Route::post('baja',           [AsignarPuestoController::class, 'darBaja']);
        Route::post('estado',         [AsignarPuestoController::class, 'estado']);
    });

    // ── Tareas por Puesto (ABM plano sobre tareapue) ──────────
    Route::prefix('tareas-puesto')->group(function () {
        Route::get ('',          [TareaPuestoController::class, 'index']);
        Route::post('',          [TareaPuestoController::class, 'store']);
        Route::put ('',          [TareaPuestoController::class, 'update']);
        Route::post('eliminar',  [TareaPuestoController::class, 'eliminar']);
    });

    // ── Parámetros del sistema ────────────────────────────────
    Route::prefix('parametros')->group(function () {
        Route::get   ('almuerzos',                 [ParametrosController::class, 'almuerzos']);
        Route::post  ('almuerzos',                 [ParametrosController::class, 'guardarAlmuerzo']);
        Route::delete('almuerzos/{anio}/{mes}',    [ParametrosController::class, 'eliminarAlmuerzo'])->whereNumber('anio')->whereNumber('mes');
        Route::get   ('bases',                     [ParametrosController::class, 'bases']);
        Route::post  ('bases',                     [ParametrosController::class, 'guardarBase']);
        Route::get   ('bases/historial',           [ParametrosController::class, 'basesHistorial']);
        Route::get   ('reloj',                     [ParametrosController::class, 'reloj']);
        Route::post  ('reloj',                     [ParametrosController::class, 'guardarReloj']);
        Route::get   ('emails',                    [ParametrosController::class, 'emails']);
        Route::post  ('emails',                    [ParametrosController::class, 'agregarEmail']);
        Route::post  ('emails/eliminar',           [ParametrosController::class, 'eliminarEmails']);
    });

    // ══════════════════════════════════════════════════════════
    // MÓDULOS DEL SISTEMA — Se irán agregando aquí
    // ══════════════════════════════════════════════════════════
    //
    // Ejemplo estructura futura:
    //   Route::apiResource('empleados',    EmpleadosController::class);
    //   Route::apiResource('vacaciones',   VacacionesController::class);
    //   Route::apiResource('liquidaciones',LiquidacionesController::class);
    //   Route::apiResource('reloj',        RelojController::class);
    //   Route::apiResource('capacitacion', CapacitacionController::class);
    //   Route::apiResource('siniestros',   SiniestrosController::class);
    //
});
