/**
 * menuVisible.ts — Utilidades de visibilidad del menú por permisos.
 *
 * Centraliza la lógica de "qué puede ver el usuario" para que el menú, las
 * tarjetas del panel de inicio, los accesos directos y el guard de rutas usen
 * exactamente el mismo criterio. Así, si un módulo no está habilitado para el
 * usuario, no aparece por NINGÚN atajo (menú, tarjeta, acceso o URL directa).
 */
import { menuConfig, type MenuItem } from './menu'

type PuedoVer = (clave: string) => boolean

/** ¿Es visible este ítem (hoja o grupo) para el usuario actual? */
export function itemVisible(item: MenuItem, puedoVer: PuedoVer, esAdmin: boolean): boolean {
  if (item.separador) return false
  if (item.hijos && item.hijos.length) {
    return item.hijos.some((h) => itemVisible(h, puedoVer, esAdmin))
  }
  if (item.adminOnly && !esAdmin) return false
  if (!item.id) return true
  return puedoVer(item.id)
}

/**
 * Depura una lista de ítems: deja solo los visibles y elimina los separadores
 * que quedarían "colgados" (al inicio, al final o entre grupos ocultos).
 */
export function depurarMenu(items: MenuItem[], puedoVer: PuedoVer, esAdmin: boolean): MenuItem[] {
  const res: MenuItem[] = []
  for (const it of items) {
    if (it.separador) {
      // Un separador solo entra si ya hay algo visible antes y no es otro separador.
      if (res.length && !res[res.length - 1].separador) res.push(it)
    } else if (itemVisible(it, puedoVer, esAdmin)) {
      res.push(it)
    }
  }
  // Quitar separadores sobrantes al final.
  while (res.length && res[res.length - 1].separador) res.pop()
  return res
}

/** Busca en el árbol del menú el id del ítem cuya ruta coincide (o null). */
function buscarIdPorRuta(items: MenuItem[], ruta: string): string | null {
  for (const it of items) {
    if (it.ruta && it.ruta === ruta && it.id) return it.id
    if (it.hijos) {
      const r = buscarIdPorRuta(it.hijos, ruta)
      if (r) return r
    }
  }
  return null
}

/** id de permiso asociado a una ruta del dashboard (o null si no está en el menú). */
export function idDeRuta(ruta: string): string | null {
  return buscarIdPorRuta(menuConfig, ruta)
}

/**
 * ¿El usuario puede acceder a esta ruta? Si la ruta corresponde a un ítem del
 * menú, se valida el permiso; si no está en el menú (ruta auxiliar), se permite.
 */
export function puedoVerRuta(ruta: string, puedoVer: PuedoVer, esAdmin: boolean): boolean {
  if (esAdmin) return true
  const id = idDeRuta(ruta)
  if (!id) return true
  return puedoVer(id)
}

/** Busca un ítem del menú por su id (puede ser hoja o grupo). */
function buscarPorId(items: MenuItem[], id: string): MenuItem | null {
  for (const it of items) {
    if (it.id === id) return it
    if (it.hijos) {
      const r = buscarPorId(it.hijos, id)
      if (r) return r
    }
  }
  return null
}

/**
 * ¿El usuario puede ver el ítem con este id? Sirve tanto para hojas como para
 * grupos (un grupo es visible si al menos una de sus hojas lo es). Se usa en los
 * accesos rápidos, que pueden apuntar a un grupo (ej. "Liquidaciones").
 */
export function puedoVerId(id: string, puedoVer: PuedoVer, esAdmin: boolean): boolean {
  const it = buscarPorId(menuConfig, id)
  if (!it) return true
  return itemVisible(it, puedoVer, esAdmin)
}

/**
 * El módulo "Vencimientos" (acceso del topbar, no está en el menú) junta datos de
 * exámenes, carnets, empleados y celulares. El usuario lo ve solo si tiene al
 * menos uno de esos módulos habilitados.
 */
export const PERMISOS_VENCIMIENTOS = ['exam-proximos', 'empleados-carnet', 'empleados-abm', 'cel-equipos']
export function puedeVerVencimientos(puedoVer: PuedoVer, esAdmin: boolean): boolean {
  return PERMISOS_VENCIMIENTOS.some((p) => puedoVerId(p, puedoVer, esAdmin))
}
