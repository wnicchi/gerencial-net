<!--
  EmpleadoInput.vue — Campo de empleado con autocompletado + lupa.

  Reemplaza el patrón repetido de "input readonly + botón 🔍 + EmpleadoBuscar": ahora
  el operador puede ESCRIBIR el nombre o código y elegir de las sugerencias, o abrir
  la lupa para la búsqueda completa (filtros, sólo activos).

  Usa el mismo endpoint que la lupa (/empleados/buscar), así que emite la fila con las
  claves PER_* y es drop-in de los handlers ya existentes: @select="seleccionar".

  Uso:
    <EmpleadoInput :codigo="codigo" :nombre="nombre" @select="onSelEmp" @clear="limpiar" />
-->
<template>
  <div class="ei-wrap">
    <input
      v-model="texto" type="text" class="ei-input" :placeholder="placeholder" :disabled="disabled"
      @input="buscar" @focus="buscar" @blur="cerrarLuego" @keydown.esc="resultados = []"
    />
    <button v-if="texto && !disabled" class="ei-clear" type="button" title="Limpiar" @mousedown.prevent="limpiar">✕</button>
    <button class="ei-lupa" type="button" :disabled="disabled" @click="lupa = true">🔍 Buscar</button>

    <ul v-if="resultados.length" class="ei-result">
      <li v-for="r in resultados" :key="r.PER_COD" @mousedown.prevent="elegir(r)">
        <b>{{ r.PER_COD }} — {{ (r.PER_NOM || '').trim() }}</b>
        <span>{{ [r.PER_LEG ? 'Leg. ' + r.PER_LEG : '', r.PER_NDO ? 'Doc. ' + r.PER_NDO : '', (r.PER_SED || '').trim()].filter(Boolean).join('  ·  ') }}</span>
      </li>
    </ul>

    <EmpleadoBuscar v-if="lupa" @select="onLupa" @close="lupa = false" />
  </div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import api from '@/services/auth'
import EmpleadoBuscar from '@/components/EmpleadoBuscar.vue'

const props = withDefaults(defineProps<{
  codigo?: number | string | null
  nombre?: string
  placeholder?: string
  soloActivos?: boolean
  disabled?: boolean
}>(), {
  codigo: 0, nombre: '', placeholder: 'Escribí el nombre o código, o usá la lupa…',
  soloActivos: true, disabled: false,
})

const emit = defineEmits<{ (e: 'select', empleado: any): void; (e: 'clear'): void }>()

const texto = ref('')
const resultados = ref<any[]>([])
const lupa = ref(false)
let deb: any = null

/** Texto que representa al empleado elegido (lo define el componente padre). */
const rotulo = () => {
  const cod = props.codigo
  if (!cod) return ''
  const nom = (props.nombre || '').trim()
  return nom ? `${cod} — ${nom}` : String(cod)
}

// Refleja en el campo lo que el padre tenga seleccionado (incluye los reset del padre).
watch(() => [props.codigo, props.nombre], () => { texto.value = rotulo() }, { immediate: true })

const buscar = () => {
  clearTimeout(deb)
  const q = texto.value.trim()
  // Si el texto es el rótulo del empleado ya elegido, no hay nada que sugerir.
  if (q.length < 2 || q === rotulo()) { resultados.value = []; return }
  deb = setTimeout(async () => {
    try {
      const { data } = await api.get('/empleados/buscar', { params: { q, activo: props.soloActivos ? 1 : 0 } })
      resultados.value = (data ?? []).slice(0, 10)
    } catch { resultados.value = [] }
  }, 250)
}

const elegir = (r: any) => { resultados.value = []; emit('select', r) }
const onLupa = (r: any) => { lupa.value = false; resultados.value = []; emit('select', r) }
const limpiar = () => { texto.value = ''; resultados.value = []; emit('clear') }
// El blur se demora para que el clic en una sugerencia llegue a procesarse.
const cerrarLuego = () => setTimeout(() => { resultados.value = []; texto.value = rotulo() }, 150)
</script>

<style scoped>
.ei-wrap { display:flex; gap:8px; position:relative; flex:1; }
.ei-input { flex:1; border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:14px; color:#1e293b; min-width:0; }
.ei-input:disabled { background:#f1f5f9; }
.ei-clear { background:#e2e8f0; border:none; border-radius:50%; width:26px; height:26px; align-self:center; cursor:pointer; font-size:12px; color:#475569; flex:0 0 auto; }
.ei-lupa { background:#394959; color:#fff; border:none; padding:9px 14px; border-radius:7px; cursor:pointer; font-weight:700; font-size:13px; white-space:nowrap; flex:0 0 auto; }
.ei-lupa:disabled { background:#cbd5e1; }
.ei-result { position:absolute; z-index:60; top:100%; left:0; right:0; margin:2px 0 0; padding:0; list-style:none; background:#fff; border:1px solid #d1d5db; border-radius:8px; box-shadow:0 12px 28px rgba(0,0,0,.18); max-height:260px; overflow:auto; }
.ei-result li { padding:7px 12px; cursor:pointer; border-bottom:1px solid #f1f5f9; display:flex; flex-direction:column; gap:2px; }
.ei-result li:hover { background:#f0faf4; }
.ei-result li b { font-size:13px; color:#1e293b; font-weight:600; }
.ei-result li span { font-size:11px; color:#6b7280; }
</style>
