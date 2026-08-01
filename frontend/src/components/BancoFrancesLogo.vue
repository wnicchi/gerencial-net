<!-- BancoFrancesLogo.vue — Logo del Banco Francés (BBVA) (aproximación). -->
<template>
  <div class="bbva" :style="{ '--h': alto + 'px' }">
    <span class="bbva-box">BBVA</span>
    <span class="bbva-tx">Banco Francés</span>
  </div>
</template>

<script setup lang="ts">
withDefaults(defineProps<{ alto?: number }>(), { alto: 40 })
</script>

<style scoped>
.bbva { display:inline-flex; align-items:center; gap:8px; }
.bbva-box { background:#072146; color:#fff; font-weight:800; font-size:calc(var(--h) * .4); letter-spacing:.5px; padding:calc(var(--h) * .14) calc(var(--h) * .22); border-radius:3px; }
.bbva-tx { color:#072146; font-weight:700; font-size:calc(var(--h) * .42); }
</style>
