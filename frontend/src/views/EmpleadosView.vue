<template>
  <div class="empleados-view">

    <!-- ══════ CABECERA ══════ -->
    <div class="cab">
      <div class="cab-icono">👥</div>
      <div class="cab-textos">
        <h1 class="cab-titulo">Empleados</h1>
        <p class="cab-sub">{{ stats.total }} empleados · {{ stats.activos }} activos</p>
      </div>
      <button class="btn-legajo" title="Legajo histórico del empleado seleccionado"
              :disabled="!empActual || generandoLegajo" @click="imprimirLegajoPDF">
        📄 {{ generandoLegajo ? 'Generando…' : 'Legajo' }}
      </button>
      <button class="btn-listados" title="Listados de empleados" @click="modalListados = true">📊 Listados</button>
      <button class="btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="btn-ayuda" title="Ayuda del módulo" @click="modalAyuda = true">❓ Ayuda</button>
      <button class="btn-nuevo" @click="abrirNuevo">＋ Nuevo empleado</button>
    </div>

    <!-- Modal: módulo de Listados -->
    <Teleport to="body">
      <div v-if="modalListados" class="listados-overlay" @click.self="modalListados = false">
        <div class="listados-modal">
          <EmpleadosListados @close="modalListados = false" />
        </div>
      </div>
    </Teleport>

    <EmpleadosAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/empleados"
            titulo="Asistente IA — Empleados"
            subtitulo="Preguntá sobre cómo usar el módulo de empleados"
            :sugerencias="[
              '¿Cómo cargo los hijos de un empleado?',
              '¿Qué validaciones hay para dar de baja?',
              '¿Cómo se genera el código de un empleado nuevo?',
              '¿Cómo adjunto un documento a una falta?']"
            @close="modalIA = false" />

    <!-- ══════ LAYOUT ══════ -->
    <div class="layout">

      <!-- ── COLUMNA LISTA ── (se colapsa al elegir un empleado; siempre visible si no hay selección) -->
      <div class="col-lista" v-show="!listaColapsada || (!empActual && !modoNuevo)">
        <div class="lista-header">
          <input v-model="buscar" class="buscador" placeholder="🔍 Código, legajo, nombre, domicilio, sexo, padre/madre, hijos..."
                 @input="buscarDebounce" />
          <div class="filtros-row">
            <button class="btn-f" :class="{ act: filtroActivo === 'todos' }"   @click="setFiltro('todos')">Todos</button>
            <button class="btn-f" :class="{ act: filtroActivo === 'activos' }" @click="setFiltro('activos')">Activos</button>
            <button class="btn-f" :class="{ act: filtroActivo === 'bajas' }"   @click="setFiltro('bajas')">Bajas</button>
          </div>
        </div>

        <div v-if="cargando" class="estado-msg"><span class="spin">⟳</span> Cargando...</div>

        <div v-else class="lista-scroll">
          <button v-for="emp in empleados" :key="emp.PER_COD"
            class="item-emp"
            :class="{ sel: empActual?.PER_COD === emp.PER_COD, baja: emp.PER_AOP !== 'A' }"
            @click="seleccionar(emp.PER_COD)">
            <div class="emp-avatar" :class="emp.PER_AOP !== 'A' ? 'av-baja' : (emp.PER_SEX === 'F' ? 'av-f' : 'av-m')">
              {{ iniciales(emp.PER_NOM) }}
            </div>
            <div class="emp-info">
              <div class="emp-nom">{{ emp.PER_NOM }}</div>
              <div class="emp-meta">
                <span class="emp-leg">Leg. {{ emp.PER_LEG }}</span>
                <span class="emp-sec">{{ emp.PER_SED?.trim() }}</span>
              </div>
            </div>
            <div class="emp-estado" :class="emp.PER_AOP === 'A' ? 'est-act' : 'est-baj'">
              {{ emp.PER_AOP === 'A' ? '●' : '○' }}
            </div>
          </button>

          <div v-if="paginas > 1" class="paginacion">
            <button :disabled="pagina === 1" @click="irPagina(pagina - 1)">‹</button>
            <span>{{ pagina }} / {{ paginas }}</span>
            <button :disabled="pagina === paginas" @click="irPagina(pagina + 1)">›</button>
          </div>
          <div v-if="!cargando && empleados.length === 0" class="sin-resultados">Sin resultados</div>
        </div>
      </div>

      <!-- ── PLACEHOLDER sin selección ── -->
      <div v-if="!empActual && !modoNuevo" class="col-detalle col-detalle-vacio">
        <div class="sin-seleccion">
          <span class="sin-seleccion-icono">👤</span>
          <p>Seleccioná un empleado de la lista</p>
        </div>
      </div>

      <!-- ── COLUMNA DETALLE ── -->
      <div class="col-detalle" v-if="empActual || modoNuevo">

        <!-- BARRA DE PESTAÑAS (18 tabs) -->
        <div class="tabs-nav" v-if="!modoNuevo">
          <button v-for="t in TABS" :key="t.id"
            class="tab-btn"
            :class="{ act: tabActual === t.id }"
            @click="cambiarTab(t.id)">
            {{ t.label }}
          </button>
        </div>
        <!-- En modo nuevo solo Tab 1 -->
        <div class="tabs-nav" v-else>
          <button class="tab-btn act">Datos</button>
        </div>

        <!-- Barra de acciones -->
        <div v-if="!modoNuevo && empActual" class="barra-acciones">
          <button class="ba-toggle-lista" :title="listaColapsada ? 'Mostrar la lista de empleados' : 'Ocultar la lista'"
                  @click="listaColapsada = !listaColapsada">
            {{ listaColapsada ? '☰' : '◀' }}
          </button>
          <!-- Foto miniatura a la izquierda, antes del nombre -->
          <img v-if="fotoUrl" :src="fotoUrl" class="ba-foto" :alt="empActual.PER_NOM" />
          <div class="ba-nombre">
            <span class="ba-emp-nombre">{{ empActual.PER_NOM }}</span>
            <span :class="empActual.PER_AOP === 'A' ? 'badge-act' : 'badge-baj'">
              {{ empActual.PER_AOP === 'A' ? 'Activo' : 'De baja' }}
            </span>
            <span class="ba-leg">Leg. {{ empActual.PER_LEG }}</span>
            <span v-if="modoEdicion" class="badge-editando">✏️ Editando</span>
          </div>
          <div class="ba-btns">
            <!-- Botones Editar / Guardar+Cancelar — solo en tabs editables -->
            <template v-if="['datos', 'obrasocial', 'licencia', 'valorhoras'].includes(tabActual)">
              <template v-if="!modoEdicion">
                <button class="btn-sm btn-editar" @click="activarEdicion">✏️ Editar</button>
              </template>
              <template v-else>
                <button class="btn-sm btn-save" @click="guardar" :disabled="guardando">
                  {{ guardando ? '...' : '💾 Guardar' }}
                </button>
                <button class="btn-sm btn-cancel" @click="cancelarEdicion">✕ Cancelar</button>
              </template>
            </template>
            <!-- Baja / Reactivar — siempre visibles -->
            <button v-if="empActual.PER_AOP === 'A'" class="btn-sm btn-baja" @click="bajaError = ''; modalBaja = true">🚫 Dar de baja</button>
            <button v-else class="btn-sm btn-reactivar" @click="reactivar">✅ Reactivar</button>
          </div>
        </div>
        <!-- Barra nuevo empleado -->
        <div v-if="modoNuevo" class="barra-acciones">
          <div class="ba-nombre"><span class="badge-act">Nuevo</span></div>
          <div class="ba-btns">
            <button class="btn-sm btn-save" @click="guardar" :disabled="guardando">{{ guardando ? '...' : '💾 Guardar' }}</button>
            <button class="btn-sm btn-cancel" @click="cancelarNuevo">✕ Cancelar</button>
          </div>
        </div>

        <div v-if="errorForm" class="error-form">{{ errorForm }}</div>

        <!-- ════════════════════════════════════════════════════
             TAB ACCIONES — lanzadores de ABM con el empleado precargado (Propuesta A)
             ════════════════════════════════════════════════════ -->
        <div v-show="tabActual === 'acciones'" class="tab-body acciones-tab">
          <p class="acc-hint">Abrí un módulo con <b>{{ empActual?.PER_NOM }}</b> ya seleccionado: ves su historial y podés cargar nuevos registros sin salir de la ficha.</p>

          <!-- Más usadas por este usuario (localStorage) -->
          <template v-if="masUsadas.length">
            <div class="acc-sec-tit">⭐ Más usadas</div>
            <div class="acc-grid">
              <button v-for="a in masUsadas" :key="'f-' + a.key" class="acc-card acc-frec" @click="abrirAbm(a.key)">
                <span class="acc-ic">{{ a.ic }}</span><b>{{ a.label }}</b><small>{{ a.sub }}</small>
              </button>
            </div>
            <div class="acc-sec-tit">Todas las acciones</div>
          </template>

          <div class="acc-grid">
            <button v-for="a in ACCIONES" :key="a.key" class="acc-card" @click="abrirAbm(a.key)">
              <span class="acc-ic">{{ a.ic }}</span><b>{{ a.label }}</b><small>{{ a.sub }}</small>
            </button>
          </div>
        </div>

        <!-- ════════════════════════════════════════════════════
             TAB 1 — DATOS (con sub-pestañas internas)
        ════════════════════════════════════════════════════ -->
        <div v-show="tabActual === 'datos'" class="tab-body-split">
          <div class="sub-tabs">
            <button class="sub-tab" :class="{ act: subTab === 'personal' }" @click="subTab = 'personal'">👤 Personal</button>
            <button class="sub-tab" :class="{ act: subTab === 'laboral' }"  @click="subTab = 'laboral'">💼 Laboral</button>
            <button class="sub-tab" :class="{ act: subTab === 'remun' }"    @click="subTab = 'remun'">💰 Remuneración</button>
            <button class="sub-tab" :class="{ act: subTab === 'obs' }"      @click="subTab = 'obs'">📝 Obs.</button>
          </div>
          <div class="tab-scroll">
          <fieldset :disabled="soloLectura" class="fs-form" v-enter-next>

          <!-- SUB-TAB: DATOS PERSONALES -->
          <div v-show="subTab === 'personal'" class="form-grid-4">

            <!-- Fila 1: Código + Legajo + Jubilado -->
            <!-- Código (PER_COD): clave del empleado, autogenerada y no editable. -->
            <div class="campo">
              <label>Código</label>
              <input :value="form.PER_COD" type="number" readonly disabled />
            </div>
            <div class="campo">
              <label>Nro. Legajo</label>
              <input v-model.number="form.PER_LEG" type="number" />
            </div>
            <label class="campo-check col2-g4" style="margin-top:18px">
              <input type="checkbox" v-model="form.PER_JSN" true-value="S" false-value="N" />
              <span class="chk-lbl">JUBILADO</span>
            </label>

            <!-- Fila 2: Nombre + Sexo -->
            <div class="campo col2-g4">
              <label>Nombre completo <span class="req">*</span></label>
              <input v-model="form.PER_NOM" type="text" placeholder="APELLIDO Nombre" />
            </div>
            <div class="campo">
              <label>Sexo</label>
              <select v-model="form.PER_SEX">
                <option value="F">FEMENINO</option>
                <option value="M">MASCULINO</option>
                <option value="N">NO BINARIO</option>
              </select>
            </div>
            <div></div>

            <!-- Fila 3: Domicilio -->
            <div class="campo col4-g4">
              <label>Domicilio</label>
              <input v-model="form.PER_DOM" type="text" maxlength="50" />
            </div>

            <!-- Fila 4: Localidad + CPA -->
            <div class="campo col3-g4">
              <label>Localidad</label>
              <input v-model="form.PER_LOC" type="text" maxlength="50" />
            </div>
            <div class="campo">
              <label>C.P.A.</label>
              <input v-model="form.PER_CPA" type="text" maxlength="15" />
            </div>

            <!-- Fila 5: 4 fechas + Rentacar -->
            <div class="campo">
              <label>F. Nacimiento</label>
              <input v-model="form.PER_FNA" type="date" />
            </div>
            <div class="campo">
              <label>F. Ingreso</label>
              <input v-model="form.PER_ING" type="date" />
            </div>
            <div class="campo">
              <label>F. Efectivo</label>
              <input v-model="form.PER_FEFECTIVO" type="date" />
            </div>
            <div class="campo">
              <label>F. Baja</label>
              <input v-model="form.PER_BAJ" type="date" />
            </div>
            <label class="campo-check col4-g4">
              <input type="checkbox" v-model="form.PER_RENTACAR" />
              <span class="chk-lbl">ASOCIADO A RENTACAR</span>
            </label>

            <!-- Fila 6: Razón Baja + Almuerza + Con Cargo + Estado -->
            <div class="campo">
              <label>Razón baja</label>
              <input v-model="form.PER_BAJ_RAZON" type="text" maxlength="100" />
            </div>
            <div class="campo">
              <label>Almuerza</label>
              <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                <label class="campo-check">
                  <input type="checkbox" :checked="form.PER_ALM === 'S'"
                    @change="form.PER_ALM = ($event.target as HTMLInputElement).checked ? 'S' : 'N'" />
                  <span class="chk-lbl">Sí</span>
                </label>
                <select v-if="form.PER_ALM === 'S'" v-model.number="form.PER_COMN" @change="onComedorChange" style="flex:1;min-width:100px">
                  <option :value="null">— —</option>
                  <option v-for="c in opciones.comedores" :key="c.COME_COD" :value="c.COME_COD">{{ c.COME_DES }}</option>
                </select>
              </div>
            </div>
            <div class="campo">
              <label>Con cargo</label>
              <label class="campo-check" style="margin-top:4px">
                <input type="checkbox" v-model="form.PER_CAR" true-value="S" false-value="N" :disabled="form.PER_ALM !== 'S'" />
                <span class="chk-lbl">Sí</span>
              </label>
            </div>
            <div class="campo">
              <label>Estado</label>
              <select v-model="form.PER_AOP">
                <option value="A">ACTIVO</option>
                <option value="P">PASIVO</option>
              </select>
            </div>

            <!-- Fila 7: Tipo Doc + Número + CUIL + Estado Civil -->
            <div class="campo">
              <label>Tipo doc.</label>
              <input v-model="form.PER_TDO" type="text" maxlength="3" style="text-transform:uppercase" />
            </div>
            <div class="campo">
              <label>Número</label>
              <input v-model.number="form.PER_NDO" type="number" />
            </div>
            <div class="campo">
              <label>C.U.I.L.</label>
              <input v-model="form.PER_CUI" type="text" maxlength="13" placeholder="xx-xxxxxxxx-x" />
            </div>
            <div class="campo">
              <label>Estado civil</label>
              <select v-model.number="form.PER_ECI">
                <option :value="null">— —</option>
                <option v-for="e in opciones.estadosciviles" :key="e.ECI_COD" :value="e.ECI_COD">{{ e.ECI_DES }}</option>
              </select>
            </div>

            <!-- Fila 8: Teléfonos (2 sub-filas bajo mismo separador) -->
            <div class="sep-label col4-g4">Teléfonos</div>
            <div class="campo">
              <label>Casa</label>
              <input v-model="form.PER_TEL" type="text" maxlength="50" />
            </div>
            <div class="campo col3-g4">
              <label>Celular</label>
              <input v-model="form.PER_CEL" type="text" maxlength="50" />
            </div>
            <div class="campo">
              <label>Emergencias</label>
              <input v-model="form.PER_EME" type="text" maxlength="50" />
            </div>
            <div class="campo">
              <label>Contacto</label>
              <input v-model="form.PER_CONTACTO" type="text" maxlength="50" />
            </div>
            <div class="campo col2-g4">
              <label>Email</label>
              <input v-model="form.PER_EMA" type="email" maxlength="30" />
            </div>

            <!-- Fila 9: Formación Académica -->
            <div class="campo col4-g4">
              <label>Formación Académica</label>
              <input v-model="form.PER_FACAD" type="text" maxlength="100" />
            </div>

            <!-- Fila 10: Padre + Madre + Hobbies -->
            <div class="campo col2-g4">
              <label>Padre</label>
              <input v-model="form.PER_PADRE" type="text" maxlength="30" />
            </div>
            <div class="campo col2-g4">
              <label>Madre</label>
              <input v-model="form.PER_MADRE" type="text" maxlength="30" />
            </div>
            <div class="campo col4-g4">
              <label>Hobbies</label>
              <input v-model="form.PER_HOB" type="text" maxlength="100" />
            </div>

            <!-- Al fondo: Este Empleado tiene PC + Requerimiento Sistemas -->
            <div class="sep-label col4-g4" style="margin-top:6px"></div>
            <label class="campo-check col4-g4">
              <input type="checkbox" v-model="form.PER_TIENEPC" />
              <span class="chk-lbl">Este Empleado tiene PC</span>
            </label>
            <div class="campo-inline col4-g4">
              <label class="campo-check">
                <input type="checkbox" v-model="form.PENDIENTE_SISTEMA" />
                <span class="chk-lbl">Requerimiento Sistemas</span>
              </label>
              <div class="campo" v-if="form.PENDIENTE_SISTEMA" style="flex:1">
                <label>Detalle</label>
                <input v-model="form.REQUIERE_SISTEMA" type="text" maxlength="200" />
              </div>
            </div>
          </div>

          <!-- SUB-TAB: LABORAL — layout 3 columnas igual al sistema viejo -->
          <div v-show="subTab === 'laboral'" class="laboral-layout">

            <!-- ── Columna izquierda ── -->
            <div class="laboral-col">
              <div class="campo">
                <label>Empresa</label>
                <select v-model.number="form.PER_EMP" @change="onEmpresaChange">
                  <option :value="null">— —</option>
                  <option v-for="e in opciones.empresas" :key="e.EMP_COD" :value="e.EMP_COD">{{ e.EMP_NOM }}</option>
                </select>
              </div>
              <div class="campo">
                <label>Contratista</label>
                <select v-model.number="form.PER_CONTRA">
                  <option :value="null">— —</option>
                  <option v-for="c in opciones.contratistas" :key="c.CONT_COD" :value="c.CONT_COD">{{ c.CONT_DET }}</option>
                </select>
              </div>
              <!-- Lugar + MSI en la misma fila -->
              <div class="laboral-lugar-row">
                <div class="campo" style="flex:1">
                  <label>Lugar</label>
                  <select v-model.number="form.PER_LUGAR">
                    <option :value="null">— —</option>
                    <option v-for="l in opciones.lugares" :key="l.LUG_COD" :value="l.LUG_COD">{{ l.LUG_NOM }}</option>
                  </select>
                </div>
                <div class="campo" style="width:80px">
                  <label>MSI</label>
                  <input type="text" maxlength="10" />
                </div>
              </div>
              <!-- Sector + Subsector + Puesto inicial (3 en misma fila) -->
              <div class="sector-row">
                <div class="campo">
                  <label>Sector laboral</label>
                  <select v-model.number="form.PER_SEC" @change="onSectorChange">
                    <option :value="null">— —</option>
                    <option v-for="s in opciones.sectores" :key="s.SEC_COD" :value="s.SEC_COD">{{ s.SEC_DES }}</option>
                  </select>
                </div>
                <div class="campo">
                  <label>Subsector</label>
                  <select v-model.number="form.PER_SUC">
                    <option :value="null">— —</option>
                    <option v-for="s in opciones.subsectores" :key="s.sub_cod" :value="s.sub_cod">{{ s.sub_des }}</option>
                  </select>
                </div>
                <!-- Puesto laboral inicial: solo al dar de alta un empleado nuevo -->
                <div class="campo" v-if="modoNuevo">
                  <label>Puesto laboral inicial</label>
                  <select v-model="puestoInicial">
                    <option value="">— sin asignar —</option>
                    <option v-for="p in catalogoPuestos" :key="p.codigo" :value="String(p.codigo)">{{ p.descripcion }}</option>
                  </select>
                </div>
              </div>
              <!-- Descripción del puesto (renglón propio) -->
              <div class="campo">
                <label>Descripción puesto</label>
                <input v-model="form.PER_SED" type="text" maxlength="50" />
              </div>
              <div class="campo">
                <label>CBU</label>
                <input v-model="form.PER_CBU" type="text" maxlength="30" />
              </div>
              <!-- Jornada + horas -->
              <div class="sector-row">
                <div class="campo">
                  <label>Jornada</label>
                  <select v-model="form.PER_JOM">
                    <option value="">— —</option>
                    <option value="M">Mensual</option>
                    <option value="J">Jornalizado</option>
                  </select>
                </div>
                <div class="campo">
                  <label>Hs. normales/día</label>
                  <input v-model.number="form.PER_HNORMAL" type="number" step="0.5" min="0" />
                </div>
                <div class="campo">
                  <label>Hs. sábado/día</label>
                  <input v-model.number="form.PER_HSABADO" type="number" step="0.5" min="0" />
                </div>
              </div>
            </div>

            <!-- ── Columna derecha ── -->
            <div class="laboral-col">
              <div class="campo">
                <label>Convenio</label>
                <select v-model.number="form.PER_CON">
                  <option :value="null">— —</option>
                  <option v-for="c in opciones.convenios" :key="c.CON_COD" :value="c.CON_COD">{{ c.CON_DES }}</option>
                </select>
              </div>
              <div class="campo">
                <label>Categoría</label>
                <select v-model.number="form.PER_CAT" @change="onCategoriaChange">
                  <option :value="null">— —</option>
                  <option v-for="c in categoriasFiltradas" :key="c.CAT_COD" :value="c.CAT_COD">{{ c.CAT_DES }}</option>
                </select>
              </div>
              <!-- Bloque de sueldos -->
              <div class="sueldos-bloque">
                <div class="sueldos-hdr">Sueldos</div>
                <div class="sueldos-hdr">Bás. Remun.</div>
                <div class="sueldos-hdr">Horas</div>
                <div class="sueldos-hdr">Valor</div>
                <div class="sueldos-hdr">Descuento</div>
                <div class="sueldos-lbl">Remunerativo</div>
                <input v-model.number="form.PER_REMU"      class="sueldos-inp" type="number" step="0.01" min="0" />
                <input v-model.number="form.PER_HORAS"     class="sueldos-inp" type="number" min="0" />
                <input v-model.number="form.PER_SUE"       class="sueldos-inp" type="number" step="0.0001" min="0" />
                <input v-model.number="form.PER_DESCUENTO" class="sueldos-inp" type="number" step="0.01" min="0" max="100" />
                <div class="sueldos-lbl">Bás. NO Remun.</div>
                <input v-model.number="form.PER_NREM"      class="sueldos-inp" type="number" step="0.01" min="0" />
                <div></div><div></div><div></div>
              </div>
            </div>

            <!-- ── Sidebar: checkboxes ── -->
            <div class="laboral-sidebar">
              <label class="campo-check">
                <input type="checkbox" v-model="form.PER_ADI_ENC" @change="onAdEncargadoChange" />
                <span class="chk-lbl">Ad.Encargado</span>
              </label>
              <label class="campo-check">
                <input type="checkbox" v-model="form.PER_ADI_SUS" @change="onAdSustPeligChange" />
                <span class="chk-lbl">Ad.Sust.Pelig.</span>
              </label>
              <label class="campo-check">
                <input type="checkbox" v-model="form.PER_AFILIADO" @change="onAfiliadoChange" />
                <span class="chk-lbl">AFILIADO</span>
              </label>
              <label class="campo-check" style="margin-top:8px">
                <input type="checkbox" v-model="form.PER_PSN" true-value="S" false-value="N" />
                <span class="chk-lbl">Promovido</span>
              </label>
              <label class="campo-check">
                <input type="checkbox" v-model="form.PER_SIN" true-value="S" false-value="N" />
                <span class="chk-lbl">Sindicato</span>
              </label>
            </div>
          </div>

          <!-- SUB-TAB: REMUNERACIÓN — layout igual al sistema viejo -->
          <div v-show="subTab === 'remun'" class="form-grid-4">

            <!-- Fila 1: ANTICIPOS | SUELDO BRUTO | PRESTACIÓN > 6M | Enviar Reporte -->
            <div class="campo" v-if="mostrarSalarios">
              <label>Anticipos</label>
              <div class="remun-calc">{{ new Intl.NumberFormat('es-AR',{minimumFractionDigits:2}).format(form.PER_ANTI || 0) }}</div>
            </div>
            <div class="campo" v-if="mostrarSalarios">
              <label>Sueldo Bruto</label>
              <div class="remun-calc">{{ new Intl.NumberFormat('es-AR',{minimumFractionDigits:2}).format(sueldoBruto) }}</div>
            </div>
            <div class="campo">
              <label>Prestación &gt; 6 meses</label>
              <div class="remun-calc">{{ prestacionTexto }}</div>
            </div>
            <div class="campo">
              <label class="campo-check" style="margin-top:18px">
                <input type="checkbox" v-model="form.PARTE_ENV" />
                <span class="chk-lbl">Enviar Reporte Automático</span>
              </label>
              <select v-if="form.PARTE_ENV" v-model.number="form.PARTE_PRG" style="margin-top:4px">
                <option :value="null">— —</option>
                <option v-for="r in opciones.reloj_envios" :key="r.rdp_cod" :value="r.rdp_cod">{{ r.rdp_des }}</option>
              </select>
            </div>

            <!-- Fila 2: SUELDO NETO | Cobra HE | Valor Hora | GRUPO LABORAL -->
            <div class="campo" v-if="mostrarSalarios">
              <label>Sueldo Neto</label>
              <div class="remun-calc">{{ new Intl.NumberFormat('es-AR',{minimumFractionDigits:2}).format(form.PER_SUE || 0) }}</div>
            </div>
            <div class="campo">
              <label>Cobra Horas Extras</label>
              <label class="campo-check" style="margin-top:4px">
                <input type="checkbox" v-model="form.PER_CHE" true-value="S" false-value="N" />
                <span class="chk-lbl">Sí</span>
              </label>
            </div>
            <div class="campo">
              <label>Valor Hora</label>
              <div class="remun-calc">{{ new Intl.NumberFormat('es-AR',{minimumFractionDigits:2}).format(valorHoraNormal) }}</div>
            </div>
            <div class="campo">
              <label>Grupo Laboral</label>
              <select v-model.number="form.PER_GRU">
                <option :value="null">— —</option>
                <option v-for="g in opciones.reloj_grupos" :key="g.rgr_cod" :value="g.rgr_cod">{{ g.rgr_des }}</option>
              </select>
            </div>

            <!-- Fila 3: Banco Depósito | ENTRADA | Observación -->
            <div class="campo col2-g4" v-if="mostrarBanco">
              <label>Banco depósito</label>
              <select v-model.number="form.PER_BAN" @change="onBancoChange">
                <option :value="null">— —</option>
                <option v-for="b in opciones.bancos" :key="b.CBA_COD" :value="b.CBA_COD">{{ b.CBA_DES }}</option>
              </select>
            </div>
            <div class="campo">
              <label>Entrada</label>
              <input v-model="form.PER_HEN" type="time" />
            </div>
            <div class="campo">
              <label>Observación</label>
              <input v-model="form.PER_SUD" type="text" maxlength="30" />
            </div>

            <!-- Fila 4: Sucursal | Nro. Cuenta | SALIDA | No marca reloj -->
            <div class="campo" v-if="mostrarBanco">
              <label>Sucursal</label>
              <input v-model.number="form.PER_SUC" type="number" />
            </div>
            <div class="campo" v-if="mostrarBanco">
              <label>Nro. Cuenta</label>
              <input v-model="form.PER_CBU_CUENTA" type="text" maxlength="20" />
            </div>
            <div class="campo">
              <label>Salida</label>
              <input v-model="form.PER_HSA" type="time" />
            </div>
            <div class="campo">
              <label class="campo-check" style="margin-bottom:4px">
                <input type="checkbox" v-model="form.PER_NOMARCA" />
                <span class="chk-lbl">NO marca en el reloj</span>
              </label>
              <input v-if="form.PER_NOMARCA" v-model="form.PER_NMRAZON" type="text" maxlength="100" placeholder="Razón..." />
            </div>
          </div>

          <!-- SUB-TAB: OBSERVACIONES -->
          <div v-show="subTab === 'obs'" class="form-grid">
            <div class="campo col2">
              <label>Observaciones generales</label>
              <textarea v-model="form.PER_OBS" rows="8" maxlength="2000"></textarea>
            </div>
          </div>
          </fieldset>
          </div><!-- /tab-scroll -->
        </div>

        <!-- ════════════════════════════════════════════════════
             TAB 2 — FAMILIA
        ════════════════════════════════════════════════════ -->
        <div v-show="tabActual === 'familia'" class="tab-body familia-tab">
          <div v-if="cargandoTab" class="tab-loading">⟳ Cargando...</div>
          <template v-else>

            <!-- Datos del núcleo familiar -->
            <div class="familia-datos">
              <div class="campo">
                <label>Nombre y apellido del cónyuge</label>
                <input v-model="familiaForm.PER_NES" type="text" maxlength="50"
                  class="familia-input-wide" placeholder="—" />
              </div>
              <div class="familia-dos-col">
                <div class="campo">
                  <label>Nombre y apellido del padre</label>
                  <input v-model="familiaForm.PER_PADRE" type="text" maxlength="30" />
                </div>
                <div class="campo">
                  <label>Nombre y apellido de la madre</label>
                  <input v-model="familiaForm.PER_MADRE" type="text" maxlength="30" />
                </div>
              </div>
            </div>

            <!-- Grilla de hijos -->
            <div class="familia-seccion">
              <div class="familia-seccion-header">
                <span>Hijos</span>
                <span class="badge-count">{{ hijosLocal.filter(h => h.PER_NOM.trim()).length }}</span>
              </div>
              <table class="familia-tabla">
                <thead>
                  <tr>
                    <th class="col-ok">Ok</th>
                    <th>Nombre</th>
                    <th class="col-fecha">Nacimiento</th>
                    <th class="col-sit">Situación</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(h, idx) in hijosLocal" :key="idx"
                      :class="{ 'fila-marcada': h._ok }">
                    <td class="col-ok">
                      <input type="checkbox" v-model="h._ok" />
                    </td>
                    <td>
                      <input v-model="h.PER_NOM" type="text" maxlength="30"
                        class="input-celda" placeholder="Nombre y apellido" />
                    </td>
                    <td class="col-fecha">
                      <input v-model="h.PER_FNA" type="date" class="input-celda" />
                    </td>
                    <td class="col-sit">
                      <input v-model="h.PER_SIT" type="text" maxlength="30"
                        class="input-celda" placeholder="situación" />
                    </td>
                  </tr>
                </tbody>
              </table>

              <!-- Botones grilla -->
              <div class="familia-btns">
                <button class="btn-sm btn-fam-agregar" @click="hijosAgregar">＋ Agregar</button>
                <button class="btn-sm btn-fam-eliminar"
                  :disabled="!hijosLocal.some(h => h._ok)"
                  @click="hijosEliminar">✕ Eliminar seleccionados</button>
                <button class="btn-sm btn-fam-guardar"
                  :disabled="hijosGuardando"
                  @click="hijosConfirmar">
                  {{ hijosGuardando ? '...' : '💾 Confirmar modificaciones' }}
                </button>
                <span v-if="hijosMsg" class="familia-msg"
                  :class="hijosMsgOk ? 'fam-ok' : 'fam-err'">{{ hijosMsg }}</span>
              </div>
            </div>

            <!-- Embargo -->
            <div class="familia-embargo">
              <label class="chk-label">
                <input type="checkbox"
                  :checked="familiaForm.PER_EMBSN === 'S'"
                  @change="(e: Event) => familiaForm.PER_EMBSN = (e.target as HTMLInputElement).checked ? 'S' : 'N'" />
                TIENE EMBARGO
              </label>
              <template v-if="familiaForm.PER_EMBSN === 'S'">
                <div class="campo">
                  <label>Nombre del beneficiario</label>
                  <input v-model="familiaForm.PER_EMBNOM" type="text" maxlength="30" />
                </div>
                <div class="campo">
                  <label>CBU del beneficiario</label>
                  <input v-model="familiaForm.PER_EMBCBU" type="text" maxlength="22" />
                </div>
              </template>
            </div>

          </template>
        </div>

        <!-- ════════════════════════════════════════════════════
             TAB 3 — OBRA SOCIAL Y OTROS
        ════════════════════════════════════════════════════ -->
        <div v-show="tabActual === 'obrasocial'" class="tab-body-split">
          <div class="sub-tabs">
            <button class="sub-tab" :class="{ act: subTabOS === 'datos' }"     @click="subTabOS = 'datos'">Datos</button>
            <button class="sub-tab" :class="{ act: subTabOS === 'historial' }" @click="subTabOS = 'historial'">Historial OS</button>
          </div>
          <div class="tab-scroll">
          <fieldset :disabled="soloLectura" class="fs-form" v-enter-next>
          <div v-show="subTabOS === 'datos'" class="form-grid os-grid">
            <div class="campo col2">
              <label>Prestador Médico Predeterminado</label>
              <input v-model="form.PER_PMP" type="text" maxlength="50" />
            </div>
            <div class="campo col2">
              <label>Fecha de Alta</label>
              <input v-model="form.PER_FAS" type="date" />
            </div>
            <div class="campo col2">
              <label>Cod. Obra Social</label>
              <input v-model="form.PER_COS" type="text" maxlength="20" />
            </div>
            <div class="campo col2">
              <label>Nombre Obra Social</label>
              <input v-model="form.OBRASOCIAL" type="text" maxlength="50" />
            </div>
            <div class="campo col2">
              <label>personal.obrasocial</label>
              <div v-if="soloLectura" class="radio-readonly">
                <span :class="form.PER_SIN === 'S' ? 'radio-on' : 'radio-off'">● SI</span>
                <span :class="form.PER_SIN === 'N' ? 'radio-on' : 'radio-off'">● NO</span>
              </div>
              <div v-else class="radio-group">
                <label><input type="radio" v-model="form.PER_SIN" value="S" /> SI</label>
                <label><input type="radio" v-model="form.PER_SIN" value="N" /> NO</label>
              </div>
            </div>
            <div class="campo col2">
              <label>Promedio Último Semestre</label>
              <input v-model.number="form.PER_PRM" type="number" step="0.01" />
            </div>
          </div>
          </fieldset>

          <div v-show="subTabOS === 'historial'">
            <div v-if="cargandoTab" class="tab-loading">⟳ Cargando...</div>
            <div v-else>
              <div v-if="tabData.obrasocial.length === 0" class="tab-empty">Sin historial</div>
              <template v-else>
              <div class="historial-os-titulo">HISTORIAL IMPORTACION PLANILLA COSTO OBRA SOCIAL</div>
              <table class="tab-tabla">
                <thead><tr><th>Obra Social</th><th>Mes</th><th>Año</th><th class="num">Neto</th><th class="num">Aporte</th><th class="num">Diferencia</th></tr></thead>
                <tbody>
                  <tr v-for="r in tabData.obrasocial" :key="r.PEO_ANO + '-' + r.PEO_MES">
                    <td>{{ r.PEO_OBRA }}</td>
                    <td>{{ String(r.PEO_MES).padStart(2,'0') }}</td>
                    <td>{{ r.PEO_ANO }}</td>
                    <td class="num">{{ formatNum(r.PEO_NET) }}</td>
                    <td class="num">{{ formatNum(r.PEO_DEB) }}</td>
                    <td class="num" :class="(r.PEO_DIF||0)<0 ? 'neg':'pos'">{{ formatNum(r.PEO_DIF) }}</td>
                  </tr>
                </tbody>
              </table>
              </template>
            </div>
          </div>
          </div><!-- /tab-scroll -->
        </div>

        <!-- ════════════════════════════════════════════════════
             TAB 4 — FOTOS
        ════════════════════════════════════════════════════ -->
        <div v-show="tabActual === 'fotos'" class="tab-body fotos-tab">
          <!-- Código y Nombre -->
          <div class="fotos-info">
            <div class="fotos-info-row">
              <span class="fotos-label">Código</span>
              <span class="fotos-value">{{ empActual?.PER_COD }}</span>
            </div>
            <div class="fotos-info-row">
              <span class="fotos-label">Nombre</span>
              <span class="fotos-value fotos-nombre-val">{{ empActual?.PER_NOM }}</span>
            </div>
          </div>

          <!-- Recuadro FOTOGRAFÍA -->
          <div class="fotos-frame">
            <div class="fotos-frame-title">FOTOGRAFÍA</div>
            <div class="fotos-img-wrap">
              <img v-if="fotoUrl" :src="fotoUrl" :alt="empActual?.PER_NOM" class="foto-empleado" />
              <div v-else class="foto-sin-foto">
                <span>👤</span>
                <p>Sin foto registrada</p>
              </div>
            </div>
          </div>

          <!-- Botones -->
          <div class="fotos-btns">
            <label class="btn-sm btn-foto-agregar">
              📷 Agregar Fotografía
              <input type="file" accept="image/*" style="display:none"
                @change="onAgregarFoto" :disabled="fotoSubiendo" />
            </label>
            <button class="btn-sm btn-foto-eliminar"
              @click="onEliminarFoto"
              :disabled="!fotoUrl || fotoSubiendo">
              🗑️ Eliminar Foto
            </button>
            <span v-if="fotoSubiendo" class="fotos-msg">⟳ Procesando...</span>
            <span v-if="fotoMsg" class="fotos-msg" :class="fotoMsgOk ? 'fotos-ok' : 'fotos-err'">{{ fotoMsg }}</span>
          </div>
        </div>

        <!-- ════════════════════════════════════════════════════
             TAB 5 — PUESTOS / CALIFICACIONES
        ════════════════════════════════════════════════════ -->
        <div v-show="tabActual === 'puestos'" class="tab-body">
          <div v-if="cargandoTab" class="tab-loading">⟳ Cargando...</div>
          <div v-else>
            <div style="display:flex; justify-content:center; gap:10px; margin-bottom:8px">
              <button class="btn-sm btn-reactivar" @click="imprimirPuestosPDF" :disabled="!tabData.puestos.puestos?.length">
                🖨️ Imprimir Puestos Asignados
              </button>
              <button class="btn-sm btn-reactivar" @click="imprimirHojaEvaluacion">
                📝 Imprimir Hoja de Evaluación
              </button>
            </div>
            <div class="tab-section-header" style="display:flex;align-items:center;justify-content:space-between">
              <span>PUESTOS ASIGNADOS</span>
              <button class="btn-sm btn-reactivar" @click="abrirAsignarPuesto">➕ Asignar Puesto</button>
            </div>
            <div v-if="tabData.puestos.puestos?.length === 0" class="tab-empty">Sin puestos registrados</div>
            <table v-else class="tab-tabla">
              <thead><tr><th>Descripción del Puesto</th><th>Desde</th><th>Hasta</th><th style="text-align:center">Estado Actual</th><th>Reporta a</th><th></th></tr></thead>
              <tbody>
                <tr v-for="p in tabData.puestos.puestos" :key="p.codigo">
                  <td>{{ p.puesto }} <button class="btn-ojo" title="Ver descripción completa del puesto" @click="verPuesto(p.codigo)">👁️</button></td>
                  <td>{{ formatFecha(p.desde) }}</td>
                  <td>{{ formatFecha(p.hasta) }}</td>
                  <td style="text-align:center">
                    <button :class="p.activo ? 'pue-badge act' : 'pue-badge baj'"
                            :title="p.activo ? 'Clic para pasar a Inactivo' : 'Clic para pasar a Activo'"
                            :disabled="puestoProc" @click="toggleActivoPuesto(p)">{{ p.activo ? 'Activo' : 'Inactivo' }}</button>
                  </td>
                  <td>{{ p.reporta }}</td>
                  <td style="text-align:center; white-space:nowrap">
                    <button class="btn-sm btn-fam-eliminar" title="Eliminar definitivamente este puesto del empleado"
                            :disabled="puestoProc" @click="eliminarPuesto(p)">🗑 Eliminar</button>
                  </td>
                </tr>
              </tbody>
            </table>

            <div class="tab-section-header" style="margin-top:16px">CALIFICACION POR PUESTO</div>
            <div v-if="tabData.puestos.calificaciones?.length === 0" class="tab-empty">Sin calificaciones</div>
            <table v-else class="tab-tabla">
              <thead><tr><th>Fecha</th><th>Resultado</th><th>Aspectos a Mejorar</th><th>Positiva</th><th>Cuil del Responsable</th></tr></thead>
              <tbody>
                <tr v-for="c in tabData.puestos.calificaciones" :key="c.CAL_FEC + c.CAL_PUE"
                  :class="{ 'cal-sel': calSeleccionada === c }"
                  @click="calSeleccionada = calSeleccionada === c ? null : c"
                  style="cursor:pointer">
                  <td>{{ formatFecha(c.CAL_FEC) }}</td>
                  <td>{{ c.CAL_RES }}</td>
                  <td>{{ c.CAL_NEG }}</td>
                  <td>{{ c.CAL_POS }}</td>
                  <td>{{ c.CAL_CUIRES }}</td>
                </tr>
              </tbody>
            </table>
            <div class="familia-btns" style="margin-top:10px">
              <button class="btn-sm btn-fam-eliminar"
                :disabled="!calSeleccionada || calEliminando"
                @click="calificacionEliminar">
                🗑️ {{ calEliminando ? 'Eliminando...' : 'Eliminar Calificación' }}
              </button>
              <span v-if="calMsg" :class="calMsg.startsWith('Error') ? 'fotos-err' : 'fotos-ok'" style="padding:4px 10px;border-radius:4px;font-size:12px">{{ calMsg }}</span>
            </div>
          </div>
        </div>

        <!-- ════════════════════════════════════════════════════
             TAB 6 — GESTIÓN UNIFORME / EPP entrega
        ════════════════════════════════════════════════════ -->
        <div v-show="tabActual === 'uniforme'" class="tab-body">
          <div v-if="cargandoTab" class="tab-loading">⟳ Cargando...</div>
          <div v-else>

            <!-- Layout dos paneles -->
            <div class="uniforme-layout">

              <!-- Panel izquierdo: fechas agrupadas -->
              <div class="uniforme-fechas">
                <div class="tab-section-header" style="margin:0 0 6px">
                  Entregas <span class="badge-count">{{ ropaFechasUnicas.length }}</span>
                </div>
                <div class="uniforme-fechas-scroll">
                  <div v-if="ropaFechasUnicas.length === 0" class="tab-empty">Sin entregas</div>
                  <table v-else class="tab-tabla uniforme-tabla-fechas">
                    <thead><tr><th>Fecha</th><th style="text-align:center">Stock</th></tr></thead>
                    <tbody>
                      <tr v-for="f in ropaFechasUnicas" :key="f.fecha"
                          :class="{ 'fila-sel': ropaFechaSeleccionada === f.fecha }"
                          @click="ropaFechaSeleccionada = f.fecha"
                          style="cursor:pointer">
                        <td>{{ formatFecha(f.fecha) }}</td>
                        <td style="text-align:center">
                          <span v-if="f.tieneStock" style="color:#16a34a;font-weight:700">✔</span>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>

              <!-- Panel derecho: detalle de la fecha seleccionada -->
              <div class="uniforme-detalle">
                <div class="tab-section-header" style="margin:0 0 6px">
                  {{ ropaFechaSeleccionada ? 'Entrega del ' + formatFecha(ropaFechaSeleccionada) : 'Seleccioná una fecha' }}
                </div>
                <div class="uniforme-detalle-scroll">
                <div v-if="!ropaFechaSeleccionada" class="tab-empty">← Hacé click en una fecha</div>
                <table v-else class="tab-tabla">
                  <thead>
                    <tr>
                      <th>Cant.</th>
                      <th>Descripción del EPP / Uniforme</th>
                      <th>Motivo</th>
                      <th>Marca</th>
                      <th>Talle</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(r, i) in ropaDetalle" :key="i">
                      <td class="num">{{ r.cantidad }}</td>
                      <td>{{ r.descripcion?.trim() }}</td>
                      <td>{{ r.motivo?.trim() }}</td>
                      <td>{{ (r.marca?.trim() || r.marca2?.trim()) }}</td>
                      <td>{{ (r.talle?.trim() || r.talle2?.trim()) }}</td>
                    </tr>
                  </tbody>
                </table>
                </div><!-- /uniforme-detalle-scroll -->
              </div>

            </div><!-- /uniforme-layout -->

            <!-- Barra inferior: rango de fechas + imprimir -->
            <div class="uniforme-footer">
              <span class="uniforme-footer-label">Ropa entregada:</span>
              <span>Desde</span>
              <input type="date" v-model="ropaDesde" class="input-fecha-ropa">
              <span>Hasta</span>
              <input type="date" v-model="ropaHasta" class="input-fecha-ropa">
              <button class="btn-sm btn-editar" @click="ropaConsultar">Consultar</button>
              <button class="btn-sm btn-reactivar" style="margin-left:auto"
                      @click="imprimirConstanciaRopa"
                      :disabled="tabData.ropa.length === 0">
                🖨️ Imprimir Constancia
              </button>
            </div>

          </div>
        </div>

        <!-- ════════════════════════════════════════════════════
             TAB 7 — CAPACITACIÓN RECIBIDA
             Dos grillas apiladas, ambas visibles a la vez (réplica FoxPro):
               · Superior  = capacitaciones recibidas (master, seleccionable)
               · Inferior = Documentos Digitales de la fila seleccionada (detail)
             La fila seleccionada dispara capSeleccionar() → carga de documentos.
        ════════════════════════════════════════════════════ -->
        <div v-show="tabActual === 'capacitacion'" class="tab-body">
          <div v-if="cargandoTab" class="tab-loading">⟳ Cargando...</div>
          <div v-else class="capacitacion-layout">

            <!-- Grilla superior: capacitaciones recibidas -->
            <div class="cap-grid cap-grid-cursos">
              <div class="tab-section-header" style="margin:0 0 6px">
                Cursos y capacitaciones
                <span class="badge-count">{{ tabData.capacitaciones.length }}</span>
              </div>
              <div class="cap-grid-scroll">
                <div v-if="tabData.capacitaciones.length === 0" class="tab-empty">Sin capacitaciones registradas</div>
                <table v-else class="tab-tabla cap-tabla">
                  <thead>
                    <tr>
                      <th style="width:70px">Código</th>
                      <th style="width:95px">Fecha</th>
                      <th>Capacitación</th>
                      <th style="width:200px">Disertante</th>
                      <th style="width:130px">Duración</th>
                      <th style="width:200px">Objetivo</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(c, i) in tabData.capacitaciones" :key="i"
                        :class="{ 'fila-sel': capSeleccionada === i }"
                        @click="capSeleccionar(i)"
                        style="cursor:pointer">
                      <td class="num">{{ c.codigo }}</td>
                      <td>{{ formatFecha(c.fecha) }}</td>
                      <td>{{ c.nombre?.trim() }}</td>
                      <td>{{ c.disertante?.trim() }}</td>
                      <td>{{ c.duracion?.trim() }}</td>
                      <td>{{ c.objetivo?.trim() }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Grilla inferior: documentos digitales de la capacitación seleccionada -->
            <div class="cap-grid cap-grid-docs">
              <div class="tab-section-header" style="margin:0 0 6px">
                Documentos Digitales
                <span v-if="capDetalleSel" style="font-weight:400;font-size:12px;color:#6b7280">
                  — {{ capDetalleSel.nombre?.trim() }}
                </span>
              </div>
              <div class="cap-grid-scroll">
                <table class="tab-tabla cap-tabla">
                  <thead>
                    <tr>
                      <th style="width:36px;text-align:center">OK</th>
                      <th style="width:50px">N#</th>
                      <th style="width:90px">TIPO</th>
                      <th style="width:150px">DETALLE TIPO</th>
                      <th>NOMBRE</th>
                      <th style="width:55px">EXT</th>
                      <th style="width:95px">FECHA</th>
                      <th style="width:130px">CREADO</th>
                      <th style="width:160px">OBSERVACIONES</th>
                      <th style="width:44px;text-align:center">VER</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(d, i) in capDocumentos" :key="i">
                      <td style="text-align:center"><span style="color:#16a34a;font-weight:700">✔</span></td>
                      <td class="num">{{ d.nro }}</td>
                      <td>{{ d.tipo?.trim() }}</td>
                      <td>{{ d.detalle?.trim() }}</td>
                      <td>{{ d.nombre?.trim() }}</td>
                      <td>{{ d.ext?.trim() }}</td>
                      <td>{{ formatFecha(d.fecha) }}</td>
                      <td>{{ d.creado?.trim() }}</td>
                      <td>{{ d.observaciones?.trim() }}</td>
                      <td style="text-align:center"><button class="btn-ojo" title="Visualizar documento" @click="capDocVer(d)">👁️</button></td>
                    </tr>
                    <tr v-if="capDocumentos.length === 0">
                      <td colspan="10" class="tab-empty" style="border:none">
                        {{ capDocsCargando ? '⟳ Cargando documentos...'
                           : (capDetalleSel ? 'Sin documentos digitales' : 'Seleccioná una capacitación') }}
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

          </div>
        </div>

        <!-- ════════════════════════════════════════════════════
             TAB 8 — LICENCIA DE CONDUCIR
        ════════════════════════════════════════════════════ -->
        <div v-show="tabActual === 'licencia'" class="tab-body">
          <fieldset :disabled="soloLectura" class="fs-form" v-enter-next>
            <div class="lic-layout">
              <!-- Columna izquierda: los 3 carnets -->
              <div class="lic-carnets">
                <div v-for="n in 3" :key="n" class="lic-carnet">
                  <div class="lic-carnet-tag">CARNET {{ n }}</div>
                  <div class="lic-carnet-campos">
                    <div class="lic-row">
                      <label>Nro. de Licencia:</label>
                      <input v-model="form['PER_LN'+n]" type="text" maxlength="12" />
                      <button type="button" class="btn-sm btn-fam-eliminar lic-btn-elim"
                              @click="eliminarCarnet(n)">Eliminar Carnet</button>
                    </div>
                    <div class="lic-row">
                      <label>Carnet de Conducir:</label>
                      <select v-model="form['PER_LC'+n]" class="lic-select" :title="carnetDesc(form['PER_LC'+n])">
                        <option value="">—</option>
                        <option v-for="c in opciones.carnetcategorias" :key="c.cod" :value="c.cod">{{ c.det }}</option>
                      </select>
                    </div>
                    <div class="lic-row">
                      <label>Fecha de Vencimiento:</label>
                      <input v-model="form['PER_LF'+n]" type="date" :class="{ 'lic-vencida': licVencida(form['PER_LF'+n]) }" />
                    </div>
                  </div>
                </div>
              </div>

              <!-- Columna derecha: foto -->
              <div class="lic-foto">
                <img v-if="fotoUrl" :src="fotoUrl" alt="Foto" />
                <div v-else class="lic-foto-vacia">Sin foto</div>
              </div>
            </div>

            <!-- Datos médicos / observaciones -->
            <div class="lic-medico">
              <div class="lic-medico-row">
                <label>Sangre Factor:</label>
                <input v-model="form.PER_LSF" type="text" maxlength="1" class="lic-mini" />
                <label>Sangre Grupo:</label>
                <input v-model="form.PER_LSG" type="text" maxlength="1" class="lic-mini" />
                <label>Donante S/N:</label>
                <input v-model="form.PER_LDO" type="text" maxlength="1" class="lic-mini" />
              </div>
              <div class="lic-row lic-row-ancho">
                <label>Observaciones:</label>
                <input v-model="form.PER_LOB" type="text" maxlength="40" />
              </div>
              <div class="lic-row lic-row-ancho">
                <label>Restricciones:</label>
                <input v-model="form.PER_LRE" type="text" maxlength="40" />
              </div>
            </div>
          </fieldset>
        </div>

        <!-- ════════════════════════════════════════════════════
             TAB 9 — EXÁMENES MÉDICOS
        ════════════════════════════════════════════════════ -->
        <div v-show="tabActual === 'examenes'" class="tab-body">
          <div v-if="cargandoTab" class="tab-loading">⟳ Cargando...</div>
          <div v-else class="examenes-layout">

            <!-- Grilla superior: lista de exámenes (master) -->
            <div class="exa-grid">
              <div class="tab-section-header" style="margin:0 0 6px">
                Exámenes y certificados
                <span class="badge-count">{{ tabData.examenes.length }}</span>
              </div>
              <div class="exa-grid-scroll">
                <div v-if="tabData.examenes.length === 0" class="tab-empty">Sin exámenes registrados</div>
                <table v-else class="tab-tabla exa-tabla">
                  <thead>
                    <tr>
                      <th style="width:60px">Control</th>
                      <th>Tipo de Control</th>
                      <th style="width:90px">Fecha</th>
                      <th style="width:90px">Próximo</th>
                      <th style="width:60px">Médico</th>
                      <th style="width:180px">Responsable</th>
                      <th style="width:60px">Notas</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(e, i) in tabData.examenes" :key="e.id"
                        :class="{ 'fila-sel': examenSeleccionado === i }"
                        @click="examenSeleccionar(i)" style="cursor:pointer">
                      <td class="num">{{ e.control }}</td>
                      <td>{{ e.tipo }}</td>
                      <td>{{ formatFecha(e.fecha) }}</td>
                      <td :class="vencido(e.proximo) ? 'vencido' : ''">{{ formatFecha(e.proximo) }}</td>
                      <td class="num">{{ e.medico_cod }}</td>
                      <td>{{ e.responsable }}</td>
                      <td>{{ e.notas?.trim() ? 'Memo' : '' }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Panel de detalle del examen seleccionado -->
            <div class="exa-detalle">
              <div class="exa-detalle-titulo">{{ examenEsCertificado ? 'CERTIFICADO' : 'EXAMEN' }}</div>
              <div v-if="!examenDetalle" class="tab-empty">Seleccioná un examen</div>
              <template v-else>
                <div class="exa-campos">
                  <div class="exa-row"><label>Fecha Examen:</label>
                    <span class="exa-val">{{ formatFecha(examenDetalle.fecha) }}</span></div>
                  <div v-if="!examenEsCertificado" class="exa-row"><label>Próximo:</label>
                    <span class="exa-val">{{ formatFecha(examenDetalle.proximo) }}</span></div>
                  <div class="exa-row"><label>Tipo de Examen:</label>
                    <span class="exa-val">{{ examenDetalle.tipo }}</span></div>
                  <div class="exa-row"><label>Enfermedad:</label>
                    <span class="exa-val">{{ examenEnfermedad(examenDetalle) }}</span></div>
                </div>
                <div class="exa-notas">
                  <label>Notas Médicas:</label>
                  <textarea readonly :value="examenDetalle.notas?.trim()"></textarea>
                </div>
              </template>
            </div>

            <!-- Grilla inferior: documentos digitales del examen -->
            <div class="exa-docs">
              <div class="tab-section-header" style="margin:0 0 6px">Documentos Digitales actuales</div>
              <div class="exa-docs-scroll">
                <table class="tab-tabla exa-tabla">
                  <thead>
                    <tr>
                      <th style="width:36px;text-align:center">OK</th>
                      <th style="width:50px">N#</th>
                      <th style="width:90px">TIPO</th>
                      <th style="width:150px">DETALLE TIPO</th>
                      <th>NOMBRE</th>
                      <th style="width:55px">EXT</th>
                      <th style="width:90px">FECHA</th>
                      <th style="width:120px">CREADO</th>
                      <th style="width:140px">OBSERVACIONES</th>
                      <th style="width:120px">USUARIO</th>
                      <th style="width:44px;text-align:center">VER</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(d, i) in examenDocs" :key="i">
                      <td style="text-align:center"><span style="color:#16a34a;font-weight:700">✔</span></td>
                      <td class="num">{{ d.nro }}</td>
                      <td>{{ d.tipo?.trim() }}</td>
                      <td>{{ d.detalle?.trim() }}</td>
                      <td>{{ d.nombre?.trim() }}</td>
                      <td>{{ d.ext?.trim() }}</td>
                      <td>{{ formatFecha(d.fecha) }}</td>
                      <td>{{ d.creado?.trim() }}</td>
                      <td>{{ d.observaciones?.trim() }}</td>
                      <td>{{ d.usuario?.trim() }}</td>
                      <td style="text-align:center"><button class="btn-ojo" title="Visualizar documento" @click="examenDocVer(d)">👁️</button></td>
                    </tr>
                    <tr v-if="examenDocs.length === 0">
                      <td colspan="11" class="tab-empty" style="border:none">
                        {{ examenDocsCargando ? '⟳ Cargando documentos...'
                           : (examenDetalle ? 'Sin documentos digitales' : 'Seleccioná un examen') }}
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

          </div>
        </div>

        <!-- ════════════════════════════════════════════════════
             TAB 10 — HISTORIAL DE CAMBIOS
        ════════════════════════════════════════════════════ -->
        <div v-show="tabActual === 'historial'" class="tab-body">
          <div v-if="cargandoTab" class="tab-loading">⟳ Cargando...</div>
          <div v-else class="hist-wrap">
            <div class="tab-section-header" style="margin:0 0 6px">Historial de cambios
              <span class="badge-count">{{ historialFiltrado.length }}</span>
            </div>

            <div class="hist-layout">
              <!-- Grilla izquierda (master) -->
              <div class="hist-grid">
                <div class="hist-grid-scroll">
                  <div v-if="historialFiltrado.length === 0" class="tab-empty">Sin historial</div>
                  <table v-else class="tab-tabla hist-tabla">
                    <thead>
                      <tr>
                        <th style="width:36px;text-align:center">OK</th>
                        <th style="width:130px">Fecha</th>
                        <th>Usuario</th>
                        <th>Terminal</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="(h, i) in historialFiltrado" :key="i"
                          :class="{ 'fila-sel': histSeleccionado === i }"
                          @click="histSeleccionado = i" style="cursor:pointer">
                        <td style="text-align:center">
                          <input type="checkbox" :checked="histMarcados.has(histKey(h))"
                                 @click.stop="histToggleMarca(h)" />
                        </td>
                        <td style="white-space:nowrap">{{ formatFechaHora(h.hla_fec) }}</td>
                        <td>{{ h.hla_usu?.trim() }}</td>
                        <td>{{ h.hla_ter?.trim() }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>

              <!-- Panel derecho: cambios + observaciones generales -->
              <div class="hist-panel">
                <div class="hist-cambios">
                  <textarea readonly :value="historialDetalle?.hla_cam ?? ''"
                            placeholder="Seleccioná un registro para ver los cambios"></textarea>
                </div>
                <div class="hist-obs">
                  <label>Observaciones Generales:</label>
                  <textarea readonly :value="empActual?.PER_OBS?.trim() ?? ''"></textarea>
                </div>
              </div>
            </div>

            <!-- Barra inferior: eliminar + filtro de fechas -->
            <div class="hist-footer">
              <button class="btn-sm btn-fam-eliminar"
                      :disabled="histEliminando || histMarcados.size === 0"
                      @click="eliminarHistorial">
                🗑️ {{ histEliminando ? 'Eliminando...' : 'Eliminar Historial' }}
              </button>
              <span style="margin-left:auto">Imprimir desde</span>
              <input type="date" v-model="histDesde" class="input-fecha-ropa" />
              <span>hasta</span>
              <input type="date" v-model="histHasta" class="input-fecha-ropa" />
              <button class="btn-sm btn-reactivar"
                      :disabled="historialFiltrado.length === 0" @click="imprimirHistorialPDF">
                🖨️ Imprimir
              </button>
            </div>
          </div>
        </div>

        <!-- ════════════════════════════════════════════════════
             TAB 11 — DOCUMENTACIÓN
        ════════════════════════════════════════════════════ -->
        <div v-show="tabActual === 'documentos'" class="tab-body">
          <div v-if="cargandoTab" class="tab-loading">⟳ Cargando...</div>
          <div v-else>
            <div class="tab-section-header">Documentación digital
              <span class="badge-count">{{ documentosFiltrados.length }}</span>
            </div>

            <!-- Filtro por detalle tipo + acciones -->
            <div class="doc-barra">
              <span class="doc-barra-lbl">Filtro:</span>
              <input v-model="docFiltro" type="text" class="doc-filtro"
                     placeholder="Filtrar por detalle tipo..." />
              <button class="btn-sm btn-reactivar" style="margin-left:auto"
                      :disabled="docSeleccionado == null" @click="documentoVisualizar">
                👁️ Visualizar Documentación
              </button>
              <button class="btn-sm btn-fam-eliminar"
                      :disabled="docSeleccionado == null" @click="documentoEliminar">
                🗑️ Eliminar
              </button>
              <button class="btn-sm btn-editar"
                      :disabled="docGuardando || tabData.documentos.length === 0"
                      @click="documentosConfirmar">
                💾 {{ docGuardando ? 'Guardando...' : 'Confirmar Cambio' }}
              </button>
              <span v-if="docMsg" :class="docMsg.startsWith('Error') || docMsg.startsWith('No se') ? 'fotos-err' : 'fotos-ok'"
                    style="padding:4px 10px;border-radius:4px;font-size:12px">{{ docMsg }}</span>
            </div>

            <div v-if="tabData.documentos.length === 0" class="tab-empty">Sin documentos digitales</div>
            <div v-else class="doc-grid-scroll">
            <table class="tab-tabla doc-tabla">
              <thead>
                <tr>
                  <th style="width:36px;text-align:center">OK</th>
                  <th style="width:50px">N#</th>
                  <th style="width:90px">TIPO</th>
                  <th style="width:160px">DETALLE TIPO</th>
                  <th>NOMBRE</th>
                  <th style="width:55px">EXT</th>
                  <th style="width:150px">FECHA</th>
                  <th style="width:120px">CREADO</th>
                  <th style="width:150px">OBSERVACIONES</th>
                  <th style="width:130px">USUARIO</th>
                  <th style="width:44px;text-align:center">VER</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="d in documentosFiltrados" :key="d.id"
                    :class="{ 'fila-sel': docSeleccionado === d.id }"
                    @click="docSeleccionado = d.id" style="cursor:pointer">
                  <td style="text-align:center">
                    <input type="radio" :value="d.id" v-model="docSeleccionado" @click.stop />
                  </td>
                  <td class="num">{{ d.nro }}</td>
                  <td>{{ d.tipo?.trim() }}</td>
                  <td>{{ d.detalle?.trim() }}</td>
                  <td>{{ d.nombre?.trim() }}</td>
                  <td>{{ d.ext?.trim() }}</td>
                  <td><input v-model="d.fecha" type="date" class="input-celda" @click.stop /></td>
                  <td>{{ d.creado?.trim() }}</td>
                  <td>{{ d.observaciones?.trim() }}</td>
                  <td>{{ d.usuario?.trim() }}</td>
                  <td style="text-align:center"><button class="btn-ojo" title="Visualizar documento" @click.stop="docFilaVer(d)">👁️</button></td>
                </tr>
              </tbody>
            </table>
            </div><!-- /doc-grid-scroll -->

            <!-- Panel: agregar nueva documentación -->
            <div class="doc-agregar">
              <div class="doc-agregar-tag">AGREGAR DOCUMENTO</div>
              <div class="doc-agregar-campos">
                <div class="doc-ag-row">
                  <label>Tipo Documento:</label>
                  <select v-model="docNuevo.tipo" class="doc-ag-tipo">
                    <option value="">— Seleccionar —</option>
                    <option v-for="t in opciones.tiposdocumento" :key="t.cod" :value="t.cod">
                      {{ t.cod }} - {{ t.det }}
                    </option>
                  </select>
                </div>
                <div class="doc-ag-row">
                  <label>Archivo Origen:</label>
                  <input ref="docFileInput" type="file" @change="docArchivoSel"
                         accept=".pdf,.jpg,.jpeg,.png,.xls,.xlsx,.doc,.docx" />
                  <button class="btn-sm btn-editar" :disabled="!docNuevo.archivo" @click="docArchivoVer">👁️ Ver</button>
                </div>
                <div class="doc-ag-row">
                  <label>Observación:</label>
                  <input v-model="docNuevo.observaciones" type="text" maxlength="60" class="doc-ag-obs" />
                </div>
                <div class="doc-ag-row">
                  <label>Fecha del Documento:</label>
                  <input v-model="docNuevo.fecha" type="date" class="doc-ag-fecha" />
                  <button class="btn-sm btn-reactivar" :disabled="docAgregando" @click="documentoAgregar">
                    {{ docAgregando ? 'Subiendo...' : '✔ Aceptar' }}
                  </button>
                  <span v-if="docAgMsg" :class="docAgMsg.startsWith('Error') || docAgMsg.startsWith('Falta') ? 'fotos-err' : 'fotos-ok'"
                        style="padding:4px 10px;border-radius:4px;font-size:12px">{{ docAgMsg }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>


        <!-- ════════════════════════════════════════════════════
             TAB 13 — PERSONAL A CARGO
        ════════════════════════════════════════════════════ -->
        <div v-show="tabActual === 'acargo'" class="tab-body">
          <div v-if="cargandoTab" class="tab-loading">⟳ Cargando...</div>
          <div v-else class="acargo-wrap">
            <div class="acargo-titulo">MARCAR LOS EMPLEADOS QUE SON PERSONAL A CARGO
              <span class="badge-count">{{ acargoAsignados.size }}</span>
            </div>

            <div class="acargo-layout">
              <!-- Grilla de empleados con checkbox -->
              <div class="acargo-grid">
                <div class="acargo-filtro-row">
                  <input v-model="acargoFiltro" type="text" class="doc-filtro"
                         placeholder="Filtrar por nombre o código..." />
                </div>
                <div class="acargo-grid-scroll">
                  <table class="tab-tabla acargo-tabla">
                    <thead>
                      <tr>
                        <th style="width:50px;text-align:center">Elegir</th>
                        <th style="width:80px">CÓDIGO</th>
                        <th>EMPLEADO</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="e in acargoFiltrados" :key="e.cod"
                          :class="{ 'acargo-marcado': acargoAsignados.has(e.cod) }"
                          @click="acargoToggle(e.cod)" style="cursor:pointer">
                        <td style="text-align:center">
                          <input type="checkbox" :checked="acargoAsignados.has(e.cod)"
                                 @click.stop="acargoToggle(e.cod)" />
                        </td>
                        <td class="num">{{ e.cod }}</td>
                        <td>{{ e.nom }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>

              <!-- Botón informe a la derecha -->
              <div class="acargo-side">
                <button class="btn-sm btn-reactivar acargo-informe" @click="imprimirAcargoPDF">
                  📋 INFORME COMPLETO DE PERSONAL A CARGO
                </button>
              </div>
            </div>

            <!-- Barra inferior: grabar -->
            <div class="acargo-footer">
              <button class="btn-sm btn-editar" :disabled="acargoGuardando" @click="acargoGuardar">
                💾 {{ acargoGuardando ? 'Guardando...' : 'Grabar Personal a Cargo' }}
              </button>
              <span v-if="acargoMsg" :class="acargoMsg.startsWith('Error') ? 'fotos-err' : 'fotos-ok'"
                    style="padding:4px 10px;border-radius:4px;font-size:12px">{{ acargoMsg }}</span>
            </div>
          </div>
        </div>

        <!-- ════════════════════════════════════════════════════
             TAB 14 — VALORES HORAS
        ════════════════════════════════════════════════════ -->
        <div v-show="tabActual === 'valorhoras'" class="tab-body">
          <div class="vh-panel">
            <!-- Costo Hora laboral (calculado) -->
            <div class="vh-seccion">
              <div class="vh-titulo">Costo Hora laboral :</div>
              <div class="vh-fila">
                <label>Extra al 50%</label>
                <span class="vh-box">{{ vhNum(valorHoraNormal * 1.5) }}</span>
              </div>
              <div class="vh-fila">
                <label>Extra al 100%</label>
                <span class="vh-box">{{ vhNum(valorHoraNormal * 2) }}</span>
              </div>
              <div class="vh-fila">
                <label>Nocturna</label>
                <span class="vh-box">{{ vhNum(valorHoraNormal * 1.1333) }}</span>
              </div>
              <div class="vh-fila">
                <label>Adicional Viajes</label>
                <span class="vh-box">{{ vhNum(opciones.viaticos_dia) }}</span>
              </div>
            </div>

            <!-- Cantidad de horas normales (editable) -->
            <div class="vh-seccion">
              <div class="vh-titulo">Cantidad de Horas consideradas como Normales (No extras)</div>
              <div class="vh-fila">
                <label>De Lunes a Viernes</label>
                <span v-if="soloLectura" class="vh-box">{{ vhNum(form.PER_HNORMAL) }}</span>
                <input v-else v-model.number="form.PER_HNORMAL" type="number" step="0.5" min="0" class="vh-box vh-edit" />
                <span class="vh-hs">Hs</span>
              </div>
              <div class="vh-fila">
                <label>Sábados</label>
                <span v-if="soloLectura" class="vh-box">{{ vhNum(form.PER_HSABADO) }}</span>
                <input v-else v-model.number="form.PER_HSABADO" type="number" step="0.5" min="0" class="vh-box vh-edit" />
                <span class="vh-hs">Hs</span>
              </div>
            </div>
          </div>
        </div>

        <!-- ════════════════════════════════════════════════════
             TAB 15 — FALTAS
        ════════════════════════════════════════════════════ -->
        <div v-show="tabActual === 'faltas'" class="tab-body">
          <div v-if="cargandoTab" class="tab-loading">⟳ Cargando...</div>
          <div v-else class="faltas-wrap">
            <div class="tab-section-header" style="margin:0 0 6px">Faltas y licencias
              <span class="badge-count">{{ tabData.faltas.length }}</span>
            </div>

            <!-- Grilla superior: faltas (verde = con doc, rojo = sin doc) -->
            <div class="faltas-grid">
              <div class="faltas-grid-scroll">
                <div v-if="tabData.faltas.length === 0" class="tab-empty">Sin faltas registradas</div>
                <table v-else class="tab-tabla faltas-tabla">
                  <thead>
                    <tr>
                      <th style="width:45px">Lic.</th>
                      <th style="width:230px">Detalle de la Falta Día</th>
                      <th style="width:90px">F.Desde</th>
                      <th style="width:90px">F.Hasta</th>
                      <th>Observaciones</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(f, i) in tabData.faltas" :key="f.id"
                        :class="[Number(f.con_doc) === 1 ? 'falta-verde' : 'falta-roja', { 'fila-sel': faltaSeleccionada === i }]"
                        @click="faltaSeleccionar(i)" style="cursor:pointer">
                      <td class="num">{{ f.lic }}</td>
                      <td>{{ f.detalle }}</td>
                      <td>{{ formatFecha(f.desde) }}</td>
                      <td>{{ formatFecha(f.hasta) }}</td>
                      <td>{{ f.observaciones?.trim() }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Documentación respaldatoria -->
            <div class="faltas-doc-titulo">DOCUMENTACIÓN RESPALDATORIA</div>
            <div class="faltas-docs">
              <div class="faltas-docs-scroll">
                <table class="tab-tabla faltas-tabla">
                  <thead>
                    <tr>
                      <th style="width:40px;text-align:center">Ver</th>
                      <th style="width:50px">N#</th>
                      <th style="width:60px">TIPO</th>
                      <th style="width:170px">DETALLE TIPO</th>
                      <th>NOMBRE</th>
                      <th style="width:55px">EXT</th>
                      <th style="width:90px">FECHA</th>
                      <th style="width:120px">CREADO</th>
                      <th style="width:140px">OBSERVACIONES</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="d in faltaDocs" :key="d.id">
                      <td style="text-align:center">
                        <button class="btn-ojo" title="Visualizar documento" @click="faltaDocVisualizar(d)">👁️</button>
                      </td>
                      <td class="num">{{ d.nro }}</td>
                      <td>{{ d.tipo?.trim() }}</td>
                      <td>{{ d.detalle?.trim() }}</td>
                      <td>{{ d.nombre?.trim() }}</td>
                      <td>{{ d.ext?.trim() }}</td>
                      <td>{{ formatFecha(d.fecha) }}</td>
                      <td>{{ d.creado?.trim() }}</td>
                      <td>{{ d.observaciones?.trim() }}</td>
                    </tr>
                    <tr v-if="faltaDocs.length === 0">
                      <td colspan="9" class="tab-empty" style="border:none">
                        {{ faltaDocsCargando ? '⟳ Cargando...'
                           : (faltaSeleccionada >= 0 ? 'Sin documentación respaldatoria' : 'Seleccioná una falta') }}
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <!-- ════════════════════════════════════════════════════
             TAB 16 — CELULAR
        ════════════════════════════════════════════════════ -->
        <div v-show="tabActual === 'celular'" class="tab-body">
          <div v-if="cargandoTab" class="tab-loading">⟳ Cargando...</div>
          <div v-else>
            <div class="tab-section-header">Celulares corporativos
              <span class="badge-count">{{ tabData.celulares.length }}</span>
            </div>
            <div v-if="tabData.celulares.length === 0" class="tab-empty">Sin celulares asignados</div>
            <div v-else class="cel-grid-scroll">
              <table class="tab-tabla cel-tabla">
                <thead>
                  <tr>
                    <th style="width:90px"># Línea</th>
                    <th style="width:50px">Cod.</th>
                    <th style="width:130px">IMEI</th>
                    <th style="width:90px">Marca</th>
                    <th style="width:160px">Modelo</th>
                    <th style="width:120px">Color</th>
                    <th style="width:55px">Pulg.</th>
                    <th style="width:120px">Sistema Operativo</th>
                    <th style="width:55px;text-align:center">Cargador</th>
                    <th style="width:55px;text-align:center">Auricular</th>
                    <th style="width:55px;text-align:center">Cable USB</th>
                    <th style="width:95px">F. Entrega</th>
                    <th style="width:95px">F. Devolución</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="c in tabData.celulares" :key="c.id"
                      :class="{ 'cel-devuelto': celDevuelto(c.fecha_devolucion) }">
                    <td>{{ c.numero }}</td>
                    <td class="num">{{ c.cod }}</td>
                    <td>{{ c.imei }}</td>
                    <td>{{ c.marca }}</td>
                    <td>{{ c.modelo }}</td>
                    <td>{{ c.color }}</td>
                    <td class="num">{{ c.pulgadas }}</td>
                    <td>{{ c.sistema }}</td>
                    <td style="text-align:center">{{ c.cargador }}</td>
                    <td style="text-align:center">{{ c.auricular }}</td>
                    <td style="text-align:center">{{ c.cableusb }}</td>
                    <td>{{ formatFecha(c.fecha_entrega) }}</td>
                    <td>{{ formatFecha(c.fecha_devolucion) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- ════════════════════════════════════════════════════
             TAB 17 — TARJETAS
        ════════════════════════════════════════════════════ -->
        <div v-show="tabActual === 'tarjetas'" class="tab-body">
          <div v-if="cargandoTab" class="tab-loading">⟳ Cargando...</div>
          <div v-else>
            <div class="tab-section-header">Tarjetas
              <span class="badge-count">{{ tabData.tarjetas.length }}</span>
            </div>
            <div v-if="tabData.tarjetas.length === 0" class="tab-empty">Sin tarjetas registradas</div>
            <table v-else class="tab-tabla">
              <thead>
                <tr>
                  <th>Descripción en el Sistema</th>
                  <th style="width:70px;text-align:center"># 1</th>
                  <th style="width:70px;text-align:center"># 2</th>
                  <th style="width:70px;text-align:center"># 3</th>
                  <th style="width:70px;text-align:center"># 4</th>
                  <th style="width:200px">Cuenta Bancaria</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(t, i) in tabData.tarjetas" :key="i">
                  <td>{{ t.descripcion }}</td>
                  <td class="num" style="text-align:center">{{ t.nt1 }}</td>
                  <td class="num" style="text-align:center">{{ t.nt2 }}</td>
                  <td class="num" style="text-align:center">{{ t.nt3 }}</td>
                  <td class="num" style="text-align:center">{{ t.nt4 }}</td>
                  <td>{{ t.cuenta }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- ════════════════════════════════════════════════════
             TAB 18 — EPP ASIGNADA
        ════════════════════════════════════════════════════ -->
        <div v-show="tabActual === 'epp'" class="tab-body">
          <div v-if="cargandoTab" class="tab-loading">⟳ Cargando...</div>
          <div v-else>
            <div class="tab-section-header">EPP actualmente asignada
              <span class="badge-count">{{ tabData.epp.length }}</span>
            </div>
            <div v-if="tabData.epp.length === 0" class="tab-empty">Sin EPP asignada</div>
            <table v-else class="tab-tabla">
              <thead><tr><th style="width:90px">Código</th><th>Descripción</th><th style="width:120px">Talle</th></tr></thead>
              <tbody>
                <tr v-for="(e, i) in tabData.epp" :key="i">
                  <td class="num">{{ e.codigo }}</td>
                  <td>{{ e.descripcion }}</td>
                  <td>{{ e.talle }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

      </div><!-- /col-detalle -->
    </div><!-- /layout -->

    <!-- ══════ MODAL BAJA ══════ -->
    <div v-if="modalBaja" class="modal-overlay" @click.self="modalBaja = false">
      <div class="modal">
        <h3>Dar de baja</h3>
        <p>Empleado: <strong>{{ empActual?.PER_NOM }}</strong></p>
        <div class="campo">
          <label>Fecha de baja</label>
          <input v-model="bajaDatos.fecha" type="date" />
        </div>
        <div class="campo">
          <label>Motivo</label>
          <input v-model="bajaDatos.motivo" type="text" maxlength="100" placeholder="Motivo de baja..." />
        </div>
        <div v-if="bajaError" class="baja-error">⚠️ {{ bajaError }}</div>
        <div class="modal-btns">
          <button class="btn-sm btn-baja" @click="confirmarBaja">🚫 Confirmar baja</button>
          <button class="btn-sm btn-cancel" @click="modalBaja = false">Cancelar</button>
        </div>
      </div>
    </div>

  </div><!-- /empleados-view -->

  <!-- ── Modal preview PDF ── -->
  <Teleport to="body">
    <div v-if="pdfModalUrl" class="pdf-modal-overlay" @click.self="cerrarPdfModal">
      <div class="pdf-modal">
        <div class="pdf-modal-header">
          <span>{{ pdfModalNombre }}</span>
          <div class="pdf-modal-btns">
            <button class="btn-sm btn-reactivar" @click="guardarDesdeUrl(pdfModalUrl, pdfModalNombre)">⬇ Descargar</button>
            <button v-if="pdfModalPreview" class="btn-sm btn-reactivar" @click="($refs.pdfFrame as HTMLIFrameElement)?.contentWindow?.print()">🖨 Imprimir</button>
            <button class="btn-sm btn-cancel"    @click="cerrarPdfModal">✕ Cerrar</button>
          </div>
        </div>
        <iframe v-if="pdfModalPreview" ref="pdfFrame" :src="pdfModalUrl" class="pdf-modal-frame"></iframe>
        <div v-else class="pdf-modal-nopreview">
          <div class="pdf-modal-nopreview-icon">📄</div>
          <p>Este tipo de archivo no se puede previsualizar en el navegador.</p>
          <button class="btn-sm btn-reactivar" @click="guardarDesdeUrl(pdfModalUrl, pdfModalNombre)">⬇ Descargar archivo</button>
        </div>
      </div>
    </div>
  </Teleport>

  <!-- Modal Informe Completo del Puesto (ojito en Puestos asignados) -->
  <Teleport to="body">
    <div v-if="puestoModal" class="puesto-modal-overlay" @click.self="puestoModal = null">
      <div class="puesto-modal">
        <div class="puesto-modal-header">
          <span>🧰 Descripción del puesto</span>
          <button class="btn-sm btn-cancel" @click="puestoModal = null">✕ Cerrar</button>
        </div>
        <div class="puesto-modal-body">
          <div v-if="puestoCargando" class="puesto-loading">⟳ Cargando…</div>
          <template v-else-if="puestoModal">
            <h2 class="pm-titulo">{{ puestoModal.puesto.descripcion }}</h2>
            <div class="pm-cod">Código: <b>{{ puestoModal.puesto.codigo }}</b></div>
            <div class="pm-grid">
              <div><span>Departamento</span>{{ puestoModal.puesto.depto_nombre || '—' }}</div>
              <div><span>Reporta a</span>{{ puestoModal.puesto.reporta || '—' }}</div>
            </div>
            <div v-if="puestoModal.puesto.objetivo" class="pm-bloque"><h4>Objetivo</h4><p>{{ puestoModal.puesto.objetivo }}</p></div>
            <div v-if="puestoModal.puesto.modificaciones" class="pm-bloque"><h4>Modificaciones</h4><p>{{ puestoModal.puesto.modificaciones }}</p></div>
            <div v-if="puestoModal.puesto.requisitos" class="pm-bloque"><h4>Requisitos legales</h4><p>{{ puestoModal.puesto.requisitos }}</p></div>
            <div v-if="puestoModal.tareas?.length" class="pm-bloque"><h4>Tareas</h4><ul><li v-for="(t, i) in puestoModal.tareas" :key="i">{{ t.des ?? t }}<em v-if="t.fre_des" class="pm-em"> · {{ t.fre_des }}</em></li></ul></div>
            <div v-if="puestoModal.educacion?.length" class="pm-bloque"><h4>Requisitos educacionales</h4><ul><li v-for="(e, i) in puestoModal.educacion" :key="i">{{ e.des ?? e }}</li></ul></div>
            <div v-if="puestoModal.cualidades?.length" class="pm-bloque"><h4>Cualidades</h4><ul><li v-for="(c, i) in puestoModal.cualidades" :key="i">{{ c.descripcion ?? c.des ?? c }}</li></ul></div>
            <div v-if="puestoModal.competencias?.length" class="pm-bloque"><h4>Competencias</h4><ul><li v-for="(c, i) in puestoModal.competencias" :key="i">{{ c.descripcion ?? c.des ?? c }}</li></ul></div>
            <div v-if="puestoModal.elementos?.length" class="pm-bloque"><h4>Elementos de protección</h4><ul><li v-for="(e, i) in puestoModal.elementos" :key="i">{{ e.descripcion ?? e.des ?? e }}</li></ul></div>
            <div v-if="puestoModal.revisiones?.length" class="pm-bloque"><h4>Revisiones</h4><ul><li v-for="(r, i) in puestoModal.revisiones" :key="i">{{ r.fre ? formatFecha(r.fre) : '' }}<template v-if="r.res"> — {{ r.res }}</template></li></ul></div>
          </template>
        </div>
      </div>
    </div>
  </Teleport>

  <!-- Aviso: el empleado no tiene puestos asignados (para la Hoja de Evaluación) -->
  <Teleport to="body">
    <div v-if="avisoSinPuesto" class="puesto-modal-overlay" @click.self="avisoSinPuesto = false">
      <div class="puesto-modal" style="max-width:560px;text-align:center;padding:32px 26px">
        <div style="font-size:52px;line-height:1">⚠️</div>
        <h2 style="font-size:26px;font-weight:800;color:#b91c1c;margin:14px 0 8px">Sin puesto asignado</h2>
        <p style="font-size:16px;color:#334155;margin:0 0 20px">
          Este empleado <b>no tiene ningún puesto de trabajo asignado</b>, por lo que no se puede imprimir la Hoja de Evaluación.<br>
          Asigná un puesto con el botón <b>➕ Asignar Puesto</b>.
        </p>
        <button class="btn-sm btn-reactivar" @click="avisoSinPuesto = false">Entendido</button>
      </div>
    </div>
  </Teleport>

  <!-- Modal Asignar Puesto de Trabajo (desde la solapa Puestos) -->
  <Teleport to="body">
    <div v-if="asignarPuestoOpen" class="puesto-modal-overlay" @click.self="asignarPuestoOpen = false">
      <div class="puesto-modal" style="max-width:520px">
        <div class="puesto-modal-header">
          <span>➕ Asignar puesto de trabajo</span>
          <button class="btn-sm btn-cancel" @click="asignarPuestoOpen = false">✕ Cerrar</button>
        </div>
        <div class="puesto-modal-body">
          <p style="font-size:13px;color:#475569;margin:0 0 10px">
            Empleado: <b>{{ empActual?.PER_COD }} — {{ empActual?.PER_NOM }}</b>
          </p>
          <label style="font-size:12px;font-weight:700;color:#374151;display:block;margin-bottom:4px">Puesto</label>
          <select v-model="puestoNuevoSel" style="width:100%;padding:9px 10px;border:1px solid #c8d8ea;border-radius:7px;font-size:14px">
            <option value="">— Seleccioná un puesto —</option>
            <option v-for="pp in catPuestos" :key="pp.codigo" :value="pp.codigo">{{ pp.descripcion }}</option>
          </select>

          <!-- Valores por defecto inteligentes según el puesto elegido -->
          <div v-if="sugCargando" class="sug-panel" style="color:#64748b">⟳ Analizando puesto…</div>
          <div v-else-if="sugLista.length" class="sug-panel">
            <div class="sug-titulo">💡 Según {{ sugPuesto?.ocupantes }} empleado{{ sugPuesto?.ocupantes === 1 ? '' : 's' }} con este puesto:</div>
            <div v-for="s in sugLista" :key="s.clave" class="sug-item">
              <span class="sug-campo">{{ s.label }}</span>
              <b class="sug-valor">{{ s.desc }}</b>
              <span class="sug-conf">{{ s.confianza }}%</span>
              <span v-if="s.coincide" class="sug-ok">✓ ya en la ficha</span>
            </div>
            <button class="btn-sm btn-reactivar" style="margin-top:8px"
                    :disabled="sugLista.every(s => s.coincide)" @click="aplicarSugerencias">
              🪄 Aplicar a la ficha
            </button>
            <p class="sug-nota">Se cargan como sugerencia editable; revisá y guardá la ficha.</p>
          </div>

          <div style="display:flex;align-items:center;gap:10px;margin-top:16px">
            <button class="btn-sm btn-reactivar" :disabled="!puestoNuevoSel || puestoProc" @click="asignarPuesto">
              {{ puestoProc ? '⟳…' : '✔ Asignar' }}
            </button>
            <button class="btn-sm btn-cancel" @click="asignarPuestoOpen = false">Cancelar</button>
            <span v-if="puestoMsg" :class="puestoMsg.startsWith('Error') || puestoMsg.startsWith('No') || puestoMsg.startsWith('Ese') ? 'fotos-err' : 'fotos-ok'"
                  style="padding:4px 10px;border-radius:4px;font-size:12px">{{ puestoMsg }}</span>
          </div>
        </div>
      </div>
    </div>
  </Teleport>

  <DocViewer ref="docVisor" />

  <!-- Modal ABM lanzado desde la ficha (Propuesta A: módulo madre) -->
  <Teleport to="body">
    <div v-if="abmActivo" class="abm-ov" @click.self="cerrarAbm">
      <div class="abm-md">
        <div class="abm-md-bar">
          <img v-if="fotoUrl" :src="fotoUrl" class="abm-foto" :alt="empActual?.PER_NOM" />
          <div v-else class="abm-foto abm-foto-ph">{{ abmIniciales }}</div>
          <div class="abm-emp">
            <span class="abm-emp-nom">{{ empActual?.PER_NOM }}</span>
            <span class="abm-emp-meta">
              Legajo <b>{{ empActual?.PER_LEG }}</b> · Cód. {{ empActual?.PER_COD }}
              <span :class="empActual?.PER_AOP === 'A' ? 'abm-badge act' : 'abm-badge baj'">{{ empActual?.PER_AOP === 'A' ? 'Activo' : 'De baja' }}</span>
            </span>
          </div>
          <button class="abm-x" @click="cerrarAbm">✕ Cerrar</button>
        </div>
        <div class="abm-md-body">
          <component :is="abmComp" :empleado="Number(empActual?.PER_COD) || 0" :empleado-nombre="(empActual?.PER_NOM || '').trim()" />
        </div>
      </div>
    </div>
  </Teleport>

</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted, watch, defineAsyncComponent } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/services/auth'
import { cargarDesdeApi, formatFecha, iniciales } from '@/composables/useForm'
import jsPDF from 'jspdf'
import { useAuthStore } from '@/stores/auth'
import EmpleadosAyuda from '@/components/EmpleadosAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'
import EmpleadosListados from '@/views/EmpleadosListadosView.vue'
import { guardarDesdeUrl } from '@/utils/descargas'
import { dibujarHojaEvaluacion } from '@/utils/hojaEvaluacion'
import DocViewer from '@/components/DocViewer.vue'

const authStore = useAuthStore()
const modalAyuda = ref(false)
const modalIA = ref(false)
const modalListados = ref(false)
const generandoLegajo = ref(false)

// ── Visor de documentos (PDF/imagen/Excel/Word en modal; otros descargan) ──
const docVisor = ref<InstanceType<typeof DocViewer> | null>(null)

// ── Modal "Informe completo" del puesto (ojito en Puestos asignados) ──
const puestoModal = ref<any>(null)
const puestoCargando = ref(false)
async function verPuesto (codigo: string) {
  if (!codigo) return
  puestoCargando.value = true
  puestoModal.value = { puesto: { descripcion: '', codigo } }
  try { puestoModal.value = (await api.get(`/puestos/${encodeURIComponent(String(codigo).trim())}`)).data }
  catch { puestoModal.value = null }
  finally { puestoCargando.value = false }
}

// ── Asignar / dar de baja puesto de trabajo desde la solapa Puestos ──
const asignarPuestoOpen = ref(false)
const catPuestos = ref<{ codigo: string; descripcion: string }[]>([])
const puestoNuevoSel = ref('')
const puestoProc = ref(false)
const puestoMsg = ref('')
async function abrirAsignarPuesto () {
  puestoMsg.value = ''; puestoNuevoSel.value = ''; sugPuesto.value = null
  if (!catPuestos.value.length) {
    try { catPuestos.value = (await api.get('/tareas/puestos')).data ?? [] } catch { /* */ }
  }
  asignarPuestoOpen.value = true
}

// ── Valores por defecto inteligentes: sugerencias según el puesto elegido ──
interface SugInfo { valor: number; confianza: number; base: number }
const sugPuesto = ref<{ ocupantes: number; sugerencias: Record<string, SugInfo> } | null>(null)
const sugCargando = ref(false)
const SUG_CAMPOS: Record<string, { label: string; formKey: string }> = {
  convenio:  { label: 'Convenio',  formKey: 'PER_CON' },
  categoria: { label: 'Categoría', formKey: 'PER_CAT' },
  sector:    { label: 'Sector',    formKey: 'PER_SEC' },
  subsector: { label: 'Subsector', formKey: 'PER_SUC' },
}
function descSug (clave: string, valor: number): string {
  const o: any = opciones
  if (clave === 'convenio')  return o.convenios.find((c: any) => c.CON_COD === valor)?.CON_DES ?? `Cód ${valor}`
  if (clave === 'categoria') return o.categorias.find((c: any) => c.CAT_COD === valor)?.CAT_DES ?? `Cód ${valor}`
  if (clave === 'sector')    return o.sectores.find((c: any) => c.SEC_COD === valor)?.SEC_DES ?? `Cód ${valor}`
  if (clave === 'subsector') return o.subsectores.find((c: any) => c.sub_cod === valor)?.sub_des ?? `Cód ${valor}`
  return `Cód ${valor}`
}
const sugLista = computed(() => {
  const s = sugPuesto.value?.sugerencias
  if (!s) return [] as { clave: string; label: string; desc: string; confianza: number; coincide: boolean }[]
  return Object.entries(s).map(([clave, info]) => ({
    clave,
    label: SUG_CAMPOS[clave]?.label ?? clave,
    desc: descSug(clave, info.valor),
    confianza: info.confianza,
    coincide: (form.value as any)[SUG_CAMPOS[clave]?.formKey ?? ''] === info.valor,
  }))
})
watch(puestoNuevoSel, async (pue) => {
  sugPuesto.value = null
  if (!pue) return
  sugCargando.value = true
  try {
    const { data } = await api.get('/puestos-sugerencias', { params: { puesto: pue } })
    sugPuesto.value = data ?? null
  } catch { sugPuesto.value = null }
  finally { sugCargando.value = false }
})
function aplicarSugerencias () {
  const s = sugPuesto.value?.sugerencias
  if (!s) return
  if (s.convenio)  (form.value as any).PER_CON = s.convenio.valor
  if (s.categoria) (form.value as any).PER_CAT = s.categoria.valor
  if (s.sector)  { (form.value as any).PER_SEC = s.sector.valor; onSectorChange() }
  if (s.subsector) (form.value as any).PER_SUC = s.subsector.valor
  modoEdicion.value = true
  tabActual.value = 'datos'
  asignarPuestoOpen.value = false
  puestoMsg.value = ''
}
async function recargarPuestos () {
  if (!empActual.value) return
  try { tabData.puestos = (await api.get(`/empleados/${empActual.value.PER_COD}/puestos`)).data } catch { /* */ }
}
async function asignarPuesto () {
  if (!puestoNuevoSel.value || !empActual.value) return
  // Si ya tiene puestos activos, preguntar si los inhabilita. El nuevo entra siempre activo.
  const tieneActivos = (tabData.puestos.puestos ?? []).some((p: any) => p.activo)
  let inhabilitar = false
  if (tieneActivos) {
    inhabilitar = confirm('El empleado ya tiene puesto(s) activo(s).\n\n¿Desea pasarlos TODOS a INACTIVO?\n(El nuevo puesto se agrega siempre como ACTIVO.)')
  }
  puestoProc.value = true; puestoMsg.value = ''
  try {
    await api.post('/asignar-puesto', { codigo: empActual.value.PER_COD, puesto: puestoNuevoSel.value, inhabilitar_actuales: inhabilitar })
    await recargarPuestos()
    asignarPuestoOpen.value = false
  } catch (e: any) { puestoMsg.value = e?.response?.data?.message ?? 'No se pudo asignar el puesto.' }
  finally { puestoProc.value = false }
}
async function toggleActivoPuesto (p: any) {
  if (!empActual.value || !p?.codigo) return
  const nuevo = !p.activo
  const estado = nuevo ? 'ACTIVO' : 'INACTIVO'
  if (!confirm(`¿Pasar el puesto "${(p.puesto || '').trim()}" a ${estado}?`)) return
  puestoProc.value = true
  try {
    await api.post('/asignar-puesto/estado', { codigo: empActual.value.PER_COD, puesto: p.codigo, activo: nuevo })
    await recargarPuestos()
  } catch (e: any) { alert(e?.response?.data?.message ?? 'No se pudo cambiar el estado del puesto.') }
  finally { puestoProc.value = false }
}
async function eliminarPuesto (p: any) {
  if (!empActual.value || !p?.codigo) return
  if (!confirm(`¿Eliminar DEFINITIVAMENTE el puesto "${p.puesto}" de este empleado?\n\nEsta acción no se puede deshacer.`)) return
  puestoProc.value = true
  try {
    await api.post('/asignar-puesto/baja', { codigo: empActual.value.PER_COD, puesto: p.codigo })
    await recargarPuestos()
  } catch (e: any) { alert(e?.response?.data?.message ?? 'No se pudo eliminar el puesto.') }
  finally { puestoProc.value = false }
}

// ── Imprimir Hoja de Evaluación (una por puesto asignado) ──
const avisoSinPuesto = ref(false)
const logoHoja = ref('')
async function cargarLogoHoja () {
  if (logoHoja.value) return
  try {
    const b = await (await fetch(authStore.logoEmpresa)).blob()
    logoHoja.value = await new Promise<string>(res => { const fr = new FileReader(); fr.onload = () => res(fr.result as string); fr.readAsDataURL(b) })
  } catch { /* sin logo */ }
}
async function imprimirHojaEvaluacion () {
  const emp = empActual.value as any
  const puestos = tabData.puestos.puestos ?? []
  if (!puestos.length) { avisoSinPuesto.value = true; return }
  await cargarLogoHoja()
  const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })
  puestos.forEach((p: any, i: number) => {
    if (i > 0) doc.addPage()
    dibujarHojaEvaluacion(doc, { nombre: emp?.PER_NOM ?? '', puesto: p.puesto }, logoHoja.value)
  })
  pdfModalNombre.value = `Hoja_evaluacion_${emp?.PER_LEG ?? emp?.PER_COD}.pdf`
  pdfModalPreview.value = true
  pdfModalUrl.value = URL.createObjectURL(doc.output('blob'))
}

// ── Definición de las 18 pestañas ─────────────────────────────────────────────
// ── Propuesta A (módulo madre): ABM lanzados desde la ficha con el empleado precargado ──
const ABM_COMPS: Record<string, any> = {
  siniestros:      defineAsyncComponent(() => import('@/views/SiniestrosView.vue')),
  adelantos:       defineAsyncComponent(() => import('@/views/AdelantosView.vue')),
  vales:           defineAsyncComponent(() => import('@/views/ValesView.vue')),
  apercibimientos: defineAsyncComponent(() => import('@/views/ApercibimientosView.vue')),
  examenes:        defineAsyncComponent(() => import('@/views/ExamenesView.vue')),
  viajes:          defineAsyncComponent(() => import('@/views/ViajesView.vue')),
  vacaciones:      defineAsyncComponent(() => import('@/views/VacacionesAgregarView.vue')),
  faltas:          defineAsyncComponent(() => import('@/views/RelojFaltasView.vue')),
  celular:         defineAsyncComponent(() => import('@/views/CelularesAsignarView.vue')),
  epp:             defineAsyncComponent(() => import('@/views/EntregaRopaView.vue')),
  calificacion:    defineAsyncComponent(() => import('@/views/CalificacionView.vue')),
}
// Orden base FIJO por grupos lógicos (económico → salud → ausencias → disciplina/eval → equipamiento).
const ACCIONES = [
  { key: 'adelantos',      ic: '💵', label: 'Adelantos',         sub: 'Ver y cargar' },
  { key: 'vales',          ic: '🎟️', label: 'Vales',             sub: 'Ver y cargar' },
  { key: 'examenes',       ic: '🏥', label: 'Exámenes médicos',  sub: 'Ver y cargar' },
  { key: 'siniestros',     ic: '⚠️', label: 'Siniestros ART',    sub: 'Ver y cargar' },
  { key: 'vacaciones',     ic: '🏖️', label: 'Vacaciones',        sub: 'Cargar período' },
  { key: 'faltas',         ic: '📅', label: 'Faltas / Licencias',sub: 'Ver y cargar' },
  { key: 'viajes',         ic: '🧳', label: 'Viajes',            sub: 'Ver y cargar' },
  { key: 'apercibimientos',ic: '⚡', label: 'Apercibimientos',   sub: 'Ver y cargar' },
  { key: 'calificacion',   ic: '⭐', label: 'Calificación',      sub: 'Cargar evaluación' },
  { key: 'celular',        ic: '📱', label: 'Celular',           sub: 'Asignar / ver' },
  { key: 'epp',            ic: '🦺', label: 'Uniforme / EPP',    sub: 'Entregar' },
] as const

// Uso por usuario (localStorage): fila "Más usadas" (top 4), sin reordenar la grilla base.
const USO_KEY = 'rrhh_acciones_uso'
const usoMap = ref<Record<string, number>>({})
try { usoMap.value = JSON.parse(localStorage.getItem(USO_KEY) || '{}') } catch { usoMap.value = {} }
function registrarUso (k: string) {
  usoMap.value = { ...usoMap.value, [k]: (usoMap.value[k] || 0) + 1 }
  try { localStorage.setItem(USO_KEY, JSON.stringify(usoMap.value)) } catch { /* */ }
}
const masUsadas = computed(() =>
  ACCIONES.filter(a => (usoMap.value[a.key] || 0) > 0)
    .slice().sort((a, b) => (usoMap.value[b.key] || 0) - (usoMap.value[a.key] || 0))
    .slice(0, 4)
)

const abmActivo = ref('')
const abmComp = computed(() => abmActivo.value ? ABM_COMPS[abmActivo.value] : null)
const abmIniciales = computed(() => {
  const n = (empActual.value?.PER_NOM || '').trim()
  if (!n) return '?'
  const p = n.split(/\s+/)
  return (((p[0]?.[0] ?? '') + (p[1]?.[0] ?? '')).toUpperCase()) || '?'
})
const abrirAbm = (k: string) => { abmActivo.value = k; registrarUso(k) }
const cerrarAbm = () => { abmActivo.value = '' }

const TABS = [
  { id: 'datos',        label: '👤 Datos' },
  { id: 'acciones',     label: '⚡ Acciones' },
  { id: 'familia',      label: '👨‍👩‍👦 Familia' },
  { id: 'obrasocial',   label: '🏥 Obra Social' },
  { id: 'fotos',        label: '📷 Fotos' },
  { id: 'puestos',      label: '🏢 Puestos / Calificaciones' },
  { id: 'uniforme',     label: '👕 Gestión Uniforme-EPP' },
  { id: 'epp',          label: '🦺 EPP Asignada' },
  { id: 'capacitacion', label: '📚 Capacitación' },
  { id: 'licencia',     label: '🚗 Licencia' },
  { id: 'examenes',     label: '🩺 Exámenes y Certificados Médicos' },
  { id: 'documentos',   label: '📄 Documentación' },
  { id: 'acargo',       label: '👥 Personal a Cargo' },
  { id: 'valorhoras',   label: '⏱️ Valor Hs.' },
  { id: 'faltas',       label: '❌ Faltas' },
  { id: 'celular',      label: '📱 Celular' },
  { id: 'tarjetas',     label: '💳 Tarjetas' },
  { id: 'historial',    label: '📋 Historial de Cambios' },
]

// ── Interfaces ─────────────────────────────────────────────────────────────────
interface Empleado {
  PER_COD: number
  PER_NOM: string
  PER_LEG: number
  PER_NDO: number
  PER_CUI: string
  ACTIVO: boolean
  PER_AOP: string      // "A" = Activo, "P" = Pasivo
  PER_ING: string | null
  PER_BAJ: string | null
  PER_EMP: number
  PER_EMD: string
  PER_SEC: number
  PER_SED: string
  PER_CAT: number
  PER_CAD: string
  PER_CON: number
  PER_SEX: string
  PER_TEL: string
  PER_CEL: string
  [key: string]: any
}

// ── Estado principal ───────────────────────────────────────────────────────────
const empleados    = ref<Empleado[]>([])
const empActual    = ref<Empleado | null>(null)
const modoNuevo    = ref(false)
const modoEdicion  = ref(false)
const cargando     = ref(false)
const guardando    = ref(false)
const errorForm    = ref('')
const buscar       = ref('')
const filtroActivo = ref<'todos'|'activos'|'bajas'>('activos')
const pagina       = ref(1)
const paginas      = ref(1)
const total        = ref(0)
const tabActual    = ref('datos')
const subTab       = ref('personal')
const subTabOS     = ref('datos')
const cargandoTab  = ref(false)
const loadedTabs   = ref(new Set<string>())
const modalBaja    = ref(false)
const bajaDatos    = reactive({ fecha: '', motivo: '' })
const bajaError    = ref('')

// ── Datos de opciones (selects) ────────────────────────────────────────────────
const opciones = reactive<any>({
  empresas: [], sectores: [], categorias: [], convenios: [],
  estadosciviles: [], obrassociales: [], contratistas: [], comedores: [],
  lugares: [], subsectores: [], reloj_grupos: [], reloj_envios: [], bancos: [],
  carnetcategorias: [], tiposdocumento: [],
  viaticos_dia: 0,
})

// ── Datos de las pestañas relacionadas ─────────────────────────────────────────
const tabData = reactive<any>({
  hijos:          [],
  puestos:        { puestos: [], calificaciones: [], tareas: [], subordinados: [] },
  capacitaciones: [],
  examenes:       [],
  historial:      [],
  documentos:     [],
  subordinados:   [],
  faltas:         [],
  celulares:      [],
  ropa:           [],
  epp:            [],
  obrasocial:     [],
  tarjetas:       [],
})

// ── Formulario (todos los campos de personal) ──────────────────────────────────
const defaultForm = (): Record<string, any> => ({
  // Datos personales
  PER_COD: null,
  PER_NOM: '', PER_LEG: null, PER_SEX: 'M', PER_FNA: '', PER_LNA: '',
  PER_TDO: 'DNI', PER_NDO: null, PER_CUI: '',
  PER_ECI: null, PER_HIJ: null, PER_HIM: null, PER_HID: null,
  PER_DOM: '', PER_LOC: '', PER_CPA: '', PER_TEL: '', PER_CEL: '',
  PER_CONTACTO: '', PER_EME: '', PER_EMA: '',
  PER_PADRE: '', PER_MADRE: '', PER_HOB: '', PER_FACAD: '', PER_OBS: '',
  // Laboral
  ACTIVO: true, PER_AOP: 'A',
  PER_ING: '', PER_BAJ: '', PER_BAJ_RAZON: '', PER_FEFECTIVO: '',
  PER_EMP: null, PER_EMD: '', PER_CONTRA: null,
  PER_SEC: null, PER_SED: '', PER_CAT: null, PER_CAD: '', PER_CON: null,
  // PER_HORAS arranca en 200 al crear un nuevo empleado (preset FoxPro).
  PER_JOM: 'M', PER_HORAS: 200, PER_HNORMAL: null, PER_HSABADO: null,
  PER_ALM: 'N', PER_COMN: null, PER_COMD: '',
  PER_LUGAR: null, PER_GRU: null, PER_COS: null,
  PARTE_ENV: false, PARTE_PRG: null,
  PENDIENTE_SISTEMA: false, REQUIERE_SISTEMA: '',
  PER_CHE: 'N', PER_CAR: 'N',
  PER_NOMARCA: false, PER_NMRAZON: '',
  PER_RENTACAR: false, PER_TIENEPC: false, PER_ADI_ENC: false, PER_ADI_SUS: false,
  // Remuneración
  PER_SUE: null, PER_REMU: null, PER_NREM: null, PER_DESCUENTO: null, PER_ANTI: null,
  PER_CBU: '', PER_CBU_CUENTA: '', PER_HEN: '', PER_HSA: '',
  PER_BAN: null, PER_BAD: '', PER_SUC: null, PER_SUD: '',
  OBRASOCIAL: '', PER_AOS: null,
  // Obra Social y Otros
  PER_PMP: '', PER_FAS: '',
  PER_JRE: 'N', PER_JRC: null, PER_SIN: 'N', PER_AFILIADO: false,
  PER_PSN: 'N', PER_JSN: 'N', PER_PPR: 100, PER_PRO: null, PER_PRM: null,
  PER_EMBSN: 'N', PER_EMBNOM: '', PER_EMBCBU: '',
  // Licencia
  PER_LIC: 'N', PER_LCA: '', PER_LVE: '',
  PER_LN1: '', PER_LC1: '', PER_LF1: '',
  PER_LN2: '', PER_LC2: '', PER_LF2: '',
  PER_LN3: '', PER_LC3: '', PER_LF3: '',
  PER_LSF: 'N', PER_LSG: 'N', PER_LDO: 'N',
  PER_LOB: '', PER_LRE: '', PER_LCD: '',
})

const form = ref<Record<string, any>>(defaultForm())

// ── Computed ───────────────────────────────────────────────────────────────────
// true cuando los campos deben ser solo lectura (empleado seleccionado y no en modo edición)
const soloLectura = computed(() => !modoEdicion.value && !modoNuevo.value)

const stats = computed(() => ({
  total:   total.value,
  activos: empleados.value.filter(e => e.PER_AOP === 'A').length,
}))

const categoriasFiltradas = computed(() =>
  form.value.PER_CON
    ? opciones.categorias.filter((c: any) => c.CAT_CON === form.value.PER_CON)
    : opciones.categorias
)

const sueldoBruto = computed(() => {
  const sue  = form.value.PER_SUE || 0
  const desc = form.value.PER_DESCUENTO || 0
  if (desc <= 0 || sue <= 0) return sue
  return +(sue / ((100 - desc) / 100)).toFixed(2)
})

const valorHoraNormal = computed(() => {
  const sue   = form.value.PER_SUE || 0
  const horas = form.value.PER_HORAS || 0
  return horas > 0 ? +(sue / horas).toFixed(4) : 0
})

// Visibilidad de datos salariales (réplica FoxPro: solo NIVEL_USUARIO = 9)
const mostrarSalarios = computed(() => Number(authStore.usuario?.NIVEL) === 9)
// Datos bancarios: además se ocultan si el empleado es contratista (PER_CONTRA > 3)
const mostrarBanco = computed(() => mostrarSalarios.value && (Number(form.value.PER_CONTRA) || 0) <= 3)

// Cartel "Prestación" (réplica FoxPro: carTEL_AVISO según PER_CONTRA y PER_ING+90 días)
const prestacionTexto = computed(() => {
  const ingStr = form.value.PER_ING
  if (!ingStr) return '—'
  const ing = new Date(ingStr)
  if (isNaN(ing.getTime()) || ing.getFullYear() <= 1900) return '—'
  const ingMas90 = new Date(ing); ingMas90.setDate(ingMas90.getDate() + 90)
  const hoy = new Date()
  const contra = Number(form.value.PER_CONTRA) || 0
  if (contra === 0) {
    return ingMas90 > hoy ? 'PERÍODO DE PRUEBA' : '—'
  }
  return ingMas90 < hoy ? 'PRESTACIÓN MAYOR A 6 MESES' : '—'
})

// ── Formatters ─────────────────────────────────────────────────────────────────
// formatFecha e iniciales vienen del composable @/composables/useForm
const formatFechaHora = (val: any): string => {
  if (!val) return ''
  const d = new Date(val)
  if (isNaN(d.getTime()) || d.getFullYear() <= 1900) return ''
  return d.toLocaleString('es-AR', { day:'2-digit', month:'2-digit', year:'numeric', hour:'2-digit', minute:'2-digit' })
}
const formatNum = (val: any): string =>
  val != null ? Number(val).toLocaleString('es-AR', { minimumFractionDigits: 2 }) : ''
const formatMoneda = (val: any): string =>
  val != null ? '$' + Number(val).toLocaleString('es-AR', { minimumFractionDigits: 2 }) : '$0,00'
// Número plano con 2 decimales para la solapa Valores Horas (estilo FoxPro)
const vhNum = (val: any): string =>
  (val == null || val === '') ? '0.00' : Number(val).toFixed(2)
// iniciales viene del composable @/composables/useForm
const vencido = (fecha: any): boolean => {
  if (!fecha) return false
  return new Date(fecha) < new Date()
}
// Celular devuelto = tiene fecha de devolución (no vacía ni 1900-01-01)
const celDevuelto = (fecha: any): boolean => {
  if (!fecha) return false
  return !String(fecha).startsWith('1900-')
}

// ── Carga de datos ─────────────────────────────────────────────────────────────
let debounceTimer: ReturnType<typeof setTimeout>
const buscarDebounce = () => {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => { pagina.value = 1; cargarEmpleados() }, 350)
}

const setFiltro = (f: 'todos'|'activos'|'bajas') => {
  filtroActivo.value = f; pagina.value = 1; cargarEmpleados()
}
const irPagina = (p: number) => { pagina.value = p; cargarEmpleados() }

const cargarEmpleados = async () => {
  cargando.value = true
  try {
    const params: any = { pagina: pagina.value, por_pagina: 50 }
    if (buscar.value)              params.buscar = buscar.value
    if (filtroActivo.value === 'activos') params.activo = '1'
    if (filtroActivo.value === 'bajas')   params.activo = '0'

    const { data } = await api.get('/empleados', { params })
    empleados.value = data.data
    total.value     = data.total
    paginas.value   = data.paginas
  } finally {
    cargando.value = false
  }
}

const cargarOpciones = async () => {
  try {
    const { data } = await api.get('/empleados/opciones')
    Object.assign(opciones, data)
  } catch (e: any) {
    console.error('[cargarOpciones] Error:', e?.response?.data ?? e?.message ?? e)
  }
}

// ── Puesto laboral inicial (solo al dar de alta un empleado nuevo) ──────────────
const catalogoPuestos = ref<Array<{ codigo: any; descripcion: string }>>([])
const puestoInicial   = ref<string>('')   // PUE_COD elegido para asignar al alta

const cargarCatalogoPuestos = async () => {
  if (catalogoPuestos.value.length) return
  try {
    const { data } = await api.get('/puestos')
    catalogoPuestos.value = Array.isArray(data) ? data : (data.data ?? [])
  } catch (e: any) {
    console.error('[cargarCatalogoPuestos] Error:', e?.response?.data ?? e?.message ?? e)
  }
}

// ── Selección de empleado ──────────────────────────────────────────────────────
const fotoUrl    = ref<string | null>(null)

// ── Familia ───────────────────────────────────────────────────────────────────
const hijosLocal    = ref<any[]>([])

/**
 * Convierte una fecha de la API (datetime SQL "1999-07-05 00:00:00.000" o ISO)
 * al formato que exige <input type="date"> → "yyyy-MM-dd".
 * Devuelve '' para fechas vacías o 1900-01-01 (vacías en FoxPro).
 */
const toInputDate = (v: any): string => {
  if (!v) return ''
  const s = String(v)
  if (s.startsWith('1900-')) return ''
  const m = s.match(/^(\d{4}-\d{2}-\d{2})/)
  return m ? m[1] : ''
}

/** Normaliza una fila de hijo recibida de la API para el formulario. */
const mapHijo = (h: any) => ({ ...h, PER_FNA: toInputDate(h.PER_FNA), _ok: false })
const familiaForm   = reactive({ PER_NES: '', PER_PADRE: '', PER_MADRE: '', PER_EMBSN: 'N', PER_EMBNOM: '', PER_EMBCBU: '' })
const hijosGuardando = ref(false)
const hijosMsg      = ref('')
const hijosMsgOk    = ref(true)

const hijosAgregar = () => {
  hijosLocal.value.push({ _ok: false, PER_NOM: '', PER_FNA: '', PER_SIT: '' })
}

// Eliminar: quita los marcados del array local (sin ir al servidor)
// luego llama a confirmar igual que FoxPro: borra todo y re-inserta lo que queda
const hijosEliminar = () => {
  hijosLocal.value = hijosLocal.value.filter(h => !h._ok)
  hijosConfirmar()
}

// Confirmar: DELETE ALL + INSERT non-empty — igual que FoxPro
const hijosConfirmar = async () => {
  if (!empActual.value) return
  hijosGuardando.value = true
  hijosMsg.value = ''
  try {
    await api.put(`/empleados/${empActual.value.PER_COD}/hijos`, {
      hijos:      hijosLocal.value,
      PER_NES:    familiaForm.PER_NES,
      PER_PADRE:  familiaForm.PER_PADRE,
      PER_MADRE:  familiaForm.PER_MADRE,
      PER_EMBSN:  familiaForm.PER_EMBSN,
      PER_EMBNOM: familiaForm.PER_EMBNOM,
      PER_EMBCBU: familiaForm.PER_EMBCBU,
    })
    // Recargar para normalizar secuencia
    const r = await api.get(`/empleados/${empActual.value.PER_COD}/hijos`)
    hijosLocal.value = r.data.map(mapHijo)
    hijosMsg.value = 'Guardado correctamente'
    hijosMsgOk.value = true
  } catch {
    hijosMsg.value = 'Error al guardar'
    hijosMsgOk.value = false
  } finally {
    hijosGuardando.value = false
  }
}
const fotoSubiendo = ref(false)
const fotoMsg    = ref('')
const fotoMsgOk  = ref(true)

const onAgregarFoto = async (e: Event) => {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file || !empActual.value) return
  fotoSubiendo.value = true
  fotoMsg.value = ''
  try {
    const fd = new FormData()
    fd.append('imagen', file)
    await api.post(`/empleados/${empActual.value.PER_COD}/foto`, fd, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
    // Recargar la foto
    const r = await api.get(`/empleados/${empActual.value.PER_COD}/foto`)
    fotoUrl.value = r.data.foto ?? null
    fotoMsg.value = 'Foto guardada correctamente'
    fotoMsgOk.value = true
  } catch (err: any) {
    fotoMsg.value = 'Error al guardar la foto'
    fotoMsgOk.value = false
  } finally {
    fotoSubiendo.value = false
    ;(e.target as HTMLInputElement).value = ''
  }
}

const onEliminarFoto = async () => {
  if (!fotoUrl.value || !empActual.value) return
  if (!confirm('¿Eliminar la foto de este empleado?')) return
  fotoSubiendo.value = true
  fotoMsg.value = ''
  try {
    await api.delete(`/empleados/${empActual.value.PER_COD}/foto`)
    fotoUrl.value = null
    fotoMsg.value = 'Foto eliminada'
    fotoMsgOk.value = true
  } catch {
    fotoMsg.value = 'Error al eliminar la foto'
    fotoMsgOk.value = false
  } finally {
    fotoSubiendo.value = false
  }
}

// ── PDF Puestos Asignados ────────────────────────────────────────
const imprimirPuestosPDF = async () => {
  const emp   = empActual.value as any
  const datos = tabData.puestos

  const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })
  const PW = 210, ML = 14, MR = 14, CW = PW - ML - MR
  let y = 14

  const addHeader = () => {
    doc.setFillColor(27, 67, 50)
    doc.rect(ML, 8, CW, 9, 'F')
    doc.setFont('helvetica', 'bold'); doc.setFontSize(12); doc.setTextColor(255, 255, 255)
    doc.text(`Legajo de ${emp?.PER_NOM ?? ''}`, PW / 2, 14.5, { align: 'center' })
    doc.setTextColor(0, 0, 0)
  }
  const CHEKY = (needed = 20) => {
    if (y + needed > 283) { doc.addPage(); addHeader(); y = 22 }
  }

  const banner = (txt: string) => {
    CHEKY(10)
    doc.setFillColor(27, 67, 50)
    doc.rect(ML, y, CW, 7, 'F')
    doc.setFont('helvetica', 'bold'); doc.setFontSize(9); doc.setTextColor(255, 255, 255)
    doc.text(txt, ML + 2, y + 5)
    doc.setTextColor(0, 0, 0); y += 8
  }

  const hrow = (cols: {w: number, txt: string}[]) => {
    let x = ML
    doc.setFillColor(210, 230, 215)
    cols.forEach(c => { doc.rect(x, y, c.w, 6, 'F'); x += c.w })
    x = ML
    doc.setFont('helvetica', 'bold'); doc.setFontSize(8); doc.setTextColor(30, 30, 30)
    cols.forEach(c => { doc.text(c.txt, x + 2, y + 4); x += c.w })
    y += 6
  }

  // ── Encabezado ──
  doc.setFillColor(27, 67, 50)
  doc.rect(ML, y, CW, 9, 'F')
  doc.setFont('helvetica', 'bold'); doc.setFontSize(12); doc.setTextColor(255, 255, 255)
  doc.text(`Legajo de ${emp?.PER_NOM ?? ''}`, PW / 2, y + 6.5, { align: 'center' })
  doc.setTextColor(0, 0, 0); y += 14

  // ── Foto (posición fija arriba derecha) ──
  const FOTO_W = 38, FOTO_H = 48
  const fotoX = PW - MR - FOTO_W
  const fotoY = y

  doc.setDrawColor(27, 67, 50); doc.setLineWidth(0.5)
  doc.rect(fotoX, fotoY, FOTO_W, FOTO_H)

  if (fotoUrl.value) {
    try {
      const resp = await fetch(fotoUrl.value)
      const blob = await resp.blob()
      const b64: string = await new Promise(res => {
        const reader = new FileReader()
        reader.onload = () => res(reader.result as string)
        reader.readAsDataURL(blob)
      })
      const ext = b64.startsWith('data:image/png') ? 'PNG' : 'JPEG'
      // Mantener proporción original
      const imgEl = new Image()
      imgEl.src = b64
      await new Promise(r => { imgEl.onload = r })
      const ratio = imgEl.naturalWidth / imgEl.naturalHeight
      let iW = FOTO_W, iH = FOTO_W / ratio
      if (iH > FOTO_H) { iH = FOTO_H; iW = FOTO_H * ratio }
      const iX = fotoX + (FOTO_W - iW) / 2
      const iY = fotoY + (FOTO_H - iH) / 2
      doc.addImage(b64, ext, iX, iY, iW, iH)
    } catch { /* sin foto */ }
  }

  // ── Ficha empleado (izquierda, secuencial) ──
  const FICW  = CW - FOTO_W - 5   // ancho columna izquierda
  const LBL_W = 34                 // ancho para etiqueta (right-aligned desde ML)
  const LS    = 6.5                // interlineado

  const fila = (etiqueta: string, valor: string, maxW = FICW - LBL_W - 2) => {
    doc.setFont('helvetica', 'bold'); doc.setFontSize(7.5); doc.setTextColor(60, 60, 60)
    doc.text(etiqueta + ':', ML + LBL_W, y, { align: 'right' })
    doc.setFont('helvetica', 'normal'); doc.setFontSize(8.5); doc.setTextColor(0, 0, 0)
    doc.text(String(valor || ''), ML + LBL_W + 1, y, { maxWidth: maxW })
    y += LS
  }

  const filaDual = (l1: string, v1: string, l2: string, v2: string) => {
    const mid = ML + FICW * 0.52
    doc.setFont('helvetica', 'bold'); doc.setFontSize(7.5); doc.setTextColor(60, 60, 60)
    doc.text(l1 + ':', ML + LBL_W, y, { align: 'right' })
    doc.setFont('helvetica', 'normal'); doc.setFontSize(8.5); doc.setTextColor(0, 0, 0)
    doc.text(String(v1 || ''), ML + LBL_W + 1, y, { maxWidth: mid - ML - LBL_W - 3 })
    doc.setFont('helvetica', 'bold'); doc.setFontSize(7.5); doc.setTextColor(60, 60, 60)
    doc.text(l2 + ':', mid + 17, y, { align: 'right' })
    doc.setFont('helvetica', 'normal'); doc.setFontSize(8.5); doc.setTextColor(0, 0, 0)
    doc.text(String(v2 || ''), mid + 18, y, { maxWidth: FICW - (mid - ML) - 19 })
    y += LS
  }

  fila('Código',       String(emp?.PER_COD ?? ''))
  fila('Nombre',       emp?.PER_NOM ?? '')
  fila('Domicilio',    emp?.PER_DOM ?? '')
  fila('Localidad',    emp?.PER_LOC ?? '')
  filaDual('Ingreso',      formatFecha(emp?.PER_ING),  'Nacimiento', formatFecha(emp?.PER_FNA))
  filaDual('Doc. Tipo/Nro', `${emp?.PER_TDO ?? ''} ${emp?.PER_NDO ?? ''}`, 'CUIL', emp?.PER_CUI ?? '')
  filaDual('Teléfono',     emp?.PER_TEL ?? '',         'Celular',    emp?.PER_CEL ?? '')
  fila('Estado Civil', emp?.ESTADOCIVIL ?? emp?.PER_ECI_DES ?? '')
  fila('Empresa',      emp?.PER_EMD ?? '')
  filaDual('Convenio', emp?.PER_CDE ?? '', 'Categoría', emp?.PER_CAD ?? '')
  fila('CBU',          emp?.PER_CBU ?? '')
  fila('Cónyuge',      emp?.PER_NES ?? '')

  // Avanzar debajo de la foto si los campos terminaron antes
  y = Math.max(y, fotoY + FOTO_H + 2)
  doc.setDrawColor(180); doc.setLineWidth(0.2)
  doc.line(ML, y, PW - MR, y); y += 4

  // ── Puestos Laborales ──
  banner('Puestos Laborales')

  for (const p of datos.puestos ?? []) {
    CHEKY(22)
    doc.setDrawColor(180); doc.setLineWidth(0.2)

    // fila 1: código | puesto | fecha
    doc.rect(ML, y, CW, 7, 'S')
    doc.setFont('helvetica', 'bold'); doc.setFontSize(7.5); doc.setTextColor(60, 60, 60)
    doc.text('Código:', ML + 2, y + 4.5)
    doc.setFont('helvetica', 'normal'); doc.setFontSize(8); doc.setTextColor(0, 0, 0)
    doc.text(String(p.codigo ?? '').trim(), ML + 16, y + 4.5)
    doc.setFont('helvetica', 'bold'); doc.setFontSize(9); doc.setTextColor(20, 20, 20)
    doc.text(String(p.puesto ?? '').trim(), ML + 45, y + 4.5, { maxWidth: 100 })
    doc.setFont('helvetica', 'bold'); doc.setFontSize(7.5); doc.setTextColor(60, 60, 60)
    doc.text('Fecha:', PW - MR - 38, y + 4.5)
    doc.setFont('helvetica', 'normal'); doc.setFontSize(8); doc.setTextColor(0, 0, 0)
    doc.text(formatFecha(p.fecha ?? p.desde), PW - MR - 26, y + 4.5)
    y += 7

    // fila 2: departamento | reporta
    doc.rect(ML, y, CW, 6.5, 'S')
    doc.setFont('helvetica', 'bold'); doc.setFontSize(7.5); doc.setTextColor(60, 60, 60)
    doc.text('Departamento:', ML + 2, y + 4.5)
    doc.setFont('helvetica', 'normal'); doc.setFontSize(8); doc.setTextColor(0, 0, 0)
    doc.text(String(p.departamento ?? '').trim(), ML + 28, y + 4.5, { maxWidth: 60 })
    doc.setFont('helvetica', 'bold'); doc.setFontSize(7.5); doc.setTextColor(60, 60, 60)
    doc.text('Reporta a:', ML + 95, y + 4.5)
    doc.setFont('helvetica', 'normal'); doc.setFontSize(8); doc.setTextColor(0, 0, 0)
    doc.text(String(p.reporta ?? '').trim(), ML + 112, y + 4.5, { maxWidth: 60 })
    y += 6.5

    // fila 3: objetivos
    if (p.objetivo) {
      CHEKY(10)
      const lineas = doc.splitTextToSize(String(p.objetivo).replace(/[\r\n]+/g, ' ').trim(), CW - 28)
      const hObj = lineas.length * 4.5 + 4
      doc.rect(ML, y, CW, hObj, 'S')
      doc.setFont('helvetica', 'bold'); doc.setFontSize(7.5); doc.setTextColor(60, 60, 60)
      doc.text('Objetivos:', ML + 2, y + 4.5)
      doc.setFont('helvetica', 'normal'); doc.setFontSize(8); doc.setTextColor(0, 0, 0)
      doc.text(lineas, ML + 22, y + 4.5)
      y += hObj
    }
    y += 2
  }

  // ── Tareas ──
  if ((datos.tareas ?? []).length > 0) {
    y += 2; banner('Tareas')
    const TW = CW * 0.78, CW2 = CW * 0.22
    hrow([{ w: TW, txt: 'Tarea' }, { w: CW2, txt: 'Calificar' }])

    for (const t of datos.tareas) {
      CHEKY(8)
      const lineas = doc.splitTextToSize(String(t.tarea ?? '').trim(), TW - 4)
      const rh = Math.max(lineas.length * 4.5 + 3, 7)
      doc.setDrawColor(200); doc.setLineWidth(0.15)
      doc.rect(ML, y, TW, rh, 'S')
      doc.rect(ML + TW, y, CW2, rh, 'S')
      doc.setFont('helvetica', 'normal'); doc.setFontSize(8); doc.setTextColor(0, 0, 0)
      doc.text(lineas, ML + 2, y + 4)
      y += rh
    }
  }

  // ── Personal a Cargo ──
  if ((datos.subordinados ?? []).length > 0) {
    y += 4; banner('Personal a Cargo')
    const NW = CW * 0.6, SW = CW * 0.4
    hrow([{ w: NW, txt: 'Nombres' }, { w: SW, txt: 'Puestos' }])

    for (const s of datos.subordinados) {
      CHEKY(7)
      doc.setDrawColor(200); doc.setLineWidth(0.15)
      doc.rect(ML, y, NW, 6.5, 'S')
      doc.rect(ML + NW, y, SW, 6.5, 'S')
      doc.setFont('helvetica', 'normal'); doc.setFontSize(8); doc.setTextColor(0, 0, 0)
      doc.text(String(s.nombre ?? '').trim().toUpperCase(), ML + 2, y + 4.5, { maxWidth: NW - 4 })
      doc.text(String(s.puesto ?? '').trim().toUpperCase(), ML + NW + 2, y + 4.5, { maxWidth: SW - 4 })
      y += 6.5
    }
  }

  // ── Pie de página ──
  const totalPags = (doc as any).internal.getNumberOfPages()
  for (let i = 1; i <= totalPags; i++) {
    doc.setPage(i)
    doc.setFont('helvetica', 'italic'); doc.setFontSize(7); doc.setTextColor(130)
    doc.text(`Página ${i} de ${totalPags}`, PW / 2, 291, { align: 'center' })
    doc.text(new Date().toLocaleDateString('es-AR'), PW - MR, 291, { align: 'right' })
  }

  const nombre = `Puestos_${emp?.PER_LEG ?? emp?.PER_COD}.pdf`
  pdfModalNombre.value = nombre
  pdfModalPreview.value = true
  pdfModalUrl.value = URL.createObjectURL(doc.output('blob'))
}

// ── Modal PDF ───────────────────────────────────────────────────
const pdfModalUrl    = ref('')
const pdfModalNombre = ref('')
// true → se muestra en iframe (PDF/imagen). false → solo descarga (XLS/DOC/etc.)
const pdfModalPreview = ref(true)
const cerrarPdfModal = () => {
  URL.revokeObjectURL(pdfModalUrl.value)
  pdfModalUrl.value = ''
  pdfModalPreview.value = true
}

// ── Documentación (grilla con filtro + edición de fecha) ─────────────────────────
const docFiltro = ref('')
const docGuardando = ref(false)
const docMsg = ref('')
const docSeleccionado = ref<number | null>(null)   // UNICO del documento seleccionado
// Panel "Agregar documento"
const docNuevo = reactive<{ tipo: string; fecha: string; observaciones: string; archivo: File | null }>({
  tipo: '', fecha: '', observaciones: '', archivo: null,
})
const docFileInput = ref<HTMLInputElement | null>(null)
const docAgregando = ref(false)
const docAgMsg = ref('')

const docArchivoSel = (e: Event) => {
  docNuevo.archivo = (e.target as HTMLInputElement).files?.[0] ?? null
}

/** Previsualiza el archivo elegido (antes de importarlo) en el visor. */
const docArchivoVer = () => {
  if (docNuevo.archivo) docVisor.value?.open(docNuevo.archivo, docNuevo.archivo.name)
}

/** Sube un nuevo documento (tipo + fecha + observaciones + archivo). */
const documentoAgregar = async () => {
  if (!empActual.value) return
  if (!docNuevo.tipo)    { docAgMsg.value = 'Falta el tipo de documento'; return }
  if (!docNuevo.fecha)   { docAgMsg.value = 'Falta la fecha del documento'; return }
  if (!docNuevo.archivo) { docAgMsg.value = 'Falta seleccionar el archivo'; return }
  if (!confirm(`¿Desea importar el archivo "${docNuevo.archivo.name}"?`)) return
  docAgregando.value = true
  docAgMsg.value = ''
  try {
    const fd = new FormData()
    fd.append('tipo', docNuevo.tipo)
    fd.append('fecha', docNuevo.fecha || '')
    fd.append('observaciones', docNuevo.observaciones || '')
    fd.append('archivo', docNuevo.archivo)
    await api.post(`/empleados/${empActual.value.PER_COD}/documentos`, fd,
      { headers: { 'Content-Type': 'multipart/form-data' } })
    // Recargar la grilla
    const { data } = await api.get(`/empleados/${empActual.value.PER_COD}/documentos`)
    tabData.documentos = (data as any[]).map(d => ({ ...d, fecha: toInputDate(d.fecha) }))
    // Limpiar el panel
    docNuevo.tipo = ''; docNuevo.fecha = ''; docNuevo.observaciones = ''; docNuevo.archivo = null
    if (docFileInput.value) docFileInput.value.value = ''
    docAgMsg.value = 'Documento agregado'
    setTimeout(() => { docAgMsg.value = '' }, 3000)
  } catch (e) {
    console.error('Error agregando documento', e)
    docAgMsg.value = 'Error al agregar el documento'
  } finally {
    docAgregando.value = false
  }
}
/** Filtra los documentos por DETALLE TIPO (réplica del filtro FoxPro sobre doc_tdd). */
const documentosFiltrados = computed(() => {
  const f = docFiltro.value.trim().toLowerCase()
  const docs = tabData.documentos as any[]
  if (!f) return docs
  return docs.filter(d => (d.detalle ?? '').toLowerCase().includes(f))
})

/** Visualiza un documento de la fila (ojito) en el visor unificado. */
const docFilaVer = async (d: any) => {
  if (!d || !empActual.value) return
  try {
    const resp = await api.get(`/empleados/${empActual.value.PER_COD}/documentos/${d.id}/ver`, { responseType: 'blob' })
    const ext = (d.ext ?? 'pdf').trim().toLowerCase()
    docVisor.value?.open(resp.data as Blob, `${(d.nombre ?? 'documento').trim()}.${ext}`)
  } catch (e) { console.error('Error visualizando documento', e); docMsg.value = 'No se pudo abrir el documento' }
}

/** Visualiza el documento seleccionado en el modal PDF (con descargar/imprimir). */
const documentoVisualizar = async () => {
  if (docSeleccionado.value == null || !empActual.value) return
  docMsg.value = ''
  try {
    const resp = await api.get(
      `/empleados/${empActual.value.PER_COD}/documentos/${docSeleccionado.value}/ver`,
      { responseType: 'blob' }
    )
    const doc = (tabData.documentos as any[]).find(d => d.id === docSeleccionado.value)
    const ext = (doc?.ext ?? 'pdf').trim().toLowerCase()
    const nombre = `${(doc?.nombre ?? 'documento').trim()}.${ext}`
    docVisor.value?.open(resp.data as Blob, nombre)
  } catch (e) {
    console.error('Error visualizando documento', e)
    docMsg.value = 'No se pudo abrir el documento'
  }
}

/** Elimina el documento seleccionado (con confirmación). */
const documentoEliminar = async () => {
  if (docSeleccionado.value == null || !empActual.value) return
  const doc = (tabData.documentos as any[]).find(d => d.id === docSeleccionado.value)
  if (!confirm(`¿Eliminar el documento "${doc?.nombre?.trim() ?? ''}"?`)) return
  docMsg.value = ''
  try {
    await api.delete(`/empleados/${empActual.value.PER_COD}/documentos/${docSeleccionado.value}`)
    tabData.documentos = (tabData.documentos as any[]).filter(d => d.id !== docSeleccionado.value)
    docSeleccionado.value = null
    docMsg.value = 'Documento eliminado'
    setTimeout(() => { docMsg.value = '' }, 3000)
  } catch (e) {
    console.error('Error eliminando documento', e)
    docMsg.value = 'Error al eliminar'
  }
}

/** Guarda las fechas editadas de los documentos ("Confirmar Cambio"). */
const documentosConfirmar = async () => {
  if (!empActual.value) return
  docGuardando.value = true
  docMsg.value = ''
  try {
    const payload = (tabData.documentos as any[]).map(d => ({ id: d.id, fecha: d.fecha || null }))
    await api.put(`/empleados/${empActual.value.PER_COD}/documentos`, { documentos: payload })
    docMsg.value = 'Cambios guardados'
    setTimeout(() => { docMsg.value = '' }, 3000)
  } catch (e) {
    console.error('Error guardando documentos', e)
    docMsg.value = 'Error al guardar'
  } finally {
    docGuardando.value = false
  }
}

// ── Personal a Cargo (selector con checkboxes) ───────────────────────────────────
const acargoEmpleados = ref<any[]>([])
const acargoAsignados = ref<Set<number>>(new Set())
const acargoFiltro = ref('')
const acargoGuardando = ref(false)
const acargoMsg = ref('')

const acargoFiltrados = computed(() => {
  const f = acargoFiltro.value.trim().toLowerCase()
  if (!f) return acargoEmpleados.value
  return acargoEmpleados.value.filter(e =>
    (e.nom ?? '').toLowerCase().includes(f) || String(e.cod).includes(f))
})

const acargoToggle = (cod: number) => {
  const s = new Set(acargoAsignados.value)
  if (s.has(cod)) s.delete(cod); else s.add(cod)
  acargoAsignados.value = s
}

/** Graba el set de personal a cargo (GRABAR PERSONAL A CARGO). */
const acargoGuardar = async () => {
  if (!empActual.value) return
  acargoGuardando.value = true
  acargoMsg.value = ''
  try {
    await api.put(`/empleados/${empActual.value.PER_COD}/subordinados`,
      { asignados: Array.from(acargoAsignados.value) })
    acargoMsg.value = 'Personal a cargo guardado'
    setTimeout(() => { acargoMsg.value = '' }, 3000)
  } catch (e) {
    console.error('Error guardando personal a cargo', e)
    acargoMsg.value = 'Error al guardar'
  } finally {
    acargoGuardando.value = false
  }
}

/** PDF: informe GENERAL de personal a cargo de toda la empresa (réplica FoxPro). */
const imprimirAcargoPDF = async () => {
  let rows: any[] = []
  try {
    const { data } = await api.get('/empleados/personal-a-cargo-informe')
    rows = data
  } catch (e) {
    console.error('Error generando informe', e); return
  }

  // Agrupar por jefe (las filas ya vienen ordenadas por jefe y subordinado)
  const jefes: { cod: number; nom: string; subs: { cod: number; nom: string }[] }[] = []
  const idx = new Map<number, number>()
  for (const r of rows) {
    if (!idx.has(r.jefe_cod)) {
      idx.set(r.jefe_cod, jefes.length)
      jefes.push({ cod: r.jefe_cod, nom: r.jefe_nom, subs: [] })
    }
    jefes[idx.get(r.jefe_cod)!].subs.push({ cod: r.sub_cod, nom: r.sub_nom })
  }

  const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })
  const PW = 210, ML = 14, MR = 14, maxX = PW - MR
  let y = 14
  doc.setFont('helvetica', 'bold'); doc.setFontSize(13)
  doc.text('INFORME GRAL. DE PERSONAL A CARGO', PW / 2, y, { align: 'center' }); y += 9
  doc.setFontSize(9)

  // Flujo de tokens (jefe en negrita, subordinados normales) con wrap
  const flujo = (tokens: { t: string; b?: boolean }[]) => {
    let x = ML
    for (const tok of tokens) {
      doc.setFont('helvetica', tok.b ? 'bold' : 'normal')
      const w = doc.getTextWidth(tok.t + '   ')
      if (x + w > maxX) {
        y += 4.5; x = ML
        if (y > 282) { doc.addPage(); y = 16 }
      }
      doc.setTextColor(tok.b ? 27 : 0, tok.b ? 67 : 0, tok.b ? 50 : 0)
      doc.text(tok.t, x, y)
      x += w
    }
    y += 7   // separación entre bloques de jefes
  }

  for (const j of jefes) {
    if (y > 282) { doc.addPage(); y = 16 }
    const tokens = [{ t: `${j.nom} (${j.cod})`, b: true },
      ...j.subs.map(s => ({ t: `${s.nom} (${s.cod})` }))]
    flujo(tokens)
  }

  // Pie de página
  const total = doc.getNumberOfPages()
  const pie = `${new Date().toLocaleString('es-AR')} - ${(authStore.usuario?.NOMBRE ?? '').trim()}`
  for (let p = 1; p <= total; p++) {
    doc.setPage(p)
    doc.setFont('helvetica', 'normal'); doc.setFontSize(7); doc.setTextColor(90, 90, 90)
    doc.text(pie, ML, 292)
    doc.text(`Página ${p} de ${total}`, PW - MR, 292, { align: 'right' })
  }

  cerrarPdfModal()
  pdfModalNombre.value = 'Personal_a_Cargo.pdf'
  pdfModalPreview.value = true
  pdfModalUrl.value = URL.createObjectURL(doc.output('blob'))
}

/** PDF: Legajo Histórico del empleado seleccionado (réplica FoxPro "legajo_historico"). */
const imprimirLegajoPDF = async () => {
  const emp = empActual.value
  if (!emp || generandoLegajo.value) return
  generandoLegajo.value = true
  try {
    const { data } = await api.get(`/empleados/${emp.PER_COD}/legajo`)

    // Foto a base64 (si hay)
    let fotoB64 = '', fotoExt = 'JPEG'
    if (fotoUrl.value) {
      try {
        const blob = await (await fetch(fotoUrl.value)).blob()
        fotoB64 = await new Promise<string>(res => {
          const r = new FileReader(); r.onload = () => res(r.result as string); r.onerror = () => res(''); r.readAsDataURL(blob)
        })
        fotoExt = fotoB64.startsWith('data:image/png') ? 'PNG' : 'JPEG'
      } catch { /* sin foto */ }
    }

    const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })
    const PW = 210, PH = 297, ML = 12, MR = 12, maxX = PW - MR
    let y = 0

    const checkPage = (need = 6) => { if (y + need > PH - 12) { doc.addPage(); y = 14 } }
    const seccion = (t: string) => {
      checkPage(12); y += 3
      doc.setFillColor(27, 67, 50); doc.rect(ML, y - 4, PW - ML - MR, 6, 'F')
      doc.setFont('helvetica', 'bold'); doc.setFontSize(9); doc.setTextColor(255, 255, 255)
      doc.text(t, ML + 2, y); y += 7; doc.setTextColor(0, 0, 0)
    }
    const sinDatos = () => {
      doc.setFont('helvetica', 'italic'); doc.setFontSize(8); doc.setTextColor(120, 120, 120)
      doc.text('Sin datos.', ML + 2, y); y += 5; doc.setTextColor(0, 0, 0)
    }
    // Tabla genérica con cabecera y wrap básico
    const tabla = (cols: { t: string; k: string; w: number; f?: (v: any) => string }[], rows: any[]) => {
      if (!rows || rows.length === 0) { sinDatos(); return }
      const drawHead = () => {
        doc.setFillColor(229, 231, 235); doc.rect(ML, y - 4, PW - ML - MR, 5.5, 'F')
        doc.setFont('helvetica', 'bold'); doc.setFontSize(7.5); doc.setTextColor(30, 41, 59)
        let x = ML; for (const c of cols) { doc.text(c.t, x + 1, y); x += c.w }
        y += 5
      }
      drawHead()
      doc.setFont('helvetica', 'normal'); doc.setTextColor(30, 41, 59)
      let i = 0
      for (const r of rows) {
        if (y + 5 > PH - 12) { doc.addPage(); y = 14; drawHead(); doc.setFont('helvetica', 'normal') }
        if (i % 2 === 1) { doc.setFillColor(247, 250, 252); doc.rect(ML, y - 3.5, PW - ML - MR, 5, 'F') }
        let x = ML; doc.setFontSize(7.5)
        for (const c of cols) {
          let v = String(c.f ? c.f(r[c.k]) : (r[c.k] ?? ''))
          while (v && doc.getTextWidth(v) > c.w - 2) v = v.slice(0, -1)
          doc.text(v, x + 1, y); x += c.w
        }
        y += 5; i++
      }
    }
    // Lista de viñetas (cualidades, educación, competencias)
    const vinetas = (rows: any[]) => {
      if (!rows || rows.length === 0) { sinDatos(); return }
      doc.setFont('helvetica', 'normal'); doc.setFontSize(8); doc.setTextColor(30, 41, 59)
      for (const r of rows) {
        const txt = String(r.descripcion ?? '').trim(); if (!txt) continue
        const lines = doc.splitTextToSize(txt, PW - ML - MR - 8)
        checkPage(lines.length * 4 + 1)
        doc.text('•', ML + 2, y)
        doc.text(lines, ML + 6, y); y += lines.length * 4 + 1
      }
    }

    // ── Encabezado ──
    y = 14
    doc.setFont('helvetica', 'bold'); doc.setFontSize(14); doc.setTextColor(27, 67, 50)
    doc.text('LEGAJO HISTÓRICO', PW / 2, y, { align: 'center' }); y += 8
    doc.setTextColor(0, 0, 0)

    // Foto
    const fw = 26, fh = 32, fx = PW - MR - fw, fyTop = y
    doc.setDrawColor(150); doc.setLineWidth(0.3); doc.rect(fx, fyTop, fw, fh)
    if (fotoB64) { try { doc.addImage(fotoB64, fotoExt, fx + 0.4, fyTop + 0.4, fw - 0.8, fh - 0.8) } catch {} }

    const e = data.empleado ?? {}
    const campo = (lbl: string, val: any) => {
      doc.setFont('helvetica', 'bold'); doc.setFontSize(8); doc.setTextColor(27, 67, 50); doc.text(lbl, ML, y)
      doc.setFont('helvetica', 'normal'); doc.setTextColor(30, 41, 59)
      doc.text(String(val ?? '').toString().trim(), ML + 30, y); y += 4.8
    }
    const conv = (opciones.convenios.find((c: any) => c.CON_COD === e.PER_CON)?.CON_DES ?? e.PER_CDE ?? '').toString().trim()
    campo('Código / Legajo', `${e.PER_COD ?? ''}   ·   Leg. ${e.PER_LEG ?? ''}`)
    campo('Apellido y Nombre', e.PER_NOM)
    campo('Documento / CUIL', `${(e.PER_NDO ?? '').toString().trim()}   ·   ${(e.PER_CUI ?? '').toString().trim()}`)
    campo('Nacimiento', `${formatFecha(e.PER_FNA)}   ·   Estado civil: ${(data.estado_civil ?? '').toString().trim()}`)
    campo('Ingreso', formatFecha(e.PER_ING))
    campo('Empresa', (e.PER_EMD ?? '').toString().trim())
    campo('Sector / Categoría', `${(e.PER_SED ?? '').toString().trim()}   ·   ${(e.PER_CAD ?? '').toString().trim()}`)
    campo('Convenio', conv)
    campo('Domicilio', (e.PER_DOM ?? '').toString().trim())
    campo('Teléfono / Celular', `${(e.PER_TEL ?? '').toString().trim()}   ·   ${(e.PER_CEL ?? '').toString().trim()}`)
    if (data.celular) campo('Celular asignado', `${data.celular.numero ?? ''}  ${(data.celular.marca ?? '').trim()} ${(data.celular.modelo ?? '').trim()}`.trim())
    y = Math.max(y, fyTop + fh) + 2

    // ── Hijos ──
    seccion('FAMILIA — HIJOS')
    tabla([
      { t: 'Nombre', k: 'nombre', w: 90 },
      { t: 'Nacimiento', k: 'nacimiento', w: 40, f: formatFecha },
      { t: 'Situación', k: 'situacion', w: 56 },
    ], data.hijos ?? [])

    // ── Puestos ──
    seccion('PUESTOS ASIGNADOS')
    tabla([
      { t: 'Puesto', k: 'puesto', w: 56 },
      { t: 'Departamento', k: 'departamento', w: 42 },
      { t: 'Desde', k: 'desde', w: 22, f: formatFecha },
      { t: 'Hasta', k: 'hasta', w: 22, f: formatFecha },
      { t: 'Act.', k: 'activo', w: 12, f: (v) => Number(v) === 1 ? 'Sí' : '' },
      { t: 'Reporta a', k: 'reporta', w: 32 },
    ], data.puestos ?? [])

    // ── Perfil del puesto actual ──
    const pa = data.puesto_actual
    if (pa) {
      seccion(`PERFIL DEL PUESTO ACTUAL — ${String(pa.puesto ?? '').trim()}`)
      if (String(pa.objetivo ?? '').trim()) {
        doc.setFont('helvetica', 'bold'); doc.setFontSize(8); doc.text('Objetivo:', ML + 2, y); y += 4
        doc.setFont('helvetica', 'normal')
        const ol = doc.splitTextToSize(String(pa.objetivo).trim(), PW - ML - MR - 4)
        checkPage(ol.length * 4); doc.text(ol, ML + 4, y); y += ol.length * 4 + 2
      }
      const subt = (t: string) => { checkPage(8); doc.setFont('helvetica', 'bold'); doc.setFontSize(8); doc.setTextColor(27, 67, 50); doc.text(t, ML + 2, y); y += 4.5; doc.setTextColor(0, 0, 0) }
      subt('Cualidades');    vinetas(data.cualidades ?? [])
      subt('Educación');     vinetas(data.educacion ?? [])
      subt('Competencias');  vinetas(data.competencias ?? [])
      subt('Tareas');        tabla([{ t: 'Tarea', k: 'descripcion', w: 130 }, { t: 'Frecuencia', k: 'frecuencia', w: 56 }], data.tareas ?? [])
    }

    // ── Ropa ──
    seccion('ENTREGAS DE ROPA / UNIFORME')
    tabla([
      { t: 'Fecha', k: 'fecha', w: 26, f: formatFecha },
      { t: 'Cant.', k: 'cantidad', w: 16 },
      { t: 'Elemento', k: 'ropa', w: 86 },
      { t: 'Motivo', k: 'motivo', w: 58 },
    ], data.ropas ?? [])

    // ── Capacitación ──
    seccion('CAPACITACIÓN RECIBIDA')
    tabla([
      { t: 'Fecha', k: 'fecha', w: 22, f: formatFecha },
      { t: 'Capacitación', k: 'capacitacion', w: 66 },
      { t: 'Disertante', k: 'disertante', w: 44 },
      { t: 'Dur.', k: 'duracion', w: 14 },
      { t: 'Resultado', k: 'resultado', w: 40 },
    ], data.capacitaciones ?? [])

    // ── Apercibimientos ──
    seccion('APERCIBIMIENTOS')
    const aper = (data.apercibimientos ?? []).map((r: any) => ({
      fecha: r.APE_FEC,
      detalle: (r.APE_DET ?? r.APE_DES ?? r.APE_MOT ?? r.APE_OBS ?? r.APE_DETALLE ?? '').toString().trim(),
    }))
    tabla([
      { t: 'Fecha', k: 'fecha', w: 26, f: formatFecha },
      { t: 'Detalle', k: 'detalle', w: 160 },
    ], aper)

    // ── Pie ──
    const total = doc.getNumberOfPages()
    const pieTxt = `${new Date().toLocaleString('es-AR')} - ${(authStore.usuario?.NOMBRE ?? '').trim()}`
    for (let p = 1; p <= total; p++) {
      doc.setPage(p)
      doc.setFont('helvetica', 'normal'); doc.setFontSize(7); doc.setTextColor(90, 90, 90)
      doc.text(pieTxt, ML, PH - 6)
      doc.text(`Página ${p} de ${total}`, maxX, PH - 6, { align: 'right' })
    }

    cerrarPdfModal()
    pdfModalNombre.value = `Legajo_${(emp.PER_LEG ?? emp.PER_COD)}.pdf`
    pdfModalPreview.value = true
    pdfModalUrl.value = URL.createObjectURL(doc.output('blob'))
  } catch (err) {
    console.error('Error generando legajo', err)
  } finally {
    generandoLegajo.value = false
  }
}

