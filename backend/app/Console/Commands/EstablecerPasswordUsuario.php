<?php

namespace App\Console\Commands;

use App\Models\Usuario;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * EstablecerPasswordUsuario — Rescate de acceso desde la consola.
 *
 * Establece la contraseña de un usuario directamente desde el servidor, sin
 * depender de email ni de otro administrador. Pensado para el caso en que el
 * propio administrador olvida su contraseña: quien tenga acceso al servidor
 * (RDP) puede recuperar el acceso.
 *
 * Uso:
 *   php artisan usuario:password WNICCHI "NuevaClave123!"
 */
class EstablecerPasswordUsuario extends Command
{
    protected $signature = 'usuario:password {login : Login del usuario (DATO1)} {password : Nueva contraseña (mín. 6)}';

    protected $description = 'Establece la contraseña de un usuario desde la consola (rescate, ej. si el administrador la olvidó).';

    public function handle(): int
    {
        $login = strtoupper(trim((string) $this->argument('login')));
        $pass  = (string) $this->argument('password');

        if (mb_strlen($pass) < 6) {
            $this->error('La contraseña debe tener al menos 6 caracteres.');
            return self::FAILURE;
        }

        $u = Usuario::whereRaw('UPPER(LTRIM(RTRIM(DATO1))) = ?', [$login])->first();
        if (!$u) {
            $this->error("No existe un usuario con login '{$login}'.");
            return self::FAILURE;
        }

        $u->update([
            'password'      => Hash::make($pass),
            'primer_acceso' => 0,
            'ESTADO'        => 1,
        ]);
        $u->tokens()->delete();

        $this->info("Contraseña de {$u->NOMBRE} ({$u->DATO1}) actualizada. Ya puede iniciar sesión.");
        return self::SUCCESS;
    }
}
