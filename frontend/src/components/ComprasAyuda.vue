<!-- ComprasAyuda.vue -->
<template>
  <Teleport to="body"><transition name="cp-fade" appear><div class="cp-ov" @click.self="$emit('close')">
    <transition name="cp-pop" appear><div class="cp-md">
      <div class="cp-hero"><div class="cp-hero-ico">🛒</div><div class="cp-hero-tx"><h3>Ayuda — Consultar Compras</h3><p>Compras de RRHH (Gestión)</p></div><button class="cp-x" @click="$emit('close')">✕</button></div>
      <div class="cp-body">
        <p class="cp-intro">Muestra las <b>compras de RRHH</b> traídas del sistema de Gestión. Las que tienen <b>documentación digital</b> se resaltan en <b style="color:#ca8a04">amarillo</b>.</p>
        <div class="cp-pasos"><div class="cp-pasos-t">⚡ Cómo se usa</div><ol>
          <li v-for="(p,i) in pasos" :key="i" :style="{ animationDelay:(i*90+120)+'ms' }"><span class="cp-num">{{ i+1 }}</span><span v-html="p"></span></li>
        </ol></div>
        <ul class="cp-notas">
          <li><b>Amarillo</b> = la compra tiene documentación digital adjunta.</li>
          <li><b>Naranja</b> = la compra está pendiente de autorización.</li>
          <li>Podés filtrar por <b>mes/año</b>, por <b>proveedor</b> o por <b>número de compra</b>.</li>
        </ul>
      </div>
    </div></transition>
  </div></transition></Teleport>
</template>
<script setup lang="ts">
defineEmits<{ close: [] }>()
const pasos = ['Filtrá y tocá <b>Consultar</b>.','Hacé clic en una compra para seleccionarla.','<b>Visualizar Compra</b> muestra el detalle (importes, IVA, retenciones…).','<b>Visualizar Documentación</b> abre el archivo digital (si tiene).']
</script>
<style scoped>
.cp-ov { position:fixed; inset:0; background:rgba(15,23,42,.55); backdrop-filter:blur(3px); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.cp-md { width:min(480px,96vw); background:#fff; border-radius:16px; overflow:hidden; box-shadow:0 24px 60px rgba(0,0,0,.45); font-family:var(--font-sans,sans-serif); }
.cp-fade-enter-active,.cp-fade-leave-active{ transition:opacity .25s ease; } .cp-fade-enter-from,.cp-fade-leave-to{ opacity:0; }
.cp-pop-enter-active{ transition:transform .38s cubic-bezier(.2,.9,.3,1.2),opacity .3s ease; } .cp-pop-enter-from{ transform:translateY(30px) scale(.94); opacity:0; }
.cp-hero { display:flex; align-items:center; gap:12px; padding:14px 18px; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; }
.cp-hero-ico { font-size:26px; animation:cp-lat 2.4s ease-in-out infinite; } @keyframes cp-lat{0%,100%{transform:scale(1)}50%{transform:scale(1.12)}}
.cp-hero-tx h3 { margin:0; font-size:15px; } .cp-hero-tx p { margin:2px 0 0; font-size:11px; opacity:.85; }
.cp-x { margin-left:auto; background:rgba(255,255,255,.15); border:none; color:#fff; width:28px; height:28px; border-radius:8px; font-size:14px; cursor:pointer; } .cp-x:hover{ background:rgba(255,255,255,.3); }
.cp-body { padding:18px 22px; } .cp-intro { font-size:14px; color:#374151; line-height:1.55; margin:0 0 14px; }
.cp-pasos { background:#f8fafc; border:1px solid #eef2f7; border-radius:12px; padding:12px 16px; margin-bottom:12px; }
.cp-pasos-t { font-size:12px; font-weight:700; color:#1b4332; text-transform:uppercase; letter-spacing:.5px; margin-bottom:10px; }
.cp-pasos ol { margin:0; padding:0; list-style:none; }
.cp-pasos li { display:flex; align-items:flex-start; gap:10px; font-size:13px; color:#374151; line-height:1.5; margin-bottom:8px; opacity:0; animation:cp-pasoIn .4s ease forwards; }
.cp-num { flex-shrink:0; width:22px; height:22px; border-radius:50%; background:#16a34a; color:#fff; font-size:12px; font-weight:700; display:flex; align-items:center; justify-content:center; }
@keyframes cp-pasoIn{ from{opacity:0;transform:translateX(-8px)} to{opacity:1;transform:none} }
.cp-notas { margin:0; padding-left:6px; list-style:none; }
.cp-notas li { font-size:13px; color:#475569; line-height:1.55; margin-bottom:6px; padding-left:24px; position:relative; } .cp-notas li::before { content:'💡'; position:absolute; left:0; }
</style>
