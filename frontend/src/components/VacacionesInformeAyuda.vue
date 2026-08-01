<!-- VacacionesInformeAyuda.vue — Ayuda del módulo Vacaciones Informe General. -->
<template>
  <div class="vca-ov" @click.self="$emit('close')">
    <div class="vca-modal">
      <button class="vca-x" @click="$emit('close')">✕</button>
      <div class="vca-hero">
        <svg viewBox="0 0 120 80" class="vca-svg">
          <rect x="40" y="16" width="40" height="50" rx="3" class="vca-doc" />
          <line x1="47" y1="28" x2="73" y2="28" class="vca-l" /><line x1="47" y1="36" x2="73" y2="36" class="vca-l" /><line x1="47" y1="44" x2="66" y2="44" class="vca-l" />
          <circle cx="82" cy="54" r="12" class="vca-lens" /><line x1="90" y1="62" x2="98" y2="70" class="vca-handle" />
        </svg>
        <h2>Informe General</h2>
        <p>Vacaciones por rango de fechas</p>
      </div>
      <div class="vca-body">
        <div class="vca-step" v-for="(s, i) in pasos" :key="i" :style="{ animationDelay: (i * 90) + 'ms' }">
          <span class="vca-num">{{ i + 1 }}</span>
          <div><b>{{ s.t }}</b><p>{{ s.d }}</p></div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
defineEmits(['close'])
const pasos = [
  { t: 'Elegí el rango', d: 'Indicá las fechas Desde y Hasta y presioná “Consultar”. Se listan las vacaciones cuyo inicio o fin cae dentro de ese rango.' },
  { t: 'Mirá el detalle', d: 'Por cada período: legajo, personal, días que corresponden por antigüedad, número, fechas, días y el día en que se presenta.' },
  { t: 'Seleccioná', d: 'Tildá las filas o usá “Todo” / “Nada”.' },
  { t: 'Imprimí o exportá', d: 'Generá el PDF de la consulta completa o exportá a Excel.' },
]
</script>

<style scoped>
.vca-ov { position:fixed; inset:0; background:rgba(15,23,42,.55); display:flex; align-items:center; justify-content:center; z-index:1000; animation:vca-fade .2s ease; }
@keyframes vca-fade { from { opacity:0 } to { opacity:1 } }
.vca-modal { background:#fff; width:520px; max-width:94vw; max-height:90vh; overflow:auto; border-radius:14px; position:relative; box-shadow:0 20px 60px rgba(0,0,0,.3); animation:vca-pop .25s cubic-bezier(.2,.9,.3,1.2); }
@keyframes vca-pop { from { transform:translateY(20px) scale(.96); opacity:0 } to { transform:none; opacity:1 } }
.vca-x { position:absolute; top:12px; right:14px; background:rgba(255,255,255,.2); border:none; color:#fff; font-size:16px; cursor:pointer; width:28px; height:28px; border-radius:50%; }
.vca-hero { background:linear-gradient(135deg,#0e7490,#22d3ee); color:#fff; padding:26px 24px 22px; text-align:center; border-radius:14px 14px 0 0; }
.vca-svg { width:120px; height:80px; }
.vca-doc { fill:rgba(255,255,255,.15); stroke:#fff; stroke-width:2.4; } .vca-l { stroke:rgba(255,255,255,.8); stroke-width:2; } .vca-lens { fill:rgba(255,255,255,.15); stroke:#fde68a; stroke-width:3; } .vca-handle { stroke:#fde68a; stroke-width:3; stroke-linecap:round; }
.vca-hero h2 { margin:10px 0 4px; font-size:18px; } .vca-hero p { margin:0; font-size:13px; opacity:.9; }
.vca-body { padding:18px 22px 24px; }
.vca-step { display:flex; gap:12px; padding:10px 0; border-bottom:1px solid #f1f5f9; animation:vca-slide .4s ease both; }
@keyframes vca-slide { from { transform:translateX(-10px); opacity:0 } to { transform:none; opacity:1 } }
.vca-num { flex-shrink:0; width:26px; height:26px; border-radius:50%; background:#0e7490; color:#fff; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:700; }
.vca-step b { font-size:14px; color:#1e293b; } .vca-step p { margin:3px 0 0; font-size:13px; color:#64748b; }
</style>
