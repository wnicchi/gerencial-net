<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * SincronizarLegajosGestion — Completa usuarios.LEGAJO desde el sistema de gestión.
 *
 * En el sistema de gestión (conexión 'gestion' = sqlSILCAR/sqlLOGIST) la tabla
 * USUARIOS ya tiene, por cada usuario, el LEGAJO del empleado asociado. Este
 * comando matchea por login (DATO1, comparado en MAYÚSCULAS y sin espacios) con
 * la tabla usuarios del sistema RRHH y copia SOLO el campo LEGAJO. No agrega
 * usuarios ni toca ningún otro campo.
 *
 * Uso:
 *   php artisan usuarios:sincronizar-legajos            (INFORME, no escribe nada)
 *   php artisan usuarios:sincronizar-legajos --aplicar  (escribe los LEGAJO)
 */
class SincronizarLegajosGestion extends Command
{
    protected $signature = 'usuarios:sincronizar-legajos {--aplicar : Escribe los cambios (sin esto solo muestra un informe)}';

    protected $description = 'Copia usuarios.LEGAJO desde el sistema de gestión (match por login DATO1 en MAYÚSCULAS).';

    public function handle(): int
    {
        $aplicar = (bool) $this->option('aplicar');

        // Login (MAYÚSCULAS, sin espacios) => legajo, tomado del sistema de gestión.
        $porLogin = [];
        foreach (DB::connection('gestion')->table('USUARIOS')->get(['DATO1', 'LEGAJO']) as $g) {
            $login = strtoupper(trim((string) $g->DATO1));
            $leg   = (int) $g->LEGAJO;
            if ($login !== '' && $leg > 0) {
                $porLogin[$login] = $leg;
            }
        }
        $this->info('Usuarios con legajo en gestión: ' . count($porLogin));

        $filas = [];
        $cambios = 0;
        $sinMatch = 0;

        foreach (DB::table('usuarios')->get(['CODIGO', 'DATO1', 'LEGAJO']) as $u) {
            $login = strtoupper(trim((string) $u->DATO1));
            if ($login === '' || ! isset($porLogin[$login])) {
                $sinMatch++;
                continue;
            }
            $nuevo   = $porLogin[$login];
            $actual  = (int) $u->LEGAJO;
            if ($nuevo === $actual) {
                continue;
            }
            $filas[] = [trim((string) $u->DATO1), $actual ?: '-', $nuevo];
            $cambios++;

            if ($aplicar) {
                DB::table('usuarios')->where('CODIGO', $u->CODIGO)->update(['LEGAJO' => $nuevo]);
            }
        }

        if ($filas) {
            $this->table(['Login', 'Legajo actual', 'Legajo nuevo'], $filas);
        }

        $this->line('');
        $this->info("Usuarios RRHH sin match en gestión: {$sinMatch}");
        if ($aplicar) {
            $this->info("LEGAJO actualizados: {$cambios}");
        } else {
            $this->warn("INFORME (no se escribió nada). Cambios pendientes: {$cambios}. Ejecutá con --aplicar para grabarlos.");
        }

        return self::SUCCESS;
    }
}
