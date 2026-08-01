<!-- ExamenesMedicosAgregarView.vue — Control de Salud · Exámenes Médicos (Exclusivos) · Agregar
     (examenes_medicos_agregar.scx). Examen con enfermedad CIE10 y lista de documentos. -->
<template>
  <div class="ex-view">
    <div class="ex-cab">
      <div class="ex-ico">🩺</div>
      <div class="ex-tx"><h1>Exámenes Médicos — Agregar</h1><p>Examen con enfermedad (CIE10) y documentación</p></div>
      <button class="ex-ia" @click="modalIA = true">🤖 IA</button>
      <button class="ex-ayuda" @click="ayuda = true">❓ Ayuda</button>
    </div>

    <ChatIA v-if="modalIA" endpoint="/ia/examenes" titulo="Asistente IA — Exámenes Médicos"
            subtitulo="Preguntá sobre la carga de exámenes médicos"
            :sugerencias="['¿Para qué sirve este módulo?','¿Qué es la enfermedad CIE10?','¿Puedo adjuntar varios documentos?','¿Cuándo es obligatorio el próximo examen?']"
            @close="modalIA = false" />

    <transition name="fade"><div v-if="msg" :class="['ex-msg', msgErr ? 'err' : 'ok']">{{ msg }}</div></transition>

    <div class="ex-body">
      <div class="ex-top">
        <div class="campo">
          <label>Nro. Personal</label>
          <EmpleadoInput :codigo="empCod" :nombre="empNombre" @select="onSelEmp" />
        </div>
        <div class="ex-foto"><img v-if="fotoUrl" :src="fotoUrl" @error="fotoUrl=''" /><div v-else class="ex-foto-ph">Sin foto</div></div>
      </div>

      <template v-if="empCod">
        <div class="ex-nombre">{{ empNombre }}</div>

        <div class="ex-form">
          <div class="campo span2"><label>Médico responsable</label>
            <select v-model.number="medico"><option :value="0">— Seleccione —</option><option v-for="m in medicos" :key="m.cod" :value="m.cod">{{ m.nombre }}</option></select>
          </div>
          <div class="campo span2"><label>Tipo de documento</label>
            <div class="ex-radios">
              <label class="rad"><input type="radio" value="E" v-model="tipoDoc" /> Examen</label>
              <label class="rad"><input type="radio" value="C" v-model="tipoDoc" /> Certificado</label>
            </div>
          </div>
          <div class="campo"><label>Fecha del examen</label><input v-model="fechaExamen" type="date" /></div>
          <div class="campo"><label>Próximo examen <span v-if="tipoDoc==='E'" class="oblig">*</span></label><input v-model="fechaProximo" type="date" :disabled="tipoDoc!=='E'" /></div>
          <div class="campo span2"><label>Tipo de examen</label>
            <select v-model.number="tipoExamen"><option :value="0">— Seleccione —</option><option v-for="t in tiposExamen" :key="t.cod" :value="t.cod">{{ t.nombre }}</option></select>
          </div>
          <div class="campo span2"><label>Enfermedad (CIE10)</label>
            <div class="ex-enf">
              <input :value="enfCod" readonly class="ex-enf-cod" placeholder="Cód." />
              <input :value="enfDes" readonly class="ex-enf-des" placeholder="Descripción de la enfermedad…" />
              <button type="button" class="ex-lupa" @click="abrirEnf">🔍 Buscar</button>
              <button v-if="enfCod" type="button" class="ex-x" title="Quitar" @click="enfCod=''; enfDes=''; enfUni=0">✕</button>
            </div>
          </div>
          <div class="campo span2"><label>Notas médicas</label><textarea v-model="notas" rows="3" maxlength="2000"></textarea></div>
        </div>

        <!-- Lista de documentación -->
        <div class="ex-doc">
          <div class="ex-doc-h"><b>Lista de Documentación</b> <span>(opcional, podés agregar varios)</span></div>
          <table v-if="docs.length" class="ex-tabla">
            <thead><tr><th style="width:60px">Cód.</th><th>Tipo de documento</th><th>Nombre</th><th style="width:70px">Ext.</th><th>Observación</th><th style="width:90px">Fecha</th><th style="width:78px"></th></tr></thead>
            <tbody>
              <tr v-for="(dc, i) in docs" :key="i">
                <td class="ex-cod">{{ dc.tipo }}</td><td>{{ dc.tipoNombre }}</td><td>{{ dc.nombreArch }}</td><td>{{ dc.ext }}</td><td class="ex-obs">{{ dc.obs }}</td><td>{{ fmt(dc.fecha) }}</td>
                <td><button class="ex-i" title="Ver" @click="verDoc(dc)">👁️</button><button class="ex-i del" title="Quitar" @click="docs.splice(i,1)">🗑️</button></td>
              </tr>
            </tbody>
          </table>

          <div class="ex-doc-grid">
            <div class="campo"><label>Tipo de documento</label>
              <select v-model="dTipo"><option value="">— —</option><option v-for="t in tiposDoc" :key="t.cod" :value="t.cod">{{ t.cod }} — {{ t.nombre }}</option></select>
            </div>
            <div class="campo"><label>Fecha del documento</label><input v-model="dFecha" type="date" /></div>
            <div class="campo span2"><label>Archivo</label><input ref="fileInput" type="file" @change="onFile" /></div>
            <div class="campo span2"><label>Observación</label><input v-model="dObs" maxlength="60" placeholder="Opcional…" /></div>
            <div class="campo span2"><button class="btn add" @click="agregarDoc">➕ Agregar a la lista</button></div>
          </div>
          <p class="ex-nota">No se aceptan exe, bat, dll, zip, rar, cmd ni cab. Máximo 50 MB por archivo.</p>
        </div>

        <div class="ex-acc">
          <button class="btn reset" @click="reset()">↺ Reset</button>
          <span class="ex-spacer"></span>
          <button class="btn ok" :disabled="proc" @click="confirmar">{{ proc ? '⟳ Guardando…' : '✔ CONFIRMAR Y GRABAR TODO' }}</button>
        </div>
      </template>
    </div>

    <!-- Modal buscar enfermedad CIE10 -->
    <Teleport to="body">
      <div v-if="enfModal" class="ex-ov" @click.self="enfModal = false">
        <div class="ex-enf-md">
          <h3>🔍 Buscar enfermedad (CIE10)</h3>
          <input ref="inputEnf" v-model="enfQ" placeholder="Código o descripción…" @input="buscarEnf" />
          <ul class="ex-enf-list">
            <li v-for="e in enfRes" :key="e.unico" @click="elegirEnf(e)"><b>{{ e.cod }}</b> — {{ e.nombre }}</li>
            <li v-if="!enfRes.length" class="ex-enf-vacio">{{ enfQ.trim().length < 1 ? 'Escribí para buscar…' : 'Sin resultados' }}</li>
          </ul>
          <div class="ex-acc"><span class="ex-spacer"></span><button class="btn reset" @click="enfModal = false">Cerrar</button></div>
        </div>
      </div>
      <div v-if="ayuda" class="ex-ov" @click.self="ayuda = false">
        <div class="ex-enf-md">
          <h3>❓ Ayuda — Exámenes Médicos</h3>
          <ul class="ex-help">
            <li>Buscá el empleado y completá médico, tipo, fechas y tipo de examen.</li>
            <li><b>Enfermedad (CIE10)</b>: opcional, buscala con la lupa.</li>
            <li>Podés <b>agregar varios documentos</b> a la lista antes de grabar.</li>
            <li>La fecha del próximo examen es obligatoria cuando el tipo es <b>Examen</b>.</li>
          </ul>
          <div class="ex-acc"><span class="ex-spacer"></span><button class="btn ok" @click="ayuda = false">Cerrar</button></div>
        </div>
      </div>
    </Teleport>

    <DocViewer ref="visor" />
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, nextTick } from 'vue'
import api from '@/services/auth'
import EmpleadoInput from '@/components/EmpleadoInput.vue'
import ChatIA from '@/components/ChatIA.vue'
import DocViewer from '@/components/DocViewer.vue'

