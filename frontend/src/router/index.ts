import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    // ── Rutas públicas ──
    { path: '/login',           name: 'login',           component: () => import('@/views/LoginView.vue'),          meta: { publica: true } },
    { path: '/primer-acceso',   name: 'primer-acceso',   component: () => import('@/views/PrimerAccesoView.vue'),   meta: { publica: true } },
    { path: '/forgot-password', name: 'forgot-password', component: () => import('@/views/ForgotPasswordView.vue'), meta: { publica: true } },
    { path: '/reset-password',  name: 'reset-password',  component: () => import('@/views/ResetPasswordView.vue'),  meta: { publica: true } },

    // ── Dashboard (layout con sidebar + rutas anidadas) ──
    {
      path: '/dashboard',
      name: 'dashboard',
      component: () => import('@/views/DashboardView.vue'),
      meta: { requiereAuth: true },
      children: [
        // Página de inicio (resumen gerencial)
        { path: '',              name: 'dashboard-home', component: () => import('@/views/DashboardHome.vue') },

        // ── Tablero Gerencial ──
        // RRHH · Sueldos y Personal (reusa la vista de Estadísticas de RRHH)
        { path: 'estadisticas',  name: 'estadisticas',   component: () => import('@/views/EstadisticasView.vue') },
        // Stock · Logística (WMS) — vista nueva multi-empresa
        { path: 'tablero-wms',   name: 'tablero-wms',    component: () => import('@/views/TableroWmsView.vue') },
        // Alertas (reusa la vista de Alertas de RRHH)
        { path: 'alertas',       name: 'alertas',        component: () => import('@/views/AlertasView.vue') },
        // Gestión (Logística) · Pendiente de Facturación (base sqlLOGIST)
        { path: 'pendiente-facturacion', name: 'pendiente-facturacion', component: () => import('@/views/PendienteFacturacionView.vue') },
        // Gestión (Logística) · Estadísticas (réplica de GESTION.NET, base sqlLOGIST)
        { path: 'gestion/comparativa-ventas',     name: 'gestion-comparativa-ventas',     component: () => import('@/views/ComparativaVentasView.vue') },
        { path: 'gestion/comparativa-gastos',     name: 'gestion-comparativa-gastos',     component: () => import('@/views/ComparativaGastosView.vue') },
        { path: 'gestion/cobros-pagos',           name: 'gestion-cobros-pagos',           component: () => import('@/views/CobrosPagosView.vue') },
        { path: 'gestion/comparativo-saldos',     name: 'gestion-comparativo-saldos',     component: () => import('@/views/ComparativoSaldosView.vue') },
        { path: 'gestion/comparativa-utilidades', name: 'gestion-comparativa-utilidades', component: () => import('@/views/ComparativaUtilidadesView.vue') },
        { path: 'gestion/proyecciones',           name: 'gestion-proyecciones',           component: () => import('@/views/ProyeccionesView.vue') },
        { path: 'gestion/proyeccion-grafica',     name: 'gestion-proyeccion-grafica',     component: () => import('@/views/ProyeccionGraficaView.vue') },
        { path: 'gestion/proyeccion-ingresos-egresos', name: 'gestion-proyeccion-ie',     component: () => import('@/views/ProyeccionIngresosEgresosView.vue') },
        { path: 'gestion/compras-mensual',        name: 'gestion-compras-mensual',        component: () => import('@/views/ComprasMensualView.vue') },
        { path: 'gestion/ventas-mensual',         name: 'gestion-ventas-mensual',         component: () => import('@/views/VentasMensualView.vue') },
        { path: 'gestion/ccosto-mensual',         name: 'gestion-ccosto-mensual',         component: () => import('@/views/CentroCostoMensualView.vue') },
        // Gestión (Logística) · Listados
        { path: 'gestion/informe-contable',       name: 'gestion-informe-contable',       component: () => import('@/views/InformeContableView.vue') },

        // ── Sistema / Seguridad (solo admin) ──
        { path: 'cambio-clave',    name: 'cambio-clave',    component: () => import('@/views/CambioClaveView.vue') },
        { path: 'admin-permisos',  name: 'admin-permisos',  component: () => import('@/views/AdminPermisosView.vue'),  meta: { soloAdmin: true } },
        { path: 'roles',           name: 'roles',           component: () => import('@/views/RolesView.vue'),          meta: { soloAdmin: true } },
        { path: 'clonar-permisos', name: 'clonar-permisos', component: () => import('@/views/ClonarPermisosView.vue'), meta: { soloAdmin: true } },
        { path: 'usuarios-activos',name: 'usuarios-activos',component: () => import('@/views/UsuariosActivosView.vue'), meta: { soloAdmin: true } },
        { path: 'log-errores',     name: 'log-errores',     component: () => import('@/views/LogErroresView.vue'),     meta: { soloAdmin: true } },
        { path: 'log-actividad',   name: 'log-actividad',   component: () => import('@/views/LogActividadView.vue'),   meta: { soloAdmin: true } },

        // Cualquier otra ruta → placeholder "en desarrollo"
        { path: ':modulo',       name: 'modulo',         component: () => import('@/views/ModuloView.vue') },
      ],
    },

    // ── Redirecciones ──
    { path: '/',                redirect: '/login' },
    { path: '/:pathMatch(.*)*', redirect: '/login' },
  ],
})

// ── Guard de navegación ──
router.beforeEach(async (to) => {
  const auth = useAuthStore()

  if (auth.token && !auth.usuario) {
    await auth.cargarUsuario()
  }

  const requiereAuth = to.matched.some(record => record.meta.requiereAuth)
  const esPublica    = to.matched.some(record => record.meta.publica)
  const soloAdmin    = to.matched.some(record => record.meta.soloAdmin)

  if (requiereAuth && !auth.estaAutenticado) return { name: 'login' }
  if (esPublica    && auth.estaAutenticado)  return { name: 'dashboard' }

  // Rutas de Sistema/Seguridad: solo administrador total (es_admin)
  if (soloAdmin && auth.usuario && !auth.esAdmin) return { name: 'dashboard-home' }
})

export default router
