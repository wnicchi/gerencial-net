<!-- AsignarCapacitacionView.vue — Asignar empleados a una capacitación (capacitacion_asignar.scx). -->
<template>
  <div class="ac-view">
    <div class="ac-cab">
      <div class="ac-ico">👥</div>
      <div class="ac-tx"><h1>Asignar Capacitación</h1><p>Asignación de empleados a una jornada</p></div>
      <button class="ac-ia" @click="modalIA = true">🤖 IA</button>
      <button class="ac-ayuda" @click="ayuda = true">❓ Ayuda</button>
    </div>

    <ChatIA v-if="modalIA" endpoint="/ia/asignacion-cap" titulo="Asistente IA — Asignar Capacitación"
            subtitulo="Preguntá sobre la asignación de empleados"
            :sugerencias="['¿Para qué sirve este módulo?','¿Cómo agrego por grupo?','¿Qué son los necesitados?','¿Cómo elimino un cursante?']"
            @close="modalIA = false" />

    <transition name="fade"><div v-if="msg" :class="['ac-msg', msgErr ? 'err' : 'ok']">{{ msg }}</div></transition>

    <div class="ac-body">
      <!-- Selector de jornada -->
      <div class="ac-sel">
        <label>Jornada de capacitación</label>
        <div class="ac-sel-row">
          <input :value="cab ? `${cab.cod} — ${cab.capacitacion}` : ''" readonly placeholder="Elegí una jornada con la lupa →" class="ac-sel-input" @click="abrirModalCap" />
          <button class="ac-lupa" @click="abrirModalCap">🔍 Buscar jornada</button>
        </div>
      </div>

      <template v-if="cab">
        <div class="ac-cols">
          <!-- Columna izquierda -->
          <div class="ac-left">
            <div class="ac-jornada">
              <div class="jr-cod">Capacitación #{{ cab.cod }}</div>
              <div class="jr-nom">{{ cab.capacitacion }}</div>
              <div class="jr-meta">
                <span v-if="cab.fecha">📅 {{ fmt(cab.fecha) }}</span>
                <span v-if="cab.desde || cab.hasta">🗓 {{ fmt(cab.desde) }} → {{ fmt(cab.hasta) }}</span>
                <span v-if="cab.disertante">🎤 {{ cab.disertante }}</span>
                <span v-if="cab.tematica">🏷 {{ cab.tematica }}</span>
              </div>
            </div>

            <div class="ac-filtros">
              <div class="ac-fh">Agregar personal a capacitar
                <label class="rad"><input type="radio" value="grupo" v-model="lote" /> Grupo</label>
                <label class="rad"><input type="radio" value="individuo" v-model="lote" /> Individuo</label>
              </div>

              <template v-if="lote === 'grupo'">
                <div class="fl"><span class="fl-lbl">Empresa</span>
                  <select v-model.number="empresa"><option :value="0">— Todas —</option><option v-for="o in combos.empresas" :key="o.id" :value="o.id">{{ o.label }}</option></select>
                </div>
                <FiltroFila v-for="f in filtroDefs" :key="f.key" :label="f.label" :opciones="combos[f.combo]" v-model="filtros[f.key]" />
              </template>

              <template v-else>
                <div class="fl"><span class="fl-lbl">Empleado</span>
                  <div class="ac-busc grow">
                    <input v-model="busqEmp" type="text" placeholder="Buscar empleado…" @input="buscarEmp" @focus="buscarEmp" />
                    <ul v-if="resEmp.length" class="ac-result">
                      <li v-for="r in resEmp" :key="r.PER_COD" @click="elegirEmp(r)"><b>{{ (r.PER_NOM||'').trim() }}</b><span>Cód. {{ r.PER_COD }}</span></li>
                    </ul>
                  </div>
                </div>
              </template>

              <button class="btn add" :disabled="proc" @click="agregar">➕ AGREGAR EMPLEADOS A CAPACITAR</button>
            </div>
          </div>

          <!-- Columna derecha: cursantes -->
          <div class="ac-right">
            <div class="ac-rh">
              <button class="btn nece" :disabled="proc" @click="agregarNecesitados">➕ AGREGAR TODOS LOS NECESITADOS</button>
            </div>
            <table class="ac-tabla">
              <thead><tr><th class="c" style="width:36px">OK</th><th style="width:64px">Código</th><th>Nombre</th><th style="width:130px">Cuil</th><th style="width:150px">Teléfono</th></tr></thead>
              <tbody>
                <tr v-for="(r, i) in cursantes" :key="i" :class="{ sel: r.sel }">
                  <td class="c"><input type="checkbox" v-model="r.sel" /></td>
                  <td class="ac-cod">{{ r.codigo }}</td><td>{{ r.nombre }}</td><td>{{ r.cuil }}</td><td>{{ r.telefono }}</td>
                </tr>
                <tr v-if="!cursantes.length"><td colspan="5" class="ac-vacio">Sin empleados asignados a esta capacitación.</td></tr>
              </tbody>
            </table>
            <div class="ac-racc">
              <button class="btn mini" @click="marcarTodos(true)">Todos</button>
              <button class="btn mini g" @click="marcarTodos(false)">Nada</button>
              <span class="ac-cont">{{ cursantes.length }} cursante{{ cursantes.length === 1 ? '' : 's' }}</span>
              <span class="ac-spacer"></span>
              <button class="btn del" :disabled="proc || !haySel" @click="eliminar">🗑 ELIMINAR SELECCIONADOS</button>
            </div>
          </div>
        </div>
      </template>
    </div>

    <Teleport to="body">
      <div v-if="modalCap" class="ac-ov" @click.self="modalCap = false">
        <div class="ac-busca-md">
          <h3>🔍 Buscar jornada de capacitación</h3>
          <input ref="inputCap" v-model="busqCap" placeholder="Código o nombre de la capacitación…" @input="buscarCap" />
          <ul class="ac-busca-list">
            <li v-for="c in resCap" :key="c.cod" @click="elegirCap(c)">
              <div class="bl-top"><b>{{ c.cod }}</b> — {{ c.capacitacion }}</div>
              <span v-if="c.tematica">{{ c.tematica }}</span>
            </li>
            <li v-if="!resCap.length" class="ac-busca-vacio">{{ busqCap.trim().length < 1 ? 'Escribí para buscar…' : 'Sin resultados' }}</li>
          </ul>
          <div class="ac-racc"><span class="ac-spacer"></span><button class="btn mini" @click="modalCap = false">Cerrar</button></div>
        </div>
      </div>
      <div v-if="ayuda" class="ac-ov" @click.self="ayuda = false">
        <div class="ac-help-md">
          <h3>❓ Ayuda — Asignar Capacitación</h3>
          <ul>
            <li>Elegí la <b>jornada</b> por código o nombre.</li>
            <li><b>Grupo</b>: agrega todo el personal activo que cumpla los filtros (empresa, contratista, lugar, sector, etc.).</li>
            <li><b>Individuo</b>: agrega un empleado puntual.</li>
            <li><b>Agregar todos los necesitados</b>: suma a quienes están marcados como que necesitan ese tema.</li>
            <li>A la derecha tildás cursantes y los quitás con <b>Eliminar seleccionados</b>. No se asignan duplicados.</li>
          </ul>
          <div class="ac-racc"><span class="ac-spacer"></span><button class="btn add" @click="ayuda = false">Cerrar</button></div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, reactive, onMounted, nextTick, h } from 'vue'
