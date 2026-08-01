<template>
  <div class="admin-usuarios">

    <!-- ══════════════ CABECERA ══════════════ -->
    <div class="cab">
      <div class="cab-icono">👥</div>
      <div class="cab-textos">
        <h1 class="cab-titulo">Administración de Usuarios</h1>
        <p class="cab-sub">Creá, editá y configurá los permisos de acceso al menú</p>
      </div>
      <button class="btn-pdf" title="Generar PDF con la lista de usuarios" @click="generarPdfUsuarios">
        <span>📄</span> Lista PDF
      </button>
      <button class="btn-nuevo" @click="abrirFormNuevo">
        <span>＋</span> Nuevo usuario
      </button>
    </div>

    <!-- ══════════════ LAYOUT TRES ZONAS ══════════════ -->
    <div class="layout">

      <!-- ────── ZONA 1: Lista de usuarios ────── -->
      <div class="col-lista">
        <div class="col-header">
          <span>Usuarios del sistema</span>
          <div class="filtros">
            <input v-model="filtroNombre" class="buscador" placeholder="🔍 Buscar..." />
            <button
              class="btn-filtro"
              :class="{ activo: filtroEstado === 'todos' }"
              @click="filtroEstado = 'todos'"
            >Todos</button>
            <button
              class="btn-filtro"
              :class="{ activo: filtroEstado === 'online' }"
              title="Usuarios actualmente logueados en el sistema"
              @click="filtroEstado = 'online'"
            >Online</button>
          </div>
        </div>

        <div v-if="cargandoUsuarios" class="estado-msg"><span class="spin">⟳</span> Cargando...</div>

        <div v-else class="lista-usuarios">
          <button
            v-for="u in usuariosFiltrados"
            :key="u.CODIGO"
            class="item-usuario"
            :class="{ seleccionado: usuarioActual?.CODIGO === u.CODIGO }"
            @click="seleccionarUsuario(u)"
          >
            <div class="u-avatar" :class="{ 'u-av-admin': u.es_admin }">
              {{ iniciales(u.NOMBRE) }}
            </div>
            <div class="u-info">
              <div class="u-nombre">{{ u.NOMBRE }}</div>
              <div class="u-meta">
                <span class="u-login">{{ u.DATO1 }}</span>
                <span class="u-estado" :class="u.ESTADO ? 'est-activo' : 'est-inactivo'"
                      :title="u.ESTADO ? 'Online (sesión iniciada)' : 'Offline'">
                  {{ u.ESTADO ? '● Online' : '○ Offline' }}
                </span>
              </div>
            </div>
            <div class="u-badge" :class="badgeClass(u)">{{ badgeLabel(u) }}</div>
          </button>
        </div>
      </div>

      <!-- ────── ZONA 2 + 3: Detalle ────── -->
      <div class="col-detalle" v-if="usuarioActual || modoNuevo">

        <!-- TABS -->
        <div class="tabs">
          <button class="tab" :class="{ activa: tabActiva === 'datos' }" @click="tabActiva = 'datos'">
            📋 Datos del usuario
          </button>
          <button
            class="tab"
            :class="{ activa: tabActiva === 'permisos' }"
            @click="tabActiva = 'permisos'"
            :disabled="modoNuevo"
            :title="modoNuevo ? 'Guardá el usuario primero' : ''"
          >
            🛡️ Permisos de menú
          </button>
          <button
            class="tab"
            :class="{ activa: tabActiva === 'subsectores' }"
            @click="tabActiva = 'subsectores'"
            :disabled="modoNuevo"
            :title="modoNuevo ? 'Guardá el usuario primero' : ''"
          >
            🏢 Sub-Sectores
          </button>
        </div>

        <!-- ── TAB DATOS ── -->
        <div v-show="tabActiva === 'datos'" class="tab-contenido">
          <form @submit.prevent="guardarUsuario" class="form-usuario">

            <div class="form-titulo">
              <span>{{ modoNuevo ? '➕ Nuevo usuario' : '📋 Datos del usuario' }}</span>
              <div v-if="!modoNuevo" class="acciones-estado">
                <button
                  v-if="!editando"
                  type="button"
                  class="btn-accion btn-editar"
                  @click="editando = true"
                  :title="'Habilitar la edición de los datos'"
                >
                  ✏️ Editar usuario
                </button>
                <button
                  type="button"
                  class="btn-accion"
                  :class="usuarioActual!.ESTADO ? 'btn-desactivar' : 'btn-activar'"
                  @click="toggleEstado"
                  :disabled="guardandoEstado"
                >
                  {{ usuarioActual!.ESTADO ? '🚫 Desactivar' : '✅ Activar' }}
                </button>
                <button
                  type="button"
                  class="btn-accion btn-clave"
                  @click="abrirModalClave"
                  :title="'Asignarle una clave nueva al usuario (las claves están cifradas y no se pueden ver; acá le ponés una para comunicarle)'"
                >
                  🔑 Restablecer clave
                </button>
                <button
                  v-if="esAdmin"
                  type="button"
                  class="btn-accion btn-verclave"
                  @click="verClaveUsuario"
                  :title="'Ver la clave actual del usuario (solo si la cambió/restableció desde que se activó esta función)'"
                >
                  👁️ Ver clave de acceso
                </button>
                <button
                  type="button"
                  class="btn-accion btn-reset"
                  @click="confirmarReset"
                  :title="'Forzar que el usuario deba reactivar su cuenta'"
                >
                  🔑 Resetear acceso
                </button>
              </div>
            </div>

            <!-- Campos del formulario (bloqueados hasta apretar "Editar usuario") -->
            <fieldset class="fs-campos" :disabled="!puedeEditar">
            <div class="form-grid">
              <div class="campo">
                <label>Nombre completo <span class="req">*</span></label>
                <input v-model="form.NOMBRE" type="text" placeholder="JUAN PEREZ" required maxlength="50" />
                <span class="hint">Se guardará en mayúsculas</span>
              </div>

              <div class="campo">
                <label>Login (usuario) <span class="req">*</span></label>
                <input
                  v-model="form.DATO1"
                  type="text"
                  placeholder="JPEREZ"
                  required
                  maxlength="20"
                  :disabled="!modoNuevo && !editandoLogin"
                  style="text-transform: uppercase"
                />
                <div v-if="!modoNuevo" class="campo-accion">
                  <button type="button" class="link-btn" @click="editandoLogin = !editandoLogin">
                    {{ editandoLogin ? 'Cancelar cambio' : 'Cambiar login' }}
                  </button>
                </div>
              </div>

              <div class="campo">
                <label>Email <span class="req">*</span></label>
                <input v-model="form.email" type="email" placeholder="usuario@empresa.com" required maxlength="255" />
              </div>

              <div class="campo">
                <label>Nivel de acceso (rol) <span class="req">*</span></label>
                <select v-model="form.NIVEL" required>
                  <option value="" disabled>— Seleccioná —</option>
                  <option v-for="n in niveles" :key="n.CODNIV" :value="n.CODNIV">
                    {{ n.DESCRIBE }}
                  </option>
                </select>
                <span class="hint">El nivel define el rol; no otorga privilegios de administrador.</span>
              </div>

              <div class="campo">
                <label>Administrador total</label>
                <label class="chk-admin">
                  <input type="checkbox" v-model="form.ES_ADMIN" />
                  <span>★ Acceso total al sistema (ignora la lista de permisos)</span>
                </label>
                <span v-if="form.ES_ADMIN" class="hint hint-warn">⚠️ Verá todo el menú sin restricciones.</span>
              </div>

              <div class="campo">
                <label>Empresa</label>
                <input v-model="form.EMPRESA" type="text" placeholder="SILCAR EQUIPOS INDUSTRIALES" maxlength="100" />
              </div>

              <div class="campo">
                <label>Domicilio</label>
                <input v-model="form.DOMICILIO" type="text" maxlength="100" />
              </div>

              <div class="campo">
                <label>Teléfono</label>
                <input v-model="form.TELEFONO" type="text" maxlength="50" />
              </div>

              <div class="campo">
                <label>DNI</label>
                <input v-model="form.DNI" type="text" maxlength="20" />
              </div>

              <div class="campo">
                <label>Notas</label>
                <input v-model="form.NOTAS" type="text" maxlength="100" />
              </div>
            </div>

            <!-- Renovación de clave -->
            <div class="form-renovar">
              <label class="chk-renovar">
                <input type="checkbox" v-model="form.RENOVAR" />
                <span>Forzar a que renueve la clave de acceso</span>
              </label>
              <div class="renovar-detalle">
                <div class="campo-inline">
                  <label>Cada cuántas veces que accede</label>
                  <input v-model.number="form.CADACUANTO" type="number" min="0" class="inp-num" />
                </div>
                <div v-if="!modoNuevo" class="campo-inline">
                  <label>Contador actual de veces accedida</label>
                  <input :value="form.CONTADOR" type="number" class="inp-num" disabled />
                </div>
              </div>
            </div>
            </fieldset>

            <!-- Info nuevo usuario -->
            <div v-if="modoNuevo" class="info-nuevo">
              <span>ℹ️</span>
              <div>
                <strong>¿Cómo accede por primera vez?</strong>
                <p>El usuario se crea sin contraseña. Al ingresar al sistema con su login, se le pedirá que registre su email y establezca una contraseña. No es necesario enviarle una contraseña inicial.</p>
              </div>
            </div>

            <!-- Error del servidor -->
            <div v-if="errorForm" class="error-form">{{ errorForm }}</div>
            <transition name="msg">
              <div v-if="okForm" class="ok-form">✓ {{ okForm }}</div>
            </transition>

            <!-- Botones -->
            <div class="form-footer">
              <button type="button" class="btn-cancelar" @click="cancelarForm">Cancelar</button>
              <button type="submit" class="btn-guardar" :disabled="!puedeEditar || guardandoForm">
                {{ guardandoForm ? '⟳ Guardando...' : (modoNuevo ? '✓ Crear usuario' : '✓ Guardar cambios') }}
              </button>
            </div>
          </form>
        </div>

        <!-- ── TAB PERMISOS ── -->
        <div v-show="tabActiva === 'permisos' && !modoNuevo" class="tab-contenido tab-permisos">

          <!-- Usuario admin -->
          <div v-if="usuarioActual?.es_admin" class="es-admin-msg">
            <div class="sin-icono">👑</div>
            <p><strong>{{ usuarioActual?.NOMBRE }}</strong> es Administrador total.</p>
            <p class="sub">Los administradores tienen acceso completo al sistema y no requieren configuración de permisos.</p>
          </div>

          <template v-else>
            <!-- Barra de acciones -->
            <div class="permisos-barra">
              <div class="pb-info">
                <span v-if="!haySeleccionados" class="tag-libre">✅ Sin restricciones — ve todo el menú</span>
                <span v-else class="tag-restr">🔒 {{ permisosSeleccionados.size }} ítems habilitados</span>
              </div>
              <div class="pb-acciones">
                <select class="sel-rol" v-model="rolElegido" @change="cargarPlantillaRol">
                  <option value="">📋 Cargar plantilla de rol…</option>
                  <option v-for="r in roles" :key="r.cod" :value="r.cod">{{ r.descripcion }} ({{ r.permisos }})</option>
                </select>
                <button class="btn-todos"   @click="marcarTodos(true)">✓ Todo</button>
                <button class="btn-ninguno" @click="marcarTodos(false)">✗ Ninguno</button>
                <button class="btn-guardar-p" :disabled="guardandoPermisos" @click="guardarPermisos">
                  {{ guardandoPermisos ? '⟳ Guardando...' : '💾 Guardar permisos' }}
                </button>
              </div>
            </div>

            <!-- Mensaje de resultado -->
            <transition name="msg">
              <div v-if="mensajePermisos" class="mensaje-permisos" :class="mensajePermisos.tipo">
                {{ mensajePermisos.texto }}
              </div>
            </transition>

            <!-- Árbol de checkboxes -->
            <div v-if="cargandoPermisos" class="estado-msg"><span class="spin">⟳</span> Cargando permisos...</div>
            <div v-else class="arbol">
              <template v-for="seccion in menuConfig" :key="seccion.id">
                <div class="arbol-seccion">
                  <label class="arbol-item nivel-0">
                    <input type="checkbox"
                      :checked="grupoCheckeado(seccion)"
                      :indeterminate.prop="grupoIndeterminado(seccion)"
                      @change="toggleGrupo(seccion)"
                    />
                    <span class="a-ico">{{ seccion.icono }}</span>
                    <span class="a-lbl">{{ seccion.label }}</span>
                  </label>
                  <template v-if="seccion.hijos">
                    <template v-for="h1 in seccion.hijos" :key="h1.id">
                      <div v-if="h1.separador" class="arbol-sep"></div>
                      <template v-else-if="h1.hijos?.length">
                        <label class="arbol-item nivel-1">
                          <input type="checkbox"
                            :checked="grupoCheckeado(h1)"
                            :indeterminate.prop="grupoIndeterminado(h1)"
                            @change="toggleGrupo(h1)"
                          />
                          <span class="a-ico">{{ h1.icono }}</span>
                          <span class="a-lbl">{{ h1.label }}</span>
                        </label>
                        <template v-for="h2 in h1.hijos" :key="h2.id">
                          <div v-if="h2.separador" class="arbol-sep n2"></div>
                          <template v-else-if="h2.hijos?.length">
                            <label class="arbol-item nivel-2">
                              <input type="checkbox"
                                :checked="grupoCheckeado(h2)"
                                :indeterminate.prop="grupoIndeterminado(h2)"
                                @change="toggleGrupo(h2)"
                              />
                              <span class="a-ico">{{ h2.icono }}</span>
                              <span class="a-lbl">{{ h2.label }}</span>
                            </label>
                            <label v-for="h3 in h2.hijos" :key="h3.id"
                              class="arbol-item nivel-3" v-show="!h3.separador">
                              <template v-if="!h3.separador">
                                <input type="checkbox"
                                  :checked="permisosSeleccionados.has(h3.id)"
                                  @change="toggleItem(h3.id)"
                                />
                                <span class="a-ico">{{ h3.icono }}</span>
                                <span class="a-lbl">{{ h3.label }}</span>
                              </template>
                            </label>
                          </template>
                          <label v-else class="arbol-item nivel-2">
                            <input type="checkbox"
                              :checked="permisosSeleccionados.has(h2.id)"
                              @change="toggleItem(h2.id)"
                            />
                            <span class="a-ico">{{ h2.icono }}</span>
                            <span class="a-lbl">{{ h2.label }}</span>
                          </label>
                        </template>
                      </template>
                      <label v-else class="arbol-item nivel-1">
                        <input type="checkbox"
                          :checked="permisosSeleccionados.has(h1.id)"
                          @change="toggleItem(h1.id)"
                        />
                        <span class="a-ico">{{ h1.icono }}</span>
                        <span class="a-lbl">{{ h1.label }}</span>
                      </label>
                    </template>
                  </template>
                </div>
              </template>
            </div>
          </template>
        </div>

        <!-- ── TAB SUB-SECTORES ── -->
        <div v-show="tabActiva === 'subsectores' && !modoNuevo" class="tab-contenido tab-subsectores">
          <div class="ss-titulo">{{ usuarioActual?.NOMBRE }}</div>
          <div class="ss-sub">Sub-sectores asociados</div>

          <transition name="msg">
            <div v-if="mensajeSub" class="mensaje-permisos" :class="mensajeSub.tipo">{{ mensajeSub.texto }}</div>
          </transition>

          <div class="ss-tabla">
            <div class="ss-head"><span>Elegir</span><span>Descripción del Sub-Sector</span><span>S.S</span></div>
            <div v-if="cargandoSub" class="ss-vacio"><span class="spin">⟳</span> Cargando…</div>
            <template v-else>
              <label v-for="s in subAsociados" :key="s.sub_cod" class="ss-fila">
                <input type="checkbox" v-model="subElegidos" :value="s.sub_cod" />
                <span class="ss-des">{{ s.sub_des }}</span>
                <span class="ss-cod">{{ s.sub_cod }}</span>
              </label>
              <div v-if="!subAsociados.length" class="ss-vacio">Sin sub-sectores asociados.</div>
            </template>
          </div>

          <div class="ss-acciones">
            <button class="btn-ss-eliminar" :disabled="!subElegidos.length || procesandoSub" @click="eliminarSubsectores">🗑 Eliminar</button>
            <select v-model="subAgregar" class="sel-ss">
              <option value="">— Seleccioná un sub-sector —</option>
              <option v-for="s in subDisponibles" :key="s.sub_cod" :value="s.sub_cod">{{ s.sub_des }}</option>
            </select>
            <button class="btn-ss-agregar" :disabled="!subAgregar || procesandoSub" @click="agregarSubsector">＋ Agregar</button>
          </div>

          <div class="ss-aviso">⚠️ ATENCIÓN: si no asocia ningún sub-sector a este usuario, el mismo verá <strong>TODOS</strong> los empleados activos de la empresa.</div>
        </div>

      </div>

      <!-- Sin usuario seleccionado -->
      <div class="col-detalle col-vacia" v-else>
        <div class="sin-sel">
          <div class="sin-ico">👈</div>
          <p>Seleccioná un usuario para ver sus datos<br>o creá uno nuevo</p>
        </div>
      </div>

    </div>

    <!-- ══════════════ MODAL CONFIRMACIÓN RESET ══════════════ -->
    <div v-if="modalReset" class="modal-overlay" @click.self="modalReset = false">
      <div class="modal">
        <div class="modal-ico">🔑</div>
        <h3>Resetear acceso</h3>
        <p>Esto le quitará la contraseña a <strong>{{ usuarioActual?.NOMBRE }}</strong> y cerrará todas sus sesiones activas.</p>
        <p class="modal-sub">Al intentar ingresar nuevamente, deberá pasar por el proceso de activación de cuenta.</p>
        <div class="modal-btns">
          <button class="btn-cancelar" @click="modalReset = false">Cancelar</button>
          <button class="btn-confirmar" @click="ejecutarReset" :disabled="guardandoReset">
            {{ guardandoReset ? '⟳ Procesando...' : 'Sí, resetear' }}
          </button>
        </div>
      </div>
    </div>

    <!-- ══════════════ MODAL ESTABLECER CONTRASEÑA ══════════════ -->
    <div v-if="modalClave" class="modal-overlay" @click.self="cerrarModalClave">
      <div class="modal">
        <div class="modal-ico">🔑</div>
        <h3>Restablecer clave</h3>
        <p>Asignale una clave nueva al usuario: se la comunicás y ya puede iniciar sesión (queda activa, sin depender del email). Después él la puede cambiar desde <em>Cambio de Clave</em>. Desde acá la clave también queda visible en <strong>Ver clave de acceso</strong>.</p>
        <p class="modal-user">Usuario: <strong>{{ usuarioActual?.NOMBRE }}</strong></p>
        <div class="clave-campo">
          <input :type="verClave ? 'text' : 'password'" v-model="nuevaClave" placeholder="Contraseña (mín. 6)" @keyup.enter="guardarClave" />
          <button type="button" class="link-btn" @click="verClave = !verClave">{{ verClave ? 'Ocultar' : 'Ver' }}</button>
        </div>
        <button type="button" class="btn-generar" @click="generarClave">🎲 Generar una automática</button>
        <div v-if="errorClave" class="error-form">{{ errorClave }}</div>
        <div class="modal-btns">
          <button class="btn-cancelar" @click="cerrarModalClave">Cancelar</button>
          <button class="btn-guardar" @click="guardarClave" :disabled="guardandoClave || nuevaClave.length < 6">
            {{ guardandoClave ? '⟳ Guardando...' : 'Guardar contraseña' }}
          </button>
        </div>
      </div>
    </div>

    <!-- ══════════════ MODAL VER CLAVE ══════════════ -->
    <div v-if="modalVerClave" class="modal-overlay" @click.self="modalVerClave = false">
      <div class="modal">
        <div class="modal-ico">👁️</div>
        <h3>Clave de acceso</h3>
        <p class="modal-user">Usuario: <strong>{{ usuarioActual?.NOMBRE }}</strong></p>

        <template v-if="claveVista !== null">
          <div class="clave-campo">
            <input :type="verClaveVista ? 'text' : 'password'" :value="claveVista" readonly />
            <button type="button" class="link-btn" @click="verClaveVista = !verClaveVista">{{ verClaveVista ? 'Ocultar' : 'Ver' }}</button>
          </div>
          <button type="button" class="btn-generar" @click="copiarClaveVista">📋 Copiar</button>
          <p class="cv-nota">Esta es la clave actual del usuario. Manejala con cuidado.</p>
        </template>

        <template v-else>
          <p>No hay clave visible para este usuario. Es una clave anterior a esta función (guardada solo cifrada, irreversible), o el usuario todavía no la cambió.</p>
          <p class="cv-nota">Para tener una clave visible, usá <strong>🔑 Restablecer clave</strong>: le asignás una nueva y desde ahí queda visible.</p>
        </template>

        <div class="modal-btns">
          <button class="btn-cancelar" @click="modalVerClave = false">Cerrar</button>
        </div>
      </div>
    </div>

    <!-- ══════════════ MODAL PDF (lista de usuarios) ══════════════ -->
    <Teleport to="body">
      <div v-if="pdfUrl" class="pdf-ov" @click.self="cerrarPdf">
        <div class="pdf-md">
          <div class="pdf-head"><span>{{ pdfNombre }}</span>
            <div class="pdf-acc">
              <button class="pdf-b ok" @click="guardarDesdeUrl(pdfUrl, pdfNombre)">⬇ Descargar</button>
              <button class="pdf-b ok" @click="($refs.pdfFrame as HTMLIFrameElement)?.contentWindow?.print()">🖨 Imprimir</button>
              <button class="pdf-b cancel" @click="cerrarPdf">✕ Cerrar</button>
            </div>
          </div>
          <iframe ref="pdfFrame" :src="pdfUrl" class="pdf-frame"></iframe>
        </div>
      </div>
    </Teleport>

  </div>
