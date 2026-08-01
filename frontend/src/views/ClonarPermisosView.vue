<!-- ClonarPermisosView.vue — Copia los permisos de menú de un usuario a otro(s).
     Solo administradores. Reemplaza por completo los permisos de cada destino. -->
<template>
  <div class="cl-view">
    <div class="cl-cab">
      <div class="cl-ico">👥</div>
      <div class="cl-tx">
        <h1>Clonar permisos</h1>
        <p>Copiá los permisos de menú de un usuario a otro</p>
      </div>
      <button class="cl-ayuda" title="Ayuda" @click="ayuda = true">❓ Ayuda</button>
    </div>

    <div v-if="cargando" class="cl-info">⟳ Cargando usuarios…</div>

    <div v-else class="cl-body">
      <!-- Origen -->
      <div class="cl-col">
        <label class="cl-lbl">1) Usuario origen (de quién copiar)</label>
        <input v-model="filtroOrigen" class="cl-busc" placeholder="Buscar…" />
        <select v-model="origen" size="12" class="cl-lista">
          <option v-for="u in usuariosFiltrados(filtroOrigen)" :key="u.CODIGO" :value="u.CODIGO">
            {{ u.NOMBRE }} ({{ u.DATO1 }}){{ u.ES_ADMIN ? ' · ADMIN' : '' }}
          </option>
        </select>
      </div>

      <div class="cl-flecha">➡️</div>

      <!-- Destinos -->
      <div class="cl-col">
        <label class="cl-lbl">2) Usuarios destino (a quién aplicar)</label>
        <input v-model="filtroDestino" class="cl-busc" placeholder="Buscar…" />
        <div class="cl-lista cl-checks">
          <label v-for="u in usuariosFiltrados(filtroDestino)" :key="u.CODIGO" class="cl-check" :class="{ off: u.CODIGO === origen }">
            <input type="checkbox" :value="u.CODIGO" v-model="destinos" :disabled="u.CODIGO === origen" />
            {{ u.NOMBRE }} ({{ u.DATO1 }}){{ u.ES_ADMIN ? ' · ADMIN' : '' }}
          </label>
        </div>
        <div class="cl-acc-dest">
          <button class="cl-mini" @click="marcarTodos(true)">✓ Todos</button>
          <button class="cl-mini" @click="marcarTodos(false)">✗ Ninguno</button>
          <span class="cl-cnt">{{ destinos.length }} elegido(s)</span>
        </div>
      </div>
    </div>

    <div class="cl-footer">
      <button class="cl-btn" :disabled="!origen || !destinos.length || proc" @click="clonar">
        {{ proc ? '⟳ Clonando…' : '🪄 Clonar permisos' }}
      </button>
      <span v-if="msg" :class="['cl-msg', msgErr ? 'err' : 'ok']">{{ msg }}</span>
    </div>

    <!-- Ayuda -->
    <Teleport to="body">
      <div v-if="ayuda" class="cl-ov" @click.self="ayuda = false">
        <div class="cl-md">
          <div class="cl-md-head"><span>❓ Ayuda</span><button @click="ayuda = false">✕</button></div>
          <div class="cl-md-body">
            <p>Copia los <b>permisos de menú</b> de un usuario a otro. Elegí el <b>origen</b>
              (de quién copiar) y marcá uno o más <b>destinos</b> (a quién aplicar).</p>
            <p>Los permisos del destino se <b>reemplazan por completo</b> por los del origen.
              Es útil para dar de alta a alguien con los mismos accesos que un compañero.</p>
            <p style="color:#64748b">Recordá que cada usuario define sus permisos individualmente (el rol es
              solo la foto inicial). Los administradores ven todo, sin importar lo que se clone.
              El usuario clonado debe cerrar sesión y volver a entrar para tomar los cambios.</p>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '@/services/auth'

interface Usuario { CODIGO: number; NOMBRE: string; DATO1: string; NIVEL: string; ES_ADMIN: number }

const usuarios = ref<Usuario[]>([])
const cargando = ref(true)
const origen = ref<number | null>(null)
const destinos = ref<number[]>([])
const filtroOrigen = ref('')
const filtroDestino = ref('')
const proc = ref(false)
const ayuda = ref(false)
const msg = ref(''); const msgErr = ref(false)

function usuariosFiltrados (f: string): Usuario[] {
  const t = f.trim().toLowerCase()
  if (!t) return usuarios.value
  return usuarios.value.filter(u =>
    (u.NOMBRE || '').toLowerCase().includes(t) || (u.DATO1 || '').toLowerCase().includes(t))
}

function marcarTodos (v: boolean) {
  destinos.value = v
    ? usuariosFiltrados(filtroDestino.value).filter(u => u.CODIGO !== origen.value).map(u => u.CODIGO)
    : []
}

