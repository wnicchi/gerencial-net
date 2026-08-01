<!--
  MisPermisosView.vue — Portal del Encargado.
  El encargado (usuario con legajo asociado) carga permisos laborales para su
  personal a cargo (per_sub) y ve el historial de lo que solicitó. Pensado para
  usarse cómodo en celular (mobile-first). Los permisos quedan pendientes hasta
  que RRHH los procesa.
-->
<template>
  <div class="mp">
    <div class="mp-head">
      <div class="mp-ico">🪪</div>
      <div class="mp-tit">
        <h1>Permisos Laborales</h1>
        <p v-if="encargado">Responsable: {{ encargado.nombre }} (Leg. {{ encargado.legajo }})</p>
        <p v-else>Solicitud de permisos para tu personal a cargo</p>
      </div>
    </div>

    <!-- Solapas -->
    <div class="mp-tabs">
      <button :class="{ act: tab === 'solicitar' }" @click="tab = 'solicitar'">➕ Solicitar</button>
      <button :class="{ act: tab === 'mias' }" @click="verMias">📋 Mis solicitudes</button>
    </div>

    <!-- Sin legajo asociado -->
    <div v-if="sinLegajo" class="mp-aviso">
      ⚠️ Tu usuario todavía no tiene un legajo de empleado asociado, por lo que no
      podemos mostrar tu personal a cargo. Avisale a RRHH para que lo configure.
    </div>

    <!-- ══════════ SOLICITAR ══════════ -->
    <template v-else-if="tab === 'solicitar'">
      <!-- Paso 1: elegir empleado -->
      <div v-if="!empleadoSel" class="mp-seccion">
        <div class="mp-sub">Elegí el empleado</div>
        <input v-model="filtro" class="mp-filtro" placeholder="Buscar por nombre o legajo…" />
        <div v-if="cargandoEquipo" class="mp-info">Cargando tu equipo…</div>
        <div v-else-if="!empleadosVista.length" class="mp-info">No hay empleados para mostrar.</div>
        <div v-else class="mp-lista">
          <button v-for="e in empleadosVista" :key="e.cod" class="mp-emp" @click="elegir(e)">
            <span class="mp-avatar">{{ iniciales(e.nombre) }}</span>
            <span class="mp-emp-txt">
              <span class="mp-emp-nom">{{ e.nombre }}</span>
              <span class="mp-emp-sub">Leg. {{ e.legajo }}<template v-if="e.sector"> · {{ e.sector }}</template></span>
            </span>
            <span class="mp-emp-fl">›</span>
          </button>
        </div>
      </div>

      <!-- Paso 2: formulario del permiso -->
      <form v-else class="mp-form" @submit.prevent="confirmar">
        <button type="button" class="mp-volver" @click="empleadoSel = null">‹ Cambiar empleado</button>
        <div class="mp-empsel">
          <span class="mp-avatar">{{ iniciales(empleadoSel.nombre) }}</span>
          <span class="mp-emp-txt">
            <span class="mp-emp-nom">{{ empleadoSel.nombre }}</span>
            <span class="mp-emp-sub">Leg. {{ empleadoSel.legajo }}<template v-if="empleadoSel.sector"> · {{ empleadoSel.sector }}</template></span>
          </span>
        </div>

        <label>Tipo de permiso
          <select v-model="form.tipo" required>
            <option :value="0" disabled>Seleccioná…</option>
            <option v-for="t in tipos" :key="t.cod" :value="t.cod">{{ t.detalle }}</option>
          </select>
        </label>

        <div class="mp-fila">
          <label>Fecha desde
            <input v-model="form.fecha_desde" type="date" required />
          </label>
          <label>Fecha hasta
            <input v-model="form.fecha_hasta" type="date" required />
          </label>
        </div>

        <div class="mp-fila">
          <label>Hora inicio
            <input v-model="form.hora_inicio" type="time" />
          </label>
          <label>Hora fin
            <input v-model="form.hora_fin" type="time" />
          </label>
        </div>

        <label>Observaciones
          <textarea v-model="form.observaciones" rows="3" placeholder="Motivo / detalle (opcional)"></textarea>
        </label>

        <button type="submit" class="mp-confirmar" :disabled="guardando">
          {{ guardando ? 'Guardando…' : '✓ Confirmar permiso' }}
        </button>
      </form>
    </template>

    <!-- ══════════ MIS SOLICITUDES ══════════ -->
    <template v-else>
      <div class="mp-seccion">
        <div v-if="cargandoMias" class="mp-info">Cargando…</div>
        <div v-else-if="!mias.length" class="mp-info">Todavía no cargaste permisos.</div>
        <div v-else class="mp-lista">
          <div v-for="p in mias" :key="p.cod" class="mp-perm" :class="p.procesado ? 'ok' : 'pend'">
            <div class="mp-perm-top">
              <span class="mp-perm-emp">{{ p.empleado }}</span>
              <span class="mp-estado" :class="p.procesado ? 'ok' : 'pend'">
                {{ p.procesado ? 'Procesado' : 'Pendiente' }}
              </span>
            </div>
            <div class="mp-perm-det">
              <span>📅 {{ p.fecha_desde }}<template v-if="p.fecha_hasta && p.fecha_hasta !== p.fecha_desde"> → {{ p.fecha_hasta }}</template></span>
              <span v-if="p.hora_inicio">🕐 {{ p.hora_inicio }}<template v-if="p.hora_fin"> - {{ p.hora_fin }}</template></span>
              <span>🏷️ {{ p.falta }}</span>
            </div>
            <div v-if="p.observaciones" class="mp-perm-obs">{{ p.observaciones }}</div>
          </div>
        </div>
      </div>
    </template>

    <!-- Toast -->
    <transition name="fade">
      <div v-if="toast" class="mp-toast" :class="toast.tipo">{{ toast.msg }}</div>
    </transition>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import api from '@/services/auth'

