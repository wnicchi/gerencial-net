<!-- ObrasSocialesImportarAyuda.vue — Ayuda del módulo Importar Excel Pagos Aportes. -->
<template>
  <div class="osi-ov" @click.self="$emit('close')">
    <div class="osi-modal">
      <button class="osi-x" @click="$emit('close')">✕</button>
      <div class="osi-hero">
        <svg viewBox="0 0 120 80" class="osi-svg">
          <rect x="30" y="16" width="44" height="52" rx="4" class="osi-sheet" />
          <line x1="38" y1="28" x2="66" y2="28" class="osi-row osi-r1" />
          <line x1="38" y1="38" x2="66" y2="38" class="osi-row osi-r2" />
          <line x1="38" y1="48" x2="66" y2="48" class="osi-row osi-r3" />
          <path d="M78 42 h18 M88 34 l8 8 l-8 8" class="osi-arrow" fill="none" />
        </svg>
        <h2>Importar Excel Pagos Aportes</h2>
        <p>Carga masiva de aportes y contribuciones</p>
      </div>
      <div class="osi-body">
        <div class="osi-step" v-for="(s, i) in pasos" :key="i" :style="{ animationDelay: (i * 90) + 'ms' }">
          <span class="osi-num">{{ i + 1 }}</span>
          <div><b>{{ s.t }}</b><p>{{ s.d }}</p></div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
defineEmits(['close'])
const pasos = [
  { t: 'Elegí el período', d: 'Seleccioná el mes y el año al que corresponden los pagos.' },
  { t: 'Buscá el archivo Excel', d: 'Se muestran las filas con sus columnas A, B, C… tal como vienen en la planilla.' },
  { t: 'Identificá las columnas', d: 'Indicá en qué columna está el CUIL (obligatorio), el Total de Contribución y el Total de Aporte.' },
  { t: 'Destildá lo que no quieras', d: 'Cada fila tiene un OK; destildá las que no quieras importar.' },
  { t: 'Procedé', d: 'Por cada fila se busca el empleado por CUIL. Si no existe, esa fila se descarta. Si ya había datos del período, se actualizan; si no, se dan de alta. El total = contribución + aporte.' },
]
</script>

<style scoped>
.osi-ov { position:fixed; inset:0; background:rgba(15,23,42,.55); display:flex; align-items:center; justify-content:center; z-index:1000; animation:osi-fade .2s ease; }
@keyframes osi-fade { from { opacity:0 } to { opacity:1 } }
.osi-modal { background:#fff; width:520px; max-width:94vw; max-height:90vh; overflow:auto; border-radius:14px; position:relative; box-shadow:0 20px 60px rgba(0,0,0,.3); animation:osi-pop .25s cubic-bezier(.2,.9,.3,1.2); }
@keyframes osi-pop { from { transform:translateY(20px) scale(.96); opacity:0 } to { transform:none; opacity:1 } }
.osi-x { position:absolute; top:12px; right:14px; background:rgba(255,255,255,.2); border:none; color:#fff; font-size:16px; cursor:pointer; width:28px; height:28px; border-radius:50%; }
.osi-hero { background:linear-gradient(135deg,#1b4332,#40916c); color:#fff; padding:26px 24px 22px; text-align:center; border-radius:14px 14px 0 0; }
.osi-svg { width:120px; height:80px; }
.osi-sheet { fill:rgba(255,255,255,.14); stroke:rgba(255,255,255,.6); stroke-width:1.5; animation:osi-rise .6s ease both; }
@keyframes osi-rise { from { transform:translateY(8px); opacity:0 } to { transform:none; opacity:1 } }
.osi-row { stroke:rgba(255,255,255,.6); stroke-width:2.4; stroke-linecap:round; stroke-dasharray:30; stroke-dashoffset:30; }
.osi-r1 { animation:osi-draw .5s .3s ease forwards; } .osi-r2 { animation:osi-draw .5s .45s ease forwards; } .osi-r3 { animation:osi-draw .5s .6s ease forwards; }
.osi-arrow { stroke:#9ef0c0; stroke-width:3; stroke-linecap:round; stroke-linejoin:round; stroke-dasharray:40; stroke-dashoffset:40; animation:osi-draw .6s .8s ease forwards; }
@keyframes osi-draw { to { stroke-dashoffset:0 } }
.osi-hero h2 { margin:10px 0 4px; font-size:17px; } .osi-hero p { margin:0; font-size:13px; opacity:.9; }
.osi-body { padding:18px 22px 24px; }
.osi-step { display:flex; gap:12px; padding:10px 0; border-bottom:1px solid #f1f5f9; animation:osi-slide .4s ease both; }
@keyframes osi-slide { from { transform:translateX(-10px); opacity:0 } to { transform:none; opacity:1 } }
.osi-num { flex-shrink:0; width:26px; height:26px; border-radius:50%; background:#1b4332; color:#fff; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:700; }
.osi-step b { font-size:14px; color:#1e293b; } .osi-step p { margin:3px 0 0; font-size:13px; color:#64748b; }
</style>
