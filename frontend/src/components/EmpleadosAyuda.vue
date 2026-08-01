<!--
  EmpleadosAyuda.vue — v2 (elaborada con gráficos y animaciones)
  Modal de ayuda del módulo Empleados: navegación por secciones, diagramas SVG
  animados, transiciones entre secciones, pasos con aparición escalonada y
  leyendas de colores animadas.
-->
<template>
  <Teleport to="body">
    <transition name="ay-fade" appear>
      <div class="ay-overlay" @click.self="$emit('close')">
        <transition name="ay-pop" appear>
          <div class="ay-modal">
            <!-- Hero header -->
            <div class="ay-hero">
              <div class="ay-hero-orbe ay-orbe1"></div>
              <div class="ay-hero-orbe ay-orbe2"></div>
              <div class="ay-hero-ico">👥</div>
              <div class="ay-hero-tx">
                <h2>Guía del módulo Empleados</h2>
                <p>Cómo usar cada solapa, paso a paso</p>
              </div>
              <button class="ay-close" @click="$emit('close')">✕</button>
            </div>

            <div class="ay-body">
              <!-- Navegación -->
              <nav class="ay-nav">
                <button v-for="s in secciones" :key="s.id"
                        :class="{ act: sel === s.id }" @click="ir(s.id)">
                  <span class="ay-nav-ico">{{ s.icon }}</span>
                  <span class="ay-nav-tx">{{ s.titulo }}</span>
                  <span class="ay-nav-flecha">›</span>
                </button>
              </nav>

              <!-- Contenido con transición -->
              <div class="ay-content">
                <transition :name="dir" mode="out-in">
                  <section :key="actual.id" class="ay-sec">
                    <h3><span class="ay-sec-ico">{{ actual.icon }}</span> {{ actual.titulo }}</h3>
                    <p class="ay-intro" v-html="actual.intro"></p>

                    <!-- Diagrama animado -->
                    <div class="ay-diagrama">
                      <DiagramaLayout       v-if="actual.diagrama === 'layout'" />
                      <DiagramaMasterDetail v-else-if="actual.diagrama === 'masterDetail'" />
                      <DiagramaDocs         v-else-if="actual.diagrama === 'docs'" />
                      <DiagramaBaja         v-else-if="actual.diagrama === 'baja'" />
                      <DiagramaEditar       v-else-if="actual.diagrama === 'editar'" />
                      <DiagramaConstancia   v-else-if="actual.diagrama === 'constancia'" />
                      <div v-else class="ay-badge-grande">{{ actual.icon }}</div>
                    </div>

                    <!-- Leyenda de colores -->
                    <div v-if="actual.leyenda" class="ay-leyenda">
                      <span v-for="l in actual.leyenda" :key="l.texto" class="ay-chip"
                            :style="{ background: l.color, color: l.fg || '#1e293b' }">
                        <span class="ay-chip-pt" :style="{ background: l.fg || '#1e293b' }"></span>{{ l.texto }}
                      </span>
                    </div>

                    <!-- Pasos -->
                    <div v-if="actual.pasos" class="ay-pasos">
                      <div class="ay-pasos-tit">⚡ Cómo se usa</div>
                      <ol>
                        <li v-for="(p, i) in actual.pasos" :key="i"
                            class="ay-paso" :style="{ animationDelay: (i * 90 + 120) + 'ms' }">
                          <span class="ay-paso-num">{{ i + 1 }}</span>
                          <span v-html="p"></span>
                        </li>
                      </ol>
                    </div>

                    <!-- Notas -->
                    <ul v-if="actual.notas" class="ay-notas">
                      <li v-for="(n, i) in actual.notas" :key="i" v-html="n"></li>
                    </ul>
                  </section>
                </transition>
              </div>
            </div>
          </div>
        </transition>
      </div>
    </transition>
  </Teleport>
</template>

<script setup lang="ts">
import { ref, computed, h } from 'vue'

defineEmits<{ close: [] }>()

