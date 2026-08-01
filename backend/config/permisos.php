<?php

/**
 * Autorización por módulo — mapa prefijo-de-ruta (API) → clave de menú.
 *
 * Lo usa el middleware App\Http\Middleware\VerificarPermisoModulo para exigir, del
 * lado del servidor, el mismo permiso que el frontend ya usa para mostrar/ocultar el
 * ítem de menú (el campo `id` en frontend/src/config/menu.ts).
 *
 * Reglas:
 *   - La clave debe ser EXACTAMENTE el `id` del ítem de menú correspondiente.
 *   - El prefijo es la ruta de API SIN el `api/` inicial (ej. 'requerimientos-clientes').
 *   - Matchea por prefijo más largo; las rutas no listadas quedan permitidas
 *     (fail-open) → poblar este mapa es seguro e incremental.
 *
 * ⚠️ Al agregar un módulo: verificar que el `id` de menu.ts sea el correcto y que el
 * prefijo no colisione con otro módulo (ej. 'examenes' sirve a varios ítems de menú
 * con claves distintas: NO mapear el prefijo genérico, sólo prefijos inequívocos).
 *
 * El valor puede ser una clave (string) o un array de claves (any-of): se permite
 * si el usuario tiene AL MENOS UNA. El array se usa para prefijos compartidos por
 * varios ítems de menú (ej. 'liquidaciones' la usan consultar y borrar; cada banco
 * comparte prefijo entre su Exportar y su Consultar).
 */

return [
    'rutas' => [
        // ── Requerimientos ────────────────────────────────────
        'requerimientos'          => 'req-abm',
        'requerimientos-clientes' => 'req-clientes',
        'requerimientos-informes' => 'req-informes',
        'requerimientos-enviados' => 'req-email',

        // ── Permisos Laborales ────────────────────────────────
        'permisos-laborales'      => 'permisos',

        // ── Control de Salud (sólo prefijos inequívocos) ──────
        'medicos'                 => 'medicos',
        'examenes-tipo'           => 'examenes-tipo',

        // ── Sueldos / Liquidaciones (prefijos 1:1 verificados) ─
        'control-sueldos'         => 'plan-sueldos',    // Control de Sueldos
        'comparativa-liquidaciones' => 'liq-comparar',  // Comparativa Liq. vs Neto
        'sueldos-listados'        => 'liq-listados',     // Listados de Liquidaciones
        'sueldos-netos'           => 'liq-netos',        // Exportar/Importar Sueldo Neto
        'sueldos-pagos'           => 'liq-pagos',        // Comparar con Pagos Bancarios
        'sueldos-tipos'           => 'sueldos-tipos',    // Tipos de Sueldos
        'sueldos-conceptos'       => 'sueldos-conceptos',// Conceptos de Sueldos
        'multas-listados'         => 'multas',           // Multas — Listado de Control

        // Prefijos compartidos (any-of): la sub-ruta la usan varios ítems de menú.
        'liquidaciones'           => ['liq-consultar', 'liq-borrar'], // consultar/tipos + borrar
        'sueldos-importar'        => ['liq-importar', 'liq-importar2'], // ambos importadores

        // ── Exportar TXT Bancos (cada banco: Exportar + Consultar) ─
        'bco-santa-fe'            => ['bco-sf-exp', 'bco-sf-con'],
        'bco-santander-rio'       => ['bco-san-exp', 'bco-san-con'],
        'bco-frances'             => ['bco-fra-exp', 'bco-fra-con'],
        'bco-nacion'              => ['bco-nac-exp', 'bco-nac-con'],
        'bco-varios'              => ['bco-var-exp', 'bco-var-con'],
    ],
];
