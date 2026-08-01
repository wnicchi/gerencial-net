<!-- ObrasListadosAyuda.vue — Ayuda del módulo Obras - Listados. -->
<template>
  <div class="ola-ov" @click.self="$emit('close')">
    <div class="ola-modal">
      <button class="ola-x" @click="$emit('close')">✕</button>
      <div class="ola-hero">
        <svg viewBox="0 0 120 80" class="ola-svg">
          <rect x="34" y="14" width="44" height="52" rx="4" class="ola-doc" />
          <line x1="42" y1="26" x2="70" y2="26" class="ola-l ola-l1" />
          <line x1="42" y1="36" x2="70" y2="36" class="ola-l ola-l2" />
          <line x1="42" y1="46" x2="70" y2="46" class="ola-l ola-l3" />
          <line x1="42" y1="56" x2="60" y2="56" class="ola-l ola-l4" />
        </svg>
        <h2>Obras - Listados</h2>
        <p>Listado de obras y sus obreros</p>
      </div>
      <div class="ola-body">
        <div class="ola-step" v-for="(s, i) in pasos" :key="i" :style="{ animationDelay: (i * 90) + 'ms' }">
          <span class="ola-num">{{ i + 1 }}</span>
          <div><b>{{ s.t }}</b><p>{{ s.d }}</p></div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
defineEmits(['close'])
const pasos = [
  { t: 'Elegí el alcance', d: '“Todas” las obras o “Una” obra puntual (elegida del combo).' },
  { t: 'Elegí el estado', d: '“Histórico” muestra todas las obras; “Solo habilitadas” muestra únicamente las vigentes (ya empezaron y no finalizaron).' },
  { t: 'Elegí el orden', d: 'Por contratista o por código de obra.' },
  { t: 'Consultá', d: 'Cada obra muestra sus datos y la lista de obreros con su estado (P=habilitado a ingresar mientras la obra está vigente, D=no).' },
  { t: 'Visualizá / Imprimí', d: 'Generá el PDF para verlo, descargarlo o imprimirlo.' },
]
</script>

<style scoped>
.ola-ov { position:fixed; inset:0; background:rgba(15,23,42,.55); display:flex; align-items:center; justify-content:center; z-index:1000; animation:ola-fade .2s ease; }
@keyframes ola-fade { from { opacity:0 } to { opacity:1 } }
.ola-modal { background:#fff; width:520px; max-width:94vw; max-height:90vh; overflow:auto; border-radius:14px; position:relative; box-shadow:0 20px 60px rgba(0,0,0,.3); animation:ola-pop .25s cubic-bezier(.2,.9,.3,1.2); }
@keyframes ola-pop { from { transform:translateY(20px) scale(.96); opacity:0 } to { transform:none; opacity:1 } }
.ola-x { position:absolute; top:12px; right:14px; background:rgba(255,255,255,.2); border:none; color:#fff; font-size:16px; cursor:pointer; width:28px; height:28px; border-radius:50%; }
.ola-hero { background:linear-gradient(135deg,#1b4332,#40916c); color:#fff; padding:26px 24px 22px; text-align:center; border-radius:14px 14px 0 0; }
.ola-svg { width:120px; height:80px; }
.ola-doc { fill:rgba(255,255,255,.14); stroke:rgba(255,255,255,.6); stroke-width:1.5; animation:ola-rise .6s ease both; }
@keyframes ola-rise { from { transform:translateY(8px); opacity:0 } to { transform:none; opacity:1 } }
.ola-l { stroke:rgba(255,255,255,.65); stroke-width:2.4; stroke-linecap:round; stroke-dasharray:30; stroke-dashoffset:30; }
.ola-l1 { animation:ola-draw .5s .3s ease forwards; } .ola-l2 { animation:ola-draw .5s .45s ease forwards; } .ola-l3 { animation:ola-draw .5s .6s ease forwards; } .ola-l4 { animation:ola-draw .5s .75s ease forwards; }
@keyframes ola-draw { to { stroke-dashoffset:0 } }
.ola-hero h2 { margin:10px 0 4px; font-size:19px; } .ola-hero p { margin:0; font-size:13px; opacity:.9; }
.ola-body { padding:18px 22px 24px; }
.ola-step { display:flex; gap:12px; padding:10px 0; border-bottom:1px solid #f1f5f9; animation:ola-slide .4s ease both; }
@keyframes ola-slide { from { transform:translateX(-10px); opacity:0 } to { transform:none; opacity:1 } }
.ola-num { flex-shrink:0; width:26px; height:26px; border-radius:50%; background:#1b4332; color:#fff; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:700; }
.ola-step b { font-size:14px; color:#1e293b; } .ola-step p { margin:3px 0 0; font-size:13px; color:#64748b; }
</style>