import api from '@/services/auth'
import ChatIA from '@/components/ChatIA.vue'

interface Opc { id: number; label: string }
interface Cursante { sel: boolean; codigo: number; nombre: string; cuil: string; telefono: string }
type FKey = 'contratista' | 'lugar' | 'sector' | 'subsector' | 'convenio' | 'categoria'

const norm = (x: any): Opc => ({ id: Number(x.cod ?? x.codigo ?? x.id ?? 0), label: (x.nombre ?? x.descripcion ?? x.des ?? x.detalle ?? x.cont_det ?? '').toString().trim() })

// Functional component para una fila de filtro Todas/Un + combo.
// Estilos inline (el CSS scoped no alcanza a los nodos creados con h()).
const stLbl = { width: '96px', fontSize: '12.5px', fontWeight: '600', color: '#374151' }
const stRad = { display: 'flex', alignItems: 'center', gap: '4px', fontSize: '12.5px', color: '#374151', cursor: 'pointer' }
const stSel = { border: '1px solid #d1d5db', borderRadius: '6px', padding: '6px 8px', fontSize: '12.5px', minWidth: '150px', color: '#1e293b' }
const stFila = { display: 'flex', alignItems: 'center', gap: '8px', marginBottom: '8px', flexWrap: 'wrap' as const }
const FiltroFila = (props: any, { emit }: any) => h('div', { style: stFila }, [
  h('span', { style: stLbl }, props.label),
  h('label', { style: stRad }, [h('input', { type: 'radio', checked: !props.modelValue.ind, onChange: () => emit('update:modelValue', { ind: false, cod: 0 }) }), ' Todas']),
  h('label', { style: stRad }, [h('input', { type: 'radio', checked: props.modelValue.ind, onChange: () => emit('update:modelValue', { ind: true, cod: props.modelValue.cod }) }), ' Un']),
  props.modelValue.ind
    ? h('select', { style: stSel, value: props.modelValue.cod, onChange: (e: any) => emit('update:modelValue', { ind: true, cod: Number(e.target.value) }) },
        [h('option', { value: 0 }, '— Seleccione —'), ...(props.opciones || []).map((o: Opc) => h('option', { value: o.id, key: o.id }, o.label))])
    : null,
])
;(FiltroFila as any).props = ['label', 'opciones', 'modelValue']
;(FiltroFila as any).emits = ['update:modelValue']

