<!-- EmpleadosListadosAyuda.vue — Modal de ayuda del módulo Listados de Empleados. -->
<template>
  <Teleport to="body">
    <transition name="li-fade" appear>
      <div class="li-ov" @click.self="$emit('close')">
        <transition name="li-pop" appear>
          <div class="li-md">
            <div class="li-hero">
              <div class="li-hero-ico">📊</div>
              <div class="li-hero-tx">
                <h3>Ayuda — Listado de Empleados</h3>
                <p>Filtrar, ordenar e imprimir</p>
              </div>
              <button class="li-x" @click="$emit('close')">✕</button>
            </div>

            <div class="li-body">
              <p class="li-intro">
                Este módulo arma un <b>listado de empleados</b> a tu medida: elegís
                <b>qué empleados</b> incluir (filtros), <b>en qué orden</b> aparecen y
                <b>cómo querés la salida</b>.
              </p>

              <!-- Diagrama del flujo -->
              <div class="li-diag">
                <svg viewBox="0 0 320 96" width="100%" style="max-width:340px">
                  <g v-for="(p, i) in flujo" :key="i">
                    <rect :x="p.x" y="32" width="78" height="34" rx="8" fill="#fff" stroke="#16a34a" stroke-width="1.4"
                          :style="{ animation:`pulso 2.4s ${i*0.3}s ease-in-out infinite` }"/>
                    <text :x="p.x + 39" y="46" text-anchor="middle" font-size="9.5" fill="#1b4332" font-weight="700">{{ p.a }}</text>
                    <text :x="p.x + 39" y="58" text-anchor="middle" font-size="8" fill="#475569">{{ p.b }}</text>
                    <g v-if="i < flujo.length - 1">
                      <path :d="`M${p.x+80} 49 L${p.x+98} 49`" stroke="#16a34a" stroke-width="2" fill="none" stroke-linecap="round"
                            style="animation:flecha 1.8s ease-in-out infinite"/>
                      <path :d="`M${p.x+92} 44 L${p.x+100} 49 L${p.x+92} 54`" stroke="#16a34a" stroke-width="2" fill="none" stroke-linecap="round"
                            style="animation:flecha 1.8s ease-in-out infinite"/>
                    </g>
                  </g>
                </svg>
              </div>

              <div class="li-pasos">
                <div class="li-pasos-t">⚡ Cómo se usa</div>
                <ol>
                  <li v-for="(p, i) in pasos" :key="i" :style="{ animationDelay: (i*90+120)+'ms' }">
                    <span class="li-num">{{ i+1 }}</span><span v-html="p"></span>
                  </li>
                </ol>
              </div>

              <div class="li-sal">
                <div class="li-sal-card">
                  <div class="li-sal-ico">📄</div>
                  <b>PDF simple</b>
                  <span>Una grilla con los datos principales, lista para imprimir.</span>
                </div>
                <div class="li-sal-card">
                  <div class="li-sal-ico">🖼️</div>
                  <b>PDF con fotos</b>
                  <span>Una ficha por empleado con su foto (tarda un poco más).</span>
                </div>
                <div class="li-sal-card">
                  <div class="li-sal-ico">📤</div>
                  <b>Excel</b>
                  <span>Descarga la información en una planilla para trabajarla aparte.</span>
                </div>
              </div>

              <ul class="li-notas">
                <li>El orden <b>“Día y mes de nacimiento”</b> es ideal para sacar los <b>cumpleaños</b> del período.</li>
                <li>Cada filtro que dejes en <b>“Todos”</b> no restringe: incluís a todos en esa categoría.</li>
                <li>Si la combinación de filtros no encuentra empleados, te avisa y no genera el listado.</li>
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
const flujo = [
  { x: 6,   a: 'Filtros', b: 'a quiénes' },
  { x: 121, a: 'Orden',   b: 'cómo se listan' },
  { x: 236, a: 'Salida',  b: 'PDF / Excel' },
]
const pasos = [
  '<b>Estado:</b> elegí entre empleados <b>en actividad</b> o <b>dados de baja</b>.',
  '<b>Orden:</b> alfabético, por código, por ingreso, por cumpleaños o por fecha de baja.',
  '<b>Filtros:</b> acotá por empresa, contratista, lugar, convenio, categoría, sector o sub-sector.',
  '<b>Salida:</b> elegí PDF simple, PDF con fotos o Excel y se genera al instante.',
]
</script>

