<!-- InvitadosAyuda.vue — Modal de ayuda del módulo Invitados. -->
<template>
  <Teleport to="body">
    <transition name="iv-fade" appear>
      <div class="iv-ov" @click.self="$emit('close')">
        <transition name="iv-pop" appear>
          <div class="iv-md">
            <div class="iv-hero">
              <div class="iv-hero-ico">🙋</div>
              <div class="iv-hero-tx">
                <h3>Ayuda — Invitados</h3>
                <p>Agenda de invitados</p>
              </div>
              <button class="iv-x" @click="$emit('close')">✕</button>
            </div>

            <div class="iv-body">
              <p class="iv-intro">Este módulo es una <b>agenda de invitados</b>: podés agregarlos, editarlos y eliminarlos.</p>

              <div class="iv-pasos">
                <div class="iv-pasos-t">⚡ Cómo se usa</div>
                <ol>
                  <li v-for="(p, i) in pasos" :key="i" :style="{ animationDelay: (i*90+120)+'ms' }">
                    <span class="iv-num">{{ i+1 }}</span><span v-html="p"></span>
                  </li>
                </ol>
              </div>

              <ul class="iv-notas">
                <li>El <b>código</b> lo asigna el sistema solo (no se edita).</li>
                <li>El <b>nombre</b> es obligatorio; el resto de los datos son opcionales.</li>
                <li>Usá el <b>buscador</b> para encontrar un invitado por nombre, domicilio o teléfono.</li>
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
  '<b>＋ Nuevo:</b> cargá nombre, domicilio, teléfono, celular y notas.',
  '<b>✏️ Editar:</b> modificá los datos de un invitado.',
  '<b>🗑️ Eliminar:</b> borra el invitado de la agenda.',
]
</script>

<style scoped>
.iv-ov { position:fixed; inset:0; background:rgba(15,23,42,.55); backdrop-filter:blur(3px); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.iv-md { width:min(460px,96vw); background:#fff; border-radius:16px; overflow:hidden; box-shadow:0 24px 60px rgba(0,0,0,.45); font-family:var(--font-sans,sans-serif); }
.iv-fade-enter-active,.iv-fade-leave-active{ transition:opacity .25s ease; }
.iv-fade-enter-from,.iv-fade-leave-to{ opacity:0; }
.iv-pop-enter-active{ transition:transform .38s cubic-bezier(.2,.9,.3,1.2),opacity .3s ease; }
.iv-pop-enter-from{ transform:translateY(30px) scale(.94); opacity:0; }
.iv-hero { display:flex; align-items:center; gap:12px; padding:14px 18px; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; }
.iv-hero-ico { font-size:26px; animation:iv-lat 2.4s ease-in-out infinite; }
@keyframes iv-lat{0%,100%{transform:scale(1)}50%{transform:scale(1.12)}}
.iv-hero-tx h3 { margin:0; font-size:15px; } .iv-hero-tx p { margin:2px 0 0; font-size:11px; opacity:.85; }
.iv-x { margin-left:auto; background:rgba(255,255,255,.15); border:none; color:#fff; width:28px; height:28px; border-radius:8px; font-size:14px; cursor:pointer; }
.iv-x:hover{ background:rgba(255,255,255,.3); }
.iv-body { padding:18px 22px; }
.iv-intro { font-size:14px; color:#374151; line-height:1.55; margin:0 0 14px; }
.iv-pasos { background:#f8fafc; border:1px solid #eef2f7; border-radius:12px; padding:12px 16px; margin-bottom:12px; }
.iv-pasos-t { font-size:12px; font-weight:700; color:#1b4332; text-transform:uppercase; letter-spacing:.5px; margin-bottom:10px; }
.iv-pasos ol { margin:0; padding:0; list-style:none; }
.iv-pasos li { display:flex; align-items:flex-start; gap:10px; font-size:13px; color:#374151; line-height:1.5; margin-bottom:8px; opacity:0; animation:iv-pasoIn .4s ease forwards; }
.iv-num { flex-shrink:0; width:22px; height:22px; border-radius:50%; background:#16a34a; color:#fff; font-size:12px; font-weight:700; display:flex; align-items:center; justify-content:center; }
@keyframes iv-pasoIn{ from{opacity:0;transform:translateX(-8px)} to{opacity:1;transform:none} }
.iv-notas { margin:0; padding-left:6px; list-style:none; }
.iv-notas li { font-size:13px; color:#475569; line-height:1.55; margin-bottom:6px; padding-left:24px; position:relative; }
.iv-notas li::before { content:'💡'; position:absolute; left:0; }
</style>
