<?php

/**
 * ============================================================
 * AuthController.php
 * ============================================================
 * Controlador de Autenticación — Sistema RRHH.NET
 *
 * Maneja todo el ciclo de autenticación de usuarios:
 *   1. Login normal (email + contraseña bcrypt)
 *   2. Primer acceso (activación de cuenta en 4 pasos):
 *        Paso 1 → verificarUsuario   : confirmar que el DATO1 existe y está activo
 *        Paso 2 → enviarCodigo       : registrar email y enviar código de 6 dígitos
 *        Paso 3 → validarCodigo      : verificar el código recibido por email
 *        Paso 4 → crearPassword      : establecer la contraseña definitiva
 *   3. Recuperación de contraseña (forgot / reset)
 *   4. Logout
 *   5. Datos del usuario autenticado (me)
 *
 * Tabla origen: usuarios (migrada desde SQL Server)
 *   - DATO1  = login (nombre de usuario)
 *   - DATO2  = contraseña original de FoxPro (ya no se usa)
 *   - NOMBRE = nombre completo
 *   - ESTADO = 1 activo / 0 inactivo
 *   - NIVEL  = nivel de acceso del usuario
 *
 * Campos agregados por migración (2026_06_08_000001):
 *   - email, password, email_verified_at, primer_acceso, remember_token
 *
 * Tokens: Laravel Sanctum (personal_access_tokens)
 * Cache:  Laravel Cache (driver database) para códigos y tokens temporales
 *
 * @package    App\Http\Controllers
 * @author     Sistema RRHH.NET
 * @version    1.0.0
 * @since      2026-06-08
 * ============================================================
 */

namespace App\Http\Controllers;

use App\Models\Usuario;
use App\Models\UsuarioPermiso;
use App\Notifications\CodigoVerificacionNotification;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

class AuthController extends Controller
{
    // ══════════════════════════════════════════════════════════
    // 1. LOGIN
    // ══════════════════════════════════════════════════════════

    /**
     * Autentica al usuario con email o usuario (DATO1) + contraseña.
     *
     * - Acepta tanto el email como el DATO1 original de FoxPro como identificador.
     * - Si el campo 'login' contiene '@' → busca por email.
     * - Si no contiene '@' → busca por DATO1.
     * - Solo pueden ingresar usuarios que completaron el primer acceso (tienen password).
     * - Verifica que el campo ESTADO sea 1 (activo).
     * - Compara la contraseña usando bcrypt (Hash::check).
     * - Revoca todos los tokens anteriores del usuario.
     * - Genera un nuevo token Sanctum y lo retorna.
     *
     * @route  POST /api/auth/login
     * @body   { login: string (email o DATO1), password: string }
     * @return JsonResponse { token, usuario: { CODIGO, DATO1, NOMBRE, email, NIVEL } }
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'login'    => 'required|string',
            'password' => 'required|string',
        ]);

        $loginIngresado = trim($request->login);

        // Determinar si es email o DATO1 por la presencia de '@'
        if (str_contains($loginIngresado, '@')) {
            $usuario = Usuario::where('email', $loginIngresado)->first();
        } else {
            $usuario = Usuario::where('DATO1', $loginIngresado)->first();
        }

        // Verificar existencia y que haya completado el primer acceso (tenga contraseña).
        // NO se valida ESTADO: en FoxPro es el flag de sesión (logueado/no) y ambas apps
        // comparten la tabla usuarios, así que un ESTADO=0 dejado por Fox no debe impedir
        // el login web (antes causaba rechazos intermitentes con "Credenciales incorrectas").
        if (!$usuario || !$usuario->password) {
            return response()->json(['message' => 'Credenciales incorrectas.'], 401);
        }

        // Verificar contraseña bcrypt
        if (!Hash::check($request->password, $usuario->password)) {
            return response()->json(['message' => 'Credenciales incorrectas.'], 401);
        }

        // GERENCIAL: NO se revocan los tokens anteriores. El tablero se ve desde
        // varias terminales/navegadores a la vez; si se borraran, un login nuevo
        // dejaba "Unauthenticated" a las otras sesiones. Se permiten concurrentes.
        // (Se limpian solo los sobrantes viejos para que no se acumulen sin límite.)
        $usuario->tokens()->where('created_at', '<', now()->subDays(30))->delete();

        $token    = $usuario->createToken('auth_token')->plainTextToken;
        $esAdmin  = (int)$usuario->ES_ADMIN === 1;
        $permisos = $esAdmin ? [] : UsuarioPermiso::clavesDeUsuario($usuario->CODIGO);

        return response()->json([
            'token'   => $token,
            'empresa' => config('rrhh.empresa'),
            'usuario' => [
                'CODIGO'   => $usuario->CODIGO,
                'DATO1'    => $usuario->DATO1,
                'NOMBRE'   => $usuario->NOMBRE,
                'email'    => $usuario->email,
                'NIVEL'    => $usuario->NIVEL,
                'es_admin' => $esAdmin,
                'permisos' => $permisos,
            ],
        ]);
    }

    // ══════════════════════════════════════════════════════════
    // 2. PRIMER ACCESO — Paso 1: Verificar usuario
    // ══════════════════════════════════════════════════════════

    /**
     * Verifica que el nombre de usuario (DATO1) exista y esté activo.
     *
     * - Busca por el campo DATO1 (login heredado de FoxPro).
     * - Confirma que ESTADO = 1.
     * - Si el usuario ya completó el primer acceso (primer_acceso = false
     *   y tiene contraseña), informa que debe usar el login normal.
     *
     * @route  POST /api/auth/verificar-usuario
     * @body   { usuario: string }   (valor del campo DATO1)
     * @return JsonResponse { nombre: string, tiene_email: boolean }
     */
    public function verificarUsuario(Request $request): JsonResponse
    {
        $request->validate([
            'usuario' => 'required|string',
        ]);

        $u = Usuario::where('DATO1', $request->usuario)->first();

        // Solo verificamos que el usuario exista (no filtramos por ESTADO,
        // porque los usuarios del sistema anterior vienen con ESTADO=0
        // y necesitan poder activar su cuenta)
        if (!$u) {
            return response()->json(['message' => 'Usuario no encontrado.'], 404);
        }

        // El usuario ya activó su cuenta → redirigir al login normal
        if (!$u->primer_acceso && $u->password) {
            return response()->json([
                'message' => 'Este usuario ya tiene una cuenta activa. Usá el login normal.',
            ], 409);
        }

        return response()->json([
            'nombre'      => $u->NOMBRE,
            'tiene_email' => !empty($u->email),
        ]);
    }

