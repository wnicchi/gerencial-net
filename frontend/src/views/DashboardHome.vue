<template>
  <div class="home">
    <div class="home-head">
      <h1>Tablero Gerencial</h1>
      <p>Bienvenido{{ nombre ? ', ' + nombre : '' }}. Un vistazo consolidado de RRHH y Logística para la toma de decisiones.</p>
    </div>

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

    <p class="home-foot">Fuentes en vivo: RRHH (sqlRRHHlog) · Stock/Logística (LOGIST_UNIVERSAL) · Gestión (sqlLOGIST). Solo lectura.</p>
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
</script>

<style scoped>
.home { padding: 2rem 2.2rem; max-width: 1100px; margin: 0 auto; }
.home-head h1 { margin: 0 0 0.35rem; color: #1b4332; font-size: 1.6rem; font-weight: 800; }
.home-head p { margin: 0 0 1.8rem; color: #475569; font-size: 0.98rem; }

.cards {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
  gap: 1.1rem;
}
.card {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  padding: 1.4rem 1.3rem;
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
.card-ico { font-size: 2rem; line-height: 1; }
.card-tit { color: #1e293b; font-size: 1.06rem; font-weight: 700; }
.card-desc { color: #64748b; font-size: 0.88rem; line-height: 1.4; }

.home-foot { margin-top: 1.8rem; color: #94a3b8; font-size: 0.8rem; }
</style>
