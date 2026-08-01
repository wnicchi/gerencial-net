<!-- ComedoresAyuda.vue -->
<template>
  <Teleport to="body"><transition name="cm-fade" appear><div class="cm-ov" @click.self="$emit('close')">
    <transition name="cm-pop" appear><div class="cm-md">
      <div class="cm-hero"><div class="cm-hero-ico">🍽️</div><div class="cm-hero-tx"><h3>Ayuda — Comedores</h3><p>ABM de comedores</p></div><button class="cm-x" @click="$emit('close')">✕</button></div>
      <div class="cm-body">
        <p class="cm-intro">Acá se administran los <b>comedores</b> que después se asignan a los empleados.</p>
        <div class="cm-pasos"><div class="cm-pasos-t">⚡ Cómo se usa</div><ol>
          <li v-for="(p,i) in pasos" :key="i" :style="{ animationDelay:(i*90+120)+'ms' }"><span class="cm-num">{{ i+1 }}</span><span v-html="p"></span></li>
        </ol></div>
        <ul class="cm-notas"><li>El <b>código</b> lo asigna el sistema solo; la <b>descripción</b> es obligatoria.</li><li>No se puede <b>eliminar</b> un comedor con empleados asignados.</li></ul>
      </div>
    </div></transition>
  </div></transition></Teleport>
</template>
<script setup lang="ts">
defineEmits<{ close: [] }>()
const pasos = ['<b>＋ Nuevo:</b> ingresá la descripción y guardá.','<b>✏️ Editar:</b> cambiá la descripción.','<b>🗑️ Eliminar:</b> borra el comedor (si no tiene empleados).']
</script>
<style scoped>
.cm-ov { position:fixed; inset:0; background:rgba(15,23,42,.55); backdrop-filter:blur(3px); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.cm-md { width:min(440px,96vw); background:#fff; border-radius:16px; overflow:hidden; box-shadow:0 24px 60px rgba(0,0,0,.45); font-family:var(--font-sans,sans-serif); }
.cm-fade-enter-active,.cm-fade-leave-active{ transition:opacity .25s ease; } .cm-fade-enter-from,.cm-fade-leave-to{ opacity:0; }
.cm-pop-enter-active{ transition:transform .38s cubic-bezier(.2,.9,.3,1.2),opacity .3s ease; } .cm-pop-enter-from{ transform:translateY(30px) scale(.94); opacity:0; }
.cm-hero { display:flex; align-items:center; gap:12px; padding:14px 18px; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; }
.cm-hero-ico { font-size:26px; animation:cm-lat 2.4s ease-in-out infinite; } @keyframes cm-lat{0%,100%{transform:scale(1)}50%{transform:scale(1.12)}}
.cm-hero-tx h3 { margin:0; font-size:15px; } .cm-hero-tx p { margin:2px 0 0; font-size:11px; opacity:.85; }
.cm-x { margin-left:auto; background:rgba(255,255,255,.15); border:none; color:#fff; width:28px; height:28px; border-radius:8px; font-size:14px; cursor:pointer; } .cm-x:hover{ background:rgba(255,255,255,.3); }
.cm-body { padding:18px 22px; } .cm-intro { font-size:14px; color:#374151; line-height:1.55; margin:0 0 14px; }
.cm-pasos { background:#f8fafc; border:1px solid #eef2f7; border-radius:12px; padding:12px 16px; margin-bottom:12px; }
.cm-pasos-t { font-size:12px; font-weight:700; color:#1b4332; text-transform:uppercase; letter-spacing:.5px; margin-bottom:10px; }
.cm-pasos ol { margin:0; padding:0; list-style:none; }
.cm-pasos li { display:flex; align-items:flex-start; gap:10px; font-size:13px; color:#374151; line-height:1.5; margin-bottom:8px; opacity:0; animation:cm-pasoIn .4s ease forwards; }
.cm-num { flex-shrink:0; width:22px; height:22px; border-radius:50%; background:#16a34a; color:#fff; font-size:12px; font-weight:700; display:flex; align-items:center; justify-content:center; }
@keyframes cm-pasoIn{ from{opacity:0;transform:translateX(-8px)} to{opacity:1;transform:none} }
.cm-notas { margin:0; padding-left:6px; list-style:none; }
.cm-notas li { font-size:13px; color:#475569; line-height:1.55; margin-bottom:6px; padding-left:24px; position:relative; } .cm-notas li::before { content:'💡'; position:absolute; left:0; }
</style>