// ── Faltas (master-detail + documentación respaldatoria) ─────────────────────────
const faltaSeleccionada = ref<number>(-1)
const faltaDocs = ref<any[]>([])
const faltaDocsCargando = ref(false)
const faltaDocSel = ref<number | null>(null)   // UNICO del documento seleccionado

const faltaSeleccionar = async (i: number) => {
  faltaSeleccionada.value = i
  faltaDocs.value = []
  faltaDocSel.value = null
  const f = (tabData.faltas as any[])[i]
  if (!f || !empActual.value || f.id == null) return
  faltaDocsCargando.value = true
  try {
    const { data } = await api.get(`/empleados/${empActual.value.PER_COD}/faltas/${f.id}/documentos`)
    faltaDocs.value = data
    if (data.length > 0) faltaDocSel.value = data[0].id
  } catch (e) {
    console.error('Error cargando documentos de falta', e)
  } finally {
    faltaDocsCargando.value = false
  }
}

/** Visualiza un documento respaldatorio en el modal (icono 👁️ del renglón). */
const faltaDocVisualizar = async (d: any) => {
  if (!d || !empActual.value) return
  try {
    const resp = await api.get(
      `/empleados/${empActual.value.PER_COD}/faltas/documentos/${d.id}/ver`,
      { responseType: 'blob' })
    const ext = (d.ext ?? 'jpg').trim().toLowerCase()
    docVisor.value?.open(resp.data as Blob, `${(d.nombre ?? 'documento').trim()}.${ext}`)
  } catch (e) {
    console.error('Error visualizando documento de falta', e)
  }
}

