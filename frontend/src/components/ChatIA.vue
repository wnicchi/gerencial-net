<!--
  ChatIA.vue — Chat genérico con un asistente IA. Reutilizable por módulo.
  Props: endpoint (POST que recibe { messages }), titulo, subtitulo, sugerencias.
  El backend (IaController) tiene el system prompt especializado por endpoint.
-->
<template>
  <Teleport to="body">
    <transition name="ia-fade" appear>
      <div class="ia-overlay" @click.self="$emit('close')">
        <transition name="ia-pop" appear>
          <div class="ia-modal">
            <div class="ia-header">
              <div class="ia-ico">🤖</div>
              <div class="ia-htx">
                <h3>{{ titulo }}</h3>
                <p>{{ subtitulo }}</p>
              </div>
              <button class="ia-close" @click="$emit('close')">✕</button>
            </div>

            <div ref="scroll" class="ia-msgs">
              <div v-if="mensajes.length === 0" class="ia-bienvenida">
                <div class="ia-bv-ico">💬</div>
                <p>Hola 👋 Preguntame lo que quieras. Por ejemplo:</p>
                <div class="ia-sugerencias">
                  <button v-for="s in sugerencias" :key="s" @click="enviar(s)">{{ s }}</button>
                </div>
              </div>

              <div v-for="(m, i) in mensajes" :key="i" :class="['ia-msg', m.role]">
                <div class="ia-burbuja" v-html="render(m.content)"></div>
              </div>

              <div v-if="cargando" class="ia-msg assistant">
                <div class="ia-burbuja ia-pensando"><span></span><span></span><span></span></div>
              </div>
            </div>

            <div class="ia-input">
              <textarea ref="ta" v-model="texto" rows="1" placeholder="Escribí tu pregunta..."
                        @keydown.enter.exact.prevent="enviar()" @input="autoResize"></textarea>
              <button class="ia-send" :disabled="cargando || !texto.trim()" @click="enviar()">
                <span v-if="!cargando">➤</span><span v-else>…</span>
              </button>
            </div>
          </div>
        </transition>
      </div>
    </transition>
  </Teleport>
</template>

<script setup lang="ts">
import { ref, nextTick } from 'vue'
import api from '@/services/auth'

const props = withDefaults(defineProps<{
  endpoint: string
  titulo?: string
  subtitulo?: string
  sugerencias?: string[]
  contexto?: string
}>(), {
  titulo: 'Asistente IA',
  subtitulo: 'Preguntá sobre este módulo',
  sugerencias: () => [],
  contexto: '',
})

defineEmits<{ close: [] }>()

interface Msg { role: 'user' | 'assistant'; content: string }

const mensajes = ref<Msg[]>([])
const texto = ref('')
const cargando = ref(false)
const scroll = ref<HTMLElement | null>(null)
const ta = ref<HTMLTextAreaElement | null>(null)

const autoResize = () => {
  const el = ta.value; if (!el) return
  el.style.height = 'auto'; el.style.height = Math.min(el.scrollHeight, 120) + 'px'
}
const bajar = async () => { await nextTick(); if (scroll.value) scroll.value.scrollTop = scroll.value.scrollHeight }

