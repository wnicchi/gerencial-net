<!-- BancoNacionLogo.vue — Logo del Banco de la Nación Argentina (aproximación). -->
<template>
  <div class="bna" :style="{ '--h': alto + 'px' }">
    <span class="bna-box">
      <svg class="bna-ico" viewBox="0 0 40 32" aria-hidden="true">
        <path d="M20 3 L36 12 L4 12 Z" fill="#fff" />
        <rect x="6" y="14" width="3" height="12" fill="#fff" /><rect x="13" y="14" width="3" height="12" fill="#fff" />
        <rect x="20" y="14" width="3" height="12" fill="#fff" /><rect x="27" y="14" width="3" height="12" fill="#fff" />
        <rect x="3" y="27" width="34" height="3" fill="#fff" />
      </svg>
    </span>
    <span class="bna-tx">Banco Nación</span>
  </div>
</template>

<script setup lang="ts">
withDefaults(defineProps<{ alto?: number }>(), { alto: 40 })
</script>

<style scoped>
.bna { display:inline-flex; align-items:center; gap:8px; }
.bna-box { background:#1d6a78; display:inline-flex; align-items:center; justify-content:center; padding:calc(var(--h) * .16) calc(var(--h) * .2); border-radius:4px; }
.bna-ico { width:calc(var(--h) * .7); height:calc(var(--h) * .56); }
.bna-tx { color:#1d6a78; font-weight:800; font-size:calc(var(--h) * .42); }
</style>