</template>

<script setup lang="ts">
/**
 * AdminPermisosView.vue — Administración de Usuarios
 *
 * Vista unificada para gestionar usuarios del sistema:
 *   - Crear y editar usuarios (nombre, login, email, nivel)
 *   - Activar/desactivar usuarios
 *   - Resetear acceso (fuerza reactivación de cuenta)
 *   - Configurar permisos de menú por usuario (checkboxes)
 *
 * Solo accesible para usuarios con NIVEL = 1 (Administrador total).
 * Ruteada en /dashboard/admin-permisos con guard soloAdmin.
 *
 * @view AdminPermisosView (Administración de Usuarios)
 */
import { ref, computed } from 'vue'
import api from '@/services/auth'
import { useAuthStore } from '@/stores/auth'
import jsPDF from 'jspdf'
import { menuConfig } from '@/config/menu'
import type { MenuItem } from '@/config/menu'
import { guardarDesdeUrl } from '@/utils/descargas'

// ── Tipos ──────────────────────────────────────────────────────────────────

interface UsuarioAdmin {
  CODIGO: number
  DATO1: string
  NOMBRE: string
  NIVEL: number
  EMPRESA?: string
  email?: string
  ESTADO: number
  primer_acceso?: number
  es_admin: boolean
  tiene_restricciones: boolean
  cantidad_permisos: number
}

