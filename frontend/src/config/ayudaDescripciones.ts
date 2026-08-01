// Descripciones cortas ("qué hace") de cada módulo, para el Centro de Ayuda.
// Clave = id del ítem en menu.ts. Si un módulo no está acá, se muestra solo el
// nombre + su ubicación. Texto funcional, sin nombres técnicos de base de datos.
export const DESCRIPCIONES: Record<string, string> = {
  // ── Empleados / Archivos ──
  'empleados-abm': 'Alta, baja y modificación de empleados; ficha completa.',
  'empleados-carnet': 'Impresión del carnet de autoelevadores.',
  'empleados-cc': 'Asignación del centro de costo de cada empleado.',
  'empleados-exportar': 'Exportar los datos de empleados a un archivo.',
  'empleados-importar': 'Exportar e importar datos de empleados.',
  'empleados-impo-os': 'Importar los costos de obras sociales.',
  'mis-permisos': 'Portal para encargados: solicitan permisos laborales para su personal a cargo (quedan pendientes hasta que RRHH los procesa) y ven el historial de lo que cargaron.',
  'invitados': 'Personas invitadas / no empleadas.',
  'empresas': 'Mantenimiento de las empresas del grupo.',
  'contratistas-arch': 'Empresas contratistas externas.',
  'lugares': 'Lugares o localidades de trabajo.',
  'convenios': 'Convenios laborales (SMATA, F. de Convenio, etc.).',
  'categorias': 'Categorías del personal por convenio.',
  'sectores': 'Sectores de trabajo (ubicaciones/clientes).',
  'subsectores': 'Subsectores dentro de cada sector.',
  'estado-civil': 'Tabla de estados civiles.',
  'tipo-doc': 'Tipos de documento (DNI, LC, etc.).',
  'haberes': 'Conceptos de haberes.',
  'descuentos': 'Conceptos de descuentos.',
  'asignaciones': 'Asignaciones familiares.',
  'valores': 'Valores y parámetros varios.',
  'carnet-cat': 'Categorías de carnet de conducir.',
  'ctas-bancarias': 'Cuentas bancarias.',
  'comedores': 'Comedores.',
  'bloqueos': 'Bloqueos varios del sistema.',

  // ── Adelantos ──
  'ade-agregar': 'Cargar un adelanto al personal.',
  'ade-consultar': 'Consultar adelantos cargados.',
  'ade-imprimir': 'Imprimir adelantos.',
  'ade-borrar': 'Eliminar adelantos.',
  'ade-listados': 'Listados de adelantos.',

  // ── Puestos / Tareas / Calificaciones ──
  'puestos-abm': 'Alta y edición de puestos de trabajo.',
  'tareas-def': 'Definición de tareas.',
  'tareas-puesto': 'Tareas asignadas a cada puesto.',
  'puestos-asignar': 'Asignar un puesto de trabajo a un empleado.',
  'departamentos': 'Departamentos de la empresa.',
  'frecuencias': 'Frecuencias (para tareas / controles).',
  'puestos-listados': 'Informes de puestos de trabajo.',
  'emp-puestos': 'Empleados por puesto de trabajo.',
  'hoja-eval': 'Impresión de la hoja de evaluación de desempeño.',
  'calif-emp': 'Calificación del empleado.',
  'calif-hoja': 'Impresión de la hoja de calificación.',
  'calif-consulta': 'Informes de calificaciones.',

  // ── Ropa / EPP ──
  'ropa-entrega': 'Entrega de ropa / EPP a un empleado.',
  'ropa-borrar': 'Borrar ropa entregada.',
  'ropa-ingreso': 'Ingreso de ropa al stock.',
  'ropa-transfer': 'Transferencia de ropa entre depósitos.',
  'ropa-inventario': 'Carga de inventario de ropa.',
  'ropa-stock': 'Consulta de stock de ropa.',
  'ropa-estadistica': 'Estadísticas de ropa.',
  'ropa-hist': 'Historial de entregas de EPP.',
  'emp-epp': 'Asignar el EPP utilizado por el empleado.',
  'ropa-depositos': 'Depósitos de ropa.',
  'ropa-marcas': 'Marcas de ropa.',
  'ropa-rubro': 'Rubros de ropa.',
  'talles': 'Talles.',
  'ropa-abm': 'Alta y edición de prendas.',

  // ── Capacitación ──
  'cap-jornadas': 'Jornadas de capacitación.',
  'cap-asignar': 'Asignar capacitación a empleados.',
  'cap-resultados': 'Resultados de la capacitación.',
  'cap-doc': 'Documentación asociada a la capacitación.',
  'areas-tematicas': 'Áreas temáticas de capacitación.',
  'disertantes': 'Disertantes.',
  'cap-informes': 'Informes de capacitación.',

  // ── Exámenes / Salud ──
  'exam-agregar': 'Cargar un examen médico.',
  'exam-med': 'Cargar exámenes médicos exclusivos.',
  'exam-modificar': 'Modificar exámenes médicos.',
  'exam-empleados': 'Control de salud por empleado.',
  'exam-listados': 'Listados de exámenes médicos.',
  'exam-proximos': 'Exámenes médicos próximos a vencer.',
  'exam-eliminar': 'Eliminar exámenes médicos.',
  'medicos': 'Médicos.',
  'examenes-tipo': 'Tipos de examen.',

  // ── Requerimientos / Entrevistas ──
  'req-abm': 'Requerimientos de personal.',
  'req-clientes': 'Requerimientos por cliente.',
  'req-informes': 'Informes de requerimientos.',
  'req-email': 'Emails de requerimientos enviados.',
  'ent-abm': 'Entrevistas.',
  'ent-grupo': 'Consulta general de entrevistas.',
  'ent-listados': 'Listado de entrevistados.',

  // ── ART / Siniestros ──
  'sin-agregar': 'Cargar un siniestro ART.',
  'sin-modificar': 'Modificar un siniestro ART.',
  'sin-consultar': 'Consultar siniestros ART.',
  'sin-eliminar': 'Eliminar siniestros ART.',
  'sin-impresion': 'Impresión de siniestros ART.',
  'sin-listados': 'Listados de siniestros ART.',
  'sin-seguimiento': 'Seguimiento de siniestros ART.',

  // ── Celulares ──
  'cel-asignar': 'Asignar un celular a un empleado.',
  'cel-devolver': 'Registrar la devolución de un celular.',
  'cel-informes': 'Informes de celulares.',
  'cel-equipos': 'Equipos celulares (inventario).',

  // ── Costos laborales ──
  'costos-fijos': 'Costos fijos laborales.',
  'costos-ind': 'Costo laboral individual.',
  'costos-grupal': 'Cálculo de costos laborales grupales.',
  'costos-informe': 'Informe general de costos laborales.',

  // ── Apercibimientos / Permisos ──
  'aper-crear': 'Cargar un apercibimiento.',
  'aper-consulta': 'Consultar apercibimientos.',
  'permisos': 'Permisos laborales.',

  // ── Compras / Vales ──
  'compras-cons': 'Consultar compras (base de Gestión).',
  'vales-borrados': 'Lista de vales borrados.',
  'vales-tesoreria': 'Ingresos de tesorería.',
  'vales-fondo': 'Consulta de fondo fijo.',
  'vales-cons': 'Lista de vales.',
  'vales-pend': 'Vales abiertos / pendientes.',

  // ── Contratistas externos / Obras ──
  'contra-nombres': 'Contratistas externos.',
  'contra-tipo': 'Tipos de contratista.',
  'exigencias': 'Exigencias legales a contratados.',
  'contra-emplea': 'Empleados de contratistas.',
  'contra-importar': 'Importar empleados de contratistas.',
  'contra-oblig': 'Presentación de obligaciones.',
  'contra-faltas': 'Contratistas con obligaciones faltantes.',
  'contra-accesos': 'Listado de accesos de contratistas.',
  'obras-listados': 'Listados de obras.',
  'obras-ingresar': 'Habilitados a ingresar a obra.',

  // ── Almuerzos / Novedades / Obras sociales ──
  'alm-editar': 'Almuerzos del personal.',
  'alm-listados': 'Listados de almuerzos.',
  'nov-editar': 'Planilla de novedades de sueldos.',
  'nov-exportar': 'Exportar novedades.',
  'os-abm': 'Obras sociales.',
  'os-importar': 'Importar pagos de obras sociales.',
  'os-aportes': 'Aportes y contribuciones de obras sociales.',

  // ── Horas extras / Liquidaciones ──
  'hs-diarias': 'Planilla de horas extras diarias.',
  'hs-empleado': 'Horas extras diarias de un empleado.',
  'planilla-hs': 'Planillas de horas extras.',
  'planilla-hs-edit': 'Editar planillas de horas extras.',
  'liq-consultar': 'Consulta de liquidaciones de sueldos.',
  'liq-borrar': 'Borrar liquidaciones.',
  'liq-listados': 'Listados de liquidaciones.',
  'liq-mejor': 'Mejor sueldo y promedio.',
  'liq-comparar': 'Comparación de netos.',
  'liq-informes': 'Informe general de liquidaciones (Excel).',
  'liq-netos': 'Exportar e importar sueldo neto.',
  'liq-pagos': 'Liquidaciones pagadas / consulta de pagos.',
  'liq-importar': 'Importar liquidación.',
  'liq-importar2': 'Importar liquidación (formato estudio 2).',
  'multas': 'Listado de multas.',
  'sueldos-tipos': 'Tipos de sueldos.',
  'sueldos-conceptos': 'Conceptos de sueldos.',
  'feriados': 'Definición de feriados.',

  // ── Bancos ──
  'bco-sf-exp': 'Exportar TXT Banco Santa Fe.',
  'bco-sf-con': 'Consultar haberes Banco Santa Fe.',
  'bco-san-exp': 'Exportar TXT Banco Santander Río.',
  'bco-san-con': 'Consultar haberes Banco Santander Río.',
  'bco-fra-exp': 'Exportar TXT Banco Francés (BBVA).',
  'bco-fra-con': 'Consultar haberes Banco Francés.',
  'bco-nac-exp': 'Exportar TXT Banco Nación.',
  'bco-nac-con': 'Consultar haberes Banco Nación.',
  'bco-var-exp': 'Exportar TXT bancos varios.',
  'bco-var-con': 'Consultar haberes bancos varios.',

  // ── Vacaciones ──
  'vac-agregar': 'Cargar el período de vacaciones de un empleado.',
  'vac-acciones': 'Modificar o eliminar vacaciones.',
  'vac-programadas': 'Vacaciones programadas.',
  'vac-planilla': 'Planilla general de vacaciones.',
  'vac-definicion': 'Definición de antigüedad para vacaciones.',
  'vac-informe': 'Informe general de vacaciones.',
  'vac-pendientes': 'Vacaciones pendientes.',

  // ── Reloj de personal ──
  'rel-capturas': 'Empleados registrados en el reloj de personal.',
  'rel-horas': 'Consulta de horas trabajadas.',
  'rel-parte': 'Parte diario de horas trabajadas.',
  'rel-secretaria': 'Planilla para Secretaría de Trabajo.',
  'rel-tardes': 'Llegadas tarde por período.',
  'rel-ajustes': 'Ajuste de horarios trabajados.',
  'rel-ajustes-borr': 'Ajustes manuales del reloj.',
  'rel-ajustes-per': 'Ajuste de capturas del reloj.',
  'rel-faltas': 'Ajuste de faltas diarias.',
  'rel-faltas-edit': 'Editar faltas diarias.',
  'rel-faltas-list': 'Listados de faltas diarias.',
  'rel-turnos': 'Asignar turno laboral al personal.',
  'rel-ubicacion': 'Ubicaciones del reloj.',
  'rel-grupos': 'Grupos de turnos laborales.',
  'rel-envios': 'Envío de partes diarios.',
  'licencias': 'Licencias laborales.',

  // ── Planillas ──
  'plan-viajantes': 'Planilla libro de viajantes (comisiones).',
  'plan-sueldos': 'Planilla de control de sueldos.',

  // ── Varios ──
  'alertas': 'Panel de alertas y avisos del sistema.',
  'agenda-telefonos': 'Agenda de teléfonos.',

  // ── Sistema / Seguridad ──
  'parametros': 'Parámetros del sistema.',
  'cambio-clave': 'Cambiar la clave de acceso.',
  'alta-accesos': 'Alta de usuarios y permisos de menú.',
  'roles': 'Roles / plantillas de permisos.',
  'usuarios-activos': 'Usuarios activos en el sistema.',
  'clonar-permisos': 'Clonar los permisos de un usuario a otro.',
  'log-errores': 'Log de errores SQL (solo administradores).',
}
