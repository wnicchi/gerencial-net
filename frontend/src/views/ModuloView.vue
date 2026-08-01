<template>
  <div class="modulo-wrapper">
    <div class="modulo-card">
      <div class="modulo-icono">{{ icono }}</div>
      <h2>{{ titulo }}</h2>
      <div class="badge-dev">🚧 En desarrollo</div>
      <p class="desc">
        Este módulo está siendo desarrollado.<br />
        Pronto estará disponible.
      </p>
      <div class="ruta-info">
        <code>{{ $route.path }}</code>
      </div>
      <button class="btn-volver" @click="$router.push('/dashboard')">
        ← Volver al inicio
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { menuConfig } from '@/config/menu'
import type { MenuItem } from '@/config/menu'

const route = useRoute()

// Busca el ítem del menú que coincide con la ruta actual
function buscarItem(items: MenuItem[], ruta: string): MenuItem | null {
  for (const item of items) {
    if (item.ruta === ruta) return item
    if (item.hijos) {
      const found = buscarItem(item.hijos, ruta)
      if (found) return found
    }
  }
  return null
}

const itemActual = computed(() => buscarItem(menuConfig, route.path))

const titulo = computed(() => itemActual.value?.label ?? route.params.modulo ?? 'Módulo')
const icono  = computed(() => itemActual.value?.icono ?? '📄')
</script>

<style scoped>
.modulo-wrapper {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 100%;
  padding: 2rem;
}

.modulo-card {
  background: rgba(255, 255, 255, 0.92);
  backdrop-filter: blur(8px);
  border-radius: 16px;
  padding: 2.5rem 3rem;
  text-align: center;
  max-width: 480px;
  width: 100%;
  box-shadow: 0 8px 40px rgba(0, 0, 0, 0.18);
}

.modulo-icono {
  font-size: 3.5rem;
  margin-bottom: 1rem;
  line-height: 1;
}

h2 {
  color: #1e3a5f;
  font-size: 1.4rem;
  margin: 0 0 0.8rem;
}

.badge-dev {
  display: inline-block;
  background: #fef3c7;
  color: #92400e;
  border: 1px solid #fde68a;
  border-radius: 20px;
  padding: 0.3rem 1rem;
  font-size: 0.82rem;
  font-weight: 600;
  margin-bottom: 1rem;
}

.desc {
  color: #666;
  font-size: 0.9rem;
  line-height: 1.6;
  margin-bottom: 1.2rem;
}

.ruta-info {
  background: #f3f4f6;
  border-radius: 6px;
  padding: 0.4rem 0.8rem;
  margin-bottom: 1.5rem;
}
.ruta-info code {
  font-size: 0.78rem;
  color: #4b5563;
}

.btn-volver {
  background: #1e3a5f;
  color: #fff;
  border: none;
  padding: 0.6rem 1.4rem;
  border-radius: 8px;
  font-size: 0.9rem;
  cursor: pointer;
  transition: background 0.2s;
}
.btn-volver:hover { background: #2d6a9f; }
</style>
