<!-- ObligacionesContratistaView.vue — Presentación de Obligaciones (contra_obligaciones). -->
<template>
  <div class="ob-view">
    <div class="ob-cab">
      <div class="ob-cab-ico">📃</div>
      <div class="ob-cab-tx"><h1>Presentación de Obligaciones</h1><p>Exigencias presentadas y acceso de los empleados por contratista</p></div>
      <button class="ob-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="ob-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
    </div>

    <ObligacionesContratistaAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/obligaciones-contratista" titulo="Asistente IA — Presentación de Obligaciones"
            subtitulo="Preguntá sobre exigencias y accesos"
            :sugerencias="['¿Cómo marco una exigencia como presentada?','¿Qué hace Actualizar estado?','¿Cuándo se deniega el acceso?','¿Cómo confirmo los cambios?']"
            @close="modalIA = false" />

    <div class="ob-filtros">
      <span class="ob-lbl">Contratista</span>
      <select v-model.number="contratista" class="ob-sel" @change="cargar">
        <option :value="0">— Elegir contratista —</option>
        <option v-for="c in contratistas" :key="c.cod" :value="c.cod">{{ c.nombre }}</option>
      </select>
      <span v-if="contratista" class="ob-ind">
        <i :class="['ob-dot', hayVencidas ? 'no' : 'ok']"></i>
        {{ hayVencidas ? 'Tiene obligaciones vencidas' : 'Obligaciones al día' }}
        <template v-if="primerVenc"> · 1er venc.: {{ fmt(primerVenc) }}</template>
      </span>
    </div>

    <div v-if="contratista" class="ob-cuerpo">
      <!-- Exigencias asignadas -->
      <section class="ob-bloque">
        <h2>Exigencias asignadas <small>tildá las presentadas y cargá fecha / vencimiento</small></h2>
        <div class="ob-wrap">
          <table class="ob-grid">
            <thead><tr>
              <th>Pres.</th><th>Detalle</th><th>Tipo</th><th>Opción</th><th>F. Present.</th><th>F. Vence</th><th>Observaciones</th>
            </tr></thead>
            <tbody>
              <tr v-for="o in obligaciones" :key="o.codigo" :class="{ venc: o.presentada && o.vence && o.vence < hoy }">
                <td style="text-align:center"><input v-model="o.presentada" type="checkbox" @change="o.presentada && !o.fecha_pres ? (o.fecha_pres = hoy) : null" /></td>
                <td>{{ o.detalle }}</td>
                <td>
                  <select v-model="o.tipo" class="ob-in">
                    <option value="O">Obligatoria</option>
                    <option value="R">Optativa con otra</option>
                    <option value="N">No obligatoria</option>
                  </select>
                </td>
                <td>
                  <select v-if="o.tipo === 'R'" v-model.number="o.relativo" class="ob-in">
                    <option :value="0">—</option>
                    <option v-for="x in obligaciones.filter(z => z.codigo !== o.codigo)" :key="x.codigo" :value="x.codigo">{{ x.detalle }}</option>
                  </select>
                  <span v-else class="ob-na">—</span>
                </td>
                <td><input v-model="o.fecha_pres" type="date" class="ob-in" /></td>
                <td><input v-model="o.vence" type="date" class="ob-in" /></td>
                <td><input v-model="o.obs" type="text" class="ob-in ancho" /></td>
              </tr>
              <tr v-if="!obligaciones.length"><td colspan="7" class="ob-vacio">Este contratista no tiene exigencias asignadas.</td></tr>
            </tbody>
          </table>
        </div>
      </section>

      <!-- Empleados asociados -->
      <section class="ob-bloque">
        <h2>Empleados asociados
          <button class="ob-mini" @click="actualizarEstado">⟳ Actualizar estado de todos</button>
          <small>el acceso se deniega si el contratista tiene obligaciones vencidas o la ART del empleado venció</small>
        </h2>
        <div class="ob-wrap">
          <table class="ob-grid">
            <thead><tr>
              <th>Nombre</th><th>DNI</th><th>CUIL</th><th>Teléfono</th><th>Observaciones</th><th>Venc. ART</th><th>Acceso</th>
            </tr></thead>
            <tbody>
              <tr v-for="e in empleados" :key="e.unico" :class="{ deny: e.acceso !== 'S', ocu: e.ocupado && e.acceso === 'S' }">
                <td>{{ e.nombre }}</td><td>{{ e.dni }}</td><td>{{ e.cuil }}</td><td>{{ e.telefono }}</td>
                <td><input v-model="e.obs" type="text" class="ob-in ancho" /></td>
                <td><input v-model="e.venc_art" type="date" class="ob-in" @change="recalcAcceso(e)" /></td>
                <td style="text-align:center">
                  <button :class="['ob-acc', e.acceso === 'S' ? 'ok' : 'no']" @click="e.acceso = e.acceso === 'S' ? 'N' : 'S'">
                    {{ e.acceso === 'S' ? 'PERMITIDO' : 'DENEGADO' }}
                  </button>
                </td>
              </tr>
              <tr v-if="!empleados.length"><td colspan="7" class="ob-vacio">Este contratista no tiene empleados asociados.</td></tr>
            </tbody>
          </table>
        </div>
      </section>

      <div class="ob-pie">
        <button class="ob-btn-excel" @click="exportarExcel">📊 Exportar a Excel</button>
        <button class="ob-btn-ok" :disabled="guardando" @click="confirmar">💾 {{ guardando ? 'Guardando…' : 'CONFIRMAR MODIFICACIONES' }}</button>
      </div>
    </div>
    <div v-else class="ob-vacio big">Elegí un contratista para gestionar sus obligaciones y accesos.</div>

    <p v-if="msg" :class="['ob-msg', msgError ? 'err' : 'ok']">{{ msg }}</p>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import api from '@/services/auth'