/* ── Diagramas SVG animados (componentes funcionales inline) ──────────────── */
const DiagramaLayout = () => h('svg', { viewBox: '0 0 320 150', class: 'dg' }, [
  // lista
  h('rect', { x: 6, y: 8, width: 84, height: 134, rx: 6, class: 'dg-panel' }),
  ...[0, 1, 2, 3].map(i => h('rect', { x: 14, y: 18 + i * 24, width: 68, height: 16, rx: 3, class: i === 1 ? 'dg-row-sel' : 'dg-row' })),
  // tabs
  ...[0, 1, 2, 3].map(i => h('rect', { x: 100 + i * 40, y: 8, width: 34, height: 14, rx: 3, class: i === 0 ? 'dg-tab-act' : 'dg-tab' })),
  // detalle
  h('rect', { x: 100, y: 28, width: 214, height: 114, rx: 6, class: 'dg-panel' }),
  ...[0, 1, 2, 3, 4].map(i => h('rect', { x: 110, y: 40 + i * 20, width: i % 2 ? 120 : 190, height: 10, rx: 3, class: 'dg-line', style: `animation-delay:${i * 120}ms` })),
  // cursor
  h('circle', { cx: 48, cy: 50, r: 5, class: 'dg-cursor' }),
])

const DiagramaMasterDetail = () => h('svg', { viewBox: '0 0 320 160', class: 'dg' }, [
  h('text', { x: 12, y: 16, class: 'dg-cap' }, 'Grilla principal'),
  h('rect', { x: 8, y: 22, width: 304, height: 52, rx: 6, class: 'dg-panel' }),
  ...[0, 1, 2].map(i => h('rect', { x: 16, y: 30 + i * 14, width: 288, height: 10, rx: 2, class: i === 0 ? 'dg-row-sel' : 'dg-row' })),
  // flecha
  h('path', { d: 'M160 78 L160 92', class: 'dg-arrow' }),
  h('path', { d: 'M155 88 L160 94 L165 88', class: 'dg-arrow' }),
  h('text', { x: 12, y: 110, class: 'dg-cap' }, 'Documentos del seleccionado'),
  h('rect', { x: 8, y: 116, width: 304, height: 40, rx: 6, class: 'dg-panel dg-panel-2' }),
  ...[0, 1].map(i => h('rect', { x: 16, y: 124 + i * 14, width: 288, height: 10, rx: 2, class: 'dg-row', style: `animation-delay:${600 + i * 150}ms` })),
])

const DiagramaDocs = () => h('svg', { viewBox: '0 0 320 150', class: 'dg' }, [
  h('rect', { x: 110, y: 24, width: 100, height: 100, rx: 8, class: 'dg-doc' }),
  h('path', { d: 'M188 24 L210 46 L188 46 Z', class: 'dg-doc-fold' }),
  ...[0, 1, 2, 3].map(i => h('rect', { x: 124, y: 60 + i * 13, width: i === 3 ? 40 : 72, height: 6, rx: 2, class: 'dg-line', style: `animation-delay:${i * 120}ms` })),
  // acciones
  h('circle', { cx: 50, cy: 50, r: 18, class: 'dg-accion dg-a1' }),
  h('text', { x: 50, y: 56, class: 'dg-emoji' }, '👁️'),
  h('circle', { cx: 50, cy: 100, r: 18, class: 'dg-accion dg-a2' }),
  h('text', { x: 50, y: 106, class: 'dg-emoji' }, '🗑️'),
  h('circle', { cx: 270, cy: 75, r: 18, class: 'dg-accion dg-a3' }),
  h('text', { x: 270, y: 81, class: 'dg-emoji' }, '➕'),
])

const DiagramaBaja = () => h('svg', { viewBox: '0 0 340 120', class: 'dg' }, [
  h('line', { x1: 30, y1: 60, x2: 310, y2: 60, class: 'dg-track' }),
  ...[
    { x: 60, emo: '📅', t: 'Fecha', d: 0 },
    { x: 160, emo: '📱', t: 'Celular', d: 300 },
    { x: 260, emo: '💳', t: 'Tarjeta', d: 600 },
  ].flatMap(g => [
    h('circle', { cx: g.x, cy: 60, r: 22, class: 'dg-gate', style: `animation-delay:${g.d}ms` }),
    h('text', { x: g.x, y: 67, class: 'dg-emoji-lg' }, g.emo),
    h('text', { x: g.x, y: 98, class: 'dg-cap', 'text-anchor': 'middle' }, g.t),
    h('text', { x: g.x + 16, y: 44, class: 'dg-check', style: `animation-delay:${g.d + 400}ms` }, '✓'),
  ]),
  h('text', { x: 310, y: 30, class: 'dg-lock' }, '🔓'),
])