// ── Historial de cambios (master-detail + filtro + eliminar) ─────────────────────
const histSeleccionado = ref<number>(-1)
const histMarcados = ref<Set<string>>(new Set())   // claves de filas marcadas para eliminar
const histDesde = ref('')
const histHasta = ref('')
const histEliminando = ref(false)
/** Clave única de una fila de historial (per_hist no tiene PK). */
const histKey = (h: any): string => `${h.hla_fec}|${(h.hla_usu ?? '').trim()}|${(h.hla_cam ?? '').trim()}`

/** Filtra el historial por rango de fechas (Imprimir desde/hasta). */
const historialFiltrado = computed(() => {
  const items = tabData.historial as any[]
  const desde = histDesde.value
  const hasta = histHasta.value
  if (!desde && !hasta) return items
  return items.filter(h => {
    const f = String(h.hla_fec ?? '').slice(0, 10)   // yyyy-MM-dd
    if (desde && f < desde) return false
    if (hasta && f > hasta) return false
    return true
  })
})

/** Texto de cambios de la fila seleccionada (panel derecho). */
const historialDetalle = computed(() =>
  histSeleccionado.value >= 0 ? historialFiltrado.value[histSeleccionado.value] : null
)

const histToggleMarca = (h: any) => {
  const k = histKey(h)
  const s = new Set(histMarcados.value)
  if (s.has(k)) s.delete(k); else s.add(k)
  histMarcados.value = s
}

