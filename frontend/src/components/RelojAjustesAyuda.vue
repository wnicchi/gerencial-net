<!-- RelojAjustesAyuda.vue — Ayuda del módulo Ajuste Horarios Trabajados. -->
<template>
  <div class="vca-ov" @click.self="$emit('close')">
    <div class="vca-modal">
      <button class="vca-x" @click="$emit('close')">✕</button>
      <div class="vca-hero">
        <svg viewBox="0 0 120 80" class="vca-svg">
          <circle cx="54" cy="40" r="20" class="vca-clock" /><line x1="54" y1="40" x2="54" y2="27" class="vca-min" /><line x1="54" y1="40" x2="64" y2="45" class="vca-hr" />
          <path d="M78 30 l10 4 l-3 10 l-10 -4 Z" class="vca-pencil" /><line x1="75" y1="40" x2="73" y2="46" class="vca-tip" />
        </svg>
        <h2>Ajuste de Horarios</h2><p>Corregir marcaciones del reloj</p>
      </div>
      <div class="vca-body"><div class="vca-step" v-for="(s, i) in pasos" :key="i" :style="{ animationDelay: (i * 90) + 'ms' }"><span class="vca-num">{{ i + 1 }}</span><div><b>{{ s.t }}</b><p>{{ s.d }}</p></div></div></div>
    </div>
  </div>
</template>
<script setup lang="ts">
defineEmits(['close'])
const pasos = [
  { t: 'Elegí empleado y fecha', d: 'Buscá al empleado y seleccioná el día a corregir. Se traen el ajuste ya cargado (si existe) y las marcaciones reales del reloj.' },
  { t: 'Cargá los turnos', d: 'Activá la entrada y/o salida de cada turno con su tilde y completá hora y minuto. Podés cargar hasta dos turnos.' },
  { t: 'Ignorá capturas erróneas', d: 'Tildá las marcaciones reales que querés que NO se tengan en cuenta en los cálculos.' },
  { t: 'Confirmá', d: 'Se guarda un ajuste por empleado y fecha. La salida no puede ser menor a la entrada; no se permiten ajustes a futuro ni con más de 35 días de antigüedad.' },
  { t: 'Borrar', d: '“Borrar ajustes del día” elimina el ajuste cargado para esa fecha.' },
]
</script>
<style scoped>
.vca-ov { position:fixed; inset:0; background:rgba(15,23,42,.55); display:flex; align-items:center; justify-content:center; z-index:1000; animation:vca-fade .2s ease; }
@keyframes vca-fade { from { opacity:0 } to { opacity:1 } }
.vca-modal { background:#fff; width:540px; max-width:94vw; max-height:90vh; overflow:auto; border-radius:14px; position:relative; box-shadow:0 20px 60px rgba(0,0,0,.3); animation:vca-pop .25s cubic-bezier(.2,.9,.3,1.2); }
@keyframes vca-pop { from { transform:translateY(20px) scale(.96); opacity:0 } to { transform:none; opacity:1 } }
.vca-x { position:absolute; top:12px; right:14px; background:rgba(255,255,255,.2); border:none; color:#fff; font-size:16px; cursor:pointer; width:28px; height:28px; border-radius:50%; }
.vca-hero { background:linear-gradient(135deg,#1b4332,#40916c); color:#fff; padding:26px 24px 22px; text-align:center; border-radius:14px 14px 0 0; }
.vca-svg { width:120px; height:80px; } .vca-clock { fill:rgba(255,255,255,.12); stroke:#fff; stroke-width:2.6; } .vca-min { stroke:#fff; stroke-width:2.6; stroke-linecap:round; } .vca-hr { stroke:rgba(255,255,255,.85); stroke-width:2.4; stroke-linecap:round; } .vca-pencil { fill:#fde68a; } .vca-tip { stroke:#fde68a; stroke-width:2; }
.vca-hero h2 { margin:10px 0 4px; font-size:18px; } .vca-hero p { margin:0; font-size:13px; opacity:.9; }
.vca-body { padding:18px 22px 24px; }
.vca-step { display:flex; gap:12px; padding:10px 0; border-bottom:1px solid #f1f5f9; animation:vca-slide .4s ease both; }
@keyframes vca-slide { from { transform:translateX(-10px); opacity:0 } to { transform:none; opacity:1 } }
.vca-num { flex-shrink:0; width:26px; height:26px; border-radius:50%; background:#1b4332; color:#fff; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:700; }
.vca-step b { font-size:14px; color:#1e293b; } .vca-step p { margin:3px 0 0; font-size:13px; color:#64748b; }
</style>
