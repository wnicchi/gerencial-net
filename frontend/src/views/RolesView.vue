<!-- RolesView.vue — Sistema / Seguridad / Roles (Plantillas de Permisos). Reemplaza "Niveles". -->
<template>
  <div class="ro-view">
    <div class="ro-cab">
      <div class="ro-cab-ico">🛡️</div>
      <div class="ro-cab-tx"><h1>Roles / Plantillas de Permisos</h1><p>Definí roles con su set de opciones de menú y aplicalos a los usuarios</p></div>
      <button class="ro-nuevo" @click="nuevo">＋ Nuevo rol</button>
    </div>

    <div class="ro-layout">
      <div class="ro-list">
        <div class="ro-list-head">Roles del sistema</div>
        <div class="ro-list-body">
          <button v-for="r in roles" :key="r.cod" class="ro-item" :class="{ on: sel?.cod === r.cod }" @click="elegir(r.cod)">
            <span class="ro-nom">{{ r.descripcion }}</span>
            <span class="ro-badge">{{ r.permisos }} ítems</span>
          </button>
          <p v-if="!roles.length" class="ro-vacio">Sin roles cargados.</p>
        </div>
      </div>

      <div class="ro-detalle" v-if="sel || modoNuevo">
        <div class="ro-form-head">
          <input v-model="descripcion" class="ro-desc" placeholder="Nombre del rol (ej. RRHH, Liquidador, Consulta)…" @input="descripcion = descripcion.toUpperCase()" />
          <div class="ro-acc">
            <button class="ro-btn todos" @click="marcarTodos(true)">✓ Todo</button>
            <button class="ro-btn ninguno" @click="marcarTodos(false)">✗ Ninguno</button>
            <button class="ro-btn ok" :disabled="procesando" @click="guardar">💾 {{ sel ? 'Guardar' : 'Crear' }}</button>
            <button v-if="sel" class="ro-btn del" :disabled="procesando" @click="eliminar">🗑</button>
          </div>
        </div>
        <p class="ro-info">Marcá las opciones de menú que este rol podrá ver. Al asignar el rol a un usuario, estos permisos se precargan.</p>
        <p v-if="msg" :class="['ro-msg', msgError ? 'err' : 'ok']">{{ msg }}</p>

        <div class="ro-arbol">
          <template v-for="seccion in menuConfig" :key="seccion.id">
            <div class="ro-sec">
              <label class="ro-arb nivel-0">
                <input type="checkbox" :checked="grupoCheckeado(seccion)" :indeterminate.prop="grupoIndeterminado(seccion)" @change="toggleGrupo(seccion)" />
                <span class="ro-ico">{{ seccion.icono }}</span><span class="ro-lbl">{{ seccion.label }}</span>
              </label>
              <template v-if="seccion.hijos">
                <template v-for="h1 in seccion.hijos" :key="h1.id">
                  <div v-if="h1.separador" class="ro-sep"></div>
                  <template v-else-if="h1.hijos?.length">
                    <label class="ro-arb nivel-1"><input type="checkbox" :checked="grupoCheckeado(h1)" :indeterminate.prop="grupoIndeterminado(h1)" @change="toggleGrupo(h1)" /><span class="ro-ico">{{ h1.icono }}</span><span class="ro-lbl">{{ h1.label }}</span></label>
                    <template v-for="h2 in h1.hijos" :key="h2.id">
                      <div v-if="h2.separador" class="ro-sep n2"></div>
                      <template v-else-if="h2.hijos?.length">
                        <label class="ro-arb nivel-2"><input type="checkbox" :checked="grupoCheckeado(h2)" :indeterminate.prop="grupoIndeterminado(h2)" @change="toggleGrupo(h2)" /><span class="ro-ico">{{ h2.icono }}</span><span class="ro-lbl">{{ h2.label }}</span></label>
                        <label v-for="h3 in h2.hijos" :key="h3.id" class="ro-arb nivel-3" v-show="!h3.separador">
                          <template v-if="!h3.separador"><input type="checkbox" :checked="sset.has(h3.id)" @change="toggleItem(h3.id)" /><span class="ro-ico">{{ h3.icono }}</span><span class="ro-lbl">{{ h3.label }}</span></template>
                        </label>
                      </template>
                      <label v-else class="ro-arb nivel-2"><input type="checkbox" :checked="sset.has(h2.id)" @change="toggleItem(h2.id)" /><span class="ro-ico">{{ h2.icono }}</span><span class="ro-lbl">{{ h2.label }}</span></label>
                    </template>
                  </template>
                  <label v-else class="ro-arb nivel-1"><input type="checkbox" :checked="sset.has(h1.id)" @change="toggleItem(h1.id)" /><span class="ro-ico">{{ h1.icono }}</span><span class="ro-lbl">{{ h1.label }}</span></label>
                </template>
              </template>
            </div>
          </template>
        </div>
      </div>
      <div class="ro-detalle ro-vacia" v-else><div class="ro-sinsel">👈 Elegí un rol o creá uno nuevo</div></div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '@/services/auth'
