<!-- VacacionesAgregarAyuda.vue — Ayuda del módulo Vacaciones - Agregar. -->
<template>
  <div class="vca-ov" @click.self="$emit('close')">
    <div class="vca-modal">
      <button class="vca-x" @click="$emit('close')">✕</button>
      <div class="vca-hero">
        <svg viewBox="0 0 120 80" class="vca-svg">
          <circle cx="92" cy="22" r="11" class="vca-sun" />
          <path d="M14 60 q14 -10 28 0 t28 0 t28 0" class="vca-wave" />
          <path d="M14 68 q14 -10 28 0 t28 0 t28 0" class="vca-wave2" />
          <line x1="46" y1="58" x2="46" y2="30" class="vca-mast" />
          <path d="M46 30 l20 8 l-20 8 Z" class="vca-sail" />
        </svg>
        <h2>Vacaciones - Agregar</h2>
        <p>Alta de un período de vacaciones</p>
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
  { t: 'Buscá al empleado', d: 'Por código o nombre. El sistema trae su fecha de ingreso y calcula los días que le corresponden por antigüedad (LCT).' },
  { t: 'Revisá los días', d: 'Corresponden (por antigüedad), Tomados y Liquidadas del año. El recuadro rojo muestra las vacaciones ya cargadas y los meses, para no superponer.' },
  { t: 'Completá el período', d: 'Año, fecha de pago, Desde/Hasta, cantidad de días, fecha en que se reincorpora (Se presenta), si es liquidada o gozada, y observaciones.' },
  { t: 'Confirmá', d: 'Se valida que no se excedan los días que corresponden y se avisa si hay otros empleados de vacaciones en ese período. Si hay observación, queda en el historial del legajo.' },
  { t: 'Generá la notificación', d: 'Se arma el PDF (art. 154 LCT) en formato “Totales” (notificación) o “Separadas” (solicitud del trabajador + notificación de la empresa). Podés imprimir o descargar.' },
]
</script>

<style scoped>
.vca-ov { position:fixed; inset:0; background:rgba(15,23,42,.55); display:flex; align-items:center; justify-content:center; z-index:1000; animation:vca-fade .2s ease; }
@keyframes vca-fade { from { opacity:0 } to { opacity:1 } }
.vca-modal { background:#fff; width:540px; max-width:94vw; max-height:90vh; overflow:auto; border-radius:14px; position:relative; box-shadow:0 20px 60px rgba(0,0,0,.3); animation:vca-pop .25s cubic-bezier(.2,.9,.3,1.2); }
@keyframes vca-pop { from { transform:translateY(20px) scale(.96); opacity:0 } to { transform:none; opacity:1 } }
.vca-x { position:absolute; top:12px; right:14px; background:rgba(255,255,255,.2); border:none; color:#fff; font-size:16px; cursor:pointer; width:28px; height:28px; border-radius:50%; }
.vca-hero { background:linear-gradient(135deg,#0e7490,#22d3ee); color:#fff; padding:26px 24px 22px; text-align:center; border-radius:14px 14px 0 0; }
.vca-svg { width:130px; height:80px; }
.vca-sun { fill:#fde68a; }
.vca-wave, .vca-wave2 { fill:none; stroke:rgba(255,255,255,.85); stroke-width:2.4; stroke-linecap:round; }
.vca-wave2 { opacity:.55; animation:vca-w 2.4s ease-in-out infinite; }
@keyframes vca-w { 0%,100% { transform:translateX(0) } 50% { transform:translateX(4px) } }
.vca-mast { stroke:#fff; stroke-width:2; } .vca-sail { fill:rgba(255,255,255,.9); }
.vca-hero h2 { margin:10px 0 4px; font-size:18px; } .vca-hero p { margin:0; font-size:13px; opacity:.9; }
.vca-body { padding:18px 22px 24px; }
.vca-step { display:flex; gap:12px; padding:10px 0; border-bottom:1px solid #f1f5f9; animation:vca-slide .4s ease both; }
@keyframes vca-slide { from { transform:translateX(-10px); opacity:0 } to { transform:none; opacity:1 } }
.vca-num { flex-shrink:0; width:26px; height:26px; border-radius:50%; background:#0e7490; color:#fff; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:700; }
.vca-step b { font-size:14px; color:#1e293b; } .vca-step p { margin:3px 0 0; font-size:13px; color:#64748b; }
</style>
