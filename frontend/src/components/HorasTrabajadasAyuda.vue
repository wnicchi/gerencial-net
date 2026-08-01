<!-- HorasTrabajadasAyuda.vue — Ayuda del módulo Consulta de Horas Trabajadas. -->
<template>
  <div class="vca-ov" @click.self="$emit('close')">
    <div class="vca-modal">
      <button class="vca-x" @click="$emit('close')">✕</button>
      <div class="vca-hero">
        <svg viewBox="0 0 120 80" class="vca-svg">
          <circle cx="60" cy="40" r="22" class="vca-clock" />
          <line x1="60" y1="40" x2="60" y2="25" class="vca-min" />
          <line x1="60" y1="40" x2="71" y2="46" class="vca-hr" />
          <circle cx="60" cy="40" r="3" class="vca-center" />
        </svg>
        <h2>Horas Trabajadas</h2>
        <p>Cálculo de horas a partir del reloj y los turnos</p>
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
  { t: 'Filtrá y traé empleados', d: 'Elegí empresa y orden, y usá “Traer Empleados”. Se listan para que selecciones a quiénes calcular (Todos / Nada o uno por uno).' },
  { t: 'Elegí el rango', d: 'Indicá Desde y al. El cálculo toma las marcaciones del reloj, los ajustes manuales, las faltas/licencias y las vacaciones de ese período.' },
  { t: 'Generá el cálculo', d: '“Estándar”: el día se toma por la fecha de la marcación. “Logística”: la jornada va de las 4 de la mañana a las 4 del día siguiente, para turnos que cruzan la medianoche.' },
  { t: 'Leé los resultados', d: 'Por día: entrada/salida real y calculada, horas normales, extras al 50% y al 100% (feriados y sábado tarde), adicional y extra nocturno. O tildá “Resumen” para ver totales por empleado.' },
  { t: 'Imprimí o exportá', d: 'Generá el PDF (vista previa con descargar/imprimir) o exportá a Excel.' },
]
</script>

<style scoped>
.vca-ov { position:fixed; inset:0; background:rgba(15,23,42,.55); display:flex; align-items:center; justify-content:center; z-index:1000; animation:vca-fade .2s ease; }
@keyframes vca-fade { from { opacity:0 } to { opacity:1 } }
.vca-modal { background:#fff; width:540px; max-width:94vw; max-height:90vh; overflow:auto; border-radius:14px; position:relative; box-shadow:0 20px 60px rgba(0,0,0,.3); animation:vca-pop .25s cubic-bezier(.2,.9,.3,1.2); }
@keyframes vca-pop { from { transform:translateY(20px) scale(.96); opacity:0 } to { transform:none; opacity:1 } }
.vca-x { position:absolute; top:12px; right:14px; background:rgba(255,255,255,.2); border:none; color:#fff; font-size:16px; cursor:pointer; width:28px; height:28px; border-radius:50%; }
.vca-hero { background:linear-gradient(135deg,#1b4332,#40916c); color:#fff; padding:26px 24px 22px; text-align:center; border-radius:14px 14px 0 0; }
.vca-svg { width:120px; height:80px; }
.vca-clock { fill:rgba(255,255,255,.12); stroke:#fff; stroke-width:2.6; } .vca-min { stroke:#fff; stroke-width:2.6; stroke-linecap:round; transform-origin:60px 40px; animation:vca-tick 5s steps(10) infinite; } .vca-hr { stroke:rgba(255,255,255,.85); stroke-width:2.4; stroke-linecap:round; } .vca-center { fill:#fde68a; }
@keyframes vca-tick { to { transform:rotate(360deg) } }
.vca-hero h2 { margin:10px 0 4px; font-size:18px; } .vca-hero p { margin:0; font-size:13px; opacity:.9; }
.vca-body { padding:18px 22px 24px; }
.vca-step { display:flex; gap:12px; padding:10px 0; border-bottom:1px solid #f1f5f9; animation:vca-slide .4s ease both; }
@keyframes vca-slide { from { transform:translateX(-10px); opacity:0 } to { transform:none; opacity:1 } }
.vca-num { flex-shrink:0; width:26px; height:26px; border-radius:50%; background:#1b4332; color:#fff; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:700; }
.vca-step b { font-size:14px; color:#1e293b; } .vca-step p { margin:3px 0 0; font-size:13px; color:#64748b; }
</style>
