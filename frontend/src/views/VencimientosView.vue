<!-- VencimientosView.vue — Vencimientos y recordatorios centralizados (vencimientos).
     Junta en una grilla exámenes médicos, licencias de conducir, plazos de prueba y
     garantías de celulares. Reutiliza GET /vencimientos. -->
<template>
  <div class="vn-view">
    <div class="vn-cab">
      <div class="vn-cab-ico">📅</div>
      <div class="vn-cab-tx">
        <h1>Vencimientos y recordatorios</h1>
        <p>Todo lo que vence, en un solo lugar</p>
      </div>
      <button class="vn-btn-ayuda" title="Ayuda" @click="ayuda = true">❓ Ayuda</button>
    </div>

    <!-- Filtros -->
    <div class="vn-filtros">
      <div class="vn-chips">
        <button
          v-for="t in tiposVisibles"
          :key="t.id"
          class="vn-chip"
          :class="[{ off: !tiposSel.has(t.id) }, t.color]"
          @click="toggleTipo(t.id)"
        >
          <span>{{ t.icon }}</span> {{ t.label }}
        </button>
      </div>

      <div class="vn-filtros-row">
        <select v-model="estado" class="vn-sel" @change="cargar">
          <option value="todos">Estado: todos</option>
          <option value="vencidos">Solo vencidos</option>
          <option value="porvencer">Solo por vencer</option>
        </select>
        <label class="vn-lbl">Desde</label>
        <input v-model="desde" type="date" class="vn-fin" @change="cargar" />
        <label class="vn-lbl">Hasta</label>
        <input v-model="hasta" type="date" class="vn-fin" @change="cargar" />
        <input v-model="q" class="vn-busc" placeholder="Filtrar por empleado…" @input="cargarDeb" />
        <div style="flex:1"></div>
        <button class="vn-btn-excel" :disabled="!filas.length" @click="exportarExcel">📊 Excel</button>
        <button class="vn-btn-pdf" :disabled="!filas.length" @click="imprimir">🖨 PDF</button>
      </div>
    </div>

    <!-- Grilla -->
    <div v-if="cargando" class="vn-info">⟳ Cargando…</div>
    <div v-else-if="!filas.length" class="vn-info">No hay vencimientos para los filtros elegidos.</div>
    <div v-else class="vn-grid-wrap">
      <div class="vn-total">{{ filas.length }} vencimiento{{ filas.length === 1 ? '' : 's' }}</div>
      <table class="vn-grid">
        <thead>
          <tr>
            <th style="width:150px">Tipo</th>
            <th>Empleado</th>
            <th style="width:200px">Detalle</th>
            <th style="width:100px" class="c">Vence</th>
            <th style="width:90px" class="c">Estado</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="(f, i) in filas"
            :key="i"
            class="vn-fila"
            :class="f.dias < 0 ? 'vencido' : 'porvencer'"
            title="Abrir ficha del empleado"
            @click="irFicha(f.cod)"
          >
            <td><span class="vn-tico">{{ tipoDe(f.tipo).icon }}</span> {{ tipoDe(f.tipo).label }}</td>
            <td>{{ f.empleado }}</td>
            <td class="vn-det">{{ f.detalle }}</td>
            <td class="c">{{ fmt(f.vence) }}</td>
            <td class="c">
              <span class="vn-badge" :class="f.dias < 0 ? 'rojo' : 'ambar'">
                {{ f.dias < 0 ? `−${-f.dias} d` : `+${f.dias} d` }}
              </span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Ayuda -->
    <Teleport to="body">
      <div v-if="ayuda" class="vn-ov" @click.self="ayuda = false">
        <div class="vn-md">
          <div class="vn-md-head"><span>❓ Ayuda — Vencimientos</span><button @click="ayuda = false">✕</button></div>
          <div class="vn-md-body">
            <p>Esta pantalla reúne los vencimientos con fecha de todo el sistema:</p>
            <ul>
              <li>🩺 <b>Exámenes médicos</b>: próximo control de cada empleado.</li>
              <li>🚗 <b>Licencias de conducir</b>: vencimiento de cada carnet cargado en la ficha.</li>
              <li>⏳ <b>Plazos de prueba</b>: fecha en que se cumple el período de prueba.</li>
            </ul>
            <p>Usá los <b>chips</b> para mostrar/ocultar tipos, el <b>rango de fechas</b> y el
              filtro de <b>estado</b>. La grilla se ordena por urgencia (primero lo vencido).
              El borde y el estado en <span style="color:#c0392b">rojo</span> indican vencido;
              en <span style="color:#e08e0b">ámbar</span>, por vencer. Hacé <b>clic en una fila</b>
              para abrir la ficha del empleado.</p>
            <p style="color:#64748b">Los siniestros ART (sin fecha de vencimiento) se ven en su módulo y en el panel de inicio.</p>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Modal PDF -->
    <Teleport to="body">
      <div v-if="pdfUrl" class="vn-ov" @click.self="cerrarPdf">
        <div class="vn-pdf-md">
          <div class="vn-md-head">
            <span>{{ pdfNombre }}</span>
            <div style="display:flex; gap:8px">
              <button class="vn-pdf-b" @click="guardarDesdeUrl(pdfUrl, pdfNombre)">⬇ Descargar</button>
              <button class="vn-pdf-b" @click="($refs.pdfFrame as HTMLIFrameElement)?.contentWindow?.print()">🖨 Imprimir</button>
              <button class="vn-pdf-b cancel" @click="cerrarPdf">✕ Cerrar</button>
            </div>
          </div>
          <iframe ref="pdfFrame" :src="pdfUrl" class="vn-pdf-frame"></iframe>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { puedoVerId } from '@/config/menuVisible'
