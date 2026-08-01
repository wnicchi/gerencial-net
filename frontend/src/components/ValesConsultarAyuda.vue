<!-- ValesConsultarAyuda.vue — Ayuda del módulo Consultar Vales. -->
<template>
  <Teleport to="body"><transition name="vc-fade" appear><div class="vc-ov" @click.self="$emit('close')">
    <transition name="vc-pop" appear><div class="vc-md">
      <div class="vc-hero">
        <div class="vc-hero-ico">🔍</div>
        <div class="vc-hero-tx"><h3>Ayuda — Consultar Vales</h3><p>Lista de vales de efectivo</p></div>
        <button class="vc-x" @click="$emit('close')">✕</button>
      </div>
      <div class="vc-body">
        <p class="vc-intro">Muestra los <b>vales de efectivo</b> entregados, con su importe, razón y autorización.</p>
        <ul class="vc-notas">
          <li><b>Histórico</b> muestra todos; <b>por Período</b> filtra por rango de fechas.</li>
          <li><b>Un Empleado</b> filtra por una persona en particular.</li>
          <li><b>Solo Vales sin cerrar</b> muestra los que aún no se rindieron.</li>
          <li>Los vales <b style="background:#e2e8f0;padding:0 4px;border-radius:3px">cerrados</b> (rendidos) aparecen en gris.</li>
          <li>Tocá los <b>títulos de columna</b> para ordenar la lista.</li>
        </ul>
      </div>
    </div></transition>
  </div></transition></Teleport>
</template>
<script setup lang="ts">
defineEmits<{ close: [] }>()
</script>
<style scoped>
.vc-ov { position:fixed; inset:0; background:rgba(15,23,42,.55); backdrop-filter:blur(3px); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.vc-md { width:min(460px,96vw); max-height:92vh; overflow:auto; background:#fff; border-radius:16px; box-shadow:0 24px 60px rgba(0,0,0,.45); font-family:var(--font-sans,sans-serif); }
.vc-fade-enter-active,.vc-fade-leave-active{ transition:opacity .25s ease; } .vc-fade-enter-from,.vc-fade-leave-to{ opacity:0; }
.vc-pop-enter-active{ transition:transform .38s cubic-bezier(.2,.9,.3,1.2),opacity .3s ease; } .vc-pop-enter-from{ transform:translateY(30px) scale(.94); opacity:0; }
.vc-hero { display:flex; align-items:center; gap:12px; padding:14px 18px; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; position:sticky; top:0; }
.vc-hero-ico { font-size:26px; } .vc-hero-tx h3 { margin:0; font-size:15px; } .vc-hero-tx p { margin:2px 0 0; font-size:11px; opacity:.85; }
.vc-x { margin-left:auto; background:rgba(255,255,255,.15); border:none; color:#fff; width:28px; height:28px; border-radius:8px; font-size:14px; cursor:pointer; }
.vc-body { padding:18px 22px; } .vc-intro { font-size:14px; color:#374151; line-height:1.55; margin:0 0 14px; }
.vc-notas { margin:0; padding-left:6px; list-style:none; }
.vc-notas li { font-size:13px; color:#475569; line-height:1.55; margin-bottom:8px; padding-left:24px; position:relative; } .vc-notas li::before { content:'💡'; position:absolute; left:0; }
</style>