/** Elimina del historial las filas marcadas (ELIMINAR HISTORIAL). */
const eliminarHistorial = async () => {
  if (!empActual.value || histMarcados.value.size === 0) return
  if (!confirm(`¿Elimina el historial seleccionado?`)) return
  histEliminando.value = true
  try {
    const registros = (tabData.historial as any[])
      .filter(h => histMarcados.value.has(histKey(h)))
      .map(h => ({ hla_fec: h.hla_fec, hla_usu: h.hla_usu, hla_ter: h.hla_ter, hla_cam: h.hla_cam }))
    await api.delete(`/empleados/${empActual.value.PER_COD}/historial`, { data: { registros } })
    tabData.historial = (tabData.historial as any[]).filter(h => !histMarcados.value.has(histKey(h)))
    histMarcados.value = new Set()
    histSeleccionado.value = -1
  } catch (e) {
    console.error('Error eliminando historial', e)
  } finally {
    histEliminando.value = false
  }
}

/** Genera el PDF "Historial de Cambios" (réplica del informe FoxPro). */
const imprimirHistorialPDF = async () => {
  const emp = empActual.value
  if (!emp) return
  const rows = historialFiltrado.value as any[]

  const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })
  const PW = 210, ML = 12, MR = 12, CW = PW - ML - MR

  // Convenio (descripción desde el código)
  const convenio = (opciones.convenios.find((c: any) => c.CON_COD === emp.PER_CON)?.CON_DES ?? '').trim()

  // Foto a base64 (si hay)
  let fotoB64 = '', fotoExt = 'JPEG'
  if (fotoUrl.value) {
    try {
      const blob = await (await fetch(fotoUrl.value)).blob()
      fotoB64 = await new Promise<string>(res => {
        const r = new FileReader(); r.onload = () => res(r.result as string); r.readAsDataURL(blob)
      })
      fotoExt = fotoB64.startsWith('data:image/png') ? 'PNG' : 'JPEG'
    } catch { /* sin foto */ }
  }

  const fHora = (v: any): string => {
    if (!v) return ''
    const d = new Date(String(v).replace(' ', 'T'))
    if (isNaN(d.getTime())) return String(v)
    return d.toLocaleString('es-AR', { day: '2-digit', month: '2-digit', year: 'numeric',
      hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true })
  }
  const desde = histDesde.value ? formatFecha(histDesde.value) : '—'
  const hasta = histHasta.value ? formatFecha(histHasta.value) : formatFecha(new Date().toISOString())
  const usuario = (authStore.usuario?.NOMBRE ?? '').trim()

  // ── Encabezado (datos del empleado + foto), se repite por página ──
  const COL_FECHA = 42
  let y = 0
  const dibujarEncabezado = () => {
    y = 12
    doc.setFont('helvetica', 'bold'); doc.setFontSize(11); doc.setTextColor(0, 0, 0)
    doc.text(`Historial de Cambios desde el ${desde} hasta el ${hasta}`, ML, y)
    y += 6

    const fotoW = 24, fotoH = 30, fotoX = PW - MR - fotoW, fotoY = y
    doc.setDrawColor(120); doc.setLineWidth(0.3); doc.rect(fotoX, fotoY, fotoW, fotoH)
    if (fotoB64) { try { doc.addImage(fotoB64, fotoExt, fotoX + 0.4, fotoY + 0.4, fotoW - 0.8, fotoH - 0.8) } catch {} }

    doc.setFontSize(8)
    const campo = (lbl: string, val: any, x: number, yy: number, lblW = 20) => {
      doc.setFont('helvetica', 'bold'); doc.text(lbl, x, yy)
      doc.setFont('helvetica', 'normal'); doc.text(String(val ?? '').trim(), x + lblW, yy)
    }
    let yy = y + 4
    campo('Código', emp.PER_COD, ML, yy, 14); campo('Nombre', emp.PER_NOM, ML + 40, yy, 16); yy += 5
    campo('Domicilio', emp.PER_DOM, ML, yy, 18); yy += 5
    campo('Empresa', emp.PER_EMD, ML, yy, 18); campo('Categoría', emp.PER_CAD, ML + 95, yy, 18); yy += 5
    campo('Convenio', convenio, ML, yy, 18); campo('CUIL', emp.PER_CUI, ML + 95, yy, 12); yy += 5
    campo('Documento', `${(emp.PER_TDO ?? '').trim()} ${emp.PER_NDO ?? ''}`, ML, yy, 20)
    campo('Nacimiento', formatFecha(emp.PER_FNA), ML + 95, yy, 20); yy += 5
    campo('Ingreso', formatFecha(emp.PER_ING), ML, yy, 18)
    campo('Baja', formatFecha(emp.PER_BAJ), ML + 95, yy, 10); yy += 5
    campo('Paga Sindicato', emp.PER_SIN, ML, yy, 26); yy += 6
    y = Math.max(yy, fotoY + fotoH + 2)

    // Cabecera de la tabla
    doc.setFillColor(210, 230, 215)
    doc.rect(ML, y, COL_FECHA, 6, 'F'); doc.rect(ML + COL_FECHA, y, CW - COL_FECHA, 6, 'F')
    doc.setFont('helvetica', 'bold'); doc.setFontSize(8); doc.setTextColor(30, 30, 30)
    doc.text('FECHA', ML + 2, y + 4); doc.text('CAMBIOS', ML + COL_FECHA + 2, y + 4)
    y += 6
  }

  dibujarEncabezado()

  // ── Filas ──
  doc.setFont('helvetica', 'normal'); doc.setFontSize(8); doc.setTextColor(0, 0, 0)
  const anchoCam = CW - COL_FECHA - 4
  for (const h of rows) {
    const lineas = doc.splitTextToSize(String(h.hla_cam ?? '').trim(), anchoCam)
    const alto = Math.max(lineas.length * 3.6 + 2, 6)
    if (y + alto > 285) { doc.addPage(); dibujarEncabezado(); doc.setFont('helvetica', 'normal'); doc.setFontSize(8) }
    doc.setDrawColor(220); doc.setLineWidth(0.15)
    doc.rect(ML, y, COL_FECHA, alto); doc.rect(ML + COL_FECHA, y, CW - COL_FECHA, alto)
    doc.text(fHora(h.hla_fec), ML + 1.5, y + 4)
    doc.text(lineas, ML + COL_FECHA + 1.5, y + 4)
    y += alto
  }

  // ── Pie de página (fecha/usuario + Página X de Y) ──
  const total = doc.getNumberOfPages()
  const pie = `${new Date().toLocaleString('es-AR')} - ${usuario}`
  for (let p = 1; p <= total; p++) {
    doc.setPage(p)
    doc.setFont('helvetica', 'normal'); doc.setFontSize(7); doc.setTextColor(90, 90, 90)
    doc.text(pie, ML, 292)
    doc.text(`Página ${p} de ${total}`, PW - MR, 292, { align: 'right' })
  }

  // Mostrar en el modal PDF
  cerrarPdfModal()
  pdfModalNombre.value = `Historial_${emp.PER_LEG ?? emp.PER_COD}.pdf`
  pdfModalPreview.value = true
  pdfModalUrl.value = URL.createObjectURL(doc.output('blob'))
}

