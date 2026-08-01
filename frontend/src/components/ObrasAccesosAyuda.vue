<!-- ObrasAccesosAyuda.vue — Ayuda del módulo Obras - Habilitados a Ingresar. -->
<template>
  <div class="oaa-ov" @click.self="$emit('close')">
    <div class="oaa-modal">
      <button class="oaa-x" @click="$emit('close')">✕</button>
      <div class="oaa-hero">
        <svg viewBox="0 0 120 80" class="oaa-svg">
          <rect x="40" y="22" width="40" height="46" rx="4" class="oaa-door" />
          <circle cx="71" cy="46" r="2.6" class="oaa-knob" />
          <circle cx="30" cy="30" r="9" class="oaa-chk-bg" />
          <path d="M25 30 l4 4 l8 -9" class="oaa-chk" fill="none" />
          <circle cx="92" cy="52" r="9" class="oaa-x-bg" />
          <path d="M88 48 l8 8 M96 48 l-8 8" class="oaa-xm" fill="none" />
        </svg>
        <h2>Obras - Habilitados a Ingresar</h2>
        <p>Quién puede ingresar según las obras</p>
      </div>
      <div class="oaa-body">
        <div class="oaa-step" v-for="(s, i) in pasos" :key="i" :style="{ animationDelay: (i * 90) + 'ms' }">
          <span class="oaa-num">{{ i + 1 }}</span>
          <div><b>{{ s.t }}</b><p>{{ s.d }}</p></div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
defineEmits(['close'])
const pasos = [
  { t: 'Elegí la salida', d: 'Visualizar abre el listado en pantalla (PDF), Imprimir lo manda a la impresora, y Exportar a Excel genera la planilla.' },
  { t: 'Generá', d: 'El botón cambia según la salida elegida (VISUALIZAR / IMPRIMIR / EXPORTAR A EXCEL).' },
  { t: 'Cómo se decide el acceso', d: 'Un empleado figura PERMITIDO si participa en al menos una obra vigente (ya empezó y no finalizó). Si no, queda DENEGADO.' },
]
</script>

<style scoped>
.oaa-ov { position:fixed; inset:0; background:rgba(15,23,42,.55); display:flex; align-items:center; justify-content:center; z-index:1000; animation:oaa-fade .2s ease; }
@keyframes oaa-fade { from { opacity:0 } to { opacity:1 } }
.oaa-modal { background:#fff; width:520px; max-width:94vw; max-height:90vh; overflow:auto; border-radius:14px; position:relative; box-shadow:0 20px 60px rgba(0,0,0,.3); animation:oaa-pop .25s cubic-bezier(.2,.9,.3,1.2); }
@keyframes oaa-pop { from { transform:translateY(20px) scale(.96); opacity:0 } to { transform:none; opacity:1 } }
.oaa-x { position:absolute; top:12px; right:14px; background:rgba(255,255,255,.2); border:none; color:#fff; font-size:16px; cursor:pointer; width:28px; height:28px; border-radius:50%; }
.oaa-hero { background:linear-gradient(135deg,#1b4332,#40916c); color:#fff; padding:26px 24px 22px; text-align:center; border-radius:14px 14px 0 0; }
.oaa-svg { width:120px; height:80px; }
.oaa-door { fill:rgba(255,255,255,.14); stroke:rgba(255,255,255,.6); stroke-width:1.5; animation:oaa-rise .6s ease both; }
@keyframes oaa-rise { from { transform:translateY(8px); opacity:0 } to { transform:none; opacity:1 } }
.oaa-knob { fill:rgba(255,255,255,.9); }
.oaa-chk-bg { fill:#16a34a; } .oaa-x-bg { fill:#dc2626; }
.oaa-chk { stroke:#fff; stroke-width:2.4; stroke-linecap:round; stroke-linejoin:round; stroke-dasharray:20; stroke-dashoffset:20; animation:oaa-draw .5s .4s ease forwards; }
.oaa-xm { stroke:#fff; stroke-width:2.4; stroke-linecap:round; stroke-dasharray:24; stroke-dashoffset:24; animation:oaa-draw .5s .7s ease forwards; }
@keyframes oaa-draw { to { stroke-dashoffset:0 } }
.oaa-hero h2 { margin:10px 0 4px; font-size:18px; } .oaa-hero p { margin:0; font-size:13px; opacity:.9; }
.oaa-body { padding:18px 22px 24px; }
.oaa-step { display:flex; gap:12px; padding:10px 0; border-bottom:1px solid #f1f5f9; animation:oaa-slide .4s ease both; }
@keyframes oaa-slide { from { transform:translateX(-10px); opacity:0 } to { transform:none; opacity:1 } }
.oaa-num { flex-shrink:0; width:26px; height:26px; border-radius:50%; background:#1b4332; color:#fff; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:700; }
.oaa-step b { font-size:14px; color:#1e293b; } .oaa-step p { margin:3px 0 0; font-size:13px; color:#64748b; }
</style>