async function cargar () {
  cargando.value = true
  try {
    const { data } = await api.get('/admin/usuarios')
    usuarios.value = (data ?? []).map((u: any) => ({
      CODIGO: Number(u.CODIGO), NOMBRE: (u.NOMBRE || '').trim(),
      DATO1: (u.DATO1 || '').trim(), NIVEL: String(u.NIVEL ?? ''), ES_ADMIN: Number(u.ES_ADMIN ?? 0),
    }))
  } catch { usuarios.value = [] }
  finally { cargando.value = false }
}

async function clonar () {
  if (!origen.value || !destinos.value.length) return
  const nomOrigen = usuarios.value.find(u => u.CODIGO === origen.value)?.NOMBRE ?? ''
  if (!confirm(`¿Clonar los permisos de "${nomOrigen}" a ${destinos.value.length} usuario(s)? Se reemplazarán sus permisos actuales.`)) return
  proc.value = true; msg.value = ''
  try {
    const { data } = await api.post('/admin/permisos/clonar', { origen: origen.value, destinos: destinos.value })
    msgErr.value = false
    msg.value = `Listo: ${data.clonados} usuario(s) actualizados con ${data.permisos} permiso(s).`
      + (data.sin_restriccion ? ' ⚠️ El origen no tenía restricciones: los destinos verán todo el menú.' : '')
    destinos.value = []
  } catch (e: any) {
    msgErr.value = true
    msg.value = e?.response?.data?.message ?? 'No se pudo clonar los permisos.'
  } finally { proc.value = false }
}

onMounted(cargar)
</script>

<style scoped>
.cl-view { padding: 1rem 1.25rem; max-width: 1000px; }
.cl-cab { display: flex; align-items: center; gap: 12px; margin-bottom: 14px; }
.cl-ico { font-size: 1.8rem; }
.cl-tx h1 { margin: 0; font-size: 1.3rem; color: #1b4332; }
.cl-tx p { margin: 2px 0 0; font-size: 0.82rem; color: #64748b; }
.cl-ayuda { margin-left: auto; background: #fff; border: 1px solid #cbd5e1; border-radius: 8px; padding: 7px 12px; cursor: pointer; font-size: 0.82rem; }

.cl-info { padding: 30px; text-align: center; color: #94a3b8; }
.cl-body { display: flex; align-items: stretch; gap: 14px; }
.cl-col { flex: 1; display: flex; flex-direction: column; min-width: 0; }
.cl-flecha { display: flex; align-items: center; font-size: 1.6rem; }
.cl-lbl { font-size: 0.82rem; font-weight: 700; color: #374151; margin-bottom: 6px; }
.cl-busc { height: 32px; border: 1px solid #cbd5e1; border-radius: 7px; padding: 0 8px; font-size: 0.82rem; margin-bottom: 6px; }
.cl-lista { border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; font-size: 0.82rem; color: #1e293b; height: 300px; overflow: auto; }
select.cl-lista { padding: 4px; }
.cl-checks { padding: 6px 8px; }
.cl-check { display: block; padding: 3px 0; cursor: pointer; }
.cl-check.off { opacity: 0.4; }
.cl-check input { margin-right: 6px; }
.cl-acc-dest { display: flex; align-items: center; gap: 8px; margin-top: 6px; }
.cl-mini { background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 6px; padding: 3px 10px; cursor: pointer; font-size: 0.76rem; }
.cl-cnt { font-size: 0.76rem; color: #64748b; }

.cl-footer { display: flex; align-items: center; gap: 12px; margin-top: 14px; }
.cl-btn { background: #16a34a; color: #fff; border: none; border-radius: 8px; padding: 10px 22px; cursor: pointer; font-weight: 700; font-size: 0.9rem; }
.cl-btn:disabled { opacity: 0.5; cursor: default; }
.cl-msg { font-size: 0.84rem; padding: 6px 12px; border-radius: 6px; }
.cl-msg.ok { background: #dcfce7; color: #166534; }
.cl-msg.err { background: #fee2e2; color: #b91c1c; }

.cl-ov { position: fixed; inset: 0; background: rgba(15,23,42,.55); z-index: 9600; display: flex; align-items: center; justify-content: center; padding: 24px; }
.cl-md { background: #fff; border-radius: 12px; width: min(560px, 96vw); max-height: 82vh; overflow: hidden; display: flex; flex-direction: column; }
.cl-md-head { display: flex; align-items: center; justify-content: space-between; padding: 12px 14px; background: #1b4332; color: #fff; font-weight: 700; font-size: 0.9rem; }
.cl-md-head button { background: rgba(255,255,255,.85); border: none; border-radius: 6px; width: 30px; height: 30px; cursor: pointer; font-weight: 700; }
.cl-md-body { padding: 16px; overflow: auto; font-size: 0.86rem; color: #334155; line-height: 1.5; }

@media (max-width: 760px) { .cl-body { flex-direction: column; } .cl-flecha { transform: rotate(90deg); justify-content: center; } }
</style>