interface Empleado { cod: number; nombre: string; legajo: string; sector: string }
interface Tipo { cod: number; detalle: string }

const tab = ref<'solicitar' | 'mias'>('solicitar')
const sinLegajo = ref(false)
const encargado = ref<{ cod: number; nombre: string; legajo: string } | null>(null)

const empleados = ref<Empleado[]>([])
const tipos = ref<Tipo[]>([])
const cargandoEquipo = ref(true)
const filtro = ref('')
const empleadoSel = ref<Empleado | null>(null)

const mias = ref<any[]>([])
const cargandoMias = ref(false)
const guardando = ref(false)
const toast = ref<{ msg: string; tipo: string } | null>(null)

const hoy = new Date().toISOString().slice(0, 10)
const form = ref({ tipo: 0, fecha_desde: hoy, fecha_hasta: hoy, hora_inicio: '', hora_fin: '', observaciones: '' })

const norm = (s: string) => (s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '')
const empleadosVista = computed(() => {
  const q = norm(filtro.value.trim())
  if (!q) return empleados.value
  return empleados.value.filter(e => norm(e.nombre).includes(q) || String(e.legajo).includes(q))
})

function iniciales (nombre: string): string {
  return (nombre || '?').split(' ').filter(Boolean).slice(0, 2).map(p => p[0]).join('').toUpperCase()
}

function mostrarToast (msg: string, tipo = 'ok') {
  toast.value = { msg, tipo }
  setTimeout(() => { toast.value = null }, 3200)
}

function elegir (e: Empleado) {
  empleadoSel.value = e
  form.value = { tipo: 0, fecha_desde: hoy, fecha_hasta: hoy, hora_inicio: '', hora_fin: '', observaciones: '' }
}

async function verMias () {
  tab.value = 'mias'
  cargandoMias.value = true
  try {
    const { data } = await api.get('/mis-permisos')
    mias.value = data?.permisos ?? []
  } catch { mias.value = [] } finally { cargandoMias.value = false }
}

async function confirmar () {
  if (!empleadoSel.value) return
  if (!form.value.tipo) { mostrarToast('Seleccioná el tipo de permiso.', 'err'); return }
  guardando.value = true
  try {
    const { data } = await api.post('/mis-permisos', {
      empleado: empleadoSel.value.cod,
      tipo: form.value.tipo,
      fecha_desde: form.value.fecha_desde,
      fecha_hasta: form.value.fecha_hasta,
      hora_inicio: form.value.hora_inicio,
      hora_fin: form.value.hora_fin,
      observaciones: form.value.observaciones,
    })
    mostrarToast(`Permiso N° ${data.nro} cargado. Queda pendiente para RRHH.`, 'ok')
    empleadoSel.value = null
    filtro.value = ''
  } catch (e: any) {
    mostrarToast(e?.response?.data?.message || 'No se pudo cargar el permiso.', 'err')
  } finally { guardando.value = false }
}

onMounted(async () => {
  try {
    const [eq, tp] = await Promise.all([
      api.get('/mis-permisos/equipo'),
      api.get('/mis-permisos/tipos'),
    ])
    sinLegajo.value = !!eq.data?.sin_legajo
    encargado.value = eq.data?.encargado ?? null
    empleados.value = eq.data?.empleados ?? []
    tipos.value = tp.data?.tipos ?? []
  } catch {
    /* sin datos */
  } finally { cargandoEquipo.value = false }
})
</script>