    // ══════════════════════════════════════════════════════════
    // 2. PRIMER ACCESO — Paso 2: Registrar email y enviar código
    // ══════════════════════════════════════════════════════════

    /**
     * Registra el email del usuario y le envía un código de verificación.
     *
     * - Si ya tenía email registrado, verifica que coincida con el ingresado.
     * - Si no tenía email, lo guarda en la base de datos.
     * - Genera un código de 6 dígitos con relleno de ceros.
     * - Guarda el código en Cache con clave 'verificacion_{CODIGO}' por 15 minutos.
     * - Envía el código por email usando CodigoVerificacionNotification.
     *
     * @route  POST /api/auth/enviar-codigo
     * @body   { usuario: string, email: string }
     * @return JsonResponse { message: string }
     */
    public function enviarCodigo(Request $request): JsonResponse
    {
        $request->validate([
            'usuario' => 'required|string',
            'email'   => 'required|email',
        ]);

        $u = Usuario::where('DATO1', $request->usuario)->first();

        // Respuesta genérica: no revelar si el usuario existe ni si el email
        // registrado coincide (evita enumeración). Sólo se envía el código cuando
        // el usuario existe y (no tenía email registrado o el email coincide).
        $generica = response()->json(['message' => 'Si los datos son correctos, se envió un código al email.']);

        if (!$u) {
            return $generica;
        }
        if ($u->email && strtolower($u->email) !== strtolower($request->email)) {
            return $generica;
        }

        // Guardar email si no tenía uno asignado
        if (!$u->email) {
            $u->email = strtolower($request->email);
            $u->save();
        }

        // Generar código de 6 dígitos (con ceros a la izquierda si es necesario)
        $codigo = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Guardar en cache 15 minutos — clave única por usuario
        Cache::put('verificacion_' . $u->CODIGO, $codigo, now()->addMinutes(15));

        // Enviar email con el código
        $u->notify(new CodigoVerificacionNotification($codigo));

        return $generica;
    }

    // ══════════════════════════════════════════════════════════
    // RECUPERAR CONTRASEÑA (olvidó su clave) — por código al email registrado
    // ══════════════════════════════════════════════════════════

