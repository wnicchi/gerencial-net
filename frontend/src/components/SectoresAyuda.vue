<!-- SectoresAyuda.vue — Modal de ayuda del módulo Sectores Laborales. -->
<template>
  <Teleport to="body">
    <transition name="se-fade" appear>
      <div class="se-ov" @click.self="$emit('close')">
        <transition name="se-pop" appear>
          <div class="se-md">
            <div class="se-hero">
              <div class="se-hero-ico">🏭</div>
              <div class="se-hero-tx">
                <h3>Ayuda — Sectores Laborales</h3>
                <p>ABM de sectores</p>
              </div>
              <button class="se-x" @click="$emit('close')">✕</button>
            </div>

            <div class="se-body">
              <p class="se-intro">Acá se administran los <b>sectores laborales</b> que después se asignan a los empleados.</p>

              <div class="se-pasos">
                <div class="se-pasos-t">⚡ Cómo se usa</div>
                <ol>
                  <li v-for="(p, i) in pasos" :key="i" :style="{ animationDelay: (i*90+120)+'ms' }">
                    <span class="se-num">{{ i+1 }}</span><span v-html="p"></span>
                  </li>
                </ol>
              </div>

              <ul class="se-notas">
                <li>El <b>código</b> lo asigna el sistema solo; el <b>nombre</b> es obligatorio.</li>
                <li>No se puede <b>eliminar</b> un sector que tenga empleados asignados.</li>
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
  '<b>＋ Nuevo:</b> ingresá el nombre del sector y guardá.',
  '<b>✏️ Editar:</b> cambiá el nombre de un sector.',
  '<b>🗑️ Eliminar:</b> borra el sector (si no tiene empleados asignados).',
]
</script>

<style scoped>
.se-ov { position:fixed; inset:0; background:rgba(15,23,42,.55); backdrop-filter:blur(3px); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.se-md { width:min(440px,96vw); background:#fff; border-radius:16px; overflow:hidden; box-shadow:0 24px 60px rgba(0,0,0,.45); font-family:var(--font-sans,sans-serif); }
.se-fade-enter-active,.se-fade-leave-active{ transition:opacity .25s ease; }
.se-fade-enter-from,.se-fade-leave-to{ opacity:0; }
.se-pop-enter-active{ transition:transform .38s cubic-bezier(.2,.9,.3,1.2),opacity .3s ease; }
.se-pop-enter-from{ transform:translateY(30px) scale(.94); opacity:0; }
.se-hero { display:flex; align-items:center; gap:12px; padding:14px 18px; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; }
.se-hero-ico { font-size:26px; animation:se-lat 2.4s ease-in-out infinite; }
@keyframes se-lat{0%,100%{transform:scale(1)}50%{transform:scale(1.12)}}
.se-hero-tx h3 { margin:0; font-size:15px; } .se-hero-tx p { margin:2px 0 0; font-size:11px; opacity:.85; }
.se-x { margin-left:auto; background:rgba(255,255,255,.15); border:none; color:#fff; width:28px; height:28px; border-radius:8px; font-size:14px; cursor:pointer; }
.se-x:hover{ background:rgba(255,255,255,.3); }
.se-body { padding:18px 22px; }
.se-intro { font-size:14px; color:#374151; line-height:1.55; margin:0 0 14px; }
.se-pasos { background:#f8fafc; border:1px solid #eef2f7; border-radius:12px; padding:12px 16px; margin-bottom:12px; }
.se-pasos-t { font-size:12px; font-weight:700; color:#1b4332; text-transform:uppercase; letter-spacing:.5px; margin-bottom:10px; }
.se-pasos ol { margin:0; padding:0; list-style:none; }
.se-pasos li { display:flex; align-items:flex-start; gap:10px; font-size:13px; color:#374151; line-height:1.5; margin-bottom:8px; opacity:0; animation:se-pasoIn .4s ease forwards; }
.se-num { flex-shrink:0; width:22px; height:22px; border-radius:50%; background:#16a34a; color:#fff; font-size:12px; font-weight:700; display:flex; align-items:center; justify-content:center; }
@keyframes se-pasoIn{ from{opacity:0;transform:translateX(-8px)} to{opacity:1;transform:none} }
.se-notas { margin:0; padding-left:6px; list-style:none; }
.se-notas li { font-size:13px; color:#475569; line-height:1.55; margin-bottom:6px; padding-left:24px; position:relative; }
.se-notas li::before { content:'💡'; position:absolute; left:0; }
</style>
