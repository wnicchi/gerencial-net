<!-- CapacitacionView.vue — Jornadas de Capacitación (capacitacion.scx). ABM simple. -->
<template>
  <div class="cp-view">
    <div class="cp-cab">
      <div class="cp-ico">🎓</div>
      <div class="cp-tx"><h1>Jornadas de Capacitación</h1><p>{{ lista.length }} jornada{{ lista.length === 1 ? '' : 's' }}</p></div>
      <button class="cp-ia" @click="modalIA = true">🤖 IA</button>
      <button class="cp-ayuda" @click="ayuda = true">❓ Ayuda</button>
      <button class="cp-print" :disabled="!lista.length" @click="imprimir">🖨 Listado</button>
      <button class="cp-nuevo" @click="abrirNuevo">＋ Nueva</button>
    </div>

    <ChatIA v-if="modalIA" endpoint="/ia/capacitaciones" titulo="Asistente IA — Jornadas de Capacitación"
            subtitulo="Preguntá sobre las jornadas de capacitación"
            :sugerencias="['¿Para qué sirve este módulo?','¿Qué es una capacitación cerrada?','¿Cómo cargo una jornada?','¿El código se asigna solo?']"
            @close="modalIA = false" />

    <div class="cp-card">
      <input v-model="filtro" class="cp-search" placeholder="🔍 Buscar por capacitación, disertante o temática…" />
      <div v-if="cargando" class="cp-vacio">⟳ Cargando…</div>
      <div v-else-if="!listaFiltrada.length" class="cp-vacio">Sin jornadas cargadas</div>
      <table v-else class="cp-tabla">
        <thead><tr>
          <th style="width:60px" class="ord" @click="ordenar('cod')">Cód.{{ flecha('cod') }}</th>
          <th class="ord" @click="ordenar('capacitacion')">Capacitación{{ flecha('capacitacion') }}</th>
          <th style="width:100px" class="ord" @click="ordenar('fecha')">Fecha{{ flecha('fecha') }}</th>
          <th class="ord" @click="ordenar('disertante')">Disertante{{ flecha('disertante') }}</th>
          <th class="ord" @click="ordenar('tematica')">Área temática{{ flecha('tematica') }}</th>
          <th style="width:110px" class="ord" @click="ordenar('duracion')">Duración{{ flecha('duracion') }}</th>
          <th class="c ord" style="width:70px" @click="ordenar('cerrada')">Cerrada{{ flecha('cerrada') }}</th>
          <th style="width:90px;text-align:center">Acciones</th>
        </tr></thead>
        <tbody>
          <tr v-for="(e, i) in listaFiltrada" :key="i">
            <td class="cp-cod">{{ e.cod }}</td><td>{{ e.capacitacion }}</td><td>{{ fmt(e.fecha) }}</td>
            <td>{{ e.disertante }}</td><td>{{ e.tematica }}</td><td>{{ e.duracion }}</td>
            <td class="c">{{ e.cerrada ? '🔒' : '' }}</td>
            <td style="text-align:center"><button class="cp-i" @click="abrirEditar(e)">✏️</button><button class="cp-i del" @click="eliminar(e)">🗑️</button></td>
          </tr>
        </tbody>
      </table>
      <p v-if="msg" :class="['cp-msg', msgError ? 'err' : 'ok']">{{ msg }}</p>
    </div>

    <Teleport to="body">
      <div v-if="modal" class="cp-ov" @click.self="modal = false">
        <div class="cp-md" v-enter-next>
          <h3>{{ editando ? 'Editar jornada' : 'Nueva jornada de capacitación' }}</h3>
          <div class="cp-grid">
            <div class="campo span2"><label>Capacitación *</label><input ref="inputNom" v-model="form.capacitacion" maxlength="100" /></div>
            <div class="campo"><label>Fecha</label><input v-model="form.fecha" type="date" /></div>
            <div class="campo"><label>Período desde</label><input v-model="form.desde" type="date" /></div>
            <div class="campo"><label>Período hasta</label><input v-model="form.hasta" type="date" /></div>
            <div class="campo span2"><label>Disertante</label>
              <select v-model.number="form.disertante_cod"><option :value="0">— —</option><option v-for="d in disertantes" :key="d.cod" :value="d.cod">{{ d.nombre }}</option></select>
            </div>
            <div class="campo"><label>Modalidad</label><input v-model="form.modalidad" list="cp-modalidades" maxlength="40" /><datalist id="cp-modalidades"><option v-for="m in modalidades" :key="m" :value="m" /></datalist></div>
            <div class="campo"><label>Duración</label><input v-model="form.duracion" list="cp-duraciones" maxlength="40" /><datalist id="cp-duraciones"><option v-for="d in duraciones" :key="d" :value="d" /></datalist></div>
            <div class="campo span2"><label>Área temática</label>
              <select v-model.number="form.tema_cod"><option :value="0">— —</option><option v-for="t in temas" :key="t.cod" :value="t.cod">{{ t.nombre }}</option></select>
            </div>
            <div class="campo span2"><label>Objetivos</label><input v-model="form.objetivo" maxlength="200" /></div>
            <div class="campo span2"><label class="chk"><input type="checkbox" v-model="form.cerrada" /> Capacitación cerrada</label></div>
          </div>
          <p v-if="modalError" class="cp-md-err">{{ modalError }}</p>
          <div class="cp-md-acc">
            <button class="cp-cancel" @click="modal = false">Cancelar</button>
            <button class="cp-ok" :disabled="guardando" @click="guardar">{{ guardando ? '⟳' : 'Guardar' }}</button>
          </div>
        </div>
      </div>
      <div v-if="ayuda" class="cp-ov" @click.self="ayuda = false">
        <div class="cp-md">
          <h3>❓ Ayuda — Jornadas de Capacitación</h3>
          <ul class="cp-help">
            <li>Registra las <b>jornadas de capacitación</b>: nombre, fecha, período, disertante, modalidad, duración, objetivos y área temática.</li>
            <li><b>Capacitación cerrada</b>: marca que la jornada ya finalizó / no admite más inscripciones.</li>
            <li>El <b>código</b> lo asigna el sistema. Disertante y área temática se eligen de las listas.</li>
          </ul>
          <div class="cp-md-acc"><button class="cp-ok" @click="ayuda = false">Cerrar</button></div>
        </div>
      </div>
    </Teleport>

    <Teleport to="body">
      <div v-if="pdfUrl" class="cp-pdf-ov" @click.self="cerrarPdf">
        <div class="cp-pdf-md">
          <div class="cp-pdf-head"><span>{{ pdfNombre }}</span>
            <div class="cp-pdf-acc">
              <button class="cp-pdf-b ok" @click="guardarDesdeUrl(pdfUrl, pdfNombre)">⬇ Descargar</button>
              <button class="cp-pdf-b ok" @click="($refs.pf as HTMLIFrameElement)?.contentWindow?.print()">🖨 Imprimir</button>
              <button class="cp-pdf-b cancel" @click="cerrarPdf">✕ Cerrar</button>
            </div>
          </div>
          <iframe ref="pf" :src="pdfUrl" class="cp-pdf-frame"></iframe>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, nextTick } from 'vue'
