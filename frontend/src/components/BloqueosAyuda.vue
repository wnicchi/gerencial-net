<!-- BloqueosAyuda.vue -->
<template>
  <Teleport to="body"><transition name="bq-fade" appear><div class="bq-ov" @click.self="$emit('close')">
    <transition name="bq-pop" appear><div class="bq-md">
      <div class="bq-hero"><div class="bq-hero-ico">🔒</div><div class="bq-hero-tx"><h3>Ayuda — Bloqueos Varios</h3><p>Bloquear o liberar un período</p></div><button class="bq-x" @click="$emit('close')">✕</button></div>
      <div class="bq-body">
        <p class="bq-intro">Permite <b>bloquear</b> (o <b>liberar</b>) la carga de un período, para impedir cambios sobre un mes ya cerrado.</p>
        <div class="bq-pasos"><div class="bq-pasos-t">⚡ Cómo se usa</div><ol>
          <li v-for="(p,i) in pasos" :key="i" :style="{ animationDelay:(i*90+120)+'ms' }"><span class="bq-num">{{ i+1 }}</span><span v-html="p"></span></li>
        </ol></div>
        <ul class="bq-notas">
          <li><b>Novedades</b> y <b>Horas Extras</b> se bloquean por separado.</li>
          <li>El estado del período elegido se muestra solo al cambiar tipo, mes o año.</li>
          <li>Tildá o destildá <b>Bloqueado</b> y tocá <b>Grabar</b> para aplicar.</li>
        </ul>
      </div>
    </div></transition>
  </div></transition></Teleport>
</template>
<script setup lang="ts">
defineEmits<{ close: [] }>()
const pasos = ['Elegí el <b>tipo</b> (Novedades u Horas Extras).','Indicá <b>mes</b> y <b>año</b>.','Marcá/desmarcá <b>Bloqueado</b> y tocá <b>Grabar</b>.']
</script>
<style scoped>
.bq-ov { position:fixed; inset:0; background:rgba(15,23,42,.55); backdrop-filter:blur(3px); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.bq-md { width:min(460px,96vw); background:#fff; border-radius:16px; overflow:hidden; box-shadow:0 24px 60px rgba(0,0,0,.45); font-family:var(--font-sans,sans-serif); }
.bq-fade-enter-active,.bq-fade-leave-active{ transition:opacity .25s ease; } .bq-fade-enter-from,.bq-fade-leave-to{ opacity:0; }
.bq-pop-enter-active{ transition:transform .38s cubic-bezier(.2,.9,.3,1.2),opacity .3s ease; } .bq-pop-enter-from{ transform:translateY(30px) scale(.94); opacity:0; }
.bq-hero { display:flex; align-items:center; gap:12px; padding:14px 18px; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; }
.bq-hero-ico { font-size:26px; animation:bq-lat 2.4s ease-in-out infinite; } @keyframes bq-lat{0%,100%{transform:scale(1)}50%{transform:scale(1.12)}}
.bq-hero-tx h3 { margin:0; font-size:15px; } .bq-hero-tx p { margin:2px 0 0; font-size:11px; opacity:.85; }
.bq-x { margin-left:auto; background:rgba(255,255,255,.15); border:none; color:#fff; width:28px; height:28px; border-radius:8px; font-size:14px; cursor:pointer; } .bq-x:hover{ background:rgba(255,255,255,.3); }
.bq-body { padding:18px 22px; } .bq-intro { font-size:14px; color:#374151; line-height:1.55; margin:0 0 14px; }
.bq-pasos { background:#f8fafc; border:1px solid #eef2f7; border-radius:12px; padding:12px 16px; margin-bottom:12px; }
.bq-pasos-t { font-size:12px; font-weight:700; color:#1b4332; text-transform:uppercase; letter-spacing:.5px; margin-bottom:10px; }
.bq-pasos ol { margin:0; padding:0; list-style:none; }
.bq-pasos li { display:flex; align-items:flex-start; gap:10px; font-size:13px; color:#374151; line-height:1.5; margin-bottom:8px; opacity:0; animation:bq-pasoIn .4s ease forwards; }
.bq-num { flex-shrink:0; width:22px; height:22px; border-radius:50%; background:#16a34a; color:#fff; font-size:12px; font-weight:700; display:flex; align-items:center; justify-content:center; }
@keyframes bq-pasoIn{ from{opacity:0;transform:translateX(-8px)} to{opacity:1;transform:none} }
.bq-notas { margin:0; padding-left:6px; list-style:none; }
.bq-notas li { font-size:13px; color:#475569; line-height:1.55; margin-bottom:6px; padding-left:24px; position:relative; } .bq-notas li::before { content:'💡'; position:absolute; left:0; }
</style>