// ── Exámenes médicos (master-detail) ─────────────────────────────────────────────
const examenSeleccionado = ref<number>(-1)
const examenDocs = ref<any[]>([])
const examenDocsCargando = ref(false)
const examenDetalle = computed(() =>
  examenSeleccionado.value >= 0 ? (tabData.examenes as any[])[examenSeleccionado.value] : null
)
/** true si el registro es un CERTIFICADO (EXA_COE='C'); si no, es un EXAMEN. */
const examenEsCertificado = computed(() =>
  (examenDetalle.value?.certificado ?? '').trim().toUpperCase() === 'C'
)
/** "(R52) DOLOR, NO CLASIFICADO..." a partir de código + detalle de enfermedad. */
const examenEnfermedad = (e: any): string => {
  const cod = (e?.enf_cod ?? '').trim()
  const det = (e?.enf_det ?? '').trim()
  if (!cod && !det) return ''
  return (cod ? `(${cod}) ` : '') + det
}

/** Selecciona un examen y carga sus documentos digitales (documentos DOC_TIP='X'). */
const examenSeleccionar = async (i: number) => {
  examenSeleccionado.value = i
  examenDocs.value = []
  const e = (tabData.examenes as any[])[i]
  if (!e || !empActual.value || e.id == null) return
  examenDocsCargando.value = true
  try {
    const { data } = await api.get(
      `/empleados/${empActual.value.PER_COD}/examenes/${e.id}/documentos`
    )
    examenDocs.value = data
  } catch (err) {
    console.error('Error cargando documentos de examen', err)
  } finally {
    examenDocsCargando.value = false
  }
}