import api from '@/services/auth'
import jsPDF from 'jspdf'
import { guardarComo, guardarDesdeUrl } from '@/utils/descargas'

interface Fila { tipo: string; cod: number; empleado: string; detalle: string; vence: string; dias: number }

// Cada tipo de vencimiento se asocia al permiso del módulo del que proviene, para
// no mostrarlo a quien no tiene ese módulo habilitado (ej. un encargado).
const TIPOS = [
  { id: 'examen',            label: 'Exámenes médicos',    icon: '🩺', color: 'rojo',  permiso: 'exam-proximos' },
  { id: 'licencia_conducir', label: 'Licencias conducir',  icon: '🚗', color: 'rojo',  permiso: 'empleados-carnet' },
  { id: 'plazo_prueba',      label: 'Plazos de prueba',    icon: '⏳', color: 'ambar', permiso: 'empleados-abm' },
]
const tipoDe = (id: string) => TIPOS.find(t => t.id === id) ?? { label: id, icon: '•', color: 'ambar' }

const auth = useAuthStore()
// Solo los tipos cuyo módulo el usuario puede ver.
const tiposVisibles = computed(() => TIPOS.filter(t => puedoVerId(t.permiso, auth.puedoVer, auth.esAdmin)))

const router = useRouter()
const hoyIso = () => new Date().toISOString().slice(0, 10)
const isoMas = (dias: number) => { const d = new Date(); d.setDate(d.getDate() + dias); return d.toISOString().slice(0, 10) }

const filas = ref<Fila[]>([])
const cargando = ref(false)
const ayuda = ref(false)
const tiposSel = ref<Set<string>>(new Set(tiposVisibles.value.map(t => t.id)))
const estado = ref('todos')
const desde = ref(isoMas(-30))
const hasta = ref(isoMas(30))
const q = ref('')
let deb: ReturnType<typeof setTimeout> | null = null

const fmt = (d: string) => d ? d.split('-').reverse().join('/') : ''

function toggleTipo (id: string) {
  const s = new Set(tiposSel.value)
  s.has(id) ? s.delete(id) : s.add(id)
  if (s.size === 0) return   // dejar al menos uno
  tiposSel.value = s
  cargar()
}

