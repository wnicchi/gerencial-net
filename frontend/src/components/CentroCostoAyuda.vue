<!-- CentroCostoAyuda.vue — Modal de ayuda del módulo Carga de Centro de Costo. -->
<template>
  <Teleport to="body">
    <transition name="cc-fade" appear>
      <div class="cc-ov" @click.self="$emit('close')">
        <transition name="cc-pop" appear>
          <div class="cc-md">
            <div class="cc-hero">
              <div class="cc-hero-ico">💼</div>
              <div class="cc-hero-tx">
                <h3>Ayuda — Centro de Costo</h3>
                <p>Distribución del costo por período</p>
              </div>
              <button class="cc-x" @click="$emit('close')">✕</button>
            </div>

            <div class="cc-body">
              <p class="cc-intro">
                Este módulo reparte el <b>costo de un empleado</b> entre uno o varios
                <b>centros de costo</b>, mes a mes.
              </p>

              <!-- Diagrama -->
              <div class="cc-diag">
                <svg viewBox="0 0 300 110" width="100%" style="max-width:300px">
                  <rect x="8" y="20" width="96" height="70" rx="8" fill="#fff" stroke="#16a34a" stroke-width="1.5"/>
                  <text x="56" y="16" text-anchor="middle" font-size="9" font-weight="700" fill="#1b4332">Períodos</text>
                  <rect x="16" y="30" width="80" height="14" rx="3" fill="#dcfce7"
                        style="animation:cc-pulso 2.4s ease-in-out infinite"/>
                  <text x="56" y="40" text-anchor="middle" font-size="8" fill="#166534">06 / 2026</text>
                  <rect x="16" y="48" width="80" height="12" rx="3" fill="#f1f5f9"/>
                  <rect x="16" y="63" width="80" height="12" rx="3" fill="#f1f5f9"/>
                  <path d="M108 55 L130 55" stroke="#16a34a" stroke-width="2" fill="none" stroke-linecap="round" style="animation:cc-flecha 1.8s ease-in-out infinite"/>
                  <path d="M124 50 L132 55 L124 60" stroke="#16a34a" stroke-width="2" fill="none" stroke-linecap="round" style="animation:cc-flecha 1.8s ease-in-out infinite"/>
                  <rect x="135" y="20" width="157" height="70" rx="8" fill="#fff" stroke="#94a3b8" stroke-width="1.5"/>
                  <text x="213" y="16" text-anchor="middle" font-size="9" font-weight="700" fill="#1b4332">Centros de costo (%)</text>
                  <g font-size="8" fill="#475569">
                    <text x="143" y="38">338 · Contenedores</text><text x="278" y="38" text-anchor="end" font-weight="700">60%</text>
                    <text x="143" y="54">101 · Generales</text><text x="278" y="54" text-anchor="end" font-weight="700">40%</text>
                    <line x1="143" y1="70" x2="284" y2="70" stroke="#e2e8f0"/>
                    <text x="143" y="83" font-weight="700" fill="#16a34a">Total</text><text x="278" y="83" text-anchor="end" font-weight="700" fill="#16a34a">100%</text>
                  </g>
                </svg>
              </div>

              <div class="cc-pasos">
                <div class="cc-pasos-t">⚡ Cómo se usa</div>
                <ol>
                  <li v-for="(p, i) in pasos" :key="i" :style="{ animationDelay: (i*90+120)+'ms' }">
                    <span class="cc-num">{{ i+1 }}</span><span v-html="p"></span>
                  </li>
                </ol>
              </div>

              <ul class="cc-notas">
                <li>Cada <b>período</b> (mes/año) se maneja por separado: elegís uno o creás uno nuevo.</li>
                <li><b>Confirmar</b> reemplaza la distribución de ese período por lo que quedó en pantalla.</li>
                <li>La suma de porcentajes suele ser <b>100%</b> (el total del costo del empleado en el mes).</li>
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
  '<b>Elegí un período</b> de la izquierda, o tocá <b>＋ Nuevo</b> y cargá mes y año.',
  '<b>Agregá centros de costo</b> con su porcentaje, o quitá los que no van.',
  'Tocá <b>Confirmar C.Costo</b> y aceptá el período para guardar.',
]
</script>

<style scoped>
.cc-ov { position:fixed; inset:0; background:rgba(15,23,42,.55); backdrop-filter:blur(3px); z-index:10000;
  display:flex; align-items:center; justify-content:center; padding:18px; }
.cc-md { width:min(540px,96vw); max-height:92vh; overflow:auto; background:#fff; border-radius:16px;
  box-shadow:0 24px 60px rgba(0,0,0,.45); font-family:var(--font-sans,sans-serif); }
.cc-fade-enter-active,.cc-fade-leave-active{ transition:opacity .25s ease; }
.cc-fade-enter-from,.cc-fade-leave-to{ opacity:0; }
.cc-pop-enter-active{ transition:transform .38s cubic-bezier(.2,.9,.3,1.2),opacity .3s ease; }
.cc-pop-enter-from{ transform:translateY(30px) scale(.94); opacity:0; }
.cc-hero { display:flex; align-items:center; gap:12px; padding:14px 18px; position:sticky; top:0;
  background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; }
.cc-hero-ico { font-size:26px; animation:cc-lat 2.4s ease-in-out infinite; }
@keyframes cc-lat{0%,100%{transform:scale(1)}50%{transform:scale(1.12)}}
.cc-hero-tx h3 { margin:0; font-size:15px; }
.cc-hero-tx p { margin:2px 0 0; font-size:11px; opacity:.85; }
.cc-x { margin-left:auto; background:rgba(255,255,255,.15); border:none; color:#fff; width:28px; height:28px;
  border-radius:8px; font-size:14px; cursor:pointer; }
.cc-x:hover{ background:rgba(255,255,255,.3); }
.cc-body { padding:18px 22px; }
.cc-intro { font-size:14px; color:#374151; line-height:1.55; margin:0 0 14px; }
.cc-diag { display:flex; justify-content:center; background:#f8fafc; border:1px solid #eef2f7; border-radius:12px; padding:12px; margin-bottom:14px; }
@keyframes cc-flecha{0%,100%{opacity:.5}50%{opacity:1}}
@keyframes cc-pulso{0%,100%{opacity:.75}50%{opacity:1}}
.cc-pasos { background:#f8fafc; border:1px solid #eef2f7; border-radius:12px; padding:12px 16px; margin-bottom:14px; }
.cc-pasos-t { font-size:12px; font-weight:700; color:#1b4332; text-transform:uppercase; letter-spacing:.5px; margin-bottom:10px; }
.cc-pasos ol { margin:0; padding:0; list-style:none; }
.cc-pasos li { display:flex; align-items:flex-start; gap:10px; font-size:13px; color:#374151; line-height:1.5;
  margin-bottom:8px; opacity:0; animation:cc-pasoIn .4s ease forwards; }
.cc-num { flex-shrink:0; width:22px; height:22px; border-radius:50%; background:#16a34a; color:#fff;
  font-size:12px; font-weight:700; display:flex; align-items:center; justify-content:center; }
@keyframes cc-pasoIn{ from{opacity:0;transform:translateX(-8px)} to{opacity:1;transform:none} }
.cc-notas { margin:0; padding-left:6px; list-style:none; }
.cc-notas li { font-size:13px; color:#475569; line-height:1.55; margin-bottom:6px; padding-left:24px; position:relative; }
.cc-notas li::before { content:'💡'; position:absolute; left:0; }
</style>
