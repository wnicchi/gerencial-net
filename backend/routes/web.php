<?php

use Illuminate\Support\Facades\Route;

/*
 | La aplicación es una SPA (Vue) compilada dentro de public/.
 | Cualquier ruta que NO empiece con "api" devuelve el index.html del SPA,
 | para que el router de Vue maneje la navegación (deep links como /empleados).
 | Las rutas de la API viven en routes/api.php (prefijo /api).
 */
Route::get('/{any?}', function () {
    // El index.html se sirve SIN caché: así, después de cada deploy, el navegador
    // siempre trae la última versión (que referencia los assets nuevos con hash) y
    // no queda "pegado" a una versión vieja. Los assets sí se cachean (web.config).
    return response()->file(public_path('index.html'), [
        'Cache-Control' => 'no-cache, no-store, must-revalidate',
        'Pragma'        => 'no-cache',
        'Expires'       => '0',
    ]);
})->where('any', '^(?!api).*$');
