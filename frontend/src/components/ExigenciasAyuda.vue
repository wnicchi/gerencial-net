<!-- ExigenciasAyuda.vue — Ayuda del módulo Exigencias Legales a Contratados. -->
<template>
  <Teleport to="body"><transition name="ex-fade" appear><div class="ex-ov" @click.self="$emit('close')">
    <transition name="ex-pop" appear><div class="ex-md">
      <div class="ex-hero"><div class="ex-hero-ico">⚖️</div><div class="ex-hero-tx"><h3>Ayuda — Exigencias a Contratados</h3><p>ABM de exigencias legales</p></div><button class="ex-x" @click="$emit('close')">✕</button></div>
      <div class="ex-body">
        <p class="ex-intro">Acá se administran las <b>exigencias legales</b> que se les piden a los contratistas externos (pólizas, ART, constancias, etc.).</p>
        <div class="ex-pasos"><div class="ex-pasos-t">⚡ Cómo se usa</div><ol>
          <li v-for="(p,i) in pasos" :key="i" :style="{ animationDelay:(i*90+120)+'ms' }"><span class="ex-num">{{ i+1 }}</span><span v-html="p"></span></li>
        </ol></div>
        <ul class="ex-notas">
          <li><b>Por única vez:</b> si la exigencia se pide una sola vez. Si es periódica, indicá los <b>meses de vigencia</b>.</li>
          <li><b>Exigencia A.R.T.:</b> marcala si corresponde a la aseguradora de riesgos del trabajo.</li>
          <li>No se puede <b>eliminar</b> una exigencia que ya está asignada a un contratista.</li>
        </ul>
      </div>
    </div></transition>
  </div></transition></Teleport>
</template>
<script setup lang="ts">
defineEmits<{ close: [] }>()
const pasos = ['<b>＋ Nueva:</b> cargá la descripción y las condiciones.','<b>✏️ Editar:</b> modificá una exigencia existente.','<b>🗑️ Eliminar:</b> borra la exigencia (si no está en uso).']
</script>
<style scoped>
.ex-ov { position:fixed; inset:0; background:rgba(15,23,42,.55); backdrop-filter:blur(3px); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.ex-md { width:min(460px,96vw); background:#fff; border-radius:16px; overflow:hidden; box-shadow:0 24px 60px rgba(0,0,0,.45); font-family:var(--font-sans,sans-serif); }
.ex-fade-enter-active,.ex-fade-leave-active{ transition:opacity .25s ease; } .ex-fade-enter-from,.ex-fade-leave-to{ opacity:0; }
.ex-pop-enter-active{ transition:transform .38s cubic-bezier(.2,.9,.3,1.2),opacity .3s ease; } .ex-pop-enter-from{ transform:translateY(30px) scale(.94); opacity:0; }
.ex-hero { display:flex; align-items:center; gap:12px; padding:14px 18px; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; }
.ex-hero-ico { font-size:26px; animation:ex-lat 2.4s ease-in-out infinite; } @keyframes ex-lat{0%,100%{transform:scale(1)}50%{transform:scale(1.12)}}
.ex-hero-tx h3 { margin:0; font-size:15px; } .ex-hero-tx p { margin:2px 0 0; font-size:11px; opacity:.85; }
.ex-x { margin-left:auto; background:rgba(255,255,255,.15); border:none; color:#fff; width:28px; height:28px; border-radius:8px; font-size:14px; cursor:pointer; }
.ex-body { padding:18px 22px; } .ex-intro { font-size:14px; color:#374151; line-height:1.55; margin:0 0 14px; }
.ex-pasos { background:#f8fafc; border:1px solid #eef2f7; border-radius:12px; padding:12px 16px; margin-bottom:12px; }
.ex-pasos-t { font-size:12px; font-weight:700; color:#1b4332; text-transform:uppercase; letter-spacing:.5px; margin-bottom:10px; }
.ex-pasos ol { margin:0; padding:0; list-style:none; }
.ex-pasos li { display:flex; align-items:flex-start; gap:10px; font-size:13px; color:#374151; line-height:1.5; margin-bottom:8px; opacity:0; animation:ex-pasoIn .4s ease forwards; }
.ex-num { flex-shrink:0; width:22px; height:22px; border-radius:50%; background:#16a34a; color:#fff; font-size:12px; font-weight:700; display:flex; align-items:center; justify-content:center; }
@keyframes ex-pasoIn{ from{opacity:0;transform:translateX(-8px)} to{opacity:1;transform:none} }
.ex-notas { margin:0; padding-left:6px; list-style:none; }
.ex-notas li { font-size:13px; color:#475569; line-height:1.55; margin-bottom:6px; padding-left:24px; position:relative; } .ex-notas li::before { content:'💡'; position:absolute; left:0; }
</style>
