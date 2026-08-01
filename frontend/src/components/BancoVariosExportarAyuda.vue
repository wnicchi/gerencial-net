<!-- BancoVariosExportarAyuda.vue — Ayuda del módulo Bcos. Varios (Santander) - Exportar. -->
<template>
  <div class="var-ov" @click.self="$emit('close')">
    <div class="var-modal">
      <button class="var-x" @click="$emit('close')">✕</button>
      <div class="var-hero">
        <BancoSantanderLogo :alto="38" />
        <h2>Bcos. Varios (Santander) - Exportar</h2>
        <p>Acreditación por CBU a otros bancos vía Santander</p>
      </div>
      <div class="var-body">
        <div class="var-nota">📌 Toma a los empleados cuyo banco de cobro <b>NO es</b> Santander Río, Santa Fe ni Francés (esos tienen su propio módulo). Por eso no hay selector de banco.</div>
        <div class="var-step" v-for="(s, i) in pasos" :key="i" :style="{ animationDelay: (i * 90) + 'ms' }">
          <span class="var-num">{{ i + 1 }}</span>
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
  { t: 'Consultá', d: 'Se calcula el monto de cada empleado y se descuenta lo ya exportado antes. En rojo, los que no tienen cuenta o CBU cargado.' },
  { t: 'Depurá y ajustá', d: 'Podés eliminar seleccionados, quitar los de importe cero y editar el monto de cada empleado.' },
  { t: 'Generá TXT o Excel', d: 'Indicá Fecha de Envío, Fecha de Imputación y el Orden de archivo del día. Ambos archivos registran el lote.' },
]
</script>

<style scoped>
.var-ov { position:fixed; inset:0; background:rgba(15,23,42,.55); display:flex; align-items:center; justify-content:center; z-index:1000; animation:var-fade .2s ease; }
@keyframes var-fade { from { opacity:0 } to { opacity:1 } }
.var-modal { background:#fff; width:560px; max-width:94vw; max-height:90vh; overflow:auto; border-radius:14px; position:relative; box-shadow:0 20px 60px rgba(0,0,0,.3); animation:var-pop .25s cubic-bezier(.2,.9,.3,1.2); }
@keyframes var-pop { from { transform:translateY(20px) scale(.96); opacity:0 } to { transform:none; opacity:1 } }
.var-x { position:absolute; top:12px; right:14px; background:rgba(255,255,255,.2); border:none; color:#fff; font-size:16px; cursor:pointer; width:28px; height:28px; border-radius:50%; }
.var-hero { background:linear-gradient(135deg,#7f1d1d,#ec0000); color:#fff; padding:24px 24px 20px; text-align:center; border-radius:14px 14px 0 0; }
.var-hero h2 { margin:12px 0 4px; font-size:18px; } .var-hero p { margin:0; font-size:13px; opacity:.92; }
.var-body { padding:18px 22px 24px; }
.var-nota { background:#fff7ed; border:1px solid #fed7aa; color:#9a3412; padding:10px 14px; border-radius:8px; font-size:13px; margin-bottom:14px; }
.var-step { display:flex; gap:12px; padding:10px 0; border-bottom:1px solid #f1f5f9; animation:var-slide .4s ease both; }
@keyframes var-slide { from { transform:translateX(-10px); opacity:0 } to { transform:none; opacity:1 } }
.var-num { flex-shrink:0; width:26px; height:26px; border-radius:50%; background:#ec0000; color:#fff; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:700; }
.var-step b { font-size:14px; color:#1e293b; } .var-step p { margin:3px 0 0; font-size:13px; color:#64748b; }
</style>
