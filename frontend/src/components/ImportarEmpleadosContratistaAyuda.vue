<!-- ImportarEmpleadosContratistaAyuda.vue — Ayuda del módulo Importar Empleados desde Excel. -->
<template>
  <div class="iea-ov" @click.self="$emit('close')">
    <div class="iea-modal">
      <button class="iea-x" @click="$emit('close')">✕</button>
      <div class="iea-hero">
        <svg viewBox="0 0 120 80" class="iea-svg">
          <rect x="30" y="16" width="44" height="52" rx="4" class="iea-sheet" />
          <line x1="38" y1="28" x2="66" y2="28" class="iea-row iea-r1" />
          <line x1="38" y1="38" x2="66" y2="38" class="iea-row iea-r2" />
          <line x1="38" y1="48" x2="66" y2="48" class="iea-row iea-r3" />
          <path d="M78 42 h18 M88 34 l8 8 l-8 8" class="iea-arrow" fill="none" />
        </svg>
        <h2>Importar Empleados desde Excel</h2>
        <p>Carga masiva de empleados de un contratista</p>
      </div>
      <div class="iea-body">
        <div class="iea-step" v-for="(s, i) in pasos" :key="i" :style="{ animationDelay: (i * 90) + 'ms' }">
          <span class="iea-num">{{ i + 1 }}</span>
          <div><b>{{ s.t }}</b><p>{{ s.d }}</p></div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
defineEmits(['close'])
const pasos = [
  { t: 'Elegí el contratista', d: 'Los empleados importados se cargan bajo este contratista.' },
  { t: 'Buscá el archivo Excel', d: 'Se muestran las filas con las columnas A, B, C… tal como vienen en la planilla.' },
  { t: 'Identificá las columnas', d: 'Indicá en qué columna está cada dato: Apellido y Nombre, DNI, CUIL (obligatorios), Teléfono y Celular.' },
  { t: 'Destildá lo que no quieras', d: 'Cada fila tiene un OK; destildá las filas que no quieras importar.' },
  { t: 'Procedé', d: 'Se dan de alta los empleados nuevos. Los que ya existen (mismo CUIL) se omiten, no se duplican ni se modifican.' },
]
</script>

<style scoped>
.iea-ov { position:fixed; inset:0; background:rgba(15,23,42,.55); display:flex; align-items:center; justify-content:center; z-index:1000; animation:iea-fade .2s ease; }
@keyframes iea-fade { from { opacity:0 } to { opacity:1 } }
.iea-modal { background:#fff; width:520px; max-width:94vw; max-height:90vh; overflow:auto; border-radius:14px; position:relative; box-shadow:0 20px 60px rgba(0,0,0,.3); animation:iea-pop .25s cubic-bezier(.2,.9,.3,1.2); }
@keyframes iea-pop { from { transform:translateY(20px) scale(.96); opacity:0 } to { transform:none; opacity:1 } }
.iea-x { position:absolute; top:12px; right:14px; background:rgba(255,255,255,.2); border:none; color:#fff; font-size:16px; cursor:pointer; width:28px; height:28px; border-radius:50%; }
.iea-hero { background:linear-gradient(135deg,#1b4332,#40916c); color:#fff; padding:26px 24px 22px; text-align:center; border-radius:14px 14px 0 0; }
.iea-svg { width:120px; height:80px; }
.iea-sheet { fill:rgba(255,255,255,.14); stroke:rgba(255,255,255,.6); stroke-width:1.5; animation:iea-rise .6s ease both; }
@keyframes iea-rise { from { transform:translateY(8px); opacity:0 } to { transform:none; opacity:1 } }
.iea-row { stroke:rgba(255,255,255,.6); stroke-width:2.4; stroke-linecap:round; stroke-dasharray:30; stroke-dashoffset:30; }
.iea-r1 { animation:iea-draw .5s .3s ease forwards; } .iea-r2 { animation:iea-draw .5s .45s ease forwards; } .iea-r3 { animation:iea-draw .5s .6s ease forwards; }
.iea-arrow { stroke:#9ef0c0; stroke-width:3; stroke-linecap:round; stroke-linejoin:round; stroke-dasharray:40; stroke-dashoffset:40; animation:iea-draw .6s .8s ease forwards; }
@keyframes iea-draw { to { stroke-dashoffset:0 } }
.iea-hero h2 { margin:10px 0 4px; font-size:18px; } .iea-hero p { margin:0; font-size:13px; opacity:.9; }
.iea-body { padding:18px 22px 24px; }
.iea-step { display:flex; gap:12px; padding:10px 0; border-bottom:1px solid #f1f5f9; animation:iea-slide .4s ease both; }
@keyframes iea-slide { from { transform:translateX(-10px); opacity:0 } to { transform:none; opacity:1 } }
.iea-num { flex-shrink:0; width:26px; height:26px; border-radius:50%; background:#1b4332; color:#fff; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:700; }
.iea-step b { font-size:14px; color:#1e293b; } .iea-step p { margin:3px 0 0; font-size:13px; color:#64748b; }
</style>