import ObligacionesContratistaAyuda from '@/components/ObligacionesContratistaAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'
import { guardarComo } from '@/utils/descargas'

const modalAyuda = ref(false); const modalIA = ref(false)
const hoy = new Date().toISOString().slice(0, 10)
interface Obl { codigo: number; detalle: string; presentada: boolean; fecha_pres: string; vence: string; tipo: string; relativo: number; obs: string; unica: boolean; vig_meses: number; art: boolean }
interface Emp { unico: number; nombre: string; dni: string; cuil: string; telefono: string; obs: string; venc_art: string; acceso: string; ocupado: boolean }

const contratistas = ref<{ cod: number; nombre: string }[]>([])
const contratista = ref(0)
const obligaciones = ref<Obl[]>([])
const empleados = ref<Emp[]>([])
const primerVenc = ref('')
const guardando = ref(false)
const msg = ref(''); const msgError = ref(false)
const flash = (t: string, e = false) => { msg.value = t; msgError.value = e; if (t) setTimeout(() => msg.value = '', 4000) }
const fmt = (d: string) => d ? d.split('-').reverse().join('/') : ''

const hayVencidas = computed(() => obligaciones.value.some(o => o.presentada && o.vence && o.vence < hoy))

const cargar = async () => {
  obligaciones.value = []; empleados.value = []; primerVenc.value = ''
  if (!contratista.value) return
  try {
    const { data } = await api.get(`/contratistas-externos/${contratista.value}/obligaciones`)
    obligaciones.value = data.obligaciones; empleados.value = data.empleados; primerVenc.value = data.primer_venc
  } catch (e) { console.error(e); flash('No se pudieron cargar las obligaciones.', true) }
}

// Recalcula el acceso de un empleado según su ART (denegado si venció)
const recalcAcceso = (e: Emp) => { if (e.venc_art && e.venc_art < hoy) e.acceso = 'N' }

// Botón: deniega a todos si el contratista tiene obligaciones presentadas vencidas; si no, permite (salvo ART vencida)
const actualizarEstado = () => {
  const denegar = hayVencidas.value
  for (const e of empleados.value) {
    e.acceso = denegar ? 'N' : 'S'
    if (e.venc_art && e.venc_art < hoy) e.acceso = 'N'
  }
  flash(denegar ? 'Acceso DENEGADO a todos (hay obligaciones vencidas).' : 'Acceso recalculado.')
}

