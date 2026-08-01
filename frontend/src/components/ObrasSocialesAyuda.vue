<!-- ObrasSocialesAyuda.vue — Ayuda del módulo Obras Sociales. -->
<template>
  <div class="osa-ov" @click.self="$emit('close')">
    <div class="osa-modal">
      <button class="osa-x" @click="$emit('close')">✕</button>
      <div class="osa-hero">
        <svg viewBox="0 0 120 80" class="osa-svg">
          <rect x="44" y="22" width="32" height="44" rx="4" class="osa-b" />
          <rect x="56" y="30" width="8" height="3" class="osa-cr" /><rect x="58.5" y="27.5" width="3" height="8" class="osa-cr" />
          <path d="M30 52 h60" class="osa-line osa-l1" />
          <path d="M30 60 h44" class="osa-line osa-l2" />
        </svg>
        <h2>Obras Sociales</h2>
        <p>ABM con cuenta corriente de comprobantes</p>
      </div>
      <div class="osa-body">
        <div class="osa-step" v-for="(s, i) in pasos" :key="i" :style="{ animationDelay: (i * 90) + 'ms' }">
          <span class="osa-num">{{ i + 1 }}</span>
          <div><b>{{ s.t }}</b><p>{{ s.d }}</p></div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
defineEmits(['close'])
const pasos = [
  { t: 'Crear o elegir', d: 'Usá “＋ Nueva” o seleccioná una de la lista. El código se asigna solo.' },
  { t: 'Solapa Datos Generales', d: 'Cargá estado (activa/pasiva), nombre o razón social, domicilio, teléfonos y email. Guardá.' },
  { t: 'Solapa Comprobantes', d: 'Cuenta corriente de la obra social. Cada comprobante tiene fecha, tipo, sucursal, número, importe y empresa.' },
  { t: 'Debe / Haber / Saldo', d: 'El tipo “N.C” (nota de crédito) suma al Haber; cualquier otro tipo suma al Debe. El saldo es Debe − Haber.' },
  { t: 'Agregar / Eliminar / Filtrar', d: 'Agregá comprobantes (no se permiten duplicados de fecha+tipo+sucursal+número), eliminá los marcados, filtrá por período/tipo/empresa e imprimí el informe.' },
  { t: 'Eliminar la obra social', d: 'No se puede si tiene comprobantes cargados.' },
]
</script>

<style scoped>
.osa-ov { position:fixed; inset:0; background:rgba(15,23,42,.55); display:flex; align-items:center; justify-content:center; z-index:1000; animation:osa-fade .2s ease; }
@keyframes osa-fade { from { opacity:0 } to { opacity:1 } }
.osa-modal { background:#fff; width:520px; max-width:94vw; max-height:90vh; overflow:auto; border-radius:14px; position:relative; box-shadow:0 20px 60px rgba(0,0,0,.3); animation:osa-pop .25s cubic-bezier(.2,.9,.3,1.2); }
@keyframes osa-pop { from { transform:translateY(20px) scale(.96); opacity:0 } to { transform:none; opacity:1 } }
.osa-x { position:absolute; top:12px; right:14px; background:rgba(255,255,255,.2); border:none; color:#fff; font-size:16px; cursor:pointer; width:28px; height:28px; border-radius:50%; }
.osa-hero { background:linear-gradient(135deg,#1b4332,#40916c); color:#fff; padding:26px 24px 22px; text-align:center; border-radius:14px 14px 0 0; }
.osa-svg { width:120px; height:80px; }
.osa-b { fill:rgba(255,255,255,.16); stroke:rgba(255,255,255,.6); stroke-width:1.5; animation:osa-rise .6s ease both; }
@keyframes osa-rise { from { transform:translateY(8px); opacity:0 } to { transform:none; opacity:1 } }
.osa-cr { fill:#fff; }
.osa-line { stroke:rgba(255,255,255,.6); stroke-width:2.4; stroke-linecap:round; stroke-dasharray:60; stroke-dashoffset:60; }
.osa-l1 { animation:osa-draw .6s .4s ease forwards; } .osa-l2 { animation:osa-draw .6s .6s ease forwards; }
@keyframes osa-draw { to { stroke-dashoffset:0 } }
.osa-hero h2 { margin:10px 0 4px; font-size:19px; } .osa-hero p { margin:0; font-size:13px; opacity:.9; }
.osa-body { padding:18px 22px 24px; }
.osa-step { display:flex; gap:12px; padding:10px 0; border-bottom:1px solid #f1f5f9; animation:osa-slide .4s ease both; }
@keyframes osa-slide { from { transform:translateX(-10px); opacity:0 } to { transform:none; opacity:1 } }
.osa-num { flex-shrink:0; width:26px; height:26px; border-radius:50%; background:#1b4332; color:#fff; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:700; }
.osa-step b { font-size:14px; color:#1e293b; } .osa-step p { margin:3px 0 0; font-size:13px; color:#64748b; }
</style>
