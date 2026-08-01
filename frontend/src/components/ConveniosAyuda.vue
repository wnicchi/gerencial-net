<!-- ConveniosAyuda.vue — Modal de ayuda del módulo Convenios. -->
<template>
  <Teleport to="body">
    <transition name="cv-fade" appear>
      <div class="cv-ov" @click.self="$emit('close')">
        <transition name="cv-pop" appear>
          <div class="cv-md">
            <div class="cv-hero">
              <div class="cv-hero-ico">📃</div>
              <div class="cv-hero-tx">
                <h3>Ayuda — Convenios</h3>
                <p>Configuración y obligaciones de EPP</p>
              </div>
              <button class="cv-x" @click="$emit('close')">✕</button>
            </div>

            <div class="cv-body">
              <p class="cv-intro">Cada <b>convenio</b> tiene dos partes: su <b>configuración</b> y las
                <b>obligaciones de EPP</b> (ropa/elementos de protección) que exige.</p>

              <div class="cv-secs">
                <div class="cv-sec">
                  <div class="cv-sec-h">⚙️ Convenio Configuración</div>
                  <p>Descripción, <b>tipo</b> (Jornal o Mensualizado), % neto de horas extras, mínimo de horas de obra social (solo Jornal), si aplica días trabajados y las <b>horas normales máximas por día</b> (lunes a domingo).</p>
                </div>
                <div class="cv-sec">
                  <div class="cv-sec-h">🦺 Obligaciones de EPP</div>
                  <p>La lista de elementos que el convenio obliga a entregar, cada uno con los <b>días</b> para la próxima reposición. Se agregan eligiendo el EPP y los días, y se quitan tildando y eliminando.</p>
                </div>
              </div>

              <ul class="cv-notas">
                <li>El <b>código</b> lo asigna el sistema; primero guardá la configuración y después cargá las obligaciones.</li>
                <li>No se puede repetir el mismo EPP dos veces en un convenio.</li>
                <li>No se puede <b>eliminar</b> un convenio que tenga empleados asignados.</li>
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
</script>

<style scoped>
.cv-ov { position:fixed; inset:0; background:rgba(15,23,42,.55); backdrop-filter:blur(3px); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.cv-md { width:min(520px,96vw); max-height:92vh; overflow:auto; background:#fff; border-radius:16px; box-shadow:0 24px 60px rgba(0,0,0,.45); font-family:var(--font-sans,sans-serif); }
.cv-fade-enter-active,.cv-fade-leave-active{ transition:opacity .25s ease; }
.cv-fade-enter-from,.cv-fade-leave-to{ opacity:0; }
.cv-pop-enter-active{ transition:transform .38s cubic-bezier(.2,.9,.3,1.2),opacity .3s ease; }
.cv-pop-enter-from{ transform:translateY(30px) scale(.94); opacity:0; }
.cv-hero { display:flex; align-items:center; gap:12px; padding:14px 18px; position:sticky; top:0; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; }
.cv-hero-ico { font-size:26px; animation:cv-lat 2.4s ease-in-out infinite; }
@keyframes cv-lat{0%,100%{transform:scale(1)}50%{transform:scale(1.12)}}
.cv-hero-tx h3 { margin:0; font-size:15px; } .cv-hero-tx p { margin:2px 0 0; font-size:11px; opacity:.85; }
.cv-x { margin-left:auto; background:rgba(255,255,255,.15); border:none; color:#fff; width:28px; height:28px; border-radius:8px; font-size:14px; cursor:pointer; }
.cv-x:hover{ background:rgba(255,255,255,.3); }
.cv-body { padding:18px 22px; }
.cv-intro { font-size:14px; color:#374151; line-height:1.55; margin:0 0 14px; }
.cv-secs { display:flex; flex-direction:column; gap:10px; margin-bottom:14px; }
.cv-sec { background:#f8fafc; border:1px solid #eef2f7; border-radius:12px; padding:12px 16px; }
.cv-sec-h { font-size:12.5px; font-weight:700; color:#1b4332; margin-bottom:7px; }
.cv-sec p { margin:0; font-size:13px; color:#475569; line-height:1.5; }
.cv-notas { margin:0; padding-left:6px; list-style:none; }
.cv-notas li { font-size:13px; color:#475569; line-height:1.55; margin-bottom:6px; padding-left:24px; position:relative; }
.cv-notas li::before { content:'💡'; position:absolute; left:0; }
</style>
