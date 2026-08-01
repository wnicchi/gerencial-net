<!-- VacacionesPlanillaAyuda.vue — Ayuda del módulo Planilla General de Vacaciones. -->
<template>
  <div class="vca-ov" @click.self="$emit('close')">
    <div class="vca-modal">
      <button class="vca-x" @click="$emit('close')">✕</button>
      <div class="vca-hero">
        <svg viewBox="0 0 120 80" class="vca-svg">
          <rect x="30" y="16" width="60" height="50" rx="4" class="vca-sheet" />
          <line x1="38" y1="28" x2="82" y2="28" class="vca-row" />
          <line x1="38" y1="38" x2="82" y2="38" class="vca-row" />
          <line x1="38" y1="48" x2="82" y2="48" class="vca-row" />
          <rect x="38" y="44" width="44" height="8" class="vca-hl" />
        </svg>
        <h2>Planilla General</h2>
        <p>Estado de vacaciones por empresa y año</p>
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
  { t: 'Elegí empresa y año', d: 'Seleccioná la empresa y el año, y presioná “Aceptar”. Se lista todo el personal activo.' },
  { t: 'Leé los días', d: 'Por empleado: total de días que corresponden por antigüedad, liquidados, gozados, y los que faltan (sin liquidar / sin gozar).' },
  { t: 'Interpretá los colores', d: 'Rojo: no queda nada por liquidar ni gozar. Amarillo: hay días pendientes.' },
  { t: 'Imprimí o exportá', d: 'Seleccioná filas (Todo / Nada) y generá el PDF o el Excel de la planilla.' },
]
</script>

<style scoped>
.vca-ov { position:fixed; inset:0; background:rgba(15,23,42,.55); display:flex; align-items:center; justify-content:center; z-index:1000; animation:vca-fade .2s ease; }
@keyframes vca-fade { from { opacity:0 } to { opacity:1 } }
.vca-modal { background:#fff; width:520px; max-width:94vw; max-height:90vh; overflow:auto; border-radius:14px; position:relative; box-shadow:0 20px 60px rgba(0,0,0,.3); animation:vca-pop .25s cubic-bezier(.2,.9,.3,1.2); }
@keyframes vca-pop { from { transform:translateY(20px) scale(.96); opacity:0 } to { transform:none; opacity:1 } }
.vca-x { position:absolute; top:12px; right:14px; background:rgba(255,255,255,.2); border:none; color:#fff; font-size:16px; cursor:pointer; width:28px; height:28px; border-radius:50%; }
.vca-hero { background:linear-gradient(135deg,#1b4332,#40916c); color:#fff; padding:26px 24px 22px; text-align:center; border-radius:14px 14px 0 0; }
.vca-svg { width:120px; height:80px; }
.vca-sheet { fill:rgba(255,255,255,.15); stroke:#fff; stroke-width:2.4; } .vca-row { stroke:rgba(255,255,255,.8); stroke-width:2; } .vca-hl { fill:#fde68a; opacity:.85; }
.vca-hero h2 { margin:10px 0 4px; font-size:18px; } .vca-hero p { margin:0; font-size:13px; opacity:.9; }
.vca-body { padding:18px 22px 24px; }
.vca-step { display:flex; gap:12px; padding:10px 0; border-bottom:1px solid #f1f5f9; animation:vca-slide .4s ease both; }
@keyframes vca-slide { from { transform:translateX(-10px); opacity:0 } to { transform:none; opacity:1 } }
.vca-num { flex-shrink:0; width:26px; height:26px; border-radius:50%; background:#1b4332; color:#fff; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:700; }
.vca-step b { font-size:14px; color:#1e293b; } .vca-step p { margin:3px 0 0; font-size:13px; color:#64748b; }
</style>
