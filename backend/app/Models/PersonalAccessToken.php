<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

/**
 * GERENCIAL.NET: los tokens Sanctum viven en la base del sistema de GESTIÓN
 * (sqlLOGIST, conexión 'gestion'), junto a los usuarios contra los que se
 * autentica. La conexión por defecto (sqlRRHHlog) queda libre para los datos
 * del tablero (RRHH). Se registra en AppServiceProvider::boot con
 * Sanctum::usePersonalAccessTokenModel().
 */
class PersonalAccessToken extends SanctumPersonalAccessToken
{
    protected $connection = 'gestion';
}