/** Visualiza un documento de examen (ojito) en el visor unificado. */
const examenDocVer = async (d: any) => {
  if (!d || !empActual.value || d.id == null) return
  try {
    const resp = await api.get(`/empleados/${empActual.value.PER_COD}/examenes/documentos/${d.id}/ver`, { responseType: 'blob' })
    const ext = (d.ext ?? 'pdf').trim().toLowerCase()
    docVisor.value?.open(resp.data as Blob, `${(d.nombre ?? 'documento').trim()}.${ext}`)
  } catch (err) { console.error('Error visualizando documento de examen', err) }
}

// ── Licencia de conducir ────────────────────────────────────────────────────────
// Categorías del carnet (combo "Carnet de Conducir") desde la tabla carn_cat
// (CRT_COD / CRT_DES), cargadas vía /empleados/opciones. El valor guardado en
// PER_LCx es el código; se muestra la descripción.

/** Descripción completa de una categoría de carnet (para tooltip del select). */
const carnetDesc = (cod: any): string => {
  const c = (opciones.carnetcategorias as any[]).find(x => x.cod === cod)
  return c?.det ?? ''
}

/** Vacía los datos de un carnet (botón "Eliminar Carnet"). */
const eliminarCarnet = (n: number) => {
  form.value['PER_LN' + n] = ''
  form.value['PER_LC' + n] = ''
  form.value['PER_LF' + n] = ''
}

/** true si la fecha de vencimiento ya pasó (resalta el vencimiento en rojo). */
const licVencida = (fecha: any): boolean => {
  if (!fecha) return false
  const f = String(fecha).slice(0, 10)
  if (f.startsWith('1900-')) return false
  return f < new Date().toISOString().slice(0, 10)
}

// ── Capacitación (solapa con dos grillas master-detail, réplica FoxPro) ──────────
// Grilla superior  = capacitaciones recibidas (master).
// Grilla inferior = "Documentos Digitales" de la capacitación seleccionada (detail).
// Al seleccionar una fila arriba, se cargan sus documentos abajo.

/** Índice de la capacitación seleccionada en tabData.capacitaciones (-1 = ninguna). */
const capSeleccionada = ref<number>(-1)
/** Documentos digitales de la capacitación seleccionada (grilla inferior). */
const capDocumentos = ref<any[]>([])
/** Spinner mientras se traen los documentos de la capacitación. */
const capDocsCargando = ref(false)
/** Objeto de la capacitación seleccionada (para mostrar su nombre en el header de docs). */
const capDetalleSel = computed(() =>
  capSeleccionada.value >= 0 ? (tabData.capacitaciones as any[])[capSeleccionada.value] : null
)

/**
 * Selecciona una capacitación (grilla superior) y carga sus documentos
 * digitales (grilla inferior) vía master-detail.
 *
 * Backend: GET /empleados/{cod}/capacitaciones/{capCod}/documentos
 * que replica el join FoxPro:
 *   cap_documentacion A INNER JOIN cap_empdoc B ON A.UNICO = B.cap_doc
 *   WHERE B.cap_nro = capCod AND B.cap_emp = perCod
 *     AND A.DOC_TIP = 'K' AND A.DOC_REF = capCod
 *
 * @param i  Índice de la fila en tabData.capacitaciones
 */
const capSeleccionar = async (i: number) => {
  capSeleccionada.value = i
  capDocumentos.value = []
  const c = (tabData.capacitaciones as any[])[i]
  // c.codigo = CAP_COD; si falta empleado/código no hay nada que consultar.
  if (!c || !empActual.value || c.codigo == null) return
  capDocsCargando.value = true
  try {
    const { data } = await api.get(
      `/empleados/${empActual.value.PER_COD}/capacitaciones/${c.codigo}/documentos`
    )
    capDocumentos.value = data
  } catch (e) {
    console.error('Error cargando documentos de capacitación', e)
  } finally {
    capDocsCargando.value = false
  }
}

/** Visualiza un documento de capacitación (ojito) en el visor unificado. */
const capDocVer = async (d: any) => {
  if (!d || d.unico == null) return
  try {
    const resp = await api.get(`/cap-doc/documento/${d.unico}/ver`, { responseType: 'blob' })
    const ext = (d.ext ?? 'pdf').trim().toLowerCase()
    docVisor.value?.open(resp.data as Blob, `${(d.nombre ?? 'documento').trim()}.${ext}`)
  } catch (e) { console.error('Error visualizando documento de capacitación', e) }
}

// ── Uniforme / EPP ──────────────────────────────────────────────
const ropaFechaSeleccionada = ref<string>('')
const ropaDesde = ref('')
const ropaHasta = ref('')

const ropaFiltrada = computed(() => {
  let items = tabData.ropa as any[]
  if (ropaDesde.value)  items = items.filter((r: any) => r.fecha >= ropaDesde.value)
  if (ropaHasta.value)  items = items.filter((r: any) => r.fecha <= ropaHasta.value)
  return items
})

const ropaFechasUnicas = computed(() => {
  const map = new Map<string, boolean>()
  for (const r of ropaFiltrada.value) {
    const f = r.fecha?.slice(0, 10) ?? ''
    if (!f) continue
    map.set(f, map.get(f) || !!r.stk)
  }
  return Array.from(map.entries())
    .sort((a, b) => b[0].localeCompare(a[0]))
    .map(([fecha, tieneStock]) => ({ fecha, tieneStock }))
})

const ropaDetalle = computed(() =>
  ropaFiltrada.value.filter((r: any) => r.fecha?.slice(0, 10) === ropaFechaSeleccionada.value)
)

const ropaConsultar = () => {
  // Filtra ambos paneles por el rango. Los computados ropaFiltrada/ropaFechasUnicas
  // se actualizan automáticamente; solo limpiamos la selección de la derecha.
  ropaFechaSeleccionada.value = ''
  // Auto-seleccionar la primera fecha si hay resultados
  if (ropaFechasUnicas.value.length > 0)
    ropaFechaSeleccionada.value = ropaFechasUnicas.value[0].fecha
}

// Genera la constancia PDF (normal = Res.299 o institucional = obsequio)
// Layout: tabla bordeada como el PDF de referencia FoxPro
const generarConstancia299 = async (
  emp: any,
  items: any[],
  empresa: any,
  puestos: any[],
  elementos: any[],
  esObsequio: boolean
): Promise<jsPDF> => {
  const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })
  const PW = 210, ML = 10, MR = 10, CW = PW - ML - MR
  let y = 10

  const BW = 0.3  // border width
  doc.setDrawColor(0); doc.setLineWidth(BW)

  // Dibuja celda bordeada y devuelve el X para texto interior
  const cell = (x: number, cy: number, w: number, h: number, fill = false) => {
    if (fill) { doc.setFillColor(240, 240, 240); doc.rect(x, cy, w, h, 'FD') }
    else doc.rect(x, cy, w, h, 'D')
  }

  // Texto dentro de celda (con padding interno de 1.5mm)
  const cellText = (txt: string, x: number, cy: number, w: number, h: number,
    opts: { bold?: boolean; size?: number; align?: 'left' | 'center' | 'right'; maxWidth?: number } = {}
  ) => {
    doc.setFont('helvetica', opts.bold ? 'bold' : 'normal')
    doc.setFontSize(opts.size ?? 8)
    doc.setTextColor(0, 0, 0)
    const tx = opts.align === 'center' ? x + w / 2 : opts.align === 'right' ? x + w - 1.5 : x + 1.5
    doc.text(String(txt ?? ''), tx, cy + h / 2 + 1.3,
      { align: opts.align ?? 'left', maxWidth: opts.maxWidth ?? w - 3 })
  }

  // ── Fila 1: Título ─────────────────────────────────────────────
  const ROW1H = 7
  cell(ML, y, CW, ROW1H)
  const titulo = esObsequio
    ? 'CONSTANCIA DE ENTREGA: OBSEQUIO INSTITUCIONAL'
    : 'CONSTANCIA DE ENTREGA DE ROPA DE TRABAJO Y ELEMENTOS DE PROTECCION PERSONAL'
  cellText(titulo, ML, y, CW, ROW1H, { bold: true, size: 10, align: 'center' })
  y += ROW1H

  // ── Fila 2: Empresa | CUIT ─────────────────────────────────────
  const ROW2H = 6, W_CUIT = 45
  cell(ML, y, CW - W_CUIT, ROW2H)
  cell(ML + CW - W_CUIT, y, W_CUIT, ROW2H)
  cellText(String(empresa.nombre ?? emp.PER_EMD ?? '').trim(), ML, y, CW - W_CUIT, ROW2H, { bold: true, size: 9 })
  cellText(String(empresa.cuit ?? '').trim(), ML + CW - W_CUIT, y, W_CUIT, ROW2H, { size: 9 })
  y += ROW2H

  // ── Fila 3: Domicilio | CP | Provincia ─────────────────────────
  const W_CP = 20, W_PROV = 40
  cell(ML, y, CW - W_CP - W_PROV, ROW2H)
  cell(ML + CW - W_CP - W_PROV, y, W_CP, ROW2H)
  cell(ML + CW - W_PROV, y, W_PROV, ROW2H)
  cellText(String(empresa.domicilio ?? '').trim() + (empresa.localidad ? '  ' + String(empresa.localidad).trim() : ''),
    ML, y, CW - W_CP - W_PROV, ROW2H, { size: 8 })
  cellText(String(empresa.cp ?? '').trim(), ML + CW - W_CP - W_PROV, y, W_CP, ROW2H, { align: 'center', size: 8 })
  cellText(String(empresa.provincia ?? '').trim(), ML + CW - W_PROV, y, W_PROV, ROW2H, { size: 8 })
  y += ROW2H

  // ── Fila 4: Nombre del Trabajador | DNI ───────────────────────
  const W_DNI = 50
  cell(ML, y, CW - W_DNI, ROW2H)
  cell(ML + CW - W_DNI, y, W_DNI, ROW2H)
  cellText(`Nombre del Trabajador: ${String(emp.PER_NOM ?? '').trim()}`, ML, y, CW - W_DNI, ROW2H, { bold: true, size: 8.5 })
  cellText(`D.N.I.: ${String(emp.PER_NDO ?? '').trim()}`, ML + CW - W_DNI, y, W_DNI, ROW2H, { bold: true, size: 8.5 })
  y += ROW2H

  if (!esObsequio) {
    // ── Fila 5: Cabecera elementos ───────────────────────────────
    const ROW5H = 5
    cell(ML, y, CW, ROW5H)
    cellText('Elementos de protección personal, necesarios para el trabajador, según el puesto de trabajo:...',
      ML, y, CW, ROW5H, { bold: true, size: 8 })
    y += ROW5H

    // ── Fila 6: Contenido elementos (altura variable) ────────────
    const elemDescs = elementos.map((e: any) => String(e.descripcion ?? '').trim()).filter(Boolean)
    const elemStr   = elemDescs.length > 0
      ? 'Elementos de Protección Personal, necesarios para el trabajador, según el puesto de trabajo:' + elemDescs.join('/')
      : 'Elementos de Protección Personal, necesarios para el trabajador, según el puesto de trabajo:'
    const txtFijo = 'Técnico Mecánico/Chofer/Baterista/Soldador:  ROPA DE TRABAJO* /CALZADO DE SEGURIDAD/GUANTES/CASCO/PROTECCION AUDITIVA/LENTES/COVID-19.\n* GRAFA/ IGNIFUGA/ KIT SOLDADOR.'
    const bloque = elemStr + '\n' + txtFijo
    doc.setFont('helvetica', 'normal'); doc.setFontSize(7.5)
    const bloqueLines = doc.splitTextToSize(bloque, CW - 3)
    const ROW6H = Math.max(bloqueLines.length * 4 + 4, 14)
    cell(ML, y, CW, ROW6H)
    doc.setTextColor(0, 0, 0)
    doc.text(bloqueLines, ML + 1.5, y + 4)
    y += ROW6H + 1
  }

  // ── Cabecera de la tabla de items ──────────────────────────────
  // Columnas: Producto | Tipo/Modelo | Marca\Talle | Certif/Obsequio | Cantidad | Fecha | Firma
  const W_PROD = 22, W_CERT = 28, W_OBS = 24, W_CAN = 14, W_FEC = 25
  const W_TIPO = esObsequio ? 58 : 55
  const W_MRC  = esObsequio ? 32 : 33
  const W_FIR  = CW - W_PROD - W_TIPO - W_MRC - (esObsequio ? W_OBS : W_CERT) - W_CAN - W_FEC

  const COLS = [
    { w: W_PROD, h: 'Producto' },
    { w: W_TIPO, h: 'Tipo / Modelo' },
    { w: W_MRC,  h: 'Marca \\ Talle' },
    esObsequio
      ? { w: W_OBS,  h: 'Tipo Obsequio' }
      : { w: W_CERT, h: 'Posee certificación\nSI / NO' },
    { w: W_CAN,  h: 'Cantidad' },
    { w: W_FEC,  h: 'Fecha de Entrega' },
    { w: W_FIR,  h: 'Firma del trabajador' },
  ]
  // Calcular altura del header según columna con más líneas
  doc.setFont('helvetica', 'bold'); doc.setFontSize(8)
  const hdrLineCounts = COLS.map(c => c.h.split('\n').length)
  const HDR_H = Math.max(Math.max(...hdrLineCounts) * 4.2 + 5, 8)
  let cx = ML
  COLS.forEach(c => { cell(cx, y, c.w, HDR_H); cx += c.w })
  cx = ML
  doc.setTextColor(0, 0, 0)
  COLS.forEach(c => {
    const lines = c.h.split('\n')
    const textH = lines.length * 4.2
    let ty = y + (HDR_H - textH) / 2 + 3.5
    lines.forEach((l: string) => { doc.text(l, cx + c.w / 2, ty, { align: 'center', maxWidth: c.w - 2 }); ty += 4.2 })
    cx += c.w
  })
  y += HDR_H

  // ── Procesar items ─────────────────────────────────────────────
  // Producto  = ROP_COD (código del artículo, como FoxPro)
  // Tipo/Mod  = descripción completa (ENR_DES)
  // Marca\Tal = ALLTRIM(MARCA_DES) + " \ " + ALLTRIM(TALLE_DES)
  const procesados = items.map((r: any) => {
    const marcaDes = String(r.marca ?? r.marca2 ?? '').trim()
    const talleDes = String(r.talle ?? r.talle2 ?? '').trim()
    return {
      producto:  String(r.rop_cod ?? '').trim(),
      tipo:      String(r.descripcion ?? '').trim(),
      marcaTalle: marcaDes + (talleDes ? ' \\ ' + talleDes : ''),
      certif:    esObsequio ? 'SI' : (String(r.certificado ?? '').trim().toUpperCase() === 'S' ? 'SI' : 'NO'),
      cantidad:  String(r.cantidad ?? ''),
      fecha:     formatFecha(String(r.fecha ?? '')),
    }
  })

  // Mínimo 15 filas
  while (procesados.length < 15)
    procesados.push({ producto: '', tipo: '', marcaTalle: '', certif: '', cantidad: '', fecha: '' })

  const FS_ROW = 8
  const LINE_H = 4.2   // mm por línea a fontSize 8
  const PAD_V  = 2.5   // padding vertical dentro de celda

  doc.setFont('helvetica', 'normal'); doc.setFontSize(FS_ROW)

  for (const item of procesados) {
    const vals = [item.producto, item.tipo, item.marcaTalle, item.certif, item.cantidad, item.fecha, '']

    // Calcular altura necesaria: máximo de líneas en cualquier celda
    const lineCounts = COLS.map((c, i) => {
      if (!vals[i]) return 1
      const lines = doc.splitTextToSize(vals[i], c.w - 3)
      return lines.length
    })
    const maxLines = Math.max(...lineCounts)
    const ROW_H = Math.max(maxLines * LINE_H + PAD_V * 2, 6)

    if (y + ROW_H > 270) { doc.addPage(); y = 10 }

    cx = ML
    // Índices de columnas centradas: producto(0), certif(3 si !obs), cantidad
    const idxCantidad = esObsequio ? 3 : 4
    COLS.forEach((c, i) => {
      cell(cx, y, c.w, ROW_H)
      doc.setFont('helvetica', 'normal'); doc.setFontSize(FS_ROW); doc.setTextColor(0, 0, 0)
      const centered = i === 0 || i === idxCantidad || (!esObsequio && i === 3)
      if (centered) {
        const lines = doc.splitTextToSize(vals[i] ?? '', c.w - 3)
        const textH = lines.length * LINE_H
        let ty = y + (ROW_H - textH) / 2 + LINE_H * 0.8
        lines.forEach((l: string) => { doc.text(l, cx + c.w / 2, ty, { align: 'center' }); ty += LINE_H })
      } else {
        const lines = doc.splitTextToSize(vals[i] ?? '', c.w - 3)
        const textH = lines.length * LINE_H
        let ty = y + (ROW_H - textH) / 2 + LINE_H * 0.8
        lines.forEach((l: string) => { doc.text(l, cx + 1.5, ty); ty += LINE_H })
      }
      cx += c.w
    })
    y += ROW_H
  }

  if (esObsequio) {
    // ── Formulario obsequio: footer dentro de celda bordeada ──────
    // (igual al PDF de referencia: no hay "Quedo notificado" separado)
    const footerObs = 'Información adicional: Dejando constancia de conformidad con la misma, para su efecto firma el colaborador este registro.'
    doc.setFont('helvetica', 'bold'); doc.setFontSize(8)
    const obsLines = doc.splitTextToSize(footerObs, CW - 80)
    const OBS_H = Math.max(obsLines.length * 4.5 + 8, 18)
    cell(ML, y, CW, OBS_H)
    doc.setFont('helvetica', 'bold'); doc.setFontSize(8); doc.setTextColor(0, 0, 0)
    doc.text(obsLines, ML + 2, y + 5.5)
    // línea punteada de firma a la derecha
    doc.setLineDashPattern([1, 1], 0)
    doc.line(ML + CW - 62, y + OBS_H - 5, ML + CW - 3, y + OBS_H - 5)
    doc.setLineDashPattern([], 0)
    y += OBS_H
  } else {
    // ── Formulario normal: fila de cierre + "Quedo notificado" + footer ──
    cell(ML, y, CW, 6)
    y += 6

    const FOOTER_Y = 280
    const BOX_H    = 18
    const BOX_Y    = FOOTER_Y - BOX_H - 6
    const BOX_W    = 70

    doc.setDrawColor(0); doc.setLineWidth(BW)
    doc.rect(ML + CW - BOX_W, BOX_Y, BOX_W, BOX_H, 'D')
    doc.setFont('helvetica', 'normal'); doc.setFontSize(8); doc.setTextColor(0, 0, 0)
    doc.text('Quedo notificado:', ML + CW - BOX_W + 2, BOX_Y + 5)
    doc.setLineDashPattern([1, 1], 0)
    doc.line(ML + CW - BOX_W + 4, BOX_Y + BOX_H - 4, ML + CW - 4, BOX_Y + BOX_H - 4)
    doc.setLineDashPattern([], 0)

    const footerTxt = 'Según Resolución 299/11 de la Superintendencia de Riesgo de Trabajo este formulario será de utilización obligatoria por parte de los empleadores, donde se registrarán las respectivas entregas de ropa de trabajo y Elementos de Protección Personal.'
    doc.setFont('helvetica', 'bold'); doc.setFontSize(7.5); doc.setTextColor(0, 0, 0)
    const fLines = doc.splitTextToSize(footerTxt, CW)
    doc.text(fLines, ML, FOOTER_Y)
  }

  return doc
}

