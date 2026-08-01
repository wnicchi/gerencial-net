<!-- LogActividadView.vue — Auditoría: qué hicieron los usuarios (solo admin). -->
<template>
  <div class="la-view">
    <div class="la-cab">
      <div class="la-cab-ico">🕵️</div>
      <div class="la-cab-tx">
        <h1>Log de actividad</h1>
        <p>Registro de altas, cambios y bajas hechos por los usuarios</p>
      </div>
      <button class="la-btn-ayuda" title="Ayuda" @click="ayuda = true">❓ Ayuda</button>
    </div>

    <div class="la-filtros">
      <label class="la-lbl">Desde</label>
      <input v-model="desde" type="date" class="la-in" @change="cargar" />
      <label class="la-lbl">Hasta</label>
      <input v-model="hasta" type="date" class="la-in" @change="cargar" />
      <input v-model="usuario" class="la-in la-txt" placeholder="Usuario…" @input="cargarDeb" />
      <input v-model="modulo" class="la-in la-txt" placeholder="Módulo…" @input="cargarDeb" />
      <select v-model="operacion" class="la-in" @change="cargar">
        <option value="">Toda operación</option>
        <option value="INSERT">Altas</option>
        <option value="UPDATE">Modificaciones</option>
        <option value="DELETE">Bajas</option>
      </select>
      <input v-model="q" class="la-in la-txt" placeholder="Texto / SQL…" @input="cargarDeb" />
      <button class="la-btn" @click="cargar">🔄 Actualizar</button>
      <div style="flex:1"></div>
      <button class="la-btn la-btn-danger" title="Eliminar actividad anterior a 180 días" @click="purgar">🧹 Purgar +180 días</button>
    </div>

    <div v-if="cargando" class="la-info">⟳ Cargando…</div>
    <div v-else-if="!filas.length" class="la-info">Sin actividad para los filtros elegidos.</div>
    <div v-else class="la-grid-wrap">
      <div class="la-total">{{ mostrados }} de {{ total }} registro{{ total === 1 ? '' : 's' }}{{ total > mostrados ? ' (refiná los filtros para ver más)' : '' }}</div>
      <table class="la-grid">
        <thead>
          <tr>
            <th style="width:150px">Fecha / hora</th>
            <th style="width:130px">Usuario</th>
            <th style="width:110px">Terminal</th>
            <th style="width:80px" class="c">Acción</th>
            <th style="width:150px">Módulo</th>
            <th>Actividad</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="f in filas" :key="f.id" class="la-fila" title="Ver detalle técnico" @click="verDetalle(f)">
            <td class="la-fec">{{ fmt(f.fecha) }}</td>
            <td>{{ (f.usuario || '').trim() }}</td>
            <td>{{ (f.terminal || '').trim() }}</td>
            <td class="c"><span class="la-op" :class="'op-' + f.operacion.toLowerCase()">{{ accion(f.operacion) }}</span></td>
            <td class="la-mod">{{ (f.modulo || '').trim() }}</td>
            <td>{{ (f.texto || '').trim() }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Detalle -->
    <Teleport to="body">
      <div v-if="sel" class="la-ov" @click.self="sel = null">
        <div class="la-md">
          <div class="la-md-head"><span>🕵️ Detalle de la actividad #{{ sel.id }}</span><button @click="sel = null">✕</button></div>
          <div class="la-md-body">
            <div class="la-kv"><b>Fecha:</b> {{ fmt(sel.fecha) }}</div>
            <div class="la-kv"><b>Usuario:</b> {{ sel.usuario || '—' }} &nbsp; <b>Terminal:</b> {{ sel.terminal || '—' }}</div>
            <div class="la-kv"><b>Módulo:</b> {{ sel.modulo || '—' }} &nbsp; <b>Acción:</b> {{ accion(sel.operacion) }}</div>
            <div class="la-seccion">Actividad</div>
            <div class="la-txt-det">{{ sel.texto }}</div>
            <div class="la-seccion">Comando SQL (técnico)</div>
            <pre class="la-pre">{{ sel.sql }}</pre>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Ayuda -->
    <Teleport to="body">
      <div v-if="ayuda" class="la-ov" @click.self="ayuda = false">
        <div class="la-md" style="width:min(560px,96vw)">
          <div class="la-md-head"><span>❓ Ayuda</span><button @click="ayuda = false">✕</button></div>
          <div class="la-md-body">
            <p>Cada vez que un usuario <b>agrega, modifica o elimina</b> algo, queda registrado acá con
              fecha, usuario, terminal, módulo y un texto simple de lo que hizo.</p>
            <p>Filtrá por fecha, usuario, módulo, tipo de acción (altas/modificaciones/bajas) o por texto.
              Hacé clic en una fila para ver el <b>detalle técnico</b> (el comando que se ejecutó).</p>
            <p><b>Purgar +180 días</b> elimina la actividad más vieja que 180 días para que la tabla no crezca indefinidamente.</p>
            <p style="color:#64748b">Es solo para administradores porque incluye información técnica del sistema.</p>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '@/services/auth'

interface Fila { id: number; fecha: string; usuario: string; terminal: string; modulo: string; operacion: string; texto: string; sql: string }

const filas = ref<Fila[]>([])
const total = ref(0); const mostrados = ref(0)
const cargando = ref(false); const ayuda = ref(false)
const sel = ref<Fila | null>(null)

const isoMas = (d: number) => { const x = new Date(); x.setDate(x.getDate() + d); return x.toISOString().slice(0, 10) }
const desde = ref(isoMas(-7)); const hasta = ref(isoMas(0))
const usuario = ref(''); const modulo = ref(''); const operacion = ref(''); const q = ref('')
let deb: ReturnType<typeof setTimeout> | null = null

function fmt (d: string): string {
  if (!d) return ''
  const s = d.replace('T', ' ')
  const [fecha, hora = ''] = s.split(' ')
  const [a, m, dia] = fecha.split('-')
  return `${dia}/${m}/${a} ${hora.slice(0, 8)}`.trim()
}
const accion = (op: string) => ({ INSERT: 'Alta', UPDATE: 'Modif.', DELETE: 'Baja' } as any)[op] || op

async function cargar () {
  cargando.value = true
  try {
    const { data } = await api.get('/admin/log-actividad', {
      params: { desde: desde.value, hasta: hasta.value, usuario: usuario.value.trim(), modulo: modulo.value.trim(), operacion: operacion.value, q: q.value.trim() },
    })
    filas.value = data?.rows ?? []
    total.value = data?.total ?? 0
    mostrados.value = data?.mostrados ?? filas.value.length
  } catch { filas.value = []; total.value = 0; mostrados.value = 0 }
  finally { cargando.value = false }
}
function cargarDeb () { if (deb) clearTimeout(deb); deb = setTimeout(cargar, 350) }
function verDetalle (f: Fila) { sel.value = f }

async function purgar () {
  if (!confirm('¿Eliminar la actividad anterior a 180 días?')) return
  try {
    const { data } = await api.delete('/admin/log-actividad', { params: { dias: 180 } })
    alert(`Se eliminaron ${data?.borrados ?? 0} registros.`)
    cargar()
  } catch { alert('No se pudo purgar el log.') }
}

onMounted(cargar)
</script>

<style scoped>
.la-view { padding: 1rem 1.25rem; }
.la-cab { display: flex; align-items: center; gap: 12px; margin-bottom: 14px; }
.la-cab-ico { font-size: 1.8rem; }
.la-cab-tx h1 { margin: 0; font-size: 1.3rem; color: #1b4332; }
.la-cab-tx p { margin: 2px 0 0; font-size: 0.82rem; color: #64748b; }
.la-btn-ayuda { margin-left: auto; background: #fff; border: 1px solid #cbd5e1; border-radius: 8px; padding: 7px 12px; cursor: pointer; font-size: 0.82rem; }

.la-filtros { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px 12px; margin-bottom: 12px; }
.la-lbl { font-size: 0.78rem; color: #475569; }
.la-in { height: 32px; border: 1px solid #cbd5e1; border-radius: 7px; padding: 0 8px; font-size: 0.82rem; color: #1e293b; background: #fff; }
.la-txt { min-width: 120px; }
.la-btn { height: 32px; border: 1px solid #cbd5e1; background: #fff; border-radius: 7px; padding: 0 12px; cursor: pointer; font-size: 0.8rem; font-weight: 600; color: #1e293b; }
.la-btn-danger { border-color: #f0c0c0; color: #b3271e; }

.la-info { padding: 30px; text-align: center; color: #94a3b8; }
.la-total { font-size: 0.78rem; color: #94a3b8; margin-bottom: 6px; }
.la-grid-wrap { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 8px 10px; overflow-x: auto; }
.la-grid { width: 100%; border-collapse: collapse; font-size: 0.8rem; }
.la-grid th { text-align: left; padding: 7px 8px; background: #f1f5f9; color: #475569; font-size: 0.76rem; white-space: nowrap; }
.la-grid th.c, .la-grid td.c { text-align: center; }
.la-fila td { padding: 6px 8px; color: #1e293b; border-bottom: 1px solid #f1f5f9; cursor: pointer; white-space: nowrap; }
.la-fila:hover td { background: #f0f9f4; }
.la-fec { white-space: nowrap; color: #475569; }
.la-mod { color: #475569; }
.la-op { font-size: 0.72rem; font-weight: 700; padding: 1px 8px; border-radius: 10px; }
.op-insert { color: #166534; background: #dcfce7; }
.op-update { color: #92400e; background: #fef3c7; }
.op-delete { color: #b3271e; background: #fee2e2; }

.la-ov { position: fixed; inset: 0; background: rgba(15,23,42,.55); z-index: 9600; display: flex; align-items: center; justify-content: center; padding: 24px; }
.la-md { background: #fff; border-radius: 12px; width: min(760px, 96vw); max-height: 86vh; overflow: hidden; display: flex; flex-direction: column; }
.la-md-head { display: flex; align-items: center; justify-content: space-between; padding: 12px 14px; background: #1b4332; color: #fff; font-weight: 700; font-size: 0.9rem; }
.la-md-head button { background: rgba(255,255,255,.85); border: none; border-radius: 6px; width: 30px; height: 30px; cursor: pointer; font-weight: 700; }
.la-md-body { padding: 16px; overflow: auto; font-size: 0.85rem; color: #334155; }
.la-kv { margin-bottom: 4px; }
.la-seccion { margin: 12px 0 4px; font-weight: 700; color: #1b4332; font-size: 0.8rem; }
.la-txt-det { background: #f1f5f9; padding: 8px 12px; border-radius: 8px; }
.la-pre { background: #0f172a; color: #e2e8f0; padding: 10px 12px; border-radius: 8px; font-size: 0.76rem; white-space: pre-wrap; word-break: break-word; margin: 0; max-height: 240px; overflow: auto; }
</style>
