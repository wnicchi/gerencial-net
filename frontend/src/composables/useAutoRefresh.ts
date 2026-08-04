import { onMounted, onBeforeUnmount } from 'vue'

/**
 * Tablero en tiempo real: llama a `fn()` cada `ms` (por defecto 5 min) mientras el
 * componente está montado y la pestaña está VISIBLE (no gasta llamadas en segundo
 * plano). Además refresca al instante cuando la pestaña vuelve a estar visible.
 *
 * Uso en una vista:  useAutoRefresh(cargar)   // reusa los filtros actuales
 */
export function useAutoRefresh (fn: () => void, ms = 5 * 60 * 1000) {
  let id: number | undefined

  const tick = () => { if (document.visibilityState === 'visible') fn() }
  const onVisible = () => { if (document.visibilityState === 'visible') fn() }

  onMounted(() => {
    id = window.setInterval(tick, ms)
    document.addEventListener('visibilitychange', onVisible)
  })
  onBeforeUnmount(() => {
    if (id !== undefined) window.clearInterval(id)
    document.removeEventListener('visibilitychange', onVisible)
  })
}
