<!-- ParametrosView.vue — Sistema / Parámetros (4 solapas) -->
<template>
  <div class="par-view">
    <div class="par-cab">
      <div class="par-ico">⚙️</div>
      <div class="par-tx"><h1>Parámetros del Sistema</h1><p>Configuración general de la aplicación</p></div>
      <button class="par-ayuda" @click="ayuda = true">❓ Ayuda</button>
    </div>

    <div class="par-tabs">
      <button v-for="t in tabs" :key="t.id" class="par-tab" :class="{ on: tab === t.id }" @click="tab = t.id">{{ t.icono }} {{ t.label }}</button>
    </div>

    <transition name="fade">
      <div v-if="msg" :class="['par-msg', msgErr ? 'err' : 'ok']">{{ msg }}</div>
    </transition>

    <div class="par-body">
      <!-- ════ PARÁMETROS VARIOS ════ -->
      <div v-show="tab === 'varios'" class="par-panel">
        <div class="par-cols">
          <!-- Costo de almuerzos -->
          <section class="par-card">
            <h2>🍽️ Costo de Almuerzos</h2>
            <form class="alm-form" @submit.prevent="guardarAlmuerzo" v-enter-next>
              <select v-model.number="almNuevo.mes" required>
                <option v-for="(m, i) in meses" :key="i" :value="i + 1">{{ m }}</option>
              </select>
              <input v-model.number="almNuevo.anio" type="number" min="1900" max="2100" placeholder="Año" required class="inp-anio" />
              <input v-model.number="almNuevo.importe" type="number" step="0.01" min="0" placeholder="Importe" required class="inp-imp" />
              <button type="submit" class="btn-add">＋ Agregar</button>
            </form>
            <div class="alm-tabla">
              <div class="alm-head"><span>Mes</span><span>Año</span><span>Importe</span><span></span></div>
              <div class="alm-body">
                <div v-for="a in almuerzos" :key="a.anio + '-' + a.mes" class="alm-fila">
                  <span>{{ meses[a.mes - 1] }}</span><span>{{ a.anio }}</span>
                  <span class="alm-imp">$ {{ a.importe.toFixed(2) }}</span>
                  <button class="alm-del" @click="eliminarAlmuerzo(a.anio, a.mes)" title="Eliminar">🗑</button>
                </div>
                <p v-if="!almuerzos.length" class="par-vacio">Sin costos cargados.</p>
              </div>
            </div>
          </section>

          <!-- Base de datos -->
          <section class="par-card">
            <h2>🗄️ Base de Datos</h2>
            <div class="bd-bloque">
              <label>Empresa SILCAR</label>
              <div class="bd-row">
                <input v-model="bases.silcar.ruta" type="text" placeholder="Ruta de la base de datos SILCAR" />
                <button class="btn-add" @click="guardarBase(1)">💾</button>
              </div>
              <small v-if="bases.silcar.usuario" class="bd-meta">Últ. cambio: {{ bases.silcar.usuario }} · {{ fmtFecha(bases.silcar.fechor) }}</small>
            </div>
            <div class="bd-bloque">
              <label>Empresa LOGÍSTICA</label>
              <div class="bd-row">
                <input v-model="bases.logistica.ruta" type="text" placeholder="Ruta de la base de datos LOGÍSTICA" />
                <button class="btn-add" @click="guardarBase(2)">💾</button>
              </div>
              <small v-if="bases.logistica.usuario" class="bd-meta">Últ. cambio: {{ bases.logistica.usuario }} · {{ fmtFecha(bases.logistica.fechor) }}</small>
            </div>
          </section>
        </div>
      </div>

      <!-- ════ RELOJ DE PERSONAL ════ -->
      <div v-show="tab === 'reloj'" class="par-panel">
        <section class="par-card par-card-solo">
          <h2>🕐 Reloj de Personal</h2>
          <p class="par-info">Ruta de la carpeta donde el reloj deposita los archivos de fichadas.</p>
          <div class="bd-bloque">
            <label>Carpeta de datos del reloj</label>
            <div class="bd-row">
              <input v-model="reloj.ruta" type="text" placeholder="C:\RELOJ_DATA\" />
              <button class="btn-add" @click="guardarReloj">💾 Guardar</button>
            </div>
            <small v-if="reloj.usuario" class="bd-meta">Últ. cambio: {{ reloj.usuario }} · {{ fmtFecha(reloj.fechor) }}</small>
          </div>
        </section>
      </div>

      <!-- ════ LIQUIDACIONES (historial de conexiones) ════ -->
      <div v-show="tab === 'liquidaciones'" class="par-panel">
        <section class="par-card par-card-solo">
          <h2>💼 Liquidaciones — Conexiones registradas</h2>
          <p class="par-info">Historial de rutas de base de datos utilizadas (auditoría: responsable y fecha).</p>
          <div class="hist-tabla">
            <div class="hist-head"><span>#</span><span>Empresa</span><span>Ruta</span><span>Responsable</span><span>Fecha</span></div>
            <div class="hist-body">
              <div v-for="h in historial" :key="h.codigo" class="hist-fila">
                <span>{{ h.codigo }}</span>
                <span><em :class="'emp-' + h.empresa">{{ empresaNombre(h.empresa) }}</em></span>
                <span class="hist-ruta">{{ h.ruta }}</span>
                <span>{{ h.usuario }}</span>
                <span>{{ fmtFecha(h.fechor) }}</span>
              </div>
              <p v-if="!historial.length" class="par-vacio">Sin registros.</p>
            </div>
          </div>
        </section>
      </div>

      <!-- ════ EMAIL CUMPLEAÑOS ════ -->
      <div v-show="tab === 'emails'" class="par-panel">
        <section class="par-card par-card-solo">
          <h2>🎂 Lista de Email — Aviso de Cumpleaños</h2>
          <form class="mail-form" @submit.prevent="agregarEmail">
            <input v-model="emailNuevo" type="email" placeholder="nombre@empresa.com" />
            <button type="submit" class="btn-add">＋ Agregar</button>
          </form>
          <div class="mail-acciones">
            <button class="btn-mini" @click="marcarMails(true)">✓ Todos</button>
            <button class="btn-mini" @click="marcarMails(false)">✗ Ninguno</button>
            <button class="btn-del" :disabled="!mailsElegidos.length" @click="eliminarMails(false)">🗑 Eliminar elegidos ({{ mailsElegidos.length }})</button>
            <button class="btn-del-todos" :disabled="!emails.length" @click="eliminarMails(true)">🗑 Vaciar lista</button>
          </div>
          <div class="mail-tabla">
            <label v-for="e in emails" :key="e" class="mail-fila">
              <input type="checkbox" :value="e" v-model="mailsElegidos" />
              <span>{{ e }}</span>
            </label>
            <p v-if="!emails.length" class="par-vacio">Sin emails en la lista.</p>
          </div>
          <p class="par-info">Total: {{ emails.length }} email(s).</p>
        </section>
      </div>
    </div>

    <!-- Ayuda -->
    <transition name="fade">
      <div v-if="ayuda" class="par-overlay" @click.self="ayuda = false">
        <div class="par-modal">
          <h3>❓ Ayuda — Parámetros del Sistema</h3>
          <ul>
            <li><strong>Parámetros Varios:</strong> cargá el costo del almuerzo por mes/año y las rutas de la base de datos de cada empresa.</li>
            <li><strong>Reloj de Personal:</strong> indicá la carpeta donde el reloj deja los archivos de fichadas.</li>
            <li><strong>Liquidaciones:</strong> consulta del historial de conexiones a la base de datos, con responsable y fecha de cada cambio.</li>
            <li><strong>Email Cumpleaños:</strong> administrá los correos que reciben el aviso diario de cumpleaños.</li>
          </ul>
          <button class="par-cerrar" @click="ayuda = false">Cerrar</button>
        </div>
      </div>
    </transition>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '@/services/auth'

const meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre']
const tabs = [
  { id: 'varios', label: 'Parámetros Varios', icono: '🔧' },
  { id: 'reloj', label: 'Reloj de Personal', icono: '🕐' },
  { id: 'liquidaciones', label: 'Liquidaciones', icono: '💼' },
  { id: 'emails', label: 'Email Cumpleaños', icono: '🎂' },
] as const
type TabId = typeof tabs[number]['id']
const tab = ref<TabId>('varios')
const ayuda = ref(false)

const msg = ref(''); const msgErr = ref(false)
const flash = (t: string, e = false) => { msg.value = t; msgErr.value = e; if (t && !e) setTimeout(() => msg.value = '', 4000) }

const hoy = new Date()
interface Almuerzo { mes: number; anio: number; importe: number }
const almuerzos = ref<Almuerzo[]>([])
const almNuevo = ref({ mes: hoy.getMonth() + 1, anio: hoy.getFullYear(), importe: 0 })

interface BaseInfo { ruta: string; usuario: string; fechor: string }
const bases = ref<{ silcar: BaseInfo; logistica: BaseInfo }>({ silcar: { ruta: '', usuario: '', fechor: '' }, logistica: { ruta: '', usuario: '', fechor: '' } })

const reloj = ref<BaseInfo>({ ruta: '', usuario: '', fechor: '' })

