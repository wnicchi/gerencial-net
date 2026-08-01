<!--
  EmpleadosAyuda.vue
  Modal de ayuda del módulo Empleados. Explica cómo usar cada solapa,
  con pasos, leyendas de colores y notas. Navegación por secciones.
-->
<template>
  <Teleport to="body">
    <div class="ay-overlay" @click.self="$emit('close')">
      <div class="ay-modal">
        <!-- Header -->
        <div class="ay-header">
          <span>❓ Ayuda — Módulo Empleados</span>
          <button class="ay-close" @click="$emit('close')">✕</button>
        </div>

        <div class="ay-body">
          <!-- Navegación lateral -->
          <nav class="ay-nav">
            <button v-for="s in secciones" :key="s.id"
                    :class="{ act: sel === s.id }" @click="sel = s.id">
              <span class="ay-nav-ico">{{ s.icon }}</span> {{ s.titulo }}
            </button>
          </nav>

          <!-- Contenido -->
          <div class="ay-content">
            <template v-for="s in secciones" :key="s.id">
              <section v-show="sel === s.id">
                <h3>{{ s.icon }} {{ s.titulo }}</h3>
                <p class="ay-intro" v-html="s.intro"></p>

                <!-- Leyenda de colores -->
                <div v-if="s.leyenda" class="ay-leyenda">
                  <span v-for="l in s.leyenda" :key="l.texto" class="ay-chip" :style="{ background: l.color, color: l.fg || '#1e293b' }">
                    {{ l.texto }}
                  </span>
                </div>

                <!-- Pasos -->
                <div v-if="s.pasos" class="ay-pasos">
                  <div class="ay-pasos-tit">Cómo se usa</div>
                  <ol>
                    <li v-for="(p, i) in s.pasos" :key="i" v-html="p"></li>
                  </ol>
                </div>

                <!-- Notas -->
                <ul v-if="s.notas" class="ay-notas">
                  <li v-for="(n, i) in s.notas" :key="i" v-html="n"></li>
                </ul>
              </section>
            </template>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { ref } from 'vue'

defineEmits<{ close: [] }>()

interface Seccion {
  id: string
  icon: string
  titulo: string
  intro: string
  pasos?: string[]
  notas?: string[]
  leyenda?: { color: string; fg?: string; texto: string }[]
}

const sel = ref('general')

