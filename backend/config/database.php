<?php

use Illuminate\Support\Str;
use Pdo\Mysql;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for database operations. This is
    | the connection which will be utilized unless another connection
    | is explicitly specified when you execute a query / statement.
    |
    */

    'default' => env('DB_CONNECTION', 'sqlite'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Below are all of the database connections defined for your application.
    | An example configuration is provided for each database system which
    | is supported by Laravel. You're free to add / remove connections.
    |
    */

    'connections' => [

        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DB_URL'),
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
            'busy_timeout' => null,
            'journal_mode' => null,
            'synchronous' => null,
            'transaction_mode' => 'DEFERRED',
        ],

        'mysql' => [
            'driver' => 'mysql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                Mysql::ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'mariadb' => [
            'driver' => 'mariadb',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                Mysql::ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => env('DB_SSLMODE', 'prefer'),
        ],

        'sqlsrv' => [
            'driver' => 'sqlsrv',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', '1433'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            // 'encrypt' => env('DB_ENCRYPT', 'yes'),
            // 'trust_server_certificate' => env('DB_TRUST_SERVER_CERTIFICATE', 'false'),
        ],

        // ── RRHH principal — SQL Server (WALTER-PC) ──────────────────────────
        'sqlsrv_rrhh' => [
            'driver'                  => 'sqlsrv',
            'host'                    => env('DB_HOST', 'WALTER-PC'),
            'port'                    => env('DB_PORT', '1433'),
            'database'                => env('DB_DATABASE', 'sqlRRHH'),
            'username'                => env('DB_USERNAME', ''),
            'password'                => env('DB_PASSWORD', ''),
            'charset'                 => 'utf8',
            'prefix'                  => '',
            'encrypt'                 => 'no',
            'trust_server_certificate'=> 'yes',
            'login_timeout'           => 5,
        ],

        // ── Documentos Digitales — SQL Server (WALTER-PC) ────────────────────
        'documentos' => [
            'driver'                  => 'sqlsrv',
            'host'                    => env('SQLSRV_HOST', 'WALTER-PC'),
            'port'                    => env('SQLSRV_PORT', '1433'),
            'database'                => env('SQLSRV_DATABASE', 'DOCUMENTOS_DIGITALES'),
            'username'                => env('SQLSRV_USERNAME', ''),
            'password'                => env('SQLSRV_PASSWORD', ''),
            'charset'                 => 'utf8',
            'prefix'                  => '',
            'encrypt'                 => 'no',
            'trust_server_certificate'=> 'yes',
            'login_timeout'           => 5,
        ],

        // ── Gestión (SILCAR) — SQL Server (WALTER-PC) — tab Tarjetas ──────────
        'gestion' => [
            'driver'                  => 'sqlsrv',
            'host'                    => env('GESTION_HOST', env('DB_HOST', 'WALTER-PC')),
            'port'                    => env('GESTION_PORT', env('DB_PORT', '1433')),
            'database'                => env('GESTION_DATABASE', 'sqlSILCAR'),
            'username'                => env('GESTION_USERNAME', env('DB_USERNAME', '')),
            'password'                => env('GESTION_PASSWORD', env('DB_PASSWORD', '')),
            'charset'                 => 'utf8',
            'prefix'                  => '',
            'encrypt'                 => 'no',
            'trust_server_certificate'=> 'yes',
            'login_timeout'           => 5,
        ],

        // ── WMS / Stock (LOGIST_UNIVERSAL) — SQL Server (WALTER-PC) ──────────
        // Solo lectura: stock, movimientos, recepción/despacho, averías (GMA/NMA).
        'wms' => [
            'driver'                  => 'sqlsrv',
            'host'                    => env('WMS_HOST', env('DB_HOST', 'WALTER-PC')),
            'port'                    => env('WMS_PORT', env('DB_PORT', '1433')),
            'database'                => env('WMS_DATABASE', 'LOGIST_UNIVERSAL'),
            'username'                => env('WMS_USERNAME', env('DB_USERNAME', '')),
            'password'                => env('WMS_PASSWORD', env('DB_PASSWORD', '')),
            'charset'                 => 'utf8',
            'prefix'                  => '',
            'encrypt'                 => 'no',
            'trust_server_certificate'=> 'yes',
            'login_timeout'           => 5,
        ],

        // ── RRHH de la OTRA empresa — SQL Server remoto ──────────────────────
        // Consulta General de Entrevistas: une los entrevistados de ambas empresas.
        // Cada servidor apunta a la base RRHH de la otra empresa (host/credenciales
        // por .env). Si RRHH_OTRA_HOST está vacío, la consulta muestra sólo la local.
        'rrhh_otra' => [
            'driver'                  => 'sqlsrv',
            'host'                    => env('RRHH_OTRA_HOST', ''),
            'port'                    => env('RRHH_OTRA_PORT', '1433'),
            'database'                => env('RRHH_OTRA_DATABASE', ''),
            'username'                => env('RRHH_OTRA_USERNAME', ''),
            'password'                => env('RRHH_OTRA_PASSWORD', ''),
            'charset'                 => 'utf8',
            'prefix'                  => '',
            'encrypt'                 => 'no',
            'trust_server_certificate'=> 'yes',
            'login_timeout'           => 5,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | This table keeps track of all the migrations that have already run for
    | your application. Using this information, we can determine which of
    | the migrations on disk haven't actually been run on the database.
    |
    */

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    |
    | Redis is an open source, fast, and advanced key-value store that also
    | provides a richer body of commands than a typical key-value system
    | such as Memcached. You may define your connection settings here.
    |
    */

    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug((string) env('APP_NAME', 'laravel')).'-database-'),
            'persistent' => env('REDIS_PERSISTENT', false),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

    ],

];
