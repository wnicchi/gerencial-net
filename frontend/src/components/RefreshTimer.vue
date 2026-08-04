<template>
  <span class="refresh-timer" title="El tablero se actualiza solo (tiempo real)">
    <span class="rt-dot"></span> Se actualiza en {{ label }}
  </span>
</template>

<script setup lang="ts">
defineProps<{ label: string }>()
</script>

<style scoped>
.refresh-timer {
  display: inline-flex; align-items: center; gap: 0.4rem;
  font-size: 0.76rem; color: #40916c; font-weight: 600;
  background: #f0faf4; border: 1px solid #c3e6cb; border-radius: 20px;
  padding: 0.28rem 0.72rem; white-space: nowrap;
}
.rt-dot { width: 7px; height: 7px; border-radius: 50%; background: #16a34a; animation: rtpulse 2s ease-in-out infinite; }
@keyframes rtpulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.25; } }
</style>