import api from '@/services/auth'
import jsPDF from 'jspdf'
import { guardarDesdeUrl } from '@/utils/descargas'
import ChatIA from '@/components/ChatIA.vue'

interface Combo { cod: number; nombre: string }
interface Jornada { cod: number; capacitacion: string; fecha: string; desde: string; hasta: string; disertante_cod: number; disertante: string; modalidad: string; duracion: string; objetivo: string; tema_cod: number; tematica: string; cerrada: boolean }

const lista = ref<Jornada[]>([]); const cargando = ref(false); const filtro = ref('')
const disertantes = ref<Combo[]>([]); const temas = ref<Combo[]>([]); const modalidades = ref<string[]>([]); const duraciones = ref<string[]>([])
const msg = ref(''); const msgError = ref(false)
const modal = ref(false); const ayuda = ref(false); const modalIA = ref(false)
const editando = ref(false); const guardando = ref(false); const modalError = ref('')
const inputNom = ref<HTMLInputElement | null>(null)
const vacio = () => ({ cod: 0, capacitacion: '', fecha: '', desde: '', hasta: '', disertante_cod: 0, modalidad: '', duracion: '', objetivo: '', tema_cod: 0, cerrada: false })
const form = ref<any>(vacio())
const fmt = (f: string) => f ? f.split('-').reverse().join('/') : ''
const flash = (t: string, e = false) => { msg.value = t; msgError.value = e; setTimeout(() => msg.value = '', 3000) }

type SortKey = 'cod' | 'capacitacion' | 'fecha' | 'disertante' | 'tematica' | 'duracion' | 'cerrada'
const sortKey = ref<SortKey>('capacitacion'); const sortDir = ref<1 | -1>(1)
const ordenar = (k: SortKey) => { if (sortKey.value === k) sortDir.value = (sortDir.value === 1 ? -1 : 1) as 1 | -1; else { sortKey.value = k; sortDir.value = 1 } }
const flecha = (k: SortKey) => sortKey.value === k ? (sortDir.value === 1 ? ' ▲' : ' ▼') : ''

