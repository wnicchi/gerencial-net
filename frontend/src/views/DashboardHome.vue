<template>
  <div class="home">
    <p class="home-head">Bienvenido{{ nombre ? ', ' + nombre : '' }}. Un vistazo consolidado de RRHH y Logística para la toma de decisiones.</p>

    <div class="cards">
      <router-link to="/dashboard/estadisticas" class="card card-rrhh">
        <span class="card-ico">📈</span>
        <span class="card-tit">RRHH · Sueldos y Personal</span>
        <span class="card-desc">Masa salarial, dotación (altas/bajas), horas extras, ausentismo y composición. Con desglose por empleado.</span>
      </router-link>

      <router-link to="/dashboard/tablero-wms" class="card card-wms">
        <span class="card-ico">📦</span>
        <span class="card-tit">Stock · Logística (WMS)</span>
        <span class="card-desc">Stock, movimientos y operación por <strong>empresa/cliente</strong> y totales <strong>globales</strong>.</span>
      </router-link>

      <router-link to="/dashboard/pendiente-facturacion" class="card card-gestion">
        <span class="card-ico">🧾</span>
        <span class="card-tit">Gestión · Pendiente de Facturación</span>
        <span class="card-desc">Montos por facturar (contratos, transportes y servicios) por cliente, con total y desglose.</span>
      </router-link>
    </div>

    <!-- Estadísticas de Gestión: informes secundarios, en tarjetas más chicas. -->
    <div class="sub">
      <h2 class="sub-tit"><span class="sub-dot"></span>Gestión · Estadísticas</h2>
      <div class="chips">
        <router-link
          v-for="e in estadisticas"
          :key="e.ruta"
          :to="e.ruta"
          class="chip"
        >
          <span class="chip-tile">{{ e.ico }}</span>
          <span class="chip-txt">
            <b>{{ e.tit }}</b>
            <small>{{ e.desc }}</small>
          </span>
        </router-link>
      </div>
    </div>

    <p class="home-foot">Fuentes en vivo: RRHH · Stock/Logística · Gestión. Solo lectura.</p>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const nombre = computed(() => {
  const n = auth.usuario?.NOMBRE || auth.usuario?.DATO1 || ''
  return n.split(' ')[0] || ''
})

/**
 * Estadísticas de Gestión (informes secundarios del Resumen).
 * Al incorporar un nuevo módulo de Gestión al tablero, agregar acá su chip
 * (ícono + título + una línea de qué trata) para que aparezca en el Resumen
 * con el mismo formato compacto.
 */
const estadisticas = [
  { ruta: '/dashboard/gestion/comparativa-ventas',     ico: '📈', tit: 'Comparativa de Ventas',     desc: 'Ventas por mes/año y variación interanual.' },
  { ruta: '/dashboard/gestion/comparativa-gastos',     ico: '📉', tit: 'Comparativa de Gastos',     desc: 'Gastos por rubro comparados mes a mes.' },
  { ruta: '/dashboard/gestion/cobros-pagos',           ico: '💵', tit: 'Cobros y Pagos',            desc: 'Cobranzas contra pagos del período.' },
  { ruta: '/dashboard/gestion/comparativo-saldos',     ico: '⚖️', tit: 'Comparativo de Saldos',     desc: 'Evolución de saldos en el tiempo.' },
  { ruta: '/dashboard/gestion/comparativa-utilidades', ico: '🧮', tit: 'Comparativa de Utilidades', desc: 'Utilidad (ventas − gastos) por período.' },
  { ruta: '/dashboard/gestion/compras-mensual',        ico: '🛒', tit: 'Estadística de Compras',    desc: 'Compras por rubro, ventana móvil 12 meses.' },
  { ruta: '/dashboard/gestion/ventas-mensual',         ico: '💲', tit: 'Estadística de Ventas',     desc: 'Ventas por rubro, ventana móvil 12 meses.' },
  { ruta: '/dashboard/gestion/ccosto-mensual',         ico: '🏭', tit: 'Centros de Costo',          desc: 'Gastos por centro de costo, mensual.' },
  { ruta: '/dashboard/gestion/proyecciones',           ico: '🔮', tit: 'Proyecciones Financieras',  desc: 'Proyección de ingresos y egresos.' },
  { ruta: '/dashboard/gestion/proyeccion-grafica',     ico: '📊', tit: 'Proyección Gráfica Mensual', desc: 'Total proyectado por día, próximas semanas.' },
  { ruta: '/dashboard/gestion/proyeccion-ingresos-egresos', ico: '📈', tit: 'Proyección Ingresos y Egresos', desc: 'Ingresos a cobrar vs egresos a pagar.' },
]
</script>

<style scoped>
.home { padding: 0.9rem 2.2rem 1.4rem; max-width: 1100px; margin: 0 auto; }
.home-head { margin: 0 0 0.9rem; color: #475569; font-size: 0.92rem; }

.cards {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
  gap: 0.8rem;
}
.card {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  padding: 0.95rem 1.1rem;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 14px;
  text-decoration: none;
  box-shadow: 0 1px 4px rgba(0,0,0,0.05);
  transition: transform 0.15s, box-shadow 0.15s, border-color 0.15s;
  border-top: 4px solid #94a3b8;
}
.card:hover { transform: translateY(-3px); box-shadow: 0 8px 22px rgba(0,0,0,0.10); }
.card-rrhh    { border-top-color: #2a78d6; }
.card-wms     { border-top-color: #1baf7a; }
.card-gestion { border-top-color: #eda100; }
.card-ico { font-size: 1.6rem; line-height: 1; }
.card-tit { color: #1e293b; font-size: 1.02rem; font-weight: 700; }
.card-desc { color: #64748b; font-size: 0.84rem; line-height: 1.35; }

/* ── Estadísticas de Gestión (secundarias, más chicas) ── */
.sub { margin-top: 1.1rem; }
.sub-tit {
  display: flex; align-items: center; gap: 0.5rem;
  margin: 0 0 0.6rem; color: #64748b;
  font-size: 0.85rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em;
}
.sub-dot { width: 9px; height: 9px; border-radius: 50%; background: #eda100; flex: 0 0 auto; }
.chips {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
  gap: 0.65rem;
}
.chip {
  display: flex; align-items: flex-start; gap: 0.65rem;
  padding: 0.7rem 0.85rem;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  text-decoration: none;
  transition: transform 0.12s, box-shadow 0.12s, border-color 0.12s;
}
.chip:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(0,0,0,0.08); border-color: #f0c56a; }
.chip-tile {
  flex: 0 0 auto;
  width: 30px; height: 30px;
  display: flex; align-items: center; justify-content: center;
  border-radius: 8px;
  background: #fbeecb;
  font-size: 1rem; line-height: 1;
}
.chip-txt { display: flex; flex-direction: column; min-width: 0; }
.chip-txt b { color: #1e293b; font-size: 0.86rem; font-weight: 700; line-height: 1.25; }
.chip-txt small { color: #64748b; font-size: 0.76rem; line-height: 1.3; margin-top: 2px; }

.home-foot { margin-top: 1.8rem; color: #94a3b8; font-size: 0.8rem; }
</style>
