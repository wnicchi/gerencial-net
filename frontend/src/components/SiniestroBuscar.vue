<!-- SiniestroBuscar.vue — Lupa de búsqueda de siniestros (buscar_siniestros.scx).
     Uso: <SiniestroBuscar v-if="show" @select="onSel" @close="show=false" /> -->
<template>
  <Teleport to="body">
    <div class="sb-ov" @click.self="$emit('close')">
      <div class="sb-md">
        <div class="sb-head">
          <span>🔎 Buscar Siniestros</span>
          <input v-model="filtro" class="sb-search" placeholder="Filtrar por nro, empleado o detalle…" />
          <button class="sb-x" @click="$emit('close')">✕</button>
        </div>
        <div class="sb-body">
          <div v-if="cargando" class="sb-info">⟳ Cargando…</div>
          <div v-else-if="!filtradas.length" class="sb-info">Sin siniestros.</div>
          <table v-else class="sb-tabla">
            <thead><tr>
              <th class="ord" @click="ordenar('nro')">NRO{{ flecha('nro') }}</th>
              <th>Tipo</th>
              <th class="ord" @click="ordenar('fecha')">Fecha{{ flecha('fecha') }}</th>
              <th class="ord" @click="ordenar('empleado')">Empleado{{ flecha('empleado') }}</th>
              <th class="ord" @click="ordenar('detalle')">Detalle{{ flecha('detalle') }}</th>
            </tr></thead>
            <tbody>
              <tr v-for="(x, i) in filtradas" :key="i" :class="{ cerrado: x.cerrado, sel: sel === i }"
                  @click="sel = i" @dblclick="elegir(x)">
                <td class="sb-nro">{{ x.nro }}</td><td>{{ x.tipo }}</td><td>{{ fmt(x.fecha) }}</td>
                <td>{{ x.empleado }}</td><td>{{ x.detalle }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="sb-foot">
          <span class="sb-ley"><span class="sb-chip cerrado"></span> Cerrado</span>
          <span style="flex:1"></span>
          <button class="sb-aceptar" :disabled="sel < 0" @click="elegir(filtradas[sel])">Aceptar</button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import api from '@/services/auth'

interface Sin { nro: number; tipo: string; fecha: string; interno: number; dominio: string; empleado: number; detalle: string; cerrado: boolean }
const emit = defineEmits<{ (e: 'select', nro: number): void; (e: 'close'): void }>()

const lista = ref<Sin[]>([]); const cargando = ref(false); const filtro = ref(''); const sel = ref(-1)
type Key = 'nro' | 'fecha' | 'empleado' | 'detalle'
const sortKey = ref<Key>('nro'); const sortDir = ref<1 | -1>(-1)

const fmt = (s: string) => s ? s.split('-').reverse().join('/') : ''
const ordenar = (k: Key) => { if (sortKey.value === k) sortDir.value = (sortDir.value === 1 ? -1 : 1) as 1 | -1; else { sortKey.value = k; sortDir.value = 1 } }
const flecha = (k: Key) => sortKey.value === k ? (sortDir.value === 1 ? ' ▲' : ' ▼') : ''

const filtradas = computed(() => {
  const q = filtro.value.trim().toLowerCase()
  let base = lista.value
  if (q) base = base.filter(x => `${x.nro} ${x.empleado} ${x.detalle}`.toLowerCase().includes(q))
  const k = sortKey.value, dir = sortDir.value
  return [...base].sort((a, b) => (k === 'nro' || k === 'empleado')
    ? ((a[k] as number) - (b[k] as number)) * dir
    : String(a[k]).localeCompare(String(b[k]), 'es', { numeric: true }) * dir)
})
const elegir = (x: Sin) => { if (x) emit('select', x.nro) }

onMounted(async () => {
  cargando.value = true
  try { lista.value = (await api.get('/siniestros/buscar')).data ?? [] } catch { lista.value = [] }
  finally { cargando.value = false }
})
</script>

<style scoped>
.sb-ov { position:fixed; inset:0; background:rgba(15,23,42,.55); z-index:9500; display:flex; align-items:flex-start; justify-content:center; padding:50px 18px; }
.sb-md { background:#fff; border-radius:12px; width:min(760px,97vw); max-height:82vh; display:flex; flex-direction:column; overflow:hidden; box-shadow:0 18px 50px rgba(0,0,0,.3); }
.sb-head { display:flex; gap:10px; align-items:center; padding:12px 14px; background:#394959; color:#fff; }
.sb-head > span { font-weight:700; font-size:14px; }
.sb-search { flex:1; border:none; border-radius:6px; padding:8px 10px; font-size:13px; color:#1e293b; outline:none; }
.sb-x { background:rgba(255,255,255,.85); border:none; border-radius:6px; width:32px; height:32px; cursor:pointer; font-weight:700; color:#334155; }
.sb-body { overflow:auto; flex:1; }
.sb-info { padding:24px; text-align:center; color:#94a3b8; }
.sb-tabla { width:100%; border-collapse:collapse; font-size:12.5px; }
.sb-tabla th { position:sticky; top:0; background:#c8c8c8; color:#1e293b; padding:6px 10px; text-align:left; font-size:12px; }
.sb-tabla th.ord { cursor:pointer; user-select:none; } .sb-tabla th.ord:hover { background:#b8b8b8; }
.sb-tabla td { padding:5px 10px; color:#1e293b; cursor:pointer; border-bottom:1px solid #f1f5f9; }
.sb-tabla tbody tr:hover td { background:#fff7cc; }
.sb-tabla tbody tr.sel td { background:#fde047; }
.sb-tabla tbody tr.cerrado td { background:#ec4899; color:#fff; } .sb-tabla tbody tr.cerrado.sel td { background:#db2777; }
.sb-nro { font-weight:700; color:#1d4ed8; } .sb-tabla tbody tr.cerrado .sb-nro { color:#fff; }
.sb-foot { display:flex; align-items:center; gap:10px; padding:10px 14px; border-top:1px solid #e2e8f0; }
.sb-ley { display:flex; align-items:center; gap:6px; font-size:12px; color:#64748b; } .sb-chip { width:13px; height:13px; border-radius:3px; display:inline-block; } .sb-chip.cerrado { background:#ec4899; }
.sb-aceptar { background:#1b4332; color:#fff; border:none; border-radius:7px; padding:9px 20px; cursor:pointer; font-weight:800; font-size:13px; } .sb-aceptar:disabled { opacity:.5; }
</style>