const DiagramaEditar = () => h('svg', { viewBox: '0 0 320 120', class: 'dg' }, [
  h('rect', { x: 40, y: 40, width: 240, height: 40, rx: 8, class: 'dg-input' }),
  h('rect', { x: 52, y: 54, width: 120, height: 12, rx: 3, class: 'dg-line' }),
  h('rect', { x: 232, y: 48, width: 36, height: 24, rx: 5, class: 'dg-pencil' }),
  h('text', { x: 250, y: 65, class: 'dg-emoji' }, '✏️'),
  h('text', { x: 160, y: 108, class: 'dg-cap', 'text-anchor': 'middle' }, 'Editar → Guardar 💾'),
])

const DiagramaConstancia = () => h('svg', { viewBox: '0 0 320 140', class: 'dg' }, [
  h('rect', { x: 60, y: 16, width: 90, height: 112, rx: 6, class: 'dg-doc' }),
  h('rect', { x: 170, y: 16, width: 90, height: 112, rx: 6, class: 'dg-doc dg-doc-b' }),
  ...[0, 1, 2, 3, 4].map(i => h('rect', { x: 70, y: 32 + i * 18, width: 70, height: 7, rx: 2, class: 'dg-line', style: `animation-delay:${i * 100}ms` })),
  h('text', { x: 105, y: 122, class: 'dg-emoji' }, '🖨️'),
])

/* ── Datos de las secciones ──────────────────────────────────────────────── */
interface Seccion {
  id: string; icon: string; titulo: string; intro: string
  diagrama?: string
  pasos?: string[]; notas?: string[]
  leyenda?: { color: string; fg?: string; texto: string }[]
}