    /**
     * Inicia la recuperación de contraseña para un usuario que YA activó su cuenta.
     *
     * Envía un código de 6 dígitos AL EMAIL YA REGISTRADO del usuario (no permite
     * ingresar uno nuevo: eso evita el secuestro de cuentas). Luego se reutilizan
     * validar-codigo y crear-password (los mismos del primer acceso).
     *
     * Si el usuario no tiene email registrado, responde { sin_email: true } para
     * que el frontend muestre el cartel de "pedile la clave al administrador".
     *
     * @route  POST /api/auth/recuperar
     * @body   { usuario: string }   (login/DATO1)
     * @return JsonResponse
     */
    public function recuperarClave(Request $request): JsonResponse
    {
        $request->validate(['usuario' => 'required|string']);

        $u = Usuario::where('DATO1', trim($request->usuario))->first();

        if (!$u) {
            return response()->json(['message' => 'Usuario no encontrado.'], 404);
        }

        // Sin email registrado → no se puede verificar la identidad automáticamente.
        if (empty($u->email)) {
            return response()->json(['sin_email' => true]);
        }

        $codigo = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        Cache::put('verificacion_' . $u->CODIGO, $codigo, now()->addMinutes(15));
        $u->notify(new CodigoVerificacionNotification($codigo));

        return response()->json([
            'enviado' => true,
            'pista'   => $this->pistaEmail($u->email),
        ]);
    }

    /** Devuelve el email parcialmente oculto (ej. "ju***@gmail.com"). */
    private function pistaEmail(string $email): string
    {
        $partes = explode('@', $email, 2);
        $usuario = $partes[0] ?? '';
        $dominio = $partes[1] ?? '';
        $visible = mb_substr($usuario, 0, min(2, mb_strlen($usuario)));
        return $visible . '***@' . $dominio;
    }

    // ══════════════════════════════════════════════════════════
    // 2. PRIMER ACCESO — Paso 3: Validar código
    // ══════════════════════════════════════════════════════════

    /**
     * Valida el código de verificación de 6 dígitos recibido por email.
     *
     * - Recupera el código del Cache y lo compara con el ingresado.
     * - Si es válido: marca email_verified_at con la fecha/hora actual.
     * - Genera un token temporal (64 chars) válido 30 minutos para el paso 4.
     * - Guarda el token en Cache con clave 'activacion_{CODIGO}'.
     * - Elimina el código de verificación del Cache.
     *
     * @route  POST /api/auth/validar-codigo
     * @body   { usuario: string, codigo: string (6 dígitos) }
     * @return JsonResponse { message: string, token_temporal: string }
     */
    public function validarCodigo(Request $request): JsonResponse
    {
        $request->validate([
            'usuario' => 'required|string',
            'codigo'  => 'required|string|size:6',
        ]);

        $u = Usuario::where('DATO1', $request->usuario)->first();

        if (!$u) {
            return response()->json(['message' => 'Usuario no encontrado.'], 404);
        }

        // Recuperar código del cache
        $codigoGuardado = Cache::get('verificacion_' . $u->CODIGO);

        // Límite de intentos: tras 5 fallos se invalida el código (evita adivinarlo por fuerza bruta).
        $claveIntentos = 'verif_intentos_' . $u->CODIGO;
        if ((int) Cache::get($claveIntentos, 0) >= 5) {
            Cache::forget('verificacion_' . $u->CODIGO);
            return response()->json(['message' => 'Demasiados intentos. Solicite un nuevo código.'], 429);
        }

        if (!$codigoGuardado || !hash_equals((string) $codigoGuardado, (string) $request->codigo)) {
            Cache::put($claveIntentos, (int) Cache::get($claveIntentos, 0) + 1, now()->addMinutes(15));
            return response()->json(['message' => 'Código incorrecto o expirado.'], 422);
        }
        Cache::forget($claveIntentos);

        // Marcar email como verificado
        $u->email_verified_at = now();
        $u->save();

        // Token temporal para el paso 4 (crear contraseña) — válido 30 minutos
        $tokenTemporal = Str::random(64);
        Cache::put('activacion_' . $u->CODIGO, $tokenTemporal, now()->addMinutes(30));

        // Limpiar el código de verificación ya usado
        Cache::forget('verificacion_' . $u->CODIGO);

        return response()->json([
            'message'        => 'Email verificado correctamente.',
            'token_temporal' => $tokenTemporal,
        ]);
    }

    // ══════════════════════════════════════════════════════════
    // 2. PRIMER ACCESO — Paso 4: Crear contraseña
    // ══════════════════════════════════════════════════════════

