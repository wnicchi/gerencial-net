<!-- EmpresasAyuda.vue — Modal de ayuda del módulo Empresas. -->
<template>
  <Teleport to="body">
    <transition name="em-fade" appear>
      <div class="em-ov" @click.self="$emit('close')">
        <transition name="em-pop" appear>
          <div class="em-md">
            <div class="em-hero">
              <div class="em-hero-ico">🏢</div>
              <div class="em-hero-tx">
                <h3>Ayuda — Empresas</h3>
                <p>ABM de empresas del grupo</p>
              </div>
              <button class="em-x" @click="$emit('close')">✕</button>
            </div>

            <div class="em-body">
              <p class="em-intro">Acá se administran las <b>empresas del grupo</b> con todos sus datos generales,
                indicadores impositivos y los códigos bancarios.</p>

              <div class="em-pasos">
                <div class="em-pasos-t">⚡ Cómo se usa</div>
                <ol>
                  <li v-for="(p, i) in pasos" :key="i" :style="{ animationDelay: (i*90+120)+'ms' }">
                    <span class="em-num">{{ i+1 }}</span><span v-html="p"></span>
                  </li>
                </ol>
              </div>

              <ul class="em-notas">
                <li>El <b>código</b> lo asigna el sistema solo; el <b>nombre</b> es obligatorio.</li>
                <li>La <b>Base de Datos Relacionada</b> indica si la empresa es de SILCAR o de Logística.</li>
                <li>No se puede <b>eliminar</b> una empresa que tenga empleados asignados.</li>
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
  '<b>＋ Nueva:</b> cargá datos generales, indicadores Sí/No y códigos bancarios.',
  '<b>✏️ Editar:</b> modificá los datos de una empresa.',
  '<b>🗑️ Eliminar:</b> borra la empresa (si no tiene empleados asignados).',
]
</script>

<style scoped>
.em-ov { position:fixed; inset:0; background:rgba(15,23,42,.55); backdrop-filter:blur(3px); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.em-md { width:min(460px,96vw); background:#fff; border-radius:16px; overflow:hidden; box-shadow:0 24px 60px rgba(0,0,0,.45); font-family:var(--font-sans,sans-serif); }
.em-fade-enter-active,.em-fade-leave-active{ transition:opacity .25s ease; }
.em-fade-enter-from,.em-fade-leave-to{ opacity:0; }
.em-pop-enter-active{ transition:transform .38s cubic-bezier(.2,.9,.3,1.2),opacity .3s ease; }
.em-pop-enter-from{ transform:translateY(30px) scale(.94); opacity:0; }
.em-hero { display:flex; align-items:center; gap:12px; padding:14px 18px; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; }
.em-hero-ico { font-size:26px; animation:em-lat 2.4s ease-in-out infinite; }
@keyframes em-lat{0%,100%{transform:scale(1)}50%{transform:scale(1.12)}}
.em-hero-tx h3 { margin:0; font-size:15px; } .em-hero-tx p { margin:2px 0 0; font-size:11px; opacity:.85; }
.em-x { margin-left:auto; background:rgba(255,255,255,.15); border:none; color:#fff; width:28px; height:28px; border-radius:8px; font-size:14px; cursor:pointer; }
.em-x:hover{ background:rgba(255,255,255,.3); }
.em-body { padding:18px 22px; }
.em-intro { font-size:14px; color:#374151; line-height:1.55; margin:0 0 14px; }
.em-pasos { background:#f8fafc; border:1px solid #eef2f7; border-radius:12px; padding:12px 16px; margin-bottom:12px; }
.em-pasos-t { font-size:12px; font-weight:700; color:#1b4332; text-transform:uppercase; letter-spacing:.5px; margin-bottom:10px; }
.em-pasos ol { margin:0; padding:0; list-style:none; }
.em-pasos li { display:flex; align-items:flex-start; gap:10px; font-size:13px; color:#374151; line-height:1.5; margin-bottom:8px; opacity:0; animation:em-pasoIn .4s ease forwards; }
.em-num { flex-shrink:0; width:22px; height:22px; border-radius:50%; background:#16a34a; color:#fff; font-size:12px; font-weight:700; display:flex; align-items:center; justify-content:center; }
@keyframes em-pasoIn{ from{opacity:0;transform:translateX(-8px)} to{opacity:1;transform:none} }
.em-notas { margin:0; padding-left:6px; list-style:none; }
.em-notas li { font-size:13px; color:#475569; line-height:1.55; margin-bottom:6px; padding-left:24px; position:relative; }
.em-notas li::before { content:'💡'; position:absolute; left:0; }
</style>
