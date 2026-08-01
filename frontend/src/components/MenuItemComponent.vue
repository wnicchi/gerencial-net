<template>
  <!-- Separador visual entre grupos -->
  <div v-if="item.separador" class="sep"></div>

  <!--
    Ítem con hijos (grupo expandible / acordeón).
    Solo se muestra si al menos uno de sus hijos es visible con los permisos actuales.
  -->
  <div v-else-if="item.hijos && item.hijos.length && tieneHijosVisibles" class="grupo">
    <button
      class="item-btn item-parent"
      :class="{ activo: tieneHijoActivo, expandido: abierto }"
      @click="abierto = !abierto"
    >
      <span class="icono">{{ item.icono }}</span>
      <!-- title: al truncarse con "…", el navegador muestra el texto completo al pasar el mouse -->
      <span class="label" :title="item.label">{{ item.label }}</span>
      <span class="flecha" :class="{ girada: abierto }">›</span>
    </button>

    <div class="sub-lista" :class="{ visible: abierto }">
      <!--
        Recursivo: cada hijo se renderiza con el mismo componente.
        La lógica de permisos se aplica en cada nivel de la jerarquía.
      -->
      <MenuItemComponent
        v-for="hijo in hijosDepurados"
        :key="hijo.id"
        :item="hijo"
        :nivel="nivel + 1"
      />
    </div>
  </div>

  <!--
    Ítem hoja (enlace a una ruta).
    Solo se muestra si el usuario tiene permiso para ver esta clave.
  -->
  <router-link
    v-else-if="!item.hijos && esVisible"
    :to="item.ruta || '#'"
    custom
    v-slot="{ navigate, isActive }"
  >
    <div
      class="item-btn item-hoja"
      :class="{ activo: isActive }"
      :style="{ paddingLeft: `${0.7 + nivel * 0.9}rem` }"
      @click="navigate"
    >
      <span class="icono">{{ item.icono }}</span>
      <span class="label" :title="item.label">{{ item.label }}</span>
    </div>
  </router-link>
</template>

<script setup lang="ts">
/**
 * MenuItemComponent.vue
 *
 * Componente recursivo que renderiza un ítem del menú lateral (Panel 2).
 *
 * Tipos de ítem soportados:
 *   1. Separador visual (item.separador = true)
 *   2. Grupo con hijos (item.hijos) — acordeón expandible
 *   3. Ítem hoja (item.ruta) — enlace de navegación
 *
 * Filtrado por permisos:
 *   Usa el store de autenticación para verificar si el usuario puede ver
 *   el ítem. Un grupo (padre) solo se muestra si al menos un hijo es visible.
 *   Un ítem hoja solo se muestra si su 'id' está en los permisos del usuario
 *   (o si el usuario no tiene restricciones).
 *
 * @component MenuItemComponent
 */
import { ref, computed } from 'vue'
import { useRoute } from 'vue-router'
import type { MenuItem } from '@/config/menu'
import { useAuthStore } from '@/stores/auth'
import { depurarMenu } from '@/config/menuVisible'
import MenuItemComponent from './MenuItemComponent.vue'

const props = defineProps<{ item: MenuItem; nivel: number }>()

const route = useRoute()
const auth  = useAuthStore()

/** Estado abierto/cerrado del acordeón para ítems con hijos */
const abierto = ref(false)

// ── Lógica de permisos ────────────────────────────────────────────────────

/**
 * Determina si este ítem hoja es visible para el usuario actual.
 * Para ítems con hijos, se usa tieneHijosVisibles en su lugar.
 */
const esVisible = computed(() => {
  // Separadores siempre visibles (su visibilidad depende del grupo)
  if (props.item.separador) return true
  // Ítems solo para administradores
  if (props.item.adminOnly && !auth.esAdmin) return false
  // Si no tiene 'id', es un ítem sin permiso configurado → visible
  if (!props.item.id) return true
  // Verificar con el store
  return auth.puedoVer(props.item.id)
})

/**
 * Comprueba recursivamente si algún hijo del grupo es visible.
 * Un grupo padre solo se renderiza si tiene al menos un ítem accesible.
 */
function algunHijoVisible(items: MenuItem[]): boolean {
  return items.some(hijo => {
    // Separadores no cuentan como "ítems visibles"
    if (hijo.separador) return false
    // Si tiene hijos, verificar recursivamente
    if (hijo.hijos && hijo.hijos.length) return algunHijoVisible(hijo.hijos)
    // Ítems solo para administradores
    if (hijo.adminOnly && !auth.esAdmin) return false
    // Ítem hoja: verificar permiso
    if (!hijo.id) return true
    return auth.puedoVer(hijo.id)
  })
}

const tieneHijosVisibles = computed(() =>
  props.item.hijos ? algunHijoVisible(props.item.hijos) : false
)

/** Hijos ya depurados: sin ítems ocultos ni separadores colgados. */
const hijosDepurados = computed(() =>
  props.item.hijos ? depurarMenu(props.item.hijos, auth.puedoVer, auth.esAdmin) : []
)

// ── Lógica de estado activo ───────────────────────────────────────────────

/**
 * Verifica recursivamente si algún hijo del ítem coincide con la ruta actual.
 * Sirve para resaltar el grupo padre cuando un hijo está activo.
 */
function hijoActivo(items: MenuItem[]): boolean {
  return items.some(h => {
    if (h.ruta && route.path === h.ruta) return true
    if (h.hijos) return hijoActivo(h.hijos)
    return false
  })
}

/** true si algún descendiente está activo en la ruta actual */
const tieneHijoActivo = computed(() =>
  props.item.hijos ? hijoActivo(props.item.hijos) : false
)

// Abrir automáticamente el acordeón si un hijo está activo al montar
if (props.item.hijos && hijoActivo(props.item.hijos)) {
  abierto.value = true
}
</script>

<style scoped>
/* ── Separador visual ─────────────────────────────────────────── */
.sep {
  height: 1px;
  background: #e5e7eb;
  margin: 0.25rem 0.5rem;
}

/* ── Ítem base (botón y enlace) ───────────────────────────────── */
.item-btn {
  display: flex;
  align-items: center;
  gap: 0.55rem;
  width: 100%;
  padding: 0.45rem 0.7rem;
  border: none;
  background: transparent;
  color: #374151;
  font-size: 0.83rem;
  text-align: left;
  cursor: pointer;
  border-radius: 6px;
  transition: background 0.15s, color 0.15s;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  text-decoration: none;
}
.item-btn:hover { background: #f0faf4; color: #1b4332; }
.item-btn.activo { background: #d1fae5; color: #1b4332; font-weight: 600; }
.item-btn.item-parent.activo { background: rgba(209,250,229,0.6); color: #1b4332; }

/* ── Iconos y labels ──────────────────────────────────────────── */
.icono { font-size: 0.9rem; flex-shrink: 0; min-width: 1.1rem; text-align: center; }
.label { flex: 1; overflow: hidden; text-overflow: ellipsis; line-height: 1.3; }

/* ── Flecha del acordeón ──────────────────────────────────────── */
.flecha { font-size: 1rem; color: #9ca3af; transition: transform 0.22s; flex-shrink: 0; }
.flecha.girada { transform: rotate(90deg); }

/* ── Sub-lista acordeón ───────────────────────────────────────── */
.sub-lista { max-height: 0; overflow: hidden; transition: max-height 0.3s ease; }
.sub-lista.visible { max-height: 3000px; }
</style>