interface NivelAcceso { CODNIV: number; DESCRIBE: string }
interface Mensaje     { texto: string; tipo: 'ok' | 'error' }

// ── Estado global ──────────────────────────────────────────────────────────
const usuarios       = ref<UsuarioAdmin[]>([])
const niveles        = ref<NivelAcceso[]>([])
const usuarioActual  = ref<UsuarioAdmin | null>(null)
const modoNuevo      = ref(false)
const tabActiva      = ref<'datos' | 'permisos' | 'subsectores'>('datos')

const filtroNombre   = ref('')
const filtroEstado   = ref<'todos' | 'online'>('todos')

const cargandoUsuarios = ref(false)

// ── Estado formulario ──────────────────────────────────────────────────────
const form = ref({ NOMBRE: '', DATO1: '', email: '', NIVEL: '' as number | '', EMPRESA: '', DOMICILIO: '', TELEFONO: '', DNI: '', NOTAS: '', RENOVAR: false, CADACUANTO: 0, CONTADOR: 0, ES_ADMIN: false })
const editandoLogin  = ref(false)
const guardandoForm  = ref(false)
const guardandoEstado = ref(false)
const guardandoReset = ref(false)
const errorForm      = ref('')
const modalReset     = ref(false)
// Modo edición: los campos y "Guardar cambios" están bloqueados hasta apretar
// "Editar usuario"; al guardar vuelven a bloquearse y se muestra el aviso okForm.
const editando       = ref(false)
const okForm         = ref('')
const puedeEditar    = computed(() => modoNuevo.value || editando.value)
function avisarOk(msg: string) {
  okForm.value = msg
  setTimeout(() => { okForm.value = '' }, 4000)
}

