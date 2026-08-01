<template>
  <div class="dashboard-layout">

    <!-- ── Sidebar (dos paneles) ── -->
    <SidebarMenu />

    <!-- ── Área de contenido ── -->
    <div class="content-area">
      <!-- Topbar -->
      <header class="topbar">
        <div class="topbar-left">
          <h1 class="sistema-nombre">Tablero Gerencial</h1>
          <span class="breadcrumb" v-if="moduloActual">› {{ moduloActual }}</span>
        </div>
        <div class="topbar-right">
          <button class="ayuda-btn" title="Centro de ayuda (Ctrl+K)" @click="ayudaOpen = true">
            <span class="ayuda-ico">❓</span>
          </button>
          <span class="user-chip">
            <span class="user-chip-avatar">{{ iniciales }}</span>
            {{ nombreUsuario }}
          </span>
        </div>
      </header>

      <!-- Contenido del módulo (RouterView nested) -->
      <main class="main-content">
        <RouterView />
      </main>
    </div>

    <!-- Centro de ayuda (buscador de módulos, Ctrl+K) -->
    <CentroAyuda v-model="ayudaOpen" />

  </div>
</template>

<script setup lang="ts">
import { computed, ref, onMounted, onBeforeUnmount } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { menuConfig } from '@/config/menu'
import type { MenuItem } from '@/config/menu'
import SidebarMenu from '@/components/SidebarMenu.vue'
import CentroAyuda from '@/components/CentroAyuda.vue'

const auth   = useAuthStore()
const route  = useRoute()

// ── Centro de ayuda (Ctrl+K) ──
const ayudaOpen = ref(false)
function onKeydown (e: KeyboardEvent) {
  if ((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K')) {
    e.preventDefault()
    ayudaOpen.value = !ayudaOpen.value
  }
}
onMounted(() => window.addEventListener('keydown', onKeydown))
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown))

const nombreUsuario = computed(() =>
  auth.usuario?.NOMBRE || auth.usuario?.DATO1 || 'Usuario'
)
const iniciales = computed(() => {
  const nombre = auth.usuario?.NOMBRE || auth.usuario?.DATO1 || 'U'
  return nombre.split(' ').slice(0, 2).map((p: string) => p[0]).join('').toUpperCase()
})

// Busca la etiqueta del módulo activo en el menú
function buscarLabel(items: MenuItem[], ruta: string): string | null {
  for (const item of items) {
    if (item.ruta === ruta) return item.label
    if (item.hijos) {
      const found = buscarLabel(item.hijos, ruta)
      if (found) return found
    }
  }
  return null
}
const moduloActual = computed(() =>
  route.path !== '/dashboard' ? buscarLabel(menuConfig, route.path) : null
)
</script>

<style scoped>
/* ── Layout base ── */
.dashboard-layout {
  display: flex;
  height: 100vh;
  overflow: hidden;
}

/* ── Área derecha ── */
.content-area {
  flex: 1;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  position: relative;
  background: #f9fafb;
}

/* ── Topbar ── */
.topbar {
  position: relative;
  z-index: 10;
  height: 56px;
  background: rgba(255, 255, 255, 0.93);
  backdrop-filter: blur(6px);
  border-bottom: 1px solid #dde6f0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 1.5rem;
  flex-shrink: 0;
  box-shadow: 0 1px 6px rgba(0, 0, 0, 0.07);
}

.topbar-left {
  display: flex;
  align-items: baseline;
  gap: 0.6rem;
}
.sistema-nombre {
  font-size: 1.05rem;
  font-weight: 700;
  color: #1b4332;
  margin: 0;
}
.breadcrumb {
  color: #40916c;
  font-size: 0.88rem;
}

.topbar-right {
  display: flex;
  align-items: center;
  gap: 0.6rem;
}

/* ── Botón de centro de ayuda ── */
.ayuda-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 38px;
  height: 38px;
  border: 1px solid #e2e8f0;
  background: #fff;
  border-radius: 50%;
  cursor: pointer;
  transition: all 0.18s;
}
.ayuda-btn:hover { background: #f0faf4; border-color: #c3e6cb; transform: translateY(-1px); }
.ayuda-ico { font-size: 1.05rem; line-height: 1; }

.user-chip {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  background: #f0faf4;
  border: 1px solid #c3e6cb;
  border-radius: 20px;
  padding: 0.28rem 0.8rem 0.28rem 0.35rem;
  font-size: 0.84rem;
  color: #1b4332;
  font-weight: 500;
}
.user-chip-avatar {
  width: 26px;
  height: 26px;
  background: #1b4332;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-size: 0.68rem;
  font-weight: 700;
}

/* ── Contenido principal ── */
.main-content {
  position: relative;
  z-index: 5;
  flex: 1;
  overflow-y: auto;
  overflow-x: hidden;
}
</style>
