<!-- HorasExtrasDiariasAyuda.vue — Ayuda del módulo Horas Extras Edición Diaria. -->
<template>
  <Teleport to="body"><transition name="hx-fade" appear><div class="hx-ov" @click.self="$emit('close')">
    <transition name="hx-pop" appear><div class="hx-md">
      <div class="hx-hero">
        <div class="hx-hero-ico">⏰</div>
        <div class="hx-hero-tx"><h3>Ayuda — Horas Extras Diarias</h3><p>Cálculo y carga de horas extras y nocturnas por día</p></div>
        <button class="hx-x" @click="$emit('close')">✕</button>
      </div>
      <div class="hx-body">
        <p class="hx-intro">Para un <b>día</b>, analiza las marcaciones de cada empleado, calcula el <b>tiempo extra</b> (lo que excede las horas normales del convenio para ese día) y las <b>horas nocturnas</b> (entre las 21:00 y las 03:00).</p>
        <div class="hx-pasos"><div class="hx-pasos-t">⚡ Cómo se usa</div><ol>
          <li v-for="(p,i) in pasos" :key="i" :style="{ animationDelay:(i*90+150)+'ms' }"><span class="hx-num">{{ i+1 }}</span><span v-html="p"></span></li>
        </ol></div>
        <ul class="hx-notas">
          <li>Solo aparecen los empleados con <b>más de 25 minutos</b> de extra o con horas nocturnas.</li>
          <li>El <b>Tiempo Extra</b> calculado es de referencia: vos lo repartís entre <b>Extra 50%</b> y <b>Extra 100%</b> (en formato HH:MM).</li>
          <li>La <b>Adicional Nocturna</b> viene pre-cargada y se puede ajustar.</li>
          <li>Las celdas que <b style="background:#fff200;padding:0 4px;border-radius:3px">modificás</b> quedan resaltadas; el personal de convenio también aparece destacado.</li>
          <li>Si el día es <b>viernes</b>, se muestra un aviso.</li>
          <li><b>CONFIRMAR</b> graba las horas extras de ese día.</li>
        </ul>
      </div>
    </div></transition>
  </div></transition></Teleport>
</template>
<script setup lang="ts">
defineEmits<{ close: [] }>()
const pasos = [
  '<b>Elegí contratista y día</b> a editar.',
  '<b>ACEPTAR:</b> se arma la planilla con las horas extra calculadas.',
  '<b>Cargá el 50% y el 100%</b> (HH:MM) y ajustá la nocturna si hace falta.',
  '<b>CONFIRMAR:</b> se guardan las horas extra del día.',
]
</script>
<style scoped>
.hx-ov { position:fixed; inset:0; background:rgba(15,23,42,.55); backdrop-filter:blur(3px); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.hx-md { width:min(480px,96vw); max-height:92vh; overflow:auto; background:#fff; border-radius:16px; box-shadow:0 24px 60px rgba(0,0,0,.45); font-family:var(--font-sans,sans-serif); }
.hx-fade-enter-active,.hx-fade-leave-active{ transition:opacity .25s ease; } .hx-fade-enter-from,.hx-fade-leave-to{ opacity:0; }
.hx-pop-enter-active{ transition:transform .38s cubic-bezier(.2,.9,.3,1.2),opacity .3s ease; } .hx-pop-enter-from{ transform:translateY(30px) scale(.94); opacity:0; }
.hx-hero { display:flex; align-items:center; gap:12px; padding:14px 18px; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; position:sticky; top:0; }
.hx-hero-ico { font-size:26px; animation:hx-lat 2.4s ease-in-out infinite; } @keyframes hx-lat{0%,100%{transform:scale(1)}50%{transform:scale(1.12)}}
.hx-hero-tx h3 { margin:0; font-size:15px; } .hx-hero-tx p { margin:2px 0 0; font-size:11px; opacity:.85; }
.hx-x { margin-left:auto; background:rgba(255,255,255,.15); border:none; color:#fff; width:28px; height:28px; border-radius:8px; font-size:14px; cursor:pointer; } .hx-x:hover{ background:rgba(255,255,255,.3); }
.hx-body { padding:18px 22px; } .hx-intro { font-size:14px; color:#374151; line-height:1.55; margin:0 0 14px; }
.hx-pasos { background:#f8fafc; border:1px solid #eef2f7; border-radius:12px; padding:12px 16px; margin-bottom:12px; }
.hx-pasos-t { font-size:12px; font-weight:700; color:#1b4332; text-transform:uppercase; letter-spacing:.5px; margin-bottom:10px; }
.hx-pasos ol { margin:0; padding:0; list-style:none; }
.hx-pasos li { display:flex; align-items:flex-start; gap:10px; font-size:13px; color:#374151; line-height:1.5; margin-bottom:8px; opacity:0; animation:hx-pasoIn .4s ease forwards; }
.hx-num { flex-shrink:0; width:22px; height:22px; border-radius:50%; background:#16a34a; color:#fff; font-size:12px; font-weight:700; display:flex; align-items:center; justify-content:center; }
@keyframes hx-pasoIn{ from{opacity:0;transform:translateX(-8px)} to{opacity:1;transform:none} }
.hx-notas { margin:0; padding-left:6px; list-style:none; }
.hx-notas li { font-size:13px; color:#475569; line-height:1.55; margin-bottom:6px; padding-left:24px; position:relative; } .hx-notas li::before { content:'💡'; position:absolute; left:0; }
</style>
