<?php

/**
 * ============================================================
 * AdminController.php
 * ============================================================
 * Controlador de Administración — Sistema RRHH.NET
 *
 * Maneja el módulo de administración del sistema, actualmente:
 *   - Gestión de permisos de menú por usuario
 *   - Listado de usuarios del sistema
 *
 * Solo pueden acceder los usuarios con NIVEL = 1 (Administrador).
 * El middleware 'admin' se aplica en las rutas de api.php.
 *
 * Endpoints disponibles:
 * ──────────────────────
 *  GET  /api/admin/usuarios
 *       Lista todos los usuarios activos con su estado de permisos.
 *
 *  GET  /api/admin/usuarios/{codigo}/permisos
 *       Retorna las claves de permisos asignadas a un usuario.
 *       Array vacío = sin restricciones (ve todo el menú).
 *
 *  PUT  /api/admin/usuarios/{codigo}/permisos
 *       Reemplaza completamente los permisos de un usuario.
 *       Body: { permisos: string[] }
 *       Array vacío = quitar todas las restricciones.
 *
 * Lógica de permisos:
 * ────────────────────
 *  • NIVEL = 1  → Administrador total. Ignora usuario_permisos.
 *  • Sin filas   → Sin restricciones (ve todo). Estado: "libre"
 *  • Con filas   → Solo ve los ítems en su lista. Estado: "restringido"
 *
 * @package  App\Http\Controllers
 * @author   Sistema RRHH.NET
 * @version  1.0.0
 * @since    2026-06-08
 * @see      App\Models\UsuarioPermiso
 * ============================================================
 */

namespace App\Http\Controllers;

