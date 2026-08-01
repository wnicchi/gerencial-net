<!-- BancoSantanderRioConsultarAyuda.vue — Ayuda del módulo Bco. Santander Río - Consultar. -->
<template>
  <div class="bca-ov" @click.self="$emit('close')">
    <div class="bca-modal">
      <button class="bca-x" @click="$emit('close')">✕</button>
      <div class="bca-hero">
        <BancoSantanderLogo :alto="40" class="bca-logo" />
        <h2>Bco. Santander Río - Consultar</h2>
        <p>Lotes de pago, autorización y regeneración de archivos</p>
      </div>
      <div class="bca-body">
        <div class="bca-step" v-for="(s, i) in pasos" :key="i" :style="{ animationDelay: (i * 90) + 'ms' }">
          <span class="bca-num">{{ i + 1 }}</span>
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
  { t: 'Elegí la empresa', d: 'Se listan los lotes ya exportados al banco. En verde los pagados; en rojo y tachado los anulados.' },
  { t: 'Seleccioná un lote', d: 'La tilde elige un solo lote a la vez y completa el Nº de autorización con su número. Al hacer clic ves sus empleados.' },
  { t: 'Generar el movimiento de pagos', d: 'Elegí el concepto y enviá el pago al sistema de Gestión para que GERENCIA lo autorice. No se duplica si ya fue enviado.' },
  { t: 'Ajustar y regenerar', d: 'Sobre los empleados podés quitar empleados, quitar los de importe cero o editar montos, y “Generar nuevo TXT” o “Generar nuevo Excel” (crea un lote nuevo).' },
  { t: 'Eliminar / Imprimir', d: '“Eliminar seleccionado” anula el lote (y lo marca anulado en Gestión si ya estaba pagado). También podés sacar el PDF del lote.' },
]
</script>

<style scoped>
.bca-ov { position:fixed; inset:0; background:rgba(15,23,42,.55); display:flex; align-items:center; justify-content:center; z-index:1000; animation:bca-fade .2s ease; }
@keyframes bca-fade { from { opacity:0 } to { opacity:1 } }
.bca-modal { background:#fff; width:560px; max-width:94vw; max-height:90vh; overflow:auto; border-radius:14px; position:relative; box-shadow:0 20px 60px rgba(0,0,0,.3); animation:bca-pop .25s cubic-bezier(.2,.9,.3,1.2); }
@keyframes bca-pop { from { transform:translateY(20px) scale(.96); opacity:0 } to { transform:none; opacity:1 } }
.bca-x { position:absolute; top:12px; right:14px; background:rgba(255,255,255,.2); border:none; color:#fff; font-size:16px; cursor:pointer; width:28px; height:28px; border-radius:50%; }
.bca-hero { background:linear-gradient(135deg,#b30000,#ec0000); color:#fff; padding:24px 24px 20px; text-align:center; border-radius:14px 14px 0 0; }
.bca-logo { background:transparent; }
.bca-hero h2 { margin:12px 0 4px; font-size:18px; } .bca-hero p { margin:0; font-size:13px; opacity:.92; }
.bca-body { padding:18px 22px 24px; }
.bca-step { display:flex; gap:12px; padding:10px 0; border-bottom:1px solid #f1f5f9; animation:bca-slide .4s ease both; }
@keyframes bca-slide { from { transform:translateX(-10px); opacity:0 } to { transform:none; opacity:1 } }
.bca-num { flex-shrink:0; width:26px; height:26px; border-radius:50%; background:#ec0000; color:#fff; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:700; }
.bca-step b { font-size:14px; color:#1e293b; } .bca-step p { margin:3px 0 0; font-size:13px; color:#64748b; }
</style>
