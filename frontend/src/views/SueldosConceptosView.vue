<!-- SueldosConceptosView.vue — Liquidaciones / Conceptos de Sueldos (sueldos_conceptos). -->
<template>
  <div class="sc-view">
    <div class="sc-cab">
      <div class="sc-cab-ico">📋</div>
      <div class="sc-cab-tx"><h1>Conceptos de Sueldos</h1><p>Conceptos de liquidación que aparecen en los recibos</p></div>
      <button class="sc-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="sc-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
    </div>

    <SueldosConceptosAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/sueldos-conceptos" titulo="Asistente IA — Conceptos de Sueldos"
            subtitulo="Preguntá sobre los conceptos de liquidación" :sugerencias="['¿Qué es un concepto de sueldo?','¿Cómo agrego uno?','¿Dónde se usan?']"
            @close="modalIA = false" />

    <AbmSimple endpoint="/sueldos-conceptos" coleccion="conceptos" titulo="Conceptos" nuevo-label="Nuevo concepto" :max-descripcion="100" />
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import SueldosConceptosAyuda from '@/components/SueldosConceptosAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'
import AbmSimple from '@/components/AbmSimple.vue'
const modalAyuda = ref(false); const modalIA = ref(false)
</script>

<style scoped>
.sc-view { display:flex; flex-direction:column; height:100%; overflow:auto; }
.sc-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.sc-cab-ico { font-size:28px; } .sc-cab-tx h1 { margin:0; font-size:19px; color:#1e293b; } .sc-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.sc-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.sc-btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
</style>
