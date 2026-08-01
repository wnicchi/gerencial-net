<!-- NovedadesAyuda.vue — Ayuda del módulo Planilla de Novedades de Sueldos. -->
<template>
  <Teleport to="body"><transition name="nv-fade" appear><div class="nv-ov" @click.self="$emit('close')">
    <transition name="nv-pop" appear><div class="nv-md">
      <div class="nv-hero">
        <div class="nv-hero-ico">📋</div>
        <div class="nv-hero-tx"><h3>Ayuda — Planilla de Novedades</h3><p>Liquidación de novedades de sueldos por período</p></div>
        <button class="nv-x" @click="$emit('close')">✕</button>
      </div>
      <div class="nv-body">
        <p class="nv-intro">Arma una <b>planilla por empleado</b> para el período elegido, reuniendo automáticamente las novedades de cada uno: días trabajados, vacaciones, licencias, horas extra y montos.</p>

        <!-- Mini diagrama del período 20 → 19 -->
        <div class="nv-diag">
          <div class="nv-diag-box nv-d1"><span class="nv-diag-d">20</span><small>mes anterior</small></div>
          <div class="nv-diag-flecha">→</div>
          <div class="nv-diag-box nv-d2"><span class="nv-diag-d">19</span><small>mes actual</small></div>
        </div>
        <p class="nv-diag-cap">El período va siempre <b>del 20 del mes anterior al 19 del mes seleccionado</b>.</p>

        <div class="nv-pasos"><div class="nv-pasos-t">⚡ Cómo se usa</div><ol>
          <li v-for="(p,i) in pasos" :key="i" :style="{ animationDelay:(i*90+150)+'ms' }"><span class="nv-num">{{ i+1 }}</span><span v-html="p"></span></li>
        </ol></div>

        <ul class="nv-notas">
          <li>Las celdas en <b style="background:#fff7ae;padding:0 4px;border-radius:3px">amarillo</b> indican que ese concepto tiene un valor cargado.</li>
          <li>El <b>nombre resaltado</b> marca al personal de convenio (SMATA / F.Convenio).</li>
          <li>Los <b>días trabajados</b> se cuentan según las marcaciones registradas en el período.</li>
          <li>Las <b>horas extra</b> se valorizan según el convenio de cada empleado.</li>
          <li>Si el período está <b>bloqueado</b>, la planilla se muestra solo para consulta.</li>
          <li>Con <b>Exportar a Excel</b> se descarga toda la planilla para su estudio.</li>
        </ul>
      </div>
    </div></transition>
  </div></transition></Teleport>
</template>
<script setup lang="ts">
defineEmits<{ close: [] }>()
const pasos = [
  '<b>Elegí el período:</b> empresa, mes y año.',
  '<b>Contratista:</b> dejá <i>Todos</i> o filtrá por uno.',
  '<b>Planilla Hs. Extra:</b> indicá el número si querés sumar las horas extra de esa planilla.',
  '<b>ACEPTAR:</b> se arma la grilla con todas las novedades calculadas.',
  '<b>Ver Completa / Parte 1 / Parte 2:</b> cambiá qué columnas ver.',
]
</script>
<style scoped>
.nv-ov { position:fixed; inset:0; background:rgba(15,23,42,.55); backdrop-filter:blur(3px); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.nv-md { width:min(480px,96vw); max-height:92vh; overflow:auto; background:#fff; border-radius:16px; box-shadow:0 24px 60px rgba(0,0,0,.45); font-family:var(--font-sans,sans-serif); }
.nv-fade-enter-active,.nv-fade-leave-active{ transition:opacity .25s ease; } .nv-fade-enter-from,.nv-fade-leave-to{ opacity:0; }
.nv-pop-enter-active{ transition:transform .38s cubic-bezier(.2,.9,.3,1.2),opacity .3s ease; } .nv-pop-enter-from{ transform:translateY(30px) scale(.94); opacity:0; }
.nv-hero { display:flex; align-items:center; gap:12px; padding:14px 18px; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; position:sticky; top:0; }
.nv-hero-ico { font-size:26px; animation:nv-lat 2.4s ease-in-out infinite; } @keyframes nv-lat{0%,100%{transform:scale(1)}50%{transform:scale(1.12)}}
.nv-hero-tx h3 { margin:0; font-size:15px; } .nv-hero-tx p { margin:2px 0 0; font-size:11px; opacity:.85; }
.nv-x { margin-left:auto; background:rgba(255,255,255,.15); border:none; color:#fff; width:28px; height:28px; border-radius:8px; font-size:14px; cursor:pointer; } .nv-x:hover{ background:rgba(255,255,255,.3); }
.nv-body { padding:18px 22px; } .nv-intro { font-size:14px; color:#374151; line-height:1.55; margin:0 0 14px; }
.nv-diag { display:flex; align-items:center; justify-content:center; gap:14px; margin:6px 0 8px; }
.nv-diag-box { display:flex; flex-direction:column; align-items:center; gap:2px; padding:10px 16px; border-radius:12px; color:#fff; box-shadow:0 6px 16px rgba(0,0,0,.18); animation:nv-flota 2.6s ease-in-out infinite; }
.nv-d1 { background:linear-gradient(135deg,#64748b,#94a3b8); } .nv-d2 { background:linear-gradient(135deg,#16a34a,#40916c); animation-delay:.4s; }
.nv-diag-d { font-size:24px; font-weight:800; line-height:1; } .nv-diag-box small { font-size:10px; opacity:.9; }
.nv-diag-flecha { font-size:22px; color:#16a34a; font-weight:800; animation:nv-desliza 1.6s ease-in-out infinite; }
@keyframes nv-flota{0%,100%{transform:translateY(0)}50%{transform:translateY(-4px)}}
@keyframes nv-desliza{0%,100%{transform:translateX(0);opacity:.6}50%{transform:translateX(4px);opacity:1}}
.nv-diag-cap { text-align:center; font-size:12px; color:#6b7280; margin:0 0 14px; }
.nv-pasos { background:#f8fafc; border:1px solid #eef2f7; border-radius:12px; padding:12px 16px; margin-bottom:12px; }
.nv-pasos-t { font-size:12px; font-weight:700; color:#1b4332; text-transform:uppercase; letter-spacing:.5px; margin-bottom:10px; }
.nv-pasos ol { margin:0; padding:0; list-style:none; }
.nv-pasos li { display:flex; align-items:flex-start; gap:10px; font-size:13px; color:#374151; line-height:1.5; margin-bottom:8px; opacity:0; animation:nv-pasoIn .4s ease forwards; }
.nv-num { flex-shrink:0; width:22px; height:22px; border-radius:50%; background:#16a34a; color:#fff; font-size:12px; font-weight:700; display:flex; align-items:center; justify-content:center; }
@keyframes nv-pasoIn{ from{opacity:0;transform:translateX(-8px)} to{opacity:1;transform:none} }
.nv-notas { margin:0; padding-left:6px; list-style:none; }
.nv-notas li { font-size:13px; color:#475569; line-height:1.55; margin-bottom:6px; padding-left:24px; position:relative; } .nv-notas li::before { content:'💡'; position:absolute; left:0; }
</style>
