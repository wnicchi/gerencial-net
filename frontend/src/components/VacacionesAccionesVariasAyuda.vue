<!-- VacacionesAccionesVariasAyuda.vue — Ayuda del módulo Vacaciones - Acciones Varias. -->
<template>
  <div class="vca-ov" @click.self="$emit('close')">
    <div class="vca-modal">
      <button class="vca-x" @click="$emit('close')">✕</button>
      <div class="vca-hero">
        <svg viewBox="0 0 120 80" class="vca-svg">
          <circle cx="60" cy="40" r="20" class="vca-gear" />
          <circle cx="60" cy="40" r="8" class="vca-hole" />
          <g class="vca-spin">
            <rect v-for="n in 8" :key="n" x="57" y="12" width="6" height="10" rx="1" class="vca-tooth"
                  :transform="`rotate(${n * 45} 60 40)`" />
          </g>
        </svg>
        <h2>Acciones Varias</h2>
        <p>Modificar, eliminar e imprimir vacaciones</p>
      </div>
      <div class="vca-body">
        <div class="vca-step" v-for="(s, i) in pasos" :key="i" :style="{ animationDelay: (i * 90) + 'ms' }">
          <span class="vca-num">{{ i + 1 }}</span>
          <div><b>{{ s.t }}</b><p>{{ s.d }}</p></div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
defineEmits(['close'])
const pasos = [
  { t: 'Buscá al empleado', d: 'Por código o nombre. Se listan todos sus períodos de vacaciones y los días que le corresponden por antigüedad.' },
  { t: 'Elegí un período', d: 'Hacé clic en una fila de la grilla. Sus datos se cargan abajo para editarlos.' },
  { t: 'Modificá y confirmá', d: 'Cambiá año, fechas, días, se presenta, liquidada/gozada, cantidad liquidada u observaciones, y guardá con “Confirmar Cambios”. La observación queda en el historial del legajo.' },
  { t: 'Imprimí el acuerdo', d: 'Generá el PDF (art. 154 LCT) del período en formato “Totales” o “Separadas”, o la consulta completa del empleado.' },
  { t: 'Eliminá si hace falta', d: 'Tildá las filas a borrar y usá “Eliminar Seleccionadas”. Se pide confirmación.' },
]
</script>

<style scoped>
.vca-ov { position:fixed; inset:0; background:rgba(15,23,42,.55); display:flex; align-items:center; justify-content:center; z-index:1000; animation:vca-fade .2s ease; }
@keyframes vca-fade { from { opacity:0 } to { opacity:1 } }
.vca-modal { background:#fff; width:540px; max-width:94vw; max-height:90vh; overflow:auto; border-radius:14px; position:relative; box-shadow:0 20px 60px rgba(0,0,0,.3); animation:vca-pop .25s cubic-bezier(.2,.9,.3,1.2); }
@keyframes vca-pop { from { transform:translateY(20px) scale(.96); opacity:0 } to { transform:none; opacity:1 } }
.vca-x { position:absolute; top:12px; right:14px; background:rgba(255,255,255,.2); border:none; color:#fff; font-size:16px; cursor:pointer; width:28px; height:28px; border-radius:50%; }
.vca-hero { background:linear-gradient(135deg,#1b4332,#40916c); color:#fff; padding:26px 24px 22px; text-align:center; border-radius:14px 14px 0 0; }
.vca-svg { width:130px; height:80px; }
.vca-gear { fill:none; stroke:rgba(255,255,255,.9); stroke-width:3; } .vca-hole { fill:#1b4332; stroke:rgba(255,255,255,.9); stroke-width:2; } .vca-tooth { fill:rgba(255,255,255,.9); }
.vca-spin { transform-origin:60px 40px; animation:vca-rot 8s linear infinite; }
@keyframes vca-rot { from { transform:rotate(0) } to { transform:rotate(360deg) } }
.vca-hero h2 { margin:10px 0 4px; font-size:18px; } .vca-hero p { margin:0; font-size:13px; opacity:.9; }
.vca-body { padding:18px 22px 24px; }
.vca-step { display:flex; gap:12px; padding:10px 0; border-bottom:1px solid #f1f5f9; animation:vca-slide .4s ease both; }
@keyframes vca-slide { from { transform:translateX(-10px); opacity:0 } to { transform:none; opacity:1 } }
.vca-num { flex-shrink:0; width:26px; height:26px; border-radius:50%; background:#1b4332; color:#fff; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:700; }
.vca-step b { font-size:14px; color:#1e293b; } .vca-step p { margin:3px 0 0; font-size:13px; color:#64748b; }
</style>
