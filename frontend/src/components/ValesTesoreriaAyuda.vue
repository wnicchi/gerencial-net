<!-- ValesTesoreriaAyuda.vue — Ayuda del módulo Tesorería. -->
<template>
  <Teleport to="body"><transition name="vt-fade" appear><div class="vt-ov" @click.self="$emit('close')">
    <transition name="vt-pop" appear><div class="vt-md">
      <div class="vt-hero">
        <div class="vt-hero-ico">💰</div>
        <div class="vt-hero-tx"><h3>Ayuda — Ingresos de Tesorería</h3><p>Reposición del fondo fijo</p></div>
        <button class="vt-x" @click="$emit('close')">✕</button>
      </div>
      <div class="vt-body">
        <p class="vt-intro">Registra un <b>ingreso de dinero</b> al fondo fijo (una reposición). Suma al saldo del fondo elegido.</p>
        <ul class="vt-notas">
          <li>Completá fecha, hora, detalle, importe, el <b>fondo</b> (SILCAR o Logística) y quién autoriza.</li>
          <li>El ingreso queda registrado como <b>movimiento del fondo fijo</b> (lo ves en Consultar Fondo Fijo).</li>
          <li>A diferencia de un vale (que saca dinero), un ingreso de tesorería <b>repone</b> el fondo.</li>
        </ul>
      </div>
    </div></transition>
  </div></transition></Teleport>
</template>
<script setup lang="ts">
defineEmits<{ close: [] }>()
</script>
<style scoped>
.vt-ov { position:fixed; inset:0; background:rgba(15,23,42,.55); backdrop-filter:blur(3px); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.vt-md { width:min(460px,96vw); background:#fff; border-radius:16px; box-shadow:0 24px 60px rgba(0,0,0,.45); font-family:var(--font-sans,sans-serif); }
.vt-fade-enter-active,.vt-fade-leave-active{ transition:opacity .25s ease; } .vt-fade-enter-from,.vt-fade-leave-to{ opacity:0; }
.vt-pop-enter-active{ transition:transform .38s cubic-bezier(.2,.9,.3,1.2),opacity .3s ease; } .vt-pop-enter-from{ transform:translateY(30px) scale(.94); opacity:0; }
.vt-hero { display:flex; align-items:center; gap:12px; padding:14px 18px; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; }
.vt-hero-ico { font-size:26px; } .vt-hero-tx h3 { margin:0; font-size:15px; } .vt-hero-tx p { margin:2px 0 0; font-size:11px; opacity:.85; }
.vt-x { margin-left:auto; background:rgba(255,255,255,.15); border:none; color:#fff; width:28px; height:28px; border-radius:8px; font-size:14px; cursor:pointer; }
.vt-body { padding:18px 22px; } .vt-intro { font-size:14px; color:#374151; line-height:1.55; margin:0 0 14px; }
.vt-notas { margin:0; padding-left:6px; list-style:none; }
.vt-notas li { font-size:13px; color:#475569; line-height:1.55; margin-bottom:8px; padding-left:24px; position:relative; } .vt-notas li::before { content:'💡'; position:absolute; left:0; }
</style>
