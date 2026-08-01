<!-- LogErroresView.vue — Consulta del log centralizado de errores SQL (solo admin).
     Lee GET /admin/log-errores con filtros. Clic en fila abre el detalle completo. -->
<template>
  <div class="le-view">
    <div class="le-cab">
      <div class="le-cab-ico">🐞</div>
      <div class="le-cab-tx">
        <h1>Log de errores SQL</h1>
        <p>Errores de base de datos registrados por el sistema</p>
      </div>
      <button class="le-btn-ayuda" title="Ayuda" @click="ayuda = true">❓ Ayuda</button>
    </div>

    <!-- Filtros -->
    <div class="le-filtros">
      <label class="le-lbl">Desde</label>
      <input v-model="desde" type="date" class="le-in" @change="cargar" />
      <label class="le-lbl">Hasta</label>
      <input v-model="hasta" type="date" class="le-in" @change="cargar" />
      <input v-model="usuario" class="le-in le-txt" placeholder="Usuario…" @input="cargarDeb" />
      <input v-model="modulo" class="le-in le-txt" placeholder="Módulo…" @input="cargarDeb" />
      <input v-model="q" class="le-in le-txt" placeholder="Texto (error o SQL)…" @input="cargarDeb" />
      <button class="le-btn" @click="cargar">🔄 Actualizar</button>
      <div style="flex:1"></div>
      <button class="le-btn le-btn-danger" title="Eliminar errores anteriores a 90 días" @click="purgar">🧹 Purgar +90 días</button>
    </div>

    <div v-if="cargando" class="le-info">⟳ Cargando…</div>
    <div v-else-if="!filas.length" class="le-info">✅ No hay errores para los filtros elegidos.</div>
    <div v-else class="le-grid-wrap">
      <div class="le-total">{{ mostrados }} de {{ total }} error{{ total === 1 ? '' : 'es' }}{{ total > mostrados ? ' (refiná los filtros para ver más)' : '' }}</div>
      <table class="le-grid">
        <thead>
          <tr>
            <th style="width:140px">Fecha / hora</th>
            <th style="width:110px">Terminal</th>
            <th style="width:120px">Usuario</th>
            <th>Módulo</th>
            <th>Error</th>
            <th style="width:70px" class="c">Código</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="f in filas" :key="f.id" class="le-fila" title="Ver detalle completo" @click="verDetalle(f)">
            <td class="le-fec">{{ fmt(f.fecha) }}</td>
            <td>{{ f.terminal }}</td>
            <td>{{ f.usuario }}</td>
            <td class="le-mod">{{ f.modulo }}</td>
            <td class="le-det">{{ resumen(f.detalle) }}</td>
            <td class="c"><span class="le-cod">{{ f.codigo }}</span></td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Detalle -->
    <Teleport to="body">
      <div v-if="sel" class="le-ov" @click.self="sel = null">
        <div class="le-md">
          <div class="le-md-head"><span>🐞 Detalle del error #{{ sel.id }}</span><button @click="sel = null">✕</button></div>
          <div class="le-md-body">
            <div class="le-kv"><b>Fecha:</b> {{ fmt(sel.fecha) }}</div>
            <div class="le-kv"><b>Terminal:</b> {{ sel.terminal || '—' }} &nbsp; <b>Usuario:</b> {{ sel.usuario || '—' }} &nbsp; <b>Código:</b> {{ sel.codigo || '—' }}</div>
            <div class="le-kv"><b>Módulo:</b> {{ sel.modulo || '—' }}</div>
            <div class="le-seccion">Detalle del error</div>
            <pre class="le-pre">{{ sel.detalle }}</pre>
            <template v-if="sel.sql">
              <div class="le-seccion">Comando SQL</div>
              <pre class="le-pre">{{ sel.sql }}</pre>
            </template>
            <div style="text-align:right;margin-top:10px">
              <button class="le-btn" @click="copiar(sel)">📋 Copiar todo</button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Ayuda -->
    <Teleport to="body">
      <div v-if="ayuda" class="le-ov" @click.self="ayuda = false">
        <div class="le-md" style="width:min(560px,96vw)">
          <div class="le-md-head"><span>❓ Ayuda</span><button @click="ayuda = false">✕</button></div>
          <div class="le-md-body">
            <p>Cada vez que el sistema tiene un error de base de datos, se guarda acá con
              fecha, terminal, usuario, módulo, el comando SQL que se intentó y el detalle completo.</p>
            <p>Sirve para diagnosticar problemas: filtrá por fecha, usuario, módulo o por texto del
              error, y hacé clic en una fila para ver el detalle y copiarlo.</p>
            <p><b>Purgar +90 días</b> elimina los errores más viejos que 90 días para que la tabla no crezca indefinidamente.</p>
            <p style="color:#64748b">Esta pantalla es solo para administradores porque muestra información técnica del sistema.</p>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '@/services/auth'

interface Fila { id: number; fecha: string; terminal: string; usuario: string; modulo: string; sql: string; detalle: string; codigo: string }

const filas = ref<Fila[]>([])
const total = ref(0)
const mostrados = ref(0)
const cargando = ref(false)
const ayuda = ref(false)
const sel = ref<Fila | null>(null)

const desde = ref('')
const hasta = ref('')
const usuario = ref('')
const modulo = ref('')
const q = ref('')
let deb: ReturnType<typeof setTimeout> | null = null

