<!-- MultasListadosAyuda.vue — Ayuda del módulo Multas - Listados de Control. -->
<template>
  <div class="mla-ov" @click.self="$emit('close')">
    <div class="mla-modal">
      <button class="mla-x" @click="$emit('close')">✕</button>
      <div class="mla-hero">
        <svg viewBox="0 0 120 80" class="mla-svg">
          <circle cx="44" cy="40" r="20" class="mla-sign" />
          <text x="44" y="48" text-anchor="middle" class="mla-excl">!</text>
          <rect x="70" y="30" width="34" height="22" rx="2" class="mla-ticket" />
          <line x1="76" y1="38" x2="98" y2="38" class="mla-line" /><line x1="76" y1="44" x2="90" y2="44" class="mla-line" />
        </svg>
        <h2>Multas - Listados de Control</h2>
        <p>Cuenta corriente de multas por empleado</p>
      </div>
      <div class="mla-body">
        <div class="mla-step" v-for="(s, i) in pasos" :key="i" :style="{ animationDelay: (i * 90) + 'ms' }">
          <span class="mla-num">{{ i + 1 }}</span>
          <div><b>{{ s.t }}</b><p>{{ s.d }}</p></div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
defineEmits(['close'])
const pasos = [
  { t: 'Elegí los empleados', d: 'Todos los activos, o uno solo buscándolo por nombre o código.' },
  { t: 'Elegí el período', d: 'Histórico (desde el 01/07/2020 hasta hoy) o un Rango de fechas (nunca antes del 01/07/2020).' },
  { t: 'Detallado o Resumido', d: 'Detallado muestra cada movimiento con su saldo; Resumido, una línea por empleado con el saldo final.' },
  { t: 'Cómo se calcula', d: 'Las multas registradas en Gestión suman como EGRESO; los descuentos de multas en las liquidaciones de sueldo restan como INGRESO. El SALDO es el acumulado por empleado.' },
  { t: 'Qué muestra', d: 'Solo aparecen los empleados que todavía adeudan (saldo mayor a cero). Podés imprimir el PDF.' },
]
</script>

<style scoped>
.mla-ov { position:fixed; inset:0; background:rgba(15,23,42,.55); display:flex; align-items:center; justify-content:center; z-index:1000; animation:mla-fade .2s ease; }
@keyframes mla-fade { from { opacity:0 } to { opacity:1 } }
.mla-modal { background:#fff; width:540px; max-width:94vw; max-height:90vh; overflow:auto; border-radius:14px; position:relative; box-shadow:0 20px 60px rgba(0,0,0,.3); animation:mla-pop .25s cubic-bezier(.2,.9,.3,1.2); }
@keyframes mla-pop { from { transform:translateY(20px) scale(.96); opacity:0 } to { transform:none; opacity:1 } }
.mla-x { position:absolute; top:12px; right:14px; background:rgba(255,255,255,.2); border:none; color:#fff; font-size:16px; cursor:pointer; width:28px; height:28px; border-radius:50%; }
.mla-hero { background:linear-gradient(135deg,#1b4332,#40916c); color:#fff; padding:26px 24px 22px; text-align:center; border-radius:14px 14px 0 0; }
.mla-svg { width:130px; height:84px; }
.mla-sign { fill:rgba(255,255,255,.14); stroke:rgba(255,255,255,.9); stroke-width:2.4; animation:mla-pulse 2s ease-in-out infinite; transform-origin:44px 40px; }
@keyframes mla-pulse { 0%,100% { transform:scale(1) } 50% { transform:scale(1.06) } }
.mla-excl { fill:#fff; font-size:22px; font-weight:800; }
.mla-ticket { fill:rgba(255,255,255,.14); stroke:rgba(255,255,255,.9); stroke-width:2; }
.mla-line { stroke:rgba(255,255,255,.8); stroke-width:2; stroke-linecap:round; }
.mla-hero h2 { margin:10px 0 4px; font-size:18px; } .mla-hero p { margin:0; font-size:13px; opacity:.9; }
.mla-body { padding:18px 22px 24px; }
.mla-step { display:flex; gap:12px; padding:10px 0; border-bottom:1px solid #f1f5f9; animation:mla-slide .4s ease both; }
@keyframes mla-slide { from { transform:translateX(-10px); opacity:0 } to { transform:none; opacity:1 } }
.mla-num { flex-shrink:0; width:26px; height:26px; border-radius:50%; background:#1b4332; color:#fff; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:700; }
.mla-step b { font-size:14px; color:#1e293b; } .mla-step p { margin:3px 0 0; font-size:13px; color:#64748b; }
</style>
