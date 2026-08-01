<!-- BancoFrancesExportarView.vue — Bco. Francés (BBVA) - Exportar (empleados_exportar_bco_frances). -->
<template>
  <div class="bs-view">
    <div class="bs-cab">
      <div class="bs-cab-ico">📤</div>
      <div class="bs-cab-tx"><h1>Bco. Francés (BBVA) - Exportar</h1><p>Archivo de acreditación de sueldos para el Banco Francés (por CBU)</p></div>
      <BancoFrancesLogo :alto="34" class="bs-logo" />
      <button class="bs-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="bs-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
    </div>

    <BancoFrancesExportarAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/bco-frances" titulo="Asistente IA — Bco. Francés Exportar"
            subtitulo="Preguntá sobre la exportación al banco"
            :sugerencias="['¿Por qué un empleado figura en cero?','¿Qué significan las filas rojas?','¿Qué es la modalidad?']"
            @close="modalIA = false" />

    <div class="bs-filtros" v-enter-next>
      <div class="bs-f"><span class="bs-lbl">Empresa</span><select v-model.number="f.empresa" class="bs-sel"><option v-for="o in cat.empresas" :key="o.cod" :value="o.cod">{{ o.nombre }}</option></select></div>
      <div class="bs-f"><span class="bs-lbl">Contratista</span><select v-model.number="f.contratista" class="bs-sel"><option :value="0">Todas</option><option v-for="o in cat.contratistas" :key="o.cod" :value="o.cod">{{ o.nombre }}</option></select></div>
      <div class="bs-f"><span class="bs-lbl">Lugar</span><select v-model.number="f.lugar" class="bs-sel"><option :value="0">Todos</option><option v-for="o in cat.lugares" :key="o.cod" :value="o.cod">{{ o.nombre }}</option></select></div>
      <div class="bs-f"><span class="bs-lbl">Convenio</span><select v-model.number="f.convenio" class="bs-sel"><option :value="0">Todos</option><option v-for="o in cat.convenios" :key="o.cod" :value="o.cod">{{ o.nombre }}</option></select></div>
      <div class="bs-f"><span class="bs-lbl">Categoría</span><select v-model.number="f.categoria" class="bs-sel"><option :value="0">Todas</option><option v-for="o in cat.categorias" :key="o.cod" :value="o.cod">{{ o.nombre }}</option></select></div>
      <div class="bs-f bs-busc">
        <span class="bs-lbl">Empleado</span>
        <input v-model="busqueda" type="text" class="bs-inp" placeholder="Todos — buscar por código o nombre…" @input="buscar" @focus="buscar" />
        <button v-if="f.empleado" class="bs-clear" title="Quitar" @click="limpiarEmpleado">✕</button>
        <ul v-if="resultados.length" class="bs-result">
          <li v-for="r in resultados" :key="r.PER_COD" @click="selEmpleado(r)">{{ r.PER_COD }} — {{ (r.PER_NOM || '').trim() }}</li>
        </ul>
      </div>
      <div class="bs-f"><span class="bs-lbl">Mes</span><select v-model.number="f.mes" class="bs-sel chico"><option v-for="m in 12" :key="m" :value="m">{{ m }}</option></select></div>
      <div class="bs-f"><span class="bs-lbl">Año</span><input v-model.number="f.anio" type="number" class="bs-anio" /></div>
      <div class="bs-f"><span class="bs-lbl">Remuneración</span><select v-model.number="f.tipoRemu" class="bs-sel"><option :value="0">Todas</option><option v-for="t in cat.tipos" :key="t.cod" :value="t.cod">{{ t.desc }}</option></select></div>
      <div class="bs-f"><span class="bs-lbl">Liquidación (etiqueta)</span><select v-model.number="f.tipoSueldo" class="bs-sel"><option :value="0">Todas (COMPLETO)</option><option v-for="t in cat.tipos" :key="t.cod" :value="t.cod">{{ t.desc }}</option></select></div>
      <div class="bs-f"><span class="bs-lbl">Banco Depósito</span><select v-model.number="f.banco" class="bs-sel"><option v-for="o in cat.bancos" :key="o.cod" :value="o.cod">{{ o.nombre }}</option></select></div>
      <label class="bs-chk"><input type="checkbox" v-model="f.anticipos" /> Traer monto de anticipos</label>
      <button class="bs-btn-ok" :disabled="cargando" @click="consultar">{{ cargando ? '⟳…' : 'CONSULTAR' }}</button>
    </div>

    <div v-if="cargado && filas.length" class="bs-acciones">
      <button class="bs-mini" @click="todos(true)">TODOS</button>
      <button class="bs-mini" @click="todos(false)">NADA</button>
      <button class="bs-mini rojo" @click="eliminarSeleccionados">Eliminar Seleccionados</button>
      <button class="bs-mini rojo" @click="quitarCero">Quitar todos con importe cero</button>
      <span v-if="sinCuentaCount" class="bs-warn">⚠ {{ sinCuentaCount }} sin cuenta/CBU</span>
    </div>

    <div v-if="cargado && filas.length" class="bs-wrap">
      <table class="bs-grid">
        <thead><tr><th><input type="checkbox" :checked="todosMarcados" @change="todos(($event.target as HTMLInputElement).checked)" /></th>
          <th>Código</th><th>Nombre</th><th>T.Doc</th><th>Nro.Doc</th><th>Cuil</th><th class="r">Suc</th><th class="r">Cuenta</th><th>CBU</th><th class="r">Monto a Depositar</th></tr></thead>
        <tbody>
          <tr v-for="(r, i) in filas" :key="i" :class="{ sel: r.elegir, sincta: r.sinCuenta || r.sinCbu }">
            <td><input type="checkbox" v-model="r.elegir" /></td>
            <td>{{ r.cod }}</td><td>{{ r.nombre }}</td><td>{{ r.tdoc }}</td><td>{{ r.ndoc }}</td><td>{{ r.cuil }}</td>
            <td class="r" :class="{ rojo: r.sinCuenta }">{{ r.suc }}</td><td class="r" :class="{ rojo: r.sinCuenta }">{{ r.cta }}</td>
            <td :class="{ rojo: r.sinCbu }">{{ r.cbu || '—' }}</td>
            <td class="r"><input class="bs-monto" type="number" step="0.01" v-model.number="r.monto" @input="recalc" /></td>
          </tr>
        </tbody>
        <tfoot><tr class="bs-tfoot"><td colspan="9">TOTAL A DEPOSITAR</td><td class="r">{{ money(total) }}</td></tr></tfoot>
      </table>
    </div>
    <div v-else-if="cargado" class="bs-vacio">No se encontraron empleados para los filtros seleccionados.</div>

    <div v-if="cargado && filas.length" class="bs-pie">
      <div class="bs-gen">
        <div class="bs-f"><span class="bs-lbl">Fecha de Envío</span><input v-model="gen.fechaEnvio" type="date" class="bs-date" /></div>
        <div class="bs-f"><span class="bs-lbl">Fecha de Imputación</span><input v-model="gen.fechaImputacion" type="date" class="bs-date" /></div>
        <div class="bs-f"><span class="bs-lbl">Modalidad</span><input v-model="gen.modalidad" type="text" class="bs-mod" /></div>
        <button class="bs-btn-gen" :disabled="generando" @click="generar">{{ generando ? '⟳…' : 'GENERAR TXT PARA EL BANCO' }}</button>
      </div>
      <div class="bs-total-box">
        <span class="bs-total-lbl">TOTAL A DEPOSITAR</span>
        <span class="bs-total-val">$ {{ money(total) }}</span>
        <span class="bs-total-cant">{{ filas.length }} empleados</span>
      </div>
    </div>
    <p v-if="msg" :class="['bs-msg', msgError ? 'err' : 'ok']">{{ msg }}</p>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import api from '@/services/auth'
