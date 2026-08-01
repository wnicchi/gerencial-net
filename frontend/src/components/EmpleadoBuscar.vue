<!-- EmpleadoBuscar.vue — Lupa reutilizable de búsqueda de empleados (empleados_buscar).
     Uso: <EmpleadoBuscar v-if="show" @select="onSel" @close="show=false" />
     Emite 'select' con el código (number) del empleado elegido. -->
<template>
  <Teleport to="body">
    <div class="eb-ov" @click.self="$emit('close')">
      <div class="eb-md">
        <div class="eb-head">
          <span>👤 Buscar Empleado</span>
          <input ref="inp" v-model="filtro" class="eb-search" placeholder="Buscar por nombre, legajo o documento…" @keyup.enter="primero" />
          <label class="eb-chk"><input v-model="soloActivos" type="checkbox" @change="cargar" /> Sólo activos</label>
          <button class="eb-x" @click="$emit('close')">✕</button>
        </div>
        <div class="eb-body">
          <div v-if="cargando" class="eb-info">⟳ Buscando…</div>
          <div v-else-if="!lista.length" class="eb-info">Sin empleados.</div>
          <table v-else class="eb-tabla">
            <thead><tr>
              <th style="width:70px">Código</th><th>Apellido y Nombres</th><th style="width:80px">Legajo</th>
              <th style="width:110px">Documento</th><th>Sector</th>
            </tr></thead>
            <tbody>
              <tr v-for="(x, i) in lista" :key="x.cod" :class="{ baja: !x.activo, sel: sel === i }"
                  @click="sel = i" @dblclick="elegir(x)">
                <td class="eb-cod">{{ x.cod }}</td><td>{{ x.nombre }}</td><td class="c">{{ x.legajo }}</td>
                <td>{{ x.documento }}</td><td>{{ x.sector }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="eb-foot">
          <span class="eb-ley"><span class="eb-chip baja"></span> Baja</span>
          <span class="eb-cnt">{{ lista.length }}{{ lista.length === 300 ? '+ (refiná la búsqueda)' : '' }}</span>
          <span style="flex:1"></span>
          <button class="eb-aceptar" :disabled="sel < 0" @click="elegir(lista[sel])">Aceptar</button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { ref, onMounted, watch, nextTick } from 'vue'
import api from '@/services/auth'

interface Emp { PER_COD: number; PER_NOM: string; PER_LEG: string; PER_NDO: string; PER_SED: string; PER_AOP: string
  cod: number; nombre: string; legajo: string; documento: string; sector: string; activo: boolean }
// Emite la fila completa (claves PER_* + alias en minúscula) para ser drop-in de los handlers existentes.
const emit = defineEmits<{ (e: 'select', empleado: Emp): void; (e: 'close'): void }>()

const lista = ref<Emp[]>([]); const cargando = ref(false); const filtro = ref(''); const soloActivos = ref(true)
const sel = ref(-1); const inp = ref<HTMLInputElement | null>(null)
let deb: ReturnType<typeof setTimeout> | null = null

async function cargar () {
  cargando.value = true; sel.value = -1
  try {
    const { data } = await api.get('/empleados/buscar', { params: { q: filtro.value.trim(), activo: soloActivos.value ? 1 : 0 } })
    lista.value = data ?? []
  } catch { lista.value = [] }
  finally { cargando.value = false }
}
const primero = () => { if (lista.value.length) { sel.value = 0 } }
const elegir = (x: Emp) => { if (x) emit('select', x) }

watch(filtro, () => { if (deb) clearTimeout(deb); deb = setTimeout(cargar, 300) })
onMounted(async () => { await cargar(); await nextTick(); inp.value?.focus() })
</script>

<style scoped>
.eb-ov { position:fixed; inset:0; background:rgba(15,23,42,.55); z-index:9600; display:flex; align-items:flex-start; justify-content:center; padding:50px 18px; }
.eb-md { background:#fff; border-radius:12px; width:min(780px,97vw); max-height:82vh; display:flex; flex-direction:column; overflow:hidden; box-shadow:0 18px 50px rgba(0,0,0,.3); }
.eb-head { display:flex; gap:10px; align-items:center; padding:12px 14px; background:#1b4332; color:#fff; flex-wrap:wrap; }
.eb-head > span { font-weight:700; font-size:14px; }
.eb-search { flex:1; min-width:180px; border:none; border-radius:6px; padding:8px 10px; font-size:13px; color:#1e293b; outline:none; }
.eb-chk { display:flex; align-items:center; gap:6px; font-size:12.5px; font-weight:600; cursor:pointer; }
.eb-x { background:rgba(255,255,255,.85); border:none; border-radius:6px; width:32px; height:32px; cursor:pointer; font-weight:700; color:#334155; }
.eb-body { overflow:auto; flex:1; }
.eb-info { padding:24px; text-align:center; color:#94a3b8; }
.eb-tabla { width:100%; border-collapse:collapse; font-size:12.5px; }
.eb-tabla th { position:sticky; top:0; background:#c8c8c8; color:#1e293b; padding:6px 10px; text-align:left; font-size:12px; white-space:nowrap; }
.eb-tabla td { padding:5px 10px; color:#1e293b; cursor:pointer; border-bottom:1px solid #f1f5f9; } .eb-tabla td.c { text-align:center; }
.eb-tabla tbody tr:hover td { background:#fff7cc; }
.eb-tabla tbody tr.sel td { background:#fde047; }
.eb-tabla tbody tr.baja td { color:#94a3b8; font-style:italic; }
.eb-cod { font-weight:700; color:#1d4ed8; }
.eb-foot { display:flex; align-items:center; gap:12px; padding:10px 14px; border-top:1px solid #e2e8f0; }
.eb-ley { display:flex; align-items:center; gap:6px; font-size:12px; color:#64748b; } .eb-chip { width:13px; height:13px; border-radius:3px; display:inline-block; } .eb-chip.baja { background:#cbd5e1; }
.eb-cnt { font-size:12px; color:#94a3b8; }
.eb-aceptar { background:#1b4332; color:#fff; border:none; border-radius:7px; padding:9px 20px; cursor:pointer; font-weight:800; font-size:13px; } .eb-aceptar:disabled { opacity:.5; }
</style>
