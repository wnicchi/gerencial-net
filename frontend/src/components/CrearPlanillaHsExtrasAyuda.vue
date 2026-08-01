<!-- CrearPlanillaHsExtrasAyuda.vue — Ayuda de Planillas de Horas Extras (crear/editar). -->
<template>
  <Teleport to="body"><transition name="pe-fade" appear><div class="pe-ov" @click.self="$emit('close')">
    <transition name="pe-pop" appear><div class="pe-md">
      <div class="pe-hero">
        <div class="pe-hero-ico">📋</div>
        <div class="pe-hero-tx"><h3>Ayuda — Planillas de Horas Extras</h3><p>Crear y editar planillas mensuales</p></div>
        <button class="pe-x" @click="$emit('close')">✕</button>
      </div>
      <div class="pe-body">
        <p class="pe-intro">Las <b>planillas de horas extras</b> totalizan, por período, las horas extras diarias ya confirmadas de cada empleado y calculan los importes a liquidar.</p>
        <div class="pe-pasos"><div class="pe-pasos-t">⚡ Crear una planilla</div><ol>
          <li v-for="(p,i) in pasos" :key="i" :style="{ animationDelay:(i*90+150)+'ms' }"><span class="pe-num">{{ i+1 }}</span><span v-html="p"></span></li>
        </ol></div>
        <ul class="pe-notas">
          <li>Solo aparecen los empleados que <b>fichan</b> (marcan reloj).</li>
          <li>Las <b>horas</b> se toman de las horas extras diarias confirmadas; el <b>día 30%</b> sale de los viajes.</li>
          <li>Los <b>valores</b> se calculan a partir del sueldo y el <b>total</b> es el neto del convenio.</li>
          <li>Para <b>editar</b>: buscá la planilla por su número, ajustá horas o valores (se recalcula todo y las celdas modificadas se resaltan) y guardá.</li>
          <li>Se puede <b>exportar a Excel</b> en cualquier momento.</li>
        </ul>
      </div>
    </div></transition>
  </div></transition></Teleport>
</template>
<script setup lang="ts">
defineEmits<{ close: [] }>()
const pasos = [
  '<b>Filtrá</b> por empresa, contratista, lugar, convenio, etc. y elegí el orden.',
  '<b>TRAER EMPLEADOS:</b> aparece la lista; tildá los que entran (todos/nada).',
  '<b>Elegí el período</b> Desde / al y poné un <b>nombre</b> de planilla.',
  '<b>GENERAR EXCEL:</b> se totaliza, se exporta y se ofrece crear la planilla.',
]
</script>
<style scoped>
.pe-ov { position:fixed; inset:0; background:rgba(15,23,42,.55); backdrop-filter:blur(3px); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.pe-md { width:min(480px,96vw); max-height:92vh; overflow:auto; background:#fff; border-radius:16px; box-shadow:0 24px 60px rgba(0,0,0,.45); font-family:var(--font-sans,sans-serif); }
.pe-fade-enter-active,.pe-fade-leave-active{ transition:opacity .25s ease; } .pe-fade-enter-from,.pe-fade-leave-to{ opacity:0; }
.pe-pop-enter-active{ transition:transform .38s cubic-bezier(.2,.9,.3,1.2),opacity .3s ease; } .pe-pop-enter-from{ transform:translateY(30px) scale(.94); opacity:0; }
.pe-hero { display:flex; align-items:center; gap:12px; padding:14px 18px; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; position:sticky; top:0; }
.pe-hero-ico { font-size:26px; animation:pe-lat 2.4s ease-in-out infinite; } @keyframes pe-lat{0%,100%{transform:scale(1)}50%{transform:scale(1.12)}}
.pe-hero-tx h3 { margin:0; font-size:15px; } .pe-hero-tx p { margin:2px 0 0; font-size:11px; opacity:.85; }
.pe-x { margin-left:auto; background:rgba(255,255,255,.15); border:none; color:#fff; width:28px; height:28px; border-radius:8px; font-size:14px; cursor:pointer; }
.pe-body { padding:18px 22px; } .pe-intro { font-size:14px; color:#374151; line-height:1.55; margin:0 0 14px; }
.pe-pasos { background:#f8fafc; border:1px solid #eef2f7; border-radius:12px; padding:12px 16px; margin-bottom:12px; }
.pe-pasos-t { font-size:12px; font-weight:700; color:#1b4332; text-transform:uppercase; letter-spacing:.5px; margin-bottom:10px; }
.pe-pasos ol { margin:0; padding:0; list-style:none; }
.pe-pasos li { display:flex; align-items:flex-start; gap:10px; font-size:13px; color:#374151; line-height:1.5; margin-bottom:8px; opacity:0; animation:pe-pasoIn .4s ease forwards; }
.pe-num { flex-shrink:0; width:22px; height:22px; border-radius:50%; background:#16a34a; color:#fff; font-size:12px; font-weight:700; display:flex; align-items:center; justify-content:center; }
@keyframes pe-pasoIn{ from{opacity:0;transform:translateX(-8px)} to{opacity:1;transform:none} }
.pe-notas { margin:0; padding-left:6px; list-style:none; }
.pe-notas li { font-size:13px; color:#475569; line-height:1.55; margin-bottom:6px; padding-left:24px; position:relative; } .pe-notas li::before { content:'💡'; position:absolute; left:0; }
</style>
