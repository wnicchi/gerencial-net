<!-- AsignacionesAyuda.vue — Modal de ayuda del módulo Asignaciones Familiares. -->
<template>
  <Teleport to="body">
    <transition name="af-fade" appear>
      <div class="af-ov" @click.self="$emit('close')">
        <transition name="af-pop" appear>
          <div class="af-md">
            <div class="af-hero">
              <div class="af-hero-ico">👨‍👩‍👧</div>
              <div class="af-hero-tx">
                <h3>Ayuda — Asignaciones Familiares</h3>
                <p>ABM de conceptos e importes</p>
              </div>
              <button class="af-x" @click="$emit('close')">✕</button>
            </div>

            <div class="af-body">
              <p class="af-intro">Acá se administran los <b>conceptos de asignaciones familiares</b> y su <b>importe</b>.</p>

              <div class="af-pasos">
                <div class="af-pasos-t">⚡ Cómo se usa</div>
                <ol>
                  <li v-for="(p, i) in pasos" :key="i" :style="{ animationDelay: (i*90+120)+'ms' }">
                    <span class="af-num">{{ i+1 }}</span><span v-html="p"></span>
                  </li>
                </ol>
              </div>

              <ul class="af-notas">
                <li>El <b>código</b> lo asigna el sistema solo; la <b>descripción</b> es obligatoria.</li>
                <li>El <b>importe</b> es opcional (podés dejarlo en 0 y cargarlo después).</li>
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
  '<b>＋ Nueva:</b> ingresá la descripción y el importe.',
  '<b>✏️ Editar:</b> modificá la descripción o el importe.',
  '<b>🗑️ Eliminar:</b> borra el concepto de asignación.',
]
</script>

<style scoped>
.af-ov { position:fixed; inset:0; background:rgba(15,23,42,.55); backdrop-filter:blur(3px); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.af-md { width:min(440px,96vw); background:#fff; border-radius:16px; overflow:hidden; box-shadow:0 24px 60px rgba(0,0,0,.45); font-family:var(--font-sans,sans-serif); }
.af-fade-enter-active,.af-fade-leave-active{ transition:opacity .25s ease; }
.af-fade-enter-from,.af-fade-leave-to{ opacity:0; }
.af-pop-enter-active{ transition:transform .38s cubic-bezier(.2,.9,.3,1.2),opacity .3s ease; }
.af-pop-enter-from{ transform:translateY(30px) scale(.94); opacity:0; }
.af-hero { display:flex; align-items:center; gap:12px; padding:14px 18px; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; }
.af-hero-ico { font-size:26px; animation:af-lat 2.4s ease-in-out infinite; }
@keyframes af-lat{0%,100%{transform:scale(1)}50%{transform:scale(1.12)}}
.af-hero-tx h3 { margin:0; font-size:15px; } .af-hero-tx p { margin:2px 0 0; font-size:11px; opacity:.85; }
.af-x { margin-left:auto; background:rgba(255,255,255,.15); border:none; color:#fff; width:28px; height:28px; border-radius:8px; font-size:14px; cursor:pointer; }
.af-x:hover{ background:rgba(255,255,255,.3); }
.af-body { padding:18px 22px; }
.af-intro { font-size:14px; color:#374151; line-height:1.55; margin:0 0 14px; }
.af-pasos { background:#f8fafc; border:1px solid #eef2f7; border-radius:12px; padding:12px 16px; margin-bottom:12px; }
.af-pasos-t { font-size:12px; font-weight:700; color:#1b4332; text-transform:uppercase; letter-spacing:.5px; margin-bottom:10px; }
.af-pasos ol { margin:0; padding:0; list-style:none; }
.af-pasos li { display:flex; align-items:flex-start; gap:10px; font-size:13px; color:#374151; line-height:1.5; margin-bottom:8px; opacity:0; animation:af-pasoIn .4s ease forwards; }
.af-num { flex-shrink:0; width:22px; height:22px; border-radius:50%; background:#16a34a; color:#fff; font-size:12px; font-weight:700; display:flex; align-items:center; justify-content:center; }
@keyframes af-pasoIn{ from{opacity:0;transform:translateX(-8px)} to{opacity:1;transform:none} }
.af-notas { margin:0; padding-left:6px; list-style:none; }
.af-notas li { font-size:13px; color:#475569; line-height:1.55; margin-bottom:6px; padding-left:24px; position:relative; }
.af-notas li::before { content:'💡'; position:absolute; left:0; }
</style>
