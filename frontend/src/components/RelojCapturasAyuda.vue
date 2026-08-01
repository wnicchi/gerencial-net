<!-- RelojCapturasAyuda.vue — Ayuda del módulo Reloj - Registro de Personal. -->
<template>
  <div class="vca-ov" @click.self="$emit('close')">
    <div class="vca-modal">
      <button class="vca-x" @click="$emit('close')">✕</button>
      <div class="vca-hero">
        <svg viewBox="0 0 120 80" class="vca-svg">
          <circle cx="60" cy="40" r="24" class="vca-clock" />
          <line x1="60" y1="40" x2="60" y2="24" class="vca-min" />
          <line x1="60" y1="40" x2="73" y2="44" class="vca-hr" />
          <circle cx="60" cy="40" r="3" class="vca-center" />
          <line x1="60" y1="12" x2="60" y2="16" class="vca-tk" /><line x1="60" y1="64" x2="60" y2="68" class="vca-tk" />
          <line x1="32" y1="40" x2="36" y2="40" class="vca-tk" /><line x1="84" y1="40" x2="88" y2="40" class="vca-tk" />
        </svg>
        <h2>Registro de Personal</h2>
        <p>Marcaciones del reloj de personal</p>
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
  { t: 'Elegí el rango de fechas', d: 'Indicá Desde y al, y presioná “Aceptar”. Se listan las marcaciones del reloj de ese período.' },
  { t: 'Filtrá por nombre', d: 'Escribí parte del nombre del empleado y la lista se filtra al instante.' },
  { t: 'Ajustes manuales', d: '“Incluir ajustes” intercala los ajustes de horario; “Mostrar solo ajustes” muestra únicamente esos. Las filas de ajuste aparecen en amarillo.' },
  { t: 'Total e impresión', d: 'Abajo se ve el total de registros. Con “Imprimir Consulta” generás el PDF del reporte.' },
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
.vca-clock { fill:rgba(255,255,255,.12); stroke:#fff; stroke-width:2.6; } .vca-min { stroke:#fff; stroke-width:2.6; stroke-linecap:round; transform-origin:60px 40px; animation:vca-tick 4s steps(8) infinite; } .vca-hr { stroke:rgba(255,255,255,.85); stroke-width:2.4; stroke-linecap:round; } .vca-center { fill:#fde68a; } .vca-tk { stroke:rgba(255,255,255,.7); stroke-width:2; }
@keyframes vca-tick { to { transform:rotate(360deg) } }
.vca-hero h2 { margin:10px 0 4px; font-size:18px; } .vca-hero p { margin:0; font-size:13px; opacity:.9; }
.vca-body { padding:18px 22px 24px; }
.vca-step { display:flex; gap:12px; padding:10px 0; border-bottom:1px solid #f1f5f9; animation:vca-slide .4s ease both; }
@keyframes vca-slide { from { transform:translateX(-10px); opacity:0 } to { transform:none; opacity:1 } }
.vca-num { flex-shrink:0; width:26px; height:26px; border-radius:50%; background:#1b4332; color:#fff; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:700; }
.vca-step b { font-size:14px; color:#1e293b; } .vca-step p { margin:3px 0 0; font-size:13px; color:#64748b; }
</style>