const imprimirConstanciaRopa = async () => {
  try {
    const emp   = empActual.value as any
    const items = ropaFechaSeleccionada.value ? ropaDetalle.value : ropaFiltrada.value

    if (!items.length) { alert('No hay items para imprimir.'); return }

    const { data: extra } = await api.get(`/empleados/${emp.PER_COD}/constancia-ropa`)
    const empresa   = extra.empresa   ?? {}
    const puestos   = (extra.puestos  ?? []) as any[]
    const elementos = (extra.elementos ?? []) as any[]

    const esObs = (r: any) => r.obsequio == 1 || r.obsequio === true || r.obsequio === 'T' || r.obsequio === '.T.'
    const hayObsequio  = items.some(esObs)
    const soloObsequio = items.length > 0 && items.every(esObs)

    let doc: jsPDF

    if (soloObsequio) {
      doc = await generarConstancia299(emp, items, empresa, puestos, elementos, true)
    } else if (!hayObsequio) {
      doc = await generarConstancia299(emp, items, empresa, puestos, elementos, false)
    } else {
      const docNormal = await generarConstancia299(emp, items.filter((r: any) => !esObs(r)), empresa, puestos, elementos, false)
      const docObs    = await generarConstancia299(emp, items.filter(esObs), empresa, puestos, elementos, true)
      const pagesObs  = (docObs as any).internal.getNumberOfPages()
      for (let p = 1; p <= pagesObs; p++) {
        docNormal.addPage()
        const pageContent = (docObs as any).internal.pages[p]
        if (pageContent) (docNormal as any).internal.pages[(docNormal as any).internal.getNumberOfPages()] = pageContent
      }
      doc = docNormal
    }

    const sufijo = soloObsequio ? 'Obsequio' : (hayObsequio ? 'Mixto' : '299')
    const nombre = `Constancia_${sufijo}_${emp?.PER_LEG ?? emp?.PER_COD}.pdf`
    pdfModalNombre.value = nombre
    pdfModalPreview.value = true
    pdfModalUrl.value = URL.createObjectURL(doc.output('blob'))
  } catch (e: any) {
    console.error('[imprimirConstanciaRopa]', e)
    alert(`Error al generar constancia: ${e?.message ?? e}`)
  }
}

// ── Calificaciones ──────────────────────────────────────────────
const calSeleccionada = ref<any>(null)
const calEliminando   = ref(false)
const calMsg          = ref('')

const calificacionEliminar = async () => {
  if (!calSeleccionada.value || !empActual.value) return
  if (!confirm('¿Eliminar esta calificación?')) return
  calEliminando.value = true
  calMsg.value = ''
  try {
    const params = new URLSearchParams({
      CAL_FEC:  calSeleccionada.value.CAL_FEC,
      CAL_PUE:  calSeleccionada.value.CAL_PUE,
      CAL_CUIL: calSeleccionada.value.CAL_CUIL,
    })
    await api.delete(`/empleados/${empActual.value.PER_COD}/calificacion?${params}`)
    tabData.puestos.calificaciones = tabData.puestos.calificaciones.filter(
      (c: any) => !(c.CAL_FEC === calSeleccionada.value.CAL_FEC &&
                    c.CAL_PUE === calSeleccionada.value.CAL_PUE)
    )
    calSeleccionada.value = null
    calMsg.value = 'Calificación eliminada'
    setTimeout(() => { calMsg.value = '' }, 3000)
  } catch {
    calMsg.value = 'Error al eliminar'
  } finally {
    calEliminando.value = false
  }
}

// Al elegir un empleado, la lista de la izquierda se colapsa (ya no hace falta y da lugar al detalle).
const listaColapsada = ref(false)

const seleccionar = async (cod: number) => {
  modoNuevo.value  = false
  modoEdicion.value = false
  tabActual.value  = 'datos'
  subTab.value     = 'personal'
  fotoUrl.value    = null
  hijosLocal.value = []
  Object.assign(familiaForm, { PER_NES: '', PER_PADRE: '', PER_MADRE: '', PER_EMBSN: 'N', PER_EMBNOM: '', PER_EMBCBU: '' })
  hijosMsg.value   = ''
  calSeleccionada.value = null
  calMsg.value = ''
  ropaFechaSeleccionada.value = ''
  ropaDesde.value = ''
  ropaHasta.value = ''
  capSeleccionada.value = -1
  capDocumentos.value = []
  examenSeleccionado.value = -1
  examenDocs.value = []
  histSeleccionado.value = -1
  histMarcados.value = new Set()
  histDesde.value = ''
  histHasta.value = ''
  acargoEmpleados.value = []
  acargoAsignados.value = new Set()
  acargoFiltro.value = ''
  acargoMsg.value = ''
  faltaSeleccionada.value = -1
  faltaDocs.value = []
  faltaDocSel.value = null
  loadedTabs.value.clear()
  // Limpiar tabData
  Object.assign(tabData, {
    hijos: [], puestos: { puestos: [], calificaciones: [], tareas: [], subordinados: [] },
    capacitaciones: [], examenes: [], historial: [], documentos: [],
    subordinados: [], faltas: [], celulares: [], ropa: [], epp: [], obrasocial: [], tarjetas: [],
  })
  const { data } = await api.get(`/empleados/${cod}`)
  empActual.value  = data
  cargarForm(data)
  listaColapsada.value = true   // ya elegí un empleado: oculto la lista para dar lugar al detalle
  // Cargar foto desde SQL Server (sin bloquear — si falla queda null)
  api.get(`/empleados/${cod}/foto`)
    .then(r => { fotoUrl.value = r.data.foto ?? null })
    .catch(() => { fotoUrl.value = null })
}

// cargarForm usa cargarDesdeApi del composable compartido.
// Aplica la corrección del bug histórico: detección de fechas ISO con regex estricto
// en lugar de .includes('T'), que daba falsos positivos en nombres como "MATIAS".
const cargarForm = (emp: any) => {
  form.value = cargarDesdeApi(defaultForm(), emp)
  // Los códigos de categoría de carnet vienen de char(4) con espacios ("B1  ").
  // Se recortan para que coincidan con los <option> del desplegable.
  for (const k of ['PER_LC1', 'PER_LC2', 'PER_LC3']) {
    if (typeof form.value[k] === 'string') form.value[k] = form.value[k].trim()
  }
}

// ── Cambio de pestaña (con lazy loading) ──────────────────────────────────────
const TABS_CON_DATOS: Record<string, string> = {
  familia: 'hijos', puestos: 'puestos', capacitacion: 'capacitaciones',
  examenes: 'examenes', historial: 'historial', documentos: 'documentos',
  acargo: 'subordinados', faltas: 'faltas',
  celular: 'celulares', uniforme: 'ropa', epp: 'epp', obrasocial: 'obrasocial',
  tarjetas: 'tarjetas',
}
const TABS_DATA_KEY: Record<string, string> = {
  familia: 'hijos', puestos: 'puestos', capacitacion: 'capacitaciones',
  examenes: 'examenes', historial: 'historial', documentos: 'documentos',
  acargo: 'subordinados', faltas: 'faltas',
  celular: 'celulares', uniforme: 'ropa', epp: 'epp', obrasocial: 'obrasocial',
  tarjetas: 'tarjetas',
}

const cambiarTab = async (tabId: string) => {
  tabActual.value = tabId
  if (!empActual.value || modoNuevo.value) return
  if (loadedTabs.value.has(tabId)) return
  const endpoint = TABS_CON_DATOS[tabId]
  if (!endpoint) return
  const dataKey = TABS_DATA_KEY[tabId]
  cargandoTab.value = true
  try {
    const { data } = await api.get(`/empleados/${empActual.value.PER_COD}/${endpoint}`)
    tabData[dataKey] = data
    loadedTabs.value.add(tabId)
    // Autoseleccionar primera capacitación para mostrar sus documentos
    if (tabId === 'capacitacion') {
      if ((data as any[]).length > 0) capSeleccionar(0)
      else { capSeleccionada.value = -1; capDocumentos.value = [] }
    }
    // Autoseleccionar primer examen para mostrar su detalle + documentos
    if (tabId === 'examenes') {
      if ((data as any[]).length > 0) examenSeleccionar(0)
      else { examenSeleccionado.value = -1; examenDocs.value = [] }
    }
    // Faltas: autoseleccionar la primera para mostrar su documentación
    if (tabId === 'faltas') {
      if ((data as any[]).length > 0) faltaSeleccionar(0)
      else { faltaSeleccionada.value = -1; faltaDocs.value = [] }
    }
    // Personal a Cargo: separar lista de empleados y set de asignados.
    // El backend devuelve los códigos como string → se normalizan a número
    // para que el pre-marcado (acargoAsignados.has(e.cod)) coincida.
    if (tabId === 'acargo') {
      acargoEmpleados.value = ((data as any).empleados ?? []).map((e: any) => ({ ...e, cod: Number(e.cod) }))
      acargoAsignados.value = new Set(((data as any).asignados ?? []).map(Number))
      acargoFiltro.value = ''
      acargoMsg.value = ''
    }
    // Documentación: normalizar la fecha para el <input type="date"> y resetear filtro
    if (tabId === 'documentos') {
      tabData.documentos = (data as any[]).map(d => ({ ...d, fecha: toInputDate(d.fecha) }))
      docFiltro.value = ''
      docMsg.value = ''
      docSeleccionado.value = null
    }
    // Sincronizar hijosLocal y familiaForm al cargar la tab familia
    if (tabId === 'familia') {
      hijosLocal.value = (data as any[]).map(mapHijo)
      const emp = empActual.value as any
      familiaForm.PER_NES   = emp.PER_NES   ?? ''
      familiaForm.PER_PADRE = emp.PER_PADRE  ?? ''
      familiaForm.PER_MADRE = emp.PER_MADRE  ?? ''
      familiaForm.PER_EMBSN = emp.PER_EMBSN  ?? 'N'
      familiaForm.PER_EMBNOM= emp.PER_EMBNOM ?? ''
      familiaForm.PER_EMBCBU= emp.PER_EMBCBU ?? ''
    }
  } catch (e) {
    console.error('Error cargando tab', tabId, e)
  } finally {
    cargandoTab.value = false
  }
}

// ── Guardar empleado ───────────────────────────────────────────────────────────
/**
 * Guarda el empleado (alta o edición).
 *
 * Antes de llamar a la API ejecuta las validaciones de negocio del FoxPro
 * para dar feedback inmediato (sin viaje al servidor) y posicionar al usuario
 * en la sub-solapa Personal donde están los campos. El backend repite estas
 * mismas reglas + la unicidad de CUIL (ver EmpleadoController::validarReglasNegocio),
 * por lo que la validación del front es una capa de UX, no la fuente de verdad.
 */
const guardar = async () => {
  errorForm.value = ''

  // ── Validaciones de negocio (réplica FoxPro) — feedback inmediato ──
  // 1. Legajo obligatorio y distinto de 0.
  if (!form.value.PER_LEG) {
    errorForm.value = 'Falta el número de legajo.'
    subTab.value = 'personal'; return
  }
  // 2. Nombre no vacío.
  if (!String(form.value.PER_NOM ?? '').trim()) {
    errorForm.value = 'No puede ingresar un nombre vacío de empleado.'
    subTab.value = 'personal'; return
  }
  // 3. Documento (DNI) distinto de 0.
  if (!form.value.PER_NDO) {
    errorForm.value = 'El número de documento no puede ser cero.'
    subTab.value = 'personal'; return
  }
  // 4. Unicidad de CUIL → la valida el backend (necesita consultar la BD).

  guardando.value = true
  try {
    if (modoNuevo.value) {
      const { data } = await api.post('/empleados', form.value)
      // Puesto laboral inicial: si se eligió uno, asignarlo al empleado recién creado.
      if (puestoInicial.value) {
        try {
          await api.post('/asignar-puesto', { codigo: data.empleado.PER_COD, puesto: puestoInicial.value })
        } catch (e: any) {
          console.error('[asignar-puesto inicial] Error:', e?.response?.data ?? e?.message ?? e)
        }
      }
      await cargarEmpleados()
      await seleccionar(data.empleado.PER_COD)
      modoNuevo.value = false
    } else {
      const { data } = await api.put(`/empleados/${empActual.value!.PER_COD}`, form.value)
      empActual.value = data.empleado
      cargarForm(data.empleado)
      modoEdicion.value = false
      // Actualizar en lista
      const idx = empleados.value.findIndex(e => e.PER_COD === data.empleado.PER_COD)
      if (idx >= 0) Object.assign(empleados.value[idx], data.empleado)
    }
  } catch (err: any) {
    errorForm.value = err.response?.data?.message || 'Error al guardar'
    if (err.response?.data?.errors) {
      errorForm.value = Object.values(err.response.data.errors).flat().join(' | ')
    }
  } finally {
    guardando.value = false
  }
}

// ── Dar de baja / Reactivar ────────────────────────────────────────────────────
const confirmarBaja = async () => {
  bajaError.value = ''
  try {
    await api.patch(`/empleados/${empActual.value!.PER_COD}/estado`, {
      ACTIVO: 0,
      PER_BAJ: bajaDatos.fecha,            // sin default: el backend exige la fecha
      PER_BAJ_RAZON: bajaDatos.motivo,
    })
    modalBaja.value = false
    await seleccionar(empActual.value!.PER_COD)
    await cargarEmpleados()
  } catch (e: any) {
    // Validaciones de baja (fecha, celular sin devolver, tarjeta activa) → 422
    bajaError.value = e?.response?.data?.message ?? 'No se pudo dar de baja.'
  }
}

const reactivar = async () => {
  if (!confirm(`¿Reactivar a ${empActual.value?.PER_NOM}?`)) return
  try {
    await api.patch(`/empleados/${empActual.value!.PER_COD}/estado`, { ACTIVO: 1 })
    await seleccionar(empActual.value!.PER_COD)
    await cargarEmpleados()
  } catch (e) {
    console.error(e)
  }
}

// ── Nuevo empleado ─────────────────────────────────────────────────────────────
// ── Activar / Cancelar edición ────────────────────────────────────────────────
const activarEdicion = () => {
  modoEdicion.value = true
  errorForm.value   = ''
}

const cancelarEdicion = () => {
  modoEdicion.value = false
  errorForm.value   = ''
  // Restaurar el formulario al estado guardado (descarta cambios)
  if (empActual.value) cargarForm(empActual.value)
}

const abrirNuevo = async () => {
  empActual.value   = null
  modoNuevo.value   = true
  modoEdicion.value = false
  tabActual.value   = 'datos'
  subTab.value      = 'personal'
  form.value        = defaultForm()
  errorForm.value   = ''
  puestoInicial.value = ''
  cargarCatalogoPuestos()   // catálogo de puestos para el listbox de alta
  // Reservar un código PAR para mostrarlo de inmediato y evitar que otra
  // terminal reciba el mismo. La reserva se consume al guardar o expira sola.
  try {
    const { data } = await api.post('/empleados/reservar-codigo')
    form.value.PER_COD = data.codigo
  } catch (e) {
    console.error('No se pudo reservar código', e)
    // Si falla la reserva, el backend asigna el código al guardar.
  }
}
const cancelarNuevo = async () => {
  // Liberar la reserva de código si se había obtenido una.
  const reservado = form.value.PER_COD
  modoNuevo.value = false
  form.value      = defaultForm()
  errorForm.value = ''
  if (reservado) {
    try { await api.delete(`/empleados/reservar-codigo/${reservado}`) }
    catch (e) { console.error('No se pudo liberar la reserva', e) }
  }
}

// ── Handlers selects en cadena ─────────────────────────────────────────────────
const onEmpresaChange = () => {
  const emp = opciones.empresas.find((e: any) => e.EMP_COD === form.value.PER_EMP)
  form.value.PER_EMD = emp?.EMP_NOM ?? ''
}
const onSectorChange = () => {
  const sec = opciones.sectores.find((s: any) => s.SEC_COD === form.value.PER_SEC)
  form.value.PER_SED = sec?.SEC_DES ?? ''
}
const onCategoriaChange = () => {
  const cat = opciones.categorias.find((c: any) => c.CAT_COD === form.value.PER_CAT)
  form.value.PER_CAD = cat?.CAT_DES ?? ''
}
const onObraSocialChange = () => {
  const os = opciones.obrassociales.find((o: any) => o.OBR_COD === form.value.PER_COS)
  form.value.OBRASOCIAL = os?.OBR_NOM ?? ''
}
const onComedorChange = () => {
  const com = opciones.comedores.find((c: any) => c.COME_COD === form.value.PER_COMN)
  form.value.PER_COMD = com?.COME_DES ?? ''
}
const onBancoChange = () => {
  const ban = opciones.bancos.find((b: any) => b.CBA_COD === form.value.PER_BAN)
  form.value.PER_BAD = ban?.CBA_DES ?? ''
}

// Lógica compartida por Ad.Encargado, Ad.Sust.Pelig. y Afiliado:
//   If PER_CON = 3 OR PER_CON = 6 → Descuento = 17
//   Else if checkbox = true        → Descuento = 22
//   Else                           → Descuento = 21
const aplicarDescuento = (valorCheck: boolean) => {
  const con = form.value.PER_CON
  if (con === 3 || con === 6) {
    form.value.PER_DESCUENTO = 17
  } else {
    form.value.PER_DESCUENTO = valorCheck ? 22 : 21
  }
}
const onAdEncargadoChange  = () => aplicarDescuento(form.value.PER_ADI_ENC)
const onAdSustPeligChange  = () => aplicarDescuento(form.value.PER_ADI_SUS)
const onAfiliadoChange     = () => aplicarDescuento(form.value.PER_AFILIADO)

// ── Init ───────────────────────────────────────────────────────────────────────
const route = useRoute()

// Abre directamente la ficha si llega ?cod= (buscador global de la topbar).
watch(() => route.query.cod, async (v) => {
  const cod = Number(v)
  if (cod) await seleccionar(cod)
})

onMounted(async () => {
  await Promise.all([cargarEmpleados(), cargarOpciones()])
  const cod = Number(route.query.cod)
  if (cod) await seleccionar(cod)
})
</script>

