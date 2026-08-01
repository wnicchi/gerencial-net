<!-- SubsectoresAyuda.vue — Modal de ayuda del módulo Sub-Sectores Laborales. -->
<template>
  <Teleport to="body">
    <transition name="su-fade" appear>
      <div class="su-ov" @click.self="$emit('close')">
        <transition name="su-pop" appear>
          <div class="su-md">
            <div class="su-hero">
              <div class="su-hero-ico">🏭</div>
              <div class="su-hero-tx">
                <h3>Ayuda — Sub-Sectores Laborales</h3>
                <p>Funciones laborales dentro del sector</p>
              </div>
              <button class="su-x" @click="$emit('close')">✕</button>
            </div>

            <div class="su-body">
              <p class="su-intro">Acá se administran los <b>sub-sectores</b> (funciones laborales dentro de un sector) que después se asignan a los empleados.</p>

              <div class="su-pasos">
                <div class="su-pasos-t">⚡ Cómo se usa</div>
                <ol>
                  <li v-for="(p, i) in pasos" :key="i" :style="{ animationDelay: (i*90+120)+'ms' }">
                    <span class="su-num">{{ i+1 }}</span><span v-html="p"></span>
                  </li>
                </ol>
              </div>

              <ul class="su-notas">
                <li>El <b>código</b> lo asigna el sistema solo; el <b>nombre</b> es obligatorio.</li>
                <li>No se puede <b>eliminar</b> un sub-sector que tenga empleados asignados.</li>
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
  '<b>＋ Nuevo:</b> ingresá el nombre del sub-sector y guardá.',
  '<b>✏️ Editar:</b> cambiá el nombre de un sub-sector.',
  '<b>🗑️ Eliminar:</b> borra el sub-sector (si no tiene empleados asignados).',
]
</script>

<style scoped>
.su-ov { position:fixed; inset:0; background:rgba(15,23,42,.55); backdrop-filter:blur(3px); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.su-md { width:min(440px,96vw); background:#fff; border-radius:16px; overflow:hidden; box-shadow:0 24px 60px rgba(0,0,0,.45); font-family:var(--font-sans,sans-serif); }
.su-fade-enter-active,.su-fade-leave-active{ transition:opacity .25s ease; }
.su-fade-enter-from,.su-fade-leave-to{ opacity:0; }
.su-pop-enter-active{ transition:transform .38s cubic-bezier(.2,.9,.3,1.2),opacity .3s ease; }
.su-pop-enter-from{ transform:translateY(30px) scale(.94); opacity:0; }
.su-hero { display:flex; align-items:center; gap:12px; padding:14px 18px; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; }
.su-hero-ico { font-size:26px; animation:su-lat 2.4s ease-in-out infinite; }
@keyframes su-lat{0%,100%{transform:scale(1)}50%{transform:scale(1.12)}}
.su-hero-tx h3 { margin:0; font-size:15px; } .su-hero-tx p { margin:2px 0 0; font-size:11px; opacity:.85; }
.su-x { margin-left:auto; background:rgba(255,255,255,.15); border:none; color:#fff; width:28px; height:28px; border-radius:8px; font-size:14px; cursor:pointer; }
.su-x:hover{ background:rgba(255,255,255,.3); }
.su-body { padding:18px 22px; }
.su-intro { font-size:14px; color:#374151; line-height:1.55; margin:0 0 14px; }
.su-pasos { background:#f8fafc; border:1px solid #eef2f7; border-radius:12px; padding:12px 16px; margin-bottom:12px; }
.su-pasos-t { font-size:12px; font-weight:700; color:#1b4332; text-transform:uppercase; letter-spacing:.5px; margin-bottom:10px; }
.su-pasos ol { margin:0; padding:0; list-style:none; }
.su-pasos li { display:flex; align-items:flex-start; gap:10px; font-size:13px; color:#374151; line-height:1.5; margin-bottom:8px; opacity:0; animation:su-pasoIn .4s ease forwards; }
.su-num { flex-shrink:0; width:22px; height:22px; border-radius:50%; background:#16a34a; color:#fff; font-size:12px; font-weight:700; display:flex; align-items:center; justify-content:center; }
@keyframes su-pasoIn{ from{opacity:0;transform:translateX(-8px)} to{opacity:1;transform:none} }
.su-notas { margin:0; padding-left:6px; list-style:none; }
.su-notas li { font-size:13px; color:#475569; line-height:1.55; margin-bottom:6px; padding-left:24px; position:relative; }
.su-notas li::before { content:'💡'; position:absolute; left:0; }
</style>