import { menuConfig } from '@/config/menu'
import type { MenuItem } from '@/config/menu'

interface Rol { cod: number; descripcion: string; permisos: number }
const roles = ref<Rol[]>([])
const sel = ref<Rol | null>(null); const modoNuevo = ref(false)
const descripcion = ref(''); const sset = ref<Set<string>>(new Set())
const procesando = ref(false); const msg = ref(''); const msgError = ref(false)
const flash = (t: string, e = false) => { msg.value = t; msgError.value = e; if (t && !e) setTimeout(() => msg.value = '', 5000) }

onMounted(cargar)
async function cargar () { try { roles.value = (await api.get('/admin/roles')).data.roles ?? [] } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudieron cargar los roles.', true) } }

const elegir = async (cod: number) => {
  try { const { data } = await api.get(`/admin/roles/${cod}`); sel.value = roles.value.find(r => r.cod === cod) ?? null; modoNuevo.value = false; descripcion.value = data.descripcion; sset.value = new Set(data.permisos) }
  catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo cargar el rol.', true) }
}
const nuevo = () => { sel.value = null; modoNuevo.value = true; descripcion.value = ''; sset.value = new Set() }

const guardar = async () => {
  if (!descripcion.value.trim()) return flash('Indicá el nombre del rol.', true)
  procesando.value = true
  try {
    const payload = { descripcion: descripcion.value, permisos: [...sset.value] }
    if (sel.value) await api.put(`/admin/roles/${sel.value.cod}`, payload)
    else { const { data } = await api.post('/admin/roles', payload); await cargar(); await elegir(data.cod); flash('Rol creado.'); procesando.value = false; return }
    flash('Rol guardado.'); await cargar(); if (sel.value) await elegir(sel.value.cod)
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo guardar.', true) }
  finally { procesando.value = false }
}
const eliminar = async () => {
  if (!sel.value || !confirm(`¿Elimina el rol "${sel.value.descripcion}"?`)) return
  procesando.value = true
  try { await api.delete(`/admin/roles/${sel.value.cod}`); flash('Rol eliminado.'); sel.value = null; await cargar() }
  catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo eliminar.', true) }
  finally { procesando.value = false }
}

// ── Árbol (reutilizado de AdminPermisos) ──
function todosLosIds (items: MenuItem[]): string[] { const ids: string[] = []; for (const it of items) { if (it.separador) continue; if (it.hijos?.length) ids.push(...todosLosIds(it.hijos)); else if (it.id) ids.push(it.id) } return ids }
function toggleItem (id: string) { const s = new Set(sset.value); s.has(id) ? s.delete(id) : s.add(id); sset.value = s }
function grupoCheckeado (it: MenuItem): boolean { const ids = todosLosIds(it.hijos?.length ? it.hijos : [it]); return ids.length > 0 && ids.every(id => sset.value.has(id)) }
function grupoIndeterminado (it: MenuItem): boolean { const ids = todosLosIds(it.hijos?.length ? it.hijos : [it]); const n = ids.filter(id => sset.value.has(id)).length; return n > 0 && n < ids.length }
function toggleGrupo (it: MenuItem) { const ids = todosLosIds(it.hijos?.length ? it.hijos : [it]); const s = new Set(sset.value); ids.every(id => s.has(id)) ? ids.forEach(id => s.delete(id)) : ids.forEach(id => s.add(id)); sset.value = s }
function marcarTodos (m: boolean) { sset.value = m ? new Set(todosLosIds(menuConfig)) : new Set() }
</script>

<style scoped>
.ro-view { display:flex; flex-direction:column; height:100%; overflow:hidden; }
.ro-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; flex-shrink:0; }
.ro-cab-ico { font-size:28px; } .ro-cab-tx h1 { margin:0; font-size:19px; color:#1e293b; } .ro-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.ro-nuevo { margin-left:auto; background:#2d6a9f; color:#fff; border:none; padding:9px 16px; border-radius:8px; cursor:pointer; font-size:13px; font-weight:700; }
.ro-layout { display:flex; gap:14px; flex:1; min-height:0; padding:14px 18px; }
.ro-list { width:300px; background:#fff; border:1px solid #dde4ee; border-radius:10px; display:flex; flex-direction:column; overflow:hidden; }
.ro-list-head { padding:9px 12px; background:#f4f7fc; border-bottom:1px solid #dde4ee; font-size:13px; font-weight:700; color:#2a4a6a; }
.ro-list-body { overflow:auto; flex:1; }
.ro-item { display:flex; align-items:center; justify-content:space-between; gap:8px; width:100%; padding:10px 12px; border:none; background:transparent; border-bottom:1px solid #f0f4f9; cursor:pointer; text-align:left; }
.ro-item:hover { background:#f0f7ff; } .ro-item.on { background:#e0eefc; border-left:3px solid #2d6a9f; }
.ro-nom { font-size:13px; color:#1a3a5c; font-weight:600; } .ro-badge { font-size:11px; color:#5a7a9a; background:#eef2f7; border-radius:8px; padding:2px 7px; white-space:nowrap; }
.ro-vacio { color:#94a3b8; padding:14px; font-size:13px; }
.ro-detalle { flex:1; background:#fff; border:1px solid #dde4ee; border-radius:10px; display:flex; flex-direction:column; overflow:hidden; }
.ro-vacia { justify-content:center; align-items:center; } .ro-sinsel { color:#8aacca; }
.ro-form-head { display:flex; gap:10px; padding:12px 14px; border-bottom:1px solid #e8eef5; background:#f4f7fc; flex-wrap:wrap; align-items:center; }
.ro-desc { flex:1; min-width:240px; border:1px solid #c8d8ea; border-radius:7px; padding:8px 10px; font-size:14px; color:#1a3a5c; font-weight:600; }
.ro-acc { display:flex; gap:6px; }
.ro-btn { border:none; padding:8px 12px; border-radius:6px; cursor:pointer; font-size:12.5px; font-weight:700; } .ro-btn.ok { background:#2d6a9f; color:#fff; } .ro-btn.del { background:#fee2e2; color:#991b1b; } .ro-btn.todos { background:#d1fae5; color:#065f46; } .ro-btn.ninguno { background:#fee2e2; color:#991b1b; } .ro-btn:disabled { opacity:.5; cursor:default; }
.ro-info { margin:8px 14px 0; font-size:12px; color:#64748b; }
.ro-msg { margin:8px 14px 0; padding:8px 12px; border-radius:6px; font-size:13px; } .ro-msg.ok { background:#d1fae5; color:#065f46; } .ro-msg.err { background:#fee2e2; color:#991b1b; }
.ro-arbol { flex:1; overflow:auto; padding:8px 10px; }
.ro-sec { margin-bottom:2px; } .ro-sep { height:1px; background:#eef2f7; margin:3px 0; } .ro-sep.n2 { margin-left:2.5rem; }
.ro-arb { display:flex; align-items:center; gap:7px; padding:4px 8px; border-radius:5px; cursor:pointer; font-size:13px; color:#2a4a6a; user-select:none; }
.ro-arb:hover { background:#f0f7ff; } .ro-arb input { width:14px; height:14px; accent-color:#2d6a9f; cursor:pointer; flex-shrink:0; }
.nivel-0 { font-weight:700; color:#1a3a5c; background:#f4f7fc; border-radius:6px; }
.nivel-1 { padding-left:1.6rem; } .nivel-2 { padding-left:3rem; color:#3a5a7a; } .nivel-3 { padding-left:4.4rem; color:#4a6a8a; font-size:12.5px; }
.ro-ico { flex-shrink:0; min-width:1rem; text-align:center; } .ro-lbl { flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
</style>