<style scoped>
.mp { max-width: 780px; margin: 0 auto; padding: 1rem; color: #1e293b; }

.mp-head { display: flex; align-items: center; gap: 12px; margin-bottom: 1rem; }
.mp-ico { font-size: 30px; }
.mp-tit h1 { margin: 0; font-size: 1.3rem; color: #1b4332; }
.mp-tit p { margin: 2px 0 0; font-size: 0.85rem; color: #6b7280; }

.mp-tabs { display: flex; gap: 8px; margin-bottom: 1rem; }
.mp-tabs button {
  flex: 1; padding: 0.7rem; border: 1.5px solid #d1d5db; background: #fff; border-radius: 10px;
  font-size: 0.95rem; font-weight: 600; color: #475569; cursor: pointer;
}
.mp-tabs button.act { background: #1b4332; color: #fff; border-color: #1b4332; }

.mp-aviso {
  background: #fff7ed; border: 1.5px solid #fed7aa; color: #9a3412;
  padding: 1rem; border-radius: 10px; font-size: 0.9rem; line-height: 1.4;
}

.mp-seccion { }
.mp-sub { font-weight: 700; color: #1b4332; margin-bottom: 0.5rem; }
.mp-filtro {
  width: 100%; padding: 0.75rem; border: 1.5px solid #d1d5db; border-radius: 10px;
  font-size: 1rem; margin-bottom: 0.75rem; box-sizing: border-box;
}
.mp-info { text-align: center; color: #6b7280; padding: 1.5rem; }

.mp-lista { display: flex; flex-direction: column; gap: 0.5rem; }

.mp-emp {
  display: flex; align-items: center; gap: 12px; width: 100%; text-align: left;
  padding: 0.7rem 0.8rem; background: #fff; border: 1.5px solid #e3e8ef; border-radius: 12px;
  cursor: pointer; transition: all 0.15s;
}
.mp-emp:hover { border-color: #40916c; background: #f0faf4; }
.mp-avatar {
  width: 42px; height: 42px; flex-shrink: 0; border-radius: 50%;
  background: #1b4332; color: #fff; font-weight: 700; font-size: 0.9rem;
  display: flex; align-items: center; justify-content: center;
}
.mp-emp-txt { display: flex; flex-direction: column; flex: 1; min-width: 0; }
.mp-emp-nom { font-weight: 600; color: #1e293b; }
.mp-emp-sub { font-size: 0.8rem; color: #6b7280; }
.mp-emp-fl { color: #9ca3af; font-size: 1.4rem; }

.mp-form { display: flex; flex-direction: column; gap: 0.85rem; }
.mp-volver { align-self: flex-start; background: none; border: none; color: #2b6cb0; font-weight: 600; cursor: pointer; padding: 0; font-size: 0.9rem; }
.mp-empsel { display: flex; align-items: center; gap: 12px; padding: 0.6rem 0.8rem; background: #f0faf4; border: 1.5px solid #cfe8db; border-radius: 12px; }
.mp-form label { display: flex; flex-direction: column; gap: 0.3rem; font-size: 0.85rem; font-weight: 600; color: #374151; }
.mp-form input, .mp-form select, .mp-form textarea {
  padding: 0.7rem; border: 1.5px solid #d1d5db; border-radius: 10px; font-size: 1rem;
  color: #1e293b; background: #fff; box-sizing: border-box; font-family: inherit;
}
.mp-fila { display: flex; gap: 0.75rem; }
.mp-fila label { flex: 1; }
.mp-confirmar {
  margin-top: 0.4rem; padding: 0.9rem; background: #1b4332; color: #fff; border: none;
  border-radius: 10px; font-size: 1.05rem; font-weight: 700; cursor: pointer;
}
.mp-confirmar:disabled { opacity: 0.6; cursor: default; }

.mp-perm { background: #fff; border: 1.5px solid #e3e8ef; border-left: 5px solid #999; border-radius: 12px; padding: 0.8rem; }
.mp-perm.ok { border-left-color: #16794a; }
.mp-perm.pend { border-left-color: #e08e0b; }
.mp-perm-top { display: flex; justify-content: space-between; align-items: center; gap: 8px; }
.mp-perm-emp { font-weight: 700; color: #1e293b; }
.mp-estado { font-size: 0.72rem; font-weight: 700; padding: 2px 8px; border-radius: 10px; }
.mp-estado.ok { background: #dcfce7; color: #16794a; }
.mp-estado.pend { background: #fef3c7; color: #92600b; }
.mp-perm-det { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 6px; font-size: 0.82rem; color: #475569; }
.mp-perm-obs { margin-top: 6px; font-size: 0.8rem; color: #6b7280; font-style: italic; }

.mp-toast {
  position: fixed; left: 50%; bottom: 24px; transform: translateX(-50%);
  padding: 0.8rem 1.2rem; border-radius: 10px; color: #fff; font-weight: 600;
  box-shadow: 0 6px 20px rgba(0,0,0,.2); z-index: 50; max-width: 90%; text-align: center;
}
.mp-toast.ok { background: #16794a; }
.mp-toast.err { background: #c0392b; }
.fade-enter-active, .fade-leave-active { transition: opacity 0.25s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

@media (max-width: 480px) {
  .mp-fila { flex-direction: column; gap: 0.85rem; }
}
</style>
