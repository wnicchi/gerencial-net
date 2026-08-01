<!-- HojasEvaluacionView.vue — Hojas de Evaluación de desempeño (una hoja por empleado) -->
<template>
  <div class="he-view">
    <div class="he-cab">
      <div class="he-ico">📝</div>
      <div class="he-tx"><h1>Hojas de Evaluación de desempeño</h1><p>Genera una hoja de evaluación por empleado</p></div>
    </div>

    <transition name="fade"><div v-if="msg" :class="['he-msg', msgErr ? 'err' : 'ok']">{{ msg }}</div></transition>

    <div class="he-opciones">
      <div class="he-fila">
        <label class="he-lbl">Empleados</label>
        <div class="he-radios"><label><input v-model.number="o.empleado" type="radio" :value="1" /> Todos</label><label><input v-model.number="o.empleado" type="radio" :value="2" /> Un</label></div>
        <div v-if="o.empleado === 2" class="he-busc">
          <input v-model="busqueda" type="text" placeholder="Buscar empleado…" @input="buscar" @focus="buscar" />
          <ul v-if="resultados.length" class="he-result">
            <li v-for="r in resultados" :key="r.PER_COD" @click="elegirEmp(r)"><b>{{ (r.PER_NOM || '').trim() }}</b><span>Cód. {{ r.PER_COD }} · Leg. {{ (r.PER_LEG || '').toString().trim() }}</span></li>
          </ul>
        </div>
        <span v-if="o.empleado === 2 && o.codigo" class="he-sel">✔ {{ empNom }}</span>
      </div>

      <FiltroCat label="Empresa" v-model:modo="o.empresa" v-model:id="o.empresa_id" :items="cat.empresas" />
      <FiltroCat label="Lugar" v-model:modo="o.lugar" v-model:id="o.lugar_id" :items="cat.lugares" />
      <FiltroCat label="Convenio" v-model:modo="o.convenio" v-model:id="o.convenio_id" :items="cat.convenios" />
      <FiltroCat label="Sector Laboral" v-model:modo="o.sector" v-model:id="o.sector_id" :items="cat.sectores" />
      <FiltroCat label="Sub-Sector" v-model:modo="o.subsector" v-model:id="o.subsector_id" :items="cat.subsectores" />

      <div class="he-acc">
        <button class="btn ok" :disabled="gen || (o.empleado === 2 && !o.codigo)" @click="presentar">{{ gen ? '⟳ Generando…' : '📄 Presentar Hojas de Evaluación' }}</button>
      </div>
    </div>

    <Teleport to="body">
      <div v-if="pdfUrl" class="he-pdf-ov" @click.self="cerrarPdf">
        <div class="he-pdf-md">
          <div class="he-pdf-head"><span>{{ pdfNombre }} ({{ cantidad }} hoja{{ cantidad === 1 ? '' : 's' }})</span>
            <div class="he-pdf-acc">
              <button class="he-pdf-b ok" @click="guardarDesdeUrl(pdfUrl, pdfNombre)">⬇ Descargar</button>
              <button class="he-pdf-b ok" @click="($refs.pf as HTMLIFrameElement)?.contentWindow?.print()">🖨 Imprimir</button>
              <button class="he-pdf-b cancel" @click="cerrarPdf">✕ Cerrar</button>
            </div>
          </div>
          <iframe ref="pf" :src="pdfUrl" class="he-pdf-frame"></iframe>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted, h, defineComponent } from 'vue'
import api from '@/services/auth'
import jsPDF from 'jspdf'
import { guardarDesdeUrl } from '@/utils/descargas'
import { dibujarHojaEvaluacion } from '@/utils/hojaEvaluacion'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()

// Sub-componente de filtro Todas/Un + combo
const FiltroCat = defineComponent({
  props: { label: String, modo: Number, id: Number, items: { type: Array as () => any[], default: () => [] } },
  emits: ['update:modo', 'update:id'],
  setup (props, { emit }) {
    return () => h('div', { class: 'he-fila' }, [
      h('label', { class: 'he-lbl' }, props.label),
      h('div', { class: 'he-radios' }, [
        h('label', [h('input', { type: 'radio', checked: props.modo === 1, onChange: () => emit('update:modo', 1) }), ' Todas']),
        h('label', [h('input', { type: 'radio', checked: props.modo === 2, onChange: () => emit('update:modo', 2) }), ' Un']),
      ]),
      props.modo === 2 ? h('select', { class: 'he-selc', value: props.id, onChange: (e: any) => emit('update:id', Number(e.target.value)) },
        [h('option', { value: 0 }, '— Seleccioná —'), ...props.items.map((it: any) => h('option', { value: catCod(it) }, catNom(it)))]) : null,
    ])
  },
})
const catCod = (it: any) => it.cod ?? it.codigo ?? it.COD ?? it.id ?? 0
const catNom = (it: any) => (it.nombre ?? it.descripcion ?? it.des ?? it.DES ?? '').toString().trim()

const o = reactive({ empleado: 1, codigo: 0, empresa: 1, empresa_id: 0, lugar: 1, lugar_id: 0, convenio: 1, convenio_id: 0, sector: 1, sector_id: 0, subsector: 1, subsector_id: 0 })
const cat = reactive<{ empresas: any[]; lugares: any[]; convenios: any[]; sectores: any[]; subsectores: any[] }>({ empresas: [], lugares: [], convenios: [], sectores: [], subsectores: [] })
const gen = ref(false); const msg = ref(''); const msgErr = ref(false); const cantidad = ref(0)
const flash = (t: string, e = false) => { msg.value = t; msgErr.value = e; if (t && !e) setTimeout(() => msg.value = '', 4000) }

