<!-- ContratistasAyuda.vue — Modal de ayuda del módulo Contratistas. -->
<template>
  <Teleport to="body">
    <transition name="co-fade" appear>
      <div class="co-ov" @click.self="$emit('close')">
        <transition name="co-pop" appear>
          <div class="co-md">
            <div class="co-hero">
              <div class="co-hero-ico">🤝</div>
              <div class="co-hero-tx">
                <h3>Ayuda — Contratistas</h3>
                <p>ABM de contratistas</p>
              </div>
              <button class="co-x" @click="$emit('close')">✕</button>
            </div>

            <div class="co-body">
              <p class="co-intro">Acá se administran los <b>contratistas</b> que después se asignan a los empleados.</p>

              <div class="co-pasos">
                <div class="co-pasos-t">⚡ Cómo se usa</div>
                <ol>
                  <li v-for="(p, i) in pasos" :key="i" :style="{ animationDelay: (i*90+120)+'ms' }">
                    <span class="co-num">{{ i+1 }}</span><span v-html="p"></span>
                  </li>
                </ol>
              </div>

              <ul class="co-notas">
                <li>El <b>código</b> lo asigna el sistema solo; el <b>nombre</b> es obligatorio.</li>
                <li>El <b>código base de empleados nuevo</b> es el número desde el cual se numeran los empleados de ese contratista.</li>
                <li>No se puede <b>eliminar</b> un contratista que tenga empleados asignados.</li>
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
  '<b>＋ Nuevo:</b> cargá el nombre y el código base de empleados.',
  '<b>✏️ Editar:</b> modificá los datos de un contratista.',
  '<b>🗑️ Eliminar:</b> borra el contratista (si no tiene empleados asignados).',
]
</script>

<style scoped>
.co-ov { position:fixed; inset:0; background:rgba(15,23,42,.55); backdrop-filter:blur(3px); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.co-md { width:min(460px,96vw); background:#fff; border-radius:16px; overflow:hidden; box-shadow:0 24px 60px rgba(0,0,0,.45); font-family:var(--font-sans,sans-serif); }
.co-fade-enter-active,.co-fade-leave-active{ transition:opacity .25s ease; }
.co-fade-enter-from,.co-fade-leave-to{ opacity:0; }
.co-pop-enter-active{ transition:transform .38s cubic-bezier(.2,.9,.3,1.2),opacity .3s ease; }
.co-pop-enter-from{ transform:translateY(30px) scale(.94); opacity:0; }
.co-hero { display:flex; align-items:center; gap:12px; padding:14px 18px; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; }
.co-hero-ico { font-size:26px; animation:co-lat 2.4s ease-in-out infinite; }
@keyframes co-lat{0%,100%{transform:scale(1)}50%{transform:scale(1.12)}}
.co-hero-tx h3 { margin:0; font-size:15px; } .co-hero-tx p { margin:2px 0 0; font-size:11px; opacity:.85; }
.co-x { margin-left:auto; background:rgba(255,255,255,.15); border:none; color:#fff; width:28px; height:28px; border-radius:8px; font-size:14px; cursor:pointer; }
.co-x:hover{ background:rgba(255,255,255,.3); }
.co-body { padding:18px 22px; }
.co-intro { font-size:14px; color:#374151; line-height:1.55; margin:0 0 14px; }
.co-pasos { background:#f8fafc; border:1px solid #eef2f7; border-radius:12px; padding:12px 16px; margin-bottom:12px; }
.co-pasos-t { font-size:12px; font-weight:700; color:#1b4332; text-transform:uppercase; letter-spacing:.5px; margin-bottom:10px; }
.co-pasos ol { margin:0; padding:0; list-style:none; }
.co-pasos li { display:flex; align-items:flex-start; gap:10px; font-size:13px; color:#374151; line-height:1.5; margin-bottom:8px; opacity:0; animation:co-pasoIn .4s ease forwards; }
.co-num { flex-shrink:0; width:22px; height:22px; border-radius:50%; background:#16a34a; color:#fff; font-size:12px; font-weight:700; display:flex; align-items:center; justify-content:center; }
@keyframes co-pasoIn{ from{opacity:0;transform:translateX(-8px)} to{opacity:1;transform:none} }
.co-notas { margin:0; padding-left:6px; list-style:none; }
.co-notas li { font-size:13px; color:#475569; line-height:1.55; margin-bottom:6px; padding-left:24px; position:relative; }
.co-notas li::before { content:'💡'; position:absolute; left:0; }
</style>
