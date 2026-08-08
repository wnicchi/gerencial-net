<!--
  ModuloAyudaIA.vue — Botones 🤖 IA + ❓ Ayuda para cualquier módulo (REGLA GENERAL de GESTIÓN.NET).
  Uso mínimo por módulo (una sola línea en la cabecera del view):

    <ModuloAyudaIA
      modulo="Cash Flow Bancario"
      icono="🏦"
      descripcion="Para un banco y saldo inicial arma el libro mayor proyectado (Debe/Haber/Saldo) …"
      :sugerencias="['¿Para qué sirve?', '¿Cómo se calcula el saldo?']"
      intro="Este módulo muestra el flujo de caja proyectado del banco elegido."
      :pasos="['<b>Elegí</b> el banco y el saldo inicial.', '<b>Calcular</b> arma el libro mayor.']"
      :notas="['Los ingresos suman al saldo; los egresos lo restan.']" />

  - IA: chat con el asistente (endpoint genérico /ia/modulo; la `descripcion` va como contexto).
  - Ayuda: modal con intro + pasos + notas (todo por props; acepta HTML en pasos/notas).
-->
<template>
  <span class="mai">
    <button class="mai-btn ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
    <button class="mai-btn ay" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>

    <!-- Modal de Ayuda (genérico) -->
    <Teleport to="body">
      <transition name="mai-fade" appear>
        <div v-if="modalAyuda" class="mai-ov" @click.self="modalAyuda = false">
          <transition name="mai-pop" appear>
            <div class="mai-md">
              <div class="mai-hero">
                <div class="mai-hero-ico">{{ icono }}</div>
                <div class="mai-hero-tx"><h3>Ayuda — {{ modulo }}</h3><p>{{ subtitulo || 'Cómo usar este módulo' }}</p></div>
                <button class="mai-x" @click="modalAyuda = false">✕</button>
              </div>
              <div class="mai-body">
                <p v-if="intro" class="mai-intro" v-html="intro"></p>
                <div v-if="pasos.length" class="mai-pasos">
                  <div class="mai-pasos-t">⚡ Cómo se usa</div>
                  <ol>
                    <li v-for="(p, i) in pasos" :key="i" :style="{ animationDelay: (i * 80 + 100) + 'ms' }">
                      <span class="mai-num">{{ i + 1 }}</span><span v-html="p"></span>
                    </li>
                  </ol>
                </div>
                <ul v-if="notas.length" class="mai-notas">
                  <li v-for="(n, i) in notas" :key="i" v-html="n"></li>
                </ul>
              </div>
            </div>
          </transition>
        </div>
      </transition>
    </Teleport>

    <!-- Chat IA -->
    <ChatIA v-if="modalIA" endpoint="/ia/modulo"
            :titulo="'Asistente IA — ' + modulo"
            :subtitulo="'Preguntá sobre ' + modulo"
            :contexto="'MÓDULO: ' + modulo + '\n\n' + descripcion"
            :sugerencias="sugerencias.length ? sugerencias : sugerenciasDefault"
            @close="modalIA = false" />
  </span>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import ChatIA from '@/components/ChatIA.vue'

const props = withDefaults(defineProps<{
  modulo: string
  descripcion: string
  icono?: string
  subtitulo?: string
  sugerencias?: string[]
  intro?: string
  pasos?: string[]
  notas?: string[]
}>(), {
  icono: '📄',
  subtitulo: '',
  sugerencias: () => [],
  intro: '',
  pasos: () => [],
  notas: () => [],
})

const modalIA = ref(false)
const modalAyuda = ref(false)
const sugerenciasDefault = computed(() => [
  `¿Para qué sirve el módulo ${props.modulo}?`,
  '¿Cómo se usa paso a paso?',
  '¿Qué datos necesito para empezar?',
])
</script>

<style scoped>
.mai { display: inline-flex; gap: 8px; }
.mai-btn { border: none; border-radius: 7px; padding: 7px 11px; font-weight: 700; font-size: 12px; cursor: pointer; white-space: nowrap; }
.mai-btn.ia { background: #16a34a; color: #fff; }
.mai-btn.ia:hover { background: #15803d; }
.mai-btn.ay { background: #e0f2fe; color: #075985; }
.mai-btn.ay:hover { background: #bae6fd; }

.mai-ov { position: fixed; inset: 0; background: rgba(15,23,42,.55); backdrop-filter: blur(3px); z-index: 10000; display: flex; align-items: center; justify-content: center; }
.mai-md { width: min(500px,94vw); max-height: 90vh; overflow: auto; background: #fff; border-radius: 16px; box-shadow: 0 24px 60px rgba(0,0,0,.45); }
.mai-fade-enter-active, .mai-fade-leave-active { transition: opacity .25s ease; } .mai-fade-enter-from, .mai-fade-leave-to { opacity: 0; }
.mai-pop-enter-active { transition: transform .38s cubic-bezier(.2,.9,.3,1.2), opacity .3s ease; } .mai-pop-enter-from { transform: translateY(30px) scale(.94); opacity: 0; }
.mai-hero { display: flex; align-items: center; gap: 12px; padding: 14px 18px; background: linear-gradient(120deg,#1b4332,#40916c); color: #fff; position: sticky; top: 0; }
.mai-hero-ico { font-size: 26px; animation: mailat 2.4s ease-in-out infinite; }
@keyframes mailat { 0%,100% { transform: scale(1) } 50% { transform: scale(1.12) } }
.mai-hero-tx h3 { margin: 0; font-size: 15px; } .mai-hero-tx p { margin: 2px 0 0; font-size: 11px; opacity: .85; }
.mai-x { margin-left: auto; background: rgba(255,255,255,.15); border: none; color: #fff; width: 28px; height: 28px; border-radius: 8px; font-size: 14px; cursor: pointer; }
.mai-x:hover { background: rgba(255,255,255,.3); }
.mai-body { padding: 18px 22px; }
.mai-intro { font-size: 14px; color: #374151; line-height: 1.55; margin: 0 0 14px; }
.mai-pasos { background: #f8fafc; border: 1px solid #eef2f7; border-radius: 12px; padding: 12px 16px; margin-bottom: 12px; }
.mai-pasos-t { font-size: 12px; font-weight: 700; color: #1b4332; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 10px; }
.mai-pasos ol { margin: 0; padding: 0; list-style: none; }
.mai-pasos li { display: flex; align-items: flex-start; gap: 10px; font-size: 13px; color: #374151; line-height: 1.5; margin-bottom: 8px; opacity: 0; animation: maipaso .4s ease forwards; }
.mai-num { flex-shrink: 0; width: 22px; height: 22px; border-radius: 50%; background: #16a34a; color: #fff; font-size: 12px; font-weight: 700; display: flex; align-items: center; justify-content: center; }
@keyframes maipaso { from { opacity: 0; transform: translateX(-8px) } to { opacity: 1; transform: none } }
.mai-notas { margin: 0; padding-left: 6px; list-style: none; }
.mai-notas li { font-size: 13px; color: #475569; line-height: 1.55; margin-bottom: 6px; padding-left: 24px; position: relative; }
.mai-notas li::before { content: '💡'; position: absolute; left: 0; }
</style>
