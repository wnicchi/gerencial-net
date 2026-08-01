<!-- ImportarConvenioAyuda.vue — Modal de ayuda del módulo Importar datos Convenio. -->
<template>
  <Teleport to="body">
    <transition name="im-fade" appear>
      <div class="im-ov" @click.self="$emit('close')">
        <transition name="im-pop" appear>
          <div class="im-md">
            <div class="im-hero">
              <div class="im-hero-ico">📥</div>
              <div class="im-hero-tx">
                <h3>Ayuda — Importar datos Convenio</h3>
                <p>Actualizar empleados desde un Excel</p>
              </div>
              <button class="im-x" @click="$emit('close')">✕</button>
            </div>

            <div class="im-body">
              <p class="im-intro">
                Este módulo tiene <b>dos solapas</b>: <b>Importar datos</b> (actualizar varios
                empleados de una vez desde un Excel) y <b>Exportar datos específicos</b>
                (generar la planilla base que después se edita y se vuelve a importar).
              </p>

              <div class="im-flujo">
                <span class="im-chip">📤 Exportar</span> → editás en Excel →
                <span class="im-chip">📥 Importar</span>
              </div>

              <!-- Diagrama: Excel → mapeo → empleados -->
              <div class="im-diag">
                <svg viewBox="0 0 300 120" width="100%" style="max-width:300px">
                  <rect x="8" y="20" width="96" height="80" rx="8" fill="#fff" stroke="#16a34a" stroke-width="1.5"/>
                  <text x="56" y="16" text-anchor="middle" font-size="9" font-weight="700" fill="#1b4332">Excel</text>
                  <g stroke="#cbd5e1"><line x1="8" y1="36" x2="104" y2="36"/><line x1="40" y1="20" x2="40" y2="100"/><line x1="72" y1="20" x2="72" y2="100"/></g>
                  <g font-size="8" font-weight="700" fill="#64748b"><text x="20" y="32">A</text><text x="52" y="32">B</text><text x="84" y="32">C</text></g>
                  <path d="M108 60 L150 60" stroke="#16a34a" stroke-width="2" fill="none" stroke-linecap="round" style="animation:im-flecha 1.8s ease-in-out infinite"/>
                  <path d="M144 55 L152 60 L144 65" stroke="#16a34a" stroke-width="2" fill="none" stroke-linecap="round" style="animation:im-flecha 1.8s ease-in-out infinite"/>
                  <text x="130" y="52" text-anchor="middle" font-size="7.5" fill="#475569">mapeás</text>
                  <rect x="156" y="20" width="136" height="80" rx="8" fill="#f0fdf4" stroke="#16a34a" stroke-width="1.5"/>
                  <text x="224" y="16" text-anchor="middle" font-size="9" font-weight="700" fill="#1b4332">Empleados</text>
                  <g font-size="8" fill="#166534">
                    <text x="164" y="38">Código → col. A</text>
                    <text x="164" y="54">Convenio → col. B</text>
                    <text x="164" y="70">Categoría → col. C</text>
                    <text x="164" y="86">Afiliado → col. …</text>
                  </g>
                </svg>
              </div>

              <div class="im-pasos">
                <div class="im-pasos-t">📥 Solapa “Importar datos”</div>
                <ol>
                  <li v-for="(p, i) in pasosImp" :key="i" :style="{ animationDelay: (i*90+120)+'ms' }">
                    <span class="im-num">{{ i+1 }}</span><span v-html="p"></span>
                  </li>
                </ol>
              </div>

              <div class="im-pasos">
                <div class="im-pasos-t">📤 Solapa “Exportar datos específicos”</div>
                <ol>
                  <li v-for="(p, i) in pasosExp" :key="i" :style="{ animationDelay: (i*90+120)+'ms' }">
                    <span class="im-num im-num-exp">{{ i+1 }}</span><span v-html="p"></span>
                  </li>
                </ol>
              </div>

              <ul class="im-notas">
                <li>Lo más práctico: <b>exportá</b> primero, ajustás convenio/categoría/afiliado en Excel y volvés a <b>importar</b>.</li>
                <li>El <b>Código de Empleado</b> es obligatorio al importar: se usa para encontrar a quién actualizar.</li>
                <li>Si un código <b>no corresponde</b> a ningún empleado, esa fila se informa y no se toca.</li>
                <li>Podés <b>destildar</b> filas en la columna OK para excluirlas de la importación.</li>
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
const pasosImp = [
  '<b>Buscá el archivo</b> Excel (.xlsx, .xls o .csv).',
  'Revisá la <b>grilla</b>: cada columna tiene una letra (A, B, C…).',
  'En “Identifique el contenido”, indicá <b>en qué columna</b> está cada dato.',
  'Tocá <b>Proceder con la importación</b> y confirmá.',
]
const pasosExp = [
  'Entrá a la solapa <b>Exportar datos específicos</b>.',
  'Si querés, <b>filtrá</b> por empresa, sector, convenio, etc.',
  'Tocá <b>Exportar Datos</b> y elegí dónde guardar la planilla.',
]
</script>