// ── Estado permisos ────────────────────────────────────────────────────────
const permisosSeleccionados = ref<Set<string>>(new Set())
const cargandoPermisos      = ref(false)
const guardandoPermisos     = ref(false)
const mensajePermisos       = ref<Mensaje | null>(null)

// ── Plantillas de rol ──────────────────────────────────────────────────────
interface RolItem { cod: number; descripcion: string; permisos: number }
const roles       = ref<RolItem[]>([])
const rolElegido  = ref<number | ''>('')
async function cargarRoles () {
  try { roles.value = (await api.get('/admin/roles')).data.roles ?? [] } catch { /* opcional */ }
}
// ── Sub-Sectores asociados ─────────────────────────────────────────────────
interface Subsector { sub_cod: number; sub_des: string }
const catalogoSub  = ref<Subsector[]>([])
const subAsociados = ref<Subsector[]>([])
const subElegidos  = ref<number[]>([])
const subAgregar   = ref<number | ''>('')
const cargandoSub  = ref(false)
const procesandoSub = ref(false)
const mensajeSub   = ref<Mensaje | null>(null)
const subDisponibles = computed(() =>
  catalogoSub.value.filter(s => !subAsociados.value.some(a => a.sub_cod === s.sub_cod))
)
const flashSub = (texto: string, tipo: 'ok' | 'error' = 'ok') => {
  mensajeSub.value = { texto, tipo }
  if (tipo === 'ok') setTimeout(() => { mensajeSub.value = null }, 4000)
}
async function cargarCatalogoSub () {
  if (catalogoSub.value.length) return
  try { catalogoSub.value = (await api.get('/admin/subsectores')).data ?? [] } catch { /* opcional */ }
}
async function cargarSubsectores (codigo: number) {
  cargandoSub.value = true
  subElegidos.value = []
  subAgregar.value = ''
  mensajeSub.value = null
  try {
    await cargarCatalogoSub()
    subAsociados.value = (await api.get(`/admin/usuarios/${codigo}/subsectores`)).data ?? []
  } catch { subAsociados.value = [] }
  finally { cargandoSub.value = false }
}
async function agregarSubsector () {
  if (!subAgregar.value || !usuarioActual.value) return
  procesandoSub.value = true
  try {
    await api.post(`/admin/usuarios/${usuarioActual.value.CODIGO}/subsectores`, { sub_cod: subAgregar.value })
    await cargarSubsectores(usuarioActual.value.CODIGO)
    flashSub('Sub-sector asociado correctamente.')
  } catch (e: any) { flashSub(e?.response?.data?.message ?? 'No se pudo agregar.', 'error') }
  finally { procesandoSub.value = false }
}
async function eliminarSubsectores () {
  if (!subElegidos.value.length || !usuarioActual.value) return
  if (!confirm('¿Quitar los sub-sectores tildados de este usuario?')) return
  procesandoSub.value = true
  try {
    for (const cod of subElegidos.value) {
      await api.delete(`/admin/usuarios/${usuarioActual.value.CODIGO}/subsectores/${cod}`)
    }
    await cargarSubsectores(usuarioActual.value.CODIGO)
    flashSub('Sub-sectores quitados.')
  } catch (e: any) { flashSub(e?.response?.data?.message ?? 'No se pudo eliminar.', 'error') }
  finally { procesandoSub.value = false }
}

async function cargarPlantillaRol () {
  if (!rolElegido.value) return
  try {
    const { data } = await api.get(`/admin/roles/${rolElegido.value}`)
    permisosSeleccionados.value = new Set(data.permisos as string[])
    mensajePermisos.value = { tipo: 'ok', texto: `Plantilla "${data.descripcion}" cargada. Revisá y guardá los permisos.` }
  } catch {
    mensajePermisos.value = { tipo: 'error', texto: 'No se pudo cargar la plantilla del rol.' }
  } finally {
    rolElegido.value = ''
  }
}

// ── Computed ───────────────────────────────────────────────────────────────

const usuariosFiltrados = computed(() => {
  // ESTADO es un flag de sesión (1 = logueado / "online"), no activo/pasivo.
  let lista = usuarios.value
  if (filtroEstado.value === 'online') lista = lista.filter(u => u.ESTADO === 1)
  const f = filtroNombre.value.toLowerCase()
  if (!f) return lista
  return lista.filter(u =>
    u.NOMBRE.toLowerCase().includes(f) || u.DATO1.toLowerCase().includes(f)
  )
})

const haySeleccionados = computed(() => permisosSeleccionados.value.size > 0)

// ── Helpers UI ─────────────────────────────────────────────────────────────

function iniciales(nombre: string): string {
  return nombre.split(' ').slice(0, 2).map(p => p[0] || '').join('').toUpperCase()
}

function badgeClass(u: UsuarioAdmin): string {
  if (u.es_admin) return 'badge-admin'
  if (u.tiene_restricciones) return 'badge-restr'
  return 'badge-libre'
}

function badgeLabel(u: UsuarioAdmin): string {
  if (u.es_admin) return 'Admin'
  if (u.tiene_restricciones) return `${u.cantidad_permisos}p`
  return 'Libre'
}

// ── PDF: lista de usuarios (ordenada por nombre) ────────────────────────────
const pdfUrl = ref(''); const pdfNombre = ref('')
const cerrarPdf = () => { if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value); pdfUrl.value = '' }
function nombreRol(cod: number): string {
  const n = (niveles.value as any[]).find(x => Number(x.CODNIV) === Number(cod))
  return n ? String(n.DESCRIBE).trim() : ''
}
function generarPdfUsuarios() {
  const lista = [...usuarios.value].sort((a, b) => a.NOMBRE.localeCompare(b.NOMBRE, 'es'))
  const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })
  const ML = 14, MR = 196; let y = 16
  doc.setFont('helvetica', 'bold'); doc.setFontSize(14)
  doc.text('USUARIOS DEL SISTEMA', 105, y, { align: 'center' })
  doc.setFont('helvetica', 'normal'); doc.setFontSize(9)
  doc.text(`Generado: ${new Date().toLocaleString('es-AR')}`, MR, y, { align: 'right' })
  y += 6; doc.setFontSize(9); doc.text(`Total: ${lista.length} usuarios`, ML, y); y += 6
  const cols = ['Nombre', 'Usuario', 'Rol', 'Admin', 'Estado']
  const xs = [ML, ML + 66, ML + 104, ML + 152, ML + 168]
  const head = () => {
    doc.setFillColor(27, 67, 50); doc.setTextColor(255, 255, 255); doc.setFont('helvetica', 'bold'); doc.setFontSize(8.5)
    doc.rect(ML, y - 4.5, MR - ML, 6.5, 'F')
    cols.forEach((c, i) => doc.text(c, xs[i], y)); y += 5
    doc.setTextColor(0, 0, 0); doc.setFont('helvetica', 'normal')
  }
  head()
  doc.setFontSize(8.5)
  lista.forEach((u, idx) => {
    if (y > 285) { doc.addPage(); y = 16; head(); doc.setFontSize(8.5) }
    if (idx % 2 === 1) { doc.setFillColor(244, 247, 250); doc.rect(ML, y - 4, MR - ML, 5.5, 'F') }
    doc.text((u.NOMBRE || '').slice(0, 40), xs[0], y)
    doc.text((u.DATO1 || '').slice(0, 22), xs[1], y)
    doc.text((nombreRol(u.NIVEL) || '-').slice(0, 26), xs[2], y)
    doc.text(u.es_admin ? 'Sí' : '', xs[3], y)
    doc.text(u.ESTADO ? 'Online' : 'Offline', xs[4], y)
    doc.setDrawColor(226, 232, 240); doc.line(ML, y + 1.5, MR, y + 1.5); y += 5.5
  })
  cerrarPdf(); pdfNombre.value = 'USUARIOS_DEL_SISTEMA.pdf'; pdfUrl.value = URL.createObjectURL(doc.output('blob'))
}

