<!-- UsuariosActivosView.vue — Sistema / Seguridad / Usuarios en el Sistema (en vivo) -->
<template>
  <div class="ua-view">
    <div class="ua-cab">
      <div class="ua-ico">👥</div>
      <div class="ua-tx">
        <h1>Usuarios en el Sistema</h1>
        <p>Quiénes están logueados en este momento — se actualiza solo</p>
      </div>
      <div class="ua-live">
        <span class="ua-dot" :class="{ on: !error }"></span>
        <span>{{ error ? 'Sin conexión' : 'En vivo' }}</span>
        <button class="ua-refresh" :class="{ girando: cargando }" title="Actualizar ahora" @click="cargar">⟳</button>
      </div>
    </div>

    <div class="ua-resumen">
      <div class="ua-chip"><strong>{{ usuarios.length }}</strong> con sesión abierta</div>
      <div class="ua-chip activos"><strong>{{ activosAhora }}</strong> activos ahora</div>
      <div class="ua-actualizado" v-if="ultima">Última lectura: {{ horaLectura }}</div>
    </div>

    <div class="ua-body">
      <div v-if="cargando && !usuarios.length" class="ua-estado"><span class="spin">⟳</span> Cargando…</div>
      <div v-else-if="error && !usuarios.length" class="ua-estado err">{{ error }}</div>
      <div v-else-if="!usuarios.length" class="ua-estado">Nadie tiene sesión abierta en este momento.</div>

      <transition-group v-else name="lista" tag="div" class="ua-tabla">
        <div class="ua-head" key="__head">
          <span>Usuario</span><span>Login</span><span>Estado</span><span>Última actividad</span><span>Ingreso</span><span>Sesiones</span>
        </div>
        <div v-for="u in usuarios" :key="u.codigo" class="ua-fila" :class="{ activo: u.activo_ahora }">
          <span class="ua-nom"><span class="ua-avatar">{{ iniciales(u.nombre) }}</span>{{ u.nombre }}</span>
          <span class="ua-login">{{ u.login }}</span>
          <span>
            <em class="ua-estado-badge" :class="u.activo_ahora ? 'on' : 'idle'">
              <span class="mini-dot"></span>{{ u.activo_ahora ? 'Activo' : 'Inactivo' }}
            </em>
          </span>
          <span>{{ desde(u.ultima_actividad) }}</span>
          <span>{{ fmtHora(u.ingreso) }}</span>
          <span class="ua-ses">{{ u.sesiones }}</span>
        </div>
      </transition-group>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import api from '@/services/auth'

interface UsuarioActivo {
  codigo: number; nombre: string; login: string
  sesiones: number; ultima_actividad: string | null; ingreso: string | null; activo_ahora: boolean
}

const usuarios = ref<UsuarioActivo[]>([])
const cargando = ref(false)
const error = ref('')
const ultima = ref<Date | null>(null)
const INTERVALO = 15000
let timer: number | undefined

const activosAhora = computed(() => usuarios.value.filter(u => u.activo_ahora).length)
const horaLectura = computed(() => ultima.value ? ultima.value.toLocaleTimeString('es-AR') : '')

const iniciales = (n: string) => n.split(' ').slice(0, 2).map(p => p[0]).join('').toUpperCase()
const fmtHora = (f: string | null) => {
  if (!f) return '—'
  const d = new Date(f)
  return isNaN(d.getTime()) ? '—' : d.toLocaleDateString('es-AR') + ' ' + d.toLocaleTimeString('es-AR', { hour: '2-digit', minute: '2-digit' })
}
const desde = (f: string | null) => {
  if (!f) return 'sin actividad'
  const d = new Date(f).getTime()
  if (isNaN(d)) return '—'
  const seg = Math.max(0, Math.floor((Date.now() - d) / 1000))
  if (seg < 60) return 'hace ' + seg + ' s'
  const min = Math.floor(seg / 60)
  if (min < 60) return 'hace ' + min + ' min'
  const h = Math.floor(min / 60)
  return 'hace ' + h + ' h ' + (min % 60) + ' min'
}

