<!-- AgendaCrearView.vue — Agenda: Crear/enviar un mensaje (agenda_crear_mensaje.scx).
     Individual o a un grupo; inmediato o programado; tema + mensaje; aviso de retorno.
     (Adjuntar archivo: micro-paso siguiente.) -->
<template>
  <div class="cm-view">
    <div class="cm-cab">
      <div class="cm-ico">✉️</div>
      <div class="cm-tx"><h1>Crear un Mensaje</h1><p>Enviar un mensaje a un usuario o a un grupo (inmediato o programado)</p></div>
    </div>

    <transition name="fade"><div v-if="msg" :class="['cm-msg', msgErr ? 'err' : 'ok']">{{ msg }}</div></transition>

    <div class="cm-body">
      <!-- Columna formulario -->
      <div class="cm-form">
        <div class="cm-box">
          <label class="cm-rad"><input type="radio" value="individual" v-model="modo" /> Para usuarios individuales</label>
          <label class="cm-rad"><input type="radio" value="grupo" v-model="modo" /> Para un grupo de usuarios</label>
          <div v-if="modo === 'grupo'" class="cm-grupo">
            <select v-model.number="grupoSel" @change="aplicarGrupo">
              <option :value="0">— Elegir grupo —</option>
              <option v-for="g in grupos" :key="g.cod" :value="g.cod">{{ g.descripcion }} ({{ g.cant }})</option>
            </select>
          </div>
        </div>

        <div class="cm-box">
          <label class="cm-rad"><input type="radio" value="inmediato" v-model="cuando" /> Inmediato</label>
          <label class="cm-rad"><input type="radio" value="programado" v-model="cuando" /> Programado</label>
        </div>

        <div class="cm-sel">Usuarios seleccionados = <b>{{ seleccionados.length }}</b></div>

        <div class="cm-row">
          <div class="cm-c"><label>Fecha</label><input v-model="fecha" type="date" :disabled="cuando === 'inmediato'" /></div>
          <div class="cm-c"><label>Hora</label><input v-model="hora" type="time" :disabled="cuando === 'inmediato'" /></div>
        </div>

        <div class="cm-c"><label>Tema</label>
          <select v-model="tema"><option value="">— Seleccione —</option><option v-for="t in temas" :key="t.cod" :value="t.descripcion">{{ t.descripcion }}</option></select>
        </div>

        <div class="cm-c"><label>Mensaje</label><input v-model="mensaje" maxlength="140" placeholder="Escribí el mensaje…" /><small>{{ mensaje.length }}/140</small></div>

        <div class="cm-c"><label>Adjuntar archivo (opcional)</label>
          <div class="cm-file"><input ref="fileInput" type="file" @change="onFile" /><button v-if="archivoNombre" type="button" class="cm-quitar" title="Quitar" @click="quitarArchivo">✕</button></div>
        </div>

        <label class="cm-chk"><input type="checkbox" v-model="avisoRetorno" /> Aviso de retorno (avisar cuando lo lean)</label>

        <div class="cm-acc">
          <button class="cm-enviar" :disabled="enviando" @click="enviar">{{ enviando ? '⟳ Enviando…' : '✉️ ENVIAR' }}</button>
          <button class="cm-reset" @click="reset">↺ Reset</button>
        </div>
        <p class="cm-nota">📎 No se aceptan exe, bat, dll, cmd, com, zip, rar ni cab. Máximo 50 MB.</p>
      </div>

      <!-- Columna lista de usuarios -->
      <div class="cm-lista">
        <div class="cm-lista-head">
          <input v-model="filtro" class="cm-lbus" placeholder="🔍 Filtrar usuarios…" />
          <button class="cm-mini" @click="limpiarSel">Limpiar</button>
        </div>
        <div class="cm-lista-ley"><span><i class="cm-lg sel"></i> Elegido</span><span><i class="cm-lg act"></i> Conectado hoy</span></div>
        <div class="cm-lista-wrap">
          <label v-for="u in usuariosFiltrados" :key="u.codigo"
                 class="cm-usu" :class="{ on: sel.has(u.codigo), act: u.activo_hoy }">
            <input type="checkbox" :checked="sel.has(u.codigo)" @change="toggle(u.codigo)" />
            <span>{{ u.nombre }}</span>
          </label>
          <div v-if="!usuariosFiltrados.length" class="cm-vacio">Sin usuarios.</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import api from '@/services/auth'
