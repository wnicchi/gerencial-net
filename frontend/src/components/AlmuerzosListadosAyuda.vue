<!-- AlmuerzosListadosAyuda.vue — Ayuda del módulo Almuerzos Listados. -->
<template>
  <Teleport to="body"><transition name="ll-fade" appear><div class="ll-ov" @click.self="$emit('close')">
    <transition name="ll-pop" appear><div class="ll-md">
      <div class="ll-hero">
        <div class="ll-hero-ico">📊</div>
        <div class="ll-hero-tx"><h3>Ayuda — Listados de Almuerzos</h3><p>Informes de almuerzos por período o jornada</p></div>
        <button class="ll-x" @click="$emit('close')">✕</button>
      </div>
      <div class="ll-body">
        <p class="ll-intro">Genera informes de los almuerzos ya registrados, agrupados por empresa.</p>
        <div class="ll-pasos"><div class="ll-pasos-t">⚡ Cómo se usa</div><ol>
          <li v-for="(p,i) in pasos" :key="i" :style="{ animationDelay:(i*90+150)+'ms' }"><span class="ll-num">{{ i+1 }}</span><span v-html="p"></span></li>
        </ol></div>
        <ul class="ll-notas">
          <li><b>Un período</b> = rango de fechas; <b>Una jornada</b> = un solo día.</li>
          <li><b>Detallado</b> lista cada almuerzo; <b>Resumido</b> muestra el total por empleado.</li>
          <li>Podés filtrar por <b>una empresa</b> y por <b>un comedor</b>, u optar por todas/todos.</li>
          <li>Solo se cuentan los almuerzos con <b>cantidad mayor a cero</b>.</li>
          <li>El informe se ve y se imprime como <b>PDF</b>, con el total por empresa y el total general.</li>
        </ul>
      </div>
    </div></transition>
  </div></transition></Teleport>
</template>
<script setup lang="ts">
defineEmits<{ close: [] }>()
const pasos = [
  '<b>Elegí el rango:</b> un período (Desde/al) o una jornada (un día).',
  '<b>Filtrá:</b> todas o una empresa, todos o un comedor.',
  '<b>Elegí el tipo:</b> detallado o resumido, y el orden.',
  '<b>CONFIRMAR:</b> se genera el PDF para ver, imprimir o descargar.',
]
</script>
<style scoped>
.ll-ov { position:fixed; inset:0; background:rgba(15,23,42,.55); backdrop-filter:blur(3px); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.ll-md { width:min(480px,96vw); max-height:92vh; overflow:auto; background:#fff; border-radius:16px; box-shadow:0 24px 60px rgba(0,0,0,.45); font-family:var(--font-sans,sans-serif); }
.ll-fade-enter-active,.ll-fade-leave-active{ transition:opacity .25s ease; } .ll-fade-enter-from,.ll-fade-leave-to{ opacity:0; }
.ll-pop-enter-active{ transition:transform .38s cubic-bezier(.2,.9,.3,1.2),opacity .3s ease; } .ll-pop-enter-from{ transform:translateY(30px) scale(.94); opacity:0; }
.ll-hero { display:flex; align-items:center; gap:12px; padding:14px 18px; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; position:sticky; top:0; }
.ll-hero-ico { font-size:26px; animation:ll-lat 2.4s ease-in-out infinite; } @keyframes ll-lat{0%,100%{transform:scale(1)}50%{transform:scale(1.12)}}
.ll-hero-tx h3 { margin:0; font-size:15px; } .ll-hero-tx p { margin:2px 0 0; font-size:11px; opacity:.85; }
.ll-x { margin-left:auto; background:rgba(255,255,255,.15); border:none; color:#fff; width:28px; height:28px; border-radius:8px; font-size:14px; cursor:pointer; }
.ll-body { padding:18px 22px; } .ll-intro { font-size:14px; color:#374151; line-height:1.55; margin:0 0 14px; }
.ll-pasos { background:#f8fafc; border:1px solid #eef2f7; border-radius:12px; padding:12px 16px; margin-bottom:12px; }
.ll-pasos-t { font-size:12px; font-weight:700; color:#1b4332; text-transform:uppercase; letter-spacing:.5px; margin-bottom:10px; }
.ll-pasos ol { margin:0; padding:0; list-style:none; }
.ll-pasos li { display:flex; align-items:flex-start; gap:10px; font-size:13px; color:#374151; line-height:1.5; margin-bottom:8px; opacity:0; animation:ll-pasoIn .4s ease forwards; }
.ll-num { flex-shrink:0; width:22px; height:22px; border-radius:50%; background:#16a34a; color:#fff; font-size:12px; font-weight:700; display:flex; align-items:center; justify-content:center; }
@keyframes ll-pasoIn{ from{opacity:0;transform:translateX(-8px)} to{opacity:1;transform:none} }
.ll-notas { margin:0; padding-left:6px; list-style:none; }
.ll-notas li { font-size:13px; color:#475569; line-height:1.55; margin-bottom:6px; padding-left:24px; position:relative; } .ll-notas li::before { content:'💡'; position:absolute; left:0; }
</style>
