<!-- ParteDiarioAyuda.vue — Ayuda del módulo Parte Diario de Horas. -->
<template>
  <div class="vca-ov" @click.self="$emit('close')">
    <div class="vca-modal">
      <button class="vca-x" @click="$emit('close')">✕</button>
      <div class="vca-hero">
        <svg viewBox="0 0 120 80" class="vca-svg"><rect x="38" y="16" width="44" height="50" rx="4" class="vca-c" /><line x1="46" y1="30" x2="74" y2="30" class="vca-l" /><line x1="46" y1="40" x2="74" y2="40" class="vca-l" /><path d="M48 50 l5 5 l10 -12" class="vca-ck" /></svg>
        <h2>Parte Diario</h2><p>Presentismo del día</p>
      </div>
      <div class="vca-body"><div class="vca-step" v-for="(s, i) in pasos" :key="i" :style="{ animationDelay: (i * 90) + 'ms' }"><span class="vca-num">{{ i + 1 }}</span><div><b>{{ s.t }}</b><p>{{ s.d }}</p></div></div></div>
    </div>
  </div>
</template>
<script setup lang="ts">
defineEmits(['close'])
const pasos = [
  { t: 'Filtrá y traé empleados', d: 'Elegí empresa y traé la lista. Seleccioná a quiénes incluir.' },
  { t: 'Elegí la fecha', d: 'El parte se arma para esa fecha puntual.' },
  { t: 'Generá', d: 'Muestra quiénes registraron entrada (con su hora) y agrega una observación si están de vacaciones o licencia.' },
  { t: 'Solo no registrados', d: 'Opción para ver únicamente los que no marcaron ni tienen observación. Se puede imprimir.' },
]
</script>
<style scoped>
.vca-ov { position:fixed; inset:0; background:rgba(15,23,42,.55); display:flex; align-items:center; justify-content:center; z-index:1000; animation:vca-fade .2s ease; }
@keyframes vca-fade { from { opacity:0 } to { opacity:1 } }
.vca-modal { background:#fff; width:520px; max-width:94vw; max-height:90vh; overflow:auto; border-radius:14px; position:relative; box-shadow:0 20px 60px rgba(0,0,0,.3); animation:vca-pop .25s cubic-bezier(.2,.9,.3,1.2); }
@keyframes vca-pop { from { transform:translateY(20px) scale(.96); opacity:0 } to { transform:none; opacity:1 } }
.vca-x { position:absolute; top:12px; right:14px; background:rgba(255,255,255,.2); border:none; color:#fff; font-size:16px; cursor:pointer; width:28px; height:28px; border-radius:50%; }
.vca-hero { background:linear-gradient(135deg,#1b4332,#40916c); color:#fff; padding:26px 24px 22px; text-align:center; border-radius:14px 14px 0 0; }
.vca-svg { width:120px; height:80px; } .vca-c { fill:rgba(255,255,255,.12); stroke:#fff; stroke-width:2.4; } .vca-l { stroke:rgba(255,255,255,.8); stroke-width:2; } .vca-ck { fill:none; stroke:#fde68a; stroke-width:3; stroke-linecap:round; stroke-linejoin:round; }
.vca-hero h2 { margin:10px 0 4px; font-size:18px; } .vca-hero p { margin:0; font-size:13px; opacity:.9; }
.vca-body { padding:18px 22px 24px; }
.vca-step { display:flex; gap:12px; padding:10px 0; border-bottom:1px solid #f1f5f9; animation:vca-slide .4s ease both; }
@keyframes vca-slide { from { transform:translateX(-10px); opacity:0 } to { transform:none; opacity:1 } }
.vca-num { flex-shrink:0; width:26px; height:26px; border-radius:50%; background:#1b4332; color:#fff; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:700; }
.vca-step b { font-size:14px; color:#1e293b; } .vca-step p { margin:3px 0 0; font-size:13px; color:#64748b; }
</style>
