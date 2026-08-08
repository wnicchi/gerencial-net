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
      { id: 'tab-gestion-fact', label: 'Gestión · Pend. Facturación', icono: '💰', ruta: '/dashboard/pendiente-facturacion' },
      {
        id: 'tab-gestion-est', label: 'Gestión · Estadísticas', icono: '📊',
        hijos: [
          { id: 'gest-est-ventas',     label: 'Comparativa de Ventas',      icono: '📈', ruta: '/dashboard/gestion/comparativa-ventas' },
          { id: 'gest-est-gastos',     label: 'Comparativa de Gastos',      icono: '📉', ruta: '/dashboard/gestion/comparativa-gastos' },
          { id: 'gest-est-cobros',     label: 'Cobros y Pagos',             icono: '💵', ruta: '/dashboard/gestion/cobros-pagos' },
          { id: 'gest-est-saldos',     label: 'Comparativo Saldos',         icono: '⚖️', ruta: '/dashboard/gestion/comparativo-saldos' },
          { id: 'gest-est-utilidades', label: 'Comparativa de Utilidades',  icono: '🧮', ruta: '/dashboard/gestion/comparativa-utilidades' },
          { id: 'gest-est-compras',    label: 'Estadística Mensual de Compras', icono: '🛒', ruta: '/dashboard/gestion/compras-mensual' },
          { id: 'gest-est-ventas-m',   label: 'Estadística Mensual de Ventas',  icono: '💲', ruta: '/dashboard/gestion/ventas-mensual' },
          { id: 'gest-est-ccosto',     label: 'Estadística por Centro de Costo', icono: '🏭', ruta: '/dashboard/gestion/ccosto-mensual' },
          { id: 'gest-est-proy',       label: 'Proyecciones Financieras',   icono: '🔮', ruta: '/dashboard/gestion/proyecciones' },
          { id: 'gest-est-proy-graf',  label: 'Proyección Gráfica Mensual', icono: '📊', ruta: '/dashboard/gestion/proyeccion-grafica' },
          { id: 'gest-est-proy-ie',    label: 'Proyección Ingresos y Egresos', icono: '📈', ruta: '/dashboard/gestion/proyeccion-ingresos-egresos' },
        ]
      },
      {
        id: 'tab-gestion-lst', label: 'Gestión · Listados', icono: '📋',
        hijos: [
          { id: 'gest-lst-inf-contable', label: 'Informe Contable de Ventas', icono: '📒', ruta: '/dashboard/gestion/informe-contable' },
        ]
      },
      { id: 's-tab-2', separador: true, label: '', icono: '' },
      { id: 'tab-alertas', label: 'Alertas',                   icono: '🔔', ruta: '/dashboard/alertas' },
    ]
  },

  // ══════════════════ SISTEMA ══════════════════
  // Cambio de Clave lo ve CUALQUIER usuario (cambiar su propia contraseña).
  // Seguridad (administración de usuarios/roles) y los logs son SOLO admin: cada
  // hoja lleva adminOnly, porque el filtro de visibilidad solo lo aplica en las
  // hojas (un grupo se muestra si alguna hoja es visible).
  {
    id: 'sistema', label: 'Sistema', icono: '🔧',
    hijos: [
      { id: 'cambio-clave', label: 'Cambio de Clave', icono: '🔑', ruta: '/dashboard/cambio-clave' },
      { id: 's-sis-1', separador: true, label: '', icono: '' },
      {
        id: 'seguridad', label: 'Seguridad', icono: '🔒',
        hijos: [
          { id: 'alta-accesos',    label: 'Administración de Usuarios', icono: '👤', ruta: '/dashboard/admin-permisos',  adminOnly: true },
          { id: 'roles',           label: 'Roles / Plantillas',         icono: '🛡️', ruta: '/dashboard/roles',           adminOnly: true },
          { id: 'clonar-permisos', label: 'Clonar Permisos',            icono: '👥', ruta: '/dashboard/clonar-permisos', adminOnly: true },
          { id: 'usuarios-activos',label: 'Usuarios en el Sistema',     icono: '👥', ruta: '/dashboard/usuarios-activos', adminOnly: true },
        ]
      },
      { id: 'log-errores',   label: 'Log de Errores SQL', icono: '🐞', ruta: '/dashboard/log-errores',   adminOnly: true },
      { id: 'log-actividad', label: 'Log de Actividad',   icono: '🕵️', ruta: '/dashboard/log-actividad', adminOnly: true },
    ]
  },
]