const secciones: Seccion[] = [
  {
    id: 'general', icon: '🧭', titulo: 'General',
    intro: 'El módulo lista los empleados a la izquierda y muestra el detalle del seleccionado a la derecha, organizado en solapas.',
    pasos: [
      '<b>Buscar:</b> escribí en la barra superior. Busca por código, legajo, nombre, domicilio, sexo, padre, madre, hijos, DNI o CUIL.',
      '<b>Filtrar:</b> botones <b>Todos / Activos / Bajas</b> para acotar la lista.',
      '<b>Seleccionar:</b> hacé click en un empleado de la lista; arriba se ve su foto, nombre, estado y legajo.',
      '<b>Nuevo empleado:</b> botón verde <b>+ Nuevo empleado</b>. El <b>Código</b> se reserva automáticamente (siempre PAR).',
      '<b>Editar:</b> en las solapas editables aparece <b>✏️ Editar</b>; al terminar, <b>💾 Guardar</b> o <b>✕ Cancelar</b>.',
      '<b>Dar de baja:</b> botón <b>🚫 Dar de baja</b> (ver notas).',
    ],
    notas: [
      '<b>Al guardar una edición</b> se registra automáticamente en la solapa <b>Historial de Cambios</b> (qué cambió, quién y desde qué terminal).',
      '<b>Para dar de baja</b> el sistema valida: que tenga <b>fecha de baja</b>, que <b>no tenga celular sin devolver</b> y que <b>no tenga tarjeta de crédito activa</b>. Si falla alguna, no permite la baja y avisa el motivo.',
    ],
  },
  {
    id: 'datos', icon: '👤', titulo: 'Datos',
    intro: 'Datos personales, laborales, de remuneración y observaciones del empleado, agrupados en sub-solapas.',
    pasos: [
      'Tocá <b>✏️ Editar</b> para habilitar los campos.',
      'Cambiá entre las sub-solapas <b>Personal · Laboral · Remuneración · Obs.</b>',
      'Guardá con <b>💾 Guardar</b>.',
    ],
    notas: [
      'El <b>Código</b> es la clave del empleado: se genera solo y no se edita.',
      'Validaciones al guardar: legajo obligatorio, nombre no vacío, documento ≠ 0 y CUIL único.',
    ],
  },
  {
    id: 'familia', icon: '👨‍👩‍👦', titulo: 'Familia',
    intro: 'Cónyuge, padre, madre, hijos (con fecha de nacimiento) y datos de embargo.',
    pasos: [
      'Completá cónyuge / padre / madre.',
      'En la grilla de hijos: <b>+ Agregar</b> una fila, cargá nombre y fecha de nacimiento.',
      'Para borrar, tildá el hijo y usá <b>Eliminar seleccionados</b>.',
      'Tocá <b>Confirmar modificaciones</b> para guardar los hijos.',
    ],
  },
  {
    id: 'puestos', icon: '🏢', titulo: 'Puestos / Calificaciones',
    intro: 'Puestos asignados, calificaciones por puesto, tareas y personal a cargo. Permite imprimir un legajo en PDF.',
    pasos: [
      'Revisá los puestos y sus calificaciones en las grillas.',
      'Usá el botón de <b>imprimir</b> para generar el PDF del legajo (con foto y datos).',
    ],
  },
  {
    id: 'uniforme', icon: '👕', titulo: 'Gestión Uniforme-EPP',
    intro: 'Entregas de ropa/EPP agrupadas por fecha. Permite imprimir la constancia de entrega.',
    pasos: [
      'En el panel izquierdo elegí una <b>fecha de entrega</b>; a la derecha se ve el detalle.',
      'Filtrá por rango con <b>Desde / Hasta</b> y <b>Consultar</b>.',
      'Tocá <b>🖨️ Imprimir Constancia</b> para generar el PDF (Res. 299/2011 o de obsequio según el contenido).',
    ],
  },
  {
    id: 'epp', icon: '🦺', titulo: 'EPP Asignada',
    intro: 'Stock actual de EPP del empleado (Código, Descripción, Talle).',
    notas: ['Es de solo lectura; se completa con el stock vigente del empleado.'],
  },
  {
    id: 'capacitacion', icon: '📚', titulo: 'Capacitación',
    intro: 'Dos grillas: arriba las capacitaciones del empleado; abajo, los <b>documentos digitales</b> de la capacitación seleccionada.',
    pasos: [
      'Hacé click en una capacitación de la grilla superior.',
      'Abajo aparecen sus documentos digitales (si tiene).',
    ],
  },
  {
    id: 'licencia', icon: '🚗', titulo: 'Licencia',
    intro: 'Hasta 3 carnets de conducir + datos médicos (grupo y factor sanguíneo, donante).',
    pasos: [
      'Tocá <b>✏️ Editar</b>.',
      'En cada <b>Carnet</b>: nº de licencia, categoría (desplegable) y vencimiento.',
      'Cargá Sangre Factor / Grupo y Donante. Guardá.',
    ],
    notas: ['El vencimiento se resalta en <b style="color:#dc2626">rojo</b> si está vencido.'],
  },
  {
    id: 'examenes', icon: '🩺', titulo: 'Exámenes y Certificados Médicos',
    intro: 'Grilla de exámenes/certificados; al seleccionar uno se ve su detalle y la documentación digital.',
    pasos: [
      'Hacé click en un registro de la grilla.',
      'En el panel <b>EXAMEN / CERTIFICADO</b> ves fecha, próximo, tipo, enfermedad y notas médicas.',
      'Abajo, los documentos digitales del examen.',
    ],
    notas: ['Si es un <b>certificado</b> (no examen), se oculta el campo "Próximo".'],
  },
  {
    id: 'documentos', icon: '📄', titulo: 'Documentación',
    intro: 'Documentos digitales del empleado: ver, agregar, eliminar, filtrar y editar la fecha.',
    pasos: [
      '<b>Filtrar</b> por detalle tipo con el cuadro de búsqueda.',
      '<b>👁️ Visualizar Documentación:</b> seleccioná un documento y abrilo en el modal (PDF/imagen) o descargalo (XLS/DOC).',
      '<b>🗑️ Eliminar:</b> borra el documento seleccionado (también el archivo).',
      '<b>Editar fecha:</b> cambiala en la columna FECHA y tocá <b>💾 Confirmar Cambio</b>.',
      '<b>Agregar:</b> en el panel inferior elegí Tipo, archivo (botón Ver para previsualizar), observación y fecha, luego <b>✔ Aceptar</b>.',
    ],
  },
  {
    id: 'acargo', icon: '👥', titulo: 'Personal a Cargo',
    intro: 'Selector para marcar qué empleados están a cargo del actual.',
    leyenda: [{ color: '#fef9c3', texto: 'Amarillo = ya está a cargo' }],
    pasos: [
      'Tildá en la columna <b>Elegir</b> los empleados a cargo (filtrá por nombre/código si querés).',
      'Tocá <b>💾 Grabar Personal a Cargo</b>.',
      '<b>📋 Informe</b>: genera el PDF general de toda la empresa (jefes y sus subordinados).',
    ],
  },
  {
    id: 'valorhoras', icon: '⏱️', titulo: 'Valores Horas',
    intro: 'Costo de la hora laboral (calculado) y la cantidad de horas consideradas normales (editable).',
    pasos: [
      'Tocá <b>✏️ Editar</b>.',
      'Cargá <b>De Lunes a Viernes</b> y <b>Sábados</b> (horas). Los costos (50%, 100%, nocturna) se recalculan solos.',
      'Guardá.',
    ],
  },
  {
    id: 'faltas', icon: '❌', titulo: 'Faltas',
    intro: 'Faltas y licencias del empleado. Al seleccionar una, abajo se ve su documentación respaldatoria.',
    leyenda: [
      { color: '#86efac', texto: 'Verde = con documentación' },
      { color: '#fca5a5', texto: 'Rojo = sin documentación' },
    ],
    pasos: [
      'Hacé click en una falta de la grilla superior.',
      'En <b>Documentación Respaldatoria</b> tocá el icono <b>👁️</b> del renglón para ver el documento.',
    ],
  },
  {
    id: 'celular', icon: '📱', titulo: 'Celular',
    intro: 'Equipos celulares entregados al empleado, con sus características y fechas.',
    leyenda: [{ color: '#ef4444', fg: '#fff', texto: 'Rojo = equipo devuelto' }],
    notas: ['Las filas en rojo indican equipos ya devueltos (tienen fecha de devolución).'],
  },
  {
    id: 'tarjetas', icon: '💳', titulo: 'Tarjetas',
    intro: 'Tarjetas del empleado (se leen del sistema de Gestión). Descripción, números y cuenta bancaria.',
    notas: ['Es de solo lectura; los datos provienen de la base de Gestión (SILCAR).'],
  },
  {
    id: 'historial', icon: '📋', titulo: 'Historial de Cambios',
    intro: 'Auditoría: cada edición de un empleado queda registrada (fecha, usuario, terminal y qué cambió).',
    pasos: [
      'Hacé click en una fila para ver el detalle de los cambios a la derecha.',
      'Filtrá por rango con <b>Imprimir desde / hasta</b>.',
      '<b>🖨️ Imprimir:</b> genera el PDF del historial.',
      '<b>🗑️ Eliminar Historial:</b> tildá registros con el checkbox y borralos.',
    ],
    notas: ['Se llena <b>automáticamente</b> cada vez que se edita y guarda un empleado.'],
  },
]
</script>