use App\Models\Usuario;
use App\Models\UsuarioPermiso;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    // ══════════════════════════════════════════════════════════
    // USUARIOS
    // ══════════════════════════════════════════════════════════

    // ══════════════════════════════════════════════════════════
    // NIVELES
    // ══════════════════════════════════════════════════════════

    /**
     * Retorna la lista de niveles de acceso disponibles.
     *
     * Se usa para poblar el selector de Nivel en el formulario de usuario.
     * Los valores vienen de la tabla 'niveles' (heredada del sistema FoxPro).
     *
     * @route  GET /api/admin/niveles
     * @auth   Requiere NIVEL = 1
     * @return JsonResponse  Array de { CODNIV, DESCRIBE }
     */
    public function listarNiveles(): JsonResponse
    {
        $niveles = \DB::table('niveles')
            ->orderBy('DESCRIBE')
            ->get(['CODNIV', 'DESCRIBE']);

        return response()->json($niveles);
    }

    // ══════════════════════════════════════════════════════════
    // USUARIOS — ABM (Alta, Baja, Modificación)
    // ══════════════════════════════════════════════════════════

    /**
     * Lista todos los usuarios del sistema con info de permisos.
     *
     * Retorna solo usuarios activos (ESTADO = 1), ordenados por nombre.
     * Incluye para cada uno:
     *   - Datos básicos: CODIGO, DATO1, NOMBRE, NIVEL
     *   - es_admin: true si NIVEL = 1 (no se pueden configurar sus permisos)
     *   - tiene_restricciones: true si tiene filas en usuario_permisos
     *   - cantidad_permisos: cuántos ítems tiene habilitados
     *
     * @route  GET /api/admin/usuarios
     * @auth   Requiere NIVEL = 1
     * @return JsonResponse  Array de usuarios
     */
    public function listarUsuarios(): JsonResponse
    {
        // Se retornan todos los usuarios (activos e inactivos).
        // El filtrado por estado se hace en el frontend.
        $usuarios = Usuario::orderBy('NOMBRE')
            ->get(['CODIGO', 'DATO1', 'NOMBRE', 'NIVEL', 'ESTADO', 'email', 'ES_ADMIN']);

        $resultado = $usuarios->map(function ($u) {
            $cantidadPermisos = UsuarioPermiso::where('usuario_codigo', $u->CODIGO)->count();

            return [
                'CODIGO'              => $u->CODIGO,
                'DATO1'               => $u->DATO1,
                'NOMBRE'              => $u->NOMBRE,
                'NIVEL'               => $u->NIVEL,
                'ESTADO'              => (int) $u->ESTADO,   // 1 = activó su acceso, 0 = pendiente
                'email'               => $u->email,
                'ES_ADMIN'            => (int) $u->ES_ADMIN,
                'es_admin'            => (int)$u->ES_ADMIN === 1,
                'tiene_restricciones' => $cantidadPermisos > 0,
                'cantidad_permisos'   => $cantidadPermisos,
            ];
        });

        return response()->json($resultado);
    }

    /**
     * Crea un nuevo usuario en el sistema.
     *
     * El usuario se crea sin contraseña (primer_acceso = 1).
     * Al intentar ingresar por primera vez, el sistema lo guiará
     * a través del flujo de activación (verificar → email → código → contraseña).
     *
     * El campo CODIGO se genera automáticamente como MAX(CODIGO) + 1
     * ya que la tabla no usa auto_increment (herencia del sistema FoxPro).
     *
     * @route  POST /api/admin/usuarios
     * @auth   Requiere NIVEL = 1
     * @body   { NOMBRE, DATO1, email, NIVEL, EMPRESA? }
     * @return JsonResponse  Usuario creado con código HTTP 201
     */
    public function crearUsuario(Request $request): JsonResponse
    {
        $request->validate([
            'NOMBRE'  => 'required|string|max:50',
            'DATO1'   => 'required|string|max:20|unique:usuarios,DATO1',
            'email'   => 'required|email|max:255|unique:usuarios,email',
            'NIVEL'   => 'required|integer',
            'EMPRESA' => 'nullable|string|max:100',
            'DOMICILIO' => 'nullable|string|max:100',
            'TELEFONO'  => 'nullable|string|max:50',
            'DNI'       => 'nullable|string|max:20',
            'NOTAS'     => 'nullable|string|max:100',
            'RENOVAR'   => 'nullable|boolean',
            'CADACUANTO'=> 'nullable|integer|min:0',
            'ES_ADMIN'  => 'nullable|boolean',
        ], [
            'DATO1.unique'  => 'El nombre de usuario ya existe.',
            'email.unique'  => 'El email ya está registrado en el sistema.',
        ]);

        // Generar CODIGO manualmente (la tabla no tiene auto_increment)
        $maxCodigo = Usuario::max('CODIGO') ?? 0;
        $nuevoCodigo = $maxCodigo + 1;

        $usuario = Usuario::create([
            'CODIGO'        => $nuevoCodigo,
            'NOMBRE'        => strtoupper(trim($request->NOMBRE)),
            'DATO1'         => strtoupper(trim($request->DATO1)),
            'email'         => strtolower(trim($request->email)),
            'NIVEL'         => $request->NIVEL,
            'EMPRESA'       => $request->EMPRESA ? strtoupper(trim($request->EMPRESA)) : null,
            'DOMICILIO'     => strtoupper(trim((string) $request->DOMICILIO)),
            'TELEFONO'      => trim((string) $request->TELEFONO),
            'DNI'           => trim((string) $request->DNI),
            'NOTAS'         => trim((string) $request->NOTAS),
            'RENOVAR'       => (int) $request->boolean('RENOVAR'),
            'CADACUANTO'    => (int) ($request->CADACUANTO ?? 0),
            'CONTADOR'      => 0,
            'ES_ADMIN'      => (int) $request->boolean('ES_ADMIN'),
            'ESTADO'        => 1,         // activo
            'primer_acceso' => 1,         // debe activar cuenta al primer login
            'password'      => null,      // sin contraseña hasta que active
        ]);

        // Foto inicial de permisos: se copian los del rol (nivel) elegido a
        // usuario_permisos. Desde ahí el usuario se ajusta individualmente; el
        // login siempre lee usuario_permisos, no el nivel. Los admin ven todo.
        if (!(int) $usuario->ES_ADMIN) {
            $claves = \DB::table('nivel_permisos')
                ->where('nivel_codigo', (int) $usuario->NIVEL)
                ->pluck('permiso_clave')->all();
            if (!empty($claves)) {
                UsuarioPermiso::reemplazar((int) $usuario->CODIGO, $claves);
            }
        }

        return response()->json([
            'message' => "Usuario {$usuario->NOMBRE} creado correctamente. Podrá activar su cuenta al ingresar por primera vez.",
            'usuario' => $this->formatearUsuario($usuario),
        ], 201);
    }

    /**
     * Actualiza los datos de un usuario existente.
     *
     * No permite modificar la contraseña (hay un endpoint separado para eso).
     * No permite cambiar el DATO1 (login) si el usuario ya activó su cuenta,
     * salvo que el administrador lo confirme explícitamente.
     *
     * @route  PUT /api/admin/usuarios/{codigo}
     * @auth   Requiere NIVEL = 1
     * @body   { NOMBRE, DATO1, email, NIVEL, EMPRESA?, ESTADO? }
     * @param  int $codigo
     * @return JsonResponse
     */
    public function actualizarUsuario(Request $request, int $codigo): JsonResponse
    {
        $usuario = Usuario::where('CODIGO', $codigo)->firstOrFail();

        $request->validate([
            'NOMBRE'  => 'required|string|max:50',
            'DATO1'   => ['required', 'string', 'max:20', Rule::unique('usuarios', 'DATO1')->ignore($codigo, 'CODIGO')],
            'email'   => ['required', 'email', 'max:255', Rule::unique('usuarios', 'email')->ignore($codigo, 'CODIGO')],
            'NIVEL'   => 'required|integer',
            'EMPRESA' => 'nullable|string|max:100',
            'ESTADO'  => 'nullable|boolean',
            'DOMICILIO' => 'nullable|string|max:100',
            'TELEFONO'  => 'nullable|string|max:50',
            'DNI'       => 'nullable|string|max:20',
            'NOTAS'     => 'nullable|string|max:100',
            'RENOVAR'   => 'nullable|boolean',
            'CADACUANTO'=> 'nullable|integer|min:0',
            'ES_ADMIN'  => 'nullable|boolean',
        ], [
            'DATO1.unique' => 'El nombre de usuario ya existe en otro usuario.',
            'email.unique' => 'El email ya está registrado en otro usuario.',
        ]);

        $usuario->update([
            'NOMBRE'  => strtoupper(trim($request->NOMBRE)),
            'DATO1'   => strtoupper(trim($request->DATO1)),
            'email'   => strtolower(trim($request->email)),
            'NIVEL'   => $request->NIVEL,
            'EMPRESA' => $request->EMPRESA ? strtoupper(trim($request->EMPRESA)) : null,
            'ESTADO'  => $request->has('ESTADO') ? (int)$request->ESTADO : $usuario->ESTADO,
            'DOMICILIO'  => strtoupper(trim((string) $request->DOMICILIO)),
            'TELEFONO'   => trim((string) $request->TELEFONO),
            'DNI'        => trim((string) $request->DNI),
            'NOTAS'      => trim((string) $request->NOTAS),
            'RENOVAR'    => (int) $request->boolean('RENOVAR'),
            'CADACUANTO' => (int) ($request->CADACUANTO ?? 0),
            'ES_ADMIN'   => (int) $request->boolean('ES_ADMIN'),
        ]);

        $usuario->refresh();

        return response()->json([
            'message' => "Usuario {$usuario->NOMBRE} actualizado correctamente.",
            'usuario' => $this->formatearUsuario($usuario),
        ]);
    }

    /**
     * Activa o desactiva un usuario (toggle de ESTADO).
     *
     * No se puede desactivar al propio administrador autenticado.
     *
     * @route  PATCH /api/admin/usuarios/{codigo}/estado
     * @auth   Requiere NIVEL = 1
     * @body   { ESTADO: 0|1 }
     * @param  int $codigo
     * @return JsonResponse
     */
    public function cambiarEstado(Request $request, int $codigo): JsonResponse
    {
        $request->validate(['ESTADO' => 'required|boolean']);

        $usuario = Usuario::where('CODIGO', $codigo)->firstOrFail();

        // No permitir desactivarse a uno mismo
        if ($request->user()->CODIGO === $codigo && (int)$request->ESTADO === 0) {
            return response()->json([
                'message' => 'No podés desactivar tu propia cuenta.',
            ], 422);
        }

        $usuario->update(['ESTADO' => (int)$request->ESTADO]);

        $accion = (int)$request->ESTADO === 1 ? 'activado' : 'desactivado';

        return response()->json([
            'message' => "Usuario {$usuario->NOMBRE} {$accion} correctamente.",
            'ESTADO'  => $usuario->ESTADO,
        ]);
    }

    /**
     * Fuerza que el usuario deba cambiar su contraseña al próximo ingreso.
     *
     * Establece primer_acceso = 1 y borra la contraseña actual.
     * El usuario deberá pasar por el flujo de activación completo.
     * Útil cuando el usuario olvidó sus credenciales o como medida de seguridad.
     *
     * @route  POST /api/admin/usuarios/{codigo}/reset-acceso
     * @auth   Requiere NIVEL = 1
     * @param  int $codigo
     * @return JsonResponse
     */
    public function resetAcceso(int $codigo): JsonResponse
    {
        $usuario = Usuario::where('CODIGO', $codigo)->firstOrFail();

        $usuario->update([
            'primer_acceso' => 1,
            'password'      => null,
        ]);

        // Revocar todos los tokens de Sanctum del usuario
        $usuario->tokens()->delete();

        return response()->json([
            'message' => "Se requiere reactivación para {$usuario->NOMBRE}. El usuario deberá completar el proceso de activación al próximo ingreso.",
        ]);
    }

    /**
     * Establece directamente una contraseña para el usuario (reset por administrador).
     *
     * No depende de email: el admin define (o genera) una clave temporal y se la
     * comunica al usuario, que ya puede iniciar sesión. Deja la cuenta lista
     * (primer_acceso = 0, ESTADO = 1) y revoca las sesiones anteriores.
     *
     * @route  POST /api/admin/usuarios/{codigo}/establecer-password
     * @auth   Requiere NIVEL = 1
     * @body   { password: string }
     * @param  int $codigo
     * @return JsonResponse
     */
    public function establecerPassword(Request $request, int $codigo): JsonResponse
    {
        $request->validate([
            'password' => 'required|string|min:6|max:100',
        ], [
            'password.min' => 'La contraseña debe tener al menos 6 caracteres.',
        ]);

        $usuario = Usuario::where('CODIGO', $codigo)->firstOrFail();

        $usuario->password      = Hash::make($request->password);
        $usuario->primer_acceso = 0;
        $usuario->ESTADO        = 1;
        $usuario->guardarClaveVisible($request->password);  // copia visible para el admin
        $usuario->save();

        // Cerrar cualquier sesión abierta con la clave anterior
        $usuario->tokens()->delete();

        return response()->json([
            'message' => "Contraseña de {$usuario->NOMBRE} establecida. Ya puede iniciar sesión con la clave que le asignaste.",
        ]);
    }

    /**
     * Devuelve la clave ACTUAL del usuario en texto plano, para que el administrador
     * pueda verla. Solo funciona si existe la copia cifrada (CLA_VER), que se guarda
     * desde que el usuario cambió/creó su clave o el admin se la restableció después
     * de habilitar esta función. Las claves anteriores (solo bcrypt) no se pueden ver.
     *
     * @route GET /api/admin/usuarios/{codigo}/ver-clave
     * @auth  Requiere ser administrador (middleware 'admin')
     */
    public function verClave(int $codigo): JsonResponse
    {
        $usuario = Usuario::where('CODIGO', $codigo)->firstOrFail();
        $clave   = $usuario->claveVisible();

        return response()->json([
            'disponible' => $clave !== null,
            'clave'      => $clave,
            'usuario'    => $usuario->NOMBRE,
        ]);
    }

    /**
     * Detalle completo de un usuario (para la solapa Datos Generales del ABM).
     *
     * @route GET /api/admin/usuarios/{codigo}/detalle
     * @auth  Requiere NIVEL = 1
     */
    public function detalleUsuario(int $codigo): JsonResponse
    {
        $u = Usuario::where('CODIGO', $codigo)->firstOrFail();
        return response()->json([
            'CODIGO'     => (int) $u->CODIGO,
            'NOMBRE'     => trim((string) $u->NOMBRE),
            'DATO1'      => trim((string) $u->DATO1),
            'email'      => $u->email,
            'NIVEL'      => (int) $u->NIVEL,
            'ESTADO'     => (int) $u->ESTADO,
            'DOMICILIO'  => trim((string) $u->DOMICILIO),
            'TELEFONO'   => trim((string) $u->TELEFONO),
            'DNI'        => trim((string) $u->DNI),
            'NOTAS'      => trim((string) $u->NOTAS),
            'RENOVAR'    => (int) $u->RENOVAR,
            'CADACUANTO' => (int) $u->CADACUANTO,
            'CONTADOR'   => (int) $u->CONTADOR,
            'ES_ADMIN'   => (int) $u->ES_ADMIN,
            'tiene_password' => !empty($u->password),
        ]);
    }

    /**
     * Catálogo de sub-sectores (para el desplegable de "Agregar").
     *
     * @route GET /api/admin/subsectores
     * @auth  Requiere NIVEL = 1
     */
    public function subsectores(): JsonResponse
    {
        $lista = \DB::table('subsector')->orderBy('sub_des')->get(['sub_cod', 'sub_des'])
            ->map(fn ($s) => ['sub_cod' => (int) $s->sub_cod, 'sub_des' => trim((string) $s->sub_des)])
            ->values();
        return response()->json($lista);
    }

    /**
     * Sub-sectores asociados a un usuario.
     *
     * @route GET /api/admin/usuarios/{codigo}/subsectores
     * @auth  Requiere NIVEL = 1
     */
    public function usuarioSubsectores(int $codigo): JsonResponse
    {
        $lista = \DB::table('usuarios_subsector')->where('USU_COD', $codigo)
            ->orderBy('SUB_DES')->get(['SUB_COD', 'SUB_DES'])
            ->map(fn ($s) => ['sub_cod' => (int) $s->SUB_COD, 'sub_des' => trim((string) $s->SUB_DES)])
            ->values();
        return response()->json($lista);
    }

    /**
     * Asocia un sub-sector a un usuario (evita duplicados).
     *
     * @route POST /api/admin/usuarios/{codigo}/subsectores
     * @body  { sub_cod }
     * @auth  Requiere NIVEL = 1
     */
    public function agregarSubsector(Request $request, int $codigo): JsonResponse
    {
        $request->validate(['sub_cod' => 'required|integer']);
        $u = Usuario::where('CODIGO', $codigo)->firstOrFail();
        $sub = \DB::table('subsector')->where('sub_cod', $request->sub_cod)->first(['sub_cod', 'sub_des']);
        if (!$sub) {
            return response()->json(['message' => 'El sub-sector no existe.'], 404);
        }
        $existe = \DB::table('usuarios_subsector')
            ->where('USU_COD', $codigo)->where('SUB_COD', $request->sub_cod)->exists();
        if ($existe) {
            return response()->json(['message' => 'Sub-sector repetido para este usuario.'], 422);
        }
        \DB::table('usuarios_subsector')->insert(\App\Support\Registro::completar('usuarios_subsector', [
            'USU_COD' => $codigo,
            'USU_NOM' => trim((string) $u->NOMBRE),
            'SUB_COD' => (int) $sub->sub_cod,
            'SUB_DES' => trim((string) $sub->sub_des),
        ]));
        return response()->json(['message' => 'Sub-sector asociado correctamente.'], 201);
    }

    /**
     * Quita la asociación de un sub-sector a un usuario.
     *
     * @route DELETE /api/admin/usuarios/{codigo}/subsectores/{subCod}
     * @auth  Requiere NIVEL = 1
     */
    public function eliminarSubsector(int $codigo, int $subCod): JsonResponse
    {
        \DB::table('usuarios_subsector')->where('USU_COD', $codigo)->where('SUB_COD', $subCod)->delete();
        return response()->json(['message' => 'Sub-sector quitado correctamente.']);
    }

    /**
     * Formatea un usuario para la respuesta de la API.
     * Método auxiliar usado en crearUsuario y actualizarUsuario.
     *
     * @param  Usuario $u
     * @return array<string, mixed>
     */
    private function formatearUsuario(Usuario $u): array
    {
        $cantidadPermisos = UsuarioPermiso::where('usuario_codigo', $u->CODIGO)->count();

        return [
            'CODIGO'              => $u->CODIGO,
            'DATO1'               => $u->DATO1,
            'NOMBRE'              => $u->NOMBRE,
            'email'               => $u->email,
            'NIVEL'               => $u->NIVEL,
            'EMPRESA'             => $u->EMPRESA,
            'ESTADO'              => $u->ESTADO,
            'primer_acceso'       => $u->primer_acceso,
            'es_admin'            => (int)$u->NIVEL === 1,
            'tiene_restricciones' => $cantidadPermisos > 0,
            'cantidad_permisos'   => $cantidadPermisos,
        ];
    }

    // ══════════════════════════════════════════════════════════
    // PERMISOS
    // ══════════════════════════════════════════════════════════

    /**
     * Retorna los permisos asignados a un usuario específico.
     *
     * Array vacío → el usuario no tiene restricciones (ve todo el menú).
     * Array con claves → lista blanca de ítems que puede ver.
     *
     * @route  GET /api/admin/usuarios/{codigo}/permisos
     * @auth   Requiere NIVEL = 1
     * @param  int $codigo  Campo CODIGO del usuario en la tabla usuarios
     * @return JsonResponse { usuario: {...}, permisos: string[], tiene_restricciones: bool }
     */
    public function getPermisos(int $codigo): JsonResponse
    {
        $usuario = Usuario::where('CODIGO', $codigo)
            ->first(['CODIGO', 'DATO1', 'NOMBRE', 'NIVEL', 'ES_ADMIN']);

        if (!$usuario) {
            return response()->json(['message' => 'Usuario no encontrado.'], 404);
        }

        $permisos = UsuarioPermiso::clavesDeUsuario($codigo);

        return response()->json([
            'usuario' => [
                'CODIGO'   => $usuario->CODIGO,
                'DATO1'    => $usuario->DATO1,
                'NOMBRE'   => $usuario->NOMBRE,
                'NIVEL'    => $usuario->NIVEL,
                'es_admin' => (int)$usuario->ES_ADMIN === 1,
            ],
            'permisos'            => $permisos,
            'tiene_restricciones' => count($permisos) > 0,
        ]);
    }

    /**
     * Reemplaza completamente los permisos de un usuario.
     *
     * Operación atómica: elimina todos los permisos actuales
     * del usuario e inserta los nuevos en una transacción.
     *
     * Si se envía array vacío → se eliminan todas las restricciones
     * (el usuario vuelve a ver todo el menú).
     *
     * No se pueden modificar los permisos de usuarios NIVEL = 1
     * (administradores totales).
     *
     * @route  PUT /api/admin/usuarios/{codigo}/permisos
     * @auth   Requiere NIVEL = 1
     * @body   { permisos: string[] }  Array de claves de ítems de menú
     * @param  int $codigo  Campo CODIGO del usuario
     * @return JsonResponse { message, cantidad_permisos, tiene_restricciones }
     */
    public function setPermisos(Request $request, int $codigo): JsonResponse
    {
        $request->validate([
            'permisos'   => 'required|array',
            'permisos.*' => 'string|max:100',
        ]);

        $usuario = Usuario::where('CODIGO', $codigo)->first();

        if (!$usuario) {
            return response()->json(['message' => 'Usuario no encontrado.'], 404);
        }

        // No se pueden modificar los permisos de un Administrador total
        if ((int)$usuario->ES_ADMIN === 1) {
            return response()->json([
                'message' => 'No se pueden configurar permisos para un Administrador. Los administradores tienen acceso total.',
            ], 422);
        }

        // Reemplazar permisos en transacción
        $cantidad = UsuarioPermiso::reemplazar($codigo, $request->permisos);

        $mensaje = $cantidad === 0
            ? "Restricciones eliminadas. {$usuario->NOMBRE} ahora ve todo el menú."
            : "Se configuraron {$cantidad} permisos para {$usuario->NOMBRE}.";

        return response()->json([
            'message'             => $mensaje,
            'cantidad_permisos'   => $cantidad,
            'tiene_restricciones' => $cantidad > 0,
        ]);
    }

    // ══════════════════════════════════════════════════════════
    // ROLES / PLANTILLAS DE PERMISOS (tabla niveles + nivel_permisos)
    // Un "rol" es un nivel con un conjunto de claves de menú permitidas,
    // que sirve de plantilla para precargar los permisos de un usuario.
    // ══════════════════════════════════════════════════════════

    /** @route GET /api/admin/roles — lista de roles con su cantidad de permisos. */
    public function roles(): JsonResponse
    {
        $cuentas = \DB::table('nivel_permisos')->select('nivel_codigo', \DB::raw('COUNT(*) as n'))
            ->groupBy('nivel_codigo')->pluck('n', 'nivel_codigo');
        $roles = \DB::table('niveles')->orderBy('DESCRIBE')->get(['CODNIV', 'DESCRIBE'])
            ->map(fn ($n) => [
                'cod' => (int) $n->CODNIV, 'descripcion' => trim((string) $n->DESCRIBE),
                'permisos' => (int) ($cuentas[$n->CODNIV] ?? 0),
            ])->values();
        return response()->json(['roles' => $roles]);
    }

    /** @route GET /api/admin/roles/{cod} — rol con sus claves de permiso. */
    public function rolDetalle(int $cod): JsonResponse
    {
        $n = \DB::table('niveles')->where('CODNIV', $cod)->first(['CODNIV', 'DESCRIBE']);
        if (!$n) return response()->json(['message' => 'Rol inexistente.'], 404);
        $permisos = \DB::table('nivel_permisos')->where('nivel_codigo', $cod)->pluck('permiso_clave')->all();
        return response()->json(['cod' => (int) $n->CODNIV, 'descripcion' => trim((string) $n->DESCRIBE), 'permisos' => $permisos]);
    }

    /** @route POST /api/admin/roles — crea un rol. */
    public function crearRol(Request $request): JsonResponse
    {
        $d = $request->validate(['descripcion' => 'required|string|max:50', 'permisos' => 'nullable|array', 'permisos.*' => 'string|max:100']);
        $cod = (int) \DB::table('niveles')->max('CODNIV') + 1;
        \DB::transaction(function () use ($cod, $d) {
            \DB::table('niveles')->insert(\App\Support\Registro::completar('niveles', ['CODNIV' => $cod, 'DESCRIBE' => mb_strtoupper(trim($d['descripcion']))]));
            $this->guardarPermisosRol($cod, $d['permisos'] ?? []);
        });
        return response()->json(['message' => 'Rol creado correctamente.', 'cod' => $cod], 201);
    }

    /** @route PUT /api/admin/roles/{cod} — actualiza descripción + permisos del rol. */
    public function actualizarRol(Request $request, int $cod): JsonResponse
    {
        if (!\DB::table('niveles')->where('CODNIV', $cod)->exists()) return response()->json(['message' => 'Rol inexistente.'], 404);
        $d = $request->validate(['descripcion' => 'required|string|max:50', 'permisos' => 'nullable|array', 'permisos.*' => 'string|max:100']);
        \DB::transaction(function () use ($cod, $d) {
            \DB::table('niveles')->where('CODNIV', $cod)->update(['DESCRIBE' => mb_strtoupper(trim($d['descripcion']))]);
            $this->guardarPermisosRol($cod, $d['permisos'] ?? []);
        });
        return response()->json(['message' => 'Rol actualizado correctamente.']);
    }

    /** @route DELETE /api/admin/roles/{cod} — elimina un rol (si no hay usuarios asignados). */
    public function eliminarRol(int $cod): JsonResponse
    {
        if (\DB::table('usuarios')->where('NIVEL', $cod)->exists()) {
            return response()->json(['message' => 'No se puede eliminar: hay usuarios con este rol asignado.'], 422);
        }
        \DB::transaction(function () use ($cod) {
            \DB::table('nivel_permisos')->where('nivel_codigo', $cod)->delete();
            \DB::table('niveles')->where('CODNIV', $cod)->delete();
        });
        return response()->json(['message' => 'Rol eliminado correctamente.']);
    }

    /**
     * Clona los permisos de menú de un usuario origen hacia uno o varios destinos.
     * Reemplaza por completo los permisos de cada destino por los del origen.
     *
     * @route POST /api/admin/permisos/clonar
     * @body  { origen: int, destinos: int[] }
     */
    public function clonarPermisos(Request $request): JsonResponse
    {
        $d = $request->validate([
            'origen'      => 'required|integer',
            'destinos'    => 'required|array|min:1',
            'destinos.*'  => 'integer',
        ]);

        $origen = (int) $d['origen'];
        $claves = UsuarioPermiso::clavesDeUsuario($origen);

        $clonados = 0;
        foreach (array_unique(array_map('intval', $d['destinos'])) as $destino) {
            if ($destino === $origen) continue;
            UsuarioPermiso::reemplazar($destino, $claves);
            $clonados++;
        }

        return response()->json([
            'clonados' => $clonados,
            'permisos' => count($claves),
            'sin_restriccion' => empty($claves),   // origen sin permisos => destinos verán todo
        ]);
    }

    private function guardarPermisosRol(int $cod, array $claves): void
    {
        \DB::table('nivel_permisos')->where('nivel_codigo', $cod)->delete();
        $filas = array_values(array_unique(array_filter(array_map('strval', $claves))));
        if ($filas) {
            \DB::table('nivel_permisos')->insert(array_map(fn ($c) => ['nivel_codigo' => $cod, 'permiso_clave' => $c], $filas));
        }
    }
}