const empNom = ref('')
const busqueda = ref(''); const resultados = ref<any[]>([])
let dq: any = null
const buscar = () => {
  clearTimeout(dq)
  const q = busqueda.value.trim(); if (q.length < 2) { resultados.value = []; return }
  dq = setTimeout(async () => { try { resultados.value = (await api.get('/empleados', { params: { buscar: q, por_pagina: 8 } })).data.data ?? [] } catch { resultados.value = [] } }, 250)
}
const elegirEmp = (r: any) => { resultados.value = []; o.codigo = Number(r.PER_COD); empNom.value = `${r.PER_COD} — ${(r.PER_NOM || '').trim()}`; busqueda.value = empNom.value }

const logoDataUrl = ref('')
onMounted(async () => {
  try {
    const [e, l, c, s, ss] = await Promise.all([api.get('/empresas'), api.get('/lugares'), api.get('/convenios'), api.get('/sectores'), api.get('/subsectores')])
    cat.empresas = e.data ?? []; cat.lugares = l.data ?? []; cat.convenios = c.data ?? []; cat.sectores = s.data ?? []; cat.subsectores = ss.data ?? []
  } catch { /* */ }
  try { const b = await (await fetch(auth.logoEmpresa)).blob(); logoDataUrl.value = await new Promise<string>(res => { const fr = new FileReader(); fr.onload = () => res(fr.result as string); fr.readAsDataURL(b) }) } catch { /* */ }
})

async function presentar () {
  gen.value = true
  try {
    const params: any = {
      empleado: o.empleado, codigo: o.codigo,
      empresa: o.empresa, empresa_id: o.empresa_id, lugar: o.lugar, lugar_id: o.lugar_id,
      convenio: o.convenio, convenio_id: o.convenio_id, sector: o.sector, sector_id: o.sector_id,
      subsector: o.subsector, subsector_id: o.subsector_id,
    }
    const { data } = await api.get('/puestos/hojas-evaluacion', { params })
    if (!data.empleados.length) { flash('No hay empleados para los filtros seleccionados.', true); return }
    generar(data.empleados)
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo generar.', true) }
  finally { gen.value = false }
}

const pdfUrl = ref(''); const pdfNombre = ref('')
const cerrarPdf = () => { if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value); pdfUrl.value = '' }

function generar (empleados: any[]) {
  const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })
  empleados.forEach((e, i) => { if (i > 0) doc.addPage(); dibujarHojaEvaluacion(doc, e, logoDataUrl.value) })
  cantidad.value = empleados.length
  cerrarPdf(); pdfNombre.value = 'Hojas_de_evaluacion.pdf'; pdfUrl.value = URL.createObjectURL(doc.output('blob'))
}
</script>

<style scoped>
.he-view { display:flex; flex-direction:column; height:100%; overflow:auto; }
.he-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.he-ico { font-size:28px; } .he-tx h1 { margin:0; font-size:19px; color:#1e293b; } .he-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.he-msg { margin:10px 18px 0; padding:9px 14px; border-radius:8px; font-size:13px; } .he-msg.ok { background:#d1fae5; color:#065f46; } .he-msg.err { background:#fee2e2; color:#991b1b; }
.he-opciones { margin:18px; max-width:760px; background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:18px; display:flex; flex-direction:column; gap:13px; }
:deep(.he-fila) { display:flex; align-items:center; gap:14px; flex-wrap:wrap; }
:deep(.he-lbl) { width:120px; font-size:13px; font-weight:600; color:#374151; flex-shrink:0; }
:deep(.he-radios) { display:flex; gap:14px; } :deep(.he-radios label) { font-size:13px; font-weight:600; color:#1b4332; display:flex; align-items:center; gap:4px; cursor:pointer; }
:deep(.he-selc) { border:1px solid #c8d8ea; border-radius:6px; padding:7px 9px; font-size:13px; color:#1e293b; min-width:280px; }
.he-busc { position:relative; flex:1; min-width:240px; }
.he-busc input { width:100%; border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:14px; }
.he-result { position:absolute; z-index:50; top:100%; left:0; right:0; margin:2px 0 0; padding:0; list-style:none; background:#fff; border:1px solid #d1d5db; border-radius:8px; box-shadow:0 12px 28px rgba(0,0,0,.18); max-height:240px; overflow:auto; }
.he-result li { padding:8px 12px; cursor:pointer; border-bottom:1px solid #f1f5f9; display:flex; flex-direction:column; gap:2px; }
.he-result li:hover { background:#f0faf4; } .he-result li b { font-size:13px; color:#1e293b; } .he-result li span { font-size:11px; color:#6b7280; }
.he-sel { font-size:13px; color:#166534; font-weight:600; }
.he-acc { margin-top:6px; }
.btn { border:none; padding:12px 26px; border-radius:7px; cursor:pointer; font-size:14px; font-weight:700; } .btn.ok { background:#2563eb; color:#fff; } .btn:disabled { opacity:.5; cursor:default; }
.he-pdf-ov { position:fixed; inset:0; background:rgba(15,23,42,.6); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.he-pdf-md { width:min(820px,97vw); height:92vh; background:#fff; border-radius:10px; overflow:hidden; display:flex; flex-direction:column; }
.he-pdf-head { display:flex; align-items:center; gap:12px; padding:10px 14px; background:#1b4332; color:#fff; font-size:13px; }
.he-pdf-acc { margin-left:auto; display:flex; gap:8px; }
.he-pdf-b { border:none; padding:7px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; }
.he-pdf-b.ok { background:#22c55e; color:#fff; } .he-pdf-b.cancel { background:#ef4444; color:#fff; }
.he-pdf-frame { flex:1; border:none; width:100%; }
.fade-enter-active,.fade-leave-active { transition:opacity .2s; } .fade-enter-from,.fade-leave-to { opacity:0; }
</style>
