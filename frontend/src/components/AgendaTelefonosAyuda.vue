<!-- AgendaTelefonosAyuda.vue — Ayuda del módulo Agenda de Teléfonos. -->
<template>
  <div class="ata-ov" @click.self="$emit('close')">
    <div class="ata-modal">
      <button class="ata-x" @click="$emit('close')">✕</button>
      <div class="ata-hero">
        <svg viewBox="0 0 120 80" class="ata-svg">
          <path d="M44 22 q-8 0 -8 8 q0 22 22 38 q22 8 30 -4 q4 -6 -2 -10 l-8 -4 q-4 -2 -7 1 l-3 3 q-8 -4 -14 -14 l3 -3 q3 -3 1 -7 l-4 -8 q-2 -4 -7 -3 z" class="ata-phone" />
        </svg>
        <h2>Agenda de Teléfonos</h2>
        <p>Lista interna de contactos</p>
      </div>
      <div class="ata-body">
        <div class="ata-step" v-for="(s, i) in pasos" :key="i" :style="{ animationDelay: (i * 90) + 'ms' }">
          <span class="ata-num">{{ i + 1 }}</span>
          <div><b>{{ s.t }}</b><p>{{ s.d }}</p></div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
defineEmits(['close'])
const pasos = [
  { t: 'Agregar un contacto', d: 'Cargá el detalle (área o persona), interno, celular y otros datos, y “Agregar nuevo teléfono”. Hace falta el detalle y al menos un teléfono.' },
  { t: 'Editar', d: 'Podés modificar cualquier campo directamente en la grilla.' },
  { t: 'Borrar', d: 'Tildá los contactos que querés quitar y usá “Borrar seleccionados” (o Todo/Nada para marcar rápido).' },
  { t: 'Confirmar', d: 'Recién al “Confirmar todos los cambios” se guarda la agenda completa.' },
]
</script>

<style scoped>
.ata-ov { position:fixed; inset:0; background:rgba(15,23,42,.55); display:flex; align-items:center; justify-content:center; z-index:1000; animation:ata-fade .2s ease; }
@keyframes ata-fade { from { opacity:0 } to { opacity:1 } }
.ata-modal { background:#fff; width:520px; max-width:94vw; max-height:90vh; overflow:auto; border-radius:14px; position:relative; box-shadow:0 20px 60px rgba(0,0,0,.3); animation:ata-pop .25s cubic-bezier(.2,.9,.3,1.2); }
@keyframes ata-pop { from { transform:translateY(20px) scale(.96); opacity:0 } to { transform:none; opacity:1 } }
.ata-x { position:absolute; top:12px; right:14px; background:rgba(255,255,255,.2); border:none; color:#fff; font-size:16px; cursor:pointer; width:28px; height:28px; border-radius:50%; }
.ata-hero { background:linear-gradient(135deg,#1b4332,#40916c); color:#fff; padding:26px 24px 22px; text-align:center; border-radius:14px 14px 0 0; }
.ata-svg { width:120px; height:80px; }
.ata-phone { fill:rgba(255,255,255,.85); animation:ata-ring 1.2s ease infinite; transform-origin:60px 50px; }
@keyframes ata-ring { 0%,100% { transform:rotate(0) } 10% { transform:rotate(-8deg) } 20% { transform:rotate(8deg) } 30% { transform:rotate(-6deg) } 40% { transform:rotate(6deg) } 50% { transform:rotate(0) } }
.ata-hero h2 { margin:10px 0 4px; font-size:19px; } .ata-hero p { margin:0; font-size:13px; opacity:.9; }
.ata-body { padding:18px 22px 24px; }
.ata-step { display:flex; gap:12px; padding:10px 0; border-bottom:1px solid #f1f5f9; animation:ata-slide .4s ease both; }
@keyframes ata-slide { from { transform:translateX(-10px); opacity:0 } to { transform:none; opacity:1 } }
.ata-num { flex-shrink:0; width:26px; height:26px; border-radius:50%; background:#1b4332; color:#fff; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:700; }
.ata-step b { font-size:14px; color:#1e293b; } .ata-step p { margin:3px 0 0; font-size:13px; color:#64748b; }
</style>
