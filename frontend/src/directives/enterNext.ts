import type { Directive } from 'vue'

/**
 * Directiva v-enter-next — regla del proyecto para las ALTAS/formularios.
 *
 * Al presionar Enter en un campo de texto, mueve el foco al siguiente campo del
 * formulario (en orden de aparición), además de Tab y mouse. Se aplica al
 * CONTENEDOR del formulario:  <div class="modal" v-enter-next> … </div>
 *
 * - Solo actúa sobre <input> de texto (no checkbox/radio/botones).
 * - En <textarea> NO intercepta: Enter sigue haciendo salto de línea.
 * - Si no hay un campo siguiente, no hace nada (deja que actúe el handler propio,
 *   p. ej. un @keydown.enter="guardar" en formularios de un solo campo).
 * - Ignora campos deshabilitados u ocultos.
 */
const TIPOS_IGNORADOS = ['checkbox', 'radio', 'button', 'submit', 'reset', 'file']

function esFocusable (el: Element): boolean {
  const e = el as HTMLInputElement
  if (e.disabled) return false
  if (e.tagName === 'INPUT' && TIPOS_IGNORADOS.includes(e.type)) return false
  // visible
  return !!(e.offsetParent !== null || e.getClientRects().length)
}

interface ConHandler extends HTMLElement { __enterNext__?: (e: KeyboardEvent) => void }

export const enterNext: Directive<ConHandler> = {
  mounted (el) {
    const handler = (e: KeyboardEvent) => {
      if (e.key !== 'Enter' || e.shiftKey || e.ctrlKey || e.altKey) return
      const t = e.target as HTMLElement
      if (!t || t.tagName !== 'INPUT') return            // textarea/select sin tocar
      if (TIPOS_IGNORADOS.includes((t as HTMLInputElement).type)) return

      const campos = (Array.from(el.querySelectorAll('input, textarea, select')) as HTMLElement[])
        .filter(esFocusable)
      const i = campos.indexOf(t)
      if (i === -1) return
      const next = campos[i + 1]
      if (next) {
        e.preventDefault()
        next.focus()
        if (typeof (next as HTMLInputElement).select === 'function') (next as HTMLInputElement).select()
      }
    }
    el.__enterNext__ = handler
    el.addEventListener('keydown', handler)
  },
  unmounted (el) {
    if (el.__enterNext__) el.removeEventListener('keydown', el.__enterNext__)
  },
}

export default enterNext
