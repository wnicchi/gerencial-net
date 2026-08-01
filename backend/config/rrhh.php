<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Identificador del "sistema" en DOCUMENTOS_DIGITALES (BIBLIOTECA_DIGITAL)
    |--------------------------------------------------------------------------
    |
    | Los archivos binarios (fotos, documentación, licencias) se guardan/buscan
    | en la base DOCUMENTOS_DIGITALES con un campo "nombre_del_sistema". Ese
    | valor cambia según el servidor donde corre la app:
    |
    |   - Servidor original : 'RRHH'
    |   - Servidor 2026     : 'RRHHLOG'
    |
    | Si no se define DOCS_SISTEMA en el .env, se deriva del NOMBRE DE LA BASE
    | (DB_DATABASE), igual que 'empresa': si contiene "log" (sqlRRHHlog) → 'RRHHLOG';
    | cualquier otra (sqlRRHH) → 'RRHH'. Así sale automático por servidor sin tener
    | que setear la variable. DOCS_SISTEMA (si se define) tiene prioridad.
    |
    */

    'docs_sistema' => env('DOCS_SISTEMA', str_contains(strtolower((string) env('DB_DATABASE', 'sqlRRHH')), 'log') ? 'RRHHLOG' : 'RRHH'),

    /*
    |--------------------------------------------------------------------------
    | Empresa (para logo/branding)
    |--------------------------------------------------------------------------
    |
    | El mismo build del frontend se despliega en las dos empresas; el logo que
    | se muestra (sidebar + PDFs) se elige en runtime según esta clave del .env:
    |
    |   - 'silcar' : SILCAR Equipos Industriales (Autoelevadores) — logo verde
    |   - 'logist' : SILCAR Logística y Representaciones — logo azul
    |
    | Se expone al frontend en el login y en /auth/me.
    |
    | Si no se define EMPRESA en el .env, se deriva del NOMBRE DE LA BASE de datos
    | (DB_DATABASE): si contiene "log" (ej. sqlRRHHlog / sqlLOGIST) → 'logist';
    | cualquier otro (ej. sqlRRHH) → 'silcar'. Así el logo/branding sale automático
    | según la base que use cada servidor, sin agregar variables. EMPRESA (si se
    | define) tiene prioridad.
    |
    */

    'empresa' => env('EMPRESA', str_contains(strtolower((string) env('DB_DATABASE', 'sqlRRHH')), 'log') ? 'logist' : 'silcar'),

];