interface Hist { codigo: number; empresa: number; ruta: string; usuario: string; fechor: string }
const historial = ref<Hist[]>([])

const emails = ref<string[]>([])
const emailNuevo = ref('')
const mailsElegidos = ref<string[]>([])

const fmtFecha = (f: string) => {
  if (!f) return ''
  const d = new Date(f.replace(' ', 'T'))
  return isNaN(d.getTime()) ? f : d.toLocaleDateString('es-AR') + ' ' + d.toLocaleTimeString('es-AR', { hour: '2-digit', minute: '2-digit' })
}
const empresaNombre = (e: number) => e === 1 ? 'SILCAR' : e === 2 ? 'LOGÍSTICA' : 'General'

onMounted(async () => { await Promise.all([cargarAlmuerzos(), cargarBases(), cargarReloj(), cargarHistorial(), cargarEmails()]) })

async function cargarAlmuerzos () { try { almuerzos.value = (await api.get('/parametros/almuerzos')).data } catch {} }
async function guardarAlmuerzo () {
  try { await api.post('/parametros/almuerzos', almNuevo.value); await cargarAlmuerzos(); flash('Costo guardado.') }
  catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo guardar.', true) }
}
async function eliminarAlmuerzo (anio: number, mes: number) {
  if (!confirm(`¿Eliminar el costo de ${meses[mes - 1]} ${anio}?`)) return
  try { await api.delete(`/parametros/almuerzos/${anio}/${mes}`); await cargarAlmuerzos(); flash('Costo eliminado.') }
  catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo eliminar.', true) }
}

async function cargarBases () { try { bases.value = (await api.get('/parametros/bases')).data } catch {} }
async function guardarBase (empresa: 1 | 2) {
  const ruta = empresa === 1 ? bases.value.silcar.ruta : bases.value.logistica.ruta
  if (!ruta.trim()) return flash('Indicá la ruta.', true)
  try { await api.post('/parametros/bases', { empresa, ruta }); await Promise.all([cargarBases(), cargarHistorial()]); flash('Ruta registrada.') }
  catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo guardar.', true) }
}

async function cargarReloj () { try { reloj.value = (await api.get('/parametros/reloj')).data } catch {} }
async function guardarReloj () {
  if (!reloj.value.ruta.trim()) return flash('Indicá la ruta.', true)
  try { await api.post('/parametros/reloj', { ruta: reloj.value.ruta }); await cargarReloj(); flash('Ruta del reloj guardada.') }
  catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo guardar.', true) }
}

