<!-- CentroAyuda.vue — Buscador global de módulos/procesos (Centro de Ayuda).
     Se arma solo desde el menú (menu.ts): cada módulo nuevo aparece automáticamente.
     Se abre con Ctrl+K o desde el botón ❓ de la topbar. Respeta permisos. -->
<template>
  <Teleport to="body">
    <div v-if="modelValue" class="ay-ov" @click.self="cerrar">
      <div class="ay-modal">
        <div class="ay-head">
          <span class="ay-ico-head">❓</span>
          <input
            ref="inp"
            v-model="q"
            class="ay-input"
            placeholder="Buscar módulo o proceso…"
            @keydown.down.prevent="mover(1)"
            @keydown.up.prevent="mover(-1)"
            @keydown.enter.prevent="abrir(resultados[sel])"
            @keydown.esc="cerrar"
          />
          <button class="ay-x" @click="cerrar">✕</button>
        </div>

        <div class="ay-body">
          <div v-if="!resultados.length" class="ay-vacio">Sin resultados para “{{ q }}”.</div>
          <button
            v-for="(r, i) in resultados"
            :key="r.id"
            class="ay-item"
            :class="{ sel: sel === i }"
            @mouseenter="sel = i"
            @click="abrir(r)"
          >
            <span class="ay-ico">{{ r.icono || '📄' }}</span>
            <span class="ay-tx">
              <span class="ay-label">{{ r.label }}<span v-if="r.bc" class="ay-bc"> · {{ r.bc }}</span></span>
              <span v-if="r.desc" class="ay-desc">{{ r.desc }}</span>
            </span>
            <span class="ay-ir">Ir →</span>
          </button>
        </div>

        <div class="ay-foot">
          <button class="ay-ia" @click="preguntarIA">🤖 Preguntar a la IA</button>
          <span>{{ resultados.length }} de {{ visibles.length }} módulos · ↑↓ · Enter · Esc</span>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { ref, computed, watch, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { menuConfig } from '@/config/menu'
import type { MenuItem } from '@/config/menu'
import { DESCRIPCIONES } from '@/config/ayudaDescripciones'

const props = defineProps<{ modelValue: boolean }>()
const emit = defineEmits<{
  (e: 'update:modelValue', v: boolean): void
  (e: 'abrir-ia', contexto: string): void
}>()

const router = useRouter()
const auth = useAuthStore()

interface Entrada { id: string; label: string; icono: string; ruta: string; bc: string; desc: string; adminOnly?: boolean }

// Aplana el menú en una lista de módulos-hoja con su "camino" (breadcrumb).
function aplanar (items: MenuItem[], prefijo: string): Entrada[] {
  const out: Entrada[] = []
  for (const it of items) {
    if (it.separador) continue
    if (it.hijos && it.hijos.length) {
      out.push(...aplanar(it.hijos, prefijo ? `${prefijo} › ${it.label}` : it.label))
    } else if (it.ruta) {
      out.push({ id: it.id, label: it.label, icono: it.icono, ruta: it.ruta, bc: prefijo, desc: DESCRIPCIONES[it.id] ?? '', adminOnly: it.adminOnly })
    }
  }
  return out
}
const todos = aplanar(menuConfig, '')

// Solo módulos accesibles para el usuario actual.
const visibles = computed(() => todos.filter(e =>
  (!e.adminOnly || auth.esAdmin) && auth.puedoVer(e.id)))

const norm = (s: string) => s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '')
const q = ref('')
const sel = ref(0)
const inp = ref<HTMLInputElement | null>(null)

const resultados = computed(() => {
  const t = norm(q.value.trim())
  if (!t) return visibles.value
  return visibles.value.filter(e =>
    norm(e.label).includes(t) || norm(e.bc).includes(t) || norm(e.desc).includes(t))
})

watch(() => props.modelValue, async (v) => {
  if (v) { q.value = ''; sel.value = 0; await nextTick(); inp.value?.focus() }
})
watch(resultados, () => { sel.value = 0 })

function mover (d: number) {
  if (!resultados.value.length) return
  sel.value = (sel.value + d + resultados.value.length) % resultados.value.length
}
function cerrar () { emit('update:modelValue', false) }
function abrir (e?: Entrada) {
  if (!e) return
  router.push(e.ruta)
  cerrar()
}

// Lista de módulos visibles como texto (contexto para el asistente IA).
const modulosTexto = computed(() => visibles.value
  .map(e => `- ${e.label}${e.bc ? ` (${e.bc})` : ''}`).join('\n'))
function preguntarIA () {
  emit('abrir-ia', modulosTexto.value)
  cerrar()
}
</script>

<style scoped>
.ay-ov { position: fixed; inset: 0; background: rgba(15,23,42,.5); z-index: 9800; display: flex; align-items: flex-start; justify-content: center; padding: 80px 18px; }
.ay-modal { width: min(620px, 96vw); max-height: 70vh; background: #fff; border-radius: 12px; overflow: hidden; display: flex; flex-direction: column; box-shadow: 0 20px 60px rgba(15,23,42,.35); }
.ay-head { display: flex; align-items: center; gap: 10px; padding: 12px 14px; border-bottom: 1px solid #eef2f7; }
.ay-ico-head { font-size: 1.1rem; }
.ay-input { flex: 1; border: none; outline: none; font-size: 1rem; color: #1e293b; }
.ay-x { background: #f1f5f9; border: none; border-radius: 6px; width: 30px; height: 30px; cursor: pointer; font-weight: 700; color: #475569; }

.ay-body { overflow-y: auto; flex: 1; }
.ay-vacio { padding: 26px; text-align: center; color: #94a3b8; }
.ay-item { display: flex; align-items: center; gap: 12px; width: 100%; text-align: left; background: none; border: none; border-bottom: 1px solid #f4f6f9; padding: 10px 14px; cursor: pointer; }
.ay-item.sel { background: #eef6ff; }
.ay-ico { font-size: 1.2rem; width: 24px; text-align: center; flex-shrink: 0; }
.ay-tx { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.ay-label { font-size: 0.9rem; color: #1e293b; }
.ay-bc { font-size: 0.74rem; color: #94a3b8; font-weight: 400; }
.ay-desc { font-size: 0.76rem; color: #64748b; }
.ay-ir { font-size: 0.8rem; color: #2b6cb0; font-weight: 600; opacity: 0; }
.ay-item.sel .ay-ir { opacity: 1; }

.ay-foot { display: flex; align-items: center; justify-content: space-between; padding: 8px 14px; border-top: 1px solid #eef2f7; font-size: 0.72rem; color: #94a3b8; }
.ay-ia { background: #eef6ff; border: 1px solid #cfe4ff; color: #1e40af; border-radius: 14px; padding: 4px 12px; cursor: pointer; font-weight: 600; font-size: 0.76rem; }
.ay-ia:hover { background: #dbeafe; }
</style>
