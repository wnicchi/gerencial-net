<!-- CarnetCategoriasAyuda.vue -->
<template>
  <Teleport to="body"><transition name="cn-fade" appear><div class="cn-ov" @click.self="$emit('close')">
    <transition name="cn-pop" appear><div class="cn-md">
      <div class="cn-hero"><div class="cn-hero-ico">🪪</div><div class="cn-hero-tx"><h3>Ayuda — Categorías de Carnet</h3><p>ABM de categorías de carnet</p></div><button class="cn-x" @click="$emit('close')">✕</button></div>
      <div class="cn-body">
        <p class="cn-intro">Acá se administran las <b>categorías del carnet</b> (de conducir / autoelevador) que después se usan al cargar carnets.</p>
        <div class="cn-pasos"><div class="cn-pasos-t">⚡ Cómo se usa</div><ol>
          <li v-for="(p,i) in pasos" :key="i" :style="{ animationDelay:(i*90+120)+'ms' }"><span class="cn-num">{{ i+1 }}</span><span v-html="p"></span></li>
        </ol></div>
        <ul class="cn-notas"><li>El <b>código</b> lo ingresás vos (corto, ej. "A21") y no se puede repetir.</li></ul>
      </div>
    </div></transition>
  </div></transition></Teleport>
</template>
<script setup lang="ts">
defineEmits<{ close: [] }>()
const pasos = ['<b>＋ Nueva:</b> ingresá código y nombre.','<b>✏️ Editar:</b> cambiá el nombre.','<b>🗑️ Eliminar:</b> borra la categoría.']
</script>
<style scoped>
.cn-ov { position:fixed; inset:0; background:rgba(15,23,42,.55); backdrop-filter:blur(3px); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.cn-md { width:min(440px,96vw); background:#fff; border-radius:16px; overflow:hidden; box-shadow:0 24px 60px rgba(0,0,0,.45); font-family:var(--font-sans,sans-serif); }
.cn-fade-enter-active,.cn-fade-leave-active{ transition:opacity .25s ease; } .cn-fade-enter-from,.cn-fade-leave-to{ opacity:0; }
.cn-pop-enter-active{ transition:transform .38s cubic-bezier(.2,.9,.3,1.2),opacity .3s ease; } .cn-pop-enter-from{ transform:translateY(30px) scale(.94); opacity:0; }
.cn-hero { display:flex; align-items:center; gap:12px; padding:14px 18px; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; }
.cn-hero-ico { font-size:26px; animation:cn-lat 2.4s ease-in-out infinite; } @keyframes cn-lat{0%,100%{transform:scale(1)}50%{transform:scale(1.12)}}
.cn-hero-tx h3 { margin:0; font-size:15px; } .cn-hero-tx p { margin:2px 0 0; font-size:11px; opacity:.85; }
.cn-x { margin-left:auto; background:rgba(255,255,255,.15); border:none; color:#fff; width:28px; height:28px; border-radius:8px; font-size:14px; cursor:pointer; } .cn-x:hover{ background:rgba(255,255,255,.3); }
.cn-body { padding:18px 22px; } .cn-intro { font-size:14px; color:#374151; line-height:1.55; margin:0 0 14px; }
.cn-pasos { background:#f8fafc; border:1px solid #eef2f7; border-radius:12px; padding:12px 16px; margin-bottom:12px; }
.cn-pasos-t { font-size:12px; font-weight:700; color:#1b4332; text-transform:uppercase; letter-spacing:.5px; margin-bottom:10px; }
.cn-pasos ol { margin:0; padding:0; list-style:none; }
.cn-pasos li { display:flex; align-items:flex-start; gap:10px; font-size:13px; color:#374151; line-height:1.5; margin-bottom:8px; opacity:0; animation:cn-pasoIn .4s ease forwards; }
.cn-num { flex-shrink:0; width:22px; height:22px; border-radius:50%; background:#16a34a; color:#fff; font-size:12px; font-weight:700; display:flex; align-items:center; justify-content:center; }
@keyframes cn-pasoIn{ from{opacity:0;transform:translateX(-8px)} to{opacity:1;transform:none} }
.cn-notas { margin:0; padding-left:6px; list-style:none; }
.cn-notas li { font-size:13px; color:#475569; line-height:1.55; margin-bottom:6px; padding-left:24px; position:relative; } .cn-notas li::before { content:'💡'; position:absolute; left:0; }
</style>
