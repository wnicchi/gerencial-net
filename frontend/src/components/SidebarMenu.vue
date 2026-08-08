<template>
  <div class="sidebar-dos-paneles" :class="{ panel2visible: seccionActiva !== null && !panelOculto }">

    <!-- ══════════════ PANEL 1 — Iconos ══════════════ -->
    <div class="panel1">

      <!-- Logo -->
      <div class="p1-logo">
        <img :src="auth.logoEmpresa" alt="Logo" class="logo-icon" />
      </div>

      <!-- Items principales -->
      <nav class="p1-nav">
        <button
          v-for="item in seccionesVisibles"
          :key="item.id"
          class="p1-item"
          :class="{
            activo: seccionActiva?.id === item.id,
            'tiene-hijos': item.hijos && item.hijos.length,
            'ruta-activa': !item.hijos && isRutaActiva(item.ruta)
          }"
          :title="item.label"
          @click="seleccionarSeccion(item)"
        >
          <span class="p1-icono">{{ item.icono }}</span>
          <span class="p1-punto" v-if="tieneHijoActivo(item)"></span>
        </button>
      </nav>

      <!-- Footer P1: avatar + toggle -->
      <div class="p1-footer">
        <button class="p1-item p1-toggle" :title="panelOculto ? 'Mostrar menú' : 'Ocultar menú'" @click="togglePanel2">
          <span class="p1-icono">{{ panelOculto ? '→' : '←' }}</span>
        </button>
        <div class="p1-avatar" :title="nombreUsuario">{{ iniciales }}</div>
        <button class="p1-item p1-logout" title="Cerrar sesión" @click="cerrarSesion">
          <span class="p1-icono">⏏️</span>
        </button>
      </div>

    </div>

    <!-- ══════════════ PANEL 2 — Sub-ítems ══════════════ -->
    <transition name="panel2">
      <div class="panel2" v-if="seccionActiva && !panelOculto">

        <!-- Cabecera sección -->
        <div class="p2-header">
          <span class="p2-icono">{{ seccionActiva.icono }}</span>
          <span class="p2-titulo" :title="seccionActiva.label">{{ seccionActiva.label }}</span>
          <button class="p2-pin" :class="{ activo: fijado }" :title="fijado ? 'Menú fijo — clic para soltar' : 'Fijar menú abierto'" @click="togglePin">📌</button>
          <button class="p2-cerrar" title="Cerrar panel" @click="cerrarPanel2">✕</button>
        </div>

        <!-- Lista de ítems (ya depurada: sin ocultos ni separadores colgados) -->
        <nav class="p2-nav">
          <MenuItemComponent
            v-for="hijo in itemsPanel2"
            :key="hijo.id"
            :item="hijo"
            :nivel="0"
          />
        </nav>

        <!-- Footer P2: usuario + salir -->
        <div class="p2-footer">
          <div class="p2-user">
            <div class="p2-user-avatar">{{ iniciales }}</div>
            <div class="p2-user-info">
              <div class="p2-user-nombre" :title="nombreUsuario">{{ nombreUsuario }}</div>
              <div class="p2-user-empresa" :title="empresaUsuario">{{ empresaUsuario }}</div>
            </div>
          </div>
          <button class="p2-logout" @click="cerrarSesion">
            ⏏️ Cerrar sesión
          </button>
        </div>

      </div>
    </transition>

  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { menuConfig } from '@/config/menu'
import type { MenuItem } from '@/config/menu'
import { depurarMenu } from '@/config/menuVisible'
import MenuItemComponent from './MenuItemComponent.vue'
import { pedidoColapsarMenu } from '@/composables/sidebarUi'

const auth   = useAuthStore()
const route  = useRoute()
const router = useRouter()

// ── Estado ──
const seccionActiva = ref<MenuItem | null>(null)
const panelOculto   = ref(false)
// Menú fijo: si está activo, el Panel 2 NO se auto-colapsa al entrar a módulos.
// Se recuerda entre sesiones.
const fijado        = ref(localStorage.getItem('gerencial_menu_fijado') === '1')

// ── Filtrado de secciones por permisos ──────────────────────────────────

/**
 * Verifica recursivamente si algún ítem del árbol es visible para el usuario.
 * Un ítem es visible si: es admin, no tiene restricciones, o su clave está en permisos.
 */
function algunItemVisible(items: MenuItem[]): boolean {
  return items.some(item => {
    if (item.separador) return false
    if (item.hijos && item.hijos.length) return algunItemVisible(item.hijos)
    if (item.adminOnly && !auth.esAdmin) return false
    if (!item.id) return true
    return auth.puedoVer(item.id)
  })
}

/**
 * Lista de secciones del Panel 1 visibles para el usuario actual.
 * Una sección se oculta si ninguno de sus hijos es accesible.
 */
const seccionesVisibles = computed(() =>
  menuConfig.filter(seccion => {
    if (!seccion.hijos || seccion.hijos.length === 0) {
      // sección sin hijos (ítem directo): verificar su propia clave
      if (!seccion.id) return true
      return auth.puedoVer(seccion.id)
    }
    // sección con hijos: mostrar si al menos un hijo es accesible
    return algunItemVisible(seccion.hijos)
  })
)