// ── Carga de datos ─────────────────────────────────────────────────────────

async function cargarUsuarios() {
  cargandoUsuarios.value = true
  try {
    const [resU, resN] = await Promise.all([
      api.get('/admin/usuarios'),
      api.get('/admin/niveles'),
    ])
    // ESTADO/ES_ADMIN llegan como string desde SQL Server ("1"/"0"); se normalizan
    // a número para que el filtro (=== 1) y el indicador de estado funcionen bien.
    usuarios.value = (resU.data ?? []).map((u: any) => ({
      ...u, ESTADO: Number(u.ESTADO ?? 0), ES_ADMIN: Number(u.ES_ADMIN ?? 0),
    }))
    niveles.value  = resN.data
  } finally {
    cargandoUsuarios.value = false
  }
}

async function seleccionarUsuario(u: UsuarioAdmin) {
  usuarioActual.value = u
  modoNuevo.value     = false
  editandoLogin.value = false
  editando.value      = false
  okForm.value        = ''
  errorForm.value     = ''
  tabActiva.value     = 'datos'

  // Poblar formulario con los datos básicos…
  form.value = { NOMBRE: u.NOMBRE, DATO1: u.DATO1, email: u.email ?? '', NIVEL: u.NIVEL, EMPRESA: u.EMPRESA ?? '', DOMICILIO: '', TELEFONO: '', DNI: '', NOTAS: '', RENOVAR: false, CADACUANTO: 0, CONTADOR: 0, ES_ADMIN: !!u.es_admin }
  // …y completar con el detalle (domicilio, teléfono, dni, notas, renovación)
  try {
    const d = (await api.get(`/admin/usuarios/${u.CODIGO}/detalle`)).data
    form.value.DOMICILIO  = d.DOMICILIO ?? ''
    form.value.TELEFONO   = d.TELEFONO ?? ''
    form.value.DNI        = d.DNI ?? ''
    form.value.NOTAS      = d.NOTAS ?? ''
    form.value.RENOVAR    = !!d.RENOVAR
    form.value.CADACUANTO = d.CADACUANTO ?? 0
    form.value.CONTADOR   = d.CONTADOR ?? 0
    form.value.ES_ADMIN   = !!d.ES_ADMIN
  } catch { /* el detalle es complementario */ }

  // Si no es admin, cargar sus permisos
  if (!u.es_admin) {
    await cargarPermisos(u.CODIGO)
  }
  await cargarSubsectores(u.CODIGO)
}

async function cargarPermisos(codigo: number) {
  cargandoPermisos.value    = true
  permisosSeleccionados.value = new Set()
  mensajePermisos.value     = null
  try {
    const res = await api.get(`/admin/usuarios/${codigo}/permisos`)
    permisosSeleccionados.value = new Set(res.data.permisos as string[])
  } finally {
    cargandoPermisos.value = false
  }
}

// ── Formulario nuevo usuario ───────────────────────────────────────────────

function abrirFormNuevo() {
  modoNuevo.value     = true
  usuarioActual.value = null
  tabActiva.value     = 'datos'
  editandoLogin.value = false
  editando.value      = false
  okForm.value        = ''
  errorForm.value     = ''
  form.value = { NOMBRE: '', DATO1: '', email: '', NIVEL: '', EMPRESA: '', DOMICILIO: '', TELEFONO: '', DNI: '', NOTAS: '', RENOVAR: false, CADACUANTO: 0, CONTADOR: 0, ES_ADMIN: false }
}

function cancelarForm() {
  modoNuevo.value     = false
  editandoLogin.value = false
  editando.value      = false
  okForm.value        = ''
  errorForm.value     = ''
  if (!usuarioActual.value) return
  // Restaurar datos recargando el detalle del usuario seleccionado
  seleccionarUsuario(usuarioActual.value)
}

// ── Guardar usuario ────────────────────────────────────────────────────────

async function guardarUsuario() {
  guardandoForm.value = true
  errorForm.value     = ''
  try {
    if (modoNuevo.value) {
      const res = await api.post('/admin/usuarios', form.value)
      usuarios.value.push(res.data.usuario)
      usuarios.value.sort((a, b) => a.NOMBRE.localeCompare(b.NOMBRE))
      modoNuevo.value = false
      await seleccionarUsuario(res.data.usuario)
      avisarOk('Usuario creado correctamente.')
    } else {
      const res = await api.put(`/admin/usuarios/${usuarioActual.value!.CODIGO}`, form.value)
      const idx = usuarios.value.findIndex(u => u.CODIGO === usuarioActual.value!.CODIGO)
      if (idx !== -1) usuarios.value[idx] = res.data.usuario
      usuarioActual.value = res.data.usuario
      editandoLogin.value = false
      editando.value      = false   // vuelve a bloquear los campos
      avisarOk('Cambios guardados correctamente.')
    }
  } catch (e: any) {
    const errs = e.response?.data?.errors
    if (errs) {
      errorForm.value = Object.values(errs).flat().join(' ')
    } else {
      errorForm.value = e.response?.data?.message || 'Error al guardar. Revisá los datos.'
    }
  } finally {
    guardandoForm.value = false
  }
}

// ── Toggle estado activo/inactivo ──────────────────────────────────────────

async function toggleEstado() {
  if (!usuarioActual.value) return
  guardandoEstado.value = true
  try {
    const nuevoEstado = usuarioActual.value.ESTADO ? 0 : 1
    await api.patch(`/admin/usuarios/${usuarioActual.value.CODIGO}/estado`, { ESTADO: nuevoEstado })
    usuarioActual.value.ESTADO = nuevoEstado
    const idx = usuarios.value.findIndex(u => u.CODIGO === usuarioActual.value!.CODIGO)
    if (idx !== -1) usuarios.value[idx].ESTADO = nuevoEstado
  } catch (e: any) {
    alert(e.response?.data?.message || 'Error al cambiar estado')
  } finally {
    guardandoEstado.value = false
  }
}

// ── Reset de acceso ────────────────────────────────────────────────────────

function confirmarReset() { modalReset.value = true }

async function ejecutarReset() {
  if (!usuarioActual.value) return
  guardandoReset.value = true
  try {
    const res = await api.post(`/admin/usuarios/${usuarioActual.value.CODIGO}/reset-acceso`)
    modalReset.value = false
    alert(res.data.message)
    const idx = usuarios.value.findIndex(u => u.CODIGO === usuarioActual.value!.CODIGO)
    if (idx !== -1) usuarios.value[idx].primer_acceso = 1
  } finally {
    guardandoReset.value = false
  }
}

// ── Establecer contraseña (reset por administrador, sin email) ──────────────
const modalClave     = ref(false)
const nuevaClave     = ref('')
const verClave       = ref(false)
const guardandoClave = ref(false)
const errorClave     = ref('')

function abrirModalClave() {
  nuevaClave.value = ''
  verClave.value   = false
  errorClave.value = ''
  modalClave.value = true
}
function cerrarModalClave() { modalClave.value = false }

// Genera una clave temporal legible (fácil de dictar) que cumple el mínimo.
function generarClave() {
  nuevaClave.value = 'Rrhh' + Math.floor(1000 + Math.random() * 9000) + '!'
  verClave.value = true
}

async function guardarClave() {
  if (!usuarioActual.value) return
  if (nuevaClave.value.length < 6) { errorClave.value = 'La contraseña debe tener al menos 6 caracteres.'; return }
  guardandoClave.value = true
  errorClave.value = ''
  try {
    const res = await api.post(`/admin/usuarios/${usuarioActual.value.CODIGO}/establecer-password`, { password: nuevaClave.value })
    modalClave.value = false
    alert(res.data.message + '\n\nContraseña asignada: ' + nuevaClave.value + '\n(Comunicásela al usuario.)')
    const idx = usuarios.value.findIndex(u => u.CODIGO === usuarioActual.value!.CODIGO)
    if (idx !== -1) usuarios.value[idx].primer_acceso = 0
  } catch (e: any) {
    errorClave.value = e.response?.data?.message || 'No se pudo establecer la contraseña.'
  } finally {
    guardandoClave.value = false
  }
}