import { useAuthStore } from '@/stores/auth'

interface Usu { codigo: number; nombre: string; email: string; activo_hoy: boolean }
const auth = useAuthStore()
const yo = computed(() => Number((auth.usuario as any)?.CODIGO ?? 0))

const usuarios = ref<Usu[]>([]); const grupos = ref<any[]>([]); const temas = ref<any[]>([])
const sel = ref<Set<number>>(new Set())
const modo = ref<'individual' | 'grupo'>('individual'); const grupoSel = ref(0)
const cuando = ref<'inmediato' | 'programado'>('inmediato')
const hoyIso = () => new Date().toISOString().slice(0, 10)
const ahoraHora = () => new Date().toTimeString().slice(0, 5)
const fecha = ref(hoyIso()); const hora = ref(ahoraHora())
const tema = ref(''); const mensaje = ref(''); const avisoRetorno = ref(false)
const archivo = ref<File | null>(null); const archivoNombre = ref(''); const fileInput = ref<HTMLInputElement | null>(null)
const filtro = ref(''); const enviando = ref(false)
const msg = ref(''); const msgErr = ref(false)
const flash = (t: string, e = false) => { msg.value = t; msgErr.value = e; if (t && !e) setTimeout(() => msg.value = '', 4000) }

const usuariosFiltrados = computed(() => {
  const q = filtro.value.trim().toLowerCase()
  return usuarios.value.filter(u => u.codigo !== yo.value && (!q || u.nombre.toLowerCase().includes(q) || String(u.codigo).includes(q)))
})
const seleccionados = computed(() => [...sel.value])

function toggle (cod: number) { const s = new Set(sel.value); s.has(cod) ? s.delete(cod) : s.add(cod); sel.value = s }
function limpiarSel () { sel.value = new Set() }

async function aplicarGrupo () {
  if (!grupoSel.value) return
  try {
    const { data } = await api.get(`/agenda/grupos/${grupoSel.value}`)
    const s = new Set(sel.value)
    for (const u of (data.usuarios ?? [])) if (u.codigo !== yo.value) s.add(u.codigo)
    sel.value = s
  } catch { flash('No se pudo cargar el grupo.', true) }
}

onMounted(async () => {
  try {
    usuarios.value = (await api.get('/agenda/usuarios')).data ?? []
    grupos.value = (await api.get('/agenda/grupos')).data ?? []
    temas.value = (await api.get('/agenda/temas')).data ?? []
  } catch { flash('No se pudieron cargar los datos.', true) }
})

const onFile = (e: Event) => { archivo.value = (e.target as HTMLInputElement).files?.[0] ?? null; archivoNombre.value = archivo.value?.name ?? '' }
function quitarArchivo () { archivo.value = null; archivoNombre.value = ''; if (fileInput.value) fileInput.value.value = '' }

function reset () {
  sel.value = new Set(); modo.value = 'individual'; grupoSel.value = 0; cuando.value = 'inmediato'
  fecha.value = hoyIso(); hora.value = ahoraHora(); tema.value = ''; mensaje.value = ''; avisoRetorno.value = false; filtro.value = ''
  quitarArchivo()
}