<style scoped>
.im-ov { position:fixed; inset:0; background:rgba(15,23,42,.55); backdrop-filter:blur(3px); z-index:10000;
  display:flex; align-items:center; justify-content:center; padding:18px; }
.im-md { width:min(540px,96vw); max-height:92vh; overflow:auto; background:#fff; border-radius:16px;
  box-shadow:0 24px 60px rgba(0,0,0,.45); font-family:var(--font-sans,sans-serif); }
.im-fade-enter-active,.im-fade-leave-active{ transition:opacity .25s ease; }
.im-fade-enter-from,.im-fade-leave-to{ opacity:0; }
.im-pop-enter-active{ transition:transform .38s cubic-bezier(.2,.9,.3,1.2),opacity .3s ease; }
.im-pop-enter-from{ transform:translateY(30px) scale(.94); opacity:0; }
.im-hero { display:flex; align-items:center; gap:12px; padding:14px 18px; position:sticky; top:0;
  background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; }
.im-hero-ico { font-size:26px; animation:im-lat 2.4s ease-in-out infinite; }
@keyframes im-lat{0%,100%{transform:scale(1)}50%{transform:scale(1.12)}}
.im-hero-tx h3 { margin:0; font-size:15px; }
.im-hero-tx p { margin:2px 0 0; font-size:11px; opacity:.85; }
.im-x { margin-left:auto; background:rgba(255,255,255,.15); border:none; color:#fff; width:28px; height:28px;
  border-radius:8px; font-size:14px; cursor:pointer; }
.im-x:hover{ background:rgba(255,255,255,.3); }
.im-body { padding:18px 22px; }
.im-intro { font-size:14px; color:#374151; line-height:1.55; margin:0 0 12px; }
.im-flujo { display:flex; flex-wrap:wrap; align-items:center; gap:8px; justify-content:center; font-size:12px;
  color:#64748b; background:#f8fafc; border:1px solid #eef2f7; border-radius:10px; padding:10px; margin-bottom:14px; }
.im-chip { background:#1b4332; color:#fff; padding:3px 10px; border-radius:20px; font-weight:600; }
.im-diag { display:flex; justify-content:center; background:#f8fafc; border:1px solid #eef2f7; border-radius:12px; padding:12px; margin-bottom:14px; }
@keyframes im-flecha{0%,100%{opacity:.5}50%{opacity:1}}
.im-pasos { background:#f8fafc; border:1px solid #eef2f7; border-radius:12px; padding:12px 16px; margin-bottom:14px; }
.im-pasos-t { font-size:12px; font-weight:700; color:#1b4332; text-transform:uppercase; letter-spacing:.5px; margin-bottom:10px; }
.im-pasos ol { margin:0; padding:0; list-style:none; }
.im-pasos li { display:flex; align-items:flex-start; gap:10px; font-size:13px; color:#374151; line-height:1.5;
  margin-bottom:8px; opacity:0; animation:im-pasoIn .4s ease forwards; }
.im-num { flex-shrink:0; width:22px; height:22px; border-radius:50%; background:#16a34a; color:#fff;
  font-size:12px; font-weight:700; display:flex; align-items:center; justify-content:center; }
.im-num-exp { background:#0d9488; }
@keyframes im-pasoIn{ from{opacity:0;transform:translateX(-8px)} to{opacity:1;transform:none} }
.im-notas { margin:0; padding-left:6px; list-style:none; }
.im-notas li { font-size:13px; color:#475569; line-height:1.55; margin-bottom:6px; padding-left:24px; position:relative; }
.im-notas li::before { content:'💡'; position:absolute; left:0; }
</style>
