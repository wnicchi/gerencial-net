<!-- AgendaNotificaciones.vue — Campana de mensajes de la Agenda (reemplaza el popup flotante del Fox).
     Sondea /agenda/pendientes; muestra un ✉️ con contador + dropdown; abre el mensaje para leerlo. -->
<template>
  <div class="agn" ref="root">
    <button class="agn-btn" :class="{ tiene: total > 0 }" title="Mensajes de la agenda" @click="toggle">
      <span class="agn-ico">✉️</span>
      <span v-if="total > 0" class="agn-badge">{{ total > 99 ? '99+' : total }}</span>
    </button>

    <div v-if="abierto" class="agn-drop">
      <div class="agn-head">
        <span>Mensajes sin leer</span><span class="agn-h-badge">{{ total }}</span>
        <button class="agn-nuevo" @click="abrirCrear">＋ Nuevo</button>
      </div>
      <div class="agn-list">
        <button v-for="m in mensajes" :key="m.unico" class="agn-item" @click="abrir(m)">
          <div class="agn-de">{{ m.origen }}<span v-if="m.tema" class="agn-tema"> · {{ m.tema }}</span></div>
          <div class="agn-txt">{{ m.texto }}</div>
          <div class="agn-fec">{{ m.fecha_hora }}</div>
        </button>
        <div v-if="!mensajes.length" class="agn-vacio">No tenés mensajes sin leer.</div>
      </div>
      <button class="agn-hist" @click="irHistorial">📋 Ver historial de mensajes</button>
    </div>

    <!-- Modal Crear Mensaje (reusa la vista, sin ir por menú) -->
    <Teleport to="body">
      <div v-if="crearAbierto" class="agn-crear-ov" @click.self="crearAbierto = false">
        <div class="agn-crear-md">
          <div class="agn-crear-bar"><span>✉️ Nuevo mensaje</span><button class="agn-x" @click="crearAbierto = false">✕ Cerrar</button></div>
          <div class="agn-crear-body"><component :is="CrearComp" /></div>
        </div>
      </div>
      <div v-if="histAbierto" class="agn-crear-ov" @click.self="histAbierto = false">
        <div class="agn-crear-md">
          <div class="agn-crear-bar"><span>📋 Historial de mensajes</span><button class="agn-x" @click="histAbierto = false">✕ Cerrar</button></div>
          <div class="agn-crear-body"><component :is="HistComp" /></div>
        </div>
      </div>
    </Teleport>

    <Teleport to="body">
      <div v-if="verMsg" class="agn-ov" @click.self="verMsg = null">
        <div class="agn-md">
          <div class="agn-md-head"><span>✉️ Mensaje de {{ verMsg.origen }}</span><button class="agn-x" @click="verMsg = null">✕</button></div>
          <div class="agn-md-body">
            <div class="agn-md-meta">{{ verMsg.fecha_hora }}<span v-if="verMsg.tema"> · Tema: <b>{{ verMsg.tema }}</b></span></div>
            <p class="agn-md-txt">{{ verMsg.texto }}</p>
            <div v-if="verMsg.adjunto" class="agn-adj">📎 {{ verMsg.archivo }}<button class="agn-adj-btn" @click="verAdjunto(verMsg)">Ver adjunto</button></div>
            <p v-if="verMsg.aviso_retorno" class="agn-md-aviso">🔔 El remitente pidió aviso de retorno: al marcarlo leído se le avisa que lo leíste.</p>
            <label class="agn-resp-lbl">Responder</label>
            <input v-model="respuesta" class="agn-resp" maxlength="140" placeholder="Escribí una respuesta (opcional)…" @keyup.enter="responder" />
            <small class="agn-resp-c">{{ respuesta.length }}/140</small>
          </div>
          <div class="agn-md-foot">
            <button class="agn-cancel" @click="verMsg = null">Cerrar</button>
            <span style="flex:1"></span>
            <button class="agn-resp-btn" :disabled="proc || !respuesta.trim()" @click="responder">{{ proc ? '⟳' : '↩ Responder' }}</button>
            <button class="agn-leido" :disabled="proc" @click="marcarLeido">{{ proc ? '⟳' : '✓ Marcar leído' }}</button>
          </div>
        </div>
      </div>
    </Teleport>

    <DocViewer ref="docVisor" />
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount, defineAsyncComponent } from 'vue'
import api from '@/services/auth'
import DocViewer from '@/components/DocViewer.vue'

