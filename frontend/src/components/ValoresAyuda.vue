<!-- ValoresAyuda.vue -->
<template>
  <Teleport to="body"><transition name="vl-fade" appear><div class="vl-ov" @click.self="$emit('close')">
    <transition name="vl-pop" appear><div class="vl-md">
      <div class="vl-hero"><div class="vl-hero-ico">💱</div><div class="vl-hero-tx"><h3>Ayuda — Valores</h3><p>Parámetros generales</p></div><button class="vl-x" @click="$emit('close')">✕</button></div>
      <div class="vl-body">
        <p class="vl-intro">Esta pantalla guarda <b>parámetros generales</b> del sistema (no es un alta/baja: es un único conjunto de valores que se edita y se guarda).</p>
        <ul class="vl-notas">
          <li><b>Datos depósito anterior:</b> banco, fecha y observación del último depósito.</li>
          <li><b>Asignaciones familiares por hijo:</b> los tramos "hasta" y el importe en pesos.</li>
          <li><b>Datos fijos DGI/SIJP:</b> porcentaje de reducción e importe adicional de obra social.</li>
          <li><b>Decreto 1273/02:</b> los valores del decreto, obra social e INSSJP.</li>
          <li>Modificá lo que necesites y tocá <b>Guardar cambios</b>.</li>
        </ul>
      </div>
    </div></transition>
  </div></transition></Teleport>
</template>
<script setup lang="ts">
defineEmits<{ close: [] }>()
</script>
<style scoped>
.vl-ov { position:fixed; inset:0; background:rgba(15,23,42,.55); backdrop-filter:blur(3px); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.vl-md { width:min(480px,96vw); background:#fff; border-radius:16px; overflow:hidden; box-shadow:0 24px 60px rgba(0,0,0,.45); font-family:var(--font-sans,sans-serif); }
.vl-fade-enter-active,.vl-fade-leave-active{ transition:opacity .25s ease; } .vl-fade-enter-from,.vl-fade-leave-to{ opacity:0; }
.vl-pop-enter-active{ transition:transform .38s cubic-bezier(.2,.9,.3,1.2),opacity .3s ease; } .vl-pop-enter-from{ transform:translateY(30px) scale(.94); opacity:0; }
.vl-hero { display:flex; align-items:center; gap:12px; padding:14px 18px; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; }
.vl-hero-ico { font-size:26px; animation:vl-lat 2.4s ease-in-out infinite; } @keyframes vl-lat{0%,100%{transform:scale(1)}50%{transform:scale(1.12)}}
.vl-hero-tx h3 { margin:0; font-size:15px; } .vl-hero-tx p { margin:2px 0 0; font-size:11px; opacity:.85; }
.vl-x { margin-left:auto; background:rgba(255,255,255,.15); border:none; color:#fff; width:28px; height:28px; border-radius:8px; font-size:14px; cursor:pointer; } .vl-x:hover{ background:rgba(255,255,255,.3); }
.vl-body { padding:18px 22px; } .vl-intro { font-size:14px; color:#374151; line-height:1.55; margin:0 0 14px; }
.vl-notas { margin:0; padding-left:6px; list-style:none; }
.vl-notas li { font-size:13px; color:#475569; line-height:1.55; margin-bottom:8px; padding-left:24px; position:relative; } .vl-notas li::before { content:'💡'; position:absolute; left:0; }
</style>