import BancoFrancesExportarAyuda from '@/components/BancoFrancesExportarAyuda.vue'
import BancoFrancesLogo from '@/components/BancoFrancesLogo.vue'
import ChatIA from '@/components/ChatIA.vue'
import { guardarComo, blobDeRespuesta } from '@/utils/descargas'

interface Fila { elegir: boolean; cod: number; nombre: string; tdoc: string; ndoc: string; cuil: string; suc: number; cta: number; monto: number; anti: number; dom: string; loc: string; cpa: string; cbu: string; sinCuenta: boolean; sinCbu: boolean }

const modalAyuda = ref(false); const modalIA = ref(false)
const hoy = new Date()
const _iso = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
const _prev = new Date(hoy.getFullYear(), hoy.getMonth() - 1, 1)

const f = reactive({ empresa: 0, contratista: 0, lugar: 0, convenio: 0, categoria: 0, empleado: 0, mes: _prev.getMonth() + 1, anio: _prev.getFullYear(), tipoRemu: 0, tipoSueldo: 0, banco: 5, anticipos: false })
const gen = reactive({ fechaEnvio: _iso(hoy), fechaImputacion: '', modalidad: 'PAGO DE SUELDOS' })
const cat = reactive<{ empresas: any[]; contratistas: any[]; lugares: any[]; convenios: any[]; categorias: any[]; tipos: any[]; bancos: any[] }>({ empresas: [], contratistas: [], lugares: [], convenios: [], categorias: [], tipos: [], bancos: [] })

