<!-- ContratistasFaltasAyuda.vue — Ayuda del módulo Listado Exigencias y Faltantes. -->
<template>
  <div class="cfa-ov" @click.self="$emit('close')">
    <div class="cfa-modal">
      <button class="cfa-x" @click="$emit('close')">✕</button>
      <div class="cfa-hero">
        <svg viewBox="0 0 120 80" class="cfa-svg">
          <rect x="22" y="50" width="14" height="18" rx="2" class="cfa-bar cfa-b1" />
          <rect x="42" y="38" width="14" height="30" rx="2" class="cfa-bar cfa-b2" />
          <rect x="62" y="44" width="14" height="24" rx="2" class="cfa-bar cfa-b3" />
          <circle cx="92" cy="30" r="11" class="cfa-warn" />
          <path d="M92 25 v7" class="cfa-wl" /><circle cx="92" cy="36" r="1.4" class="cfa-wd" />
        </svg>
        <h2>Listado Exigencias y Faltantes</h2>
        <p>Control de obligaciones pendientes por contratista</p>
      </div>
      <div class="cfa-body">
        <div class="cfa-step" v-for="(s, i) in pasos" :key="i" :style="{ animationDelay: (i * 90) + 'ms' }">
          <span class="cfa-num">{{ i + 1 }}</span>
          <div><b>{{ s.t }}</b><p>{{ s.d }}</p></div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
defineEmits(['close'])
const pasos = [
  { t: 'Elegí el alcance', d: '“Todos” recorre todos los contratistas activos; “Uno” se limita a uno.' },
  { t: 'Elegí las obligaciones', d: '“Faltantes” muestra solo las exigencias vencidas o sin presentar; “Todas” muestra todas las exigencias asignadas.' },
  { t: 'Consultá', d: 'Aparecen agrupados por contratista: las exigencias y los empleados con acceso permitido pero ART vencida. Solo se listan los contratistas que tienen algo pendiente.' },
  { t: 'Visualizá / Imprimí', d: 'Generá el PDF para verlo, descargarlo o imprimirlo.' },
]
</script>

<style scoped>
.cfa-ov { position:fixed; inset:0; background:rgba(15,23,42,.55); display:flex; align-items:center; justify-content:center; z-index:1000; animation:cfa-fade .2s ease; }
@keyframes cfa-fade { from { opacity:0 } to { opacity:1 } }
.cfa-modal { background:#fff; width:520px; max-width:94vw; max-height:90vh; overflow:auto; border-radius:14px; position:relative; box-shadow:0 20px 60px rgba(0,0,0,.3); animation:cfa-pop .25s cubic-bezier(.2,.9,.3,1.2); }
@keyframes cfa-pop { from { transform:translateY(20px) scale(.96); opacity:0 } to { transform:none; opacity:1 } }
.cfa-x { position:absolute; top:12px; right:14px; background:rgba(255,255,255,.2); border:none; color:#fff; font-size:16px; cursor:pointer; width:28px; height:28px; border-radius:50%; }
.cfa-hero { background:linear-gradient(135deg,#1b4332,#40916c); color:#fff; padding:26px 24px 22px; text-align:center; border-radius:14px 14px 0 0; }
.cfa-svg { width:120px; height:80px; }
.cfa-bar { fill:rgba(255,255,255,.8); transform-origin:bottom; }
.cfa-b1 { animation:cfa-grow .5s .2s ease both; } .cfa-b2 { animation:cfa-grow .5s .35s ease both; } .cfa-b3 { animation:cfa-grow .5s .5s ease both; }
@keyframes cfa-grow { from { transform:scaleY(0) } to { transform:scaleY(1) } }
.cfa-warn { fill:#f59e0b; opacity:0; animation:cfa-seal .4s .7s ease forwards; } @keyframes cfa-seal { to { opacity:1 } }
.cfa-wl { stroke:#fff; stroke-width:2.4; stroke-linecap:round; } .cfa-wd { fill:#fff; }
.cfa-hero h2 { margin:10px 0 4px; font-size:18px; } .cfa-hero p { margin:0; font-size:13px; opacity:.9; }
.cfa-body { padding:18px 22px 24px; }
.cfa-step { display:flex; gap:12px; padding:10px 0; border-bottom:1px solid #f1f5f9; animation:cfa-slide .4s ease both; }
@keyframes cfa-slide { from { transform:translateX(-10px); opacity:0 } to { transform:none; opacity:1 } }
.cfa-num { flex-shrink:0; width:26px; height:26px; border-radius:50%; background:#1b4332; color:#fff; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:700; }
.cfa-step b { font-size:14px; color:#1e293b; } .cfa-step p { margin:3px 0 0; font-size:13px; color:#64748b; }
</style>
