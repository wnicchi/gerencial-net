<!-- ResumenLiquidacionesAyuda.vue — Ayuda del módulo Exportar Resumen Liquidaciones. -->
<template>
  <div class="rla-ov" @click.self="$emit('close')">
    <div class="rla-modal">
      <button class="rla-x" @click="$emit('close')">✕</button>
      <div class="rla-hero">
        <svg viewBox="0 0 120 80" class="rla-svg">
          <rect x="26" y="18" width="68" height="48" rx="4" class="rla-tb" />
          <line x1="26" y1="30" x2="94" y2="30" class="rla-l rla-l1" /><line x1="48" y1="18" x2="48" y2="66" class="rla-l rla-l2" /><line x1="70" y1="18" x2="70" y2="66" class="rla-l rla-l3" />
        </svg>
        <h2>Exportar Resumen Liquidaciones</h2>
        <p>Planilla por empleado y tipo de liquidación</p>
      </div>
      <div class="rla-body">
        <div class="rla-step" v-for="(s, i) in pasos" :key="i" :style="{ animationDelay: (i * 90) + 'ms' }">
          <span class="rla-num">{{ i + 1 }}</span>
          <div><b>{{ s.t }}</b><p>{{ s.d }}</p></div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
defineEmits(['close'])
const pasos = [
  { t: 'Elegí el período', d: 'Fecha desde y hasta (por fecha de liquidación).' },
  { t: 'Filtrá (opcional)', d: 'Empresa, contratista, lugar, convenio, categoría o banco.' },
  { t: 'Generá la planilla', d: 'Se arma una grilla: una fila por empleado y una columna por cada tipo de liquidación del período, con el total de cada tipo y el total de la fila.' },
  { t: 'Adelantos', d: 'La columna de Adelantos resta (va en negativo) en el total.' },
  { t: 'Exportá', d: '“Generar Excel” baja la planilla; “Generar PDF” la imprime.' },
]
</script>

<style scoped>
.rla-ov { position:fixed; inset:0; background:rgba(15,23,42,.55); display:flex; align-items:center; justify-content:center; z-index:1000; animation:rla-fade .2s ease; }
@keyframes rla-fade { from { opacity:0 } to { opacity:1 } }
.rla-modal { background:#fff; width:520px; max-width:94vw; max-height:90vh; overflow:auto; border-radius:14px; position:relative; box-shadow:0 20px 60px rgba(0,0,0,.3); animation:rla-pop .25s cubic-bezier(.2,.9,.3,1.2); }
@keyframes rla-pop { from { transform:translateY(20px) scale(.96); opacity:0 } to { transform:none; opacity:1 } }
.rla-x { position:absolute; top:12px; right:14px; background:rgba(255,255,255,.2); border:none; color:#fff; font-size:16px; cursor:pointer; width:28px; height:28px; border-radius:50%; }
.rla-hero { background:linear-gradient(135deg,#1b4332,#40916c); color:#fff; padding:26px 24px 22px; text-align:center; border-radius:14px 14px 0 0; }
.rla-svg { width:120px; height:80px; }
.rla-tb { fill:rgba(255,255,255,.14); stroke:rgba(255,255,255,.6); stroke-width:1.5; animation:rla-rise .6s ease both; }
@keyframes rla-rise { from { transform:translateY(8px); opacity:0 } to { transform:none; opacity:1 } }
.rla-l { stroke:rgba(255,255,255,.55); stroke-width:1.6; stroke-dasharray:70; stroke-dashoffset:70; }
.rla-l1 { animation:rla-draw .5s .3s ease forwards; } .rla-l2 { animation:rla-draw .5s .45s ease forwards; } .rla-l3 { animation:rla-draw .5s .6s ease forwards; }
@keyframes rla-draw { to { stroke-dashoffset:0 } }
.rla-hero h2 { margin:10px 0 4px; font-size:17px; } .rla-hero p { margin:0; font-size:13px; opacity:.9; }
.rla-body { padding:18px 22px 24px; }
.rla-step { display:flex; gap:12px; padding:10px 0; border-bottom:1px solid #f1f5f9; animation:rla-slide .4s ease both; }
@keyframes rla-slide { from { transform:translateX(-10px); opacity:0 } to { transform:none; opacity:1 } }
.rla-num { flex-shrink:0; width:26px; height:26px; border-radius:50%; background:#1b4332; color:#fff; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:700; }
.rla-step b { font-size:14px; color:#1e293b; } .rla-step p { margin:3px 0 0; font-size:13px; color:#64748b; }
</style>
