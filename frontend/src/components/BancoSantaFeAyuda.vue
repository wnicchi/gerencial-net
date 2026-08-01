<!-- BancoSantaFeAyuda.vue — Ayuda del módulo Bco. Santa Fe - Exportar. -->
<template>
  <div class="bsf-ov" @click.self="$emit('close')">
    <div class="bsf-modal">
      <button class="bsf-x" @click="$emit('close')">✕</button>
      <div class="bsf-hero">
        <svg viewBox="0 0 120 80" class="bsf-svg">
          <rect x="22" y="32" width="50" height="34" rx="3" class="bsf-bank" />
          <path d="M22 32 L47 16 L72 32 Z" class="bsf-roof" />
          <line x1="32" y1="40" x2="32" y2="58" class="bsf-col" /><line x1="47" y1="40" x2="47" y2="58" class="bsf-col" /><line x1="62" y1="40" x2="62" y2="58" class="bsf-col" />
          <path d="M80 40 h22 M94 32 l10 8 l-10 8" class="bsf-arrow" />
          <rect x="100" y="32" width="14" height="20" rx="2" class="bsf-file" />
        </svg>
        <h2>Bco. Santa Fe - Exportar</h2>
        <p>Archivo de acreditación de sueldos para el banco</p>
      </div>
      <div class="bsf-body">
        <div class="bsf-step" v-for="(s, i) in pasos" :key="i" :style="{ animationDelay: (i * 90) + 'ms' }">
          <span class="bsf-num">{{ i + 1 }}</span>
          <div><b>{{ s.t }}</b><p>{{ s.d }}</p></div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
defineEmits(['close'])
const pasos = [
  { t: 'Elegí empresa y período', d: 'La empresa a pagar, el mes y el año de la liquidación. Filtrá por contratista, lugar, convenio, categoría o empleado si hace falta.' },
  { t: 'Tipo de remuneración', d: 'Todas (suma todos los tipos de liquidación) o una sola. Tildá “Traer monto de anticipos” para sumarlos.' },
  { t: 'Consultá', d: 'Se calcula el monto a depositar de cada empleado y se descuenta lo ya exportado antes (para no pagar dos veces). En rojo, los empleados sin sucursal o cuenta cargada.' },
  { t: 'Depurá la lista', d: 'Podés Eliminar los seleccionados o Quitar a todos los que quedaron en cero.' },
  { t: 'Generá el TXT', d: 'Indicá la Fecha de Envío, la Fecha de Imputación y la modalidad. “Generar TXT para el banco” crea el archivo y registra el lote.' },
]
</script>

<style scoped>
.bsf-ov { position:fixed; inset:0; background:rgba(15,23,42,.55); display:flex; align-items:center; justify-content:center; z-index:1000; animation:bsf-fade .2s ease; }
@keyframes bsf-fade { from { opacity:0 } to { opacity:1 } }
.bsf-modal { background:#fff; width:560px; max-width:94vw; max-height:90vh; overflow:auto; border-radius:14px; position:relative; box-shadow:0 20px 60px rgba(0,0,0,.3); animation:bsf-pop .25s cubic-bezier(.2,.9,.3,1.2); }
@keyframes bsf-pop { from { transform:translateY(20px) scale(.96); opacity:0 } to { transform:none; opacity:1 } }
.bsf-x { position:absolute; top:12px; right:14px; background:rgba(255,255,255,.2); border:none; color:#fff; font-size:16px; cursor:pointer; width:28px; height:28px; border-radius:50%; }
.bsf-hero { background:linear-gradient(135deg,#1b4332,#40916c); color:#fff; padding:26px 24px 22px; text-align:center; border-radius:14px 14px 0 0; }
.bsf-svg { width:130px; height:84px; }
.bsf-bank, .bsf-file { fill:rgba(255,255,255,.14); stroke:rgba(255,255,255,.9); stroke-width:2; }
.bsf-roof { fill:rgba(255,255,255,.16); stroke:rgba(255,255,255,.9); stroke-width:2; }
.bsf-col { stroke:rgba(255,255,255,.8); stroke-width:2; stroke-linecap:round; }
.bsf-arrow { stroke:#fff; stroke-width:2.4; fill:none; stroke-linecap:round; stroke-linejoin:round; animation:bsf-move 1.6s ease-in-out infinite; }
@keyframes bsf-move { 0%,100% { transform:translateX(0) } 50% { transform:translateX(4px) } }
.bsf-hero h2 { margin:10px 0 4px; font-size:18px; } .bsf-hero p { margin:0; font-size:13px; opacity:.9; }
.bsf-body { padding:18px 22px 24px; }
.bsf-step { display:flex; gap:12px; padding:10px 0; border-bottom:1px solid #f1f5f9; animation:bsf-slide .4s ease both; }
@keyframes bsf-slide { from { transform:translateX(-10px); opacity:0 } to { transform:none; opacity:1 } }
.bsf-num { flex-shrink:0; width:26px; height:26px; border-radius:50%; background:#1b4332; color:#fff; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:700; }
.bsf-step b { font-size:14px; color:#1e293b; } .bsf-step p { margin:3px 0 0; font-size:13px; color:#64748b; }
</style>
