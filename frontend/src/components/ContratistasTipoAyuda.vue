<!-- ContratistasTipoAyuda.vue — Ayuda del módulo Contratistas Tipo. -->
<template>
  <Teleport to="body"><transition name="ct-fade" appear><div class="ct-ov" @click.self="$emit('close')">
    <transition name="ct-pop" appear><div class="ct-md">
      <div class="ct-hero"><div class="ct-hero-ico">🏷️</div><div class="ct-hero-tx"><h3>Ayuda — Contratistas Tipo</h3><p>ABM de tipos de contratista</p></div><button class="ct-x" @click="$emit('close')">✕</button></div>
      <div class="ct-body">
        <p class="ct-intro">Acá se administran los <b>tipos de contratista</b> que después se eligen al cargar un contratista externo.</p>
        <div class="ct-pasos"><div class="ct-pasos-t">⚡ Cómo se usa</div><ol>
          <li v-for="(p,i) in pasos" :key="i" :style="{ animationDelay:(i*90+120)+'ms' }"><span class="ct-num">{{ i+1 }}</span><span v-html="p"></span></li>
        </ol></div>
        <ul class="ct-notas">
          <li>La <b>clase</b> indica si el contratista es <b>Fijo</b> o <b>Eventual</b>.</li>
          <li>El <b>código</b> lo asigna el sistema solo; el <b>nombre</b> es obligatorio.</li>
          <li>No se puede <b>eliminar</b> un tipo si hay contratistas que lo usan.</li>
        </ul>
      </div>
    </div></transition>
  </div></transition></Teleport>
</template>
<script setup lang="ts">
defineEmits<{ close: [] }>()
const pasos = ['<b>＋ Nuevo:</b> poné el nombre y la clase (Fijo/Eventual).','<b>✏️ Editar:</b> cambiá el nombre o la clase.','<b>🗑️ Eliminar:</b> borra el tipo (si no está en uso).']
</script>
<style scoped>
.ct-ov { position:fixed; inset:0; background:rgba(15,23,42,.55); backdrop-filter:blur(3px); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.ct-md { width:min(440px,96vw); background:#fff; border-radius:16px; overflow:hidden; box-shadow:0 24px 60px rgba(0,0,0,.45); font-family:var(--font-sans,sans-serif); }
.ct-fade-enter-active,.ct-fade-leave-active{ transition:opacity .25s ease; } .ct-fade-enter-from,.ct-fade-leave-to{ opacity:0; }
.ct-pop-enter-active{ transition:transform .38s cubic-bezier(.2,.9,.3,1.2),opacity .3s ease; } .ct-pop-enter-from{ transform:translateY(30px) scale(.94); opacity:0; }
.ct-hero { display:flex; align-items:center; gap:12px; padding:14px 18px; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; }
.ct-hero-ico { font-size:26px; animation:ct-lat 2.4s ease-in-out infinite; } @keyframes ct-lat{0%,100%{transform:scale(1)}50%{transform:scale(1.12)}}
.ct-hero-tx h3 { margin:0; font-size:15px; } .ct-hero-tx p { margin:2px 0 0; font-size:11px; opacity:.85; }
.ct-x { margin-left:auto; background:rgba(255,255,255,.15); border:none; color:#fff; width:28px; height:28px; border-radius:8px; font-size:14px; cursor:pointer; }
.ct-body { padding:18px 22px; } .ct-intro { font-size:14px; color:#374151; line-height:1.55; margin:0 0 14px; }
.ct-pasos { background:#f8fafc; border:1px solid #eef2f7; border-radius:12px; padding:12px 16px; margin-bottom:12px; }
.ct-pasos-t { font-size:12px; font-weight:700; color:#1b4332; text-transform:uppercase; letter-spacing:.5px; margin-bottom:10px; }
.ct-pasos ol { margin:0; padding:0; list-style:none; }
.ct-pasos li { display:flex; align-items:flex-start; gap:10px; font-size:13px; color:#374151; line-height:1.5; margin-bottom:8px; opacity:0; animation:ct-pasoIn .4s ease forwards; }
.ct-num { flex-shrink:0; width:22px; height:22px; border-radius:50%; background:#16a34a; color:#fff; font-size:12px; font-weight:700; display:flex; align-items:center; justify-content:center; }
@keyframes ct-pasoIn{ from{opacity:0;transform:translateX(-8px)} to{opacity:1;transform:none} }
.ct-notas { margin:0; padding-left:6px; list-style:none; }
.ct-notas li { font-size:13px; color:#475569; line-height:1.55; margin-bottom:6px; padding-left:24px; position:relative; } .ct-notas li::before { content:'💡'; position:absolute; left:0; }
</style>