async function cargar () {
  // Sin tipos permitidos → no consultar (evita que el backend devuelva todo con lista vacía).
  if (!tiposSel.value.size) { filas.value = []; return }
  cargando.value = true
  try {
    const { data } = await api.get('/vencimientos', {
      params: {
        desde: desde.value, hasta: hasta.value, estado: estado.value,
        q: q.value.trim(), tipos: [...tiposSel.value].join(','),
      },
    })
    filas.value = data?.rows ?? []
  } catch { filas.value = [] }
  finally { cargando.value = false }
}
function cargarDeb () { if (deb) clearTimeout(deb); deb = setTimeout(cargar, 300) }

function irFicha (cod: number) { router.push({ path: '/dashboard/empleados', query: { cod } }) }

async function exportarExcel () {
  const cab = ['Tipo', 'Empleado', 'Detalle', 'Vence', 'Días']
  const filasCsv = filas.value.map(f => [
    tipoDe(f.tipo).label, f.empleado, f.detalle, fmt(f.vence),
    f.dias < 0 ? `vencido ${-f.dias}` : `faltan ${f.dias}`,
  ])
  const csv = [cab, ...filasCsv]
    .map(r => r.map(c => `"${String(c).replace(/"/g, '""')}"`).join(';'))
    .join('\r\n')
  await guardarComo(new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8' }), 'Vencimientos.csv')
}

const pdfUrl = ref(''); const pdfNombre = ref('')
const cerrarPdf = () => { if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value); pdfUrl.value = '' }
function imprimir () {
  const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })
  const mL = 12, mR = 198
  doc.setFont('helvetica', 'bold'); doc.setFontSize(13)
  doc.text('VENCIMIENTOS Y RECORDATORIOS', mL, 16)
  doc.setFontSize(9); doc.setFont('helvetica', 'normal')
  doc.text(`Rango ${fmt(desde.value)} a ${fmt(hasta.value)}  ·  ${filas.value.length} registros`, mL, 22)
  let y = 30
  const x = [mL, mL + 40, mL + 92, mL + 150, mL + 172]
  const head = () => {
    doc.setFont('helvetica', 'bold'); doc.setFontSize(8)
    doc.text('Tipo', x[0], y); doc.text('Empleado', x[1], y); doc.text('Detalle', x[2], y)
    doc.text('Vence', x[3], y); doc.text('Estado', x[4], y)
    doc.setLineWidth(0.3); doc.line(mL, y + 1.5, mR, y + 1.5)
    y += 5; doc.setFont('helvetica', 'normal')
  }
  head()
  for (const f of filas.value) {
    if (y > 285) { doc.addPage(); y = 20; head() }
    doc.setFontSize(7.5)
    doc.text(tipoDe(f.tipo).label, x[0], y)
    doc.text(String(f.empleado).slice(0, 28), x[1], y)
    doc.text(String(f.detalle).slice(0, 30), x[2], y)
    doc.text(fmt(f.vence), x[3], y)
    doc.text(f.dias < 0 ? `venc ${-f.dias}d` : `+${f.dias}d`, x[4], y)
    y += 4.5
  }
  pdfNombre.value = 'Vencimientos.pdf'
  pdfUrl.value = URL.createObjectURL(doc.output('blob'))
}

onMounted(cargar)
</script>

