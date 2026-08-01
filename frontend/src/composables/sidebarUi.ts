import { ref } from 'vue'

/**
 * Señal para pedirle al SidebarMenu que colapse el panel 2 (sub-ítems).
 * Se incrementa desde acciones que quieren maximizar el área de contenido
 * (ej: elegir un empleado en el buscador global → abrir el ABM Empleados a lo ancho).
 * El SidebarMenu observa este contador y, ante un cambio, oculta el panel 2.
 */
export const pedidoColapsarMenu = ref(0)

/** Dispara el pedido de colapsar el menú lateral. */
export function colapsarMenu(): void {
  pedidoColapsarMenu.value++
}
