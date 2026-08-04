<!-- AlertasView.vue — Vista de Alertas (menú). Muestra el panel de alertas. -->
<template>
  <div class="av-view">
    <div class="av-cab">
      <div class="av-cab-ico">🔔</div>
      <div class="av-cab-tx"><h1>Alertas</h1><p>Avisos del análisis de los datos</p></div>
      <button class="av-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="av-btn-recargar" @click="recargar">↻ Actualizar</button>
    </div>

    <ChatIA v-if="modalIA" endpoint="/ia/alertas" titulo="Asistente IA — Alertas"
            subtitulo="Preguntá sobre los avisos"
            :sugerencias="['¿Qué alertas hay hoy?','¿Qué es el plazo de prueba?','¿Cómo se calculan los cumpleaños?','¿Qué hago con un permiso pendiente?']"
            @close="modalIA = false" />

    <div class="av-card">
      <AlertasPanel :key="recarga" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import AlertasPanel from '@/components/AlertasPanel.vue'
import ChatIA from '@/components/ChatIA.vue'
import { useAutoRefresh } from '@/composables/useAutoRefresh'

const modalIA = ref(false)
const recarga = ref(0)
const recargar = () => { recarga.value++ }
useAutoRefresh(recargar)   // tablero en tiempo real: recarga cada 5 min
</script>

<style scoped>
.av-view { display:flex; flex-direction:column; height:100%; overflow:hidden; }
.av-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; flex-shrink:0; }
.av-cab-ico { font-size:28px; } .av-cab-tx h1 { margin:0; font-size:20px; color:#1e293b; } .av-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.av-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.av-btn-ia:hover { filter:brightness(1.1); }
.av-btn-recargar { background:#fff; color:#475569; border:1px solid #d1d5db; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; }
.av-btn-recargar:hover { background:#f8fafc; }
.av-card { flex:1; min-height:0; margin:18px; background:#fff; border:1px solid #e2e8f0; border-radius:10px; overflow:hidden; box-shadow:0 4px 14px rgba(0,0,0,.06); }
</style>