const docVisor = ref<InstanceType<typeof DocViewer> | null>(null)
async function verAdjunto (m: any) {
  try { const resp = await api.get(`/agenda/mensajes/${m.unico}/adjunto`, { responseType: 'blob' }); docVisor.value?.open(resp.data as Blob, m.archivo || 'adjunto') }
  catch { /* */ }
}
const CrearComp = defineAsyncComponent(() => import('@/views/AgendaCrearView.vue'))
const HistComp = defineAsyncComponent(() => import('@/views/AgendaHistorialView.vue'))
const crearAbierto = ref(false); const histAbierto = ref(false)
const abrirCrear = () => { crearAbierto.value = true; abierto.value = false }
const irHistorial = () => { histAbierto.value = true; abierto.value = false }

const total = ref(0); const mensajes = ref<any[]>([]); const abierto = ref(false)
const verMsg = ref<any>(null); const proc = ref(false); const respuesta = ref('')
const root = ref<HTMLElement | null>(null)
let timer: any = null

async function cargar () {
  try {
    const { data } = await api.get('/agenda/pendientes')
    mensajes.value = data.mensajes ?? []; total.value = data.total ?? 0
  } catch { /* silencioso: no molestar si falla el polling */ }
}
function toggle () { abierto.value = !abierto.value; if (abierto.value) cargar() }
function abrir (m: any) { verMsg.value = m; respuesta.value = ''; abierto.value = false }

async function marcarLeido () {
  if (!verMsg.value) return
  proc.value = true
  try {
    await api.post(`/agenda/mensajes/${verMsg.value.unico}/leer`)
    verMsg.value = null
    await cargar()
  } catch { /* */ }
  finally { proc.value = false }
}

async function responder () {
  if (!verMsg.value || !respuesta.value.trim()) return
  proc.value = true
  try {
    await api.post(`/agenda/mensajes/${verMsg.value.unico}/responder`, { texto: respuesta.value })
    verMsg.value = null; respuesta.value = ''
    await cargar()
  } catch { /* */ }
  finally { proc.value = false }
}

function onDocClick (e: MouseEvent) {
  if (abierto.value && root.value && !root.value.contains(e.target as Node)) abierto.value = false
}

onMounted(() => {
  cargar()
  timer = setInterval(cargar, 45000)   // sondeo cada 45s
  document.addEventListener('click', onDocClick)
})
onBeforeUnmount(() => { if (timer) clearInterval(timer); document.removeEventListener('click', onDocClick) })
</script>