const secciones: Seccion[] = [
  {
    id: 'general', icon: '🧭', titulo: 'General', diagrama: 'layout',
    intro: 'A la izquierda está la <b>lista de empleados</b>; al seleccionar uno, a la derecha aparece su detalle organizado en <b>solapas</b>.',
    pasos: [
      '<b>Buscar:</b> por código, legajo, nombre, domicilio, sexo, padre, madre, hijos, DNI o CUIL.',
      '<b>Filtrar:</b> <b>Todos / Activos / Bajas</b>.',
      '<b>Seleccionar</b> un empleado de la lista.',
      '<b>+ Nuevo empleado:</b> el código se reserva solo (siempre PAR).',
      '<b>✏️ Editar</b> en las solapas editables y <b>💾 Guardar</b>.',
    ],
    notas: [
      'Cada edición se registra solo en <b>Historial de Cambios</b>.',
      'Para <b>dar de baja</b> hay validaciones (mirá la sección "Dar de baja").',
    ],
  },
  {
    id: 'baja', icon: '🚫', titulo: 'Dar de baja', diagrama: 'baja',
    intro: 'Antes de pasar un empleado a pasivo, el sistema verifica <b>tres condiciones</b>. Si alguna falla, no permite la baja.',
    leyenda: [
      { color: '#dcfce7', texto: '✓ Cumple → permite la baja' },
      { color: '#fee2e2', texto: '✗ Falta → bloquea y avisa' },
    ],
    pasos: [
      'Tocá <b>🚫 Dar de baja</b> e ingresá la <b>fecha</b> (obligatoria).',
      'El sistema chequea que <b>no tenga celular sin devolver</b>.',
      'Y que <b>no tenga tarjeta de crédito activa</b> en el sistema de Gestión.',
      'Si todo está OK, confirma la baja.',
    ],
  },
  {
    id: 'datos', icon: '👤', titulo: 'Datos', diagrama: 'editar',
    intro: 'Datos personales, laborales, de remuneración y observaciones, en sub-solapas.',
    pasos: [
      'Tocá <b>✏️ Editar</b>.',
      'Navegá <b>Personal · Laboral · Remuneración · Obs.</b>',
      'Guardá con <b>💾 Guardar</b>.',
    ],
    notas: [
      'El <b>Código</b> es la clave: se genera solo, no se edita.',
      'Valida: legajo, nombre, documento ≠ 0 y CUIL único.',
    ],
  },
  {
    id: 'familia', icon: '👨‍👩‍👦', titulo: 'Familia',
    intro: 'Cónyuge, padre, madre, <b>hijos</b> (con fecha de nacimiento) y datos de embargo.',
    pasos: [
      'Completá cónyuge / padre / madre.',
      '<b>+ Agregar</b> hijos y cargar nombre y fecha de nacimiento.',
      'Tildá y <b>Eliminar seleccionados</b> para quitar.',
      '<b>Confirmar modificaciones</b> para guardar.',
    ],
  },
  {
    id: 'puestos', icon: '🏢', titulo: 'Puestos / Calificaciones',
    intro: 'Puestos, calificaciones, tareas y personal a cargo. Imprime un legajo en PDF.',
    pasos: ['Revisá puestos y calificaciones.', 'Imprimí el <b>PDF del legajo</b> (con foto y datos).'],
  },
  {
    id: 'uniforme', icon: '👕', titulo: 'Gestión Uniforme-EPP', diagrama: 'constancia',
    intro: 'Entregas de ropa/EPP por fecha. Imprime la constancia de entrega.',
    pasos: [
      'Elegí una <b>fecha</b> a la izquierda; el detalle aparece a la derecha.',
      'Filtrá por <b>Desde / Hasta</b> y <b>Consultar</b>.',
      '<b>🖨️ Imprimir Constancia</b> (Res.299/2011 u obsequio).',
    ],
  },
  {
    id: 'epp', icon: '🦺', titulo: 'EPP Asignada',
    intro: 'Stock actual de EPP del empleado: Código, Descripción y Talle.',
    notas: ['Es de solo lectura.'],
  },
  {
    id: 'capacitacion', icon: '📚', titulo: 'Capacitación', diagrama: 'masterDetail',
    intro: 'Arriba las capacitaciones; abajo, los <b>documentos digitales</b> de la seleccionada.',
    pasos: ['Click en una capacitación.', 'Abajo aparecen sus documentos.'],
  },
  {
    id: 'licencia', icon: '🚗', titulo: 'Licencia',
    intro: 'Hasta 3 carnets de conducir + grupo/factor sanguíneo y donante.',
    pasos: [
      'Tocá <b>✏️ Editar</b>.',
      'Por carnet: nº, categoría (desplegable) y vencimiento.',
      'Cargá Sangre Factor / Grupo / Donante. Guardá.',
    ],
    notas: ['El vencimiento se marca en <b style="color:#dc2626">rojo</b> si está vencido.'],
  },
  {
    id: 'examenes', icon: '🩺', titulo: 'Exámenes y Certificados', diagrama: 'masterDetail',
    intro: 'Grilla de exámenes/certificados; al elegir uno se ve su detalle y documentación.',
    pasos: [
      'Click en un registro.',
      'Ves fecha, próximo, tipo, enfermedad y notas médicas.',
      'Abajo, los documentos del examen.',
    ],
    notas: ['En un <b>certificado</b> se oculta el campo "Próximo".'],
  },
  {
    id: 'documentos', icon: '📄', titulo: 'Documentación', diagrama: 'docs',
    intro: 'Documentos digitales del empleado: ver, agregar, eliminar, filtrar y editar la fecha.',
    pasos: [
      '<b>Filtrar</b> por detalle tipo.',
      '<b>👁️ Visualizar</b> el documento (PDF/imagen) o descargarlo.',
      '<b>🗑️ Eliminar</b> el seleccionado.',
      '<b>Editar fecha</b> y <b>💾 Confirmar Cambio</b>.',
      '<b>➕ Agregar:</b> tipo, archivo (Ver para previsualizar), observación, fecha y <b>✔ Aceptar</b>.',
    ],
  },
  {
    id: 'acargo', icon: '👥', titulo: 'Personal a Cargo',
    intro: 'Marcá qué empleados están a cargo del actual y generá el informe.',
    leyenda: [{ color: '#fef9c3', texto: '🟡 Amarillo = ya está a cargo' }],
    pasos: [
      'Tildá en <b>Elegir</b> (filtrá por nombre/código).',
      '<b>💾 Grabar Personal a Cargo</b>.',
      '<b>📋 Informe</b>: PDF general de toda la empresa.',
    ],
  },
  {
    id: 'valorhoras', icon: '⏱️', titulo: 'Valores Horas', diagrama: 'editar',
    intro: 'Costo de la hora (calculado) y horas consideradas normales (editable).',
    pasos: [
      'Tocá <b>✏️ Editar</b>.',
      'Cargá <b>Lunes a Viernes</b> y <b>Sábados</b>; los costos se recalculan solos.',
      'Guardá.',
    ],
  },
  {
    id: 'faltas', icon: '❌', titulo: 'Faltas', diagrama: 'masterDetail',
    intro: 'Faltas y licencias; al elegir una se ve su documentación respaldatoria.',
    leyenda: [
      { color: '#86efac', texto: '🟢 Verde = con documentación' },
      { color: '#fca5a5', texto: '🔴 Rojo = sin documentación' },
    ],
    pasos: ['Click en una falta.', 'Tocá el <b>👁️</b> del renglón para ver el documento.'],
  },
  {
    id: 'celular', icon: '📱', titulo: 'Celular',
    intro: 'Equipos celulares entregados, con características y fechas.',
    leyenda: [{ color: '#ef4444', fg: '#fff', texto: '🔴 Rojo = equipo devuelto' }],
    notas: ['Las filas rojas son equipos ya devueltos.'],
  },
  {
    id: 'tarjetas', icon: '💳', titulo: 'Tarjetas',
    intro: 'Tarjetas del empleado (se leen del sistema de Gestión): descripción, números y cuenta.',
    notas: ['Solo lectura; los datos provienen del sistema de Gestión.'],
  },
  {
    id: 'historial', icon: '📋', titulo: 'Historial de Cambios',
    intro: 'Auditoría: cada edición queda registrada (fecha, usuario, terminal y qué cambió).',
    pasos: [
      'Click en una fila para ver el detalle.',
      'Filtrá por <b>desde / hasta</b>.',
      '<b>🖨️ Imprimir</b> el historial en PDF.',
      '<b>🗑️ Eliminar</b> registros marcados.',
    ],
    notas: ['Se llena <b>automáticamente</b> al editar y guardar.'],
  },
]

