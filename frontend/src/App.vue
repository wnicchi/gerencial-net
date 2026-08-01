<script setup lang="ts">
import { onMounted } from 'vue'
import { RouterView } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

// Detecta la empresa del deploy (para el fondo/logo correcto ya en el login).
onMounted(() => { useAuthStore().cargarEmpresa() })
</script>

<template>
  <RouterView />
</template>
