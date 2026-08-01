<!-- ObligacionesContratistaAyuda.vue — Ayuda del módulo Presentación de Obligaciones. -->
<template>
  <div class="oba-ov" @click.self="$emit('close')">
    <div class="oba-modal">
      <button class="oba-x" @click="$emit('close')">✕</button>
      <div class="oba-hero">
        <svg viewBox="0 0 120 80" class="oba-svg">
          <rect x="34" y="14" width="40" height="52" rx="4" class="oba-doc" />
          <path d="M40 28 l4 4 l7 -8" class="oba-ck oba-c1" fill="none" />
          <path d="M40 42 l4 4 l7 -8" class="oba-ck oba-c2" fill="none" />
          <path d="M40 56 l4 4 l7 -8" class="oba-ck oba-c3" fill="none" />
          <circle cx="86" cy="54" r="11" class="oba-seal" />
          <path d="M81 54 l3.5 3.5 l6 -7" class="oba-sk" fill="none" />
        </svg>
        <h2>Presentación de Obligaciones</h2>
        <p>Exigencias presentadas y acceso de empleados</p>
      </div>
      <div class="oba-body">
        <div class="oba-step" v-for="(s, i) in pasos" :key="i" :style="{ animationDelay: (i * 90) + 'ms' }">
          <span class="oba-num">{{ i + 1 }}</span>
          <div><b>{{ s.t }}</b><p>{{ s.d }}</p></div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
defineEmits(['close'])
const pasos = [
  { t: 'Elegí el contratista', d: 'Aparecen sus exigencias asignadas y sus empleados asociados.' },
  { t: 'Marcá las presentadas', d: 'Tildá cada exigencia presentada; se carga la fecha del día y podés ajustar la fecha de presentación y de vencimiento. Las exigencias presentadas vencidas se resaltan en rojo.' },
  { t: 'Actualizar estado de todos', d: 'Recalcula el acceso de los empleados: si el contratista tiene obligaciones vencidas, se les deniega el acceso a todos.' },
  { t: 'Acceso por empleado', d: 'Podés cambiar el acceso de cada empleado con un clic (PERMITIDO/DENEGADO). Si la ART del empleado venció, queda denegado.' },
  { t: 'Confirmar / Exportar', d: '“Confirmar modificaciones” guarda todo. “Exportar a Excel” genera una planilla con las exigencias y los empleados.' },
]
</script>

<style scoped>
.oba-ov { position:fixed; inset:0; background:rgba(15,23,42,.55); display:flex; align-items:center; justify-content:center; z-index:1000; animation:oba-fade .2s ease; }
@keyframes oba-fade { from { opacity:0 } to { opacity:1 } }
.oba-modal { background:#fff; width:520px; max-width:94vw; max-height:90vh; overflow:auto; border-radius:14px; position:relative; box-shadow:0 20px 60px rgba(0,0,0,.3); animation:oba-pop .25s cubic-bezier(.2,.9,.3,1.2); }
@keyframes oba-pop { from { transform:translateY(20px) scale(.96); opacity:0 } to { transform:none; opacity:1 } }
.oba-x { position:absolute; top:12px; right:14px; background:rgba(255,255,255,.2); border:none; color:#fff; font-size:16px; cursor:pointer; width:28px; height:28px; border-radius:50%; }
.oba-hero { background:linear-gradient(135deg,#1b4332,#40916c); color:#fff; padding:26px 24px 22px; text-align:center; border-radius:14px 14px 0 0; }
.oba-svg { width:120px; height:80px; }
.oba-doc { fill:rgba(255,255,255,.14); stroke:rgba(255,255,255,.6); stroke-width:1.5; animation:oba-rise .6s ease both; }
@keyframes oba-rise { from { transform:translateY(8px); opacity:0 } to { transform:none; opacity:1 } }
.oba-ck { stroke:#9ef0c0; stroke-width:2.4; stroke-linecap:round; stroke-linejoin:round; stroke-dasharray:18; stroke-dashoffset:18; }
.oba-c1 { animation:oba-draw .5s .3s ease forwards; } .oba-c2 { animation:oba-draw .5s .5s ease forwards; } .oba-c3 { animation:oba-draw .5s .7s ease forwards; }
.oba-seal { fill:#16a34a; opacity:0; animation:oba-seal .4s .9s ease forwards; } @keyframes oba-seal { to { opacity:1 } }
.oba-sk { stroke:#fff; stroke-width:2.4; stroke-linecap:round; stroke-linejoin:round; stroke-dasharray:16; stroke-dashoffset:16; animation:oba-draw .4s 1.1s ease forwards; }
@keyframes oba-draw { to { stroke-dashoffset:0 } }
.oba-hero h2 { margin:10px 0 4px; font-size:18px; } .oba-hero p { margin:0; font-size:13px; opacity:.9; }
.oba-body { padding:18px 22px 24px; }
.oba-step { display:flex; gap:12px; padding:10px 0; border-bottom:1px solid #f1f5f9; animation:oba-slide .4s ease both; }
@keyframes oba-slide { from { transform:translateX(-10px); opacity:0 } to { transform:none; opacity:1 } }
.oba-num { flex-shrink:0; width:26px; height:26px; border-radius:50%; background:#1b4332; color:#fff; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:700; }
.oba-step b { font-size:14px; color:#1e293b; } .oba-step p { margin:3px 0 0; font-size:13px; color:#64748b; }
</style>