const listaFiltrada = computed(() => {
  const q = filtro.value.trim().toLowerCase()
  const base = q ? lista.value.filter(e => `${e.capacitacion} ${e.disertante} ${e.tematica}`.toLowerCase().includes(q)) : lista.value.slice()
  const k = sortKey.value, dir = sortDir.value
  return base.slice().sort((a: any, b: any) => {
    let va = a[k], vb = b[k]
    if (typeof va === 'boolean') { va = va ? 1 : 0; vb = vb ? 1 : 0 }
    if (typeof va === 'number') return (va - vb) * dir
    return String(va).localeCompare(String(vb), 'es', { numeric: true }) * dir
  })
})

const cargar = async () => { cargando.value = true; try { lista.value = (await api.get('/capacitaciones')).data } catch (e) { console.error(e) } finally { cargando.value = false } }
onMounted(async () => {
  await Promise.all([cargar(), (async () => {
    try { const { data } = await api.get('/capacitaciones/init'); disertantes.value = data.disertantes ?? []; temas.value = data.temas ?? []; modalidades.value = data.modalidades ?? []; duraciones.value = data.duraciones ?? [] } catch { /* */ }
  })()])
})

const abrirNuevo = async () => { editando.value = false; form.value = vacio(); modalError.value = ''; modal.value = true; await nextTick(); inputNom.value?.focus() }
const abrirEditar = async (e: Jornada) => { editando.value = true; form.value = { ...e }; modalError.value = ''; modal.value = true; await nextTick(); inputNom.value?.focus() }

const guardar = async () => {
  if (!form.value.capacitacion.trim()) { modalError.value = 'El nombre de la capacitación es obligatorio.'; return }
  guardando.value = true; modalError.value = ''
  const body = { capacitacion: form.value.capacitacion, fecha: form.value.fecha || null, desde: form.value.desde || null, hasta: form.value.hasta || null, disertante_cod: form.value.disertante_cod, modalidad: form.value.modalidad, duracion: form.value.duracion, objetivo: form.value.objetivo, tema_cod: form.value.tema_cod, cerrada: form.value.cerrada }
  try {
    if (editando.value) { await api.put(`/capacitaciones/${form.value.cod}`, body); flash('Jornada actualizada') }
    else { await api.post('/capacitaciones', body); flash('Jornada creada') }
    modal.value = false; await cargar()
  } catch (e: any) { modalError.value = e?.response?.data?.message ?? Object.values(e?.response?.data?.errors ?? {}).flat()[0] ?? 'No se pudo guardar.' }
  finally { guardando.value = false }
}
const eliminar = async (e: Jornada) => {
  if (!confirm(`¿Eliminar la jornada "${e.capacitacion}"?`)) return
  try { await api.delete(`/capacitaciones/${e.cod}`); flash('Jornada eliminada'); await cargar() }
  catch (err: any) { flash(err?.response?.data?.message ?? 'No se pudo eliminar.', true) }
}

const pdfUrl = ref(''); const pdfNombre = ref('')
const cerrarPdf = () => { if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value); pdfUrl.value = '' }
function imprimir () {
  const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })
  const ML = 15, PW = 210, PH = 297; let y = 18
  doc.setFont('times', 'bold'); doc.setFontSize(15); doc.setTextColor(20, 60, 40)
  doc.text('JORNADAS DE CAPACITACIÓN', PW / 2, y, { align: 'center' }); doc.setTextColor(0, 0, 0); y += 10
  for (const e of lista.value) {
    if (y > PH - 36) { doc.addPage(); y = 18 }
    doc.setDrawColor(200); doc.setFillColor(240, 245, 242); doc.rect(ML, y - 4, PW - ML * 2, 7, 'F')
    doc.setFont('helvetica', 'bold'); doc.setFontSize(10)
    doc.text(`(${e.cod})  ${e.capacitacion}`, ML + 2, y + 1); doc.text(fmt(e.fecha), PW - ML - 2, y + 1, { align: 'right' }); y += 9
    doc.setFont('helvetica', 'normal'); doc.setFontSize(9)
    const linea = (lbl: string, val: string) => { if (!val) return; doc.setFont('helvetica', 'bold'); doc.text(lbl, ML + 2, y); doc.setFont('helvetica', 'normal'); doc.text(doc.splitTextToSize(val, 150)[0] || '', ML + 38, y); y += 5 }
    linea('Disertante:', e.disertante); linea('Modalidad:', e.modalidad); linea('Duración:', e.duracion)
    linea('Área temática:', e.tematica); linea('Objetivos:', e.objetivo)
    if (e.desde || e.hasta) linea('Período:', `${fmt(e.desde)} a ${fmt(e.hasta)}`)
    if (e.cerrada) { doc.setFont('helvetica', 'bold'); doc.setTextColor(150, 0, 0); doc.text('CAPACITACIÓN CERRADA', ML + 2, y); doc.setTextColor(0, 0, 0); doc.setFont('helvetica', 'normal'); y += 5 }
    y += 3
  }
  cerrarPdf(); pdfNombre.value = 'Jornadas_capacitacion.pdf'; pdfUrl.value = URL.createObjectURL(doc.output('blob'))
}
</script>