// ── Ver clave actual (solo Administrador total, requiere copia cifrada reversible) ─
const esAdmin = computed(() => useAuthStore().esAdmin)
const modalVerClave = ref(false)
const claveVista    = ref<string | null>(null)
const verClaveVista = ref(false)

async function verClaveUsuario() {
  if (!usuarioActual.value) return
  try {
    const res = await api.get(`/admin/usuarios/${usuarioActual.value.CODIGO}/ver-clave`)
    claveVista.value = res.data.disponible ? res.data.clave : null
    verClaveVista.value = true
    modalVerClave.value = true
  } catch (e: any) {
    alert(e.response?.data?.message || 'No se pudo obtener la clave.')
  }
}

function copiarClaveVista() {
  if (claveVista.value) navigator.clipboard?.writeText(claveVista.value)
}

// ── Permisos: checkboxes ───────────────────────────────────────────────────

function todosLosIds(items: MenuItem[]): string[] {
  const ids: string[] = []
  for (const item of items) {
    if (item.separador) continue
    if (item.hijos?.length) ids.push(...todosLosIds(item.hijos))
    else if (item.id) ids.push(item.id)
  }
  return ids
}

function toggleItem(id: string) {
  const s = new Set(permisosSeleccionados.value)
  s.has(id) ? s.delete(id) : s.add(id)
  permisosSeleccionados.value = s
}

function grupoCheckeado(item: MenuItem): boolean {
  const ids = todosLosIds(item.hijos?.length ? item.hijos : [item])
  return ids.length > 0 && ids.every(id => permisosSeleccionados.value.has(id))
}

function grupoIndeterminado(item: MenuItem): boolean {
  const ids = todosLosIds(item.hijos?.length ? item.hijos : [item])
  const n = ids.filter(id => permisosSeleccionados.value.has(id)).length
  return n > 0 && n < ids.length
}

function toggleGrupo(item: MenuItem) {
  const ids = todosLosIds(item.hijos?.length ? item.hijos : [item])
  const s   = new Set(permisosSeleccionados.value)
  const todos = ids.every(id => s.has(id))
  todos ? ids.forEach(id => s.delete(id)) : ids.forEach(id => s.add(id))
  permisosSeleccionados.value = s
}

function marcarTodos(marcar: boolean) {
  permisosSeleccionados.value = marcar ? new Set(todosLosIds(menuConfig)) : new Set()
}

async function guardarPermisos() {
  if (!usuarioActual.value) return
  guardandoPermisos.value = true
  mensajePermisos.value   = null
  try {
    const res = await api.put(
      `/admin/usuarios/${usuarioActual.value.CODIGO}/permisos`,
      { permisos: [...permisosSeleccionados.value] }
    )
    mensajePermisos.value = { texto: res.data.message, tipo: 'ok' }
    const idx = usuarios.value.findIndex(u => u.CODIGO === usuarioActual.value!.CODIGO)
    if (idx !== -1) {
      usuarios.value[idx].tiene_restricciones = res.data.tiene_restricciones
      usuarios.value[idx].cantidad_permisos   = res.data.cantidad_permisos
    }
    setTimeout(() => { mensajePermisos.value = null }, 4000)
  } catch (e: any) {
    mensajePermisos.value = {
      texto: e.response?.data?.message || 'Error al guardar permisos',
      tipo: 'error',
    }
  } finally {
    guardandoPermisos.value = false
  }
}

// ── Inicializar ───────────────────────────────────────────────────────────
cargarUsuarios()
cargarRoles()
</script>

<style scoped>
/* ── Layout ─────────────────────────────────────────────────────────── */
.admin-usuarios {
  padding: 1.25rem 1.5rem;
  height: 100%;
  display: flex;
  flex-direction: column;
  gap: 1rem;
  overflow: hidden;
}

