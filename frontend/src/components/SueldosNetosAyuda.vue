<!-- SueldosNetosAyuda.vue — Ayuda del módulo Exportar/Importar Sueldo Neto. -->
<template>
  <div class="sna-ov" @click.self="$emit('close')">
    <div class="sna-modal">
      <button class="sna-x" @click="$emit('close')">✕</button>
      <div class="sna-hero">
        <svg viewBox="0 0 120 80" class="sna-svg">
          <path d="M30 34 h40 M30 34 l8 -8 M30 34 l8 8" class="sna-arr sna-a1" fill="none" />
          <path d="M90 50 h-40 M90 50 l-8 -8 M90 50 l-8 8" class="sna-arr sna-a2" fill="none" />
          <text x="60" y="74" text-anchor="middle" class="sna-doll">$</text>
        </svg>
        <h2>Exportar / Importar Sueldo Neto</h2>
        <p>Actualizá o descargá el neto de empleados</p>
      </div>
      <div class="sna-body">
        <div class="sna-step" v-for="(s, i) in pasos" :key="i" :style="{ animationDelay: (i * 90) + 'ms' }">
          <span class="sna-num">{{ i + 1 }}</span>
          <div><b>{{ s.t }}</b><p>{{ s.d }}</p></div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
defineEmits(['close'])
const pasos = [
  { t: 'Exportar (solapa Exportar)', d: 'Filtrá por empresa, contratista, lugar, convenio, categoría, sector o subsector y descargá el Excel con código, nombre y neto de los empleados activos.' },
  { t: 'Editá el Excel', d: 'Modificá los netos en la planilla descargada.' },
  { t: 'Importar (solapa Importar)', d: 'Abrí el Excel, indicá en qué columna está el código del empleado y en cuál el sueldo neto. Destildá las filas que no quieras.' },
  { t: 'Procedé', d: 'Por cada fila se busca el empleado por código y se actualiza su neto. Los códigos inexistentes se descartan. Cada cambio queda registrado en el historial del empleado (antes/después).' },
]
</script>

<style scoped>
.sna-ov { position:fixed; inset:0; background:rgba(15,23,42,.55); display:flex; align-items:center; justify-content:center; z-index:1000; animation:sna-fade .2s ease; }
@keyframes sna-fade { from { opacity:0 } to { opacity:1 } }
.sna-modal { background:#fff; width:520px; max-width:94vw; max-height:90vh; overflow:auto; border-radius:14px; position:relative; box-shadow:0 20px 60px rgba(0,0,0,.3); animation:sna-pop .25s cubic-bezier(.2,.9,.3,1.2); }
@keyframes sna-pop { from { transform:translateY(20px) scale(.96); opacity:0 } to { transform:none; opacity:1 } }
.sna-x { position:absolute; top:12px; right:14px; background:rgba(255,255,255,.2); border:none; color:#fff; font-size:16px; cursor:pointer; width:28px; height:28px; border-radius:50%; }
.sna-hero { background:linear-gradient(135deg,#1b4332,#40916c); color:#fff; padding:26px 24px 22px; text-align:center; border-radius:14px 14px 0 0; }
.sna-svg { width:120px; height:80px; }
.sna-arr { stroke:rgba(255,255,255,.85); stroke-width:2.6; stroke-linecap:round; stroke-linejoin:round; }
.sna-a1 { animation:sna-r 2s ease-in-out infinite; } .sna-a2 { animation:sna-l 2s ease-in-out infinite; }
@keyframes sna-r { 0%,100% { transform:translateX(0) } 50% { transform:translateX(6px) } }
@keyframes sna-l { 0%,100% { transform:translateX(0) } 50% { transform:translateX(-6px) } }
.sna-doll { fill:#fff; font-size:20px; font-weight:bold; font-family:Arial; }
.sna-hero h2 { margin:10px 0 4px; font-size:17px; } .sna-hero p { margin:0; font-size:13px; opacity:.9; }
.sna-body { padding:18px 22px 24px; }
.sna-step { display:flex; gap:12px; padding:10px 0; border-bottom:1px solid #f1f5f9; animation:sna-slide .4s ease both; }
@keyframes sna-slide { from { transform:translateX(-10px); opacity:0 } to { transform:none; opacity:1 } }
.sna-num { flex-shrink:0; width:26px; height:26px; border-radius:50%; background:#1b4332; color:#fff; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:700; }
.sna-step b { font-size:14px; color:#1e293b; } .sna-step p { margin:3px 0 0; font-size:13px; color:#64748b; }
</style>