<style scoped>
.cp-view { display:flex; flex-direction:column; min-height:100%; }
.cp-cab { display:flex; align-items:center; gap:10px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; flex-wrap:wrap; }
.cp-ico { font-size:28px; } .cp-tx h1 { margin:0; font-size:19px; color:#1e293b; } .cp-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.cp-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.cp-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.cp-print { background:#e0eefc; color:#2d6a9f; border:none; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; } .cp-print:disabled { opacity:.5; cursor:default; }
.cp-nuevo { background:#2d6a9f; color:#fff; border:none; padding:9px 16px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; }
.cp-card { margin:18px; background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:16px; }
.cp-search { width:100%; max-width:480px; border:1px solid #d1d5db; border-radius:7px; padding:9px 12px; font-size:14px; margin-bottom:12px; outline:none; }
.cp-vacio { text-align:center; color:#94a3b8; padding:24px; }
.cp-tabla { width:100%; border-collapse:collapse; font-size:13.5px; }
.cp-tabla th { background:#f4f7fc; color:#2a4a6a; padding:8px 10px; text-align:left; font-size:12px; border-bottom:1px solid #e2e8f0; } .cp-tabla th.c { text-align:center; }
.cp-tabla th.ord { cursor:pointer; user-select:none; white-space:nowrap; } .cp-tabla th.ord:hover { background:#e6eefc; color:#1a3a5c; }
.cp-tabla td { padding:7px 10px; border-bottom:1px solid #f0f4f9; color:#1e293b; } .cp-tabla td.c { text-align:center; } .cp-cod { color:#2d6a9f; font-weight:700; }
.cp-i { background:none; border:none; cursor:pointer; font-size:15px; padding:2px 5px; } .cp-i.del:hover { filter:saturate(2); }
.cp-msg { margin:12px 0 0; padding:8px 12px; border-radius:6px; font-size:13px; } .cp-msg.ok { background:#d1fae5; color:#065f46; } .cp-msg.err { background:#fee2e2; color:#991b1b; }
.cp-ov { position:fixed; inset:0; background:rgba(15,23,42,.5); z-index:9000; display:flex; align-items:flex-start; justify-content:center; padding:40px 18px; overflow:auto; }
.cp-md { background:#fff; border-radius:14px; padding:22px; width:min(640px,95vw); }
.cp-md h3 { margin:0 0 14px; color:#1a3a5c; }
.cp-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
.campo { display:flex; flex-direction:column; gap:4px; } .campo.span2 { grid-column:1 / -1; }
.campo label { font-size:12px; font-weight:600; color:#374151; } .campo .chk { flex-direction:row; align-items:center; gap:7px; font-weight:700; color:#1b4332; }
.cp-md input, .cp-md select { width:100%; border:1px solid #c8d8ea; border-radius:7px; padding:9px 11px; font-size:14px; outline:none; box-sizing:border-box; color:#1e293b; }
.campo .chk input { width:auto; }
.cp-md-err { color:#991b1b; font-size:13px; margin:10px 0 0; }
.cp-help { margin:0; padding-left:18px; color:#334155; font-size:13.5px; line-height:1.7; }
.cp-md-acc { display:flex; justify-content:flex-end; gap:8px; margin-top:16px; }
.cp-cancel { background:#eef2f7; color:#475569; border:none; border-radius:7px; padding:9px 16px; cursor:pointer; font-weight:600; }
.cp-ok { background:#2d6a9f; color:#fff; border:none; border-radius:7px; padding:9px 18px; cursor:pointer; font-weight:700; } .cp-ok:disabled { opacity:.5; }
.cp-pdf-ov { position:fixed; inset:0; background:rgba(15,23,42,.6); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.cp-pdf-md { width:min(820px,97vw); height:92vh; background:#fff; border-radius:10px; overflow:hidden; display:flex; flex-direction:column; }
.cp-pdf-head { display:flex; align-items:center; gap:12px; padding:10px 14px; background:#1b4332; color:#fff; font-size:13px; } .cp-pdf-acc { margin-left:auto; display:flex; gap:8px; }
.cp-pdf-b { border:none; padding:7px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; } .cp-pdf-b.ok { background:#22c55e; color:#fff; } .cp-pdf-b.cancel { background:#ef4444; color:#fff; }
.cp-pdf-frame { flex:1; border:none; width:100%; }
</style>