async function enviar () {
  if (!seleccionados.value.length) { flash('Debe elegir al menos un destinatario.', true); return }
  if (!tema.value) { flash('Falta el tema del mensaje.', true); return }
  if (!mensaje.value.trim()) { flash('Debe escribir un mensaje.', true); return }
  enviando.value = true
  try {
    const hhmm = parseInt(hora.value.replace(':', ''), 10) || 0
    const fd = new FormData()
    for (const c of seleccionados.value) fd.append('usuarios[]', String(c))
    fd.append('tema', tema.value); fd.append('mensaje', mensaje.value)
    fd.append('programado', cuando.value === 'programado' ? '1' : '0')
    fd.append('fecha', fecha.value); fd.append('hora', String(hhmm))
    fd.append('aviso_retorno', avisoRetorno.value ? '1' : '0')
    if (archivo.value) fd.append('archivo', archivo.value)
    const { data } = await api.post('/agenda/mensajes', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    flash(`Mensaje enviado a ${data.enviados} destinatario(s).`)
    reset()
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo enviar el mensaje.', true) }
  finally { enviando.value = false }
}
</script>

<style scoped>
.cm-view { display:flex; flex-direction:column; min-height:100%; }
.cm-cab { display:flex; align-items:center; gap:12px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.cm-ico { font-size:28px; } .cm-tx h1 { margin:0; font-size:19px; color:#1e293b; } .cm-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.cm-msg { margin:10px 18px 0; padding:9px 14px; border-radius:8px; font-size:13px; } .cm-msg.ok { background:#d1fae5; color:#065f46; } .cm-msg.err { background:#fee2e2; color:#991b1b; }
.cm-body { display:flex; gap:18px; padding:16px 18px; flex-wrap:wrap; }
.cm-form { flex:1; min-width:320px; max-width:460px; display:flex; flex-direction:column; gap:12px; }
.cm-box { border:1px solid #e2e8f0; border-radius:10px; padding:12px 14px; display:flex; flex-direction:column; gap:6px; background:#fafdff; }
.cm-rad { display:flex; align-items:center; gap:8px; font-size:13px; color:#1e293b; cursor:pointer; font-weight:600; }
.cm-grupo { margin-top:6px; } .cm-grupo select { width:100%; border:1px solid #c8d8ea; border-radius:7px; padding:8px 10px; font-size:14px; }
.cm-sel { font-size:14px; color:#1b4332; font-weight:700; }
.cm-row { display:flex; gap:12px; } .cm-row .cm-c { flex:1; }
.cm-c { display:flex; flex-direction:column; gap:4px; } .cm-c label { font-size:12px; font-weight:600; color:#374151; }
.cm-c input, .cm-c select { border:1px solid #c8d8ea; border-radius:7px; padding:8px 10px; font-size:14px; color:#1e293b; box-sizing:border-box; }
.cm-c input:disabled { background:#f1f5f9; color:#94a3b8; } .cm-c small { font-size:11px; color:#94a3b8; align-self:flex-end; }
.cm-file { display:flex; align-items:center; gap:8px; } .cm-file input { flex:1; font-size:13px; }
.cm-quitar { background:#fee2e2; color:#991b1b; border:none; border-radius:6px; width:28px; height:28px; cursor:pointer; font-weight:700; }
.cm-chk { display:flex; align-items:center; gap:8px; font-size:13px; color:#1e293b; cursor:pointer; }
.cm-acc { display:flex; gap:10px; align-items:center; margin-top:4px; }
.cm-enviar { background:#1b4332; color:#fff; border:none; border-radius:8px; padding:11px 26px; cursor:pointer; font-weight:800; font-size:14px; } .cm-enviar:disabled { opacity:.5; }
.cm-reset { background:#eef2f7; color:#475569; border:none; border-radius:8px; padding:11px 16px; cursor:pointer; font-weight:600; }
.cm-nota { font-size:11.5px; color:#94a3b8; margin:2px 0 0; }
/* Lista */
.cm-lista { flex:1; min-width:300px; max-width:460px; border:1px solid #e2e8f0; border-radius:10px; overflow:hidden; display:flex; flex-direction:column; align-self:flex-start; }
.cm-lista-head { display:flex; gap:8px; padding:10px 12px; background:#f8fafc; border-bottom:1px solid #e2e8f0; }
.cm-lbus { flex:1; border:1px solid #c8d8ea; border-radius:6px; padding:7px 10px; font-size:13px; }
.cm-mini { background:#eef2f7; border:none; border-radius:6px; padding:0 12px; cursor:pointer; font-size:12px; font-weight:600; color:#475569; }
.cm-lista-ley { display:flex; gap:16px; padding:6px 12px; font-size:11.5px; color:#64748b; border-bottom:1px solid #f1f5f9; }
.cm-lista-ley span { display:flex; align-items:center; gap:5px; } .cm-lg { width:12px; height:12px; border-radius:3px; display:inline-block; }
.cm-lg.sel { background:#ff8000; } .cm-lg.act { background:#16a34a; }
.cm-lista-wrap { max-height:56vh; overflow:auto; }
.cm-usu { display:flex; align-items:center; gap:9px; padding:7px 12px; border-bottom:1px solid #f1f5f9; cursor:pointer; font-size:13px; color:#1e293b; }
.cm-usu:hover { background:#f8fafc; }
.cm-usu.act span { font-weight:800; color:#166534; }
.cm-usu.on { background:#fff2e6; } .cm-usu.on span { color:#c2410c; }
.cm-vacio { padding:16px; text-align:center; color:#94a3b8; }
.fade-enter-active,.fade-leave-active { transition:opacity .2s; } .fade-enter-from,.fade-leave-to { opacity:0; }
</style>