/** Ítems del panel 2 de la sección activa, ya depurados (sin ocultos ni separadores colgados). */
const itemsPanel2 = computed(() =>
  seccionActiva.value?.hijos
    ? depurarMenu(seccionActiva.value.hijos, auth.puedoVer, auth.esAdmin)
    : []
)

// ── Usuario ──
const nombreUsuario  = computed(() => auth.usuario?.NOMBRE || auth.usuario?.DATO1 || 'Usuario')
const empresaUsuario = computed(() => auth.usuario?.EMPRESA || 'Sistema RRHH')
const iniciales      = computed(() => {
  const n = auth.usuario?.NOMBRE || auth.usuario?.DATO1 || 'U'
  return n.split(' ').slice(0, 2).map((p: string) => p[0]).join('').toUpperCase()
})

// ── Helpers ──
function isRutaActiva(ruta?: string) {
  return !!ruta && route.path === ruta
}

/** Un módulo es cualquier ruta que cuelga de /dashboard/ (el Resumen es /dashboard). */
function esRutaDeModulo(ruta: string) {
  return /^\/dashboard\/.+/.test(ruta)
}

function hijoActivoEn(items: MenuItem[]): boolean {
  return items.some(h => {
    if (h.ruta && route.path === h.ruta) return true
    if (h.hijos) return hijoActivoEn(h.hijos)
    return false
  })
}

function tieneHijoActivo(item: MenuItem): boolean {
  return item.hijos ? hijoActivoEn(item.hijos) : false
}

// Encuentra la sección raíz de la ruta activa (solo entre las visibles)
function encontrarSeccionDeRuta(ruta: string): MenuItem | null {
  for (const item of seccionesVisibles.value) {
    if (item.ruta === ruta) return item
    if (item.hijos && hijoActivoEn(item.hijos)) return item
  }
  return null
}

// ── Al cargar: abrir sección activa. Si se entra directo a un módulo (ruta con
//    sub-path, no el Resumen), arrancar con el panel colapsado para ganar ancho. ──
const seccionInicial = encontrarSeccionDeRuta(route.path)
if (seccionInicial) seccionActiva.value = seccionInicial
if (esRutaDeModulo(route.path) && !fijado.value) panelOculto.value = true

// ── Cuando cambia la ruta, actualizar sección activa y, al entrar a un módulo,
//    colapsar el Panel 2 automáticamente (más ancho para el contenido). El rail
//    de íconos queda para reabrirlo cuando el usuario quiera. ──
watch(() => route.path, (nuevaRuta) => {
  const s = encontrarSeccionDeRuta(nuevaRuta)
  if (s) seccionActiva.value = s
  if (esRutaDeModulo(nuevaRuta) && !fijado.value) panelOculto.value = true
})

// ── Pedido externo de colapsar el panel (ej: buscador global de empleados) ──
watch(pedidoColapsarMenu, () => { panelOculto.value = true })

// ── Acciones ──
function seleccionarSeccion(item: MenuItem) {
  if (!item.hijos || item.hijos.length === 0) {
    // ítem directo sin hijos → navegar
    if (item.ruta) router.push(item.ruta)
    seccionActiva.value = null
    return
  }

  if (seccionActiva.value?.id === item.id) {
    // click en la misma sección → toggle panel2
    panelOculto.value = !panelOculto.value
  } else {
    seccionActiva.value = item
    panelOculto.value   = false
  }
}

function cerrarPanel2() {
  panelOculto.value = true
}

function togglePanel2() {
  panelOculto.value = !panelOculto.value
}

/** Fija/suelta el menú. Al fijar, lo deja abierto; se recuerda entre sesiones. */
function togglePin() {
  fijado.value = !fijado.value
  localStorage.setItem('gerencial_menu_fijado', fijado.value ? '1' : '0')
  if (fijado.value) panelOculto.value = false
}

async function cerrarSesion() {
  await auth.logout()
  router.push('/login')
}
</script>

<style scoped>
/* ══════════════════════════════════
   Contenedor dos paneles
══════════════════════════════════ */
.sidebar-dos-paneles {
  display: flex;
  height: 100vh;
  flex-shrink: 0;
}

/* ══════════════════════════════════
   PANEL 1 — iconos
══════════════════════════════════ */
.panel1 {
  width: 62px;
  min-width: 62px;
  background: #ffffff;
  display: flex;
  flex-direction: column;
  border-right: 1px solid #e5e7eb;
  z-index: 20;
}

.p1-logo {
  height: 62px;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 5px;
  border-bottom: 1px solid #e5e7eb;
  flex-shrink: 0;
  background: #fff;
}
.logo-icon {
  width: 100%;
  height: 100%;
  object-fit: contain;
  object-position: center;
  border-radius: 4px;
}

.p1-nav {
  flex: 1;
  overflow-y: auto;
  overflow-x: hidden;
  padding: 0.4rem 0.3rem;
  display: flex;
  flex-direction: column;
  gap: 2px;
  scrollbar-width: none;
}
.p1-nav::-webkit-scrollbar { display: none; }

