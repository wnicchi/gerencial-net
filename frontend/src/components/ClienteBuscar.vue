<!-- ClienteBuscar.vue — Lupa de búsqueda de cliente por nombre (clientes_buscar_x_nombre.scx).
     Consulta la base de gestión vía /clientes/buscar. Rojo = cliente bloqueado.
     Uso: <ClienteBuscar v-if="show" @select="onSel" @close="show=false" /> -->
<template>
  <Teleport to="body">
    <div class="cb-ov" @click.self="$emit('close')">
      <div class="cb-md">
        <div class="cb-head">
          <input ref="inp" v-model="texto" class="cb-input" placeholder="Ingrese parte del nombre del Cliente…"
                 @input="buscar" @keyup.enter="elegirPrimero" @keyup.esc="$emit('close')" />
          <button class="cb-x" @click="$emit('close')">✕</button>
        </div>
        <div class="cb-body">
          <div v-if="cargando" class="cb-info">⟳ Buscando…</div>
          <div v-else-if="texto.trim().length < 2" class="cb-info">Escriba al menos 2 letras.</div>
          <div v-else-if="!lista.length" class="cb-info">Sin coincidencias.</div>
          <table v-else class="cb-tabla">
            <thead><tr><th>Opciones</th><th style="width:90px;text-align:right">Código</th></tr></thead>
            <tbody>
              <tr v-for="(c, i) in lista" :key="i" :class="{ blo: c.bloqueado }"
                  @click="sel = i" @dblclick="elegir(c)" :data-sel="sel === i">
                <td>{{ c.nombre }}</td><td style="text-align:right" class="cb-cod">{{ c.cod }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="cb-foot"><span class="cb-ley"><span class="cb-chip blo"></span> Bloqueado (no usar)</span></div>
      </div>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { ref, onMounted, nextTick } from 'vue'
import api from '@/services/auth'

interface Cli { cod: number; nombre: string; bloqueado: boolean }
const emit = defineEmits<{ (e: 'select', c: Cli): void; (e: 'close'): void }>()

const texto = ref(''); const lista = ref<Cli[]>([]); const cargando = ref(false); const sel = ref(-1)
const inp = ref<HTMLInputElement | null>(null)
let deb: any = null

const buscar = () => {
  clearTimeout(deb)
  const q = texto.value.trim()
  if (q.length < 2) { lista.value = []; sel.value = -1; return }
  deb = setTimeout(async () => {
    cargando.value = true
    try { lista.value = (await api.get('/clientes/buscar', { params: { nombre: q } })).data ?? []; sel.value = lista.value.length ? 0 : -1 }
    catch { lista.value = [] }
    finally { cargando.value = false }
  }, 300)
}
const elegir = (c: Cli) => { emit('select', c) }
const elegirPrimero = () => { if (sel.value >= 0 && lista.value[sel.value]) elegir(lista.value[sel.value]) }

onMounted(async () => { await nextTick(); inp.value?.focus() })
</script>

<style scoped>
.cb-ov { position:fixed; inset:0; background:rgba(15,23,42,.5); z-index:9500; display:flex; align-items:flex-start; justify-content:center; padding:60px 18px; }
.cb-md { background:#fff; border-radius:12px; width:min(600px,96vw); max-height:80vh; display:flex; flex-direction:column; overflow:hidden; box-shadow:0 18px 50px rgba(0,0,0,.3); }
.cb-head { display:flex; gap:8px; align-items:center; padding:12px; background:#ff8040; }
.cb-input { flex:1; border:none; border-radius:6px; padding:10px 12px; font-size:16px; background:#ffff80; color:#1e293b; outline:none; }
.cb-x { background:rgba(255,255,255,.85); border:none; border-radius:6px; width:34px; height:34px; cursor:pointer; font-size:14px; font-weight:700; color:#7c2d12; }
.cb-body { overflow:auto; flex:1; }
.cb-info { padding:20px; text-align:center; color:#94a3b8; }
.cb-tabla { width:100%; border-collapse:collapse; font-size:14px; }
.cb-tabla th { position:sticky; top:0; background:#c1dcc1; color:#1e293b; padding:7px 12px; text-align:left; font-size:12px; }
.cb-tabla td { padding:6px 12px; color:#1e293b; cursor:pointer; }
.cb-tabla tbody tr:nth-child(even) { background:#eef6ee; }
.cb-tabla tbody tr:hover { background:#d7efd7; }
.cb-tabla tbody tr[data-sel="true"] { background:#bbf7d0; }
.cb-tabla tr.blo td { background:#ef4444 !important; color:#fff; font-weight:700; }
.cb-cod { font-weight:700; color:#1d4ed8; }
.cb-tabla tr.blo .cb-cod { color:#fff; }
.cb-foot { padding:8px 12px; border-top:1px solid #e2e8f0; font-size:12px; color:#64748b; }
.cb-ley { display:flex; align-items:center; gap:6px; } .cb-chip { width:14px; height:14px; border-radius:3px; display:inline-block; } .cb-chip.blo { background:#ef4444; }
</style>