async function cargar () {
  cargando.value = true
  try {
    const { data } = await api.get('/usuarios-activos')
    usuarios.value = data.usuarios ?? []
    ultima.value = new Date()
    error.value = ''
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? 'No se pudo obtener la lista de usuarios.'
  } finally {
    cargando.value = false
  }
}

onMounted(() => { cargar(); timer = window.setInterval(cargar, INTERVALO) })
onUnmounted(() => { if (timer) window.clearInterval(timer) })
</script>

<style scoped>
.ua-view { display: flex; flex-direction: column; height: 100%; overflow: hidden; }
.ua-cab { display: flex; align-items: center; gap: 14px; padding: 14px 18px; background: #fff; border-bottom: 1px solid #e2e8f0; }
.ua-ico { font-size: 28px; } .ua-tx h1 { margin: 0; font-size: 19px; color: #1e293b; } .ua-tx p { margin: 2px 0 0; font-size: 13px; color: #6b7280; }
.ua-live { margin-left: auto; display: flex; align-items: center; gap: 8px; font-size: 13px; color: #475569; font-weight: 600; }
.ua-dot { width: 9px; height: 9px; border-radius: 50%; background: #cbd5e1; }
.ua-dot.on { background: #16a34a; box-shadow: 0 0 0 0 rgba(22,163,74,.5); animation: pulso 2s infinite; }
@keyframes pulso { 0% { box-shadow: 0 0 0 0 rgba(22,163,74,.5); } 70% { box-shadow: 0 0 0 7px rgba(22,163,74,0); } 100% { box-shadow: 0 0 0 0 rgba(22,163,74,0); } }
.ua-refresh { border: none; background: #eef2f7; color: #2d6a9f; width: 28px; height: 28px; border-radius: 7px; cursor: pointer; font-size: 15px; }
.ua-refresh.girando { animation: giro .8s linear infinite; }
@keyframes giro { to { transform: rotate(360deg); } }
.ua-resumen { display: flex; align-items: center; gap: 10px; padding: 12px 18px; background: #f8fafc; border-bottom: 1px solid #eef2f7; }
.ua-chip { background: #fff; border: 1px solid #dde4ee; border-radius: 999px; padding: 5px 14px; font-size: 13px; color: #334155; }
.ua-chip strong { color: #1a3a5c; font-size: 15px; } .ua-chip.activos strong { color: #16a34a; }
.ua-actualizado { margin-left: auto; font-size: 12px; color: #94a3b8; }
.ua-body { flex: 1; overflow: auto; padding: 16px 18px; }
.ua-estado { text-align: center; color: #64748b; padding: 40px; font-size: 14px; } .ua-estado.err { color: #b91c1c; }
.ua-tabla { border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; background: #fff; }
.ua-head, .ua-fila { display: grid; grid-template-columns: 1.6fr 1fr 1fr 1.1fr 1.2fr 0.7fr; align-items: center; }
.ua-head { background: #2d6a9f; color: #fff; font-size: 12.5px; font-weight: 700; padding: 10px 14px; }
.ua-fila { padding: 9px 14px; border-top: 1px solid #f0f4f9; font-size: 13px; color: #1e293b; }
.ua-fila.activo { background: #f0fdf4; }
.ua-nom { display: flex; align-items: center; gap: 9px; font-weight: 600; color: #1a3a5c; }
.ua-avatar { width: 30px; height: 30px; border-radius: 50%; background: #1b4332; color: #fff; display: inline-flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; flex-shrink: 0; }
.ua-login { color: #5a7a9a; font-family: monospace; }
.ua-estado-badge { display: inline-flex; align-items: center; gap: 6px; font-style: normal; font-size: 12px; font-weight: 600; padding: 3px 9px; border-radius: 999px; }
.ua-estado-badge.on { background: #dcfce7; color: #166534; } .ua-estado-badge.idle { background: #f1f5f9; color: #64748b; }
.mini-dot { width: 7px; height: 7px; border-radius: 50%; background: currentColor; }
.ua-ses { text-align: center; font-weight: 600; }
.lista-enter-active, .lista-leave-active { transition: all .4s ease; }
.lista-enter-from { opacity: 0; transform: translateY(-8px); }
.lista-leave-to { opacity: 0; transform: translateX(20px); }
.lista-move { transition: transform .4s ease; }
</style>
