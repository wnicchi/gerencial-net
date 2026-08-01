<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Helper de empleado — utilidades transversales sobre la tabla `personal`.
 *
 * Se usa para bloquear la CARGA de registros (faltas, vales, adelantos, siniestros,
 * exámenes, apercibimientos, viajes, vacaciones, ajustes de reloj…) a empleados dados
 * de baja. El módulo maestro de Empleados NO usa esto (permite ver/editar bajas).
 */
class Empleado
{
    /** ¿El empleado está activo? (PER_AOP = 'A' = Alta en FoxPro). */
    public static function activo(int $cod): bool
    {
        $aop = DB::table('personal')->where('PER_COD', $cod)->value('PER_AOP');
        return trim((string) $aop) === 'A';
    }
}
