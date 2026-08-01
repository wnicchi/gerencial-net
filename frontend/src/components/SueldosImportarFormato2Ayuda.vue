<!-- SueldosImportarFormato2Ayuda.vue — Ayuda del módulo Importar del Estudio - Formato 2. -->
<template>
  <div class="si2-ov" @click.self="$emit('close')">
    <div class="si2-modal">
      <button class="si2-x" @click="$emit('close')">✕</button>
      <div class="si2-hero">
        <svg viewBox="0 0 120 80" class="si2-svg">
          <rect x="24" y="20" width="44" height="52" rx="4" class="si2-sheet" />
          <line x1="32" y1="34" x2="60" y2="34" class="si2-row" /><line x1="32" y1="46" x2="60" y2="46" class="si2-row" />
          <text x="46" y="64" text-anchor="middle" class="si2-pm">±</text>
          <path d="M74 46 h22 M88 38 l10 8 l-10 8" class="si2-arrow" />
          <rect x="100" y="34" width="14" height="24" rx="2" class="si2-db" />
        </svg>
        <h2>Importar del Estudio - Formato 2</h2>
        <p>Una sola columna IMPORTE: + es haber, − es deducción</p>
      </div>
      <div class="si2-body">
        <div class="si2-nota">📌 De la columna <b>IMPORTE</b>, los valores <b>negativos</b> se toman como <b>DEDUCCIONES</b> y los <b>positivos</b> como <b>HABERES</b>.</div>
        <div class="si2-step" v-for="(s, i) in pasos" :key="i" :style="{ animationDelay: (i * 90) + 'ms' }">
          <span class="si2-num">{{ i + 1 }}</span>
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
  { t: 'Abrí el Excel', d: 'Buscá el archivo del estudio. Se muestra en una grilla con sus columnas (A, B, C…).' },
  { t: 'Identificá las columnas', d: 'Indicá qué columna contiene el CUIL, el Código, el Detalle, la Cantidad y el IMPORTE (una sola).' },
  { t: 'Revisá los renglones', d: 'Cada fila tiene una tilde “OK”. Destildá las que no quieras importar.' },
  { t: 'Procedé', d: 'Por cada empleado (buscado por CUIL) se reemplaza su liquidación anterior del mismo tipo/mes/año y se cargan los conceptos. Si un CUIL no existe en el padrón, no se importa nada y se listan los faltantes.' },
]
</script>

<style scoped>
.si2-ov { position:fixed; inset:0; background:rgba(15,23,42,.55); display:flex; align-items:center; justify-content:center; z-index:1000; animation:si2-fade .2s ease; }
@keyframes si2-fade { from { opacity:0 } to { opacity:1 } }
.si2-modal { background:#fff; width:560px; max-width:94vw; max-height:90vh; overflow:auto; border-radius:14px; position:relative; box-shadow:0 20px 60px rgba(0,0,0,.3); animation:si2-pop .25s cubic-bezier(.2,.9,.3,1.2); }
@keyframes si2-pop { from { transform:translateY(20px) scale(.96); opacity:0 } to { transform:none; opacity:1 } }
.si2-x { position:absolute; top:12px; right:14px; background:rgba(255,255,255,.2); border:none; color:#fff; font-size:16px; cursor:pointer; width:28px; height:28px; border-radius:50%; }
.si2-hero { background:linear-gradient(135deg,#1b4332,#40916c); color:#fff; padding:26px 24px 22px; text-align:center; border-radius:14px 14px 0 0; }
.si2-svg { width:130px; height:84px; }
.si2-sheet, .si2-db { fill:rgba(255,255,255,.14); stroke:rgba(255,255,255,.85); stroke-width:2; }
.si2-row { stroke:rgba(255,255,255,.7); stroke-width:2; stroke-linecap:round; }
.si2-pm { fill:#fff; font-size:16px; font-weight:800; }
.si2-arrow { stroke:#fff; stroke-width:2.4; fill:none; stroke-linecap:round; stroke-linejoin:round; animation:si2-move 1.6s ease-in-out infinite; }
@keyframes si2-move { 0%,100% { transform:translateX(0) } 50% { transform:translateX(4px) } }
.si2-hero h2 { margin:10px 0 4px; font-size:18px; } .si2-hero p { margin:0; font-size:13px; opacity:.9; }
.si2-body { padding:18px 22px 24px; }
.si2-nota { background:#fffbeb; border:1px solid #fde68a; color:#92400e; padding:10px 14px; border-radius:8px; font-size:13px; margin-bottom:14px; }
.si2-step { display:flex; gap:12px; padding:10px 0; border-bottom:1px solid #f1f5f9; animation:si2-slide .4s ease both; }
@keyframes si2-slide { from { transform:translateX(-10px); opacity:0 } to { transform:none; opacity:1 } }
.si2-num { flex-shrink:0; width:26px; height:26px; border-radius:50%; background:#1b4332; color:#fff; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:700; }
.si2-step b { font-size:14px; color:#1e293b; } .si2-step p { margin:3px 0 0; font-size:13px; color:#64748b; }
</style>
