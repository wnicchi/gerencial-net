<!-- AccesosEmpresaAyuda.vue — Modal de ayuda del módulo Listado de Accesos a la Empresa. -->
<template>
  <div class="aea-ov" @click.self="$emit('close')">
    <div class="aea-modal">
      <button class="aea-x" @click="$emit('close')">✕</button>
      <div class="aea-hero">
        <svg viewBox="0 0 120 80" class="aea-svg">
          <rect x="40" y="22" width="40" height="46" rx="4" class="aea-door" />
          <circle cx="71" cy="46" r="2.6" class="aea-knob" />
          <circle cx="30" cy="30" r="9" class="aea-chk-bg" />
          <path d="M25 30 l4 4 l8 -9" class="aea-chk" fill="none" />
          <circle cx="92" cy="52" r="9" class="aea-x-bg" />
          <path d="M88 48 l8 8 M96 48 l-8 8" class="aea-xm" fill="none" />
        </svg>
        <h2>Listado de Accesos a la Empresa</h2>
        <p>Quién puede ingresar y quién no</p>
      </div>
      <div class="aea-body">
        <div class="aea-step" v-for="(s, i) in pasos" :key="i" :style="{ animationDelay: (i * 90) + 'ms' }">
          <span class="aea-num">{{ i + 1 }}</span>
          <div><b>{{ s.t }}</b><p>{{ s.d }}</p></div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
defineEmits(['close'])
const pasos = [
  { t: 'Elegí el alcance', d: '“Todos” lista los empleados de todos los contratistas activos; “Uno” te deja elegir un contratista puntual.' },
  { t: 'Elegí la salida', d: 'Visualizar abre el listado en pantalla (PDF), Imprimir lo manda a la impresora, y Exportar a Excel genera la planilla.' },
  { t: 'Generá el listado', d: 'El botón cambia según la salida elegida (VISUALIZAR / IMPRIMIR / EXPORTAR A EXCEL).' },
  { t: 'Cómo se decide el acceso', d: 'Un empleado figura PERMITIDO solo si tiene el acceso habilitado Y su contratista está al día con las exigencias legales. Si el contratista tiene obligaciones vencidas o sin presentar, todos sus empleados quedan DENEGADOS.' },
]
</script>

<style scoped>
.aea-ov { position:fixed; inset:0; background:rgba(15,23,42,.55); display:flex; align-items:center; justify-content:center; z-index:1000; animation:aea-fade .2s ease; }
@keyframes aea-fade { from { opacity:0 } to { opacity:1 } }
.aea-modal { background:#fff; width:520px; max-width:94vw; max-height:90vh; overflow:auto; border-radius:14px; position:relative; box-shadow:0 20px 60px rgba(0,0,0,.3); animation:aea-pop .25s cubic-bezier(.2,.9,.3,1.2); }
@keyframes aea-pop { from { transform:translateY(20px) scale(.96); opacity:0 } to { transform:none; opacity:1 } }
.aea-x { position:absolute; top:12px; right:14px; background:rgba(255,255,255,.2); border:none; color:#fff; font-size:16px; cursor:pointer; width:28px; height:28px; border-radius:50%; }
.aea-hero { background:linear-gradient(135deg,#1b4332,#40916c); color:#fff; padding:26px 24px 22px; text-align:center; border-radius:14px 14px 0 0; }
.aea-svg { width:120px; height:80px; }
.aea-door { fill:rgba(255,255,255,.14); stroke:rgba(255,255,255,.6); stroke-width:1.5; animation:aea-rise .6s ease both; }
@keyframes aea-rise { from { transform:translateY(8px); opacity:0 } to { transform:none; opacity:1 } }
.aea-knob { fill:rgba(255,255,255,.9); }
.aea-chk-bg { fill:#16a34a; } .aea-x-bg { fill:#dc2626; }
.aea-chk { stroke:#fff; stroke-width:2.4; stroke-linecap:round; stroke-linejoin:round; stroke-dasharray:20; stroke-dashoffset:20; animation:aea-draw .5s .4s ease forwards; }
.aea-xm { stroke:#fff; stroke-width:2.4; stroke-linecap:round; stroke-dasharray:24; stroke-dashoffset:24; animation:aea-draw .5s .7s ease forwards; }
@keyframes aea-draw { to { stroke-dashoffset:0 } }
.aea-hero h2 { margin:10px 0 4px; font-size:19px; } .aea-hero p { margin:0; font-size:13px; opacity:.9; }
.aea-body { padding:18px 22px 24px; }
.aea-step { display:flex; gap:12px; padding:10px 0; border-bottom:1px solid #f1f5f9; animation:aea-slide .4s ease both; }
@keyframes aea-slide { from { transform:translateX(-10px); opacity:0 } to { transform:none; opacity:1 } }
.aea-num { flex-shrink:0; width:26px; height:26px; border-radius:50%; background:#1b4332; color:#fff; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:700; }
.aea-step b { font-size:14px; color:#1e293b; } .aea-step p { margin:3px 0 0; font-size:13px; color:#64748b; }
</style>