function fmt (d: string): string {
  if (!d) return ''
  const s = d.replace('T', ' ')
  const [fecha, hora = ''] = s.split(' ')
  const [a, m, dia] = fecha.split('-')
  return `${dia}/${m}/${a} ${hora.slice(0, 8)}`.trim()
}
const resumen = (t: string) => (t || '').replace(/\s+/g, ' ').slice(0, 120)

async function cargar () {
  cargando.value = true
  try {
    const { data } = await api.get('/admin/log-errores', {
      params: { desde: desde.value, hasta: hasta.value, usuario: usuario.value.trim(), modulo: modulo.value.trim(), q: q.value.trim() },
    })
    filas.value = data?.rows ?? []
    total.value = data?.total ?? 0
    mostrados.value = data?.mostrados ?? filas.value.length
  } catch { filas.value = []; total.value = 0; mostrados.value = 0 }
  finally { cargando.value = false }
}
function cargarDeb () { if (deb) clearTimeout(deb); deb = setTimeout(cargar, 350) }

function verDetalle (f: Fila) { sel.value = f }

async function copiar (f: Fila) {
  const txt = `Fecha: ${fmt(f.fecha)}\nTerminal: ${f.terminal}\nUsuario: ${f.usuario}\nMódulo: ${f.modulo}\nCódigo: ${f.codigo}\n\n--- Error ---\n${f.detalle}\n\n--- SQL ---\n${f.sql}`
  try { await navigator.clipboard.writeText(txt) } catch { /* */ }
}

async function purgar () {
  if (!confirm('¿Eliminar los errores anteriores a 90 días?')) return
  try {
    const { data } = await api.delete('/admin/log-errores', { params: { dias: 90 } })
    alert(`Se eliminaron ${data?.borrados ?? 0} registros.`)
    cargar()
  } catch { alert('No se pudo purgar el log.') }
}

onMounted(cargar)
</script>

<style scoped>
.le-view { padding: 1rem 1.25rem; }
.le-cab { display: flex; align-items: center; gap: 12px; margin-bottom: 14px; }
.le-cab-ico { font-size: 1.8rem; }
.le-cab-tx h1 { margin: 0; font-size: 1.3rem; color: #1b4332; }
.le-cab-tx p { margin: 2px 0 0; font-size: 0.82rem; color: #64748b; }
.le-btn-ayuda { margin-left: auto; background: #fff; border: 1px solid #cbd5e1; border-radius: 8px; padding: 7px 12px; cursor: pointer; font-size: 0.82rem; }

.le-filtros { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px 12px; margin-bottom: 12px; }
.le-lbl { font-size: 0.78rem; color: #475569; }
.le-in { height: 32px; border: 1px solid #cbd5e1; border-radius: 7px; padding: 0 8px; font-size: 0.82rem; color: #1e293b; background: #fff; }
.le-txt { min-width: 130px; }
.le-btn { height: 32px; border: 1px solid #cbd5e1; background: #fff; border-radius: 7px; padding: 0 12px; cursor: pointer; font-size: 0.8rem; font-weight: 600; color: #1e293b; }
.le-btn-danger { border-color: #f0c0c0; color: #b3271e; }

.le-info { padding: 30px; text-align: center; color: #94a3b8; }
.le-total { font-size: 0.78rem; color: #94a3b8; margin-bottom: 6px; }
.le-grid-wrap { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 8px 10px; overflow-x: auto; }
.le-grid { width: 100%; border-collapse: collapse; font-size: 0.8rem; }
.le-grid th { text-align: left; padding: 7px 8px; background: #f1f5f9; color: #475569; font-size: 0.76rem; white-space: nowrap; }
.le-grid th.c, .le-grid td.c { text-align: center; }
.le-fila td { padding: 6px 8px; color: #1e293b; border-bottom: 1px solid #f1f5f9; cursor: pointer; }
.le-fila:hover td { background: #fef2f2; }
.le-fec { white-space: nowrap; color: #475569; }
.le-mod { color: #475569; font-family: ui-monospace, Consolas, monospace; font-size: 0.74rem; }
.le-det { color: #b3271e; }
.le-cod { font-size: 0.72rem; font-weight: 700; color: #7c2d12; background: #fef3c7; padding: 1px 7px; border-radius: 8px; }

.le-ov { position: fixed; inset: 0; background: rgba(15,23,42,.55); z-index: 9600; display: flex; align-items: center; justify-content: center; padding: 24px; }
.le-md { background: #fff; border-radius: 12px; width: min(760px, 96vw); max-height: 86vh; overflow: hidden; display: flex; flex-direction: column; }
.le-md-head { display: flex; align-items: center; justify-content: space-between; padding: 12px 14px; background: #1b4332; color: #fff; font-weight: 700; font-size: 0.9rem; }
.le-md-head button { background: rgba(255,255,255,.85); border: none; border-radius: 6px; width: 30px; height: 30px; cursor: pointer; font-weight: 700; }
.le-md-body { padding: 16px; overflow: auto; font-size: 0.85rem; color: #334155; }
.le-kv { margin-bottom: 4px; }
.le-seccion { margin: 12px 0 4px; font-weight: 700; color: #1b4332; font-size: 0.8rem; }
.le-pre { background: #0f172a; color: #e2e8f0; padding: 10px 12px; border-radius: 8px; font-size: 0.76rem; white-space: pre-wrap; word-break: break-word; margin: 0; max-height: 240px; overflow: auto; }
</style>