<style scoped>
.li-ov { position:fixed; inset:0; background:rgba(15,23,42,.55); backdrop-filter:blur(3px); z-index:10000;
  display:flex; align-items:center; justify-content:center; padding:18px; }
.li-md { width:min(560px,96vw); max-height:92vh; overflow:auto; background:#fff; border-radius:16px;
  box-shadow:0 24px 60px rgba(0,0,0,.45); font-family:var(--font-sans,sans-serif); }
.li-fade-enter-active,.li-fade-leave-active{ transition:opacity .25s ease; }
.li-fade-enter-from,.li-fade-leave-to{ opacity:0; }
.li-pop-enter-active{ transition:transform .38s cubic-bezier(.2,.9,.3,1.2),opacity .3s ease; }
.li-pop-enter-from{ transform:translateY(30px) scale(.94); opacity:0; }
.li-hero { display:flex; align-items:center; gap:12px; padding:14px 18px; position:sticky; top:0;
  background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; }
.li-hero-ico { font-size:26px; animation:lat 2.4s ease-in-out infinite; }
@keyframes lat{0%,100%{transform:scale(1)}50%{transform:scale(1.12)}}
.li-hero-tx h3 { margin:0; font-size:15px; }
.li-hero-tx p { margin:2px 0 0; font-size:11px; opacity:.85; }
.li-x { margin-left:auto; background:rgba(255,255,255,.15); border:none; color:#fff; width:28px; height:28px;
  border-radius:8px; font-size:14px; cursor:pointer; }
.li-x:hover{ background:rgba(255,255,255,.3); }
.li-body { padding:18px 22px; }
.li-intro { font-size:14px; color:#374151; line-height:1.55; margin:0 0 14px; }
.li-diag { display:flex; justify-content:center; background:#f8fafc; border:1px solid #eef2f7; border-radius:12px; padding:12px; margin-bottom:14px; }
@keyframes flecha{0%,100%{opacity:.5}50%{opacity:1}}
@keyframes pulso{0%,100%{transform:translateY(0)}50%{transform:translateY(-2px)}}
.li-pasos { background:#f8fafc; border:1px solid #eef2f7; border-radius:12px; padding:12px 16px; margin-bottom:14px; }
.li-pasos-t { font-size:12px; font-weight:700; color:#1b4332; text-transform:uppercase; letter-spacing:.5px; margin-bottom:10px; }
.li-pasos ol { margin:0; padding:0; list-style:none; }
.li-pasos li { display:flex; align-items:flex-start; gap:10px; font-size:13px; color:#374151; line-height:1.5;
  margin-bottom:8px; opacity:0; animation:pasoIn .4s ease forwards; }
.li-num { flex-shrink:0; width:22px; height:22px; border-radius:50%; background:#16a34a; color:#fff;
  font-size:12px; font-weight:700; display:flex; align-items:center; justify-content:center; }
@keyframes pasoIn{ from{opacity:0;transform:translateX(-8px)} to{opacity:1;transform:none} }
.li-sal { display:grid; grid-template-columns:repeat(3,1fr); gap:10px; margin-bottom:14px; }
.li-sal-card { background:#f0fdf4; border:1px solid #d1fae5; border-radius:10px; padding:12px 10px; text-align:center;
  display:flex; flex-direction:column; gap:4px; }
.li-sal-ico { font-size:22px; }
.li-sal-card b { font-size:12.5px; color:#1b4332; }
.li-sal-card span { font-size:11px; color:#475569; line-height:1.35; }
.li-notas { margin:0; padding-left:6px; list-style:none; }
.li-notas li { font-size:13px; color:#475569; line-height:1.55; margin-bottom:6px; padding-left:24px; position:relative; }
.li-notas li::before { content:'💡'; position:absolute; left:0; }
@media (max-width:480px){ .li-sal{ grid-template-columns:1fr; } }
</style>
