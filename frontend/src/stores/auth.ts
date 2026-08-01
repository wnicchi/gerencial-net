/**
 * ============================================================
 * stores/auth.ts — Store de Autenticación
 * ============================================================
 * Maneja el estado de sesión del usuario en el frontend.
 * Usa Pinia como gestor de estado global.
 *
 * Estado almacenado:
 *   - token:   Bearer token de Sanctum (en sessionStorage: se borra al cerrar el navegador)
 *   - usuario: Datos del usuario autenticado (nombre, nivel, etc.)
 *
 * Permisos de menú:
 *   - es_admin:  true si NIVEL = 1 → ve todo, ignora lista de permisos
 *   - permisos:  array de claves de ítems de menú que puede ver
 *                Array vacío = ve todo (sin restricciones)
 *                Array con claves = lista blanca (whitelist)
 *
 * Lógica de visibilidad de ítem:
 *   puedoVer(clave) → true si:
 *     1. El usuario es admin (NIVEL=1), o
 *     2. No tiene restricciones (permisos vacío), o
 *     3. La clave está en su lista de permisos
 *
 * @module stores/auth
 */

import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api, { authService } from '@/services/auth'
import logoSilcar from '@/assets/logo-silcar.jpg'
import logoLogist from '@/assets/logo-logist.png'
import fondoSilcar from '@/assets/fondo-silcar.png'
import fondoLogist from '@/assets/fondo-logist.png'

// Logo por empresa (definida por el backend según el .env). Ver config/rrhh.php.
const LOGOS: Record<string, string> = { silcar: logoSilcar, logist: logoLogist }
// Imagen de fondo del login por empresa (RRHH Autoelevadores / RRHH Logística).
const FONDOS: Record<string, string> = { silcar: fondoSilcar, logist: fondoLogist }

/**
 * Estructura del usuario autenticado.
 * Los campos en mayúsculas provienen directamente de la tabla MySQL
 * (heredados del sistema FoxPro original).
 */
export interface UsuarioAuth {
  // ── Campos de la tabla usuarios (nombres tal como devuelve el backend) ──
  CODIGO?: number        // PK del usuario
  DATO1?: string         // login/username
  NOMBRE?: string        // nombre completo
  email?: string         // email registrado
  EMPRESA?: string       // empresa asociada
  NIVEL?: number         // nivel de acceso (1 = Administrador)
  primer_acceso?: boolean

  // ── Permisos de menú ──────────────────────────────────────────────────
  es_admin?: boolean     // true si NIVEL=1 → acceso total sin restricciones
  permisos?: string[]    // claves de ítems de menú permitidos (whitelist)
                         // vacío = ve todo, no vacío = solo esos ítems

  // ── Aliases en minúsculas (por compatibilidad) ────────────────────────
  nombre?: string
  id?: number
}

