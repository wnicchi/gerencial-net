<!-- VacacionesPendientesAyuda.vue — Ayuda del módulo Vacaciones Pendientes. -->
<template>
  <div class="vca-ov" @click.self="$emit('close')">
    <div class="vca-modal">
      <button class="vca-x" @click="$emit('close')">✕</button>
      <div class="vca-hero">
        <svg viewBox="0 0 120 80" class="vca-svg">
          <circle cx="60" cy="40" r="22" class="vca-clock" />
          <line x1="60" y1="40" x2="60" y2="26" class="vca-hand" />
          <line x1="60" y1="40" x2="72" y2="44" class="vca-hand2" />
          <circle cx="60" cy="40" r="2.5" class="vca-center" />
        </svg>
        <h2>Vacaciones Pendientes</h2>
        <p>Días que le quedan a cada empleado</p>
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
  { t: 'Elegí el rango de años', d: 'Indicá Desde y Hasta. Podés analizar un solo empleado y/o incluir al personal pasivo (dado de baja).' },
  { t: 'Consultá', d: 'Por cada empleado y año aparece: días que correspondían, tomados, liquidados y los pendientes (corresponden menos tomados). Solo se muestran los que tienen pendientes.' },
  { t: 'Interpretá los colores', d: 'El personal pasivo se resalta en rojo; los días pendientes en amarillo. Abajo se ve el total de días pendientes.' },
  { t: 'Imprimí', d: 'Generá el PDF de la consulta completa.' },
]
</script>

<style scoped>
.vca-ov { position:fixed; inset:0; background:rgba(15,23,42,.55); display:flex; align-items:center; justify-content:center; z-index:1000; animation:vca-fade .2s ease; }
@keyframes vca-fade { from { opacity:0 } to { opacity:1 } }
.vca-modal { background:#fff; width:520px; max-width:94vw; max-height:90vh; overflow:auto; border-radius:14px; position:relative; box-shadow:0 20px 60px rgba(0,0,0,.3); animation:vca-pop .25s cubic-bezier(.2,.9,.3,1.2); }
@keyframes vca-pop { from { transform:translateY(20px) scale(.96); opacity:0 } to { transform:none; opacity:1 } }
.vca-x { position:absolute; top:12px; right:14px; background:rgba(255,255,255,.2); border:none; color:#fff; font-size:16px; cursor:pointer; width:28px; height:28px; border-radius:50%; }
.vca-hero { background:linear-gradient(135deg,#b45309,#f59e0b); color:#fff; padding:26px 24px 22px; text-align:center; border-radius:14px 14px 0 0; }
.vca-svg { width:120px; height:80px; }
.vca-clock { fill:rgba(255,255,255,.12); stroke:#fff; stroke-width:2.6; } .vca-hand { stroke:#fff; stroke-width:2.6; stroke-linecap:round; transform-origin:60px 40px; animation:vca-tick 6s steps(12) infinite; } .vca-hand2 { stroke:rgba(255,255,255,.85); stroke-width:2.2; stroke-linecap:round; } .vca-center { fill:#fde68a; }
@keyframes vca-tick { to { transform:rotate(360deg) } }
.vca-hero h2 { margin:10px 0 4px; font-size:18px; } .vca-hero p { margin:0; font-size:13px; opacity:.9; }
.vca-body { padding:18px 22px 24px; }
.vca-step { display:flex; gap:12px; padding:10px 0; border-bottom:1px solid #f1f5f9; animation:vca-slide .4s ease both; }
@keyframes vca-slide { from { transform:translateX(-10px); opacity:0 } to { transform:none; opacity:1 } }
.vca-num { flex-shrink:0; width:26px; height:26px; border-radius:50%; background:#b45309; color:#fff; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:700; }
.vca-step b { font-size:14px; color:#1e293b; } .vca-step p { margin:3px 0 0; font-size:13px; color:#64748b; }
</style>