<style scoped>
.ay-overlay { position:fixed; inset:0; background:rgba(0,0,0,.55); z-index:10000;
  display:flex; align-items:center; justify-content:center; }
.ay-modal { width:min(900px, 94vw); height:min(620px, 92vh); background:#fff;
  border-radius:10px; overflow:hidden; display:flex; flex-direction:column;
  box-shadow:0 12px 40px rgba(0,0,0,.4); }
.ay-header { display:flex; align-items:center; justify-content:space-between;
  padding:12px 18px; background:#1b4332; color:#fff; font-weight:700; font-size:15px; }
.ay-close { background:transparent; border:none; color:#fff; font-size:18px; cursor:pointer; }
.ay-body { flex:1; display:flex; min-height:0; }
.ay-nav { width:230px; flex-shrink:0; border-right:1px solid #e5e7eb; overflow-y:auto;
  background:#f8fafc; padding:8px; }
.ay-nav button { width:100%; text-align:left; background:transparent; border:none;
  padding:8px 10px; border-radius:6px; font-size:13px; color:#374151; cursor:pointer;
  display:flex; align-items:center; gap:8px; }
.ay-nav button:hover { background:#eef4fb; }
.ay-nav button.act { background:#d1fae5; color:#1b4332; font-weight:700; }
.ay-nav-ico { width:18px; text-align:center; }
.ay-content { flex:1; overflow-y:auto; padding:18px 24px; }
.ay-content h3 { margin:0 0 8px; color:#1b4332; font-size:18px; }
.ay-intro { font-size:14px; color:#374151; line-height:1.5; margin:0 0 14px; }
.ay-leyenda { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:14px; }
.ay-chip { font-size:12px; font-weight:600; padding:3px 10px; border-radius:12px; border:1px solid rgba(0,0,0,.1); }
.ay-pasos { background:#f8fafc; border:1px solid #e5e7eb; border-radius:8px; padding:10px 14px; margin-bottom:12px; }
.ay-pasos-tit { font-size:12px; font-weight:700; color:#1b4332; text-transform:uppercase;
  letter-spacing:.5px; margin-bottom:6px; }
.ay-pasos ol { margin:0; padding-left:20px; }
.ay-pasos li { font-size:13px; color:#374151; line-height:1.7; }
.ay-notas { margin:0; padding-left:18px; }
.ay-notas li { font-size:13px; color:#475569; line-height:1.6; margin-bottom:4px; }
.ay-notas li::marker { content:'💡 '; }
</style>