const medicos = ref<{ cod: number; nombre: string }[]>([])
const tiposExamen = ref<{ cod: number; nombre: string }[]>([])
const tiposDoc = ref<{ cod: string; nombre: string }[]>([])
const empCod = ref(0); const empNombre = ref(''); const fotoUrl = ref('')
const medico = ref(0); const tipoDoc = ref<'E' | 'C'>('E'); const tipoExamen = ref(0)
const fechaExamen = ref(new Date().toISOString().slice(0, 10)); const fechaProximo = ref(''); const notas = ref('')
const enfCod = ref(''); const enfDes = ref(''); const enfUni = ref(0)
const proc = ref(false); const msg = ref(''); const msgErr = ref(false); const modalIA = ref(false); const ayuda = ref(false)
const flash = (t: string, e = false) => { msg.value = t; msgErr.value = e; if (t && !e) setTimeout(() => msg.value = '', 5000) }
const fmt = (f: string) => f ? f.split('-').reverse().join('/') : ''

interface Doc { tipo: string; tipoNombre: string; fecha: string; obs: string; file: File; nombreArch: string; ext: string }
const docs = ref<Doc[]>([])
const dTipo = ref(''); const dFecha = ref(''); const dObs = ref(''); const fileInput = ref<HTMLInputElement | null>(null); let dArchivo: File | null = null
const EXT_BLOQ = ['EXE', 'BAT', 'DLL', 'ZIP', 'RAR', 'CMD', 'CAB']

