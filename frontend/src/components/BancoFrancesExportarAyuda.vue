<!-- BancoFrancesExportarAyuda.vue — Ayuda del módulo Bco. Francés (BBVA) - Exportar. -->
<template>
  <div class="fra-ov" @click.self="$emit('close')">
    <div class="fra-modal">
      <button class="fra-x" @click="$emit('close')">✕</button>
      <div class="fra-hero">
        <BancoFrancesLogo :alto="38" class="fra-logo" />
        <h2>Bco. Francés (BBVA) - Exportar</h2>
        <p>Archivo de acreditación de sueldos (por CBU)</p>
      </div>
      <div class="fra-body">
        <div class="fra-step" v-for="(s, i) in pasos" :key="i" :style="{ animationDelay: (i * 90) + 'ms' }">
          <span class="fra-num">{{ i + 1 }}</span>
          <div><b>{{ s.t }}</b><p>{{ s.d }}</p></div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import BancoFrancesLogo from '@/components/BancoFrancesLogo.vue'
defineEmits(['close'])
const pasos = [
  { t: 'Elegí empresa y período', d: 'La empresa a pagar, el mes y el año. Filtrá por contratista, lugar, convenio, categoría o empleado si hace falta.' },
  { t: 'Tipo de remuneración', d: 'Todas (suma todos los tipos) o una sola. Tildá “Traer monto de anticipos” para sumarlos.' },
  { t: 'Consultá', d: 'Se calcula el monto de cada empleado y se descuenta lo ya exportado antes. En rojo, los que no tienen cuenta o CBU cargado (la acreditación es por CBU).' },
  { t: 'Depurá y ajustá', d: 'Podés eliminar seleccionados, quitar los de importe cero y editar el monto de cada empleado.' },
  { t: 'Generá el TXT', d: 'Indicá Fecha de Envío, Fecha de Imputación y la modalidad (concepto). “Generar TXT para el banco” crea el archivo del BBVA y registra el lote.' },
]
</script>

<style scoped>
.fra-ov { position:fixed; inset:0; background:rgba(15,23,42,.55); display:flex; align-items:center; justify-content:center; z-index:1000; animation:fra-fade .2s ease; }
@keyframes fra-fade { from { opacity:0 } to { opacity:1 } }
.fra-modal { background:#fff; width:560px; max-width:94vw; max-height:90vh; overflow:auto; border-radius:14px; position:relative; box-shadow:0 20px 60px rgba(0,0,0,.3); animation:fra-pop .25s cubic-bezier(.2,.9,.3,1.2); }
@keyframes fra-pop { from { transform:translateY(20px) scale(.96); opacity:0 } to { transform:none; opacity:1 } }
.fra-x { position:absolute; top:12px; right:14px; background:rgba(255,255,255,.2); border:none; color:#fff; font-size:16px; cursor:pointer; width:28px; height:28px; border-radius:50%; }
.fra-hero { background:linear-gradient(135deg,#072146,#1e4fa0); color:#fff; padding:24px 24px 20px; text-align:center; border-radius:14px 14px 0 0; }
.fra-logo { background:#fff; padding:6px 12px; border-radius:8px; }
.fra-hero h2 { margin:12px 0 4px; font-size:18px; } .fra-hero p { margin:0; font-size:13px; opacity:.92; }
.fra-body { padding:18px 22px 24px; }
.fra-step { display:flex; gap:12px; padding:10px 0; border-bottom:1px solid #f1f5f9; animation:fra-slide .4s ease both; }
@keyframes fra-slide { from { transform:translateX(-10px); opacity:0 } to { transform:none; opacity:1 } }
.fra-num { flex-shrink:0; width:26px; height:26px; border-radius:50%; background:#072146; color:#fff; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:700; }
.fra-step b { font-size:14px; color:#1e293b; } .fra-step p { margin:3px 0 0; font-size:13px; color:#64748b; }
</style>
