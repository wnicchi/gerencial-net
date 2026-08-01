<!-- RelojPeriodosAyuda.vue — Ayuda del módulo Ajuste Horarios por Períodos. -->
<template>
  <div class="vca-ov" @click.self="$emit('close')">
    <div class="vca-modal">
      <button class="vca-x" @click="$emit('close')">✕</button>
      <div class="vca-hero">
        <svg viewBox="0 0 120 80" class="vca-svg"><rect x="30" y="20" width="60" height="40" rx="4" class="vca-grid" /><line x1="30" y1="33" x2="90" y2="33" class="vca-l" /><line x1="50" y1="20" x2="50" y2="60" class="vca-l" /><line x1="70" y1="20" x2="70" y2="60" class="vca-l" /><rect x="51" y="34" width="18" height="12" class="vca-hl" /></svg>
        <h2>Ajuste por Períodos</h2><p>Editar fichadas día por día</p>
      </div>
      <div class="vca-body"><div class="vca-step" v-for="(s, i) in pasos" :key="i" :style="{ animationDelay: (i * 90) + 'ms' }"><span class="vca-num">{{ i + 1 }}</span><div><b>{{ s.t }}</b><p>{{ s.d }}</p></div></div></div>
    </div>
  </div>
</template>
<script setup lang="ts">
defineEmits(['close'])
const pasos = [
  { t: 'Elegí empleado y período', d: 'Buscá al empleado y el rango de fechas, y presioná “Recuperar Datos”.' },
  { t: 'Revisá la grilla', d: 'Una fila por día con las 4 fichadas (entradas y salidas) y el tiempo trabajado. La hora 00:00 significa sin marcación; las marcaciones antes de las 3 de la mañana van al día anterior.' },
  { t: 'Editá las horas', d: 'Cambiá las horas que haga falta (formato HH:MM). Las celdas modificadas se resaltan en verde y el total se recalcula.' },
  { t: 'Limpiar / Confirmar', d: '“Limpiar Marcados” pone en 00:00 las filas tildadas. Al “Aceptar”, cada valor cambiado reemplaza la marcación real (que queda archivada) por un ajuste manual.' },
]
</script>
<style scoped>
.vca-ov { position:fixed; inset:0; background:rgba(15,23,42,.55); display:flex; align-items:center; justify-content:center; z-index:1000; animation:vca-fade .2s ease; }
@keyframes vca-fade { from { opacity:0 } to { opacity:1 } }
.vca-modal { background:#fff; width:540px; max-width:94vw; max-height:90vh; overflow:auto; border-radius:14px; position:relative; box-shadow:0 20px 60px rgba(0,0,0,.3); animation:vca-pop .25s cubic-bezier(.2,.9,.3,1.2); }
@keyframes vca-pop { from { transform:translateY(20px) scale(.96); opacity:0 } to { transform:none; opacity:1 } }
.vca-x { position:absolute; top:12px; right:14px; background:rgba(255,255,255,.2); border:none; color:#fff; font-size:16px; cursor:pointer; width:28px; height:28px; border-radius:50%; }
.vca-hero { background:linear-gradient(135deg,#1b4332,#40916c); color:#fff; padding:26px 24px 22px; text-align:center; border-radius:14px 14px 0 0; }
.vca-svg { width:120px; height:80px; } .vca-grid { fill:rgba(255,255,255,.1); stroke:#fff; stroke-width:2.4; } .vca-l { stroke:rgba(255,255,255,.8); stroke-width:1.6; } .vca-hl { fill:#86efac; opacity:.9; }
.vca-hero h2 { margin:10px 0 4px; font-size:18px; } .vca-hero p { margin:0; font-size:13px; opacity:.9; }
.vca-body { padding:18px 22px 24px; }
.vca-step { display:flex; gap:12px; padding:10px 0; border-bottom:1px solid #f1f5f9; animation:vca-slide .4s ease both; }
@keyframes vca-slide { from { transform:translateX(-10px); opacity:0 } to { transform:none; opacity:1 } }
.vca-num { flex-shrink:0; width:26px; height:26px; border-radius:50%; background:#1b4332; color:#fff; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:700; }
.vca-step b { font-size:14px; color:#1e293b; } .vca-step p { margin:3px 0 0; font-size:13px; color:#64748b; }
</style>