const sel = ref('general')
const dir = ref('ay-slide-next')
const actual = computed(() => secciones.find(s => s.id === sel.value)!)

const ir = (id: string) => {
  const a = secciones.findIndex(s => s.id === sel.value)
  const b = secciones.findIndex(s => s.id === id)
  dir.value = b > a ? 'ay-slide-next' : 'ay-slide-prev'
  sel.value = id
}
</script>

<style scoped>
/* ── Overlay y modal ── */
.ay-overlay { position:fixed; inset:0; background:rgba(15,23,42,.6); backdrop-filter:blur(3px);
  z-index:10000; display:flex; align-items:center; justify-content:center; }
.ay-modal { width:min(960px, 95vw); height:min(640px, 93vh); background:#fff;
  border-radius:16px; overflow:hidden; display:flex; flex-direction:column;
  box-shadow:0 24px 60px rgba(0,0,0,.45); }

/* entrada modal */
.ay-fade-enter-active, .ay-fade-leave-active { transition:opacity .25s ease; }
.ay-fade-enter-from, .ay-fade-leave-to { opacity:0; }
.ay-pop-enter-active { transition:transform .38s cubic-bezier(.2,.9,.3,1.2), opacity .3s ease; }
.ay-pop-leave-active { transition:transform .2s ease, opacity .2s ease; }
.ay-pop-enter-from { transform:translateY(30px) scale(.94); opacity:0; }
.ay-pop-leave-to { transform:scale(.96); opacity:0; }