<style>
/* Modal PDF — fuera de scoped para que Teleport lo aplique correctamente */
.pdf-modal-overlay {
  position: fixed; inset: 0; background: rgba(0,0,0,0.6);
  display: flex; align-items: center; justify-content: center; z-index: 9999;
}
.pdf-modal {
  background: #fff; border-radius: 8px; overflow: hidden;
  width: 90vw; height: 90vh;
  display: flex; flex-direction: column;
  box-shadow: 0 8px 32px rgba(0,0,0,0.35);
}
.pdf-modal-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 10px 16px; background: #1b4332; color: #fff;
  font-weight: 600; font-size: 14px; gap: 12px;
}
.pdf-modal-btns { display: flex; gap: 8px; }
.pdf-modal-frame { flex: 1; border: none; width: 100%; }
.pdf-modal-nopreview { flex: 1; display: flex; flex-direction: column;
  align-items: center; justify-content: center; gap: 14px; color: #475569; padding: 24px; }
.pdf-modal-nopreview-icon { font-size: 64px; opacity: .5; }
.pdf-modal-nopreview p { margin: 0; font-size: 15px; }

/* Modal Informe completo del puesto — fuera de scoped (Teleport a body) */
.puesto-modal-overlay { position: fixed; inset: 0; background: rgba(15,23,42,.55); display: flex; align-items: flex-start; justify-content: center; z-index: 10000; padding: 40px 18px; overflow: auto; }
.puesto-modal { background: #fff; border-radius: 12px; width: min(720px, 96vw); max-height: 90vh; display: flex; flex-direction: column; box-shadow: 0 12px 40px rgba(0,0,0,.3); }
.puesto-modal-header { display: flex; align-items: center; justify-content: space-between; padding: 12px 18px; background: #1b4332; color: #fff; font-weight: 700; font-size: 15px; border-radius: 12px 12px 0 0; }
.puesto-modal-body { padding: 18px 22px; overflow: auto; color: #1e293b; }
.puesto-loading { text-align: center; color: #64748b; padding: 30px; }
.pm-titulo { margin: 0 0 2px; font-size: 19px; color: #14532d; }
.pm-cod { font-size: 12px; color: #64748b; margin-bottom: 12px; }
.pm-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px; }
.pm-grid > div { background: #f4f7fc; border-radius: 8px; padding: 8px 12px; font-size: 14px; color: #1e293b; }
.pm-grid span { display: block; font-size: 11px; font-weight: 700; color: #2a4a6a; text-transform: uppercase; margin-bottom: 2px; }
.pm-bloque { margin-top: 14px; } .pm-bloque h4 { margin: 0 0 6px; font-size: 13px; color: #1b4332; text-transform: uppercase; letter-spacing: .3px; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px; }
.pm-bloque p { margin: 0; font-size: 14px; line-height: 1.55; color: #334155; white-space: pre-line; }
.pm-bloque ul { margin: 0; padding-left: 20px; } .pm-bloque li { font-size: 14px; line-height: 1.6; color: #334155; }
</style>

<style scoped>
/* ── Layout principal ── */
.empleados-view { display:flex; flex-direction:column; height:100%; overflow:hidden; }

.btn-ojo { border:none; background:#eef2f7; border-radius:5px; cursor:pointer; padding:1px 6px; font-size:13px; margin-left:6px; vertical-align:middle; }
.btn-ojo:hover { background:#dbeafe; }
.cab { display:flex; align-items:center; gap:12px; padding:12px 16px; background:#ffffff;
  border-bottom:1px solid #e2e8f0; flex-shrink:0; }
.cab-icono { font-size:28px; }
.cab-textos { flex:1; }
.cab-titulo { margin:0; font-size:18px; color:#1e293b; font-weight:700; }
.cab-sub { margin:0; font-size:12px; color:#6b7280; }
.btn-nuevo { background:#22c55e; color:#fff; border:none; padding:8px 14px; border-radius:6px;
  cursor:pointer; font-size:13px; font-weight:600; white-space:nowrap; }
.btn-nuevo:hover { background:#16a34a; }
.btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:8px 12px;
  border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; white-space:nowrap; margin-right:8px; }
.btn-ayuda:hover { background:#f0faf4; }
.btn-ia { background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:8px 14px;
  border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; white-space:nowrap; margin-right:8px; }
.btn-ia:hover { filter:brightness(1.1); }
.btn-listados { background:#0d9488; color:#fff; border:none; padding:8px 14px; border-radius:6px;
  cursor:pointer; font-size:13px; font-weight:600; white-space:nowrap; margin-right:8px; }
.btn-listados:hover { background:#0f766e; }
.btn-legajo { background:#2563eb; color:#fff; border:none; padding:8px 14px; border-radius:6px;
  cursor:pointer; font-size:13px; font-weight:600; white-space:nowrap; margin-right:8px; }
.btn-legajo:hover:not(:disabled) { background:#1d4ed8; }
.btn-legajo:disabled { background:#cbd5e1; cursor:default; }

/* Modal del módulo de Listados */
.listados-overlay { position:fixed; inset:0; background:rgba(15,23,42,.55); backdrop-filter:blur(2px);
  z-index:8500; display:flex; align-items:center; justify-content:center; padding:24px; }
.listados-modal { background:#f8fafc; width:min(1080px,97vw); height:92vh; border-radius:14px; overflow:hidden;
  box-shadow:0 24px 60px rgba(0,0,0,.45); display:flex; flex-direction:column; }

.layout { display:flex; flex:1; overflow:hidden; }

/* ── Lista de empleados ── */
.col-lista { width:285px; flex-shrink:0; border-right:1px solid #e2e8f0;
  display:flex; flex-direction:column; background:#f8fafc; }
.lista-header { padding:8px; border-bottom:1px solid #e2e8f0; }
.buscador { width:100%; box-sizing:border-box; background:#ffffff; border:1px solid #d1d5db;
  color:#1e293b; border-radius:6px; padding:6px 8px; font-size:13px; }
.filtros-row { display:flex; gap:4px; margin-top:6px; }
.btn-f { flex:1; background:#ffffff; border:1px solid #d1d5db; color:#6b7280; border-radius:4px;
  padding:4px; font-size:11px; cursor:pointer; white-space:nowrap; }
.btn-f.act { background:#3b82f6; color:#fff; border-color:#3b82f6; }
.btn-f:hover:not(.act) { border-color:#9ca3af; color:#374151; }
.lista-scroll { flex:1; overflow-y:auto; }
.item-emp { display:flex; align-items:center; gap:8px; width:100%; text-align:left;
  background:none; border:none; border-bottom:1px solid #f1f5f9; padding:8px;
  cursor:pointer; color:#1e293b; }
.item-emp:hover { background:#f1f5f9; }
.item-emp.sel { background:#dbeafe; }
.item-emp.baja { opacity:.65; }
.emp-avatar { width:34px; height:34px; border-radius:50%; display:flex; align-items:center;
  justify-content:center; font-size:13px; font-weight:700; flex-shrink:0; }
.av-m { background:#1d4ed8; color:#fff; }
.av-f { background:#7c3aed; color:#fff; }
.av-baja { background:#e5e7eb; color:#6b7280; }
.emp-info { flex:1; min-width:0; }
.emp-nom { font-size:12px; font-weight:600; color:#1e293b; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.emp-meta { display:flex; gap:6px; margin-top:2px; }
.emp-leg { font-size:10px; color:#9ca3af; }
.emp-sec { font-size:10px; color:#9ca3af; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:90px; }
.emp-estado { font-size:10px; }
.est-act { color:#16a34a; }
.est-baj { color:#9ca3af; }
.paginacion { display:flex; align-items:center; justify-content:center; gap:8px;
  padding:8px; font-size:12px; color:#6b7280; }
.paginacion button { background:#ffffff; border:1px solid #d1d5db; color:#6b7280;
  padding:2px 8px; border-radius:4px; cursor:pointer; }
.paginacion button:disabled { opacity:.4; cursor:not-allowed; }
.sin-resultados { text-align:center; color:#9ca3af; padding:20px; font-size:13px; }
.estado-msg { padding:20px; text-align:center; color:#9ca3af; font-size:13px; }
.spin { display:inline-block; animation:spin 1s linear infinite; }
@keyframes spin { from{transform:rotate(0)} to{transform:rotate(360deg)} }

/* ── Columna detalle ── */
.col-detalle { flex:1; display:flex; flex-direction:column; overflow:hidden; }
.col-detalle-vacio { justify-content:center; align-items:center; background:#f9fafb; }
.sin-seleccion { display:flex; flex-direction:column; align-items:center; gap:12px; color:#9ca3af; }
.sin-seleccion-icono { font-size:56px; opacity:.4; }
.sin-seleccion p { font-size:15px; font-weight:500; margin:0; }

/* ── Barra de 18 pestañas ── */
.tabs-nav { display:flex; flex-wrap:wrap; gap:2px; padding:6px 8px;
  background:#f8fafc; border-bottom:1px solid #e2e8f0; flex-shrink:0; }
.tab-btn { background:#ffffff; border:1px solid #d1d5db; color:#6b7280;
  padding:5px 9px; border-radius:4px; cursor:pointer; font-size:11px;
  white-space:nowrap; transition:all .15s; }
.tab-btn:hover { background:#f1f5f9; color:#374151; }
.tab-btn.act { background:#3b82f6; border-color:#3b82f6; color:#fff; font-weight:600; }

/* ── Barra de acciones ── */
.barra-acciones { display:flex; align-items:center;
  padding:6px 12px; background:#ffffff; border-bottom:1px solid #e2e8f0; flex-shrink:0; }
.ba-toggle-lista { flex:0 0 auto; width:34px; height:34px; margin-right:12px; border:1px solid #d1d5db;
  background:#f8fafc; color:#1b4332; border-radius:8px; cursor:pointer; font-size:15px; font-weight:700; }
.ba-toggle-lista:hover { background:#eef2f7; border-color:#40916c; }
.ba-nombre { flex:1; display:flex; align-items:center; justify-content:flex-start; gap:10px; }
.ba-emp-nombre { font-size:16px; font-weight:800; color:#1b4332; letter-spacing:.2px;
  white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:420px; }
.ba-btns   { flex:0 0 auto; display:flex; gap:6px; margin-left:auto; }
.ba-foto   { width:48px; height:60px; object-fit:cover; border-radius:4px;
  border:1px solid #e2e8f0; flex-shrink:0; margin-right:14px; }
.ba-leg { font-size:12px; color:#6b7280; }
.badge-act { background:#dcfce7; color:#16a34a; border:1px solid #86efac;
  padding:2px 8px; border-radius:12px; font-size:11px; font-weight:600; }
.badge-baj { background:#fee2e2; color:#dc2626; border:1px solid #fca5a5;
  padding:2px 8px; border-radius:12px; font-size:11px; font-weight:600; }
.btn-sm { padding:6px 12px; border:none; border-radius:5px; cursor:pointer;
  font-size:12px; font-weight:600; white-space:nowrap; }
.btn-sm:disabled { opacity:.5; cursor:not-allowed; }
.btn-save      { background:#3b82f6; color:#fff; }
.btn-save:hover:not(:disabled) { background:#2563eb; }
.btn-baja      { background:#dc2626; color:#fff; }
.btn-baja:hover { background:#b91c1c; }
.btn-reactivar { background:#16a34a; color:#fff; }
.btn-reactivar:hover { background:#15803d; }
.btn-cancel    { background:#e5e7eb; color:#374151; }
.btn-cancel:hover { background:#d1d5db; }
.error-form { background:#fee2e2; color:#dc2626; padding:8px 12px;
  font-size:12px; border-bottom:1px solid #fca5a5; flex-shrink:0; }

/* ── Cuerpo de pestañas ── */
.tab-body { flex:1; overflow-y:auto; padding:12px 16px; }
.tab-body-split { flex:1; display:flex; flex-direction:column; overflow:hidden; }
.tab-scroll { flex:1; overflow-y:auto; padding:12px 16px; }
.tab-loading { padding:30px; text-align:center; color:#6b7280; font-size:14px; }
.tab-empty   { padding:20px; text-align:center; color:#6b7280; font-size:13px; font-style:italic; }
.tab-section-header { font-size:12px; font-weight:700; color:#1f2937;
  text-transform:uppercase; letter-spacing:.5px; margin-bottom:8px;
  display:flex; align-items:center; gap:8px; }
.badge-count { background:#e5e7eb; color:#374151; padding:1px 7px;
  border-radius:10px; font-size:11px; font-weight:700; }

/* ── Sub-pestañas ── */
.sub-tabs { display:flex; gap:4px; padding:8px 12px; flex-shrink:0;
  border-bottom:1px solid #e2e8f0; background:#f8fafc; }
.sub-tab { background:#ffffff; border:1px solid #d1d5db; color:#6b7280;
  padding:5px 12px; border-radius:4px; cursor:pointer; font-size:12px; }
.sub-tab.act { background:#dbeafe; border-color:#3b82f6; color:#2563eb; font-weight:600; }

/* ── Formulario ── */
.form-grid { display:grid; grid-template-columns:1fr 1fr; gap:8px 12px; }

/* ── Grid de 4 columnas ── */
.form-grid-4 { display:grid; grid-template-columns:repeat(4,1fr); gap:8px 12px; }
.form-grid-4 .col4-g4 { grid-column:span 4; }
.form-grid-4 .col3-g4 { grid-column:span 3; }
.form-grid-4 .col2-g4 { grid-column:span 2; }
.form-grid-4 .sep-label { grid-column:span 4; font-size:11px; font-weight:700; color:#374151;
  text-transform:uppercase; letter-spacing:.5px; border-top:1px solid #e2e8f0; padding-top:6px; margin-top:4px; }

/* ── Layout 3 columnas para Laboral ── */
.laboral-layout { display:grid; grid-template-columns:1fr 1fr auto; gap:12px 20px; align-items:start; }
.laboral-col { display:flex; flex-direction:column; gap:8px; }
.laboral-sidebar { display:flex; flex-direction:column; gap:2px; padding-left:12px;
  border-left:1px solid #e2e8f0; min-width:130px; }
.laboral-lugar-row { display:flex; gap:8px; }
.sector-row { display:grid; grid-template-columns:1fr 1fr 1fr; gap:6px; }

/* Panel de sugerencias (valores por defecto inteligentes) */
.sug-panel { margin-top:12px; background:#f0f9f4; border:1px solid #bfe3cd; border-radius:8px; padding:10px 12px; }
.sug-titulo { font-size:12px; font-weight:700; color:#166534; margin-bottom:6px; }
.sug-item { display:flex; align-items:center; gap:8px; font-size:13px; color:#1e293b; padding:2px 0; }
.sug-campo { color:#64748b; min-width:74px; }
.sug-valor { color:#14532d; }
.sug-conf { font-size:11px; font-weight:700; color:#166534; background:#dcfce7; padding:1px 7px; border-radius:10px; }
.sug-ok { font-size:11px; color:#94a3b8; }
.sug-nota { font-size:11px; color:#64748b; margin:6px 0 0; }

/* ── Bloque de sueldos dentro de Laboral ── */
.sueldos-bloque { display:grid; grid-template-columns:auto 1fr auto auto auto; gap:4px 8px; align-items:center; }
.sueldos-hdr { font-size:10px; color:#374151; font-weight:700; text-align:center; padding:2px 0; }
.sueldos-lbl { font-size:11px; color:#6b7280; font-weight:500; white-space:nowrap; }
.sueldos-inp { background:#ffffff; border:1px solid #d1d5db; color:#1e293b; padding:4px 6px;
  border-radius:4px; font-size:12px; width:100%; min-width:0; }
.sueldos-inp:focus { outline:none; border-color:#3b82f6; }

/* ── Campos calculados (amarillo, solo lectura) ── */
.remun-calc { background:#fefce8; border:1px solid #fde047; color:#854d0e; font-weight:700;
  padding:5px 8px; border-radius:4px; font-size:13px; font-family:monospace; }
.campo-inline { display:flex; gap:12px; align-items:flex-end; }
.campo-inline .campo { flex:1; }
.campo { display:flex; flex-direction:column; gap:3px; }
.campo.col2 { grid-column:span 2; }
.campo-check { display:flex; align-items:center; gap:6px; padding:6px 0; }
.campo-check input[type="checkbox"] {
  width:15px; height:15px; cursor:pointer; accent-color:#3b82f6; flex-shrink:0;
}
.campo-check .chk-lbl { font-size:12px; color:#374151; cursor:pointer; user-select:none; }
fieldset[disabled] .campo-check input[type="checkbox"] { cursor:default; opacity:0.6; }
.checks-grupo { display:grid; grid-template-columns:repeat(3,1fr); gap:2px 8px;
  padding:6px 0; grid-column:span 2; }
.campo label { font-size:11px; color:#374151; font-weight:600; }
.campo input, .campo select, .campo textarea {
  background:#ffffff; border:1px solid #d1d5db; color:#1e293b;
  border-radius:5px; padding:6px 8px; font-size:13px; }
.campo input:focus, .campo select:focus, .campo textarea:focus {
  border-color:#3b82f6; outline:none; }
.campo textarea { resize:vertical; }
.campo-calc { opacity:.7; cursor:not-allowed; }
.req { color:#dc2626; }

/* ── Fieldset sin borde ── */
.fs-form { border:none; padding:0; margin:0; min-width:0; }

/* ── Campos en modo solo lectura ── */
.fs-form:disabled input,
.fs-form:disabled select,
.fs-form:disabled textarea,
fieldset[disabled] input,
fieldset[disabled] select,
fieldset[disabled] textarea {
  background:#f8fafc !important;
  color:#374151 !important;
  border-color:#e2e8f0 !important;
  cursor:default;
  opacity:1;
}
fieldset[disabled] select {
  appearance:none;
  -webkit-appearance:none;
  pointer-events:none;
}

/* ── Badge "Editando" ── */
.badge-editando {
  background:#dbeafe;
  color:#1d4ed8;
  border:1px solid #93c5fd;
  padding:2px 8px;
  border-radius:12px;
  font-size:11px;
  font-weight:600;
  animation: pulseEdit 2s ease-in-out infinite;
}
@keyframes pulseEdit {
  0%, 100% { opacity:1; }
  50%       { opacity:.6; }
}

/* ── Botón Editar ── */
.btn-editar { background:#dbeafe; color:#1d4ed8; border:1px solid #93c5fd; }
.btn-editar:hover { background:#bfdbfe; color:#1e40af; }

/* ── Documentación: barra de filtro ── */
.doc-barra { display:flex; align-items:center; gap:8px; margin-bottom:10px; }
.doc-barra-lbl { font-size:12px; font-weight:600; color:#374151; }
.doc-filtro { border:1px solid #d1d5db; border-radius:4px; padding:4px 8px;
  font-size:13px; color:#1e293b; min-width:260px; }

/* ── Documentación: grilla con scroll (≈3 filas visibles) ── */
.doc-grid-scroll { max-height: 170px; overflow-y: auto; border: 1px solid #e5e7eb; border-radius: 4px; }
.doc-tabla thead th { position: sticky; top: 0; z-index: 1; }  /* header fijo al scrollear */

/* ── Documentación: panel agregar ── */
.doc-agregar { display:flex; margin-top:16px; border:1px solid #1e40af; border-radius:6px;
  overflow:hidden; background:#eff6ff; }
.doc-agregar-tag { writing-mode:vertical-rl; transform:rotate(180deg);
  background:#1e40af; color:#fff; font-weight:700; font-size:11px; letter-spacing:1px;
  display:flex; align-items:center; justify-content:center; padding:8px 4px; min-width:26px; }
.doc-agregar-campos { flex:1; padding:10px 14px; display:flex; flex-direction:column; gap:8px; }
.doc-ag-row { display:flex; align-items:center; gap:8px; }
.doc-ag-row > label { width:150px; flex-shrink:0; text-align:right; font-size:12px; font-weight:600; color:#374151; }
.doc-ag-tipo { min-width:340px; border:1px solid #d1d5db; border-radius:4px; padding:4px 6px; font-size:13px; color:#1e293b; }
.doc-ag-fecha { border:1px solid #d1d5db; border-radius:4px; padding:4px 6px; font-size:13px; color:#1e293b; }
.doc-ag-obs { flex:1; max-width:420px; border:1px solid #d1d5db; border-radius:4px; padding:4px 6px; font-size:13px; color:#1e293b; }

/* ── Faltas (master-detail, réplica FoxPro) ── */
.faltas-wrap { display:flex; flex-direction:column; height:470px; }
.faltas-grid { flex:1.5; display:flex; flex-direction:column; min-height:0; }
.faltas-grid-scroll { flex:1; overflow:auto; border:1px solid #e5e7eb; border-radius:4px; }
.faltas-tabla { font-size:12px; }
.faltas-tabla thead th { position:sticky; top:0; z-index:1; }
.falta-verde td { background:#86efac; }     /* con documentación */
.falta-roja  td { background:#fca5a5; }      /* sin documentación */
.falta-verde.fila-sel td, .falta-roja.fila-sel td { outline:2px solid #1e40af; outline-offset:-2px; font-weight:600; }
.faltas-doc-titulo { align-self:center; background:#bbf7d0; color:#166534; font-weight:700;
  font-size:12px; padding:3px 14px; border-radius:3px; margin:10px 0 6px; }
.faltas-docs { flex:1; display:flex; flex-direction:column; min-height:0; }
.faltas-docs-scroll { flex:1; overflow:auto; border:1px solid #e5e7eb; border-radius:4px; }
.btn-ojo { border:none; background:transparent; cursor:pointer; font-size:15px;
  padding:0 2px; line-height:1; }
.btn-ojo:hover { transform:scale(1.2); }

/* ── Personal a Cargo (selector con checkboxes) ── */
.acargo-wrap { display:flex; flex-direction:column; height:460px; }
.acargo-titulo { text-align:center; font-weight:700; font-size:13px; color:#1f2937;
  letter-spacing:.5px; margin-bottom:8px; display:flex; align-items:center; justify-content:center; gap:8px; }
.acargo-layout { display:flex; gap:12px; flex:1; min-height:0; }
.acargo-grid { flex:1; display:flex; flex-direction:column; min-width:0; }
.acargo-filtro-row { margin-bottom:6px; }
.acargo-grid-scroll { flex:1; overflow:auto; border:1px solid #e5e7eb; border-radius:4px; }
.acargo-tabla { font-size:12px; }
.acargo-tabla thead th { position:sticky; top:0; z-index:1; }
.acargo-tabla tbody tr:hover td { background:#f0faf4; }
.acargo-marcado td { background:#fef9c3 !important; font-weight:600; }   /* amarillo como FoxPro */
.acargo-side { flex:0 0 200px; display:flex; flex-direction:column; }
.acargo-informe { white-space:normal; height:auto; padding:12px; text-align:center; line-height:1.3; }
.acargo-footer { display:flex; align-items:center; gap:8px; margin-top:12px;
  padding:8px 12px; background:#f1f5f9; border-radius:6px; }

/* ── Historial de cambios (master-detail, réplica FoxPro) ── */
.hist-wrap { display:flex; flex-direction:column; height:460px; }
.hist-layout { display:flex; gap:12px; flex:1; min-height:0; }
.hist-grid { flex:1.3; display:flex; flex-direction:column; min-width:0; }
.hist-grid-scroll { flex:1; overflow:auto; border:1px solid #e5e7eb; border-radius:4px; }
.hist-tabla { font-size:12px; }
.hist-tabla thead th { position:sticky; top:0; z-index:1; }
.hist-tabla tbody tr:hover td { background:#f0faf4; }
.hist-panel { flex:1; display:flex; flex-direction:column; gap:10px; min-width:0; }
.hist-cambios { flex:1; display:flex; }
.hist-cambios textarea { flex:1; resize:none; border:1px solid #d1d5db; border-radius:4px;
  padding:8px 10px; font-size:12px; color:#1e293b; background:#fff; white-space:pre-wrap; }
.hist-obs { flex:0 0 130px; display:flex; flex-direction:column; }
.hist-obs > label { font-size:12px; font-weight:600; color:#374151; margin-bottom:4px; }
.hist-obs textarea { flex:1; resize:none; border:1px solid #d1d5db; border-radius:4px;
  padding:8px 10px; font-size:12px; color:#1e293b; background:#f8fafc; }
.hist-footer { display:flex; align-items:center; gap:8px; flex-wrap:wrap;
  margin-top:12px; padding:8px 12px; background:#f1f5f9; border-radius:6px;
  font-size:13px; color:#374151; }

/* ── Exámenes médicos (master-detail, réplica FoxPro) ── */
.examenes-layout { display:flex; flex-direction:column; gap:12px; height:460px; }
.exa-grid { display:flex; flex-direction:column; min-height:0; flex:2; }
.exa-grid-scroll { flex:1; overflow:auto; border:1px solid #e5e7eb; border-radius:4px; }
.exa-tabla { font-size:12px; }
.exa-tabla thead th { position:sticky; top:0; z-index:1; }
.exa-grid .exa-tabla tbody tr:hover td { background:#f0faf4; }
.exa-detalle { flex:0 0 auto; border:1px solid #cbd5e1; border-radius:6px; padding:10px 12px; background:#f8fafc; }
.exa-detalle-titulo { font-weight:700; color:#1f2937; font-size:13px; letter-spacing:1px; margin-bottom:8px; }
.exa-campos { display:grid; grid-template-columns:1fr 1fr; gap:4px 16px; }
.exa-row { display:flex; gap:8px; align-items:baseline; }
.exa-row > label { font-size:12px; font-weight:600; color:#374151; min-width:110px; text-align:right; }
.exa-val { font-size:13px; color:#1e293b; }
.exa-notas { margin-top:8px; }
.exa-notas > label { font-size:12px; font-weight:600; color:#374151; display:block; margin-bottom:4px; }
.exa-notas textarea { width:100%; min-height:70px; resize:vertical; border:1px solid #d1d5db;
  border-radius:4px; padding:6px 8px; font-size:12px; color:#1e293b; background:#fff; box-sizing:border-box; }
.exa-docs { display:flex; flex-direction:column; min-height:0; flex:1; }
.exa-docs-scroll { flex:1; overflow:auto; border:1px solid #e5e7eb; border-radius:4px; }

/* ── Licencia de conducir (réplica FoxPro: 3 carnets + foto) ── */
.lic-layout { display:flex; gap:16px; align-items:flex-start; }
.lic-carnets { flex:1; display:flex; flex-direction:column; gap:10px; min-width:0; }
.lic-carnet { display:flex; border:1px solid #cbd5e1; border-radius:6px; overflow:hidden; background:#f8fafc; }
.lic-carnet-tag {
  writing-mode:vertical-rl; transform:rotate(180deg);
  background:#1f2937; color:#fff; font-weight:700; font-size:11px; letter-spacing:1px;
  display:flex; align-items:center; justify-content:center; padding:8px 4px; min-width:26px;
}
.lic-carnet-campos { flex:1; padding:8px 10px; display:flex; flex-direction:column; gap:6px; min-width:0; }
.lic-row { display:flex; align-items:center; gap:8px; }
.lic-row > label { width:130px; flex-shrink:0; text-align:right; font-size:12px; font-weight:600; color:#374151; }
.lic-row > input, .lic-row > select { flex:1; min-width:0; border:1px solid #d1d5db; border-radius:4px;
  padding:4px 6px; font-size:13px; color:#1e293b; background:#fff; }
.lic-row > input[type="date"] { flex:0 0 150px; }
.lic-select { text-transform:uppercase; }
.lic-btn-elim { flex:0 0 auto; }
.lic-vencida { border-color:#dc2626 !important; color:#dc2626 !important; font-weight:700; }
.lic-foto { flex:0 0 200px; border:2px solid #1f2937; border-radius:6px; overflow:hidden;
  background:#0f172a; display:flex; align-items:center; justify-content:center; aspect-ratio:3/4; }
.lic-foto img { width:100%; height:100%; object-fit:cover; }
.lic-foto-vacia { color:#94a3b8; font-size:12px; }
.lic-medico { margin-top:14px; display:flex; flex-direction:column; gap:8px; max-width:640px; }
.lic-medico-row { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
.lic-medico-row > label { font-size:12px; font-weight:600; color:#374151; }
.lic-mini { width:46px; text-align:center; border:1px solid #d1d5db; border-radius:4px;
  padding:4px; font-size:13px; color:#1e293b; margin-right:12px; }
.lic-row-ancho > input { flex:1; }

/* ── Familia: encabezados y etiquetas legibles ── */
.familia-seccion-header { display:flex; align-items:center; gap:8px; margin:14px 0 6px;
  font-size:13px; font-weight:700; color:#1f2937; }
.familia-embargo { margin-top:14px; }
.familia-embargo .chk-label,
.chk-label { display:flex; align-items:center; gap:6px; cursor:pointer;
  font-size:13px; font-weight:700; color:#1f2937; user-select:none; }

/* ── Tabla de hijos (Familia) ── */
/* Encabezado en negro: sobre fondo blanco el color por defecto no se distinguía. */
.familia-tabla { width:100%; border-collapse:collapse; }
.familia-tabla thead th {
  color:#111827; font-weight:700; font-size:12px; text-align:left;
  background:#f3f4f6; border-bottom:1px solid #d1d5db; padding:6px 8px;
}

/* ── Botones Familia ── */
.familia-btns { display:flex; flex-wrap:wrap; gap:8px; align-items:center; margin-top:8px; }
.os-grid { max-width:520px; }
.historial-os-titulo { text-align:center; font-weight:700; font-size:13px; letter-spacing:.5px;
  color:#374151; padding:8px 0 6px; text-transform:uppercase; }
.radio-group { display:flex; gap:16px; align-items:center; padding:6px 0; }
.radio-group label { display:flex; align-items:center; gap:4px; cursor:pointer; font-size:13px; color:#374151; }
.radio-readonly { display:flex; gap:16px; align-items:center; padding:6px 0; }
.radio-on  { color:#16a34a; font-size:13px; font-weight:600; }
.radio-off { color:#9ca3af; font-size:13px; }
.btn-fam-agregar  { background:#dcfce7; color:#166534; border:1px solid #86efac; }
.btn-fam-agregar:hover { background:#bbf7d0; color:#14532d; }
.btn-fam-eliminar { background:#fee2e2; color:#991b1b; border:1px solid #fca5a5; }
.btn-fam-eliminar:hover:not(:disabled) { background:#fecaca; color:#7f1d1d; }
.btn-fam-eliminar:disabled { opacity:.4; cursor:not-allowed; }
.btn-fam-guardar  { background:#dbeafe; color:#1d4ed8; border:1px solid #93c5fd; }
.btn-fam-guardar:hover:not(:disabled) { background:#2563eb; color:#fff; }
.btn-fam-guardar:disabled { opacity:.5; cursor:not-allowed; }
.sep-label { grid-column:span 2; font-size:11px; font-weight:700; color:#374151;
  text-transform:uppercase; letter-spacing:.5px; border-top:1px solid #e2e8f0;
  padding-top:8px; margin-top:4px; }

/* ── Tablas de datos ── */
.tab-tabla { width:100%; border-collapse:collapse; font-size:12px; }
.tab-tabla th { background:#e5e7eb; color:#1f2937; font-size:11px; font-weight:700;
  text-transform:uppercase; letter-spacing:.3px; padding:6px 8px;
  text-align:left; border-bottom:1px solid #cbd5e1; }
.tab-tabla td { padding:6px 8px; border-bottom:1px solid #f1f5f9; color:#1e293b; }
.tab-tabla tr:hover td { background:#f8fafc; }
.tab-tabla tr.cal-sel td { background:#dbeafe; }
.num { text-align:right !important; font-family:monospace; }
.neg { color:#dc2626; }
.pos { color:#16a34a; }
.vencido { color:#dc2626; font-weight:600; }
.hist-cam { font-size:11px; color:#6b7280; max-width:300px;
  white-space:pre-wrap; word-break:break-word; }

/* ── Valores Horas ── */
/* ── Valores Horas (réplica FoxPro: dos secciones) ── */
.vh-panel { max-width:560px; border:1px solid #cbd5e1; border-radius:6px;
  background:#eef4fb; padding:14px 18px; display:flex; flex-direction:column; gap:18px; }
.vh-seccion { display:flex; flex-direction:column; gap:7px; }
.vh-titulo { background:#1f2937; color:#fff; font-weight:700; font-size:13px;
  padding:4px 10px; border-radius:3px; align-self:flex-start; }
.vh-fila { display:flex; align-items:center; gap:10px; justify-content:flex-end; }
.vh-fila > label { font-size:13px; color:#374151; font-weight:600; text-align:right; }
.vh-box { min-width:90px; text-align:right; font-family:monospace; font-size:14px;
  color:#1e293b; background:#fff; border:1px solid #cbd5e1; border-radius:3px; padding:3px 8px; }
.vh-edit { width:90px; box-sizing:border-box; border-color:#16a34a; }
.vh-hs { font-size:12px; color:#6b7280; width:18px; }

/* ── Celulares (grilla, réplica FoxPro) ── */
.cel-grid-scroll { overflow:auto; border:1px solid #e5e7eb; border-radius:4px; max-height:440px; }
.cel-tabla { font-size:12px; white-space:nowrap; }
.cel-tabla thead th { position:sticky; top:0; z-index:1; }
/* Devuelto: fondo rojo, texto blanco (igual que FoxPro) */
.cel-devuelto td { background:#ef4444 !important; color:#fff !important; font-weight:600; }

/* ── Placeholder ── */
.tab-placeholder { display:flex; flex-direction:column; align-items:center; justify-content:center;
  height:200px; gap:8px; color:#9ca3af; }
.placeholder-icon { font-size:48px; }
.tab-placeholder p { margin:0; font-size:14px; }
.placeholder-sub { font-size:12px !important; color:#9ca3af !important; }

/* ── Tab Fotos ── */
.fotos-tab { display:flex; flex-direction:column; align-items:center; gap:10px; padding:16px; }
.fotos-info { width:100%; max-width:500px; display:flex; flex-direction:column; gap:4px; }
.fotos-info-row { display:flex; align-items:center; gap:8px; }
.fotos-label { font-size:12px; color:#6b7280; width:52px; text-align:right; flex-shrink:0; }
.fotos-value { background:#f8fafc; border:1px solid #e2e8f0; color:#1e293b;
  padding:3px 8px; border-radius:3px; font-size:13px; min-width:60px; }
.fotos-nombre-val { flex:1; }

.fotos-frame { border:1px solid #86efac; border-radius:4px; padding:8px;
  background:#f8fafc; position:relative; width:100%; max-width:500px; }
.fotos-frame-title { position:absolute; top:-10px; left:12px;
  background:#f8fafc; padding:0 6px; font-size:11px; color:#6b7280;
  font-weight:700; letter-spacing:1px; text-transform:uppercase; }
.fotos-img-wrap { display:flex; justify-content:center; align-items:center;
  min-height:300px; padding:8px; }
.foto-empleado { max-width:320px; max-height:400px; width:auto; height:auto;
  object-fit:contain; }
.foto-sin-foto { display:flex; flex-direction:column; align-items:center;
  gap:8px; color:#9ca3af; }
.foto-sin-foto span { font-size:64px; }
.foto-sin-foto p { margin:0; font-size:13px; }

.fotos-btns { display:flex; align-items:center; gap:8px; flex-wrap:wrap; justify-content:center; }
.btn-foto-agregar { background:#dbeafe; color:#1d4ed8; border:1px solid #93c5fd;
  padding:6px 14px; border-radius:5px; cursor:pointer; font-size:12px;
  font-weight:600; transition:background .15s; }
.btn-foto-agregar:hover { background:#bfdbfe; }
.btn-foto-eliminar { background:#fee2e2; color:#991b1b; border:1px solid #fca5a5;
  padding:6px 14px; border-radius:5px; cursor:pointer; font-size:12px;
  font-weight:600; transition:background .15s; }
.btn-foto-eliminar:hover:not(:disabled) { background:#fecaca; }
.btn-foto-eliminar:disabled { opacity:.4; cursor:not-allowed; }
.fotos-msg { font-size:12px; padding:4px 10px; border-radius:4px; }
.fotos-ok  { background:#dcfce7; color:#16a34a; }
.fotos-err { background:#fee2e2; color:#dc2626; }

/* ── Modal ── */
.modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,.4);
  display:flex; align-items:center; justify-content:center; z-index:1000; }
.modal { background:#ffffff; border:1px solid #e2e8f0; border-radius:10px;
  padding:24px; min-width:340px; box-shadow:0 10px 30px rgba(0,0,0,.12); }
.modal h3 { margin:0 0 12px; color:#1e293b; font-size:16px; }
.modal p { color:#6b7280; font-size:13px; margin:0 0 12px; }
.modal-btns { display:flex; gap:8px; margin-top:16px; justify-content:flex-end; }
.baja-error { background:#fee2e2; color:#b91c1c; border:1px solid #fca5a5;
  border-radius:6px; padding:8px 12px; font-size:13px; margin-top:10px; line-height:1.4; }

/* ── Uniforme / EPP layout ── */
.uniforme-layout {
  display: flex; gap: 12px; align-items: flex-start; height: 380px;
}
.uniforme-fechas {
  width: 160px; flex-shrink: 0;
  display: flex; flex-direction: column; height: 100%;
}
.uniforme-fechas .tab-tabla { flex: 1; }
.uniforme-fechas-scroll {
  flex: 1; overflow-y: auto; border: 1px solid #e5e7eb; border-radius: 4px;
}
.uniforme-detalle {
  flex: 1; min-width: 0;
  display: flex; flex-direction: column; height: 100%;
}
.uniforme-detalle-scroll {
  flex: 1; overflow-y: auto; border: 1px solid #e5e7eb; border-radius: 4px;
}
.uniforme-tabla-fechas { font-size: 12px; }
.uniforme-tabla-fechas tr:hover td { background: #f0faf4; }
.uniforme-footer {
  display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
  margin-top: 14px; padding: 10px 12px;
  background: #f1f5f9; border-radius: 6px; font-size: 13px; color: #374151;
}
.uniforme-footer-label { font-weight: 600; }
.input-fecha-ropa {
  border: 1px solid #d1d5db; border-radius: 4px;
  padding: 4px 8px; font-size: 12px; color: #1e293b;
}
.fila-sel td { background: #d1fae5 !important; font-weight: 600; color: #1b4332; }

/* ── Capacitación: dos grillas apiladas, ambas visibles a la vez (réplica FoxPro) ──
   Layout en columna con altura fija; cada grilla tiene su propio scroll interno
   y el header de columnas queda fijo (sticky) al hacer scroll. */
.capacitacion-layout {
  display: flex; flex-direction: column; gap: 12px;
  height: 440px;
}
.cap-grid { display: flex; flex-direction: column; min-height: 0; }
.cap-grid-cursos { flex: 3; }   /* grilla superior (cursos) más alta */
.cap-grid-docs   { flex: 2; }   /* grilla inferior (documentos digitales) */
.cap-grid-scroll {
  flex: 1; overflow: auto; border: 1px solid #e5e7eb; border-radius: 4px;
}
.cap-tabla { font-size: 12px; }
.cap-tabla thead th { position: sticky; top: 0; z-index: 1; }  /* header fijo al scrollear */
.cap-grid-cursos .cap-tabla tbody tr:hover td { background: #f0faf4; }

/* ── TAB Acciones (Propuesta A) ── */
.acciones-tab { padding: 18px; }
.acc-hint { font-size: 13.5px; color: #475569; margin: 0 0 16px; }
.acc-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 14px; }
.acc-sec-tit { font-size: 12px; font-weight: 800; letter-spacing: .03em; text-transform: uppercase; color: #64748b; margin: 18px 0 8px; }
.acc-sec-tit:first-of-type { margin-top: 4px; }
.acc-card.acc-frec { border-color: #facc15; background: #fffdf5; }
.acc-card.acc-frec:hover { border-color: #eab308; box-shadow: 0 8px 20px rgba(234,179,8,.18); }
.acc-card { display: flex; flex-direction: column; align-items: flex-start; gap: 3px; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; cursor: pointer; transition: box-shadow .15s, border-color .15s, transform .05s; text-align: left; }
.acc-card:hover { border-color: #40916c; box-shadow: 0 8px 20px rgba(27,67,50,.12); }
.acc-card:active { transform: translateY(1px); }
.acc-card .acc-ic { font-size: 30px; }
.acc-card b { font-size: 14px; color: #1e293b; }
.acc-card small { font-size: 11.5px; color: #94a3b8; }
/* Modal ABM lanzado desde la ficha */
/* z-index por DEBAJO de los overlays propios del ABM (detalle 9000, ayuda/IA 9200, pdf 10000)
   para que sus modales (Ayuda, +Nuevo, PDF) queden por encima al abrirse desde la ficha. */
.abm-ov { position: fixed; inset: 0; background: rgba(15,23,42,.6); z-index: 8800; display: flex; align-items: center; justify-content: center; padding: 2vh 2vw; }
.abm-md { background: #f8fafc; border-radius: 12px; width: 96vw; height: 94vh; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 24px 70px rgba(0,0,0,.45); }
.abm-md-bar { display: flex; align-items: center; gap: 14px; padding: 10px 16px; background: linear-gradient(120deg,#14352a,#1b4332); color: #fff; }
.abm-foto { width: 52px; height: 52px; border-radius: 10px; object-fit: cover; border: 2px solid rgba(255,255,255,.35); flex-shrink: 0; }
.abm-foto-ph { display: flex; align-items: center; justify-content: center; background: rgba(255,255,255,.18); font-size: 20px; font-weight: 800; color: #fff; letter-spacing: .5px; }
.abm-emp { display: flex; flex-direction: column; gap: 3px; line-height: 1.2; }
.abm-emp-nom { font-size: 18px; font-weight: 800; letter-spacing: .2px; }
.abm-emp-meta { font-size: 12.5px; color: #cfe8db; display: flex; align-items: center; gap: 8px; } .abm-emp-meta b { color: #fff; }
.abm-badge { font-size: 10.5px; font-weight: 800; padding: 1px 8px; border-radius: 999px; } .abm-badge.act { background: #22c55e; color: #052e16; } .abm-badge.baj { background: #f87171; color: #450a0a; }
.pue-badge { font-size: 10.5px; font-weight: 800; padding: 3px 12px; border-radius: 999px; border: 1px solid transparent; cursor: pointer; transition: filter .12s; } .pue-badge:hover:not(:disabled) { filter: brightness(0.94); } .pue-badge:disabled { cursor: default; opacity: .6; } .pue-badge.act { background: #22c55e; color: #052e16; border-color: #16a34a; } .pue-badge.baj { background: #ef4444; color: #fff; border-color: #dc2626; }
.abm-x { margin-left: auto; background: rgba(255,255,255,.92); color: #334155; border: none; border-radius: 7px; padding: 8px 15px; cursor: pointer; font-weight: 700; font-size: 13px; align-self: flex-start; }
.abm-md-body { flex: 1; overflow: auto; background: #f8fafc; }
</style>
