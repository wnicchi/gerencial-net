<!-- MejoresSueldosAyuda.vue — Ayuda del módulo Mejores Sueldos / Promedio. -->
<template>
  <div class="msa-ov" @click.self="$emit('close')">
    <div class="msa-modal">
      <button class="msa-x" @click="$emit('close')">✕</button>
      <div class="msa-hero">
        <svg viewBox="0 0 120 80" class="msa-svg">
          <rect x="26" y="48" width="14" height="18" rx="2" class="msa-bar msa-b1" />
          <rect x="46" y="36" width="14" height="30" rx="2" class="msa-bar msa-b2" />
          <rect x="66" y="26" width="14" height="40" rx="2" class="msa-bar msa-b3" />
          <path d="M26 44 l20 -10 l20 -8 l16 -6" class="msa-line" fill="none" /><path d="M82 14 l6 0 l0 6" class="msa-arr" fill="none" />
        </svg>
        <h2>Mejores Sueldos / Promedio</h2>
        <p>Mejor bruto, mejor neto y promedio</p>
      </div>
      <div class="msa-body">
        <div class="msa-step" v-for="(s, i) in pasos" :key="i" :style="{ animationDelay: (i * 90) + 'ms' }">
          <span class="msa-num">{{ i + 1 }}</span>
          <div><b>{{ s.t }}</b><p>{{ s.d }}</p></div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
defineEmits(['close'])
const pasos = [
  { t: 'Filtrá', d: 'Empresa, contratista, lugar, convenio, categoría y banco. Tildá “Rango” en Período para acotar por fechas.' },
  { t: 'Tildá los tipos', d: 'Para “Mejor Sueldo” y “Promedio”, marcá los tipos de liquidación que querés que se sumen (Todo/Nada para marcar rápido).' },
  { t: 'Mejor Sueldo Bruto', d: 'El mayor sueldo bruto (haberes) liquidado a cada empleado y en qué mes/año fue. No depende de los tipos tildados.' },
  { t: 'Mejor Sueldo', d: 'El mejor neto de un período de cada empleado, sumando solo los tipos tildados.' },
  { t: 'Sueldo Promedio', d: 'El promedio de los netos por período. Se divide por la cantidad de meses con datos (histórico) o por los meses del rango.' },
]
</script>

<style scoped>
.msa-ov { position:fixed; inset:0; background:rgba(15,23,42,.55); display:flex; align-items:center; justify-content:center; z-index:1000; animation:msa-fade .2s ease; }
@keyframes msa-fade { from { opacity:0 } to { opacity:1 } }
.msa-modal { background:#fff; width:520px; max-width:94vw; max-height:90vh; overflow:auto; border-radius:14px; position:relative; box-shadow:0 20px 60px rgba(0,0,0,.3); animation:msa-pop .25s cubic-bezier(.2,.9,.3,1.2); }
@keyframes msa-pop { from { transform:translateY(20px) scale(.96); opacity:0 } to { transform:none; opacity:1 } }
.msa-x { position:absolute; top:12px; right:14px; background:rgba(255,255,255,.2); border:none; color:#fff; font-size:16px; cursor:pointer; width:28px; height:28px; border-radius:50%; }
.msa-hero { background:linear-gradient(135deg,#1b4332,#40916c); color:#fff; padding:26px 24px 22px; text-align:center; border-radius:14px 14px 0 0; }
.msa-svg { width:120px; height:80px; }
.msa-bar { fill:rgba(255,255,255,.8); transform-origin:bottom; }
.msa-b1 { animation:msa-grow .5s .2s ease both; } .msa-b2 { animation:msa-grow .5s .35s ease both; } .msa-b3 { animation:msa-grow .5s .5s ease both; }
@keyframes msa-grow { from { transform:scaleY(0) } to { transform:scaleY(1) } }
.msa-line { stroke:#9ef0c0; stroke-width:2.4; stroke-linecap:round; stroke-linejoin:round; stroke-dasharray:80; stroke-dashoffset:80; animation:msa-draw .7s .7s ease forwards; }
.msa-arr { stroke:#9ef0c0; stroke-width:2.4; stroke-linecap:round; stroke-linejoin:round; stroke-dasharray:14; stroke-dashoffset:14; animation:msa-draw .4s 1.3s ease forwards; }
@keyframes msa-draw { to { stroke-dashoffset:0 } }
.msa-hero h2 { margin:10px 0 4px; font-size:18px; } .msa-hero p { margin:0; font-size:13px; opacity:.9; }
.msa-body { padding:18px 22px 24px; }
.msa-step { display:flex; gap:12px; padding:10px 0; border-bottom:1px solid #f1f5f9; animation:msa-slide .4s ease both; }
@keyframes msa-slide { from { transform:translateX(-10px); opacity:0 } to { transform:none; opacity:1 } }
.msa-num { flex-shrink:0; width:26px; height:26px; border-radius:50%; background:#1b4332; color:#fff; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:700; }
.msa-step b { font-size:14px; color:#1e293b; } .msa-step p { margin:3px 0 0; font-size:13px; color:#64748b; }
</style>
