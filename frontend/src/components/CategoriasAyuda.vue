<!-- CategoriasAyuda.vue — Modal de ayuda del módulo Categorías. -->
<template>
  <Teleport to="body">
    <transition name="ca-fade" appear>
      <div class="ca-ov" @click.self="$emit('close')">
        <transition name="ca-pop" appear>
          <div class="ca-md">
            <div class="ca-hero">
              <div class="ca-hero-ico">🏷️</div>
              <div class="ca-hero-tx">
                <h3>Ayuda — Categorías</h3>
                <p>ABM de categorías laborales</p>
              </div>
              <button class="ca-x" @click="$emit('close')">✕</button>
            </div>

            <div class="ca-body">
              <p class="ca-intro">Acá se administran las <b>categorías</b> laborales que después se asignan a los empleados.
                Cada categoría pertenece a un <b>convenio</b> y tiene su <b>sueldo básico</b>.</p>

              <div class="ca-pasos">
                <div class="ca-pasos-t">⚡ Cómo se usa</div>
                <ol>
                  <li v-for="(p, i) in pasos" :key="i" :style="{ animationDelay: (i*90+120)+'ms' }">
                    <span class="ca-num">{{ i+1 }}</span><span v-html="p"></span>
                  </li>
                </ol>
              </div>

              <ul class="ca-notas">
                <li>El <b>código</b> lo asigna el sistema solo; la <b>descripción</b> y el <b>convenio</b> son obligatorios.</li>
                <li>No se puede <b>eliminar</b> una categoría que tenga empleados asignados.</li>
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
  '<b>＋ Nueva:</b> ingresá la descripción, elegí el convenio y el sueldo básico.',
  '<b>✏️ Editar:</b> modificá los datos de una categoría.',
  '<b>🗑️ Eliminar:</b> borra la categoría (si no tiene empleados asignados).',
]
</script>

<style scoped>
.ca-ov { position:fixed; inset:0; background:rgba(15,23,42,.55); backdrop-filter:blur(3px); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.ca-md { width:min(460px,96vw); background:#fff; border-radius:16px; overflow:hidden; box-shadow:0 24px 60px rgba(0,0,0,.45); font-family:var(--font-sans,sans-serif); }
.ca-fade-enter-active,.ca-fade-leave-active{ transition:opacity .25s ease; }
.ca-fade-enter-from,.ca-fade-leave-to{ opacity:0; }
.ca-pop-enter-active{ transition:transform .38s cubic-bezier(.2,.9,.3,1.2),opacity .3s ease; }
.ca-pop-enter-from{ transform:translateY(30px) scale(.94); opacity:0; }
.ca-hero { display:flex; align-items:center; gap:12px; padding:14px 18px; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; }
.ca-hero-ico { font-size:26px; animation:ca-lat 2.4s ease-in-out infinite; }
@keyframes ca-lat{0%,100%{transform:scale(1)}50%{transform:scale(1.12)}}
.ca-hero-tx h3 { margin:0; font-size:15px; } .ca-hero-tx p { margin:2px 0 0; font-size:11px; opacity:.85; }
.ca-x { margin-left:auto; background:rgba(255,255,255,.15); border:none; color:#fff; width:28px; height:28px; border-radius:8px; font-size:14px; cursor:pointer; }
.ca-x:hover{ background:rgba(255,255,255,.3); }
.ca-body { padding:18px 22px; }
.ca-intro { font-size:14px; color:#374151; line-height:1.55; margin:0 0 14px; }
.ca-pasos { background:#f8fafc; border:1px solid #eef2f7; border-radius:12px; padding:12px 16px; margin-bottom:12px; }
.ca-pasos-t { font-size:12px; font-weight:700; color:#1b4332; text-transform:uppercase; letter-spacing:.5px; margin-bottom:10px; }
.ca-pasos ol { margin:0; padding:0; list-style:none; }
.ca-pasos li { display:flex; align-items:flex-start; gap:10px; font-size:13px; color:#374151; line-height:1.5; margin-bottom:8px; opacity:0; animation:ca-pasoIn .4s ease forwards; }
.ca-num { flex-shrink:0; width:22px; height:22px; border-radius:50%; background:#16a34a; color:#fff; font-size:12px; font-weight:700; display:flex; align-items:center; justify-content:center; }
@keyframes ca-pasoIn{ from{opacity:0;transform:translateX(-8px)} to{opacity:1;transform:none} }
.ca-notas { margin:0; padding-left:6px; list-style:none; }
.ca-notas li { font-size:13px; color:#475569; line-height:1.55; margin-bottom:6px; padding-left:24px; position:relative; }
.ca-notas li::before { content:'💡'; position:absolute; left:0; }
</style>
