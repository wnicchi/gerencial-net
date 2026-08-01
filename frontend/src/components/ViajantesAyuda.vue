<!-- ViajantesAyuda.vue — Ayuda del módulo Planilla Libro de Viajantes. -->
<template>
  <div class="vja-ov" @click.self="$emit('close')">
    <div class="vja-modal">
      <button class="vja-x" @click="$emit('close')">✕</button>
      <div class="vja-hero">
        <svg viewBox="0 0 120 80" class="vja-svg">
          <rect x="38" y="30" width="44" height="32" rx="4" class="vja-bag" />
          <path d="M52 30 v-6 a8 8 0 0 1 16 0 v6" class="vja-handle" fill="none" />
          <line x1="38" y1="44" x2="82" y2="44" class="vja-z" />
          <path d="M92 26 l14 6 l-14 6" class="vja-arrow" fill="none" />
        </svg>
        <h2>Planilla Libro de Viajantes</h2>
        <p>Comisiones por ventas y cobros (Ley 14546)</p>
      </div>
      <div class="vja-body">
        <div class="vja-step" v-for="(s, i) in pasos" :key="i" :style="{ animationDelay: (i * 90) + 'ms' }">
          <span class="vja-num">{{ i + 1 }}</span>
          <div><b>{{ s.t }}</b><p>{{ s.d }}</p></div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
defineEmits(['close'])
const pasos = [
  { t: 'Elegí el vendedor', d: 'Buscalo por nombre o legajo. Debe estar habilitado como vendedor; si no, se avisa que no se puede generar.' },
  { t: 'Elegí el período', d: 'Fecha desde y hasta. “Generar planilla” calcula las comisiones del rango.' },
  { t: 'Comisión por ventas', d: 'Se calcula sobre el monto facturado por el % del vendedor en ese rubro. Las facturas “B” se toman sin IVA y las notas de crédito restan.' },
  { t: 'Comisión por cobros', d: 'Se calcula sobre lo efectivamente cobrado (recibos del período), por el % de cobro del rubro.' },
  { t: 'Filtrar por rubro', d: 'Tildá los rubros que querés incluir; los totales se recalculan al instante.' },
  { t: 'Imprimir / Exportar', d: '“Imprimir libro” genera el PDF del Libro de Viajantes; “Exportar a Excel” baja la planilla.' },
]
</script>

<style scoped>
.vja-ov { position:fixed; inset:0; background:rgba(15,23,42,.55); display:flex; align-items:center; justify-content:center; z-index:1000; animation:vja-fade .2s ease; }
@keyframes vja-fade { from { opacity:0 } to { opacity:1 } }
.vja-modal { background:#fff; width:520px; max-width:94vw; max-height:90vh; overflow:auto; border-radius:14px; position:relative; box-shadow:0 20px 60px rgba(0,0,0,.3); animation:vja-pop .25s cubic-bezier(.2,.9,.3,1.2); }
@keyframes vja-pop { from { transform:translateY(20px) scale(.96); opacity:0 } to { transform:none; opacity:1 } }
.vja-x { position:absolute; top:12px; right:14px; background:rgba(255,255,255,.2); border:none; color:#fff; font-size:16px; cursor:pointer; width:28px; height:28px; border-radius:50%; }
.vja-hero { background:linear-gradient(135deg,#1b4332,#40916c); color:#fff; padding:26px 24px 22px; text-align:center; border-radius:14px 14px 0 0; }
.vja-svg { width:120px; height:80px; }
.vja-bag { fill:rgba(255,255,255,.16); stroke:rgba(255,255,255,.6); stroke-width:1.5; animation:vja-rise .6s ease both; }
@keyframes vja-rise { from { transform:translateY(8px); opacity:0 } to { transform:none; opacity:1 } }
.vja-handle { stroke:rgba(255,255,255,.7); stroke-width:2.2; }
.vja-z { stroke:rgba(255,255,255,.5); stroke-width:1.5; stroke-dasharray:44; stroke-dashoffset:44; animation:vja-draw .6s .4s ease forwards; }
.vja-arrow { stroke:#9ef0c0; stroke-width:2.6; stroke-linecap:round; stroke-linejoin:round; stroke-dasharray:30; stroke-dashoffset:30; animation:vja-draw .5s .7s ease forwards; }
@keyframes vja-draw { to { stroke-dashoffset:0 } }
.vja-hero h2 { margin:10px 0 4px; font-size:18px; } .vja-hero p { margin:0; font-size:13px; opacity:.9; }
.vja-body { padding:18px 22px 24px; }
.vja-step { display:flex; gap:12px; padding:10px 0; border-bottom:1px solid #f1f5f9; animation:vja-slide .4s ease both; }
@keyframes vja-slide { from { transform:translateX(-10px); opacity:0 } to { transform:none; opacity:1 } }
.vja-num { flex-shrink:0; width:26px; height:26px; border-radius:50%; background:#1b4332; color:#fff; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:700; }
.vja-step b { font-size:14px; color:#1e293b; } .vja-step p { margin:3px 0 0; font-size:13px; color:#64748b; }
</style>
