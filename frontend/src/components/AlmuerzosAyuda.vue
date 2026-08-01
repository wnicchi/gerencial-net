<!-- AlmuerzosAyuda.vue — Ayuda del módulo Almuerzos del Personal. -->
<template>
  <Teleport to="body"><transition name="al-fade" appear><div class="al-ov" @click.self="$emit('close')">
    <transition name="al-pop" appear><div class="al-md">
      <div class="al-hero">
        <div class="al-hero-ico">🍽️</div>
        <div class="al-hero-tx"><h3>Ayuda — Almuerzos del Personal</h3><p>Carga diaria de comensales por comedor</p></div>
        <button class="al-x" @click="$emit('close')">✕</button>
      </div>
      <div class="al-body">
        <p class="al-intro">Registra los <b>almuerzos de un comedor</b> para una fecha: aparecen los empleados de ese comedor que almuerzan, más los invitados.</p>
        <div class="al-pasos"><div class="al-pasos-t">⚡ Cómo se usa</div><ol>
          <li v-for="(p,i) in pasos" :key="i" :style="{ animationDelay:(i*90+150)+'ms' }"><span class="al-num">{{ i+1 }}</span><span v-html="p"></span></li>
        </ol></div>
        <ul class="al-notas">
          <li>Filas <b style="background:#86efac;padding:0 4px;border-radius:3px">verdes</b> = invitados; <b style="background:#fff7ae;padding:0 4px;border-radius:3px">amarillas</b> = con cantidad cargada.</li>
          <li>Quien está de <b>vacaciones o licencia</b> ese día no aparece en la lista.</li>
          <li><b>Almuerza</b> indica si come ese día; <b>Descuenta</b> si se le descuenta el almuerzo.</li>
          <li><b>Todos Almuerzan / Todos Descuentan</b> marcan o desmarcan toda la lista.</li>
          <li>Si el día ya fue cargado, se trae lo editado para seguir ajustando.</li>
          <li><b>Imprimir</b> genera el listado de almuerzos del día con el total.</li>
        </ul>
      </div>
    </div></transition>
  </div></transition></Teleport>
</template>
<script setup lang="ts">
defineEmits<{ close: [] }>()
const pasos = [
  '<b>Elegí el comedor y la fecha</b> del almuerzo.',
  '<b>ACEPTAR:</b> se arma la lista de comensales.',
  '<b>Marcá Almuerza/Descuenta</b>, ajustá cantidad y observaciones.',
  '<b>CONFIRMAR:</b> se guardan los almuerzos del día.',
]
</script>
<style scoped>
.al-ov { position:fixed; inset:0; background:rgba(15,23,42,.55); backdrop-filter:blur(3px); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.al-md { width:min(480px,96vw); max-height:92vh; overflow:auto; background:#fff; border-radius:16px; box-shadow:0 24px 60px rgba(0,0,0,.45); font-family:var(--font-sans,sans-serif); }
.al-fade-enter-active,.al-fade-leave-active{ transition:opacity .25s ease; } .al-fade-enter-from,.al-fade-leave-to{ opacity:0; }
.al-pop-enter-active{ transition:transform .38s cubic-bezier(.2,.9,.3,1.2),opacity .3s ease; } .al-pop-enter-from{ transform:translateY(30px) scale(.94); opacity:0; }
.al-hero { display:flex; align-items:center; gap:12px; padding:14px 18px; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; position:sticky; top:0; }
.al-hero-ico { font-size:26px; animation:al-lat 2.4s ease-in-out infinite; } @keyframes al-lat{0%,100%{transform:scale(1)}50%{transform:scale(1.12)}}
.al-hero-tx h3 { margin:0; font-size:15px; } .al-hero-tx p { margin:2px 0 0; font-size:11px; opacity:.85; }
.al-x { margin-left:auto; background:rgba(255,255,255,.15); border:none; color:#fff; width:28px; height:28px; border-radius:8px; font-size:14px; cursor:pointer; }
.al-body { padding:18px 22px; } .al-intro { font-size:14px; color:#374151; line-height:1.55; margin:0 0 14px; }
.al-pasos { background:#f8fafc; border:1px solid #eef2f7; border-radius:12px; padding:12px 16px; margin-bottom:12px; }
.al-pasos-t { font-size:12px; font-weight:700; color:#1b4332; text-transform:uppercase; letter-spacing:.5px; margin-bottom:10px; }
.al-pasos ol { margin:0; padding:0; list-style:none; }
.al-pasos li { display:flex; align-items:flex-start; gap:10px; font-size:13px; color:#374151; line-height:1.5; margin-bottom:8px; opacity:0; animation:al-pasoIn .4s ease forwards; }
.al-num { flex-shrink:0; width:22px; height:22px; border-radius:50%; background:#16a34a; color:#fff; font-size:12px; font-weight:700; display:flex; align-items:center; justify-content:center; }
@keyframes al-pasoIn{ from{opacity:0;transform:translateX(-8px)} to{opacity:1;transform:none} }
.al-notas { margin:0; padding-left:6px; list-style:none; }
.al-notas li { font-size:13px; color:#475569; line-height:1.55; margin-bottom:6px; padding-left:24px; position:relative; } .al-notas li::before { content:'💡'; position:absolute; left:0; }
</style>
