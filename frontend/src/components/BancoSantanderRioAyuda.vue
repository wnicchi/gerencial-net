<!-- BancoSantanderRioAyuda.vue — Ayuda del módulo Bco. Santander Río - Exportar. -->
<template>
  <div class="sra-ov" @click.self="$emit('close')">
    <div class="sra-modal">
      <button class="sra-x" @click="$emit('close')">✕</button>
      <div class="sra-hero">
        <BancoSantanderLogo :alto="40" class="sra-logo" />
        <h2>Bco. Santander Río - Exportar</h2>
        <p>Archivo de acreditación de sueldos (por CBU)</p>
      </div>
      <div class="sra-body">
        <div class="sra-step" v-for="(s, i) in pasos" :key="i" :style="{ animationDelay: (i * 90) + 'ms' }">
          <span class="sra-num">{{ i + 1 }}</span>
          <div><b>{{ s.t }}</b><p>{{ s.d }}</p></div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import BancoSantanderLogo from '@/components/BancoSantanderLogo.vue'
defineEmits(['close'])
const pasos = [
  { t: 'Elegí empresa y período', d: 'La empresa a pagar, el mes y el año. Filtrá por contratista, lugar, convenio, categoría o empleado si hace falta.' },
  { t: 'Tipo de remuneración', d: 'Todas (suma todos los tipos) o una sola. Tildá “Traer monto de anticipos” para sumarlos.' },
  { t: 'Consultá', d: 'Se calcula el monto de cada empleado y se descuenta lo ya exportado antes. En rojo, los que no tienen cuenta o CBU cargado (la acreditación es por CBU).' },
  { t: 'Depurá y ajustá', d: 'Podés eliminar seleccionados, quitar los de importe cero y editar el monto de cada empleado.' },
  { t: 'Generá TXT o Excel', d: 'Indicá Fecha de Envío, Fecha de Imputación y el Orden de archivo del día. “Generar TXT” crea el archivo del banco; “Generar Excel” la planilla. Ambos registran el lote.' },
]
</script>

<style scoped>
.sra-ov { position:fixed; inset:0; background:rgba(15,23,42,.55); display:flex; align-items:center; justify-content:center; z-index:1000; animation:sra-fade .2s ease; }
@keyframes sra-fade { from { opacity:0 } to { opacity:1 } }
.sra-modal { background:#fff; width:560px; max-width:94vw; max-height:90vh; overflow:auto; border-radius:14px; position:relative; box-shadow:0 20px 60px rgba(0,0,0,.3); animation:sra-pop .25s cubic-bezier(.2,.9,.3,1.2); }
@keyframes sra-pop { from { transform:translateY(20px) scale(.96); opacity:0 } to { transform:none; opacity:1 } }
.sra-x { position:absolute; top:12px; right:14px; background:rgba(255,255,255,.2); border:none; color:#fff; font-size:16px; cursor:pointer; width:28px; height:28px; border-radius:50%; }
.sra-hero { background:linear-gradient(135deg,#b30000,#ec0000); color:#fff; padding:24px 24px 20px; text-align:center; border-radius:14px 14px 0 0; }
.sra-logo { background:transparent; }
.sra-hero h2 { margin:12px 0 4px; font-size:18px; } .sra-hero p { margin:0; font-size:13px; opacity:.92; }
.sra-body { padding:18px 22px 24px; }
.sra-step { display:flex; gap:12px; padding:10px 0; border-bottom:1px solid #f1f5f9; animation:sra-slide .4s ease both; }
@keyframes sra-slide { from { transform:translateX(-10px); opacity:0 } to { transform:none; opacity:1 } }
.sra-num { flex-shrink:0; width:26px; height:26px; border-radius:50%; background:#ec0000; color:#fff; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:700; }
.sra-step b { font-size:14px; color:#1e293b; } .sra-step p { margin:3px 0 0; font-size:13px; color:#64748b; }
</style>
