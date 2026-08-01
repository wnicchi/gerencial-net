<!-- CtasBancariasAyuda.vue -->
<template>
  <Teleport to="body"><transition name="cb-fade" appear><div class="cb-ov" @click.self="$emit('close')">
    <transition name="cb-pop" appear><div class="cb-md">
      <div class="cb-hero"><div class="cb-hero-ico">🏦</div><div class="cb-hero-tx"><h3>Ayuda — Cuentas Bancarias</h3><p>ABM de bancos / cuentas</p></div><button class="cb-x" @click="$emit('close')">✕</button></div>
      <div class="cb-body">
        <p class="cb-intro">Acá se administran los <b>bancos / cuentas bancarias</b> que después se usan en los empleados y las exportaciones a bancos.</p>
        <div class="cb-pasos"><div class="cb-pasos-t">⚡ Cómo se usa</div><ol>
          <li v-for="(p,i) in pasos" :key="i" :style="{ animationDelay:(i*90+120)+'ms' }"><span class="cb-num">{{ i+1 }}</span><span v-html="p"></span></li>
        </ol></div>
        <ul class="cb-notas"><li>El <b>código</b> lo asigna el sistema; la <b>descripción</b> es obligatoria.</li><li>Los <b>códigos SILCAR y Logística</b> son los números provistos del banco en cada sistema.</li></ul>
      </div>
    </div></transition>
  </div></transition></Teleport>
</template>
<script setup lang="ts">
defineEmits<{ close: [] }>()
const pasos = ['<b>＋ Nueva:</b> cargá la descripción y los códigos SILCAR/Logística.','<b>✏️ Editar:</b> modificá los datos.','<b>🗑️ Eliminar:</b> borra la cuenta.']
</script>
<style scoped>
.cb-ov { position:fixed; inset:0; background:rgba(15,23,42,.55); backdrop-filter:blur(3px); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.cb-md { width:min(440px,96vw); background:#fff; border-radius:16px; overflow:hidden; box-shadow:0 24px 60px rgba(0,0,0,.45); font-family:var(--font-sans,sans-serif); }
.cb-fade-enter-active,.cb-fade-leave-active{ transition:opacity .25s ease; } .cb-fade-enter-from,.cb-fade-leave-to{ opacity:0; }
.cb-pop-enter-active{ transition:transform .38s cubic-bezier(.2,.9,.3,1.2),opacity .3s ease; } .cb-pop-enter-from{ transform:translateY(30px) scale(.94); opacity:0; }
.cb-hero { display:flex; align-items:center; gap:12px; padding:14px 18px; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; }
.cb-hero-ico { font-size:26px; animation:cb-lat 2.4s ease-in-out infinite; } @keyframes cb-lat{0%,100%{transform:scale(1)}50%{transform:scale(1.12)}}
.cb-hero-tx h3 { margin:0; font-size:15px; } .cb-hero-tx p { margin:2px 0 0; font-size:11px; opacity:.85; }
.cb-x { margin-left:auto; background:rgba(255,255,255,.15); border:none; color:#fff; width:28px; height:28px; border-radius:8px; font-size:14px; cursor:pointer; } .cb-x:hover{ background:rgba(255,255,255,.3); }
.cb-body { padding:18px 22px; } .cb-intro { font-size:14px; color:#374151; line-height:1.55; margin:0 0 14px; }
.cb-pasos { background:#f8fafc; border:1px solid #eef2f7; border-radius:12px; padding:12px 16px; margin-bottom:12px; }
.cb-pasos-t { font-size:12px; font-weight:700; color:#1b4332; text-transform:uppercase; letter-spacing:.5px; margin-bottom:10px; }
.cb-pasos ol { margin:0; padding:0; list-style:none; }
.cb-pasos li { display:flex; align-items:flex-start; gap:10px; font-size:13px; color:#374151; line-height:1.5; margin-bottom:8px; opacity:0; animation:cb-pasoIn .4s ease forwards; }
.cb-num { flex-shrink:0; width:22px; height:22px; border-radius:50%; background:#16a34a; color:#fff; font-size:12px; font-weight:700; display:flex; align-items:center; justify-content:center; }
@keyframes cb-pasoIn{ from{opacity:0;transform:translateX(-8px)} to{opacity:1;transform:none} }
.cb-notas { margin:0; padding-left:6px; list-style:none; }
.cb-notas li { font-size:13px; color:#475569; line-height:1.55; margin-bottom:6px; padding-left:24px; position:relative; } .cb-notas li::before { content:'💡'; position:absolute; left:0; }
</style>
