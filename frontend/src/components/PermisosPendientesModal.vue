<!--
  PermisosPendientesModal.vue — Modal para elegir un permiso laboral pendiente.
  Se usa en Faltas y Vacaciones: al seleccionar al empleado, si tiene permisos
  pendientes, este modal los lista y permite elegir cuál corresponde para cargarlo.
-->
<template>
  <Teleport to="body">
    <div class="pp-ov" @click.self="$emit('close')">
      <div class="pp-md">
        <div class="pp-head">
          <span>🪪 Permisos pendientes<template v-if="nombre"> — {{ nombre }}</template></span>
          <button @click="$emit('close')">✕</button>
        </div>
        <div class="pp-body">
          <p class="pp-sub">Elegí el permiso que corresponde. Se van a cargar la fecha, el tipo y la observación.</p>
          <table class="pp-grid">
            <thead>
              <tr>
                <th>Desde</th><th>Hasta</th><th>Horario</th><th>Tipo</th><th>Observación</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="p in permisos" :key="p.cod" class="pp-fila" @click="$emit('elegir', p)" title="Usar este permiso">
                <td>{{ fmt(p.fecha_desde) }}</td>
                <td>{{ fmt(p.fecha_hasta) }}</td>
                <td class="c">{{ p.hora_inicio ? `${p.hora_inicio} - ${p.hora_fin || ''}` : '—' }}</td>
                <td>{{ p.falta }}</td>
                <td class="pp-obs">{{ p.observaciones }}</td>
              </tr>
              <tr v-if="!permisos.length"><td colspan="5" class="pp-vacio">No hay permisos pendientes.</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
interface Permiso {
  cod: number; fecha_desde: string; fecha_hasta: string
  hora_inicio: string; hora_fin: string; falta: string; observaciones: string
}
defineProps<{ permisos: Permiso[]; nombre?: string }>()
defineEmits<{ (e: 'elegir', p: Permiso): void; (e: 'close'): void }>()

const fmt = (d: string) => d ? d.split('-').reverse().join('/') : ''
</script>

<style scoped>
.pp-ov { position: fixed; inset: 0; background: rgba(15,23,42,.55); z-index: 9700; display: flex; align-items: center; justify-content: center; padding: 24px; }
.pp-md { background: #fff; border-radius: 12px; width: min(720px, 96vw); max-height: 82vh; overflow: hidden; display: flex; flex-direction: column; }
.pp-head { display: flex; align-items: center; justify-content: space-between; padding: 12px 14px; background: #1b4332; color: #fff; font-weight: 700; font-size: 0.9rem; }
.pp-head button { background: rgba(255,255,255,.85); border: none; border-radius: 6px; width: 30px; height: 30px; cursor: pointer; font-weight: 700; }
.pp-body { padding: 14px; overflow: auto; }
.pp-sub { margin: 0 0 10px; font-size: 0.85rem; color: #475569; }
.pp-grid { width: 100%; border-collapse: collapse; font-size: 0.82rem; }
.pp-grid th { text-align: left; padding: 7px 8px; background: #f1f5f9; color: #475569; font-size: 0.76rem; white-space: nowrap; }
.pp-grid td { padding: 7px 8px; border-bottom: 1px solid #f1f5f9; color: #1e293b; }
.pp-grid td.c, .pp-grid th:nth-child(3) { text-align: center; }
.pp-fila { cursor: pointer; }
.pp-fila:hover td { background: #f0faf4; }
.pp-obs { color: #64748b; }
.pp-vacio { text-align: center; color: #94a3b8; padding: 1rem; }
</style>
