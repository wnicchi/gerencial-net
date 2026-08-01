<!-- FeriadosAyuda.vue — Ayuda del módulo Definición de Feriados. -->
<template>
  <div class="vca-ov" @click.self="$emit('close')">
    <div class="vca-modal">
      <button class="vca-x" @click="$emit('close')">✕</button>
      <div class="vca-hero">
        <svg viewBox="0 0 120 80" class="vca-svg"><rect x="34" y="18" width="52" height="48" rx="4" class="vca-c" /><line x1="34" y1="30" x2="86" y2="30" class="vca-l" /><line x1="46" y1="14" x2="46" y2="24" class="vca-r" /><line x1="74" y1="14" x2="74" y2="24" class="vca-r" /><circle cx="68" cy="48" r="6" class="vca-dot" /></svg>
        <h2>Definición de Feriados</h2><p>Cargar los feriados del calendario</p>
      </div>
      <div class="vca-body"><div class="vca-step" v-for="(s, i) in pasos" :key="i" :style="{ animationDelay: (i * 90) + 'ms' }"><span class="vca-num">{{ i + 1 }}</span><div><b>{{ s.t }}</b><p>{{ s.d }}</p></div></div></div>
    </div>
  </div>
</template>
<script setup lang="ts">
defineEmits(['close'])
const pasos = [
  { t: 'Elegí mes y año', d: 'Se listan los feriados de ese mes/año.' },
  { t: 'Agregar / modificar', d: 'Indicá el día y la observación con la razón del feriado, y tocá “Aceptar”. Si ya existe el día, se actualiza.' },
  { t: 'Eliminar', d: 'Hacé doble clic sobre un feriado de la lista para borrarlo (con confirmación).' },
  { t: 'Imprimir', d: 'Indicá un rango de fechas y generá el PDF con la lista de feriados. Los feriados se pagan al 100% en el cálculo de horas.' },
]
</script>
<style scoped>
.vca-ov { position:fixed; inset:0; background:rgba(15,23,42,.55); display:flex; align-items:center; justify-content:center; z-index:1000; animation:vca-fade .2s ease; }
@keyframes vca-fade { from { opacity:0 } to { opacity:1 } }
.vca-modal { background:#fff; width:520px; max-width:94vw; max-height:90vh; overflow:auto; border-radius:14px; position:relative; box-shadow:0 20px 60px rgba(0,0,0,.3); animation:vca-pop .25s cubic-bezier(.2,.9,.3,1.2); }
@keyframes vca-pop { from { transform:translateY(20px) scale(.96); opacity:0 } to { transform:none; opacity:1 } }
.vca-x { position:absolute; top:12px; right:14px; background:rgba(255,255,255,.2); border:none; color:#fff; font-size:16px; cursor:pointer; width:28px; height:28px; border-radius:50%; }
.vca-hero { background:linear-gradient(135deg,#0e7490,#22d3ee); color:#fff; padding:26px 24px 22px; text-align:center; border-radius:14px 14px 0 0; }
.vca-svg { width:120px; height:80px; } .vca-c { fill:rgba(255,255,255,.12); stroke:#fff; stroke-width:2.4; } .vca-l { stroke:#fff; stroke-width:2.4; } .vca-r { stroke:#fff; stroke-width:3; stroke-linecap:round; } .vca-dot { fill:#fde68a; }
.vca-hero h2 { margin:10px 0 4px; font-size:18px; } .vca-hero p { margin:0; font-size:13px; opacity:.9; }
.vca-body { padding:18px 22px 24px; }
.vca-step { display:flex; gap:12px; padding:10px 0; border-bottom:1px solid #f1f5f9; animation:vca-slide .4s ease both; }
@keyframes vca-slide { from { transform:translateX(-10px); opacity:0 } to { transform:none; opacity:1 } }
.vca-num { flex-shrink:0; width:26px; height:26px; border-radius:50%; background:#0e7490; color:#fff; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:700; }
.vca-step b { font-size:14px; color:#1e293b; } .vca-step p { margin:3px 0 0; font-size:13px; color:#64748b; }
</style>
