<!-- CelularBuscar.vue — Lupa de búsqueda de equipos celulares (buscar_celular.scx).
     Uso: <CelularBuscar v-if="show" @select="onSel" @close="show=false" /> -->
<template>
  <Teleport to="body">
    <div class="cb-ov" @click.self="$emit('close')">
      <div class="cb-md">
        <div class="cb-head">
          <span>📲 Buscar Celulares</span>
          <input v-model="filtro" class="cb-search" placeholder="Filtrar por marca / modelo / color / IMEI…" />
          <label class="cb-chk"><input v-model="soloActivos" type="checkbox" /> Ver sólo equipos activos</label>
          <button class="cb-x" @click="$emit('close')">✕</button>
        </div>
        <div class="cb-body">
          <div v-if="cargando" class="cb-info">⟳ Cargando…</div>
          <div v-else-if="!filtradas.length" class="cb-info">Sin equipos.</div>
          <table v-else class="cb-tabla">
            <thead><tr>
              <th class="ord" @click="ordenar('cod')">Cod.{{ flecha('cod') }}</th>
              <th class="ord" @click="ordenar('imei')">IMEI{{ flecha('imei') }}</th>
              <th class="ord" @click="ordenar('marca')">Marca{{ flecha('marca') }}</th>
              <th class="ord" @click="ordenar('modelo')">Modelo{{ flecha('modelo') }}</th>
              <th class="ord" @click="ordenar('color')">Color{{ flecha('color') }}</th>
              <th class="ord" @click="ordenar('pantalla')">Pulg.{{ flecha('pantalla') }}</th>
              <th>Sistema Operativo</th><th>Carg.</th><th>Auric.</th><th>Cable</th>
            </tr></thead>
            <tbody>
              <tr v-for="(x, i) in filtradas" :key="x.cod" :class="{ baja: x.baja, sel: sel === i }"
                  @click="sel = i" @dblclick="elegir(x)">
                <td class="cb-cod">{{ x.cod }}</td><td>{{ x.imei }}</td><td>{{ x.marca }}</td>
                <td>{{ x.modelo }}</td><td>{{ x.color }}</td><td class="c">{{ x.pantalla }}</td>
                <td>{{ x.sistema }}</td>
                <td class="c">{{ x.cargador ? 'SI' : 'NO' }}</td><td class="c">{{ x.auricular ? 'SI' : 'NO' }}</td><td class="c">{{ x.cableusb ? 'SI' : 'NO' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="cb-foot">
          <span class="cb-ley"><span class="cb-chip baja"></span> Celular dado de baja</span>
          <span style="flex:1"></span>
          <button class="cb-aceptar" :disabled="sel < 0" @click="elegir(filtradas[sel])">Aceptar</button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import api from '@/services/auth'

interface Eq { cod: number; imei: string; marca: string; modelo: string; color: string; pantalla: number; sistema: string; cargador: boolean; auricular: boolean; cableusb: boolean; baja: boolean }
const emit = defineEmits<{ (e: 'select', cod: number): void; (e: 'close'): void }>()

const lista = ref<Eq[]>([]); const cargando = ref(false); const filtro = ref(''); const soloActivos = ref(false); const sel = ref(-1)
type Key = 'cod' | 'imei' | 'marca' | 'modelo' | 'color' | 'pantalla'
const sortKey = ref<Key>('cod'); const sortDir = ref<1 | -1>(1)

const ordenar = (k: Key) => { if (sortKey.value === k) sortDir.value = (sortDir.value === 1 ? -1 : 1) as 1 | -1; else { sortKey.value = k; sortDir.value = 1 } }
const flecha = (k: Key) => sortKey.value === k ? (sortDir.value === 1 ? ' ▲' : ' ▼') : ''

const filtradas = computed(() => {
  const q = filtro.value.trim().toLowerCase()
  let base = lista.value
  if (soloActivos.value) base = base.filter(x => !x.baja)
  if (q) base = base.filter(x => `${x.imei} ${x.marca} ${x.modelo} ${x.color}`.toLowerCase().includes(q))
  const k = sortKey.value, dir = sortDir.value
  return [...base].sort((a, b) => (k === 'cod' || k === 'pantalla')
    ? ((a[k] as number) - (b[k] as number)) * dir
    : String(a[k]).localeCompare(String(b[k]), 'es', { numeric: true }) * dir)
})
const elegir = (x: Eq) => { if (x) emit('select', x.cod) }

onMounted(async () => {
  cargando.value = true
  try { lista.value = (await api.get('/celulares/equipos/buscar')).data ?? [] } catch { lista.value = [] }
  finally { cargando.value = false }
})
</script>

<style scoped>
.cb-ov { position:fixed; inset:0; background:rgba(15,23,42,.55); z-index:9500; display:flex; align-items:flex-start; justify-content:center; padding:50px 18px; }
.cb-md { background:#fff; border-radius:12px; width:min(900px,97vw); max-height:82vh; display:flex; flex-direction:column; overflow:hidden; box-shadow:0 18px 50px rgba(0,0,0,.3); }
.cb-head { display:flex; gap:10px; align-items:center; padding:12px 14px; background:#6a957b; color:#fff; flex-wrap:wrap; }
.cb-head > span { font-weight:700; font-size:14px; }
.cb-search { flex:1; min-width:180px; border:none; border-radius:6px; padding:8px 10px; font-size:13px; color:#1e293b; outline:none; }
.cb-chk { display:flex; align-items:center; gap:6px; font-size:12.5px; font-weight:600; cursor:pointer; }
.cb-x { background:rgba(255,255,255,.85); border:none; border-radius:6px; width:32px; height:32px; cursor:pointer; font-weight:700; color:#334155; }
.cb-body { overflow:auto; flex:1; }
.cb-info { padding:24px; text-align:center; color:#94a3b8; }
.cb-tabla { width:100%; border-collapse:collapse; font-size:12px; }
.cb-tabla th { position:sticky; top:0; background:#c8c8c8; color:#1e293b; padding:6px 8px; text-align:left; font-size:11.5px; white-space:nowrap; }
.cb-tabla th.ord { cursor:pointer; user-select:none; } .cb-tabla th.ord:hover { background:#b8b8b8; }
.cb-tabla td { padding:5px 8px; color:#1e293b; cursor:pointer; border-bottom:1px solid #f1f5f9; } .cb-tabla td.c { text-align:center; }
.cb-tabla tbody tr:hover td { background:#fff7cc; }
.cb-tabla tbody tr.sel td { background:#fde047; }
.cb-tabla tbody tr.baja td { background:#ec4899; color:#fff; } .cb-tabla tbody tr.baja.sel td { background:#db2777; }
.cb-cod { font-weight:700; color:#1d4ed8; } .cb-tabla tbody tr.baja .cb-cod { color:#fff; }
.cb-foot { display:flex; align-items:center; gap:10px; padding:10px 14px; border-top:1px solid #e2e8f0; }
.cb-ley { display:flex; align-items:center; gap:6px; font-size:12px; color:#64748b; } .cb-chip { width:13px; height:13px; border-radius:3px; display:inline-block; } .cb-chip.baja { background:#ec4899; }
.cb-aceptar { background:#1b4332; color:#fff; border:none; border-radius:7px; padding:9px 20px; cursor:pointer; font-weight:800; font-size:13px; } .cb-aceptar:disabled { opacity:.5; }
</style>
