<!-- ListadosLiquidacionesAyuda.vue — Ayuda del módulo Listados de Liquidaciones. -->
<template>
  <div class="lla-ov" @click.self="$emit('close')">
    <div class="lla-modal">
      <button class="lla-x" @click="$emit('close')">✕</button>
      <div class="lla-hero">
        <svg viewBox="0 0 120 80" class="lla-svg">
          <rect x="34" y="14" width="40" height="52" rx="4" class="lla-doc" />
          <line x1="40" y1="26" x2="68" y2="26" class="lla-l lla-l1" /><line x1="40" y1="36" x2="68" y2="36" class="lla-l lla-l2" /><line x1="40" y1="46" x2="68" y2="46" class="lla-l lla-l3" /><line x1="40" y1="56" x2="58" y2="56" class="lla-l lla-l4" />
        </svg>
        <h2>Listados de Liquidaciones</h2>
        <p>Informes de liquidaciones de sueldo</p>
      </div>
      <div class="lla-body">
        <div class="lla-step" v-for="(s, i) in pasos" :key="i" :style="{ animationDelay: (i * 90) + 'ms' }">
          <span class="lla-num">{{ i + 1 }}</span>
          <div><b>{{ s.t }}</b><p>{{ s.d }}</p></div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
defineEmits(['close'])
const pasos = [
  { t: 'Filtrá lo que necesites', d: 'Tipo de liquidación, concepto, empresa, contratista, lugar, convenio, categoría y banco. Dejalos en “Todas” para no filtrar.' },
  { t: 'Acotá por período', d: 'Tildá “Rango” en Período para limitar por fecha de liquidación, y/o en Pagado para limitar por fecha de pago.' },
  { t: 'Elegí el detalle', d: 'Completo muestra cada liquidación con todos sus conceptos; Resumido muestra solo el total por liquidación.' },
  { t: 'Generá', d: '“Liquidaciones” usa el detalle elegido. “Sueldos Bruto” muestra solo los haberes (sin deducciones). Cada salida abre un PDF con el total general al pie.' },
]
</script>

<style scoped>
.lla-ov { position:fixed; inset:0; background:rgba(15,23,42,.55); display:flex; align-items:center; justify-content:center; z-index:1000; animation:lla-fade .2s ease; }
@keyframes lla-fade { from { opacity:0 } to { opacity:1 } }
.lla-modal { background:#fff; width:520px; max-width:94vw; max-height:90vh; overflow:auto; border-radius:14px; position:relative; box-shadow:0 20px 60px rgba(0,0,0,.3); animation:lla-pop .25s cubic-bezier(.2,.9,.3,1.2); }
@keyframes lla-pop { from { transform:translateY(20px) scale(.96); opacity:0 } to { transform:none; opacity:1 } }
.lla-x { position:absolute; top:12px; right:14px; background:rgba(255,255,255,.2); border:none; color:#fff; font-size:16px; cursor:pointer; width:28px; height:28px; border-radius:50%; }
.lla-hero { background:linear-gradient(135deg,#1b4332,#40916c); color:#fff; padding:26px 24px 22px; text-align:center; border-radius:14px 14px 0 0; }
.lla-svg { width:120px; height:80px; }
.lla-doc { fill:rgba(255,255,255,.14); stroke:rgba(255,255,255,.6); stroke-width:1.5; animation:lla-rise .6s ease both; }
@keyframes lla-rise { from { transform:translateY(8px); opacity:0 } to { transform:none; opacity:1 } }
.lla-l { stroke:rgba(255,255,255,.65); stroke-width:2.4; stroke-linecap:round; stroke-dasharray:30; stroke-dashoffset:30; }
.lla-l1 { animation:lla-draw .5s .3s ease forwards; } .lla-l2 { animation:lla-draw .5s .45s ease forwards; } .lla-l3 { animation:lla-draw .5s .6s ease forwards; } .lla-l4 { animation:lla-draw .5s .75s ease forwards; }
@keyframes lla-draw { to { stroke-dashoffset:0 } }
.lla-hero h2 { margin:10px 0 4px; font-size:18px; } .lla-hero p { margin:0; font-size:13px; opacity:.9; }
.lla-body { padding:18px 22px 24px; }
.lla-step { display:flex; gap:12px; padding:10px 0; border-bottom:1px solid #f1f5f9; animation:lla-slide .4s ease both; }
@keyframes lla-slide { from { transform:translateX(-10px); opacity:0 } to { transform:none; opacity:1 } }
.lla-num { flex-shrink:0; width:26px; height:26px; border-radius:50%; background:#1b4332; color:#fff; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:700; }
.lla-step b { font-size:14px; color:#1e293b; } .lla-step p { margin:3px 0 0; font-size:13px; color:#64748b; }
</style>