onMounted(async () => {
  try { const { data } = await api.get('/examenes/init'); medicos.value = data.medicos ?? []; tiposExamen.value = data.tipos_examen ?? []; tiposDoc.value = data.tipos_doc ?? [] }
  catch { flash('No se pudieron cargar los datos iniciales.', true) }
})

const onSelEmp = (r: any) => seleccionarEmp(r)
async function seleccionarEmp (r: any) {
  empCod.value = Number(r.PER_COD); empNombre.value = (r.PER_NOM || '').trim()
  fotoUrl.value = ''
  try { const { data } = await api.get(`/empleados/${r.PER_COD}/foto`); if (data?.foto) fotoUrl.value = data.foto } catch { /* sin foto */ }
}

// Enfermedad CIE10
const enfModal = ref(false); const inputEnf = ref<HTMLInputElement | null>(null)
const enfQ = ref(''); const enfRes = ref<any[]>([]); let de: any = null
async function abrirEnf () { enfModal.value = true; enfQ.value = enfCod.value || ''; enfRes.value = []; await nextTick(); inputEnf.value?.focus() }
const buscarEnf = () => {
  clearTimeout(de); const q = enfQ.value.trim()
  if (q.length < 1) { enfRes.value = []; return }
  de = setTimeout(async () => { try { enfRes.value = (await api.get('/enfermedades', { params: { buscar: q } })).data ?? [] } catch { enfRes.value = [] } }, 250)
}
function elegirEnf (e: any) { enfCod.value = e.cod; enfDes.value = e.nombre; enfUni.value = e.unico; enfModal.value = false }

// Documentos
const visor = ref<InstanceType<typeof DocViewer> | null>(null)
const verDoc = (dc: Doc) => visor.value?.open(dc.file, dc.file.name)
const onFile = (e: Event) => { dArchivo = (e.target as HTMLInputElement).files?.[0] ?? null }
function agregarDoc () {
  if (!dTipo.value) { flash('Elegí el tipo de documento.', true); return }
  if (!dArchivo) { flash('Seleccioná el archivo.', true); return }
  if (!dFecha.value) { flash('Indicá la fecha del documento.', true); return }
  const ext = (dArchivo.name.split('.').pop() || '').toUpperCase()
  if (!ext || EXT_BLOQ.includes(ext)) { flash('Extensión de archivo no válida.', true); return }
  const tn = tiposDoc.value.find(t => t.cod === dTipo.value)?.nombre || ''
  docs.value.push({ tipo: dTipo.value, tipoNombre: tn, fecha: dFecha.value, obs: dObs.value, file: dArchivo, nombreArch: dArchivo.name.replace(/\.[^.]+$/, ''), ext })
  dTipo.value = ''; dFecha.value = ''; dObs.value = ''; dArchivo = null; if (fileInput.value) fileInput.value.value = ''
}

