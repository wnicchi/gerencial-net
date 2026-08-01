<!-- SueldosPagosAyuda.vue — Ayuda del módulo Comparar con Pagos Bancarios. -->
<template>
  <div class="spa-ov" @click.self="$emit('close')">
    <div class="spa-modal">
      <button class="spa-x" @click="$emit('close')">✕</button>
      <div class="spa-hero">
        <svg viewBox="0 0 120 80" class="spa-svg">
          <rect x="30" y="34" width="60" height="30" rx="3" class="spa-bank" />
          <path d="M30 34 L60 18 L90 34 Z" class="spa-roof" />
          <line x1="40" y1="42" x2="40" y2="58" class="spa-col" /><line x1="52" y1="42" x2="52" y2="58" class="spa-col" />
          <line x1="68" y1="42" x2="68" y2="58" class="spa-col" /><line x1="80" y1="42" x2="80" y2="58" class="spa-col" />
          <circle cx="96" cy="52" r="13" class="spa-coin spa-c1" />
          <circle cx="96" cy="52" r="13" class="spa-coin spa-c2" />
          <text x="96" y="56" text-anchor="middle" class="spa-pesos">$</text>
        </svg>
        <h2>Comparar con Pagos Bancarios</h2>
        <p>Lo liquidado contra lo realmente pagado por banco</p>
      </div>
      <div class="spa-body">
        <div class="spa-step" v-for="(s, i) in pasos" :key="i" :style="{ animationDelay: (i * 90) + 'ms' }">
          <span class="spa-num">{{ i + 1 }}</span>
          <div><b>{{ s.t }}</b><p>{{ s.d }}</p></div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
defineEmits(['close'])
const pasos = [
  { t: 'Período de Liquidación', d: 'Rango de fechas de PAGO de las liquidaciones que querés revisar.' },
  { t: 'Pagado desde', d: 'Rango de fechas en que el banco realizó los pagos reales (sistema de Gestión). Suele coincidir con el período de liquidación.' },
  { t: 'Filtrá (opcional)', d: 'Empresa, banco y tipo de liquidación. Dejalos en “Todos” para no filtrar.' },
  { t: 'Elegí la vista', d: 'Detallado por Liquidación, Totalizada por Liquidación, o Totales Bancarios.' },
  { t: 'Qué mirar', d: 'En Totales Bancarios, la columna Diferencia (liquidado menos pagado). Si no es cero, lo que pagó el banco no coincide con lo liquidado y hay que revisarlo. Las filas con diferencia se resaltan. Podés imprimir el PDF.' },
]
</script>

<style scoped>
.spa-ov { position:fixed; inset:0; background:rgba(15,23,42,.55); display:flex; align-items:center; justify-content:center; z-index:1000; animation:spa-fade .2s ease; }
@keyframes spa-fade { from { opacity:0 } to { opacity:1 } }
.spa-modal { background:#fff; width:540px; max-width:94vw; max-height:90vh; overflow:auto; border-radius:14px; position:relative; box-shadow:0 20px 60px rgba(0,0,0,.3); animation:spa-pop .25s cubic-bezier(.2,.9,.3,1.2); }
@keyframes spa-pop { from { transform:translateY(20px) scale(.96); opacity:0 } to { transform:none; opacity:1 } }
.spa-x { position:absolute; top:12px; right:14px; background:rgba(255,255,255,.2); border:none; color:#fff; font-size:16px; cursor:pointer; width:28px; height:28px; border-radius:50%; }
.spa-hero { background:linear-gradient(135deg,#1b4332,#40916c); color:#fff; padding:26px 24px 22px; text-align:center; border-radius:14px 14px 0 0; }
.spa-svg { width:130px; height:84px; }
.spa-bank, .spa-roof, .spa-col { stroke:rgba(255,255,255,.85); stroke-width:2; fill:none; stroke-linecap:round; }
.spa-roof { fill:rgba(255,255,255,.16); }
.spa-coin { fill:rgba(255,255,255,.18); stroke:rgba(255,255,255,.85); stroke-width:1.8; }
.spa-c2 { fill:none; animation:spa-pulse 2s ease-in-out infinite; transform-origin:96px 52px; }
@keyframes spa-pulse { 0%,100% { transform:scale(1); opacity:.7 } 50% { transform:scale(1.18); opacity:0 } }
.spa-pesos { fill:#fff; font-size:13px; font-weight:700; }
.spa-hero h2 { margin:10px 0 4px; font-size:18px; } .spa-hero p { margin:0; font-size:13px; opacity:.9; }
.spa-body { padding:18px 22px 24px; }
.spa-step { display:flex; gap:12px; padding:10px 0; border-bottom:1px solid #f1f5f9; animation:spa-slide .4s ease both; }
@keyframes spa-slide { from { transform:translateX(-10px); opacity:0 } to { transform:none; opacity:1 } }
.spa-num { flex-shrink:0; width:26px; height:26px; border-radius:50%; background:#1b4332; color:#fff; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:700; }
.spa-step b { font-size:14px; color:#1e293b; } .spa-step p { margin:3px 0 0; font-size:13px; color:#64748b; }
</style>