/* ── Cabecera ────────────────────────────────────────────────────────── */
.cab {
  display: flex;
  align-items: center;
  gap: 0.85rem;
  flex-shrink: 0;
}
.cab-icono  { font-size: 2rem; background: rgba(45,106,159,0.12); border-radius: 12px; padding: 0.45rem 0.6rem; }
.cab-textos { flex: 1; }
.cab-titulo { margin: 0; font-size: 1.25rem; color: #1a3a5c; font-weight: 700; }
.cab-sub    { margin: 0; font-size: 0.82rem; color: #5a7a9a; }

.btn-nuevo {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  padding: 0.45rem 1rem;
  background: #2d6a9f;
  color: #fff;
  border: none;
  border-radius: 8px;
  font-size: 0.83rem;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.15s;
  flex-shrink: 0;
}
.btn-nuevo:hover { background: #1e4d7a; }
.btn-pdf {
  display: flex; align-items: center; gap: 0.35rem; margin-right: 8px;
  padding: 0.45rem 0.9rem; background: #fff; color: #1b4332; border: 1px solid #c3e6cb;
  border-radius: 8px; font-size: 0.83rem; font-weight: 600; cursor: pointer; flex-shrink: 0; transition: background .15s;
}
.btn-pdf:hover { background: #f0faf4; }
/* Modal PDF */
.pdf-ov { position: fixed; inset: 0; background: rgba(15,23,42,.6); z-index: 10000; display: flex; align-items: center; justify-content: center; padding: 18px; }
.pdf-md { width: min(900px,97vw); height: 92vh; background: #fff; border-radius: 10px; overflow: hidden; display: flex; flex-direction: column; box-shadow: 0 24px 60px rgba(0,0,0,.5); }
.pdf-head { display: flex; align-items: center; gap: 14px; padding: 10px 14px; background: #1b4332; color: #fff; font-size: 13px; }
.pdf-acc { margin-left: auto; display: flex; gap: 8px; }
.pdf-b { border: none; padding: 7px 12px; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 600; } .pdf-b.ok { background: #22c55e; color: #fff; } .pdf-b.cancel { background: #ef4444; color: #fff; }
.pdf-frame { flex: 1; border: none; width: 100%; }

/* ── Layout dos columnas ─────────────────────────────────────────────── */
.layout {
  display: flex;
  gap: 1rem;
  flex: 1;
  overflow: hidden;
  min-height: 0;
}

/* ── Columna lista ───────────────────────────────────────────────────── */
.col-lista {
  width: 285px;
  min-width: 285px;
  background: #fff;
  border: 1px solid #dde4ee;
  border-radius: 10px;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.col-header {
  padding: 0.6rem 0.75rem;
  border-bottom: 1px solid #dde4ee;
  background: #f4f7fc;
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
  font-size: 0.8rem;
  font-weight: 700;
  color: #2a4a6a;
}

.filtros {
  display: flex;
  gap: 0.3rem;
  align-items: center;
}

.buscador {
  flex: 1;
  min-width: 0;
  border: 1px solid #c8d8ea;
  border-radius: 6px;
  padding: 0.22rem 0.4rem;
  font-size: 0.74rem;
  outline: none;
  color: #1a3a5c;
}
.buscador:focus { border-color: #2d6a9f; }

.btn-filtro {
  padding: 0.22rem 0.4rem;
  border: 1px solid #c8d8ea;
  border-radius: 5px;
  background: #fff;
  font-size: 0.7rem;
  cursor: pointer;
  color: #5a7a9a;
  white-space: nowrap;
  flex-shrink: 0;
}
.btn-filtro.activo { background: #2d6a9f; color: #fff; border-color: #2d6a9f; }

.lista-usuarios { overflow-y: auto; flex: 1; scrollbar-width: thin; }

.item-usuario {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  width: 100%;
  padding: 0.5rem 0.75rem;
  border: none;
  background: transparent;
  cursor: pointer;
  border-bottom: 1px solid #f0f4f9;
  text-align: left;
  transition: background 0.1s;
}
.item-usuario:hover     { background: #f0f7ff; }
.item-usuario.seleccionado { background: #e0eefc; border-left: 3px solid #2d6a9f; padding-left: calc(0.75rem - 3px); }
.item-usuario.inactivo  { opacity: 0.5; }

.u-avatar {
  width: 30px; height: 30px; border-radius: 50%;
  background: #1e4d7a; color: #b8d4f0;
  font-size: 0.65rem; font-weight: 700;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.u-av-admin { background: #92400e; color: #fef3c7; }
.u-info  { flex: 1; overflow: hidden; }
.u-nombre { font-size: 0.78rem; color: #1a3a5c; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.u-meta   { display: flex; align-items: center; gap: 0.35rem; }
.u-login  { font-size: 0.68rem; color: #7a9ab0; }
.u-estado { font-size: 0.7rem; }
.est-activo  { color: #16a34a; }
.est-inactivo { color: #9ca3af; }

.u-badge {
  font-size: 0.62rem; font-weight: 700;
  padding: 0.15rem 0.38rem; border-radius: 8px;
  white-space: nowrap; flex-shrink: 0;
}
.badge-admin { background: #fef3c7; color: #92400e; }
.badge-libre { background: #d1fae5; color: #065f46; }
.badge-restr { background: #fee2e2; color: #991b1b; }

/* ── Columna detalle ─────────────────────────────────────────────────── */
.col-detalle {
  flex: 1;
  background: #fff;
  border: 1px solid #dde4ee;
  border-radius: 10px;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.col-vacia { justify-content: center; align-items: center; }
.sin-sel   { text-align: center; color: #8aacca; }
.sin-ico   { font-size: 2.5rem; margin-bottom: 0.5rem; }

/* ── Tabs ────────────────────────────────────────────────────────────── */
.tabs {
  display: flex;
  border-bottom: 2px solid #e8eef5;
  background: #f4f7fc;
  flex-shrink: 0;
}

.tab {
  padding: 0.6rem 1.1rem;
  border: none;
  background: transparent;
  font-size: 0.82rem;
  font-weight: 600;
  color: #6a8aaa;
  cursor: pointer;
  border-bottom: 2px solid transparent;
  margin-bottom: -2px;
  transition: color 0.15s, border-color 0.15s;
}
.tab:hover   { color: #2a4a6a; }
.tab.activa  { color: #2d6a9f; border-bottom-color: #2d6a9f; }
.tab:disabled { opacity: 0.35; cursor: not-allowed; }

/* ── Contenido tabs ──────────────────────────────────────────────────── */
.tab-contenido {
  flex: 1;
  overflow-y: auto;
  padding: 1.25rem;
  scrollbar-width: thin;
}

/* ── Formulario ──────────────────────────────────────────────────────── */
.form-usuario { display: flex; flex-direction: column; gap: 1rem; max-width: 640px; }

.form-titulo {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 0.9rem;
  font-weight: 700;
  color: #1a3a5c;
  padding-bottom: 0.5rem;
  border-bottom: 1px solid #e8eef5;
}

.acciones-estado { display: flex; gap: 0.5rem; }

.btn-accion {
  padding: 0.3rem 0.7rem;
  border-radius: 6px;
  border: none;
  font-size: 0.75rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s;
}
.btn-desactivar { background: #fee2e2; color: #991b1b; }
.btn-desactivar:hover { background: #fecaca; }
.btn-activar    { background: #d1fae5; color: #065f46; }
.btn-activar:hover { background: #a7f3d0; }
.btn-reset      { background: #fef3c7; color: #92400e; }
.btn-reset:hover { background: #fde68a; }
.btn-clave      { background: #dbeafe; color: #1e40af; }
.btn-clave:hover { background: #bfdbfe; }
.btn-verclave      { background: #fff7ed; color: #9a3412; border-color: #fed7aa; }
.btn-verclave:hover { background: #ffedd5; }
.cv-nota { font-size: 12px; color: #64748b; margin: 6px 0 0; }
.btn-editar     { background: #d1fae5; color: #065f46; }
.btn-editar:hover { background: #a7f3d0; }
.fs-campos { border: none; margin: 0; padding: 0; min-width: 0; }
.btn-accion:disabled { opacity: 0.5; cursor: not-allowed; }

/* ── Modal establecer contraseña ── */
.clave-campo { display: flex; gap: 0.5rem; align-items: center; }
.clave-campo input {
  flex: 1; padding: 0.5rem 0.65rem; border: 1px solid #c8d8ea;
  border-radius: 6px; font-size: 0.9rem; color: #1a3a5c; outline: none;
}
.clave-campo input:focus { border-color: #2d6a9f; }
.btn-generar {
  background: #f0f4f9; border: 1px solid #c8d8ea; border-radius: 6px;
  padding: 0.45rem; font-size: 0.8rem; cursor: pointer; color: #2d6a9f; font-weight: 600;
}
.btn-generar:hover { background: #e2ebf5; }

.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.85rem; }

.campo { display: flex; flex-direction: column; gap: 0.3rem; }
.campo label { font-size: 0.78rem; font-weight: 600; color: #3a5a7a; }
.campo input, .campo select {
  padding: 0.4rem 0.65rem;
  border: 1px solid #c8d8ea;
  border-radius: 6px;
  font-size: 0.82rem;
  color: #1a3a5c;
  outline: none;
  transition: border-color 0.15s;
  background: #fff;
}
.campo input:focus, .campo select:focus { border-color: #2d6a9f; }
.campo input:disabled { background: #f4f7fc; color: #8aacca; cursor: not-allowed; }
.req  { color: #e53e3e; }
.hint { font-size: 0.7rem; color: #8aacca; }
.hint-warn { color: #c05621; }

.campo-accion { margin-top: 0.15rem; }
.link-btn {
  background: none;
  border: none;
  color: #2d6a9f;
  font-size: 0.72rem;
  cursor: pointer;
  padding: 0;
  text-decoration: underline;
}

.info-nuevo {
  display: flex;
  gap: 0.65rem;
  background: #eff8ff;
  border: 1px solid #bde0f9;
  border-radius: 8px;
  padding: 0.75rem;
  font-size: 0.78rem;
  color: #1a5276;
}
.info-nuevo p { margin: 0.25rem 0 0; color: #2874a6; }
.info-nuevo strong { display: block; margin-bottom: 0.2rem; }

.error-form {
  background: #fee2e2;
  color: #991b1b;
  border-radius: 6px;
  padding: 0.5rem 0.75rem;
  font-size: 0.8rem;
}
.ok-form {
  background: #d1fae5;
  color: #065f46;
  border-radius: 6px;
  padding: 0.5rem 0.75rem;
  font-size: 0.8rem;
  font-weight: 600;
}

.form-footer {
  display: flex;
  gap: 0.6rem;
  justify-content: flex-end;
  padding-top: 0.5rem;
  border-top: 1px solid #e8eef5;
}

.btn-cancelar {
  padding: 0.4rem 1rem;
  border: 1px solid #c8d8ea;
  border-radius: 6px;
  background: #fff;
  color: #5a7a9a;
  font-size: 0.82rem;
  cursor: pointer;
}
.btn-cancelar:hover { background: #f4f7fc; }

.btn-guardar {
  padding: 0.4rem 1.1rem;
  border: none;
  border-radius: 6px;
  background: #2d6a9f;
  color: #fff;
  font-size: 0.82rem;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.15s;
}
.btn-guardar:hover:not(:disabled) { background: #1e4d7a; }
.btn-guardar:disabled { opacity: 0.5; cursor: not-allowed; }

/* ── Tab permisos ────────────────────────────────────────────────────── */
.tab-permisos { padding: 0; display: flex; flex-direction: column; overflow: hidden; }

.es-admin-msg {
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  height: 100%; color: #8aacca; text-align: center; padding: 2rem; gap: 0.5rem;
}
.es-admin-msg .sin-icono { font-size: 2.5rem; }
.es-admin-msg strong { color: #1a3a5c; }
.es-admin-msg .sub { font-size: 0.8rem; color: #9ab8cc; }

.permisos-barra {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.65rem 1rem;
  border-bottom: 1px solid #e8eef5;
  background: #f4f7fc;
  flex-shrink: 0;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.pb-acciones { display: flex; gap: 0.4rem; align-items: center; }
.sel-rol { border: 1px solid #c8d8ea; border-radius: 6px; padding: 0.45rem 0.5rem; font-size: 0.8rem; color: #1a3a5c; background: #f4f7fc; cursor: pointer; }

.tag-libre { font-size: 0.78rem; color: #065f46; background: #d1fae5; padding: 0.2rem 0.6rem; border-radius: 8px; }
.tag-restr { font-size: 0.78rem; color: #991b1b; background: #fee2e2; padding: 0.2rem 0.6rem; border-radius: 8px; }

.btn-todos, .btn-ninguno, .btn-guardar-p {
  padding: 0.3rem 0.7rem; border-radius: 5px; border: none;
  font-size: 0.75rem; font-weight: 600; cursor: pointer; transition: all 0.15s;
}
.btn-todos    { background: #d1fae5; color: #065f46; }
.btn-todos:hover { background: #a7f3d0; }
.btn-ninguno  { background: #fee2e2; color: #991b1b; }
.btn-ninguno:hover { background: #fecaca; }
.btn-guardar-p { background: #2d6a9f; color: #fff; padding: 0.3rem 0.85rem; }
.btn-guardar-p:hover:not(:disabled) { background: #1e4d7a; }
.btn-guardar-p:disabled { opacity: 0.5; cursor: not-allowed; }

.mensaje-permisos {
  margin: 0.5rem 1rem;
  padding: 0.45rem 0.75rem;
  border-radius: 6px;
  font-size: 0.8rem;
  font-weight: 600;
  flex-shrink: 0;
}
.mensaje-permisos.ok    { background: #d1fae5; color: #065f46; }
.mensaje-permisos.error { background: #fee2e2; color: #991b1b; }
.msg-enter-active, .msg-leave-active { transition: opacity 0.3s; }
.msg-enter-from, .msg-leave-to { opacity: 0; }

/* ── Árbol de checkboxes ─────────────────────────────────────────────── */
.arbol {
  flex: 1; overflow-y: auto; padding: 0.5rem;
  scrollbar-width: thin; scrollbar-color: #c8d8ea transparent;
}

.arbol-seccion { margin-bottom: 0.1rem; }
.arbol-sep { height: 1px; background: #eef2f7; margin: 0.15rem 0; }
.arbol-sep.n2 { margin-left: 2.5rem; }

.arbol-item {
  display: flex; align-items: center; gap: 0.4rem;
  padding: 0.27rem 0.5rem; border-radius: 5px;
  cursor: pointer; font-size: 0.78rem; color: #2a4a6a;
  transition: background 0.1s; user-select: none;
}
.arbol-item:hover { background: #f0f7ff; }
.arbol-item input[type="checkbox"] { cursor: pointer; width: 13px; height: 13px; flex-shrink: 0; accent-color: #2d6a9f; }

.nivel-0 { font-weight: 700; color: #1a3a5c; background: #f4f7fc; border-radius: 6px; font-size: 0.8rem; }
.nivel-1 { padding-left: 1.4rem; }
.nivel-2 { padding-left: 2.7rem; color: #3a5a7a; }
.nivel-3 { padding-left: 4rem; color: #4a6a8a; font-size: 0.74rem; }

.a-ico { flex-shrink: 0; font-size: 0.82rem; min-width: 1rem; text-align: center; }
.a-lbl { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

/* ── Estado cargando ─────────────────────────────────────────────────── */
.estado-msg { padding: 1.5rem; text-align: center; color: #8aacca; font-size: 0.85rem; }
.spin { display: inline-block; animation: spin 1s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Modal ───────────────────────────────────────────────────────────── */
.modal-overlay {
  position: fixed; inset: 0;
  background: rgba(0,0,0,0.45);
  display: flex; align-items: center; justify-content: center;
  z-index: 100;
}

.modal {
  background: #fff;
  border-radius: 12px;
  padding: 2rem;
  max-width: 400px;
  width: 90%;
  text-align: center;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}
.modal-ico { font-size: 2.5rem; }
.modal h3  { margin: 0; color: #1a3a5c; font-size: 1.05rem; }
.modal p   { margin: 0; color: #3a5a7a; font-size: 0.85rem; }
.modal-sub { color: #8aacca !important; font-size: 0.78rem !important; }

.modal-btns { display: flex; gap: 0.6rem; justify-content: center; margin-top: 0.5rem; }
.btn-confirmar {
  padding: 0.42rem 1.1rem;
  background: #dc2626; color: #fff;
  border: none; border-radius: 6px;
  font-size: 0.83rem; font-weight: 600; cursor: pointer;
}
.btn-confirmar:hover:not(:disabled) { background: #b91c1c; }
.btn-confirmar:disabled { opacity: 0.5; cursor: not-allowed; }

/* ── Checkbox administrador total ── */
.chk-admin { display: flex; align-items: center; gap: 0.5rem; font-size: 0.84rem; color: #1e293b; cursor: pointer; padding: 0.4rem 0; }
.chk-admin input { width: 15px; height: 15px; accent-color: #b45309; }

/* ── Renovación de clave ── */
.form-renovar { margin-top: 1rem; padding: 0.9rem 1rem; background: #f4f7fc; border: 1px solid #e2e8f0; border-radius: 8px; }
.chk-renovar { display: flex; align-items: center; gap: 0.5rem; font-size: 0.88rem; color: #1e293b; cursor: pointer; font-weight: 600; }
.chk-renovar input { width: 15px; height: 15px; accent-color: #2d6a9f; }
.renovar-detalle { margin-top: 0.7rem; padding-left: 1.5rem; display: flex; flex-wrap: wrap; gap: 1.2rem; }
.campo-inline { display: flex; align-items: center; gap: 0.6rem; }
.campo-inline label { font-size: 0.82rem; color: #475569; }
.inp-num { width: 80px; border: 1px solid #c8d8ea; border-radius: 6px; padding: 0.4rem 0.5rem; text-align: right; color: #1e293b; }
.inp-num:disabled { background: #eef2f7; color: #64748b; }

/* ── Sub-Sectores ── */
.tab-subsectores { padding: 1rem 0.5rem; }
.ss-titulo { text-align: center; font-weight: 700; color: #1a3a5c; background: #f4f7fc; border: 1px solid #dde4ee; border-radius: 8px; padding: 0.6rem; font-size: 0.95rem; }
.ss-sub { text-align: center; color: #2d6a9f; font-size: 0.8rem; margin: 0.4rem 0 0.7rem; font-weight: 600; }
.ss-tabla { border: 1px solid #dde4ee; border-radius: 8px; overflow: hidden; }
.ss-head, .ss-fila { display: grid; grid-template-columns: 70px 1fr 70px; align-items: center; }
.ss-head { background: #2d6a9f; color: #fff; font-size: 0.8rem; font-weight: 700; padding: 0.5rem 0.6rem; }
.ss-head span:last-child, .ss-fila .ss-cod { text-align: center; }
.ss-fila { padding: 0.45rem 0.6rem; border-top: 1px solid #f0f4f9; cursor: pointer; }
.ss-fila:hover { background: #f0f7ff; }
.ss-fila input { width: 15px; height: 15px; accent-color: #2d6a9f; }
.ss-des { color: #1e293b; font-size: 0.85rem; }
.ss-cod { color: #5a7a9a; font-size: 0.82rem; }
.ss-vacio { padding: 1rem; text-align: center; color: #94a3b8; font-size: 0.85rem; }
.ss-acciones { display: flex; gap: 0.5rem; margin-top: 0.8rem; align-items: center; flex-wrap: wrap; }
.sel-ss { flex: 1; min-width: 200px; border: 1px solid #c8d8ea; border-radius: 6px; padding: 0.5rem; color: #1e293b; background: #fff; }
.btn-ss-eliminar { background: #fee2e2; color: #991b1b; border: none; border-radius: 6px; padding: 0.5rem 0.9rem; font-weight: 700; font-size: 0.82rem; cursor: pointer; }
.btn-ss-agregar { background: #2d6a9f; color: #fff; border: none; border-radius: 6px; padding: 0.5rem 0.9rem; font-weight: 700; font-size: 0.82rem; cursor: pointer; }
.btn-ss-eliminar:disabled, .btn-ss-agregar:disabled { opacity: 0.5; cursor: not-allowed; }
.ss-aviso { margin-top: 0.9rem; padding: 0.7rem 0.9rem; background: #fef9c3; border: 1px solid #fde68a; border-radius: 8px; color: #854d0e; font-size: 0.82rem; text-align: center; }
</style>
