<!-- ExportarExcelAyuda.vue — Modal de ayuda del módulo Exportar a Excel. -->
<template>
  <Teleport to="body">
    <transition name="ex-fade" appear>
      <div class="ex-ov" @click.self="$emit('close')">
        <transition name="ex-pop" appear>
          <div class="ex-md">
            <div class="ex-hero">
              <div class="ex-hero-ico">📤</div>
              <div class="ex-hero-tx">
                <h3>Ayuda — Exportar a Excel</h3>
                <p>Planilla de datos de empleados a medida</p>
              </div>
              <button class="ex-x" @click="$emit('close')">✕</button>
            </div>

            <div class="ex-body">
              <p class="ex-intro">
                Este módulo genera una <b>planilla de Excel</b> con los datos de los empleados,
                eligiendo <b>qué columnas</b> incluir y <b>cómo ordenarlas</b>.
              </p>

              <!-- Diagrama -->
              <div class="ex-diag">
                <svg viewBox="0 0 300 110" width="100%" style="max-width:300px">
                  <rect x="8" y="18" width="120" height="78" rx="8" fill="#fff" stroke="#16a34a" stroke-width="1.5"/>
                  <text x="68" y="14" text-anchor="middle" font-size="9" font-weight="700" fill="#1b4332">Elegís columnas</text>
                  <g font-size="8" fill="#475569">
                    <rect x="16" y="26" width="10" height="10" rx="2" fill="#22c55e"/><text x="30" y="34">Nombre</text>
                    <rect x="16" y="42" width="10" height="10" rx="2" fill="#22c55e"/><text x="30" y="50">Legajo</text>
                    <rect x="16" y="58" width="10" height="10" rx="2" fill="#fff" stroke="#94a3b8"/><text x="30" y="66">Sueldo</text>
                    <rect x="16" y="74" width="10" height="10" rx="2" fill="#22c55e"/><text x="30" y="82">Sector</text>
                  </g>
                  <path d="M132 57 L156 57" stroke="#16a34a" stroke-width="2" fill="none" stroke-linecap="round" style="animation:ex-flecha 1.8s ease-in-out infinite"/>
                  <path d="M150 52 L158 57 L150 62" stroke="#16a34a" stroke-width="2" fill="none" stroke-linecap="round" style="animation:ex-flecha 1.8s ease-in-out infinite"/>
                  <rect x="162" y="18" width="130" height="78" rx="8" fill="#f0fdf4" stroke="#16a34a" stroke-width="1.5"/>
                  <text x="227" y="14" text-anchor="middle" font-size="9" font-weight="700" fill="#1b4332">Excel</text>
                  <g stroke="#cbd5e1"><line x1="162" y1="34" x2="292" y2="34"/><line x1="162" y1="50" x2="292" y2="50"/><line x1="162" y1="66" x2="292" y2="66"/><line x1="205" y1="18" x2="205" y2="96"/><line x1="248" y1="18" x2="248" y2="96"/></g>
                  <g font-size="7" fill="#166534" font-weight="700"><text x="168" y="30">Nombre</text><text x="210" y="30">Legajo</text><text x="253" y="30">Sector</text></g>
                </svg>
              </div>

              <div class="ex-pasos">
                <div class="ex-pasos-t">⚡ Cómo se usa</div>
                <ol>
                  <li v-for="(p, i) in pasos" :key="i" :style="{ animationDelay: (i*90+120)+'ms' }">
                    <span class="ex-num">{{ i+1 }}</span><span v-html="p"></span>
                  </li>
                </ol>
              </div>

              <ul class="ex-notas">
                <li><b>Todo / Nada</b> marca o desmarca todas las columnas de una sola vez.</li>
                <li>Marcá una columna como <b>Índice</b> para ordenar la planilla por ella; el <b>Orden</b> define la prioridad.</li>
                <li>Si no marcás ninguna columna, te avisa que tenés que elegir al menos una.</li>
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
  '<b>Marcá las columnas</b> que querés en la planilla (o usá Todo/Nada).',
  'Si querés ordenarla, marcá una o más columnas como <b>Índice</b> y ajustá el <b>Orden</b>.',
  'Elegí el <b>estado</b> (todos, activos o pasivos) y la <b>empresa</b>.',
  'Tocá <b>Exportar a Excel</b> y se descarga el archivo.',
]
</script>

<style scoped>
.ex-ov { position:fixed; inset:0; background:rgba(15,23,42,.55); backdrop-filter:blur(3px); z-index:10000;
  display:flex; align-items:center; justify-content:center; padding:18px; }
.ex-md { width:min(540px,96vw); max-height:92vh; overflow:auto; background:#fff; border-radius:16px;
  box-shadow:0 24px 60px rgba(0,0,0,.45); font-family:var(--font-sans,sans-serif); }
.ex-fade-enter-active,.ex-fade-leave-active{ transition:opacity .25s ease; }
.ex-fade-enter-from,.ex-fade-leave-to{ opacity:0; }
.ex-pop-enter-active{ transition:transform .38s cubic-bezier(.2,.9,.3,1.2),opacity .3s ease; }
.ex-pop-enter-from{ transform:translateY(30px) scale(.94); opacity:0; }
.ex-hero { display:flex; align-items:center; gap:12px; padding:14px 18px; position:sticky; top:0;
  background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; }
.ex-hero-ico { font-size:26px; animation:ex-lat 2.4s ease-in-out infinite; }
@keyframes ex-lat{0%,100%{transform:scale(1)}50%{transform:scale(1.12)}}
.ex-hero-tx h3 { margin:0; font-size:15px; }
.ex-hero-tx p { margin:2px 0 0; font-size:11px; opacity:.85; }
.ex-x { margin-left:auto; background:rgba(255,255,255,.15); border:none; color:#fff; width:28px; height:28px;
  border-radius:8px; font-size:14px; cursor:pointer; }
.ex-x:hover{ background:rgba(255,255,255,.3); }
.ex-body { padding:18px 22px; }
.ex-intro { font-size:14px; color:#374151; line-height:1.55; margin:0 0 14px; }
.ex-diag { display:flex; justify-content:center; background:#f8fafc; border:1px solid #eef2f7; border-radius:12px; padding:12px; margin-bottom:14px; }
@keyframes ex-flecha{0%,100%{opacity:.5}50%{opacity:1}}
.ex-pasos { background:#f8fafc; border:1px solid #eef2f7; border-radius:12px; padding:12px 16px; margin-bottom:14px; }
.ex-pasos-t { font-size:12px; font-weight:700; color:#1b4332; text-transform:uppercase; letter-spacing:.5px; margin-bottom:10px; }
.ex-pasos ol { margin:0; padding:0; list-style:none; }
.ex-pasos li { display:flex; align-items:flex-start; gap:10px; font-size:13px; color:#374151; line-height:1.5;
  margin-bottom:8px; opacity:0; animation:ex-pasoIn .4s ease forwards; }
.ex-num { flex-shrink:0; width:22px; height:22px; border-radius:50%; background:#16a34a; color:#fff;
  font-size:12px; font-weight:700; display:flex; align-items:center; justify-content:center; }
@keyframes ex-pasoIn{ from{opacity:0;transform:translateX(-8px)} to{opacity:1;transform:none} }
.ex-notas { margin:0; padding-left:6px; list-style:none; }
.ex-notas li { font-size:13px; color:#475569; line-height:1.55; margin-bottom:6px; padding-left:24px; position:relative; }
.ex-notas li::before { content:'💡'; position:absolute; left:0; }
</style>