async function confirmar () {
  if (medico.value <= 0) { flash('Debe identificar el médico responsable de este análisis.', true); return }
  if (!fechaExamen.value) { flash('Fecha del examen vacía.', true); return }
  if (tipoDoc.value === 'E' && !fechaProximo.value) { flash('Ingrese la fecha del próximo examen médico.', true); return }
  if (tipoExamen.value <= 0) { flash('Debe identificar el tipo de examen médico.', true); return }
  if (!confirm('¿Acepta el examen médico?')) return
  proc.value = true
  try {
    const fd = new FormData()
    fd.append('empleado', String(empCod.value)); fd.append('tipo_documento', tipoDoc.value)
    fd.append('medico_cod', String(medico.value)); fd.append('tipo_examen_cod', String(tipoExamen.value))
    fd.append('fecha_examen', fechaExamen.value); if (fechaProximo.value) fd.append('fecha_proximo', fechaProximo.value)
    fd.append('notas', notas.value)
    if (enfCod.value) { fd.append('enf_cod', enfCod.value); fd.append('enf_des', enfDes.value); fd.append('enf_uni', String(enfUni.value)) }
    docs.value.forEach((dc, i) => {
      fd.append(`documentos[${i}][tipo]`, dc.tipo); fd.append(`documentos[${i}][fecha]`, dc.fecha)
      fd.append(`documentos[${i}][obs]`, dc.obs); fd.append(`documentos[${i}][archivo]`, dc.file)
    })
    const { data } = await api.post('/examenes-medicos', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    if (data.doc_error) flash(data.doc_error, true)
    else flash('Examen médico registrado correctamente.')
    reset(true)
  } catch (e: any) { flash(e?.response?.data?.message ?? Object.values(e?.response?.data?.errors ?? {}).flat()[0] ?? 'No se pudo registrar el examen.', true) }
  finally { proc.value = false }
}

function reset (mantenerMsg = false) {
  empCod.value = 0; empNombre.value = ''; fotoUrl.value = ''
  medico.value = 0; tipoDoc.value = 'E'; tipoExamen.value = 0; fechaExamen.value = new Date().toISOString().slice(0, 10); fechaProximo.value = ''; notas.value = ''
  enfCod.value = ''; enfDes.value = ''; enfUni.value = 0
  docs.value = []; dTipo.value = ''; dFecha.value = ''; dObs.value = ''; dArchivo = null; if (fileInput.value) fileInput.value.value = ''
  if (!mantenerMsg) msg.value = ''
}
</script>

<style scoped>
.ex-view { display:flex; flex-direction:column; min-height:100%; }
.ex-cab { display:flex; align-items:center; gap:12px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.ex-ico { font-size:28px; } .ex-tx h1 { margin:0; font-size:19px; color:#1e293b; } .ex-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.ex-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ex-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ex-msg { margin:10px 18px 0; padding:9px 14px; border-radius:8px; font-size:13px; } .ex-msg.ok { background:#d1fae5; color:#065f46; } .ex-msg.err { background:#fee2e2; color:#991b1b; }
.ex-body { padding:16px 18px; max-width:900px; }
.ex-top { display:flex; gap:14px; align-items:flex-end; }
.campo { display:flex; flex-direction:column; gap:5px; } .campo.span2 { grid-column:1 / -1; }
.campo label { font-size:12px; font-weight:600; color:#374151; } .oblig { color:#dc2626; }
.ex-busc { position:relative; flex:1; } .ex-top .campo { flex:1; } .ex-busc input { width:100%; border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:14px; box-sizing:border-box; }
.ex-busc-lupa { display:flex; gap:8px; } .ex-busc-lupa input { flex:1; border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:14px; box-sizing:border-box; }
.ex-lupa { background:#394959; color:#fff; border:none; padding:9px 14px; border-radius:7px; cursor:pointer; font-weight:700; font-size:13px; white-space:nowrap; }
.ex-result { position:absolute; z-index:50; top:100%; left:0; right:0; margin:2px 0 0; padding:0; list-style:none; background:#fff; border:1px solid #d1d5db; border-radius:8px; box-shadow:0 12px 28px rgba(0,0,0,.18); max-height:240px; overflow:auto; }
.ex-result li { padding:8px 12px; cursor:pointer; border-bottom:1px solid #f1f5f9; display:flex; flex-direction:column; gap:2px; color:#1e293b; } .ex-result li:hover { background:#f0faf4; } .ex-result li b { font-size:13px; } .ex-result li span { font-size:11px; color:#6b7280; }
.ex-foto { width:130px; height:96px; border:1px solid #e2e8f0; border-radius:8px; overflow:hidden; background:#f8fafc; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.ex-foto img { width:100%; height:100%; object-fit:cover; } .ex-foto-ph { font-size:11px; color:#9ca3af; }
.ex-nombre { margin:12px 0 0; font-size:16px; font-weight:700; color:#14532d; }
.ex-form { display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-top:14px; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:14px; }
.ex-form select, .ex-form input, .ex-form textarea { border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:14px; color:#1e293b; box-sizing:border-box; width:100%; }
.ex-form input:disabled { background:#f1f5f9; color:#94a3b8; }
.ex-radios { display:flex; gap:18px; padding-top:4px; } .rad { display:flex; align-items:center; gap:6px; font-size:14px; color:#374151; cursor:pointer; }
.ex-enf { display:flex; gap:8px; align-items:center; } .ex-enf-cod { width:90px !important; flex:none !important; font-weight:700; } .ex-enf-des { flex:1; } .ex-enf input { background:#f8fafc !important; }
.ex-lupa { border:none; background:#2d6a9f; color:#fff; border-radius:6px; padding:8px 14px; font-size:13px; font-weight:700; cursor:pointer; white-space:nowrap; }
.ex-x { border:none; background:#fee2e2; color:#991b1b; border-radius:6px; padding:8px 10px; cursor:pointer; }
.ex-doc { margin-top:14px; background:#f8fafc; border:1px dashed #cbd5e1; border-radius:10px; padding:14px; }
.ex-doc-h { font-size:13px; color:#2a4a6a; margin-bottom:10px; } .ex-doc-h span { color:#94a3b8; font-weight:400; }
.ex-tabla { width:100%; border-collapse:collapse; font-size:12.5px; border:1px solid #e2e8f0; margin-bottom:12px; background:#fff; }
.ex-tabla th { background:#1e293b; color:#fff; padding:6px 8px; text-align:left; font-size:11px; } .ex-tabla td { padding:5px 8px; border-bottom:1px solid #f0f4f9; color:#1e293b; } .ex-cod { color:#2d6a9f; font-weight:700; } .ex-obs { color:#64748b; }
.ex-i { background:none; border:none; cursor:pointer; font-size:14px; }
.ex-doc-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
.ex-doc-grid select, .ex-doc-grid input { border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:14px; color:#1e293b; box-sizing:border-box; width:100%; }
.ex-nota { font-size:11px; color:#94a3b8; margin:8px 0 0; }
.btn { border:none; padding:9px 18px; border-radius:7px; cursor:pointer; font-size:13px; font-weight:700; } .btn.ok { background:#1b4332; color:#fff; } .btn.add { background:#d1fae5; color:#065f46; } .btn.reset { background:#eef2f7; color:#475569; } .btn:disabled { opacity:.5; cursor:default; }
.ex-acc { display:flex; gap:8px; align-items:center; margin-top:16px; } .ex-spacer { flex:1; }
.ex-ov { position:fixed; inset:0; background:rgba(15,23,42,.5); z-index:9000; display:flex; align-items:flex-start; justify-content:center; padding:60px 18px; }
.ex-enf-md { background:#fff; border-radius:14px; padding:20px; width:min(620px,94vw); } .ex-enf-md h3 { margin:0 0 12px; color:#1a3a5c; font-size:16px; }
.ex-enf-md input { width:100%; border:1px solid #c8d8ea; border-radius:7px; padding:9px 11px; font-size:14px; box-sizing:border-box; color:#1e293b; }
.ex-enf-list { list-style:none; margin:12px 0; padding:0; max-height:380px; overflow:auto; border:1px solid #eef2f7; border-radius:8px; }
.ex-enf-list li { padding:8px 12px; cursor:pointer; border-bottom:1px solid #f4f7fc; font-size:13px; color:#1e293b; } .ex-enf-list li:hover { background:#f0faf4; } .ex-enf-list li b { color:#2d6a9f; }
.ex-enf-vacio { color:#94a3b8; cursor:default !important; } .ex-enf-vacio:hover { background:none !important; }
.ex-help { margin:0; padding-left:18px; color:#334155; font-size:13.5px; line-height:1.7; }
.fade-enter-active,.fade-leave-active { transition:opacity .2s; } .fade-enter-from,.fade-leave-to { opacity:0; }
</style>
