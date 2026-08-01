<!--
  ComprasView.vue — Consultar Compras (lee de la base de Gestión).
  Resalta en amarillo las compras con documentación digital. Permite ver el
  detalle de una compra y abrir su documentación.
-->
<template>
  <div class="cp-view">
    <div class="cp-cab">
      <div class="cp-cab-ico">🛒</div>
      <div class="cp-cab-tx"><h1>Consultar Compras</h1><p>{{ total }} compra{{ total === 1 ? '' : 's' }} de RRHH</p></div>
      <button class="cp-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="cp-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
    </div>

    <ComprasAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/compras" titulo="Asistente IA — Consultar Compras"
            subtitulo="Preguntá sobre las compras"
            :sugerencias="['¿Qué significan las filas amarillas?','¿Cómo filtro por proveedor?','¿Cómo veo el detalle de una compra?','¿Cómo abro la documentación?']"
            @close="modalIA = false" />

    <div class="cp-card">
      <!-- Filtros -->
      <div class="cp-filtros">
        <div class="cp-campo"><label>IVA Mes</label><input v-model.number="f.mes" type="number" min="0" max="12" class="cp-in-num" @keydown.enter="cargar" /></div>
        <div class="cp-campo"><label>Año</label><input v-model.number="f.anio" type="number" min="0" max="2099" class="cp-in-num" @keydown.enter="cargar" /></div>
        <div class="cp-campo cp-campo-flex"><label>Filtro (proveedor)</label><input v-model="f.proveedor" type="text" @keydown.enter="cargar" /></div>
        <div class="cp-campo"><label>Compra Nº</label><input v-model.number="f.nro" type="number" min="0" class="cp-in-num" @keydown.enter="cargar" /></div>
        <button class="cp-btn-buscar" @click="cargar">🔍 Consultar</button>
      </div>

      <div class="cp-leyenda">
        <span class="cp-lg cp-lg-doc">▉</span> Con documentación digital
        <span class="cp-lg cp-lg-pend">▉</span> Pendiente de autorización
      </div>

      <!-- Grilla -->
      <div class="cp-grid-wrap">
        <div v-if="cargando" class="cp-estado">⟳ Consultando Gestión…</div>
        <div v-else-if="error" class="cp-estado err">⚠️ {{ error }}</div>
        <div v-else-if="rows.length === 0" class="cp-estado">Sin compras para el filtro</div>
        <table v-else class="cp-tabla">
          <thead><tr><th style="width:90px">Número</th><th>Proveedor</th><th style="width:100px">Fecha</th><th style="width:60px">Tipo</th><th style="width:140px">Comprobante</th><th style="width:150px;text-align:right">Importe Total</th></tr></thead>
          <tbody>
            <tr v-for="r in rows" :key="r.nro" :class="{ sel: sel?.nro === r.nro, pend: r.pendiente && !r.con_doc }" @click="sel = r">
              <td class="cp-cod">{{ r.nro }}</td>
              <td>{{ r.proveedor }}</td>
              <td>{{ r.fecha }}</td>
              <td :class="{ doc: r.con_doc }">{{ r.tipo }}</td>
              <td :class="{ doc: r.con_doc }">{{ r.comprobante }}</td>
              <td class="cp-num">{{ money(r.importe) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Acciones -->
      <div class="cp-acciones">
        <button class="cp-btn-ver" :disabled="!sel || verCargando" @click="verCompra">📄 {{ verCargando ? 'Cargando…' : 'Visualizar Compra' }}</button>
        <button class="cp-btn-doc" :disabled="!sel || !sel.con_doc || docCargando" @click="verDocumento">📎 {{ docCargando ? 'Abriendo…' : 'Visualizar Documentación' }}</button>
        <span v-if="sel" class="cp-sel-info">Compra Nº {{ sel.nro }} — {{ sel.proveedor }}</span>
      </div>
    </div>

    <!-- Modal detalle (texto) -->
    <Teleport to="body">
      <div v-if="detalle" class="cp-modal-ov" @click.self="detalle = null">
        <div class="cp-modal-txt">
          <div class="cp-modal-head"><span>{{ detalle.titulo }}</span><button class="cp-modal-x" @click="detalle = null">✕</button></div>
          <pre class="cp-detalle">{{ detalle.lineas.join('\n') }}</pre>
        </div>
      </div>
    </Teleport>

    <DocViewer ref="docVisor" />
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import api from '@/services/auth'
import ComprasAyuda from '@/components/ComprasAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'
import DocViewer from '@/components/DocViewer.vue'

const modalAyuda = ref(false); const modalIA = ref(false)
const money = (v: any) => Number(v ?? 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

interface Compra { nro: number; proveedor: string; fecha: string; tipo: string; comprobante: string; importe: number; con_doc: boolean; pendiente: boolean }

const f = reactive({ mes: 0, anio: 0, proveedor: '', nro: 0 })
const rows = ref<Compra[]>([])
const total = ref(0)
const cargando = ref(false)
const error = ref('')
const sel = ref<Compra | null>(null)

const cargar = async () => {
  cargando.value = true; error.value = ''; sel.value = null
  try {
    const params: any = {}
    if (f.mes > 0) params.mes = f.mes
    if (f.anio > 0) params.anio = f.anio
    if (f.proveedor.trim()) params.proveedor = f.proveedor.trim()
    if (f.nro > 0) params.nro = f.nro
    const { data } = await api.get('/compras', { params })
    rows.value = data.rows ?? []; total.value = data.total ?? rows.value.length
  } catch (e: any) {
    error.value = e?.response?.data?.error ?? 'No se pudo consultar las compras.'
    rows.value = []; total.value = 0
  } finally { cargando.value = false }
}

// ── Visualizar compra (detalle de texto) ──
const detalle = ref<{ titulo: string; lineas: string[] } | null>(null)
const verCargando = ref(false)
const verCompra = async () => {
  if (!sel.value) return
  verCargando.value = true
  try { detalle.value = (await api.get(`/compras/${sel.value.nro}/detalle`)).data }
  catch (e) { console.error(e) } finally { verCargando.value = false }
}

// ── Visualizar documentación ──
const docVisor = ref<InstanceType<typeof DocViewer> | null>(null)
const docCargando = ref(false)
const verDocumento = async () => {
  if (!sel.value || !sel.value.con_doc) return
  docCargando.value = true
  try {
    const resp = await api.get(`/compras/${sel.value.nro}/documento`, { responseType: 'blob' })
    const tipo = (resp.data as Blob).type || ''
    // Extensión a partir del nombre que sugiere el backend (Content-Disposition) o el MIME
    const cd = String(resp.headers?.['content-disposition'] ?? '')
    const ext = (cd.match(/\.([a-z0-9]+)"/i)?.[1]
      ?? (tipo.includes('pdf') ? 'pdf' : tipo.includes('png') ? 'png' : tipo.includes('jpeg') ? 'jpg' : 'bin')).toLowerCase()
    docVisor.value?.open(resp.data as Blob, `Compra_${sel.value.nro}.${ext}`)
  } catch (e: any) {
    // El error viene como blob; intentar leer el mensaje
    let msg = 'No se pudo abrir el documento.'
    try { if (e?.response?.data) msg = JSON.parse(await (e.response.data as Blob).text())?.error ?? msg } catch {}
    alert(msg)
  } finally { docCargando.value = false }
}

onMounted(cargar)
</script>

<style scoped>
.cp-view { display:flex; flex-direction:column; height:100%; overflow:hidden; }
.cp-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; flex-shrink:0; }
.cp-cab-ico { font-size:28px; } .cp-cab-tx h1 { margin:0; font-size:20px; color:#1e293b; } .cp-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.cp-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.cp-btn-ia:hover { filter:brightness(1.1); }
.cp-btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.cp-btn-ayuda:hover { background:#f0faf4; }

.cp-card { margin:18px; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:14px 16px; display:flex; flex-direction:column; min-height:0; flex:1; }
.cp-filtros { display:flex; flex-wrap:wrap; align-items:flex-end; gap:12px; padding-bottom:12px; border-bottom:1px solid #f1f5f9; }
.cp-campo { display:flex; flex-direction:column; gap:5px; } .cp-campo-flex { flex:1; min-width:200px; }
.cp-campo label { font-size:12px; font-weight:600; color:#374151; }
.cp-campo input { border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:13px; outline:none; }
.cp-campo input:focus { border-color:#40916c; box-shadow:0 0 0 3px rgba(64,145,108,.15); }
.cp-in-num { width:80px; }
.cp-btn-buscar { background:#16a34a; color:#fff; border:none; padding:9px 16px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.cp-btn-buscar:hover { background:#15803d; }
.cp-leyenda { display:flex; align-items:center; gap:6px; font-size:12px; color:#64748b; padding:8px 0; }
.cp-lg { font-size:13px; } .cp-lg-doc { color:#facc15; } .cp-lg-pend { color:#fb923c; margin-left:14px; }

.cp-grid-wrap { flex:1; overflow:auto; border:1px solid #e2e8f0; border-radius:8px; }
.cp-estado { padding:30px; text-align:center; color:#9ca3af; font-size:14px; } .cp-estado.err { color:#b91c1c; }
.cp-tabla { width:100%; border-collapse:collapse; font-size:13px; }
.cp-tabla th { position:sticky; top:0; background:#1b4332; color:#fff; text-align:left; padding:8px 10px; font-size:11px; text-transform:uppercase; letter-spacing:.3px; z-index:1; }
.cp-tabla td { padding:6px 10px; border-bottom:1px solid #f1f5f9; color:#1e293b; }
.cp-tabla tbody tr { cursor:pointer; }
.cp-tabla tbody tr:hover td { background:#f0faf4; }
.cp-tabla tr.pend td { background:#ffedd5; }
.cp-tabla tr.sel td { background:#bfdbfe !important; }
.cp-tabla td.doc { background:#fde047; font-weight:600; }
.cp-tabla tr.sel td.doc { background:#fde047 !important; }
.cp-cod { font-family:monospace; font-weight:700; color:#1b4332; }
.cp-num { text-align:right; font-variant-numeric:tabular-nums; }

.cp-acciones { display:flex; flex-wrap:wrap; align-items:center; gap:10px; margin-top:12px; padding-top:12px; border-top:1px solid #f1f5f9; }
.cp-btn-ver { background:#86efac; color:#052e16; border:1px solid #22c55e; padding:10px 18px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; }
.cp-btn-ver:hover:not(:disabled){ background:#4ade80; }
.cp-btn-doc { background:#2563eb; color:#fff; border:none; padding:10px 18px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; }
.cp-btn-doc:hover:not(:disabled){ background:#1d4ed8; }
.cp-acciones button:disabled { background:#cbd5e1; color:#64748b; border-color:#cbd5e1; cursor:default; }
.cp-sel-info { font-size:13px; color:#475569; }

.cp-modal-ov { position:fixed; inset:0; background:rgba(15,23,42,.6); z-index:9000; display:flex; align-items:center; justify-content:center; padding:20px; }
.cp-modal-txt { background:#fff; border-radius:10px; width:min(720px,96vw); max-height:90vh; display:flex; flex-direction:column; overflow:hidden; box-shadow:0 24px 60px rgba(0,0,0,.45); }
.cp-modal-doc { background:#fff; border-radius:10px; width:min(960px,97vw); height:90vh; display:flex; flex-direction:column; overflow:hidden; box-shadow:0 24px 60px rgba(0,0,0,.45); }
.cp-modal-head { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:10px 16px; background:#1b4332; color:#fff; font-size:14px; }
.cp-modal-acc { display:flex; gap:8px; }
.cp-modal-x { background:rgba(255,255,255,.15); border:none; color:#fff; width:28px; height:28px; border-radius:8px; font-size:14px; cursor:pointer; }
.cp-detalle { margin:0; padding:18px 22px; overflow:auto; font-family:'Consolas','Courier New',monospace; font-size:13px; color:#1e293b; white-space:pre; line-height:1.5; }
.cp-doc-frame { flex:1; border:none; width:100%; }
.cp-doc-nopreview { flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:14px; color:#475569; }
.cp-doc-nopreview-ico { font-size:54px; }
.cp-doc-nopreview .btn-ok { background:#16a34a; padding:10px 20px; }
.btn-sm { padding:6px 12px; border-radius:6px; font-size:12px; font-weight:600; cursor:pointer; border:none; }
.btn-ok { background:#22c55e; color:#fff; } .btn-ok:hover { background:#16a34a; }
.btn-cancel { background:rgba(255,255,255,.2); color:#fff; } .btn-cancel:hover { background:rgba(255,255,255,.35); }
</style>
