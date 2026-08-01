<!-- SueldosTiposView.vue — Liquidaciones / Tipos de Sueldos (sueldos_tipos). -->
<template>
  <div class="st-view">
    <div class="st-cab">
      <div class="st-cab-ico">🏷️</div>
      <div class="st-cab-tx"><h1>Tipos de Sueldos</h1><p>Tipos de sueldo que clasifican las liquidaciones</p></div>
      <button class="st-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="st-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
    </div>

    <SueldosTiposAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/sueldos-tipos" titulo="Asistente IA — Tipos de Sueldos"
            subtitulo="Preguntá sobre los tipos de sueldo" :sugerencias="['¿Qué es un tipo de sueldo?','¿Cómo agrego uno?','¿Para qué se usan?']"
            @close="modalIA = false" />

    <AbmSimple endpoint="/sueldos-tipos" coleccion="tipos" titulo="Tipos" nuevo-label="Nuevo tipo" :max-descripcion="50" />
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import SueldosTiposAyuda from '@/components/SueldosTiposAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'
import AbmSimple from '@/components/AbmSimple.vue'
const modalAyuda = ref(false); const modalIA = ref(false)
</script>

<style scoped>
.st-view { display:flex; flex-direction:column; height:100%; overflow:auto; }
.st-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.st-cab-ico { font-size:28px; } .st-cab-tx h1 { margin:0; font-size:19px; color:#1e293b; } .st-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.st-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.st-btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
</style>
