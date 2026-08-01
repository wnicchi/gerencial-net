<!-- AgendaHistorialView.vue — Agenda: historial de mensajes del usuario (recibidos / enviados). -->
<template>
  <div class="ab-view">
    <div class="ab-cab">
      <div class="ab-ico">📋</div>
      <div class="ab-tx"><h1>Agenda — Historial de Mensajes</h1><p>Tus mensajes recibidos y enviados</p></div>
    </div>

    <div class="ab-tools">
      <div class="ab-tabs">
        <button v-for="t in TABS" :key="t.id" :class="['ab-tab', { act: tipo === t.id }]" @click="tipo = t.id; cargar()">{{ t.label }}</button>
      </div>
      <input v-model="filtro" class="ab-search" placeholder="🔍 Buscar por usuario, tema o texto…" />
      <span class="ab-count">{{ filtradas.length }} mensaje(s)</span>
    </div>

    <div class="ab-tabla-wrap">
      <table class="ab-tabla">
        <thead>
          <tr>
            <th style="width:40px" class="c"></th>
            <th style="width:130px">Aparece</th>
            <th style="width:200px">{{ tipo === 'enviados' ? 'Para' : 'De' }}</th>
            <th style="width:120px">Tema</th>
            <th>Mensaje</th>
            <th style="width:44px" class="c">📎</th>
            <th style="width:110px" class="c">Estado</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="cargando"><td colspan="7" class="ab-vacio">⟳ Cargando…</td></tr>
          <tr v-else-if="!filtradas.length"><td colspan="7" class="ab-vacio">No hay mensajes.</td></tr>
          <tr v-for="m in filtradas" :key="m.unico" class="ab-click" @click="ver = m">
            <td class="c"><span :title="m.direccion === 'recibido' ? 'Recibido' : 'Enviado'">{{ m.direccion === 'recibido' ? '📥' : '📤' }}</span></td>
            <td>{{ m.fecha_hora }}</td>
            <td>{{ m.contraparte }}</td>
            <td>{{ m.tema }}</td>
            <td class="ab-txt">{{ m.texto }}</td>
            <td class="c"><button v-if="m.adjunto" class="ab-clip" :title="'Ver adjunto: ' + m.archivo" @click.stop="verAdjunto(m)">📎</button></td>
            <td class="c">
              <span v-if="m.respondido" class="ab-chip blue">↩ Respondido</span>
              <template v-else>
                <span v-if="m.direccion === 'recibido'" :class="['ab-chip', m.leido ? 'green' : 'amber']">{{ m.leido ? 'Leído' : 'No leído' }}</span>
                <span v-else :class="['ab-chip', m.leido ? 'green' : 'slate']">{{ m.leido ? 'Leído por dest.' : 'Sin leer' }}</span>
              </template>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <Teleport to="body">
      <div v-if="ver" class="ab-ov" @click.self="ver = null">
        <div class="ab-md">
          <div class="ab-md-head"><span>{{ ver.direccion === 'recibido' ? '📥 De ' + ver.contraparte : '📤 Para ' + ver.contraparte }}</span><button class="ab-x" @click="ver = null">✕</button></div>
          <div class="ab-md-body">
            <div class="ab-meta">{{ ver.fecha_hora }}<span v-if="ver.tema"> · Tema: <b>{{ ver.tema }}</b></span></div>
            <p class="ab-md-txt">{{ ver.texto }}</p>
            <div v-if="ver.adjunto" class="ab-adj">📎 {{ ver.archivo }}<button class="ab-adj-btn" @click="verAdjunto(ver)">Ver adjunto</button></div>
          </div>
          <div class="ab-md-foot"><span style="flex:1"></span><button class="ab-cancel" @click="ver = null">Cerrar</button></div>
        </div>
      </div>
    </Teleport>

    <DocViewer ref="docVisor" />
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import api from '@/services/auth'
import DocViewer from '@/components/DocViewer.vue'

const docVisor = ref<InstanceType<typeof DocViewer> | null>(null)
async function verAdjunto (m: any) {
  try { const resp = await api.get(`/agenda/mensajes/${m.unico}/adjunto`, { responseType: 'blob' }); docVisor.value?.open(resp.data as Blob, m.archivo || 'adjunto') }
  catch { /* */ }
}

const TABS = [{ id: 'todos', label: 'Todos' }, { id: 'recibidos', label: 'Recibidos' }, { id: 'enviados', label: 'Enviados' }] as const
const tipo = ref<'todos' | 'recibidos' | 'enviados'>('todos')
const rows = ref<any[]>([]); const cargando = ref(false); const filtro = ref(''); const ver = ref<any>(null)

