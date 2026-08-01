<!-- BancoSantaFeLogo.vue — Logo del Nuevo Banco de Santa Fe (aproximación con SVG). -->
<template>
  <div class="nbsf" :style="{ '--h': alto + 'px' }">
    <svg class="nbsf-ico" viewBox="0 0 64 64" aria-hidden="true">
      <!-- pétalos: rojo y azul alternados -->
      <path d="M32 6 C40 14 40 24 32 32 C24 24 24 14 32 6 Z" fill="#e2231a" />
      <path d="M58 32 C50 40 40 40 32 32 C40 24 50 24 58 32 Z" fill="#1b3a8c" />
      <path d="M32 58 C24 50 24 40 32 32 C40 40 40 50 32 58 Z" fill="#e2231a" />
      <path d="M6 32 C14 24 24 24 32 32 C24 40 14 40 6 32 Z" fill="#1b3a8c" />
      <circle cx="32" cy="32" r="5" fill="#fff" />
    </svg>
    <div class="nbsf-tx">
      <span class="nbsf-1">Nuevo Banco</span>
      <span class="nbsf-2">de Santa Fe</span>
    </div>
  </div>
</template>

<script setup lang="ts">
withDefaults(defineProps<{ alto?: number }>(), { alto: 40 })
</script>

<style scoped>
.nbsf { display:inline-flex; align-items:center; gap:10px; }
.nbsf-ico { width:var(--h); height:var(--h); flex-shrink:0; }
.nbsf-tx { display:flex; flex-direction:column; line-height:1.05; }
.nbsf-1 { font-weight:800; color:#1b3a8c; font-size:calc(var(--h) * .42); letter-spacing:.2px; }
.nbsf-2 { font-weight:700; color:#e2231a; font-size:calc(var(--h) * .38); }
</style>
