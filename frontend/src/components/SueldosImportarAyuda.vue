<!-- SueldosImportarAyuda.vue — Ayuda del módulo Importar del Estudio. -->
<template>
  <div class="sia-ov" @click.self="$emit('close')">
    <div class="sia-modal">
      <button class="sia-x" @click="$emit('close')">✕</button>
      <div class="sia-hero">
        <svg viewBox="0 0 120 80" class="sia-svg">
          <rect x="24" y="20" width="44" height="52" rx="4" class="sia-sheet" />
          <line x1="32" y1="32" x2="60" y2="32" class="sia-row" /><line x1="32" y1="42" x2="60" y2="42" class="sia-row" />
          <line x1="32" y1="52" x2="60" y2="52" class="sia-row" /><line x1="32" y1="62" x2="52" y2="62" class="sia-row" />
          <path d="M74 46 h22 M88 38 l10 8 l-10 8" class="sia-arrow" />
          <rect x="100" y="34" width="14" height="24" rx="2" class="sia-db" />
        </svg>
        <h2>Importar del Estudio</h2>
        <p>Cargá una liquidación desde el Excel del estudio contable</p>
      </div>
      <div class="sia-body">
        <div class="sia-step" v-for="(s, i) in pasos" :key="i" :style="{ animationDelay: (i * 90) + 'ms' }">
          <span class="sia-num">{{ i + 1 }}</span>
          <div><b>{{ s.t }}</b><p>{{ s.d }}</p></div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
defineEmits(['close'])
const pasos = [
  { t: 'Definí la liquidación', d: 'Elegí el Tipo de liquidación, el Mes, el Año y la Fecha de Pago.' },
  { t: 'Abrí el Excel', d: 'Buscá el archivo que mandó el estudio. Se muestra en una grilla con sus columnas (A, B, C…).' },
  { t: 'Identificá las columnas', d: 'Indicá qué columna contiene el CUIL, el Código, el Detalle, la Cantidad, los Haberes y las Deducciones.' },
  { t: 'Revisá los renglones', d: 'Cada fila tiene una tilde “OK”. Destildá las que no quieras importar.' },
  { t: 'Procedé', d: 'Por cada empleado (buscado por CUIL) se reemplaza su liquidación anterior del mismo tipo/mes/año y se cargan los conceptos. Si un CUIL no existe en el padrón, no se importa nada y se listan los faltantes.' },
]
</script>

<style scoped>
.sia-ov { position:fixed; inset:0; background:rgba(15,23,42,.55); display:flex; align-items:center; justify-content:center; z-index:1000; animation:sia-fade .2s ease; }
@keyframes sia-fade { from { opacity:0 } to { opacity:1 } }
.sia-modal { background:#fff; width:540px; max-width:94vw; max-height:90vh; overflow:auto; border-radius:14px; position:relative; box-shadow:0 20px 60px rgba(0,0,0,.3); animation:sia-pop .25s cubic-bezier(.2,.9,.3,1.2); }
@keyframes sia-pop { from { transform:translateY(20px) scale(.96); opacity:0 } to { transform:none; opacity:1 } }
.sia-x { position:absolute; top:12px; right:14px; background:rgba(255,255,255,.2); border:none; color:#fff; font-size:16px; cursor:pointer; width:28px; height:28px; border-radius:50%; }
.sia-hero { background:linear-gradient(135deg,#1b4332,#40916c); color:#fff; padding:26px 24px 22px; text-align:center; border-radius:14px 14px 0 0; }
.sia-svg { width:130px; height:84px; }
.sia-sheet, .sia-db { fill:rgba(255,255,255,.14); stroke:rgba(255,255,255,.85); stroke-width:2; }
.sia-row { stroke:rgba(255,255,255,.7); stroke-width:2; stroke-linecap:round; }
.sia-arrow { stroke:#fff; stroke-width:2.4; fill:none; stroke-linecap:round; stroke-linejoin:round; animation:sia-move 1.6s ease-in-out infinite; }
@keyframes sia-move { 0%,100% { transform:translateX(0) } 50% { transform:translateX(4px) } }
.sia-hero h2 { margin:10px 0 4px; font-size:18px; } .sia-hero p { margin:0; font-size:13px; opacity:.9; }
.sia-body { padding:18px 22px 24px; }
.sia-step { display:flex; gap:12px; padding:10px 0; border-bottom:1px solid #f1f5f9; animation:sia-slide .4s ease both; }
@keyframes sia-slide { from { transform:translateX(-10px); opacity:0 } to { transform:none; opacity:1 } }
.sia-num { flex-shrink:0; width:26px; height:26px; border-radius:50%; background:#1b4332; color:#fff; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:700; }
.sia-step b { font-size:14px; color:#1e293b; } .sia-step p { margin:3px 0 0; font-size:13px; color:#64748b; }
</style>
