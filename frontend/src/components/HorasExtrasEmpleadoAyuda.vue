<!-- HorasExtrasEmpleadoAyuda.vue — Ayuda del módulo Horas Extras por Empleado. -->
<template>
  <Teleport to="body"><transition name="he-fade" appear><div class="he-ov" @click.self="$emit('close')">
    <transition name="he-pop" appear><div class="he-md">
      <div class="he-hero">
        <div class="he-hero-ico">🔍</div>
        <div class="he-hero-tx"><h3>Ayuda — Horas Extras por Empleado</h3><p>Detalle diario de horas extras en un rango</p></div>
        <button class="he-x" @click="$emit('close')">✕</button>
      </div>
      <div class="he-body">
        <p class="he-intro">Muestra, para <b>un empleado</b> y un <b>rango de fechas</b>, el detalle diario de sus horas extras: entrada, salida, tiempo extra calculado y lo cargado al 50%, 100% y nocturna.</p>
        <div class="he-pasos"><div class="he-pasos-t">⚡ Cómo se usa</div><ol>
          <li v-for="(p,i) in pasos" :key="i" :style="{ animationDelay:(i*90+150)+'ms' }"><span class="he-num">{{ i+1 }}</span><span v-html="p"></span></li>
        </ol></div>
        <ul class="he-notas">
          <li>Solo aparecen los días con <b>más de 25 minutos</b> de extra o con horas extra ya cargadas.</li>
          <li>Es una <b>consulta de solo lectura</b>: no se modifica nada.</li>
          <li>Al pie se muestra el <b>total de horas extras</b> del rango.</li>
        </ul>
      </div>
    </div></transition>
  </div></transition></Teleport>
</template>
<script setup lang="ts">
defineEmits<{ close: [] }>()
const pasos = [
  '<b>Ingresá el Nro. de Personal</b> del empleado.',
  '<b>Elegí el rango</b> Desde / al.',
  '<b>ACEPTAR:</b> se muestra el detalle diario y el total.',
]
</script>
<style scoped>
.he-ov { position:fixed; inset:0; background:rgba(15,23,42,.55); backdrop-filter:blur(3px); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.he-md { width:min(470px,96vw); max-height:92vh; overflow:auto; background:#fff; border-radius:16px; box-shadow:0 24px 60px rgba(0,0,0,.45); font-family:var(--font-sans,sans-serif); }
.he-fade-enter-active,.he-fade-leave-active{ transition:opacity .25s ease; } .he-fade-enter-from,.he-fade-leave-to{ opacity:0; }
.he-pop-enter-active{ transition:transform .38s cubic-bezier(.2,.9,.3,1.2),opacity .3s ease; } .he-pop-enter-from{ transform:translateY(30px) scale(.94); opacity:0; }
.he-hero { display:flex; align-items:center; gap:12px; padding:14px 18px; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; position:sticky; top:0; }
.he-hero-ico { font-size:26px; animation:he-lat 2.4s ease-in-out infinite; } @keyframes he-lat{0%,100%{transform:scale(1)}50%{transform:scale(1.12)}}
.he-hero-tx h3 { margin:0; font-size:15px; } .he-hero-tx p { margin:2px 0 0; font-size:11px; opacity:.85; }
.he-x { margin-left:auto; background:rgba(255,255,255,.15); border:none; color:#fff; width:28px; height:28px; border-radius:8px; font-size:14px; cursor:pointer; } .he-x:hover{ background:rgba(255,255,255,.3); }
.he-body { padding:18px 22px; } .he-intro { font-size:14px; color:#374151; line-height:1.55; margin:0 0 14px; }
.he-pasos { background:#f8fafc; border:1px solid #eef2f7; border-radius:12px; padding:12px 16px; margin-bottom:12px; }
.he-pasos-t { font-size:12px; font-weight:700; color:#1b4332; text-transform:uppercase; letter-spacing:.5px; margin-bottom:10px; }
.he-pasos ol { margin:0; padding:0; list-style:none; }
.he-pasos li { display:flex; align-items:flex-start; gap:10px; font-size:13px; color:#374151; line-height:1.5; margin-bottom:8px; opacity:0; animation:he-pasoIn .4s ease forwards; }
.he-num { flex-shrink:0; width:22px; height:22px; border-radius:50%; background:#16a34a; color:#fff; font-size:12px; font-weight:700; display:flex; align-items:center; justify-content:center; }
@keyframes he-pasoIn{ from{opacity:0;transform:translateX(-8px)} to{opacity:1;transform:none} }
.he-notas { margin:0; padding-left:6px; list-style:none; }
.he-notas li { font-size:13px; color:#475569; line-height:1.55; margin-bottom:6px; padding-left:24px; position:relative; } .he-notas li::before { content:'💡'; position:absolute; left:0; }
</style>
