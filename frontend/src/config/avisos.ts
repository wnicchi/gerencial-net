/**
 * avisos.ts — Metadatos y filtrado de los avisos del panel de inicio.
 *
 * Cada aviso (sección de /alertas) se asocia a un módulo destino. Se usa tanto
 * en las tarjetas del panel de inicio como en el contador de la campana, para
 * que un usuario solo vea (y cuente) los avisos de módulos que tiene habilitados.
 */
import { puedoVerRuta } from './menuVisible'

export interface MetaAviso { icon: string; color: string; ruta: string }
type PuedoVer = (clave: string) => boolean

/** Mapea el título de un aviso a su icono, color de urgencia y módulo destino. */
export function metaAviso(titulo: string): MetaAviso {
  const t = titulo.toUpperCase()
  if (t.includes('EXAMEN'))          return { icon: '🩺', color: 'rojo',  ruta: '/dashboard/examenes-proximos' }
  if (t.includes('LICENCIA'))        return { icon: '🏥', color: 'rojo',  ruta: '/dashboard/empleados' }
  if (t.includes('SINIESTRO'))       return { icon: '⚠️', color: 'rojo',  ruta: '/dashboard/art-siniestros' }
  if (t.includes('PLAZO DE PRUEBA')) return { icon: '⏳', color: 'ambar', ruta: '/dashboard/empleados' }
  if (t.includes('VACACIONES'))      return { icon: '🏖️', color: 'ambar', ruta: '/dashboard/vacaciones-agregar' }
  if (t.includes('CUMPLEN AÑOS'))    return { icon: '🎂', color: 'ambar', ruta: '/dashboard/empleados' }
  if (t.includes('CELULAR') || t.includes('GARANTIA')) return { icon: '📱', color: 'ambar', ruta: '/dashboard/celulares' }
  if (t.includes('FALTA CARGAR'))    return { icon: '📝', color: 'ambar', ruta: '/dashboard/empleados' }
  if (t.includes('DECADA') || t.includes('AÑOS')) return { icon: '🏅', color: 'azul', ruta: '/dashboard/empleados' }
  if (t.includes('PERMISO'))         return { icon: '📋', color: 'azul',  ruta: '/dashboard/permisos-laborales' }
  if (t.includes('ROPA'))            return { icon: '👕', color: 'azul',  ruta: '/dashboard/entrega-ropa' }
  if (t.includes('MULTA'))           return { icon: '🧾', color: 'azul',  ruta: '/dashboard/multas-listados' }
  return { icon: '🔔', color: 'azul', ruta: '/dashboard/empleados' }
}

/** Filtra las secciones de avisos dejando solo las de módulos que el usuario ve. */
export function avisosVisibles<T extends { titulo: string }>(
  secciones: T[], puedoVer: PuedoVer, esAdmin: boolean,
): T[] {
  return secciones.filter((s) => puedoVerRuta(metaAviso(s.titulo).ruta, puedoVer, esAdmin))
}