const filas = ref<Fila[]>([]); const total = ref(0); const cargando = ref(false); const cargado = ref(false); const generando = ref(false)
const msg = ref(''); const msgError = ref(false)
const flash = (t: string, e = false) => { msg.value = t; msgError.value = e; if (t) setTimeout(() => msg.value = '', 8000) }
const nf = new Intl.NumberFormat('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const money = (v: number) => nf.format(Number(v || 0))

const recalc = () => { total.value = filas.value.reduce((a, r) => a + (r.monto || 0), 0) }
const sinCuentaCount = computed(() => filas.value.filter(r => r.sinCuenta || r.sinCbu).length)
const todosMarcados = computed(() => filas.value.length > 0 && filas.value.every(r => r.elegir))

const busqueda = ref(''); const resultados = ref<any[]>([]); let tB: any = null
const buscar = () => {
  clearTimeout(tB); const q = busqueda.value.trim()
  if (q.length < 2) { resultados.value = []; return }
  tB = setTimeout(async () => { try { resultados.value = (await api.get('/empleados', { params: { buscar: q, por_pagina: 8 } })).data.data ?? [] } catch { resultados.value = [] } }, 250)
}
const selEmpleado = (r: any) => { f.empleado = r.PER_COD; busqueda.value = `${r.PER_COD} — ${(r.PER_NOM || '').trim()}`; resultados.value = [] }
const limpiarEmpleado = () => { f.empleado = 0; busqueda.value = ''; resultados.value = [] }

const consultar = async () => {
  if (!f.empresa) return flash('Seleccioná la empresa.', true)
  cargando.value = true; cargado.value = true
  try {
    const params: any = {
      empresa: f.empresa, mes: f.mes, anio: f.anio, banco: f.banco, anticipos: f.anticipos ? 1 : 0,
      contratista: f.contratista, lugar: f.lugar, convenio: f.convenio, categoria: f.categoria, empleado: f.empleado,
      remuModo: f.tipoRemu ? 2 : 1, tipoRemu: f.tipoRemu, liquidacionModo: f.tipoSueldo ? 2 : 1, tipoSueldo: f.tipoSueldo,
    }
    const { data } = await api.get('/bco-frances/consultar', { params })
    filas.value = data.empleados || []; recalc()
  } catch (e) { console.error(e); filas.value = []; total.value = 0; flash('No se pudo consultar.', true) }
  finally { cargando.value = false }
}

const todos = (v: boolean) => filas.value.forEach(r => r.elegir = v)
const eliminarSeleccionados = () => { filas.value = filas.value.filter(r => !r.elegir); recalc() }
const quitarCero = () => { filas.value = filas.value.filter(r => r.monto !== 0); recalc() }

const generar = async () => {
  if (!filas.value.length) return flash('No hay empleados para generar.', true)
  if (!gen.fechaImputacion) return flash('Indicá la Fecha de Imputación.', true)
  const sinCbu = filas.value.filter(r => r.sinCbu).length
  if (sinCbu && !confirm(`Hay ${sinCbu} empleado(s) sin CBU válido. ¿Continuar igual?`)) return
  if (!confirm('Se generará el TXT y se registrará el lote. ¿Continuar?')) return
  generando.value = true
  try {
    const payload = {
      empresa: f.empresa, mes: f.mes, anio: f.anio,
      fechaEnvio: gen.fechaEnvio, fechaImputacion: gen.fechaImputacion, modalidad: gen.modalidad,
      liquidacionModo: f.tipoSueldo ? 2 : 1, tipoSueldo: f.tipoSueldo,
      filas: filas.value.map(r => ({ cod: r.cod, nombre: r.nombre, cuil: r.cuil, dom: r.dom, loc: r.loc, cpa: r.cpa, cbu: r.cbu, suc: r.suc, cta: r.cta, monto: r.monto })),
    }
    const { data } = await api.post('/bco-frances/generar', payload)
    await guardarComo(blobDeRespuesta(data), data.filename)
    flash(`Archivo ${data.filename} generado. Lote ${data.lote}, total ${money(data.total)}.`)
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo generar el archivo.', true) }
  finally { generando.value = false }
}

const mapCat = (arr: any[]) => arr.map((o: any) => ({ cod: o.cod ?? o.COD ?? o.id, nombre: (o.nombre ?? o.desc ?? o.det ?? o.descripcion ?? '').toString().trim() }))
onMounted(async () => {
  const fetch = async (url: string) => { try { return (await api.get(url)).data } catch { return [] } }
  cat.empresas = mapCat(await fetch('/empresas'))
  cat.contratistas = mapCat(await fetch('/contratistas'))
  cat.lugares = mapCat(await fetch('/lugares'))
  cat.convenios = mapCat(await fetch('/convenios'))
  cat.categorias = mapCat(await fetch('/categorias'))
  cat.bancos = mapCat(await fetch('/ctas-bancarias'))
  cat.tipos = (await fetch('/liquidaciones/tipos')).map((t: any) => ({ cod: t.cod ?? t.COD, desc: (t.desc ?? t.descripcion ?? '').toString().trim() }))
  const auto = cat.empresas.find((e: any) => e.nombre.toUpperCase().includes('AUTOELEVADORES'))
  if (auto) f.empresa = auto.cod; else if (cat.empresas[0]) f.empresa = cat.empresas[0].cod
  const fra = cat.bancos.find((b: any) => b.nombre.toUpperCase().includes('FRANCE') && !b.nombre.toUpperCase().includes('U$S'))
  if (fra) f.banco = fra.cod
})
</script>

<style scoped>
.bs-view { display:flex; flex-direction:column; height:100%; overflow:auto; }
.bs-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.bs-cab-ico { font-size:28px; } .bs-cab-tx h1 { margin:0; font-size:19px; color:#1e293b; } .bs-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.bs-logo { margin-left:18px; }
.bs-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.bs-btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.bs-filtros { display:flex; flex-wrap:wrap; align-items:flex-end; gap:12px; padding:14px 18px; background:#f8fafc; border-bottom:1px solid #e2e8f0; }
.bs-f { display:flex; flex-direction:column; gap:4px; position:relative; } .bs-lbl { font-size:12px; font-weight:700; color:#072146; }
.bs-sel { border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:13px; min-width:150px; color:#1e293b; } .bs-sel.chico { min-width:60px; }
.bs-anio { border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:13px; width:80px; }
.bs-busc { min-width:240px; } .bs-inp { border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:13px; color:#1e293b; min-width:240px; }
.bs-clear { position:absolute; right:6px; top:26px; background:none; border:none; color:#94a3b8; cursor:pointer; font-size:13px; }
.bs-result { position:absolute; top:100%; left:0; right:0; z-index:30; margin:2px 0 0; padding:0; list-style:none; background:#fff; border:1px solid #cbd5e1; border-radius:6px; max-height:220px; overflow:auto; box-shadow:0 8px 24px rgba(0,0,0,.15); }
.bs-result li { padding:7px 10px; font-size:13px; cursor:pointer; color:#1e293b; border-bottom:1px solid #f1f5f9; } .bs-result li:hover { background:#eff6ff; }
.bs-chk { display:flex; align-items:center; gap:6px; font-size:13px; color:#1e293b; }
.bs-btn-ok { background:#072146; color:#fff; border:none; padding:8px 22px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; } .bs-btn-ok:disabled { background:#cbd5e1; }
.bs-acciones { display:flex; align-items:center; gap:10px; padding:10px 18px 0; flex-wrap:wrap; }
.bs-mini { background:#fff; border:1px solid #cbd5e1; padding:6px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; color:#1e293b; }
.bs-mini.rojo { border-color:#fca5a5; color:#b91c1c; }
.bs-warn { font-size:12px; color:#b45309; font-weight:600; margin-left:auto; }
.bs-wrap { margin:10px 18px; overflow:auto; border:1px solid #e2e8f0; border-radius:8px; flex:1 1 auto; min-height:360px; }
.bs-grid { width:100%; border-collapse:collapse; font-size:13px; }
.bs-grid th { position:sticky; top:0; background:#072146; color:#fff; font-weight:600; padding:7px 10px; font-size:12px; text-align:left; } .bs-grid th.r { text-align:right; }
.bs-grid td { padding:5px 10px; border-bottom:1px solid #f1f5f9; color:#1e293b; } .bs-grid td.r { text-align:right; }
.bs-grid tr:nth-child(even) td { background:#f8fafc; }
.bs-grid tr.sel td { background:#fee2e2 !important; }
.bs-grid td.rojo { color:#dc2626; font-weight:800; background:#fef2f2 !important; }
.bs-monto { width:130px; text-align:right; border:1px solid #d1d5db; border-radius:4px; padding:3px 6px; font-size:13px; color:#1e293b; }
.bs-tfoot td { background:#0a2e5e !important; color:#fff !important; font-weight:800; padding:9px 10px; }
.bs-vacio { padding:40px; text-align:center; color:#9ca3af; font-size:14px; }
.bs-pie { display:flex; align-items:flex-end; gap:20px; padding:8px 18px 18px; flex-wrap:nowrap; }
.bs-gen { flex:1 1 auto; min-width:0; }
.bs-total-box { flex-shrink:0; }
.bs-total-box { margin-left:auto; display:flex; align-items:center; gap:12px; background:linear-gradient(120deg,#072146,#1e4fa0); color:#fff; padding:9px 16px; border-radius:9px; box-shadow:0 6px 18px rgba(7,33,70,.35); }
.bs-total-lbl { font-size:11px; font-weight:700; letter-spacing:.4px; opacity:.95; } .bs-total-val { font-size:18px; font-weight:800; } .bs-total-cant { font-size:11px; opacity:.9; }
.bs-gen { display:flex; align-items:flex-end; gap:12px; flex-wrap:wrap; }
.bs-date { border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:13px; color:#1e293b; }
.bs-mod { border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:13px; width:170px; color:#1e293b; }
.bs-btn-gen { background:#1e4fa0; color:#fff; border:none; padding:10px 18px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; } .bs-btn-gen:disabled { background:#cbd5e1; }
.bs-msg { padding:9px 14px; margin:0 18px 16px; font-size:13px; border-radius:6px; } .bs-msg.ok { background:#dcfce7; color:#166534; } .bs-msg.err { background:#fee2e2; color:#b91c1c; }
</style>
