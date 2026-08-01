<!-- BuscadorGlobal.vue — Buscador de empleados accesible desde la topbar en todo el sistema.
     Reutiliza GET /empleados/buscar (misma fuente que la lupa EmpleadoBuscar) y al elegir
     navega a la ficha del empleado en el ABM (/dashboard/empleados?cod=PER_COD). -->
<template>
  <div ref="root" class="bg-wrap">
    <i class="bg-ico">🔎</i>
    <input
      v-model="q"
      class="bg-inp"
      placeholder="Buscar empleado…"
      autocomplete="off"
      @focus="abierto = true; onInput()"
      @input="onInput"
      @keydown.down.prevent="mover(1)"
      @keydown.up.prevent="mover(-1)"
      @keydown.enter.prevent="elegir(lista[sel >= 0 ? sel : 0])"
      @keydown.esc="cerrar"
    />
    <label class="bg-chk" title="Incluir empleados dados de baja">
      <input v-model="incluirBajas" type="checkbox" @change="cargar" /> bajas
    </label>

    <div v-if="abierto && mostrarDrop" class="bg-drop">
      <div v-if="cargando" class="bg-info">⟳ Buscando…</div>
      <div v-else-if="!lista.length" class="bg-info">Sin empleados</div>
      <template v-else>
        <div class="bg-cnt">{{ lista.length }} empleado{{ lista.length === 1 ? '' : 's' }}</div>
        <div
          v-for="(x, i) in lista"
          :key="x.cod"
          class="bg-row"
          :class="{ sel: sel === i, baja: !x.activo }"
          @mouseenter="sel = i"
          @mousedown.prevent="elegir(x)"
        >
          <span class="bg-av">{{ iniciales(x.nombre) }}</span>
          <div class="bg-tx">
            <div class="bg-nom">{{ x.nombre }}</div>
            <div class="bg-sub">Legajo {{ x.legajo }} · {{ x.documento }}</div>
          </div>
          <span class="bg-badge" :class="x.activo ? 'act' : 'baj'">{{ x.activo ? 'Activo' : 'Baja' }}</span>
        </div>
      </template>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/services/auth'
import { colapsarMenu } from '@/composables/sidebarUi'

interface Emp { cod: number; nombre: string; legajo: string; documento: string; sector: string; activo: boolean }

const router = useRouter()
const root = ref<HTMLElement | null>(null)
const q = ref('')
const lista = ref<Emp[]>([])
const cargando = ref(false)
const abierto = ref(false)
const incluirBajas = ref(false)
const sel = ref(-1)
let deb: ReturnType<typeof setTimeout> | null = null

const mostrarDrop = computed(() => cargando.value || lista.value.length > 0 || q.value.trim().length >= 2)

function iniciales (nombre: string): string {
  return (nombre || '?').split(/\s+/).slice(0, 2).map(p => p[0]).join('').toUpperCase()
}

async function cargar () {
  const term = q.value.trim()
  if (term.length < 2) { lista.value = []; cargando.value = false; return }
  cargando.value = true; sel.value = -1
  try {
    const { data } = await api.get('/empleados/buscar', { params: { q: term, activo: incluirBajas.value ? 0 : 1 } })
    lista.value = (data ?? []).slice(0, 8)
  } catch { lista.value = [] }
  finally { cargando.value = false }
}

function onInput () {
  abierto.value = true
  if (deb) clearTimeout(deb)
  deb = setTimeout(cargar, 250)
}

function mover (d: number) {
  if (!lista.value.length) return
  sel.value = (sel.value + d + lista.value.length) % lista.value.length
}

function cerrar () { abierto.value = false }

function elegir (x?: Emp) {
  if (!x) return
  cerrar()
  q.value = ''
  lista.value = []
  router.push({ path: '/dashboard/empleados', query: { cod: x.cod } })
  colapsarMenu()   // maximizar el ABM de Empleados: colapsar el panel del menú lateral
}

function onClickFuera (e: MouseEvent) {
  if (root.value && !root.value.contains(e.target as Node)) abierto.value = false
}
onMounted(() => document.addEventListener('mousedown', onClickFuera))
onBeforeUnmount(() => document.removeEventListener('mousedown', onClickFuera))
</script>

<style scoped>
.bg-wrap { position: relative; display: flex; align-items: center; gap: 6px; }
.bg-ico {
  position: absolute; left: 10px; top: 50%; transform: translateY(-50%);
  font-size: 0.85rem; font-style: normal; pointer-events: none; opacity: 0.7;
}
.bg-inp {
  width: 230px; height: 34px; padding: 0 8px 0 30px;
  border: 1px solid #e2e8f0; border-radius: 18px; outline: none;
  font-size: 0.84rem; color: #1e293b; background: #fff; transition: all 0.18s;
}
.bg-inp:focus { border-color: #40916c; box-shadow: 0 0 0 3px rgba(64, 145, 108, 0.15); width: 260px; }
.bg-chk { display: flex; align-items: center; gap: 3px; font-size: 0.72rem; color: #64748b; cursor: pointer; white-space: nowrap; }

.bg-drop {
  position: absolute; top: calc(100% + 6px); left: 0;
  width: 340px; max-width: 80vw; background: #fff;
  border: 1px solid #cbd5e1; border-radius: 10px; overflow: hidden;
  box-shadow: 0 12px 32px rgba(15, 23, 42, 0.18); z-index: 9700;
}
.bg-info { padding: 14px; text-align: center; color: #94a3b8; font-size: 0.82rem; }
.bg-cnt { padding: 6px 12px; font-size: 0.72rem; color: #94a3b8; border-bottom: 1px solid #f1f5f9; }
.bg-row { display: flex; align-items: center; gap: 10px; padding: 8px 12px; cursor: pointer; border-bottom: 1px solid #f1f5f9; }
.bg-row:last-child { border-bottom: none; }
.bg-row.sel { background: #eaf6ef; }
.bg-row.baja .bg-nom { color: #94a3b8; font-style: italic; }
.bg-av { width: 30px; height: 30px; border-radius: 50%; background: #1b4332; color: #fff; font-size: 0.66rem; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.bg-row.baja .bg-av { background: #9aa5b1; }
.bg-tx { flex: 1; min-width: 0; }
.bg-nom { font-size: 0.82rem; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.bg-sub { font-size: 0.74rem; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.bg-badge { font-size: 0.68rem; padding: 2px 8px; border-radius: 10px; flex-shrink: 0; }
.bg-badge.act { color: #166534; background: #dcfce7; }
.bg-badge.baj { color: #64748b; background: #f1f5f9; }

@media (max-width: 820px) {
  .bg-inp { width: 150px; } .bg-inp:focus { width: 170px; } .bg-chk { display: none; }
}
</style>