async function cargarHistorial () { try { historial.value = (await api.get('/parametros/bases/historial')).data } catch {} }

async function cargarEmails () { try { emails.value = (await api.get('/parametros/emails')).data; mailsElegidos.value = [] } catch {} }
async function agregarEmail () {
  if (!emailNuevo.value.trim()) return
  try { await api.post('/parametros/emails', { email: emailNuevo.value }); emailNuevo.value = ''; await cargarEmails(); flash('Email agregado.') }
  catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo agregar.', true) }
}
const marcarMails = (m: boolean) => { mailsElegidos.value = m ? [...emails.value] : [] }
async function eliminarMails (todos: boolean) {
  if (todos) { if (!confirm('¿Vaciar TODA la lista de emails?')) return }
  else if (!mailsElegidos.value.length) return
  try {
    await api.post('/parametros/emails/eliminar', todos ? { todos: true } : { emails: mailsElegidos.value })
    await cargarEmails(); flash('Emails eliminados.')
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo eliminar.', true) }
}
</script>

<style scoped>
.par-view { display: flex; flex-direction: column; height: 100%; overflow: hidden; }
.par-cab { display: flex; align-items: center; gap: 14px; padding: 14px 18px; background: #fff; border-bottom: 1px solid #e2e8f0; }
.par-ico { font-size: 28px; } .par-tx h1 { margin: 0; font-size: 19px; color: #1e293b; } .par-tx p { margin: 2px 0 0; font-size: 13px; color: #6b7280; }
.par-ayuda { margin-left: auto; background: #e0eefc; color: #2d6a9f; border: none; padding: 8px 14px; border-radius: 8px; cursor: pointer; font-weight: 700; font-size: 13px; }
.par-tabs { display: flex; gap: 4px; padding: 10px 18px 0; background: #fff; border-bottom: 1px solid #e2e8f0; }
.par-tab { border: none; background: #f1f5f9; color: #475569; padding: 9px 16px; border-radius: 8px 8px 0 0; cursor: pointer; font-size: 13px; font-weight: 600; }
.par-tab.on { background: #2d6a9f; color: #fff; }
.par-msg { margin: 10px 18px 0; padding: 9px 14px; border-radius: 8px; font-size: 13px; } .par-msg.ok { background: #d1fae5; color: #065f46; } .par-msg.err { background: #fee2e2; color: #991b1b; }
.par-body { flex: 1; overflow: auto; padding: 16px 18px; }
.par-cols { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.par-card { background: #fff; border: 1px solid #dde4ee; border-radius: 12px; padding: 16px; }
.par-card-solo { max-width: 820px; }
.par-card h2 { margin: 0 0 12px; font-size: 15px; color: #1a3a5c; }
.par-info { font-size: 12.5px; color: #64748b; margin: 6px 0; }
.par-vacio { color: #94a3b8; padding: 12px; font-size: 13px; text-align: center; }

.alm-form { display: flex; gap: 6px; margin-bottom: 10px; flex-wrap: wrap; }
.alm-form select, .alm-form input { border: 1px solid #c8d8ea; border-radius: 6px; padding: 7px 8px; font-size: 13px; color: #1e293b; }
.inp-anio { width: 80px; } .inp-imp { width: 100px; }
.btn-add { background: #2d6a9f; color: #fff; border: none; border-radius: 6px; padding: 7px 12px; cursor: pointer; font-weight: 700; font-size: 13px; }
.alm-tabla, .hist-tabla, .mail-tabla { border: 1px solid #e8eef5; border-radius: 8px; overflow: hidden; }
.alm-head, .alm-fila { display: grid; grid-template-columns: 1fr 70px 110px 40px; align-items: center; }
.alm-head { background: #f4f7fc; font-size: 12px; font-weight: 700; color: #2a4a6a; padding: 8px 10px; }
.alm-body { max-height: 320px; overflow: auto; }
.alm-fila { padding: 7px 10px; border-top: 1px solid #f0f4f9; font-size: 13px; color: #1e293b; }
.alm-imp { font-weight: 600; color: #065f46; } .alm-del { background: none; border: none; cursor: pointer; }

.bd-bloque { margin-bottom: 14px; } .bd-bloque label { display: block; font-size: 12.5px; font-weight: 700; color: #2a4a6a; margin-bottom: 5px; }
.bd-row { display: flex; gap: 6px; } .bd-row input { flex: 1; border: 1px solid #c8d8ea; border-radius: 6px; padding: 8px 10px; font-size: 13px; color: #1e293b; }
.bd-meta { display: block; margin-top: 4px; font-size: 11.5px; color: #94a3b8; }

.hist-head, .hist-fila { display: grid; grid-template-columns: 50px 90px 1fr 160px 140px; align-items: center; }
.hist-head { background: #2d6a9f; color: #fff; font-size: 12px; font-weight: 700; padding: 8px 10px; }
.hist-body { max-height: 460px; overflow: auto; }
.hist-fila { padding: 7px 10px; border-top: 1px solid #f0f4f9; font-size: 12.5px; color: #1e293b; }
.hist-ruta { font-family: monospace; font-size: 11.5px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.emp-1 { color: #1d4ed8; font-style: normal; font-weight: 600; } .emp-2 { color: #b45309; font-style: normal; font-weight: 600; } .emp-0 { color: #64748b; font-style: normal; }

.mail-form { display: flex; gap: 6px; margin-bottom: 10px; } .mail-form input { flex: 1; border: 1px solid #c8d8ea; border-radius: 6px; padding: 8px 10px; font-size: 13px; color: #1e293b; }
.mail-acciones { display: flex; gap: 6px; margin-bottom: 10px; flex-wrap: wrap; }
.btn-mini { background: #eef2f7; color: #2a4a6a; border: none; border-radius: 6px; padding: 7px 11px; cursor: pointer; font-size: 12.5px; font-weight: 600; }
.btn-del { background: #fee2e2; color: #991b1b; border: none; border-radius: 6px; padding: 7px 11px; cursor: pointer; font-size: 12.5px; font-weight: 700; }
.btn-del-todos { background: #991b1b; color: #fff; border: none; border-radius: 6px; padding: 7px 11px; cursor: pointer; font-size: 12.5px; font-weight: 700; }
.btn-del:disabled, .btn-del-todos:disabled { opacity: .5; cursor: default; }
.mail-tabla { max-height: 380px; overflow: auto; }
.mail-fila { display: flex; align-items: center; gap: 9px; padding: 7px 10px; border-bottom: 1px solid #f0f4f9; cursor: pointer; font-size: 13px; color: #1e293b; }
.mail-fila:hover { background: #f0f7ff; } .mail-fila input { width: 15px; height: 15px; accent-color: #2d6a9f; }

.par-overlay { position: fixed; inset: 0; background: rgba(15,23,42,.5); display: flex; align-items: center; justify-content: center; z-index: 50; }
.par-modal { background: #fff; border-radius: 14px; padding: 22px; max-width: 520px; width: 90%; }
.par-modal h3 { margin: 0 0 12px; color: #1a3a5c; } .par-modal ul { margin: 0; padding-left: 18px; color: #334155; font-size: 13.5px; line-height: 1.7; }
.par-cerrar { margin-top: 16px; background: #2d6a9f; color: #fff; border: none; border-radius: 8px; padding: 9px 18px; cursor: pointer; font-weight: 700; }
.fade-enter-active, .fade-leave-active { transition: opacity .2s; } .fade-enter-from, .fade-leave-to { opacity: 0; }
@media (max-width: 900px) { .par-cols { grid-template-columns: 1fr; } }
</style>