const combos = reactive<Record<string, Opc[]>>({ empresas: [], contratistas: [], lugares: [], sectores: [], subsectores: [], convenios: [], categorias: [] })
const filtroDefs: { key: FKey; label: string; combo: string }[] = [
  { key: 'contratista', label: 'Contratista', combo: 'contratistas' },
  { key: 'lugar', label: 'Lugar', combo: 'lugares' },
  { key: 'sector', label: 'Sector laboral', combo: 'sectores' },
  { key: 'subsector', label: 'Sub-sector', combo: 'subsectores' },
  { key: 'convenio', label: 'Convenio', combo: 'convenios' },
  { key: 'categoria', label: 'Categoría', combo: 'categorias' },
]
const empresa = ref(0)
const filtros = reactive<Record<FKey, { ind: boolean; cod: number }>>({
  contratista: { ind: false, cod: 0 }, lugar: { ind: false, cod: 0 }, sector: { ind: false, cod: 0 },
  subsector: { ind: false, cod: 0 }, convenio: { ind: false, cod: 0 }, categoria: { ind: false, cod: 0 },
})

const lote = ref<'grupo' | 'individuo'>('grupo')
const cab = ref<any>(null); const cursantes = ref<Cursante[]>([])
const proc = ref(false); const msg = ref(''); const msgErr = ref(false); const modalIA = ref(false); const ayuda = ref(false)
const haySel = computed(() => cursantes.value.some(c => c.sel))
const fmt = (f: string) => f ? f.split('-').reverse().join('/') : ''
const flash = (t: string, e = false) => { msg.value = t; msgErr.value = e; if (t && !e) setTimeout(() => msg.value = '', 4500) }

onMounted(async () => {
  try {
    const [e, c, l, s, ss, cv, ct] = await Promise.all([
      api.get('/empresas'), api.get('/contratistas'), api.get('/lugares'), api.get('/sectores'),
      api.get('/subsectores'), api.get('/convenios'), api.get('/categorias'),
    ])
    combos.empresas = (e.data ?? []).map(norm); combos.contratistas = (c.data ?? []).map(norm); combos.lugares = (l.data ?? []).map(norm)
    combos.sectores = (s.data ?? []).map(norm); combos.subsectores = (ss.data ?? []).map(norm); combos.convenios = (cv.data ?? []).map(norm); combos.categorias = (ct.data ?? []).map(norm)
  } catch { flash('No se pudieron cargar los filtros.', true) }
})