<style scoped>
.vn-view { padding: 1rem 1.25rem; }
.vn-cab { display: flex; align-items: center; gap: 12px; margin-bottom: 14px; }
.vn-cab-ico { font-size: 1.8rem; }
.vn-cab-tx h1 { margin: 0; font-size: 1.3rem; color: #1b4332; }
.vn-cab-tx p { margin: 2px 0 0; font-size: 0.82rem; color: #64748b; }
.vn-btn-ayuda { margin-left: auto; background: #fff; border: 1px solid #cbd5e1; border-radius: 8px; padding: 7px 12px; cursor: pointer; font-size: 0.82rem; }

.vn-filtros { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px 12px; margin-bottom: 12px; }
.vn-chips { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 10px; }
.vn-chip { display: inline-flex; align-items: center; gap: 5px; border: 1.5px solid transparent; border-radius: 16px; padding: 4px 11px; font-size: 0.78rem; cursor: pointer; font-weight: 600; }
.vn-chip.rojo { background: #fdecec; color: #b3271e; border-color: #f3c0bd; }
.vn-chip.ambar { background: #fdf3e0; color: #9a6510; border-color: #f0d49a; }
.vn-chip.off { background: #f1f5f9; color: #94a3b8; border-color: #e2e8f0; }

.vn-filtros-row { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
.vn-lbl { font-size: 0.78rem; color: #475569; }
.vn-sel, .vn-fin, .vn-busc { height: 32px; border: 1px solid #cbd5e1; border-radius: 7px; padding: 0 8px; font-size: 0.82rem; color: #1e293b; background: #fff; }
.vn-busc { min-width: 180px; }
.vn-btn-excel, .vn-btn-pdf { height: 32px; border: none; border-radius: 7px; padding: 0 14px; cursor: pointer; font-size: 0.8rem; font-weight: 700; color: #fff; }
.vn-btn-excel { background: #15803d; } .vn-btn-pdf { background: #1b4332; }
.vn-btn-excel:disabled, .vn-btn-pdf:disabled { opacity: 0.5; cursor: default; }

.vn-info { padding: 30px; text-align: center; color: #94a3b8; }
.vn-total { font-size: 0.78rem; color: #94a3b8; margin-bottom: 6px; }
.vn-grid-wrap { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 8px 10px; }
.vn-grid { width: 100%; border-collapse: collapse; font-size: 0.8rem; }
.vn-grid th { text-align: left; padding: 7px 8px; background: #f1f5f9; color: #475569; font-size: 0.76rem; white-space: nowrap; position: sticky; top: 0; }
.vn-grid th.c, .vn-grid td.c { text-align: center; }
.vn-fila td { padding: 6px 8px; color: #1e293b; border-bottom: 1px solid #f1f5f9; cursor: pointer; border-left: 3px solid transparent; }
.vn-fila:hover td { background: #f0f9f4; }
.vn-fila.vencido td:first-child { border-left-color: #c0392b; }
.vn-fila.porvencer td:first-child { border-left-color: #e08e0b; }
.vn-tico { margin-right: 3px; }
.vn-det { color: #64748b; }
.vn-badge { font-size: 0.72rem; padding: 2px 8px; border-radius: 10px; font-weight: 700; }
.vn-badge.rojo { background: #fdecec; color: #b3271e; }
.vn-badge.ambar { background: #fdf3e0; color: #9a6510; }

.vn-ov { position: fixed; inset: 0; background: rgba(15,23,42,.55); z-index: 9600; display: flex; align-items: center; justify-content: center; padding: 24px; }
.vn-md { background: #fff; border-radius: 12px; width: min(560px, 96vw); max-height: 82vh; overflow: hidden; display: flex; flex-direction: column; }
.vn-md-head { display: flex; align-items: center; justify-content: space-between; padding: 12px 14px; background: #1b4332; color: #fff; font-weight: 700; font-size: 0.9rem; }
.vn-md-head button { background: rgba(255,255,255,.85); border: none; border-radius: 6px; width: 30px; height: 30px; cursor: pointer; font-weight: 700; }
.vn-md-body { padding: 16px; overflow: auto; font-size: 0.86rem; color: #334155; line-height: 1.55; }
.vn-md-body ul { margin: 8px 0; padding-left: 18px; } .vn-md-body li { margin: 4px 0; }
.vn-pdf-md { background: #fff; border-radius: 10px; width: 92vw; height: 92vh; display: flex; flex-direction: column; overflow: hidden; }
.vn-pdf-frame { flex: 1; border: none; width: 100%; }
.vn-pdf-b { background: rgba(255,255,255,.9); border: none; border-radius: 6px; padding: 6px 12px; cursor: pointer; font-weight: 700; font-size: 0.8rem; color: #1b4332; }
.vn-pdf-b.cancel { color: #b91c1c; }
</style>