<style scoped>
.agn { position:relative; display:inline-flex; }
.agn-btn { position:relative; background:#f1f5f9; border:none; width:40px; height:40px; border-radius:10px; cursor:pointer; display:flex; align-items:center; justify-content:center; }
.agn-btn:hover { background:#e2e8f0; }
.agn-btn.tiene { background:#dcfce7; }
.agn-ico { font-size:18px; }
.agn-badge { position:absolute; top:-4px; right:-4px; background:#dc2626; color:#fff; font-size:10px; font-weight:800; min-width:17px; height:17px; border-radius:999px; display:flex; align-items:center; justify-content:center; padding:0 4px; border:2px solid #fff; }
.agn-drop { position:absolute; top:48px; right:0; width:340px; max-height:60vh; background:#fff; border:1px solid #e2e8f0; border-radius:12px; box-shadow:0 18px 45px rgba(0,0,0,.18); z-index:9400; overflow:hidden; display:flex; flex-direction:column; }
.agn-head { padding:11px 14px; font-weight:800; color:#1e293b; font-size:14px; border-bottom:1px solid #f1f5f9; display:flex; align-items:center; gap:8px; }
.agn-h-badge { background:#1b4332; color:#fff; font-size:11px; font-weight:700; padding:1px 8px; border-radius:999px; }
.agn-nuevo { margin-left:auto; background:#1b4332; color:#fff; border:none; border-radius:7px; padding:6px 12px; cursor:pointer; font-weight:800; font-size:12px; }
.agn-list { overflow:auto; }
.agn-item { display:block; width:100%; text-align:left; background:none; border:none; border-bottom:1px solid #f1f5f9; padding:10px 14px; cursor:pointer; }
.agn-item:hover { background:#f8fafc; }
.agn-de { font-size:13px; font-weight:700; color:#14532d; } .agn-tema { color:#64748b; font-weight:400; font-size:12px; }
.agn-txt { font-size:12.5px; color:#334155; margin:2px 0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.agn-fec { font-size:11px; color:#94a3b8; }
.agn-vacio { padding:22px 14px; text-align:center; color:#94a3b8; font-size:13px; }
.agn-hist { width:100%; border:none; border-top:1px solid #f1f5f9; background:#f8fafc; padding:10px; cursor:pointer; font-size:12.5px; font-weight:700; color:#1b4332; }
.agn-hist:hover { background:#eef2f7; }
.agn-ov { position:fixed; inset:0; background:rgba(15,23,42,.55); z-index:10000; display:flex; align-items:center; justify-content:center; padding:20px; }
.agn-md { background:#fff; border-radius:14px; width:min(500px,96vw); display:flex; flex-direction:column; }
.agn-md-head { display:flex; align-items:center; padding:14px 18px; border-bottom:1px solid #e2e8f0; font-weight:800; color:#1e293b; font-size:15px; }
.agn-x { margin-left:auto; background:#eef2f7; border:none; border-radius:6px; width:30px; height:30px; cursor:pointer; color:#475569; }
.agn-md-body { padding:16px 18px; }
.agn-md-meta { font-size:12px; color:#64748b; margin-bottom:10px; }
.agn-md-txt { font-size:15px; color:#1e293b; line-height:1.5; white-space:pre-wrap; margin:0; }
.agn-md-aviso { margin:14px 0 0; font-size:12.5px; color:#92600b; background:#fef9c3; border:1px solid #fde68a; border-radius:8px; padding:8px 12px; }
.agn-adj { margin:12px 0 0; display:flex; align-items:center; gap:10px; font-size:13px; color:#334155; background:#f1f5f9; border-radius:8px; padding:8px 12px; }
.agn-adj-btn { margin-left:auto; background:#2563eb; color:#fff; border:none; border-radius:6px; padding:6px 12px; cursor:pointer; font-size:12px; font-weight:700; }
.agn-resp-lbl { display:block; font-size:12px; font-weight:700; color:#374151; margin:16px 0 4px; }
.agn-resp { width:100%; border:1px solid #c8d8ea; border-radius:7px; padding:9px 11px; font-size:14px; box-sizing:border-box; color:#1e293b; }
.agn-resp-c { display:block; text-align:right; font-size:11px; color:#94a3b8; margin-top:2px; }
.agn-resp-btn { background:#2563eb; color:#fff; border:none; border-radius:8px; padding:10px 18px; cursor:pointer; font-weight:800; font-size:13px; } .agn-resp-btn:disabled { opacity:.5; }
.agn-md-foot { display:flex; align-items:center; gap:8px; padding:12px 18px; border-top:1px solid #e2e8f0; }
.agn-cancel { background:#eef2f7; color:#475569; border:none; border-radius:8px; padding:10px 18px; cursor:pointer; font-weight:600; }
.agn-leido { background:#16a34a; color:#fff; border:none; border-radius:8px; padding:10px 20px; cursor:pointer; font-weight:800; font-size:13px; } .agn-leido:disabled { opacity:.5; }
/* Modal Crear Mensaje */
.agn-crear-ov { position:fixed; inset:0; background:rgba(15,23,42,.6); z-index:10000; display:flex; align-items:center; justify-content:center; padding:2vh 2vw; }
.agn-crear-md { background:#f8fafc; border-radius:12px; width:min(900px,97vw); height:min(680px,94vh); display:flex; flex-direction:column; overflow:hidden; box-shadow:0 24px 70px rgba(0,0,0,.45); }
.agn-crear-bar { display:flex; align-items:center; padding:10px 16px; background:#1b4332; color:#fff; font-size:14px; font-weight:800; }
.agn-crear-bar .agn-x { margin-left:auto; background:rgba(255,255,255,.92); color:#334155; border:none; border-radius:7px; padding:7px 14px; cursor:pointer; font-weight:700; font-size:13px; }
.agn-crear-body { flex:1; overflow:auto; background:#f8fafc; }
</style>
