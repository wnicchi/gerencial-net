<!-- LugaresAyuda.vue — Modal de ayuda del módulo Lugares. -->
<template>
  <Teleport to="body">
    <transition name="lg-fade" appear>
      <div class="lg-ov" @click.self="$emit('close')">
        <transition name="lg-pop" appear>
          <div class="lg-md">
            <div class="lg-hero">
              <div class="lg-hero-ico">📍</div>
              <div class="lg-hero-tx">
                <h3>Ayuda — Lugares</h3>
                <p>ABM de lugares</p>
              </div>
              <button class="lg-x" @click="$emit('close')">✕</button>
            </div>

            <div class="lg-body">
              <p class="lg-intro">Acá se administran los <b>lugares</b> (localidades) que después se asignan a los empleados.</p>

              <div class="lg-pasos">
                <div class="lg-pasos-t">⚡ Cómo se usa</div>
                <ol>
                  <li v-for="(p, i) in pasos" :key="i" :style="{ animationDelay: (i*90+120)+'ms' }">
                    <span class="lg-num">{{ i+1 }}</span><span v-html="p"></span>
                  </li>
                </ol>
              </div>

              <ul class="lg-notas">
                <li>El <b>código</b> lo asigna el sistema solo; el <b>nombre</b> es obligatorio.</li>
                <li>No se puede <b>eliminar</b> un lugar que tenga empleados asignados.</li>
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
  '<b>＋ Nuevo:</b> ingresá el nombre del lugar y guardá.',
  '<b>✏️ Editar:</b> cambiá el nombre de un lugar.',
  '<b>🗑️ Eliminar:</b> borra el lugar (si no tiene empleados asignados).',
]
</script>

<style scoped>
.lg-ov { position:fixed; inset:0; background:rgba(15,23,42,.55); backdrop-filter:blur(3px); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.lg-md { width:min(440px,96vw); background:#fff; border-radius:16px; overflow:hidden; box-shadow:0 24px 60px rgba(0,0,0,.45); font-family:var(--font-sans,sans-serif); }
.lg-fade-enter-active,.lg-fade-leave-active{ transition:opacity .25s ease; }
.lg-fade-enter-from,.lg-fade-leave-to{ opacity:0; }
.lg-pop-enter-active{ transition:transform .38s cubic-bezier(.2,.9,.3,1.2),opacity .3s ease; }
.lg-pop-enter-from{ transform:translateY(30px) scale(.94); opacity:0; }
.lg-hero { display:flex; align-items:center; gap:12px; padding:14px 18px; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; }
.lg-hero-ico { font-size:26px; animation:lg-lat 2.4s ease-in-out infinite; }
@keyframes lg-lat{0%,100%{transform:scale(1)}50%{transform:scale(1.12)}}
.lg-hero-tx h3 { margin:0; font-size:15px; } .lg-hero-tx p { margin:2px 0 0; font-size:11px; opacity:.85; }
.lg-x { margin-left:auto; background:rgba(255,255,255,.15); border:none; color:#fff; width:28px; height:28px; border-radius:8px; font-size:14px; cursor:pointer; }
.lg-x:hover{ background:rgba(255,255,255,.3); }
.lg-body { padding:18px 22px; }
.lg-intro { font-size:14px; color:#374151; line-height:1.55; margin:0 0 14px; }
.lg-pasos { background:#f8fafc; border:1px solid #eef2f7; border-radius:12px; padding:12px 16px; margin-bottom:12px; }
.lg-pasos-t { font-size:12px; font-weight:700; color:#1b4332; text-transform:uppercase; letter-spacing:.5px; margin-bottom:10px; }
.lg-pasos ol { margin:0; padding:0; list-style:none; }
.lg-pasos li { display:flex; align-items:flex-start; gap:10px; font-size:13px; color:#374151; line-height:1.5; margin-bottom:8px; opacity:0; animation:lg-pasoIn .4s ease forwards; }
.lg-num { flex-shrink:0; width:22px; height:22px; border-radius:50%; background:#16a34a; color:#fff; font-size:12px; font-weight:700; display:flex; align-items:center; justify-content:center; }
@keyframes lg-pasoIn{ from{opacity:0;transform:translateX(-8px)} to{opacity:1;transform:none} }
.lg-notas { margin:0; padding-left:6px; list-style:none; }
.lg-notas li { font-size:13px; color:#475569; line-height:1.55; margin-bottom:6px; padding-left:24px; position:relative; }
.lg-notas li::before { content:'💡'; position:absolute; left:0; }
</style>
