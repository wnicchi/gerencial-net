<!-- TipoDocAyuda.vue — Modal de ayuda del módulo Tipo de Documentación. -->
<template>
  <Teleport to="body">
    <transition name="td-fade" appear>
      <div class="td-ov" @click.self="$emit('close')">
        <transition name="td-pop" appear>
          <div class="td-md">
            <div class="td-hero">
              <div class="td-hero-ico">📄</div>
              <div class="td-hero-tx">
                <h3>Ayuda — Tipo de Documentación</h3>
                <p>Categorías para los documentos</p>
              </div>
              <button class="td-x" @click="$emit('close')">✕</button>
            </div>

            <div class="td-body">
              <p class="td-intro">
                Acá se definen los <b>tipos de documentación</b> que después se usan al adjuntar
                documentos (por ejemplo, una licencia, un examen o un documento del empleado).
              </p>

              <div class="td-pasos">
                <div class="td-pasos-t">⚡ Cómo se usa</div>
                <ol>
                  <li v-for="(p, i) in pasos" :key="i" :style="{ animationDelay: (i*90+120)+'ms' }">
                    <span class="td-num">{{ i+1 }}</span><span v-html="p"></span>
                  </li>
                </ol>
              </div>

              <ul class="td-notas">
                <li>El <b>código</b> lo ingresás vos (corto, ej. “AU”) y no se puede repetir.</li>
                <li>El <b>Tipo</b> indica a qué grupo pertenece (Empleados, Licencias, Exámenes, etc.).</li>
                <li>Usá el <b>buscador</b> para encontrar un tipo por código o detalle.</li>
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
  '<b>＋ Nuevo:</b> ingresá código, detalle y elegí el Tipo.',
  '<b>✏️ Editar:</b> cambiá el detalle o el tipo de un registro.',
  '<b>🗑️ Eliminar:</b> borra el tipo de documentación.',
]
</script>

<style scoped>
.td-ov { position:fixed; inset:0; background:rgba(15,23,42,.55); backdrop-filter:blur(3px); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.td-md { width:min(460px,96vw); background:#fff; border-radius:16px; overflow:hidden; box-shadow:0 24px 60px rgba(0,0,0,.45); font-family:var(--font-sans,sans-serif); }
.td-fade-enter-active,.td-fade-leave-active{ transition:opacity .25s ease; }
.td-fade-enter-from,.td-fade-leave-to{ opacity:0; }
.td-pop-enter-active{ transition:transform .38s cubic-bezier(.2,.9,.3,1.2),opacity .3s ease; }
.td-pop-enter-from{ transform:translateY(30px) scale(.94); opacity:0; }
.td-hero { display:flex; align-items:center; gap:12px; padding:14px 18px; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; }
.td-hero-ico { font-size:26px; animation:td-lat 2.4s ease-in-out infinite; }
@keyframes td-lat{0%,100%{transform:scale(1)}50%{transform:scale(1.12)}}
.td-hero-tx h3 { margin:0; font-size:15px; } .td-hero-tx p { margin:2px 0 0; font-size:11px; opacity:.85; }
.td-x { margin-left:auto; background:rgba(255,255,255,.15); border:none; color:#fff; width:28px; height:28px; border-radius:8px; font-size:14px; cursor:pointer; }
.td-x:hover{ background:rgba(255,255,255,.3); }
.td-body { padding:18px 22px; }
.td-intro { font-size:14px; color:#374151; line-height:1.55; margin:0 0 14px; }
.td-pasos { background:#f8fafc; border:1px solid #eef2f7; border-radius:12px; padding:12px 16px; margin-bottom:12px; }
.td-pasos-t { font-size:12px; font-weight:700; color:#1b4332; text-transform:uppercase; letter-spacing:.5px; margin-bottom:10px; }
.td-pasos ol { margin:0; padding:0; list-style:none; }
.td-pasos li { display:flex; align-items:flex-start; gap:10px; font-size:13px; color:#374151; line-height:1.5; margin-bottom:8px; opacity:0; animation:td-pasoIn .4s ease forwards; }
.td-num { flex-shrink:0; width:22px; height:22px; border-radius:50%; background:#16a34a; color:#fff; font-size:12px; font-weight:700; display:flex; align-items:center; justify-content:center; }
@keyframes td-pasoIn{ from{opacity:0;transform:translateX(-8px)} to{opacity:1;transform:none} }
.td-notas { margin:0; padding-left:6px; list-style:none; }
.td-notas li { font-size:13px; color:#475569; line-height:1.55; margin-bottom:6px; padding-left:24px; position:relative; }
.td-notas li::before { content:'💡'; position:absolute; left:0; }
</style>
