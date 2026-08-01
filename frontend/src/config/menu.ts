// ──────────────────────────────────────────────────────────────
//  Menú lateral — Tablero Gerencial (GERENCIAL.NET)
//  App liviana: consolida Estadísticas + Alertas de RRHH y Stock/WMS
//  para análisis gerencial (lectura sobre sqlRRHHlog + LOGIST_UNIVERSAL).
// ──────────────────────────────────────────────────────────────

export interface MenuItem {
  id: string
  label: string
  icono: string
  ruta?: string
  hijos?: MenuItem[]
  separador?: boolean
  adminOnly?: boolean   // visible solo para administradores (es_admin)
}

export const menuConfig: MenuItem[] = [
  // ══════════════════ TABLERO GERENCIAL ══════════════════
  {
    id: 'tablero', label: 'Tablero Gerencial', icono: '📊',
    hijos: [
      { id: 'tab-resumen', label: 'Resumen',                  icono: '🏠', ruta: '/dashboard' },
      { id: 's-tab-1', separador: true, label: '', icono: '' },
      { id: 'tab-rrhh',    label: 'RRHH · Sueldos y Personal', icono: '📈', ruta: '/dashboard/estadisticas' },
      { id: 'tab-wms',     label: 'Stock · Logística (WMS)',   icono: '📦', ruta: '/dashboard/tablero-wms' },
      { id: 'tab-alertas', label: 'Alertas',                   icono: '🔔', ruta: '/dashboard/alertas' },
    ]
  },

  // ══════════════════ SISTEMA ══════════════════
  {
    id: 'sistema', label: 'Sistema', icono: '🔧', adminOnly: true,
    hijos: [
      {
        id: 'seguridad', label: 'Seguridad', icono: '🔒',
        hijos: [
          { id: 'cambio-clave',    label: 'Cambio de Clave',            icono: '🔑', ruta: '/dashboard/cambio-clave' },
          { id: 'alta-accesos',    label: 'Administración de Usuarios', icono: '👤', ruta: '/dashboard/admin-permisos' },
          { id: 'roles',           label: 'Roles / Plantillas',         icono: '🛡️', ruta: '/dashboard/roles' },
          { id: 'clonar-permisos', label: 'Clonar Permisos',            icono: '👥', ruta: '/dashboard/clonar-permisos' },
          { id: 'usuarios-activos',label: 'Usuarios en el Sistema',     icono: '👥', ruta: '/dashboard/usuarios-activos' },
        ]
      },
      { id: 'log-errores',   label: 'Log de Errores SQL', icono: '🐞', ruta: '/dashboard/log-errores' },
      { id: 'log-actividad', label: 'Log de Actividad',   icono: '🕵️', ruta: '/dashboard/log-actividad' },
    ]
  },
]