.p1-item {
  position: relative;
  width: 46px;
  height: 46px;
  background: transparent;
  border: none;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  color: #6b7280;
  transition: background 0.15s, color 0.15s, transform 0.1s;
  flex-shrink: 0;
}
.p1-item:hover {
  background: #f0faf4;
  color: #1b4332;
  transform: scale(1.05);
}
.p1-item.activo {
  background: #d1fae5;
  color: #1b4332;
}
.p1-item.ruta-activa {
  background: #d1fae5;
  color: #1b4332;
}

.p1-icono { font-size: 1.25rem; line-height: 1; }

/* Punto indicador de hijo activo */
.p1-punto {
  position: absolute;
  top: 6px;
  right: 6px;
  width: 7px;
  height: 7px;
  background: #16a34a;
  border-radius: 50%;
  border: 1px solid #fff;
}

.p1-footer {
  padding: 0.4rem 0.3rem;
  border-top: 1px solid #e5e7eb;
  background: #ffffff;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.35rem;
  flex-shrink: 0;
}

.p1-toggle { color: #6b7280; }
.p1-toggle:hover { color: #1b4332; background: #f0faf4 !important; }

.p1-logout { color: #b91c1c; }
.p1-logout:hover { background: rgba(220,38,38,0.14) !important; }

.p1-avatar {
  width: 34px;
  height: 34px;
  background: #1b4332;
  border: 2px solid #40916c;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #ffffff;
  font-size: 0.72rem;
  font-weight: 700;
  cursor: default;
}

/* ══════════════════════════════════
   PANEL 2 — sub-ítems
══════════════════════════════════ */
.panel2 {
  width: 220px;
  min-width: 220px;
  background: #ffffff;
  display: flex;
  flex-direction: column;
  border-right: 1px solid #e5e7eb;
  overflow: hidden;
}

.p2-header {
  height: 62px;
  display: flex;
  align-items: center;
  gap: 0.6rem;
  padding: 0 0.75rem;
  border-bottom: 1px solid #e5e7eb;
  flex-shrink: 0;
  background: #ffffff;
}
.p2-icono { font-size: 1.15rem; flex-shrink: 0; color: #1b4332; }
.p2-titulo {
  flex: 1;
  color: #1b4332;
  font-size: 0.88rem;
  font-weight: 700;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  letter-spacing: 0.01em;
}
.p2-cerrar {
  background: transparent;
  border: none;
  color: #6b7280;
  font-size: 0.8rem;
  cursor: pointer;
  padding: 0.2rem 0.3rem;
  border-radius: 4px;
  transition: color 0.15s, background 0.15s;
  flex-shrink: 0;
}
.p2-cerrar:hover { color: #1b4332; background: #f0faf4; }

.p2-pin {
  background: transparent;
  border: none;
  font-size: 0.85rem;
  cursor: pointer;
  padding: 0.2rem 0.3rem;
  border-radius: 4px;
  opacity: 0.35;
  filter: grayscale(1);
  transition: opacity 0.15s, background 0.15s, filter 0.15s;
  flex-shrink: 0;
}
.p2-pin:hover { opacity: 0.7; background: #f0faf4; }
.p2-pin.activo { opacity: 1; filter: none; background: #d1fae5; }

.p2-nav {
  flex: 1;
  overflow-y: auto;
  overflow-x: hidden;
  padding: 0.5rem 0.35rem;
  scrollbar-width: thin;
  scrollbar-color: rgba(255,255,255,0.12) transparent;
}
.p2-nav::-webkit-scrollbar { width: 3px; }
.p2-nav::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.12); border-radius: 2px; }

.p2-footer {
  border-top: 1px solid #e5e7eb;
  padding: 0.6rem 0.5rem;
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  background: #ffffff;
}
.p2-user {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  overflow: hidden;
}
.p2-user-avatar {
  width: 30px;
  height: 30px;
  background: #1b4332;
  border: 1.5px solid #40916c;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-size: 0.66rem;
  font-weight: 700;
  flex-shrink: 0;
}
.p2-user-info { overflow: hidden; }
.p2-user-nombre { color: #1e293b; font-size: 0.78rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.p2-user-empresa { color: #6b7280; font-size: 0.68rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.p2-logout {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.4rem;
  width: 100%;
  padding: 0.38rem;
  background: rgba(220, 38, 38, 0.08);
  border: 1px solid rgba(220, 38, 38, 0.25);
  border-radius: 6px;
  color: #b91c1c;
  font-size: 0.78rem;
  cursor: pointer;
  transition: background 0.2s;
}
.p2-logout:hover { background: rgba(220,38,38,0.18); color: #991b1b; }

/* ══════════════════════════════════
   Animación entrada/salida Panel 2
══════════════════════════════════ */
.panel2-enter-active,
.panel2-leave-active {
  transition: width 0.25s ease, opacity 0.22s ease;
  overflow: hidden;
}
.panel2-enter-from,
.panel2-leave-to {
  width: 0 !important;
  min-width: 0 !important;
  opacity: 0;
}
.panel2-enter-to,
.panel2-leave-from {
  width: 220px;
  opacity: 1;
}
</style>