// ── Selección de jornada (modal con lupa) ──
const modalCap = ref(false); const inputCap = ref<HTMLInputElement | null>(null)
const busqCap = ref(''); const resCap = ref<any[]>([]); let dc: any = null
async function abrirModalCap () { modalCap.value = true; await nextTick(); inputCap.value?.focus() }
const buscarCap = () => {
  clearTimeout(dc); const q = busqCap.value.trim()
  if (q.length < 1) { resCap.value = []; return }
  dc = setTimeout(async () => { try { resCap.value = ((await api.get('/capacitaciones', { params: { buscar: q } })).data ?? []).slice(0, 20) } catch { resCap.value = [] } }, 250)
}
async function elegirCap (c: any) {
  modalCap.value = false; resCap.value = []; busqCap.value = ''
  await cargar(c.cod)
}
async function cargar (cod: number) {
  try {
    const { data } = await api.get(`/asignacion-cap/capacitacion/${cod}`)
    cab.value = data.cabecera; cursantes.value = (data.cursantes ?? []).map((x: any) => ({ ...x, sel: false }))
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo cargar la capacitación.', true) }
}

// ── Empleado (individuo) ──
const busqEmp = ref(''); const resEmp = ref<any[]>([]); const empSel = ref(0); let de: any = null
const buscarEmp = () => {
  clearTimeout(de); const q = busqEmp.value.trim()
  if (q.length < 2) { resEmp.value = []; return }
  de = setTimeout(async () => { try { resEmp.value = (await api.get('/empleados', { params: { buscar: q, por_pagina: 8 } })).data.data ?? [] } catch { resEmp.value = [] } }, 250)
}
const elegirEmp = (r: any) => { empSel.value = Number(r.PER_COD); busqEmp.value = `${r.PER_COD} — ${(r.PER_NOM || '').trim()}`; resEmp.value = [] }

// ── Acciones ──
async function agregar () {
  if (!cab.value) return
  if (lote.value === 'individuo' && empSel.value <= 0) { flash('Seleccione un empleado.', true); return }
  proc.value = true
  try {
    const body: any = { lote: lote.value }
    if (lote.value === 'individuo') body.empleado = empSel.value
    else {
      body.empresa = empresa.value
      for (const f of filtroDefs) body[f.key] = filtros[f.key].ind ? filtros[f.key].cod : 0
    }
    const { data } = await api.post(`/asignacion-cap/${cab.value.cod}/agregar`, body)
    cursantes.value = (data.cursantes ?? []).map((x: any) => ({ ...x, sel: false }))
    flash(data.agregados > 0 ? `Se agregaron ${data.agregados} empleado(s) a la capacitación.` : 'No se agregó ningún empleado (ya estaban asignados o no hubo coincidencias).', data.agregados === 0)
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo agregar.', true) }
  finally { proc.value = false }
}

async function agregarNecesitados () {
  if (!cab.value) return
  proc.value = true
  try {
    const { data } = await api.post(`/asignacion-cap/${cab.value.cod}/necesitados`)
    cursantes.value = (data.cursantes ?? []).map((x: any) => ({ ...x, sel: false }))
    flash(data.agregados > 0 ? `Se agregaron ${data.agregados} necesitado(s) del tema.` : 'No había necesitados pendientes para este tema.', data.agregados === 0)
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo agregar.', true) }
  finally { proc.value = false }
}

async function eliminar () {
  const sel = cursantes.value.filter(c => c.sel)
  if (!sel.length) return
  if (!confirm('¿Elimina al/los empleado(s) de esta capacitación?')) return
  proc.value = true
  try {
    const { data } = await api.post(`/asignacion-cap/${cab.value.cod}/eliminar`, { empleados: sel.map(c => c.codigo) })
    cursantes.value = (data.cursantes ?? []).map((x: any) => ({ ...x, sel: false }))
    flash('Cursante(s) eliminado(s).')
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo eliminar.', true) }
  finally { proc.value = false }
}

const marcarTodos = (v: boolean) => cursantes.value.forEach(c => c.sel = v)
</script>

<style scoped>
.ac-view { display:flex; flex-direction:column; min-height:100%; }
.ac-cab { display:flex; align-items:center; gap:12px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.ac-ico { font-size:28px; } .ac-tx h1 { margin:0; font-size:19px; color:#1e293b; } .ac-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.ac-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ac-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ac-msg { margin:10px 18px 0; padding:9px 14px; border-radius:8px; font-size:13px; } .ac-msg.ok { background:#d1fae5; color:#065f46; } .ac-msg.err { background:#fef3c7; color:#92400e; }
.ac-body { padding:16px 18px; }
.ac-sel { display:flex; flex-direction:column; gap:5px; max-width:680px; } .ac-sel label { font-size:12px; font-weight:600; color:#374151; }
.ac-sel-row { display:flex; gap:8px; align-items:stretch; }
.ac-sel-input { flex:1; border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:14px; box-sizing:border-box; background:#fff; color:#1e293b; cursor:pointer; }
.ac-lupa { border:none; background:#2d6a9f; color:#fff; border-radius:6px; padding:8px 16px; font-size:13px; font-weight:700; cursor:pointer; white-space:nowrap; }
.ac-busc { position:relative; } .ac-busc.grow { flex:1; } .ac-busc input { width:100%; border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:14px; box-sizing:border-box; color:#1e293b; }
.ac-busca-md { background:#fff; border-radius:14px; padding:20px; width:min(560px,94vw); } .ac-busca-md h3 { margin:0 0 12px; color:#1a3a5c; font-size:16px; }
.ac-busca-md input { width:100%; border:1px solid #c8d8ea; border-radius:7px; padding:9px 11px; font-size:14px; box-sizing:border-box; color:#1e293b; }
.ac-busca-list { list-style:none; margin:12px 0; padding:0; max-height:380px; overflow:auto; border:1px solid #eef2f7; border-radius:8px; }
.ac-busca-list li { padding:9px 12px; cursor:pointer; border-bottom:1px solid #f4f7fc; color:#1e293b; } .ac-busca-list li:hover { background:#f0faf4; }
.ac-busca-list .bl-top { font-size:13px; } .ac-busca-list li b { color:#2d6a9f; } .ac-busca-list li span { display:block; font-size:11px; color:#6b7280; margin-top:1px; }
.ac-busca-vacio { color:#94a3b8; cursor:default !important; } .ac-busca-vacio:hover { background:none !important; }
.ac-result { position:absolute; z-index:50; top:100%; left:0; right:0; margin:2px 0 0; padding:0; list-style:none; background:#fff; border:1px solid #d1d5db; border-radius:8px; box-shadow:0 12px 28px rgba(0,0,0,.18); max-height:260px; overflow:auto; }
.ac-result li { padding:8px 12px; cursor:pointer; border-bottom:1px solid #f1f5f9; display:flex; flex-direction:column; gap:1px; color:#1e293b; font-size:13px; } .ac-result li:hover { background:#f0faf4; } .ac-result li b { font-size:13px; color:#2d6a9f; } .ac-result li span { font-size:11px; color:#6b7280; }
.ac-cols { display:flex; gap:16px; margin-top:16px; align-items:flex-start; flex-wrap:wrap; }
.ac-left { flex:1; min-width:340px; max-width:460px; display:flex; flex-direction:column; gap:14px; }
.ac-right { flex:1.4; min-width:420px; }
.ac-jornada { background:#eef6ef; border:1px solid #c3e6cb; border-radius:10px; padding:12px 14px; }
.jr-cod { font-size:11px; color:#1b7a4b; font-weight:700; } .jr-nom { font-size:15px; font-weight:700; color:#14532d; margin:2px 0 6px; }
.jr-meta { display:flex; flex-wrap:wrap; gap:10px; font-size:12px; color:#475569; }
.ac-filtros { background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:14px; }
.ac-fh { font-weight:700; color:#2a4a6a; font-size:13px; margin-bottom:10px; display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
.fl { display:flex; align-items:center; gap:8px; margin-bottom:8px; flex-wrap:wrap; } .fl-lbl { width:96px; font-size:12.5px; font-weight:600; color:#374151; }
.rad { display:flex; align-items:center; gap:4px; font-size:12.5px; color:#374151; cursor:pointer; }
.fl select, .ac-filtros .fl :deep(select) { border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:12.5px; min-width:150px; }
.btn { border:none; padding:9px 14px; border-radius:7px; cursor:pointer; font-size:13px; font-weight:700; }
.btn.add { background:#1b4332; color:#fff; width:100%; margin-top:8px; } .btn.nece { background:#dbeafe; color:#1e40af; }
.btn.del { background:#fee2e2; color:#991b1b; } .btn.mini { background:#eef2f7; color:#334155; padding:5px 12px; font-size:12px; } .btn.mini.g { background:#e2e8f0; }
.btn:disabled { opacity:.5; cursor:default; }
.ac-rh { margin-bottom:8px; }
.ac-tabla { width:100%; border-collapse:collapse; font-size:13px; border:1px solid #e2e8f0; }
.ac-tabla th { background:#1e293b; color:#fff; padding:6px 8px; text-align:left; font-size:11.5px; } .ac-tabla th.c { text-align:center; }
.ac-tabla td { padding:5px 8px; border-bottom:1px solid #f0f4f9; color:#1e293b; } .ac-tabla td.c { text-align:center; }
.ac-tabla tr.sel td { background:#fffbe6; } .ac-cod { color:#2d6a9f; font-weight:700; }
.ac-tabla input[type=checkbox] { width:15px; height:15px; accent-color:#2d6a9f; }
.ac-vacio { text-align:center; color:#94a3b8; padding:16px; }
.ac-racc { display:flex; gap:8px; align-items:center; margin-top:10px; } .ac-spacer { flex:1; } .ac-cont { font-size:12px; color:#6b7280; }
.ac-ov { position:fixed; inset:0; background:rgba(15,23,42,.5); z-index:9000; display:flex; align-items:flex-start; justify-content:center; padding:50px 18px; }
.ac-help-md { background:#fff; border-radius:14px; padding:22px; width:min(520px,94vw); } .ac-help-md h3 { margin:0 0 12px; color:#1a3a5c; } .ac-help-md ul { margin:0 0 8px; padding-left:18px; color:#334155; font-size:13.5px; line-height:1.7; }
.fade-enter-active,.fade-leave-active { transition:opacity .2s; } .fade-enter-from,.fade-leave-to { opacity:0; }
</style>
