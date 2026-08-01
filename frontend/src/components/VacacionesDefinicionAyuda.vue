<!-- VacacionesDefinicionAyuda.vue — Ayuda del módulo Vacaciones Definición de Antigüedad. -->
<template>
  <div class="vca-ov" @click.self="$emit('close')">
    <div class="vca-modal">
      <button class="vca-x" @click="$emit('close')">✕</button>
      <div class="vca-hero">
        <svg viewBox="0 0 120 80" class="vca-svg">
          <line x1="60" y1="20" x2="60" y2="56" class="vca-bar" />
          <line x1="34" y1="30" x2="86" y2="30" class="vca-bar" />
          <line x1="34" y1="30" x2="28" y2="46" class="vca-bar" /><line x1="34" y1="30" x2="40" y2="46" class="vca-bar" />
          <line x1="86" y1="30" x2="80" y2="46" class="vca-bar" /><line x1="86" y1="30" x2="92" y2="46" class="vca-bar" />
          <path d="M24 46 q10 8 20 0" class="vca-pan" /><path d="M76 46 q10 8 20 0" class="vca-pan" />
          <circle cx="60" cy="18" r="4" class="vca-knob" />
        </svg>
        <h2>Definición de Antigüedad</h2>
        <p>Días de vacaciones según los años de servicio</p>
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
  { t: 'Qué define', d: 'Cada fila es un tramo de antigüedad (desde X años y meses, hasta Y años y meses) con los días de vacaciones que corresponden a ese tramo.' },
  { t: 'Es la base del cálculo', d: 'Todos los módulos de vacaciones usan esta tabla para saber cuántos días le corresponden a cada empleado.' },
  { t: 'Agregar o actualizar', d: 'Cargá año/mes de inicio, año/mes de fin y los días, y presioná “Agregar”. Si el tramo ya existe, se actualizan sus días.' },
  { t: 'Eliminar', d: 'Tildá los tramos a borrar y usá “Eliminar”.' },
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
.vca-bar { stroke:#fff; stroke-width:2.4; stroke-linecap:round; } .vca-pan { fill:none; stroke:rgba(255,255,255,.9); stroke-width:2.4; } .vca-knob { fill:#fde68a; }
.vca-hero h2 { margin:10px 0 4px; font-size:18px; } .vca-hero p { margin:0; font-size:13px; opacity:.9; }
.vca-body { padding:18px 22px 24px; }
.vca-step { display:flex; gap:12px; padding:10px 0; border-bottom:1px solid #f1f5f9; animation:vca-slide .4s ease both; }
@keyframes vca-slide { from { transform:translateX(-10px); opacity:0 } to { transform:none; opacity:1 } }
.vca-num { flex-shrink:0; width:26px; height:26px; border-radius:50%; background:#1b4332; color:#fff; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:700; }
.vca-step b { font-size:14px; color:#1e293b; } .vca-step p { margin:3px 0 0; font-size:13px; color:#64748b; }
</style>