const confirmar = async () => {
  if (!confirm('¿Confirma los cambios de obligaciones del contratista?')) return
  guardando.value = true
  try {
    await api.post(`/contratistas-externos/${contratista.value}/obligaciones`, {
      obligaciones: obligaciones.value, empleados: empleados.value,
    })
    flash('Cambios guardados.'); await cargar()
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo guardar.', true) }
  finally { guardando.value = false }
}

const exportarExcel = async () => {
  const tipoTxt = (t: string) => t === 'O' ? 'OBLIGATORIA' : t === 'R' ? 'OPTATIVA CON OTRA' : 'NO OBLIGATORIA'
  const exi = obligaciones.value.map(o =>
    `<tr><td>${esc(o.detalle)}</td><td>${o.presentada ? 'SI' : 'NO'}</td><td>${tipoTxt(o.tipo)}</td><td>${fmt(o.fecha_pres)}</td><td>${fmt(o.vence)}</td><td>${esc(o.obs)}</td></tr>`).join('')
  const aso = empleados.value.map(e =>
    `<tr><td>${esc(e.nombre)}</td><td>${esc(e.dni)}</td><td>${esc(e.cuil)}</td><td>${esc(e.telefono)}</td><td>${fmt(e.venc_art)}</td><td>${e.acceso === 'S' ? 'PERMITIDO' : 'DENEGADO'}</td></tr>`).join('')
  const html = `<html xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="utf-8"></head><body>
    <h3>EXIGENCIAS ASIGNADAS</h3>
    <table border="1"><tr style="background:#1b4332;color:#fff"><th>Detalle</th><th>Presentada</th><th>Tipo</th><th>F. Present.</th><th>F. Vence</th><th>Observaciones</th></tr>${exi}</table>
    <br><h3>EMPLEADOS ASOCIADOS</h3>
    <table border="1"><tr style="background:#1b4332;color:#fff"><th>Nombre</th><th>DNI</th><th>CUIL</th><th>Teléfono</th><th>Venc. ART</th><th>Acceso</th></tr>${aso}</table>
    </body></html>`
  await guardarComo(new Blob(['﻿' + html], { type: 'application/vnd.ms-excel' }), 'Obligaciones_Contratista.xls')
}
const esc = (s: string) => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')

onMounted(async () => {
  try { contratistas.value = (await api.get('/contratistas-externos')).data.map((c: any) => ({ cod: c.cod, nombre: c.nombre })) }
  catch (e) { console.error(e) }
})
</script>

<style scoped>
.ob-view { display:flex; flex-direction:column; height:100%; overflow:auto; }
.ob-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.ob-cab-ico { font-size:28px; } .ob-cab-tx h1 { margin:0; font-size:19px; color:#1e293b; } .ob-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.ob-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ob-btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ob-filtros { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#f8fafc; border-bottom:1px solid #e2e8f0; flex-wrap:wrap; }
.ob-lbl { font-size:13px; font-weight:700; color:#1b4332; }
.ob-sel { border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:13px; min-width:280px; }
.ob-ind { margin-left:auto; font-size:13px; color:#475569; display:flex; align-items:center; gap:7px; }
.ob-dot { width:11px; height:11px; border-radius:50%; display:inline-block; } .ob-dot.ok { background:#22c55e; } .ob-dot.no { background:#ef4444; }
.ob-cuerpo { padding:14px 18px; display:flex; flex-direction:column; gap:18px; }
.ob-bloque h2 { font-size:14px; color:#1b4332; margin:0 0 8px; display:flex; align-items:center; gap:12px; }
.ob-bloque h2 small { font-weight:400; color:#94a3b8; font-size:12px; }
.ob-wrap { border:1px solid #e2e8f0; border-radius:8px; overflow:auto; max-height:42vh; }
.ob-grid { width:100%; border-collapse:collapse; font-size:13px; white-space:nowrap; }
.ob-grid th { position:sticky; top:0; background:#1b4332; color:#fff; font-weight:600; padding:8px 10px; font-size:12px; text-align:left; }
.ob-grid td { padding:4px 8px; border-bottom:1px solid #f1f5f9; color:#1e293b; }
.ob-grid tr.venc td { background:#fee2e2; } .ob-grid tr.deny td { background:#fee2e2; } .ob-grid tr.ocu td { background:#fef9c3; }
.ob-in { border:1px solid #d1d5db; border-radius:5px; padding:5px 7px; font-size:13px; outline:none; } .ob-in.ancho { width:100%; min-width:160px; }
.ob-in:focus { border-color:#40916c; box-shadow:0 0 0 2px rgba(64,145,108,.15); }
.ob-na { color:#cbd5e1; } .ob-mini { background:#fff; border:1px solid #cbd5e1; padding:5px 11px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; } .ob-mini:hover { background:#f0faf4; }
.ob-acc { border:none; padding:4px 12px; border-radius:999px; font-size:11px; font-weight:700; cursor:pointer; } .ob-acc.ok { background:#dcfce7; color:#166534; } .ob-acc.no { background:#fee2e2; color:#b91c1c; }
.ob-vacio { padding:24px; text-align:center; color:#9ca3af; font-size:14px; } .ob-vacio.big { padding:60px; }
.ob-pie { display:flex; gap:14px; }
.ob-btn-excel { background:#fff; border:1px solid #16a34a; color:#15803d; padding:10px 18px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; }
.ob-btn-ok { margin-left:auto; background:#16a34a; color:#fff; border:none; padding:10px 22px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; } .ob-btn-ok:disabled { background:#cbd5e1; }
.ob-msg { padding:9px 14px; margin:0 18px 14px; font-size:13px; border-radius:6px; } .ob-msg.ok { background:#dcfce7; color:#166534; } .ob-msg.err { background:#fee2e2; color:#b91c1c; }
</style>
