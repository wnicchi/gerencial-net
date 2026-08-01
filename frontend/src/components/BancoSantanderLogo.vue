<!-- BancoSantanderLogo.vue — Logo del Banco Santander (aproximación con SVG). -->
<template>
  <div class="san" :style="{ '--h': alto + 'px' }">
    <span class="san-box">
      <svg class="san-ico" viewBox="0 0 32 32" aria-hidden="true">
        <path d="M16 4 C12 9 12 13 16 16 C20 19 20 23 16 28 C20 23 20 19 16 16 C12 13 12 9 16 4 Z" fill="#fff" />
        <path d="M9 9 C9 15 12 18 16 16 C12 14 11 12 9 9 Z" fill="#fff" opacity=".85" />
        <path d="M23 23 C23 17 20 14 16 16 C20 18 21 20 23 23 Z" fill="#fff" opacity=".85" />
      </svg>
      <span class="san-tx">Santander</span>
    </span>
  </div>
</template>

<script setup lang="ts">
withDefaults(defineProps<{ alto?: number }>(), { alto: 40 })
</script>

<style scoped>
.san { display:inline-flex; align-items:center; }
.san-box { display:inline-flex; align-items:center; gap:6px; background:#ec0000; border-radius:4px; padding:calc(var(--h) * .14) calc(var(--h) * .28); }
.san-ico { width:calc(var(--h) * .62); height:calc(var(--h) * .62); flex-shrink:0; }
.san-tx { color:#fff; font-weight:800; font-size:calc(var(--h) * .42); letter-spacing:.3px; }
</style>
