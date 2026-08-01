<!-- ControlSueldosAyuda.vue — Ayuda del módulo Planilla Control de Sueldos. -->
<template>
  <div class="csa-ov" @click.self="$emit('close')">
    <div class="csa-modal">
      <button class="csa-x" @click="$emit('close')">✕</button>
      <div class="csa-hero">
        <svg viewBox="0 0 120 80" class="csa-svg">
          <circle cx="60" cy="40" r="22" class="csa-coin" />
          <text x="60" y="48" text-anchor="middle" class="csa-sign">$</text>
          <path d="M30 60 h60" class="csa-l csa-l1" /><path d="M36 67 h48" class="csa-l csa-l2" />
        </svg>
        <h2>Control de Sueldos</h2>
        <p>Control de sueldos depositados por período</p>
      </div>
      <div class="csa-body">
        <div class="csa-step" v-for="(s, i) in pasos" :key="i" :style="{ animationDelay: (i * 90) + 'ms' }">
          <span class="csa-num">{{ i + 1 }}</span>
          <div><b>{{ s.t }}</b><p>{{ s.d }}</p></div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
defineEmits(['close'])
const pasos = [
  { t: 'Elegí mes y año', d: 'Y “Generar planilla”. Se arma el control de todos los empleados activos de la empresa.' },
  { t: 'Qué muestra', d: 'Por empleado: el neto de la ficha, y de las liquidaciones el sueldo, horas extras, anticipo, premio, vacaciones y SAC. El almuerzo del período se calcula descontando vacaciones y licencias.' },
  { t: 'Total Depositado y Control', d: 'El Total Depositado suma los conceptos liquidados. El Control (diferencia) compara contra el neto de la ficha. Premio, Ganancia y Otros se resaltan en amarillo cuando tienen valor.' },
  { t: 'Tomar planilla grabada', d: 'Recuperás una planilla guardada anteriormente para volver a verla.' },
  { t: 'Grabar / Exportar', d: 'Poné un nombre y “Grabar planilla” para guardarla. “Exportar a Excel” baja la planilla con sector y subsector.' },
]
</script>

<style scoped>
.csa-ov { position:fixed; inset:0; background:rgba(15,23,42,.55); display:flex; align-items:center; justify-content:center; z-index:1000; animation:csa-fade .2s ease; }
@keyframes csa-fade { from { opacity:0 } to { opacity:1 } }
.csa-modal { background:#fff; width:520px; max-width:94vw; max-height:90vh; overflow:auto; border-radius:14px; position:relative; box-shadow:0 20px 60px rgba(0,0,0,.3); animation:csa-pop .25s cubic-bezier(.2,.9,.3,1.2); }
@keyframes csa-pop { from { transform:translateY(20px) scale(.96); opacity:0 } to { transform:none; opacity:1 } }
.csa-x { position:absolute; top:12px; right:14px; background:rgba(255,255,255,.2); border:none; color:#fff; font-size:16px; cursor:pointer; width:28px; height:28px; border-radius:50%; }
.csa-hero { background:linear-gradient(135deg,#1b4332,#40916c); color:#fff; padding:26px 24px 22px; text-align:center; border-radius:14px 14px 0 0; }
.csa-svg { width:120px; height:80px; }
.csa-coin { fill:rgba(255,255,255,.16); stroke:rgba(255,255,255,.7); stroke-width:2; animation:csa-rise .6s ease both; }
@keyframes csa-rise { from { transform:translateY(8px); opacity:0 } to { transform:none; opacity:1 } }
.csa-sign { fill:#fff; font-size:22px; font-weight:bold; font-family:Arial; }
.csa-l { stroke:rgba(255,255,255,.6); stroke-width:2.4; stroke-linecap:round; stroke-dasharray:60; stroke-dashoffset:60; }
.csa-l1 { animation:csa-draw .6s .4s ease forwards; } .csa-l2 { animation:csa-draw .6s .6s ease forwards; }
@keyframes csa-draw { to { stroke-dashoffset:0 } }
.csa-hero h2 { margin:10px 0 4px; font-size:19px; } .csa-hero p { margin:0; font-size:13px; opacity:.9; }
.csa-body { padding:18px 22px 24px; }
.csa-step { display:flex; gap:12px; padding:10px 0; border-bottom:1px solid #f1f5f9; animation:csa-slide .4s ease both; }
@keyframes csa-slide { from { transform:translateX(-10px); opacity:0 } to { transform:none; opacity:1 } }
.csa-num { flex-shrink:0; width:26px; height:26px; border-radius:50%; background:#1b4332; color:#fff; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:700; }
.csa-step b { font-size:14px; color:#1e293b; } .csa-step p { margin:3px 0 0; font-size:13px; color:#64748b; }
</style>