    /**
     * Establece la contraseña definitiva del usuario y activa su cuenta.
     *
     * - Verifica el token temporal del Cache (clave 'activacion_{CODIGO}').
     * - Hashea la nueva contraseña con bcrypt.
     * - Marca primer_acceso = false (la cuenta queda completamente activa).
     * - Realiza login automático: crea y retorna un token Sanctum.
     * - Limpia el token temporal del Cache.
     *
     * Reglas de contraseña: mínimo 8 caracteres, mayúscula, número y símbolo.
     *
     * @route  POST /api/auth/crear-password
     * @body   { usuario: string, token_temporal: string, password: string, password_confirmation: string }
     * @return JsonResponse { message, token, usuario: { CODIGO, DATO1, NOMBRE, email, NIVEL } }
     */
    public function crearPassword(Request $request): JsonResponse
    {
        $request->validate([
            'usuario'        => 'required|string',
            'token_temporal' => 'required|string',
            'password'       => ['required', 'confirmed',
                PasswordRule::min(8)->mixedCase()->numbers()->symbols()
            ],
        ]);

        $u = Usuario::where('DATO1', $request->usuario)->first();

        if (!$u) {
            return response()->json(['message' => 'Usuario no encontrado.'], 404);
        }

        // Validar token temporal
        $tokenGuardado = Cache::get('activacion_' . $u->CODIGO);

        if (!$tokenGuardado || $tokenGuardado !== $request->token_temporal) {
            return response()->json([
                'message' => 'Token inválido o expirado. Reiniciá el proceso.',
            ], 422);
        }

        // Guardar nueva contraseña hasheada y activar la cuenta (web).
        // ⚠️ GERENCIAL: auth contra sqlLOGIST (GESTIÓN). NO tocar ESTADO (es el flag
        // de sesión del Fox: en 1 bloquea al usuario por "login duplicado") ni CLA_VER
        // (columna de RRHH que no existe en sqlLOGIST).
        $u->password      = Hash::make($request->password);
        $u->primer_acceso = false;
        $u->save();

        // Eliminar token temporal del cache
        Cache::forget('activacion_' . $u->CODIGO);

        // Login automático al finalizar la activación
        $token    = $u->createToken('auth_token')->plainTextToken;
        $esAdmin  = (int)$u->ES_ADMIN === 1;
        $permisos = $esAdmin ? [] : UsuarioPermiso::clavesDeUsuario($u->CODIGO);

        return response()->json([
            'message' => 'Cuenta activada correctamente.',
            'token'   => $token,
            'usuario' => [
                'CODIGO'   => $u->CODIGO,
                'DATO1'    => $u->DATO1,
                'NOMBRE'   => $u->NOMBRE,
                'email'    => $u->email,
                'NIVEL'    => $u->NIVEL,
                'es_admin' => $esAdmin,
                'permisos' => $permisos,
            ],
        ]);
    }

    // ══════════════════════════════════════════════════════════
    // 3. RECUPERACIÓN DE CONTRASEÑA
    // ══════════════════════════════════════════════════════════