/* ── Hero ── */
.ay-hero { position:relative; overflow:hidden; padding:18px 22px; display:flex; align-items:center; gap:16px;
  background:linear-gradient(120deg,#1b4332,#2d6a4f 60%,#40916c); color:#fff; }
.ay-hero-orbe { position:absolute; border-radius:50%; filter:blur(2px); opacity:.25; }
.ay-orbe1 { width:120px; height:120px; background:#95d5b2; top:-40px; right:60px; animation:flota 6s ease-in-out infinite; }
.ay-orbe2 { width:80px; height:80px; background:#74c69d; bottom:-30px; right:200px; animation:flota 8s ease-in-out infinite reverse; }
@keyframes flota { 0%,100%{ transform:translateY(0) } 50%{ transform:translateY(14px) } }
.ay-hero-ico { font-size:34px; animation:late 2.4s ease-in-out infinite; }
@keyframes late { 0%,100%{ transform:scale(1) } 50%{ transform:scale(1.12) } }
.ay-hero-tx h2 { margin:0; font-size:18px; }
.ay-hero-tx p { margin:2px 0 0; font-size:12px; opacity:.85; }
.ay-close { margin-left:auto; background:rgba(255,255,255,.15); border:none; color:#fff;
  width:30px; height:30px; border-radius:8px; font-size:15px; cursor:pointer; transition:background .2s; z-index:2; }
.ay-close:hover { background:rgba(255,255,255,.3); }

/* ── Body ── */
.ay-body { flex:1; display:flex; min-height:0; }
.ay-nav { width:240px; flex-shrink:0; border-right:1px solid #eef2f7; overflow-y:auto; padding:10px; background:#f8fafc; }
.ay-nav button { width:100%; display:flex; align-items:center; gap:9px; text-align:left;
  background:transparent; border:none; padding:9px 11px; border-radius:9px; font-size:13px;
  color:#475569; cursor:pointer; transition:all .18s; position:relative; }
.ay-nav button:hover { background:#eef4fb; transform:translateX(2px); }
.ay-nav button.act { background:linear-gradient(90deg,#d1fae5,#ecfdf5); color:#1b4332; font-weight:700;
  box-shadow:inset 3px 0 0 #16a34a; }
.ay-nav-ico { width:20px; text-align:center; font-size:15px; }
.ay-nav-tx { flex:1; }
.ay-nav-flecha { opacity:0; transition:opacity .2s, transform .2s; }
.ay-nav button.act .ay-nav-flecha { opacity:1; transform:translateX(2px); color:#16a34a; }

/* ── Contenido ── */
.ay-content { flex:1; overflow-y:auto; padding:22px 26px; position:relative; }
.ay-sec h3 { margin:0 0 8px; color:#1b4332; font-size:20px; display:flex; align-items:center; gap:10px; }
.ay-sec-ico { font-size:24px; }
.ay-intro { font-size:14px; color:#374151; line-height:1.55; margin:0 0 16px; }

/* transición de secciones */
.ay-slide-next-enter-active, .ay-slide-prev-enter-active { transition:all .32s cubic-bezier(.2,.8,.3,1); }
.ay-slide-next-leave-active, .ay-slide-prev-leave-active { transition:all .16s ease; position:absolute; }
.ay-slide-next-enter-from { opacity:0; transform:translateX(28px); }
.ay-slide-prev-enter-from { opacity:0; transform:translateX(-28px); }
.ay-slide-next-leave-to, .ay-slide-prev-leave-to { opacity:0; }

/* ── Diagramas ── */
.ay-diagrama { display:flex; justify-content:center; align-items:center; min-height:120px;
  background:#f8fafc; border:1px solid #eef2f7; border-radius:12px; padding:14px; margin-bottom:16px; }
.dg { width:100%; max-width:360px; height:auto; }
.ay-badge-grande { font-size:56px; animation:late 2.4s ease-in-out infinite; }

/* primitivas de diagrama */
.dg-panel { fill:#fff; stroke:#cbd5e1; stroke-width:1.5; }
.dg-panel-2 { stroke-dasharray:4 3; }
.dg-row { fill:#e2e8f0; }
.dg-row-sel { fill:#86efac; animation:pulso 2s ease-in-out infinite; }
.dg-tab { fill:#e2e8f0; }
.dg-tab-act { fill:#16a34a; }
.dg-line { fill:#cbd5e1; transform-origin:left; animation:crece 1.2s ease forwards; opacity:0; }
.dg-cap { fill:#64748b; font-size:9px; font-weight:600; }
.dg-cursor { fill:#1b4332; animation:cursor 3s ease-in-out infinite; }
.dg-arrow { stroke:#16a34a; stroke-width:2.5; fill:none; stroke-linecap:round; animation:bajar 1.8s ease-in-out infinite; }
.dg-doc { fill:#fff; stroke:#94a3b8; stroke-width:1.5; }
.dg-doc-b { fill:#f0fdf4; }
.dg-doc-fold { fill:#cbd5e1; }
.dg-accion { fill:#fff; stroke:#16a34a; stroke-width:1.5; }
.dg-a1 { animation:saltar 2.4s ease-in-out infinite; }
.dg-a2 { animation:saltar 2.4s ease-in-out .4s infinite; }
.dg-a3 { animation:saltar 2.4s ease-in-out .8s infinite; }
.dg-emoji { font-size:15px; text-anchor:middle; }
.dg-emoji-lg { font-size:20px; text-anchor:middle; }
.dg-track { stroke:#cbd5e1; stroke-width:3; stroke-linecap:round; }
.dg-gate { fill:#fff; stroke:#16a34a; stroke-width:2; animation:aparece .5s ease forwards; opacity:0; transform:scale(.6); transform-origin:center; transform-box:fill-box; }
.dg-check { fill:#16a34a; font-size:14px; font-weight:800; text-anchor:middle; opacity:0; animation:tilde .4s ease forwards; }
.dg-lock { font-size:18px; text-anchor:middle; animation:late 2s ease-in-out infinite; }
.dg-input { fill:#fff; stroke:#16a34a; stroke-width:1.5; }
.dg-pencil { fill:#dcfce7; stroke:#16a34a; stroke-width:1; animation:saltar 2s ease-in-out infinite; }

@keyframes pulso { 0%,100%{ opacity:1 } 50%{ opacity:.5 } }
@keyframes crece { to { opacity:1 } }
@keyframes cursor { 0%{ transform:translate(0,0); opacity:0 } 20%{ opacity:1 } 50%{ transform:translate(0,0) } 60%{ transform:translate(56px,-2px) } 100%{ transform:translate(56px,-2px); opacity:.4 } }
@keyframes bajar { 0%,100%{ transform:translateY(-3px); opacity:.5 } 50%{ transform:translateY(3px); opacity:1 } }
@keyframes saltar { 0%,100%{ transform:translateY(0) } 50%{ transform:translateY(-5px) } }
@keyframes aparece { to { opacity:1; transform:scale(1) } }
@keyframes tilde { to { opacity:1 } }

/* ── Leyenda ── */
.ay-leyenda { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:16px; }
.ay-chip { display:inline-flex; align-items:center; gap:7px; font-size:12px; font-weight:600;
  padding:5px 12px; border-radius:14px; border:1px solid rgba(0,0,0,.08); animation:chipIn .4s ease backwards; }
.ay-chip-pt { width:8px; height:8px; border-radius:50%; animation:pulso 1.6s ease-in-out infinite; }
@keyframes chipIn { from { opacity:0; transform:translateY(6px) } }

/* ── Pasos ── */
.ay-pasos { background:#f8fafc; border:1px solid #eef2f7; border-radius:12px; padding:12px 16px; margin-bottom:14px; }
.ay-pasos-tit { font-size:12px; font-weight:700; color:#1b4332; text-transform:uppercase; letter-spacing:.5px; margin-bottom:10px; }
.ay-pasos ol { margin:0; padding:0; list-style:none; }
.ay-paso { display:flex; align-items:flex-start; gap:10px; font-size:13px; color:#374151;
  line-height:1.5; margin-bottom:9px; opacity:0; animation:pasoIn .4s ease forwards; }
.ay-paso-num { flex-shrink:0; width:22px; height:22px; border-radius:50%; background:#16a34a; color:#fff;
  font-size:12px; font-weight:700; display:flex; align-items:center; justify-content:center; }
@keyframes pasoIn { from { opacity:0; transform:translateX(-8px) } to { opacity:1; transform:none } }

/* ── Notas ── */
.ay-notas { margin:0; padding-left:6px; list-style:none; }
.ay-notas li { font-size:13px; color:#475569; line-height:1.55; margin-bottom:6px; padding-left:24px; position:relative; }
.ay-notas li::before { content:'💡'; position:absolute; left:0; }
</style>
