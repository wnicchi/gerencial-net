<!-- ContratistasExternosAyuda.vue — Modal de ayuda animado del módulo Contratistas Externos. -->
<template>
  <div class="cea-ov" @click.self="$emit('close')">
    <div class="cea-modal">
      <button class="cea-x" @click="$emit('close')">✕</button>
      <div class="cea-hero">
        <svg viewBox="0 0 120 80" class="cea-svg">
          <rect x="14" y="20" width="40" height="44" rx="4" class="cea-card cea-c1" />
          <rect x="66" y="20" width="40" height="44" rx="4" class="cea-card cea-c2" />
          <circle cx="34" cy="34" r="7" class="cea-head" />
          <rect x="22" y="46" width="24" height="4" rx="2" class="cea-line" />
          <rect x="22" y="53" width="18" height="4" rx="2" class="cea-line" />
          <path d="M74 40 l5 5 l10 -12" class="cea-check" fill="none" />
        </svg>
        <h2>Contratistas Externos</h2>
        <p>ABM de contratistas con sus exigencias legales y empleados</p>
      </div>
      <div class="cea-body">
        <div class="cea-step" v-for="(s, i) in pasos" :key="i" :style="{ animationDelay: (i * 90) + 'ms' }">
          <span class="cea-num">{{ i + 1 }}</span>
          <div><b>{{ s.t }}</b><p>{{ s.d }}</p></div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
defineEmits(['close'])
const pasos = [
  { t: 'Crear o elegir un contratista', d: 'Usá “＋ Nuevo” para crear uno, o seleccioná uno de la lista de la izquierda. El código se asigna solo.' },
  { t: 'Solapa Datos Generales', d: 'Cargá estado (activo/pasivo), tipo, nombre, domicilio, teléfonos, email y ART. Guardá los datos antes de pasar a las otras solapas.' },
  { t: 'Solapa Exigencias en Obras', d: 'Tildá las exigencias legales que se le piden a este contratista y guardá. La lista sale del módulo de Exigencias Legales.' },
  { t: 'Solapa Empleados Externos', d: 'Cargá los empleados del contratista (DNI, CUIL, teléfonos, vencimiento de ART). Las filas con ART vencida se resaltan en rojo. Confirmá para guardar.' },
  { t: 'Eliminar', d: 'No se puede eliminar un contratista que ya tiene empleados o exigencias asociadas.' },
]
</script>

<style scoped>
.cea-ov { position:fixed; inset:0; background:rgba(15,23,42,.55); display:flex; align-items:center; justify-content:center; z-index:1000; animation:cea-fade .2s ease; }
@keyframes cea-fade { from { opacity:0 } to { opacity:1 } }
.cea-modal { background:#fff; width:520px; max-width:94vw; max-height:90vh; overflow:auto; border-radius:14px; position:relative; box-shadow:0 20px 60px rgba(0,0,0,.3); animation:cea-pop .25s cubic-bezier(.2,.9,.3,1.2); }
@keyframes cea-pop { from { transform:translateY(20px) scale(.96); opacity:0 } to { transform:none; opacity:1 } }
.cea-x { position:absolute; top:12px; right:14px; background:rgba(255,255,255,.2); border:none; color:#fff; font-size:16px; cursor:pointer; width:28px; height:28px; border-radius:50%; }
.cea-hero { background:linear-gradient(135deg,#1b4332,#40916c); color:#fff; padding:26px 24px 22px; text-align:center; border-radius:14px 14px 0 0; }
.cea-svg { width:120px; height:80px; }
.cea-card { fill:rgba(255,255,255,.14); stroke:rgba(255,255,255,.5); stroke-width:1.5; }
.cea-c1 { animation:cea-rise .6s ease both; } .cea-c2 { animation:cea-rise .6s .15s ease both; }
@keyframes cea-rise { from { transform:translateY(8px); opacity:0 } to { transform:none; opacity:1 } }
.cea-head { fill:rgba(255,255,255,.85); } .cea-line { fill:rgba(255,255,255,.55); }
.cea-check { stroke:#9ef0c0; stroke-width:3; stroke-linecap:round; stroke-linejoin:round; stroke-dasharray:30; stroke-dashoffset:30; animation:cea-draw .6s .5s ease forwards; }
@keyframes cea-draw { to { stroke-dashoffset:0 } }
.cea-hero h2 { margin:10px 0 4px; font-size:20px; } .cea-hero p { margin:0; font-size:13px; opacity:.9; }
.cea-body { padding:18px 22px 24px; }
.cea-step { display:flex; gap:12px; padding:10px 0; border-bottom:1px solid #f1f5f9; animation:cea-slide .4s ease both; }
@keyframes cea-slide { from { transform:translateX(-10px); opacity:0 } to { transform:none; opacity:1 } }
.cea-num { flex-shrink:0; width:26px; height:26px; border-radius:50%; background:#1b4332; color:#fff; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:700; }
.cea-step b { font-size:14px; color:#1e293b; } .cea-step p { margin:3px 0 0; font-size:13px; color:#64748b; }
</style>
