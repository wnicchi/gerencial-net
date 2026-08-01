<?php

namespace App\Database;

use App\Support\RegistroError;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Database\SqlServerConnection;

/**
 * Conexión SQL Server que registra en `log_errores_sql` CUALQUIER error de consulta,
 * en la capa de conexión (antes de que un try/catch de un controller lo pueda tapar).
 *
 * Se engancha con Connection::resolverFor('sqlsrv', ...) en AppServiceProvider, así cubre
 * las tres conexiones del proyecto (sqlsrv_rrhh / documentos / gestion) de una sola vez.
 * El error se registra y se RE-LANZA sin alterar: el flujo normal de errores sigue igual.
 */
class LoggingSqlServerConnection extends SqlServerConnection
{
    protected function runQueryCallback($query, $bindings, Closure $callback)
    {
        try {
            return parent::runQueryCallback($query, $bindings, $callback);
        } catch (QueryException $e) {
            RegistroError::registrar($e);   // deriva módulo de la ruta actual
            throw $e;                       // se re-lanza tal cual: nada cambia aguas arriba
        }
    }
}
