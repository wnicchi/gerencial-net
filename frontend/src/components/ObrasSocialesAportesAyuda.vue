<!-- ObrasSocialesAportesAyuda.vue — Ayuda del módulo Planilla Contribuciones y Aportes. -->
<template>
  <div class="apa-ov" @click.self="$emit('close')">
    <div class="apa-modal">
      <button class="apa-x" @click="$emit('close')">✕</button>
      <div class="apa-hero">
        <svg viewBox="0 0 120 80" class="apa-svg">
          <rect x="30" y="16" width="60" height="52" rx="4" class="apa-sheet" />
          <line x1="30" y1="30" x2="90" y2="30" class="apa-l apa-l1" />
          <line x1="50" y1="16" x2="50" y2="68" class="apa-l apa-l2" />
          <line x1="70" y1="16" x2="70" y2="68" class="apa-l apa-l3" />
          <rect x="71" y="44" width="18" height="10" rx="2" class="apa-hl" />
        </svg>
        <h2>Planilla Contribuciones y Aportes</h2>
        <p>Edición de aportes de obras sociales</p>
      </div>
      <div class="apa-body">
        <div class="apa-step" v-for="(s, i) in pasos" :key="i" :style="{ animationDelay: (i * 90) + 'ms' }">
          <span class="apa-num">{{ i + 1 }}</span>
          <div><b>{{ s.t }}</b><p>{{ s.d }}</p></div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
defineEmits(['close'])
const pasos = [
  { t: 'Elegí período y empresa', d: 'Seleccioná mes, año y empresa, y “Consultar”. Aparecen todos los empleados activos de la empresa (a los que falten se los agrega en cero).' },
  { t: 'Datos que vienen de la importación', d: 'Total de Contribuciones y de Aportes salen de “Importar Excel Pagos Aportes” y no se editan acá.' },
  { t: 'Qué podés editar', d: 'Tres columnas: Aportes Reconocidos, FC medife y FC medicus.' },
  { t: 'Diferencias automáticas', d: 'Dif.Medife = FC medife − Reconocido (si FC medife > 0); igual para Medicus. Abajo se totalizan las diferencias. Las filas que tocás se resaltan en amarillo.' },
  { t: 'Confirmar / Imprimir', d: '“Confirmar cambios” guarda lo editado. “Imprimir informe” genera el PDF con los totales.' },
]
</script>

<style scoped>
.apa-ov { position:fixed; inset:0; background:rgba(15,23,42,.55); display:flex; align-items:center; justify-content:center; z-index:1000; animation:apa-fade .2s ease; }
@keyframes apa-fade { from { opacity:0 } to { opacity:1 } }
.apa-modal { background:#fff; width:520px; max-width:94vw; max-height:90vh; overflow:auto; border-radius:14px; position:relative; box-shadow:0 20px 60px rgba(0,0,0,.3); animation:apa-pop .25s cubic-bezier(.2,.9,.3,1.2); }
@keyframes apa-pop { from { transform:translateY(20px) scale(.96); opacity:0 } to { transform:none; opacity:1 } }
.apa-x { position:absolute; top:12px; right:14px; background:rgba(255,255,255,.2); border:none; color:#fff; font-size:16px; cursor:pointer; width:28px; height:28px; border-radius:50%; }
.apa-hero { background:linear-gradient(135deg,#1b4332,#40916c); color:#fff; padding:26px 24px 22px; text-align:center; border-radius:14px 14px 0 0; }
.apa-svg { width:120px; height:80px; }
.apa-sheet { fill:rgba(255,255,255,.14); stroke:rgba(255,255,255,.6); stroke-width:1.5; animation:apa-rise .6s ease both; }
@keyframes apa-rise { from { transform:translateY(8px); opacity:0 } to { transform:none; opacity:1 } }
.apa-l { stroke:rgba(255,255,255,.55); stroke-width:1.6; stroke-dasharray:60; stroke-dashoffset:60; }
.apa-l1 { animation:apa-draw .5s .3s ease forwards; } .apa-l2 { animation:apa-draw .5s .45s ease forwards; } .apa-l3 { animation:apa-draw .5s .6s ease forwards; }
@keyframes apa-draw { to { stroke-dashoffset:0 } }
.apa-hl { fill:#fde047; opacity:0; animation:apa-hl .4s 1s ease forwards; } @keyframes apa-hl { to { opacity:.85 } }
.apa-hero h2 { margin:10px 0 4px; font-size:17px; } .apa-hero p { margin:0; font-size:13px; opacity:.9; }
.apa-body { padding:18px 22px 24px; }
.apa-step { display:flex; gap:12px; padding:10px 0; border-bottom:1px solid #f1f5f9; animation:apa-slide .4s ease both; }
@keyframes apa-slide { from { transform:translateX(-10px); opacity:0 } to { transform:none; opacity:1 } }
.apa-num { flex-shrink:0; width:26px; height:26px; border-radius:50%; background:#1b4332; color:#fff; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:700; }
.apa-step b { font-size:14px; color:#1e293b; } .apa-step p { margin:3px 0 0; font-size:13px; color:#64748b; }
</style>