    /**
     * Envía un link de restablecimiento de contraseña al email del usuario.
     *
     * - La respuesta es siempre genérica (no revela si el email existe).
     * - Genera un token aleatorio de 64 caracteres.
     * - Guarda el HASH del token en Cache con clave 'reset_{CODIGO}' por 1 hora.
     * - Envía el email con ResetPasswordNotification (incluye link al frontend).
     *
     * @route  POST /api/auth/forgot-password
     * @body   { email: string }
     * @return JsonResponse { message: string }
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        $u = Usuario::where('email', $request->email)->first();

        // Respuesta genérica por seguridad (no revelar si el email existe o no)
        if (!$u || !$u->email_verified_at) {
            return response()->json([
                'message' => 'Si el email existe, recibirás un link para restablecer tu contraseña.',
            ]);
        }

        // Generar token y guardar su hash en cache por 1 hora
        $token = Str::random(64);
        Cache::put('reset_' . $u->CODIGO, Hash::make($token), now()->addHour());

        // Enviar email con el link de reseteo
        $u->notify(new ResetPasswordNotification($token));

        return response()->json([
            'message' => 'Si el email existe, recibirás un link para restablecer tu contraseña.',
        ]);
    }

    /**
     * Restablece la contraseña usando el token recibido por email.
     *
     * - Busca el usuario por email.
     * - Recupera el hash del token del Cache y lo compara con el token recibido.
     * - Actualiza la contraseña con bcrypt.
     * - Limpia el token del Cache.
     *
     * @route  POST /api/auth/reset-password
     * @body   { email: string, token: string, password: string, password_confirmation: string }
     * @return JsonResponse { message: string }
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => 'required|email',
            'token'    => 'required|string',
            'password' => ['required', 'confirmed',
                PasswordRule::min(8)->mixedCase()->numbers()->symbols()
            ],
        ]);

        $u = Usuario::where('email', $request->email)->first();

        if (!$u) {
            return response()->json(['message' => 'Token inválido o expirado.'], 422);
        }

        // Verificar el token contra su hash guardado en cache
        $hashGuardado = Cache::get('reset_' . $u->CODIGO);

        if (!$hashGuardado || !Hash::check($request->token, $hashGuardado)) {
            return response()->json(['message' => 'Token inválido o expirado.'], 422);
        }

        // Actualizar contraseña (sin CLA_VER: no existe en sqlLOGIST)
        $u->password = Hash::make($request->password);
        $u->save();

        // Limpiar token usado
        Cache::forget('reset_' . $u->CODIGO);

        return response()->json([
            'message' => 'Contraseña restablecida correctamente. Ya podés iniciar sesión.',
        ]);
    }

    // ══════════════════════════════════════════════════════════
    // 4. LOGOUT
    // ══════════════════════════════════════════════════════════

    /**
     * Cierra la sesión del usuario autenticado.
     *
     * - Elimina únicamente el token actual (currentAccessToken).
     * - Requiere autenticación via Sanctum (middleware auth:sanctum).
     *
     * @route  POST /api/auth/logout
     * @auth   Requiere Bearer token
     * @return JsonResponse { message: string }
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sesión cerrada correctamente.']);
    }

    // ══════════════════════════════════════════════════════════
    // 4b. CAMBIO DE CLAVE (usuario logueado)
    // ══════════════════════════════════════════════════════════

    /**
     * Permite al usuario autenticado cambiar su propia contraseña.
     *
     * - Verifica la contraseña actual contra el hash guardado.
     * - La nueva debe confirmarse y cumplir la política (8+, mayús/minús, número, símbolo).
     * - No puede ser igual a la actual. Limpia el flag de "forzar renovar" si existe.
     *
     * @route  POST /api/auth/cambiar-password
     * @auth   Requiere Bearer token
     */
    public function cambiarPassword(Request $request): JsonResponse
    {
        $request->validate([
            'actual'   => 'required|string',
            'password' => ['required', 'confirmed', 'different:actual',
                PasswordRule::min(8)->mixedCase()->numbers()->symbols()
            ],
        ]);

        $u = $request->user();

        if (!Hash::check($request->actual, (string) $u->password)) {
            return response()->json(['message' => 'La contraseña actual no es correcta.'], 422);
        }

        $u->password = Hash::make($request->password);
        // Si el usuario estaba forzado a renovar la clave, se desmarca y se reinicia el contador.
        // hasColumn sobre la conexión 'gestion' (sqlLOGIST), donde vive el usuario.
        if (\Illuminate\Support\Facades\Schema::connection('gestion')->hasColumn('usuarios', 'renovar')) {
            $u->renovar = 0;
            if (\Illuminate\Support\Facades\Schema::connection('gestion')->hasColumn('usuarios', 'contador')) $u->contador = 0;
        }
        $u->save();

        return response()->json(['message' => 'Contraseña cambiada correctamente.']);
    }

    // ══════════════════════════════════════════════════════════
    // 5. USUARIO AUTENTICADO
    // ══════════════════════════════════════════════════════════

    /**
     * Retorna los datos del usuario actualmente autenticado + sus permisos de menú.
     *
     * - Usado por el frontend al recargar la página para restaurar la sesión.
     * - Incluye el array de permisos para que el menú filtre correctamente.
     * - NIVEL = 1 → permisos = [] (array vacío = sin restricciones, ve todo).
     * - Otros niveles → permisos = claves configuradas en usuario_permisos.
     *   Si no tiene filas configuradas → [] (sin restricciones todavía).
     *
     * @route  GET /api/auth/me
     * @auth   Requiere Bearer token
     * @return JsonResponse { CODIGO, DATO1, NOMBRE, email, NIVEL, es_admin, permisos[] }
     */
    public function me(Request $request): JsonResponse
    {
        $u = $request->user();

        // Los administradores (NIVEL=1) no tienen restricciones de menú
        $esAdmin  = (int)$u->ES_ADMIN === 1;
        $permisos = $esAdmin ? [] : UsuarioPermiso::clavesDeUsuario($u->CODIGO);

        return response()->json([
            'CODIGO'   => $u->CODIGO,
            'DATO1'    => $u->DATO1,
            'NOMBRE'   => $u->NOMBRE,
            'email'    => $u->email,
            'NIVEL'    => $u->NIVEL,
            'es_admin' => $esAdmin,
            'permisos' => $permisos,  // [] = sin restricciones, [claves] = lista blanca
            'empresa'  => config('rrhh.empresa'),
        ]);
    }
}
