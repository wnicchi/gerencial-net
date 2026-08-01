<!-- EmpleadosContratistaAyuda.vue — Modal de ayuda del módulo Empleados de Contratistas. -->
<template>
  <div class="eca-ov" @click.self="$emit('close')">
    <div class="eca-modal">
      <button class="eca-x" @click="$emit('close')">✕</button>
      <div class="eca-hero">
        <svg viewBox="0 0 120 80" class="eca-svg">
          <circle cx="42" cy="32" r="9" class="eca-h eca-h1" />
          <path d="M28 60 q14 -16 28 0" class="eca-b eca-b1" fill="none" />
          <circle cx="74" cy="34" r="7" class="eca-h eca-h2" />
          <path d="M62 58 q12 -13 24 0" class="eca-b eca-b2" fill="none" />
          <circle cx="92" cy="24" r="8" class="eca-plus-bg" />
          <path d="M92 20 v8 M88 24 h8" class="eca-plus" fill="none" />
        </svg>
        <h2>Empleados de Contratistas</h2>
        <p>Carga y mantenimiento de empleados por contratista</p>
      </div>
      <div class="eca-body">
        <div class="eca-step" v-for="(s, i) in pasos" :key="i" :style="{ animationDelay: (i * 90) + 'ms' }">
          <span class="eca-num">{{ i + 1 }}</span>
          <div><b>{{ s.t }}</b><p>{{ s.d }}</p></div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
defineEmits(['close'])
const pasos = [
  { t: 'Elegí el contratista', d: 'Seleccionalo de la lista. Aparecen sus empleados cargados.' },
  { t: 'Agregá o editá', d: 'Con “Agregar empleado” sumás una fila nueva; podés editar nombre, DNI, CUIL, teléfonos y vencimiento de ART directamente en la grilla.' },
  { t: 'Colores de las filas', d: 'Roja = ART vencida; amarilla = el empleado está ocupado en una obra que todavía no finalizó.' },
  { t: 'Eliminar', d: 'Tildá los empleados que querés quitar y usá “Eliminar marcados”.' },
  { t: 'Confirmar', d: 'Recién al “CONFIRMAR EMPLEADOS” se guardan en el sistema las altas, bajas y modificaciones de la grilla.' },
]
</script>

<style scoped>
.eca-ov { position:fixed; inset:0; background:rgba(15,23,42,.55); display:flex; align-items:center; justify-content:center; z-index:1000; animation:eca-fade .2s ease; }
@keyframes eca-fade { from { opacity:0 } to { opacity:1 } }
.eca-modal { background:#fff; width:520px; max-width:94vw; max-height:90vh; overflow:auto; border-radius:14px; position:relative; box-shadow:0 20px 60px rgba(0,0,0,.3); animation:eca-pop .25s cubic-bezier(.2,.9,.3,1.2); }
@keyframes eca-pop { from { transform:translateY(20px) scale(.96); opacity:0 } to { transform:none; opacity:1 } }
.eca-x { position:absolute; top:12px; right:14px; background:rgba(255,255,255,.2); border:none; color:#fff; font-size:16px; cursor:pointer; width:28px; height:28px; border-radius:50%; }
.eca-hero { background:linear-gradient(135deg,#1b4332,#40916c); color:#fff; padding:26px 24px 22px; text-align:center; border-radius:14px 14px 0 0; }
.eca-svg { width:120px; height:80px; }
.eca-h { fill:rgba(255,255,255,.85); } .eca-b { stroke:rgba(255,255,255,.7); stroke-width:2.4; }
.eca-h1, .eca-b1 { animation:eca-rise .6s ease both; } .eca-h2, .eca-b2 { animation:eca-rise .6s .15s ease both; }
@keyframes eca-rise { from { transform:translateY(8px); opacity:0 } to { transform:none; opacity:1 } }
.eca-plus-bg { fill:#22c55e; } .eca-plus { stroke:#fff; stroke-width:2.4; stroke-linecap:round; animation:eca-pop2 .4s .5s ease both; }
@keyframes eca-pop2 { from { transform:scale(0); transform-origin:92px 24px } to { transform:scale(1) } }
.eca-hero h2 { margin:10px 0 4px; font-size:19px; } .eca-hero p { margin:0; font-size:13px; opacity:.9; }
.eca-body { padding:18px 22px 24px; }
.eca-step { display:flex; gap:12px; padding:10px 0; border-bottom:1px solid #f1f5f9; animation:eca-slide .4s ease both; }
@keyframes eca-slide { from { transform:translateX(-10px); opacity:0 } to { transform:none; opacity:1 } }
.eca-num { flex-shrink:0; width:26px; height:26px; border-radius:50%; background:#1b4332; color:#fff; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:700; }
.eca-step b { font-size:14px; color:#1e293b; } .eca-step p { margin:3px 0 0; font-size:13px; color:#64748b; }
</style>
