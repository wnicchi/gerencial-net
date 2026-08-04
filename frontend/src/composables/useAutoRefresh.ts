import { onMounted, onBeforeUnmount, ref, computed } from 'vue'

/**
 * Tablero en tiempo real: llama a `fn()` cada `ms` (por defecto 5 min) mientras el
 * componente está montado y la pestaña está VISIBLE (no gasta llamadas en segundo
 * plano; el conteo se pausa si la pestaña se oculta y refresca al instante cuando
 * vuelve a estar visible).
 *
 * Devuelve `{ restante, etiqueta }` para mostrar un contador "Se actualiza en M:SS".
 *
 * Uso:  const { etiqueta } = useAutoRefresh(cargar)
 */
export function useAutoRefresh (fn: () => void, ms = 5 * 60 * 1000) {
  const total = Math.round(ms / 1000)
  const restante = ref(total)
  const etiqueta = computed(() => {
    const m = Math.floor(restante.value / 60)
    const s = restante.value % 60
    return `${m}:${String(s).padStart(2, '0')}`
  })

  let id: number | undefined
  const reset = () => { restante.value = total }
  const onVisible = () => { if (document.visibilityState === 'visible') { fn(); reset() } }

  onMounted(() => {
    id = window.setInterval(() => {
      if (document.visibilityState !== 'visible') return   // pausa el conteo si no está visible
      restante.value -= 1
      if (restante.value <= 0) { fn(); reset() }
    }, 1000)
    document.addEventListener('visibilitychange', onVisible)
  })
  onBeforeUnmount(() => {
    if (id !== undefined) window.clearInterval(id)
    document.removeEventListener('visibilitychange', onVisible)
  })

  return { restante, etiqueta }
}
