<!-- BancoSantaFeConsultarView.vue — Bco. Santa Fe - Consultar (empleados_consultar_bco_sf). -->
<template>
  <div class="bc-view">
    <div class="bc-cab">
      <div class="bc-cab-ico">🔍</div>
      <div class="bc-cab-tx"><h1>Bco. Santa Fe - Consultar</h1><p>Lotes de pago exportados — autorización y regeneración</p></div>
      <BancoSantaFeLogo :alto="40" class="bc-logo" />
      <button class="bc-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="bc-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
    </div>

    <BancoSantaFeConsultarAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/bco-santa-fe-consultar" titulo="Asistente IA — Bco. Santa Fe Consultar"
            subtitulo="Preguntá sobre los lotes y autorizaciones"
            :sugerencias="['¿Qué hace Generar el movimiento de pagos?','¿Qué significa un lote anulado?','¿Cómo regenero un TXT?']"
            @close="modalIA = false" />

    <div class="bc-filtros" v-enter-next>
      <div class="bc-f"><span class="bc-lbl">Empresa</span><select v-model.number="empresa" class="bc-sel"><option v-for="o in cat.empresas" :key="o.cod" :value="o.cod">{{ o.nombre }}</option></select></div>
      <button class="bc-btn-ok" :disabled="cargando" @click="cargarLotes">{{ cargando ? '⟳…' : 'CONSULTAR' }}</button>
      <span v-if="lotes.length" class="bc-info">{{ lotes.length }} lotes · seleccionados: {{ lotesSel.length }}</span>
    </div>

    <div class="bc-cols">
      <!-- Lotes -->
      <div class="bc-panel">
        <div class="bc-panel-tit">Lotes (Haberes Bco. Santa Fe)</div>
        <div class="bc-wrap">
          <table class="bc-grid">
            <thead><tr><th>OK</th><th>Nro.</th><th>Empresa</th><th>Concepto</th><th>T.Arc.</th><th>Fecha</th><th class="r">Monto</th></tr></thead>
            <tbody>
              <tr v-for="l in lotes" :key="l.nro" :class="{ anul: l.anulado, pag: l.pagado, activo: l.nro === loteActivo }" @click="abrirLote(l)">
                <td @click.stop><input type="checkbox" :checked="l.elegir" @change="seleccionarLote(l)" /></td>
                <td>{{ l.nro }}</td><td>{{ l.empresa }}</td><td>{{ l.concepto }}</td><td>{{ l.tipoArc }}</td><td>{{ l.fecha }}</td>
                <td class="r">{{ money(l.monto) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Beneficiarios -->
      <div class="bc-panel">
        <div class="bc-panel-tit">Empleados del lote {{ loteActivo || '—' }}</div>
        <div class="bc-wrap">
          <table class="bc-grid">
            <thead><tr><th>OK</th><th>Empleado</th><th class="r">Suc</th><th class="r">Cuenta</th><th class="r">Acredita</th></tr></thead>
            <tbody>
              <tr v-for="(b, i) in bene" :key="i" :class="{ edit: b.mon !== b.montoInicial }">
                <td><input type="checkbox" v-model="b.elegir" /></td>
                <td>{{ b.emd }}</td><td class="r">{{ b.suc }}</td><td class="r">{{ b.cta }}</td>
                <td class="r"><input class="bc-monto" type="number" step="0.01" v-model.number="b.mon" /></td>
              </tr>
              <tr v-if="!bene.length"><td colspan="5" class="bc-empty">Hacé clic en un lote para ver sus empleados.</td></tr>
            </tbody>
            <tfoot v-if="bene.length"><tr class="bc-tfoot"><td colspan="4">TOTAL</td><td class="r">{{ money(totalBene) }}</td></tr></tfoot>
          </table>
        </div>
        <div v-if="bene.length" class="bc-bene-acc">
          <button class="bc-mini" @click="quitarEmpleado">Quitar empleado de la lista</button>
          <button class="bc-mini" @click="quitarCero">Quitar todos con importe cero</button>
          <button class="bc-mini" @click="grabarCambios">Grabar cambios</button>
          <button class="bc-mini azul" @click="imprimirPdf">PDF</button>
        </div>
      </div>
    </div>

    <!-- Acciones inferiores -->
    <div class="bc-acciones">
      <div class="bc-pagos">
        <div class="bc-pagos-tit">GENERAR PAGOS VARIOS</div>
        <select v-model.number="movimiento.tipoSueldo" class="bc-sel"><option :value="0">— Concepto (tipo de sueldo) —</option><option v-for="t in cat.tipos" :key="t.cod" :value="t.cod">{{ t.desc }}</option></select>
        <label class="bc-lbl2">Nº Autorización Bancario</label>
        <input v-model.number="movimiento.nroBancario" type="number" class="bc-inp" />
        <button class="bc-btn-mov" :disabled="procesando" @click="generarMovimiento">{{ procesando ? '⟳…' : 'GENERAR EL MOVIMIENTO DE PAGOS' }}</button>
      </div>

      <div class="bc-derecha">
        <div class="bc-acc-top">
          <button class="bc-mini rojo" @click="eliminarLotes">Eliminar Seleccionado</button>
        </div>
        <div class="bc-gen">
          <BancoSantaFeLogo :alto="34" />
          <div class="bc-f"><span class="bc-lbl">Fecha de Envío</span><input v-model="gen.fechaEnvio" type="date" class="bc-date" /></div>
          <div class="bc-f"><span class="bc-lbl">Fecha de Imputación</span><input v-model="gen.fechaImputacion" type="date" class="bc-date" /></div>
          <button class="bc-btn-txt" :disabled="generando || !loteActivo" @click="generarNuevoTxt">{{ generando ? '⟳…' : 'GENERAR NUEVO TXT' }}</button>
        </div>
      </div>
    </div>
    <p v-if="msg" :class="['bc-msg', msgError ? 'err' : 'ok']">{{ msg }}</p>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import api from '@/services/auth'
import BancoSantaFeConsultarAyuda from '@/components/BancoSantaFeConsultarAyuda.vue'
import BancoSantaFeLogo from '@/components/BancoSantaFeLogo.vue'
import ChatIA from '@/components/ChatIA.vue'
import { guardarComo } from '@/utils/descargas'
import { pdfAcreditaciones } from '@/utils/pdfAcreditaciones'
import { svgToPng } from '@/utils/svgToPng'

interface Lote { elegir: boolean; nro: number; empresa: string; concepto: string; tipoArc: string; fecha: string; monto: number; anulado: boolean; pagado: boolean }
interface Bene { elegir: boolean; emp: number; emd: string; suc: number; cta: number; mon: number; montoInicial: number; leg: number }

const modalAyuda = ref(false); const modalIA = ref(false)
const hoy = new Date()
const _iso = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`

const empresa = ref(0)
const cat = reactive<{ empresas: any[]; tipos: any[] }>({ empresas: [], tipos: [] })
const lotes = ref<Lote[]>([]); const bene = ref<Bene[]>([]); const loteActivo = ref(0)
const cargando = ref(false); const procesando = ref(false); const generando = ref(false)
const movimiento = reactive({ tipoSueldo: 0, nroBancario: 0 })
const gen = reactive({ fechaEnvio: _iso(hoy), fechaImputacion: _iso(hoy) })
const msg = ref(''); const msgError = ref(false)
const flash = (t: string, e = false) => { msg.value = t; msgError.value = e; if (t) setTimeout(() => msg.value = '', 9000) }
const nf = new Intl.NumberFormat('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const money = (v: number) => nf.format(Number(v || 0))

const lotesSel = computed(() => lotes.value.filter(l => l.elegir))
const totalBene = computed(() => bene.value.reduce((a, b) => a + (b.mon || 0), 0))

// Solo un lote seleccionable a la vez. El Nº de autorización bancario = número del lote.
const seleccionarLote = (l: Lote) => {
  const nuevo = !l.elegir
  lotes.value.forEach(x => { x.elegir = false })
  l.elegir = nuevo
  movimiento.nroBancario = nuevo ? l.nro : 0
  if (nuevo) abrirLote(l)
}

const cargarLotes = async () => {
  cargando.value = true
  try { lotes.value = (await api.get('/bco-santa-fe/lotes', { params: { empresa: empresa.value } })).data.lotes || []; bene.value = []; loteActivo.value = 0 }
  catch (e) { console.error(e); flash('No se pudieron cargar los lotes.', true) } finally { cargando.value = false }
}
const abrirLote = async (l: Lote) => {
  loteActivo.value = l.nro
  try { bene.value = (await api.get('/bco-santa-fe/beneficiarios', { params: { lote: l.nro } })).data.beneficiarios || [] }
  catch (e) { console.error(e); bene.value = [] }
}

const quitarEmpleado = () => { bene.value = bene.value.filter(b => !b.elegir) }
const quitarCero = () => { bene.value = bene.value.filter(b => b.mon !== 0) }
const grabarCambios = () => flash('Cambios guardados en memoria. Se aplicarán al Generar nuevo TXT.')

const generarMovimiento = async () => {
  const sel = lotesSel.value
  if (!sel.length) return flash('Seleccioná el/los lote(s) a confirmar el pago.', true)
  if (!movimiento.tipoSueldo) return flash('Seleccioná el concepto (tipo de sueldo) del pago.', true)
  if (!movimiento.nroBancario || movimiento.nroBancario <= 0) return flash('Ingresá el número de autorización bancario.', true)
  if (!confirm(`¿Generar el pago en el sistema para ${sel.length} lote(s)? Quedarán para que autorice Gerencia.`)) return
  procesando.value = true
  try {
    const { data } = await api.post('/bco-santa-fe/generar-movimiento', {
      lotes: sel.map(l => l.nro), tipoSueldo: movimiento.tipoSueldo, nroBancario: movimiento.nroBancario, empresa: empresa.value,
    })
    const yaEnv = (data.resultados || []).filter((r: any) => r.estado === 'ya_enviado').map((r: any) => r.lote)
    const errs = (data.resultados || []).filter((r: any) => r.estado === 'error').map((r: any) => r.lote)
    let txt = data.message
    if (yaEnv.length) txt += ` Ya enviados (no duplicados): ${yaEnv.join(', ')}.`
    if (errs.length) txt += ` Con error: ${errs.join(', ')}.`
    flash(txt, errs.length > 0)
    sel.forEach(l => l.pagado = true)
    movimiento.tipoSueldo = 0; movimiento.nroBancario = 0
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo generar el movimiento.', true) }
  finally { procesando.value = false }
}

const eliminarLotes = async () => {
  const sel = lotesSel.value
  if (!sel.length) return flash('Seleccioná el/los lote(s) a eliminar.', true)
  if (!confirm(`¿Anular ${sel.length} lote(s) de SFHABERES seleccionado(s)?`)) return
  try {
    await api.post('/bco-santa-fe/eliminar', { lotes: sel.map(l => l.nro), nroBancario: movimiento.nroBancario || 0 })
    sel.forEach(l => { l.anulado = true; l.elegir = false })
    flash('Lote(s) anulado(s).')
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo anular.', true) }
}

const generarNuevoTxt = async () => {
  if (!loteActivo.value) return flash('Abrí un lote primero.', true)
  if (!bene.value.length) return flash('El lote no tiene empleados.', true)
  if (!gen.fechaImputacion) return flash('Indicá la Fecha de Imputación.', true)
  if (!confirm('Se creará un NUEVO lote y su TXT con los empleados/montos actuales. ¿Continuar?')) return
  generando.value = true
  try {
    const { data } = await api.post('/bco-santa-fe/generar-nuevo-txt', {
      loteOrigen: loteActivo.value, fechaEnvio: gen.fechaEnvio, fechaImputacion: gen.fechaImputacion,
      beneficiarios: bene.value.map(b => ({ emp: b.emp, emd: b.emd, suc: b.suc, cta: b.cta, mon: b.mon })),
    })
    await guardarComo(new Blob([data.contenido], { type: 'text/plain' }), data.filename)
    flash(`Nuevo lote ${data.lote} generado. Archivo ${data.filename}, total ${money(data.total)}.`)
    await cargarLotes()
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo generar el TXT.', true) }
  finally { generando.value = false }
}

const imprimirPdf = async () => {
  if (!bene.value.length) return
  const lote = lotes.value.find(l => l.nro === loteActivo.value)
  const cuentaFmt = (b: Bene) => `${b.cta}/${String(b.suc).padStart(2, '0')}`   // ej. 46254205/06
  // Rasteriza el logo (la flor) que se ve en pantalla para incrustarlo arriba a la derecha.
  let bancoLogoUrl: string | undefined; let bancoLogoRatio = 1
  const svg = document.querySelector('.bc-logo svg') as SVGSVGElement | null
  if (svg) { const png = await svgToPng(svg); if (png) { bancoLogoUrl = png.url; bancoLogoRatio = png.ratio } }
  const url = pdfAcreditaciones({
    banco: 'Nuevo Banco Santa Fe',
    empresa: (lote?.empresa || '').trim(),
    concepto: (lote?.concepto || '').trim(),
    fecha: fmtFecha(lote?.fecha || ''),
    monto: totalBene.value,
    nroInterno: loteActivo.value,
    bancoLogoUrl, bancoLogoRatio,
    filas: bene.value.map(b => ({ legajo: b.leg, nombre: b.emd, cuenta: cuentaFmt(b), importe: b.mon })),
  })
  window.open(url, '_blank')
}
const fmtFecha = (iso: string) => { if (!iso) return ''; const s = String(iso).slice(0, 10); const [a, m, d] = s.split('-'); return d ? `${d}/${m}/${a}` : s }

const mapCat = (arr: any[]) => arr.map((o: any) => ({ cod: o.cod ?? o.COD ?? o.id, nombre: (o.nombre ?? o.desc ?? o.det ?? o.descripcion ?? '').toString().trim() }))
onMounted(async () => {
  const fetch = async (url: string) => { try { return (await api.get(url)).data } catch { return [] } }
  cat.empresas = mapCat(await fetch('/empresas'))
  cat.tipos = (await fetch('/liquidaciones/tipos')).map((t: any) => ({ cod: t.cod ?? t.COD, desc: (t.desc ?? t.descripcion ?? '').toString().trim() }))
  const auto = cat.empresas.find((e: any) => e.nombre.toUpperCase().includes('AUTOELEVADORES'))
  if (auto) empresa.value = auto.cod; else if (cat.empresas[0]) empresa.value = cat.empresas[0].cod
  await cargarLotes()
})
</script>

<style scoped>
.bc-view { display:flex; flex-direction:column; height:100%; overflow:auto; }
.bc-cab { display:flex; align-items:center; gap:14px; padding:12px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.bc-cab-ico { font-size:26px; } .bc-cab-tx h1 { margin:0; font-size:18px; color:#1e293b; } .bc-cab-tx p { margin:2px 0 0; font-size:12px; color:#6b7280; }
.bc-logo { margin-left:18px; padding:5px 12px; background:#fff; border:1px solid #e2e8f0; border-radius:8px; }
.bc-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:8px 13px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.bc-btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:8px 11px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.bc-filtros { display:flex; align-items:flex-end; gap:14px; padding:12px 18px; background:#f8fafc; border-bottom:1px solid #e2e8f0; }
.bc-f { display:flex; flex-direction:column; gap:4px; } .bc-lbl { font-size:12px; font-weight:700; color:#1b4332; }
.bc-sel { border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:13px; min-width:200px; color:#1e293b; }
.bc-btn-ok { background:#16a34a; color:#fff; border:none; padding:8px 22px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; } .bc-btn-ok:disabled { background:#cbd5e1; }
.bc-info { font-size:12px; color:#475569; }
.bc-cols { display:grid; grid-template-columns:1fr 1fr; gap:14px; padding:14px 18px; }
.bc-panel { border:1px solid #e2e8f0; border-radius:8px; overflow:hidden; display:flex; flex-direction:column; }
.bc-panel-tit { background:#1b4332; color:#fff; font-size:12px; font-weight:700; padding:7px 12px; }
.bc-wrap { overflow:auto; max-height:40vh; }
.bc-grid { width:100%; border-collapse:collapse; font-size:12.5px; }
.bc-grid th { position:sticky; top:0; background:#334155; color:#fff; font-weight:600; padding:6px 8px; font-size:11.5px; text-align:left; } .bc-grid th.r { text-align:right; }
.bc-grid td { padding:4px 8px; border-bottom:1px solid #f1f5f9; color:#1e293b; } .bc-grid td.r { text-align:right; }
.bc-grid tbody tr { cursor:pointer; }
.bc-grid tbody tr.pag td { background:#dcfce7; }
.bc-grid tbody tr.anul td { background:#fee2e2; color:#b91c1c; text-decoration:line-through; }
.bc-grid tbody tr.activo td { outline:2px solid #2563eb; outline-offset:-2px; }
.bc-grid tbody tr.edit td { background:#fef9c3; }
.bc-monto { width:110px; text-align:right; border:1px solid #d1d5db; border-radius:4px; padding:2px 6px; font-size:12.5px; }
.bc-empty { text-align:center; color:#9ca3af; padding:24px; }
.bc-tfoot td { background:#14532d !important; color:#fff !important; font-weight:800; padding:7px 8px; }
.bc-bene-acc { display:flex; gap:8px; padding:8px 10px; background:#f8fafc; border-top:1px solid #e2e8f0; flex-wrap:wrap; }
.bc-mini { background:#fff; border:1px solid #cbd5e1; padding:6px 11px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; color:#1e293b; }
.bc-mini.rojo { border-color:#fca5a5; color:#b91c1c; } .bc-mini.azul { border-color:#93c5fd; color:#1d4ed8; }
.bc-acciones { display:grid; grid-template-columns:auto 1fr; gap:18px; padding:0 18px 16px; align-items:stretch; }
.bc-pagos { display:flex; flex-direction:column; gap:8px; background:#fffbe6; border:1px solid #fde68a; border-radius:10px; padding:12px 16px; max-width:360px; }
.bc-pagos-tit { font-size:13px; font-weight:800; color:#92400e; }
.bc-lbl2 { font-size:12px; font-weight:700; color:#92400e; }
.bc-inp { border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:13px; width:120px; }
.bc-btn-mov { background:#16a34a; color:#fff; border:none; padding:12px; border-radius:8px; cursor:pointer; font-size:13px; font-weight:800; } .bc-btn-mov:disabled { background:#cbd5e1; }
.bc-derecha { display:flex; flex-direction:column; gap:10px; }
.bc-acc-top { display:flex; gap:10px; }
.bc-gen { display:flex; align-items:flex-end; gap:14px; flex-wrap:wrap; background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:12px 16px; }
.bc-date { border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:13px; color:#1e293b; }
.bc-btn-txt { background:#2563eb; color:#fff; border:none; padding:10px 18px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; } .bc-btn-txt:disabled { background:#cbd5e1; }
.bc-msg { padding:9px 14px; margin:0 18px 16px; font-size:13px; border-radius:6px; } .bc-msg.ok { background:#dcfce7; color:#166534; } .bc-msg.err { background:#fee2e2; color:#b91c1c; }
</style>
