<!-- HaberesAyuda.vue — Modal de ayuda del módulo Haberes. -->
<template>
  <Teleport to="body">
    <transition name="hb-fade" appear>
      <div class="hb-ov" @click.self="$emit('close')">
        <transition name="hb-pop" appear>
          <div class="hb-md">
            <div class="hb-hero">
              <div class="hb-hero-ico">💰</div>
              <div class="hb-hero-tx"><h3>Ayuda — Haberes</h3><p>Conceptos que cobra el empleado</p></div>
              <button class="hb-x" @click="$emit('close')">✕</button>
            </div>
            <div class="hb-body">
              <p class="hb-intro">Acá se administran los <b>haberes</b> (conceptos que el empleado <b>cobra</b>), con su alícuota o importe, sus indicadores y el convenio.</p>
              <div class="hb-pasos">
                <div class="hb-pasos-t">⚡ Cómo se usa</div>
                <ol><li v-for="(p, i) in pasos" :key="i" :style="{ animationDelay: (i*90+120)+'ms' }"><span class="hb-num">{{ i+1 }}</span><span v-html="p"></span></li></ol>
              </div>
              <ul class="hb-notas">
                <li>El <b>código</b> lo asigna el sistema; la <b>descripción</b> y el <b>convenio</b> son obligatorios.</li>
                <li>Los indicadores <b>Sí/No</b> definen cómo se calcula el haber (antigüedad, valor hora, retenciones, etc.).</li>
              </ul>
            </div>
          </div>
        </transition>
      </div>
    </transition>
  </Teleport>
</template>

<script setup lang="ts">
defineEmits<{ close: [] }>()
const pasos = [
  '<b>＋ Nuevo:</b> cargá descripción, alícuota/importe, los indicadores y el convenio.',
  '<b>✏️ Editar:</b> modificá los datos del haber.',
  '<b>🗑️ Eliminar:</b> borra el haber.',
]
</script>

<style scoped>
.hb-ov { position:fixed; inset:0; background:rgba(15,23,42,.55); backdrop-filter:blur(3px); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.hb-md { width:min(460px,96vw); background:#fff; border-radius:16px; overflow:hidden; box-shadow:0 24px 60px rgba(0,0,0,.45); font-family:var(--font-sans,sans-serif); }
.hb-fade-enter-active,.hb-fade-leave-active{ transition:opacity .25s ease; } .hb-fade-enter-from,.hb-fade-leave-to{ opacity:0; }
.hb-pop-enter-active{ transition:transform .38s cubic-bezier(.2,.9,.3,1.2),opacity .3s ease; } .hb-pop-enter-from{ transform:translateY(30px) scale(.94); opacity:0; }
.hb-hero { display:flex; align-items:center; gap:12px; padding:14px 18px; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; }
.hb-hero-ico { font-size:26px; animation:hb-lat 2.4s ease-in-out infinite; } @keyframes hb-lat{0%,100%{transform:scale(1)}50%{transform:scale(1.12)}}
.hb-hero-tx h3 { margin:0; font-size:15px; } .hb-hero-tx p { margin:2px 0 0; font-size:11px; opacity:.85; }
.hb-x { margin-left:auto; background:rgba(255,255,255,.15); border:none; color:#fff; width:28px; height:28px; border-radius:8px; font-size:14px; cursor:pointer; } .hb-x:hover{ background:rgba(255,255,255,.3); }
.hb-body { padding:18px 22px; } .hb-intro { font-size:14px; color:#374151; line-height:1.55; margin:0 0 14px; }
.hb-pasos { background:#f8fafc; border:1px solid #eef2f7; border-radius:12px; padding:12px 16px; margin-bottom:12px; }
.hb-pasos-t { font-size:12px; font-weight:700; color:#1b4332; text-transform:uppercase; letter-spacing:.5px; margin-bottom:10px; }
.hb-pasos ol { margin:0; padding:0; list-style:none; }
.hb-pasos li { display:flex; align-items:flex-start; gap:10px; font-size:13px; color:#374151; line-height:1.5; margin-bottom:8px; opacity:0; animation:hb-pasoIn .4s ease forwards; }
.hb-num { flex-shrink:0; width:22px; height:22px; border-radius:50%; background:#16a34a; color:#fff; font-size:12px; font-weight:700; display:flex; align-items:center; justify-content:center; }
@keyframes hb-pasoIn{ from{opacity:0;transform:translateX(-8px)} to{opacity:1;transform:none} }
.hb-notas { margin:0; padding-left:6px; list-style:none; }
.hb-notas li { font-size:13px; color:#475569; line-height:1.55; margin-bottom:6px; padding-left:24px; position:relative; } .hb-notas li::before { content:'💡'; position:absolute; left:0; }
</style>
