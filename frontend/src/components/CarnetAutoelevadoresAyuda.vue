<!-- CarnetAutoelevadoresAyuda.vue — Modal de ayuda del módulo Carnet de Autoelevadores. -->
<template>
  <Teleport to="body">
    <transition name="ca-fade" appear>
      <div class="ca-ov" @click.self="$emit('close')">
        <transition name="ca-pop" appear>
          <div class="ca-md">
            <div class="ca-hero">
              <div class="ca-hero-ico">🪪</div>
              <div class="ca-hero-tx">
                <h3>Ayuda — Carnet de Autoelevadores</h3>
                <p>Emisión e impresión del carnet</p>
              </div>
              <button class="ca-x" @click="$emit('close')">✕</button>
            </div>

            <div class="ca-body">
              <p class="ca-intro">
                Este módulo imprime el <b>carnet de conductor de autoelevador</b> de un empleado,
                con su foto y los datos de habilitación.
              </p>

              <!-- Mini-vista del carnet -->
              <div class="ca-diag">
                <svg viewBox="0 0 300 150" width="100%" style="max-width:300px">
                  <rect x="8" y="10" width="284" height="130" rx="8" fill="#fff" stroke="#16a34a" stroke-width="2"/>
                  <text x="150" y="30" text-anchor="middle" font-size="11" font-weight="700" fill="#1b4332">CONDUCTOR DE AUTOELEVADOR</text>
                  <line x1="20" y1="38" x2="280" y2="38" stroke="#16a34a" stroke-width="1"/>
                  <rect x="210" y="48" width="68" height="72" rx="3" fill="#f0fdf4" stroke="#94a3b8" stroke-width="1.2"
                        style="animation:ca-pulso 2.4s ease-in-out infinite"/>
                  <text x="244" y="88" text-anchor="middle" font-size="9" fill="#94a3b8">foto</text>
                  <g fill="#475569" font-size="8.5">
                    <text x="24" y="56" font-weight="700" fill="#1b4332">Apellido y Nombre</text>
                    <text x="24" y="68">— — — — — —</text>
                    <text x="24" y="86" font-weight="700" fill="#1b4332">DNI</text>
                    <text x="24" y="104" font-weight="700" fill="#1b4332">Otorgamiento / Vencimiento</text>
                    <text x="24" y="122" font-weight="700" fill="#1b4332">Clasificación</text>
                  </g>
                </svg>
              </div>

              <div class="ca-pasos">
                <div class="ca-pasos-t">⚡ Cómo se usa</div>
                <ol>
                  <li v-for="(p, i) in pasos" :key="i" :style="{ animationDelay: (i*90+120)+'ms' }">
                    <span class="ca-num">{{ i+1 }}</span><span v-html="p"></span>
                  </li>
                </ol>
              </div>

              <ul class="ca-notas">
                <li>El carnet se <b>imprime al momento</b>: no queda guardado, así que cada vez completás los datos.</li>
                <li>Antes de imprimir se <b>previsualiza</b> en pantalla, para revisar foto y datos.</li>
                <li>La <b>clasificación</b> se escribe en mayúsculas (ej.: PRIMERA).</li>
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
  '<b>Elegí el empleado</b> buscándolo por código, legajo o nombre.',
  'Revisá que aparezca su <b>foto, nombre y DNI</b>.',
  'Cargá <b>Otorgado</b>, <b>Vence</b> y la <b>Clasificación</b>.',
  'Tocá <b>Imprimir Carnet</b>: se genera el PDF para revisar e imprimir.',
]
</script>

<style scoped>
.ca-ov { position:fixed; inset:0; background:rgba(15,23,42,.55); backdrop-filter:blur(3px); z-index:10000;
  display:flex; align-items:center; justify-content:center; padding:18px; }
.ca-md { width:min(520px,96vw); max-height:92vh; overflow:auto; background:#fff; border-radius:16px;
  box-shadow:0 24px 60px rgba(0,0,0,.45); font-family:var(--font-sans,sans-serif); }
.ca-fade-enter-active,.ca-fade-leave-active{ transition:opacity .25s ease; }
.ca-fade-enter-from,.ca-fade-leave-to{ opacity:0; }
.ca-pop-enter-active{ transition:transform .38s cubic-bezier(.2,.9,.3,1.2),opacity .3s ease; }
.ca-pop-enter-from{ transform:translateY(30px) scale(.94); opacity:0; }
.ca-hero { display:flex; align-items:center; gap:12px; padding:14px 18px; position:sticky; top:0;
  background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; }
.ca-hero-ico { font-size:26px; animation:ca-lat 2.4s ease-in-out infinite; }
@keyframes ca-lat{0%,100%{transform:scale(1)}50%{transform:scale(1.12)}}
.ca-hero-tx h3 { margin:0; font-size:15px; }
.ca-hero-tx p { margin:2px 0 0; font-size:11px; opacity:.85; }
.ca-x { margin-left:auto; background:rgba(255,255,255,.15); border:none; color:#fff; width:28px; height:28px;
  border-radius:8px; font-size:14px; cursor:pointer; }
.ca-x:hover{ background:rgba(255,255,255,.3); }
.ca-body { padding:18px 22px; }
.ca-intro { font-size:14px; color:#374151; line-height:1.55; margin:0 0 14px; }
.ca-diag { display:flex; justify-content:center; background:#f8fafc; border:1px solid #eef2f7; border-radius:12px; padding:12px; margin-bottom:14px; }
@keyframes ca-pulso{0%,100%{opacity:.7}50%{opacity:1}}
.ca-pasos { background:#f8fafc; border:1px solid #eef2f7; border-radius:12px; padding:12px 16px; margin-bottom:14px; }
.ca-pasos-t { font-size:12px; font-weight:700; color:#1b4332; text-transform:uppercase; letter-spacing:.5px; margin-bottom:10px; }
.ca-pasos ol { margin:0; padding:0; list-style:none; }
.ca-pasos li { display:flex; align-items:flex-start; gap:10px; font-size:13px; color:#374151; line-height:1.5;
  margin-bottom:8px; opacity:0; animation:ca-pasoIn .4s ease forwards; }
.ca-num { flex-shrink:0; width:22px; height:22px; border-radius:50%; background:#16a34a; color:#fff;
  font-size:12px; font-weight:700; display:flex; align-items:center; justify-content:center; }
@keyframes ca-pasoIn{ from{opacity:0;transform:translateX(-8px)} to{opacity:1;transform:none} }
.ca-notas { margin:0; padding-left:6px; list-style:none; }
.ca-notas li { font-size:13px; color:#475569; line-height:1.55; margin-bottom:6px; padding-left:24px; position:relative; }
.ca-notas li::before { content:'💡'; position:absolute; left:0; }
</style>