const filtradas = computed(() => {
  const q = filtro.value.trim().toLowerCase()
  if (!q) return rows.value
  return rows.value.filter(m => (m.contraparte || '').toLowerCase().includes(q) || (m.tema || '').toLowerCase().includes(q) || (m.texto || '').toLowerCase().includes(q))
})

async function cargar () {
  cargando.value = true
  try { rows.value = (await api.get('/agenda/historial', { params: { tipo: tipo.value } })).data ?? [] }
  catch { rows.value = [] }
  finally { cargando.value = false }
}
cargar()
</script>

<style scoped>
.ab-view { display:flex; flex-direction:column; min-height:100%; }
.ab-cab { display:flex; align-items:center; gap:12px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.ab-ico { font-size:28px; } .ab-tx h1 { margin:0; font-size:19px; color:#1e293b; } .ab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.ab-tools { display:flex; align-items:center; gap:12px; padding:14px 18px 8px; flex-wrap:wrap; }
.ab-tabs { display:flex; gap:4px; background:#f1f5f9; border-radius:9px; padding:3px; }
.ab-tab { border:none; background:none; padding:7px 14px; border-radius:7px; cursor:pointer; font-size:13px; font-weight:700; color:#64748b; }
.ab-tab.act { background:#fff; color:#1b4332; box-shadow:0 1px 3px rgba(0,0,0,.1); }
.ab-search { flex:1; min-width:220px; border:1px solid #c8d8ea; border-radius:8px; padding:9px 12px; font-size:14px; color:#1e293b; }
.ab-count { font-size:12.5px; color:#64748b; font-weight:600; }
.ab-tabla-wrap { padding:6px 18px 24px; overflow-x:auto; }
.ab-tabla { width:100%; border-collapse:collapse; font-size:13px; background:#fff; border:1px solid #e2e8f0; border-radius:10px; overflow:hidden; }
.ab-tabla th { background:#1e293b; color:#fff; padding:9px 10px; text-align:left; font-size:12px; font-weight:700; } .ab-tabla th.c, .ab-tabla td.c { text-align:center; }
.ab-tabla td { padding:8px 10px; border-bottom:1px solid #f0f4f9; color:#1e293b; } .ab-tabla tbody tr:hover { background:#f8fafc; }
.ab-tabla tbody tr.ab-click { cursor:pointer; }
.ab-txt { max-width:360px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; color:#475569; }
.ab-clip { background:#eef2f7; border:none; border-radius:6px; padding:4px 8px; cursor:pointer; font-size:14px; } .ab-clip:hover { background:#e0eefc; }
.ab-vacio { text-align:center; color:#94a3b8; padding:24px; }
.ab-chip { display:inline-block; font-size:10.5px; font-weight:700; padding:2px 8px; border-radius:999px; }
.ab-chip.green { background:#d1fae5; color:#065f46; } .ab-chip.amber { background:#fef3c7; color:#92400e; } .ab-chip.slate { background:#e2e8f0; color:#334155; } .ab-chip.blue { background:#dbeafe; color:#1e40af; }
.ab-ov { position:fixed; inset:0; background:rgba(15,23,42,.5); z-index:9000; display:flex; align-items:center; justify-content:center; padding:20px; }
.ab-md { background:#fff; border-radius:14px; width:min(500px,96vw); display:flex; flex-direction:column; }
.ab-md-head { display:flex; align-items:center; padding:14px 18px; border-bottom:1px solid #e2e8f0; font-weight:800; color:#1e293b; font-size:15px; }
.ab-x { margin-left:auto; background:#eef2f7; border:none; border-radius:6px; width:30px; height:30px; cursor:pointer; color:#475569; }
.ab-md-body { padding:16px 18px; } .ab-meta { font-size:12px; color:#64748b; margin-bottom:10px; }
.ab-md-txt { font-size:15px; color:#1e293b; line-height:1.5; white-space:pre-wrap; margin:0; }
.ab-adj { margin:12px 0 0; display:flex; align-items:center; gap:10px; font-size:13px; color:#334155; background:#f1f5f9; border-radius:8px; padding:8px 12px; }
.ab-adj-btn { margin-left:auto; background:#2563eb; color:#fff; border:none; border-radius:6px; padding:6px 12px; cursor:pointer; font-size:12px; font-weight:700; }
.ab-md-foot { display:flex; align-items:center; gap:8px; padding:12px 18px; border-top:1px solid #e2e8f0; }
.ab-cancel { background:#eef2f7; color:#475569; border:none; border-radius:8px; padding:10px 18px; cursor:pointer; font-weight:600; }
</style>
