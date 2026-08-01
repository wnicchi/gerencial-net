<!-- ComparativaLiqNetoAyuda.vue — Ayuda del módulo Comparativa Liq. vs Neto. -->
<template>
  <div class="cna-ov" @click.self="$emit('close')">
    <div class="cna-modal">
      <button class="cna-x" @click="$emit('close')">✕</button>
      <div class="cna-hero">
        <svg viewBox="0 0 120 80" class="cna-svg">
          <line x1="60" y1="20" x2="60" y2="58" class="cna-post" />
          <path d="M60 26 l-22 6 M60 26 l22 6" class="cna-beam" />
          <circle cx="38" cy="46" r="9" class="cna-pan cna-p1" /><line x1="38" y1="32" x2="38" y2="37" class="cna-rope" />
          <circle cx="82" cy="46" r="9" class="cna-pan cna-p2" /><line x1="82" y1="32" x2="82" y2="37" class="cna-rope" />
          <rect x="50" y="58" width="20" height="6" rx="2" class="cna-base" />
        </svg>
        <h2>Comparativa Liq. vs Neto</h2>
        <p>Liquidado del período contra el neto de ficha</p>
      </div>
      <div class="cna-body">
        <div class="cna-step" v-for="(s, i) in pasos" :key="i" :style="{ animationDelay: (i * 90) + 'ms' }">
          <span class="cna-num">{{ i + 1 }}</span>
          <div><b>{{ s.t }}</b><p>{{ s.d }}</p></div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
defineEmits(['close'])
const pasos = [
  { t: 'Elegí el período', d: 'Mes y año a comparar.' },
  { t: 'Filtrá (opcional)', d: 'Empresa, contratista, lugar, convenio, categoría o banco. Dejalos en “Todas” para no filtrar.' },
  { t: 'Visualizá', d: 'Por cada empleado se muestra lo Liquidado (suma de sus liquidaciones del mes), el Neto de la ficha y la Diferencia.' },
  { t: 'Qué mirar', d: 'Las filas con diferencia se resaltan en amarillo. Sirve para detectar liquidaciones que no coinciden con el neto esperado. Podés imprimir el PDF.' },
]
</script>

<style scoped>
.cna-ov { position:fixed; inset:0; background:rgba(15,23,42,.55); display:flex; align-items:center; justify-content:center; z-index:1000; animation:cna-fade .2s ease; }
@keyframes cna-fade { from { opacity:0 } to { opacity:1 } }
.cna-modal { background:#fff; width:520px; max-width:94vw; max-height:90vh; overflow:auto; border-radius:14px; position:relative; box-shadow:0 20px 60px rgba(0,0,0,.3); animation:cna-pop .25s cubic-bezier(.2,.9,.3,1.2); }
@keyframes cna-pop { from { transform:translateY(20px) scale(.96); opacity:0 } to { transform:none; opacity:1 } }
.cna-x { position:absolute; top:12px; right:14px; background:rgba(255,255,255,.2); border:none; color:#fff; font-size:16px; cursor:pointer; width:28px; height:28px; border-radius:50%; }
.cna-hero { background:linear-gradient(135deg,#1b4332,#40916c); color:#fff; padding:26px 24px 22px; text-align:center; border-radius:14px 14px 0 0; }
.cna-svg { width:120px; height:80px; }
.cna-post, .cna-beam, .cna-rope, .cna-base { stroke:rgba(255,255,255,.8); stroke-width:2; fill:none; stroke-linecap:round; }
.cna-base { fill:rgba(255,255,255,.8); }
.cna-pan { fill:rgba(255,255,255,.16); stroke:rgba(255,255,255,.7); stroke-width:1.8; transform-origin:60px 30px; }
.cna-p1 { animation:cna-tilt 2.4s ease-in-out infinite; } .cna-p2 { animation:cna-tilt2 2.4s ease-in-out infinite; }
@keyframes cna-tilt { 0%,100% { transform:translateY(0) } 50% { transform:translateY(4px) } }
@keyframes cna-tilt2 { 0%,100% { transform:translateY(0) } 50% { transform:translateY(-4px) } }
.cna-hero h2 { margin:10px 0 4px; font-size:18px; } .cna-hero p { margin:0; font-size:13px; opacity:.9; }
.cna-body { padding:18px 22px 24px; }
.cna-step { display:flex; gap:12px; padding:10px 0; border-bottom:1px solid #f1f5f9; animation:cna-slide .4s ease both; }
@keyframes cna-slide { from { transform:translateX(-10px); opacity:0 } to { transform:none; opacity:1 } }
.cna-num { flex-shrink:0; width:26px; height:26px; border-radius:50%; background:#1b4332; color:#fff; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:700; }
.cna-step b { font-size:14px; color:#1e293b; } .cna-step p { margin:3px 0 0; font-size:13px; color:#64748b; }
</style>