export const useAuthStore = defineStore('auth', () => {
  // ── Estado ─────────────────────────────────────────────────────────────
  // El token va en sessionStorage: se borra al cerrar el navegador, así que al
  // reabrir el sitio el usuario debe iniciar sesión de nuevo (más seguro).
  const token   = ref<string | null>(sessionStorage.getItem('token'))
  const usuario = ref<UsuarioAuth | null>(null)
  // Empresa (persiste para elegir el logo sin esperar a /auth/me al refrescar).
  const empresa = ref<string>(localStorage.getItem('empresa') || 'silcar')

  // ── Computed ───────────────────────────────────────────────────────────
  const estaAutenticado = computed(() => !!token.value)

  /** Logo de la empresa actual (sidebar y PDFs). */
  const logoEmpresa = computed(() => LOGOS[empresa.value] ?? logoSilcar)

  /** Imagen de fondo del login según la empresa. */
  const fondoLogin = computed(() => FONDOS[empresa.value] ?? fondoSilcar)

  function setEmpresa(e?: string) {
    if (e) { empresa.value = e; localStorage.setItem('empresa', e); aplicarFavicon(e) }
  }

  /**
   * Favicon "RH" según la empresa: azul para Logística, verde para Autoelevadores.
   * Se dibuja en un canvas y se exporta como PNG para que también funcione en
   * navegadores viejos (ej. Chrome en Windows 8.1) que no soportan favicon SVG.
   */
  function aplicarFavicon(emp: string) {
    const color = emp === 'logist' ? '#1e3a5f' : '#1b4332'
    const cv = document.createElement('canvas')
    cv.width = 64; cv.height = 64
    const ctx = cv.getContext('2d')
    if (!ctx) return
    const r = 14
    ctx.fillStyle = color
    ctx.beginPath()
    ctx.moveTo(r, 0); ctx.lineTo(64 - r, 0); ctx.quadraticCurveTo(64, 0, 64, r)
    ctx.lineTo(64, 64 - r); ctx.quadraticCurveTo(64, 64, 64 - r, 64)
    ctx.lineTo(r, 64); ctx.quadraticCurveTo(0, 64, 0, 64 - r)
    ctx.lineTo(0, r); ctx.quadraticCurveTo(0, 0, r, 0)
    ctx.closePath(); ctx.fill()
    ctx.fillStyle = '#ffffff'
    ctx.font = 'bold 34px Arial, Helvetica, sans-serif'
    ctx.textAlign = 'center'
    ctx.textBaseline = 'middle'
    ctx.fillText('RH', 32, 37)
    const href = cv.toDataURL('image/png')
    // Quitar cualquier favicon previo (incluido el .ico viejo) y poner el nuevo PNG.
    document.querySelectorAll("link[rel~='icon']").forEach((l) => l.remove())
    const link = document.createElement('link')
    link.rel = 'icon'
    link.type = 'image/png'
    link.href = href
    document.head.appendChild(link)
  }

  // Aplicar el favicon con la empresa persistida ni bien arranca la app.
  aplicarFavicon(empresa.value)

  /** Lee la empresa del deploy desde el endpoint público (para el login, antes de autenticar). */
  async function cargarEmpresa() {
    try {
      const { data } = await api.get('/config')
      if (data?.empresa) setEmpresa(data.empresa)
    } catch { /* si falla, queda la empresa persistida/por defecto */ }
  }

  /**
   * Verifica si el usuario puede ver un ítem de menú por su clave.
   *
   * @param clave  El campo 'id' del ítem en menu.ts (ej: 'empleados-abm')
   * @returns true si puede ver el ítem
   */
  // Ítems SIEMPRE accesibles para cualquier usuario logueado (no dependen de permisos).
  // La Agenda de mensajes es transversal: enviar/ver/historial libre para todos.
  // Cambio de Clave: todo usuario debe poder cambiar su propia contraseña cuando quiera.
  // (Grupos y Temas quedan solo para administradores → adminOnly en el menú, no van acá.)
  const CLAVES_LIBRES = new Set(['agenda', 'agenda-crear', 'agenda-historial', 'cambio-clave'])

  const puedoVer = computed(() => (clave: string): boolean => {
    if (!usuario.value) return false

    // Ítems libres (Agenda): siempre permitidos
    if (CLAVES_LIBRES.has(clave)) return true

    // Administrador total: ve todo
    if (usuario.value.es_admin) return true

    // Sin restricciones (array vacío): ve todo
    const permisos = usuario.value.permisos ?? []
    if (permisos.length === 0) return true

    // Lista blanca: solo ve los ítems en su lista
    return permisos.includes(clave)
  })

  /**
   * Indica si el usuario actual es Administrador total (campo ES_ADMIN en la BD,
   * expuesto como es_admin por la API). NIVEL es el rol del usuario, no el flag de admin.
   * Útil para mostrar/ocultar secciones de la UI exclusivas de admin.
   */
  const esAdmin = computed(() => usuario.value?.es_admin === true)

  // ── Acciones ───────────────────────────────────────────────────────────

  /**
   * Guarda token y datos de usuario en el store y sessionStorage.
   */
  function guardarSesion(nuevoToken: string, nuevoUsuario: UsuarioAuth) {
    token.value   = nuevoToken
    usuario.value = nuevoUsuario
    sessionStorage.setItem('token', nuevoToken)
  }

  /**
   * Hace login con email o usuario (DATO1) + contraseña.
   * El backend detecta automáticamente si es email (contiene @) o DATO1.
   * El backend retorna token + usuario con permisos incluidos.
   */
  async function login(login: string, password: string) {
    const res = await authService.login(login, password)
    guardarSesion(res.data.token, res.data.usuario)
    setEmpresa(res.data.empresa)
    // Forzar que las alertas de arranque se muestren en cada nuevo inicio de sesión.
    sessionStorage.removeItem('alertas_vistas')
  }

  /**
   * Cierra la sesión: invalida el token en el servidor y limpia el store.
   */
  async function logout() {
    try { await authService.logout() } catch {}
    token.value   = null
    usuario.value = null
    sessionStorage.removeItem('token')
    sessionStorage.removeItem('alertas_vistas')
  }

  /**
   * Recarga los datos del usuario desde /api/auth/me.
   * Se llama al refrescar la página para restaurar la sesión.
   * Incluye permisos actualizados.
   */
  async function cargarUsuario() {
    if (!token.value) return
    try {
      const res = await authService.me()
      usuario.value = res.data
      setEmpresa(res.data.empresa)
    } catch {
      await logout()
    }
  }

  return {
    token,
    usuario,
    empresa,
    logoEmpresa,
    fondoLogin,
    cargarEmpresa,
    setEmpresa,
    estaAutenticado,
    esAdmin,
    puedoVer,
    guardarSesion,
    login,
    logout,
    cargarUsuario,
  }
})