const render = (t: string): string => {
  const esc = t.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
  return esc.replace(/\*\*(.+?)\*\*/g, '<b>$1</b>').replace(/`([^`]+)`/g, '<code>$1</code>').replace(/\n/g, '<br>')
}

const enviar = async (pregunta?: string) => {
  const q = (pregunta ?? texto.value).trim()
  if (!q || cargando.value) return
  texto.value = ''
  if (ta.value) ta.value.style.height = 'auto'
  mensajes.value.push({ role: 'user', content: q })
  cargando.value = true
  await bajar()
  try {
    const { data } = await api.post(props.endpoint, { messages: mensajes.value, contexto: props.contexto })
    mensajes.value.push({ role: 'assistant', content: data.respuesta || '(sin respuesta)' })
  } catch (e: any) {
    const msg = e?.response?.data?.error ?? 'No se pudo contactar al asistente. Revisá que esté configurada la API key.'
    mensajes.value.push({ role: 'assistant', content: '⚠️ ' + msg })
  } finally {
    cargando.value = false
    await bajar()
  }
}
</script>

<style scoped>
.ia-overlay { position:fixed; inset:0; background:rgba(15,23,42,.55); backdrop-filter:blur(3px);
  z-index:10000; display:flex; align-items:center; justify-content:center; }
.ia-modal { width:min(560px,95vw); height:min(640px,92vh); background:#fff; border-radius:16px;
  overflow:hidden; display:flex; flex-direction:column; box-shadow:0 24px 60px rgba(0,0,0,.45);
  font-family:var(--font-sans, sans-serif); }
.ia-fade-enter-active,.ia-fade-leave-active{ transition:opacity .25s ease; }
.ia-fade-enter-from,.ia-fade-leave-to{ opacity:0; }
.ia-pop-enter-active{ transition:transform .38s cubic-bezier(.2,.9,.3,1.2),opacity .3s ease; }
.ia-pop-enter-from{ transform:translateY(30px) scale(.94); opacity:0; }
.ia-header { display:flex; align-items:center; gap:12px; padding:14px 18px;
  background:linear-gradient(120deg,#1b4332,#2d6a4f 70%,#40916c); color:#fff; }
.ia-ico { font-size:26px; animation:lat 2.4s ease-in-out infinite; }
@keyframes lat{0%,100%{transform:scale(1)}50%{transform:scale(1.12)}}
.ia-htx h3 { margin:0; font-size:15px; }
.ia-htx p { margin:2px 0 0; font-size:11px; opacity:.85; }
.ia-close { margin-left:auto; background:rgba(255,255,255,.15); border:none; color:#fff;
  width:28px; height:28px; border-radius:8px; font-size:14px; cursor:pointer; }
.ia-close:hover { background:rgba(255,255,255,.3); }
.ia-msgs { flex:1; overflow-y:auto; padding:16px; display:flex; flex-direction:column; gap:12px; background:#f8fafc; }
.ia-bienvenida { text-align:center; color:#475569; margin:auto 0; }
.ia-bv-ico { font-size:40px; margin-bottom:8px; }
.ia-bienvenida p { font-size:14px; margin:0 0 14px; }
.ia-sugerencias { display:flex; flex-direction:column; gap:8px; }
.ia-sugerencias button { background:#fff; border:1px solid #c3e6cb; color:#1b4332; border-radius:10px;
  padding:9px 12px; font-size:13px; cursor:pointer; transition:all .15s; text-align:left; }
.ia-sugerencias button:hover { background:#f0faf4; transform:translateX(2px); }
.ia-msg { display:flex; }
.ia-msg.user { justify-content:flex-end; }
.ia-burbuja { max-width:80%; padding:9px 13px; border-radius:14px; font-size:13.5px; line-height:1.5;
  animation:burbIn .25s ease; word-wrap:break-word; }
.ia-msg.user .ia-burbuja { background:#1b4332; color:#fff; border-bottom-right-radius:4px; }
.ia-msg.assistant .ia-burbuja { background:#fff; color:#1e293b; border:1px solid #e5e7eb; border-bottom-left-radius:4px; }
.ia-burbuja :deep(code) { background:#eef2f7; padding:1px 5px; border-radius:4px; font-size:12px; font-family:var(--font-mono,monospace); }
.ia-msg.user .ia-burbuja :deep(code) { background:rgba(255,255,255,.2); }
@keyframes burbIn{ from{opacity:0; transform:translateY(6px)} }
.ia-pensando { display:flex; gap:4px; align-items:center; }
.ia-pensando span { width:7px; height:7px; border-radius:50%; background:#94a3b8; animation:rebote 1.2s ease-in-out infinite; }
.ia-pensando span:nth-child(2){ animation-delay:.18s; }
.ia-pensando span:nth-child(3){ animation-delay:.36s; }
@keyframes rebote{ 0%,60%,100%{ transform:translateY(0); opacity:.5 } 30%{ transform:translateY(-5px); opacity:1 } }
.ia-input { display:flex; gap:8px; align-items:flex-end; padding:12px 14px; border-top:1px solid #eef2f7; background:#fff; }
.ia-input textarea { flex:1; resize:none; border:1px solid #d1d5db; border-radius:12px; padding:9px 12px;
  font-size:13.5px; font-family:inherit; color:#1e293b; outline:none; line-height:1.4; max-height:120px; }
.ia-input textarea:focus { border-color:#40916c; box-shadow:0 0 0 3px rgba(64,145,108,.15); }
.ia-send { flex-shrink:0; width:40px; height:40px; border-radius:50%; border:none; background:#16a34a;
  color:#fff; font-size:16px; cursor:pointer; transition:background .15s; }
.ia-send:hover:not(:disabled){ background:#15803d; }
.ia-send:disabled { background:#cbd5e1; cursor:default; }
</style>
