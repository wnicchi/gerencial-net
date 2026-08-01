<!-- LiquidacionesConsultarAyuda.vue — Ayuda de los módulos Consultar/Borrar liquidaciones. -->
<template>
  <div class="lca-ov" @click.self="$emit('close')">
    <div class="lca-modal">
      <button class="lca-x" @click="$emit('close')">✕</button>
      <div class="lca-hero">
        <svg viewBox="0 0 120 80" class="lca-svg">
          <rect x="34" y="14" width="40" height="52" rx="4" class="lca-doc" />
          <line x1="40" y1="26" x2="68" y2="26" class="lca-l lca-l1" />
          <line x1="40" y1="36" x2="68" y2="36" class="lca-l lca-l2" />
          <line x1="40" y1="46" x2="60" y2="46" class="lca-l lca-l3" />
          <circle cx="84" cy="52" r="12" class="lca-lupa" fill="none" /><line x1="92" y1="60" x2="100" y2="68" class="lca-mango" />
        </svg>
        <h2>Liquidaciones de Sueldos</h2>
        <p>Consultar o borrar una liquidación</p>
      </div>
      <div class="lca-body">
        <div class="lca-step" v-for="(s, i) in pasos" :key="i" :style="{ animationDelay: (i * 90) + 'ms' }">
          <span class="lca-num">{{ i + 1 }}</span>
          <div><b>{{ s.t }}</b><p>{{ s.d }}</p></div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
defineEmits(['close'])
const pasos = [
  { t: 'Elegí el empleado', d: 'Buscalo por código o nombre.' },
  { t: 'Elegí tipo, mes y año', d: 'El tipo de liquidación (sueldo mensual, anticipo, vacaciones, SAC, etc.) y el período.' },
  { t: 'Buscá', d: 'Si existe la liquidación, se muestran sus conceptos (haber y deducción) y el total. Si no, te avisa.' },
  { t: 'Imprimir o Borrar', d: 'En Consultar podés ver e imprimir el PDF. En Borrar se elimina la liquidación completa (no se puede deshacer).' },
]
</script>

<style scoped>
.lca-ov { position:fixed; inset:0; background:rgba(15,23,42,.55); display:flex; align-items:center; justify-content:center; z-index:1000; animation:lca-fade .2s ease; }
@keyframes lca-fade { from { opacity:0 } to { opacity:1 } }
.lca-modal { background:#fff; width:520px; max-width:94vw; max-height:90vh; overflow:auto; border-radius:14px; position:relative; box-shadow:0 20px 60px rgba(0,0,0,.3); animation:lca-pop .25s cubic-bezier(.2,.9,.3,1.2); }
@keyframes lca-pop { from { transform:translateY(20px) scale(.96); opacity:0 } to { transform:none; opacity:1 } }
.lca-x { position:absolute; top:12px; right:14px; background:rgba(255,255,255,.2); border:none; color:#fff; font-size:16px; cursor:pointer; width:28px; height:28px; border-radius:50%; }
.lca-hero { background:linear-gradient(135deg,#1b4332,#40916c); color:#fff; padding:26px 24px 22px; text-align:center; border-radius:14px 14px 0 0; }
.lca-svg { width:120px; height:80px; }
.lca-doc { fill:rgba(255,255,255,.14); stroke:rgba(255,255,255,.6); stroke-width:1.5; animation:lca-rise .6s ease both; }
@keyframes lca-rise { from { transform:translateY(8px); opacity:0 } to { transform:none; opacity:1 } }
.lca-l { stroke:rgba(255,255,255,.6); stroke-width:2.4; stroke-linecap:round; stroke-dasharray:30; stroke-dashoffset:30; }
.lca-l1 { animation:lca-draw .5s .3s ease forwards; } .lca-l2 { animation:lca-draw .5s .45s ease forwards; } .lca-l3 { animation:lca-draw .5s .6s ease forwards; }
.lca-lupa { stroke:#9ef0c0; stroke-width:2.6; } .lca-mango { stroke:#9ef0c0; stroke-width:2.6; stroke-linecap:round; }
@keyframes lca-draw { to { stroke-dashoffset:0 } }
.lca-hero h2 { margin:10px 0 4px; font-size:18px; } .lca-hero p { margin:0; font-size:13px; opacity:.9; }
.lca-body { padding:18px 22px 24px; }
.lca-step { display:flex; gap:12px; padding:10px 0; border-bottom:1px solid #f1f5f9; animation:lca-slide .4s ease both; }
@keyframes lca-slide { from { transform:translateX(-10px); opacity:0 } to { transform:none; opacity:1 } }
.lca-num { flex-shrink:0; width:26px; height:26px; border-radius:50%; background:#1b4332; color:#fff; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:700; }
.lca-step b { font-size:14px; color:#1e293b; } .lca-step p { margin:3px 0 0; font-size:13px; color:#64748b; }
</style>
