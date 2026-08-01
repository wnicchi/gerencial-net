<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * StockRopa — helper de stock de ropa/EPP (tabla ropa_stock).
 *
 * Centraliza el "upsert" del stock por (código, marca, talle, depósito) sin
 * tocar la columna computada `unico`. Réplica de la lógica FoxPro que inserta
 * el registro si no existe o ajusta SEPP_STOCK si ya existe.
 */
class StockRopa
{
    /** Suma (delta) al stock; si no existe el registro lo crea con stock = delta. */
    public static function sumar(int $cod, string $des, int $mar, string $mad, int $tal, string $tad, int $dep, string $ded, int $delta): void
    {
        $existe = self::existe($cod, $mar, $tal, $dep);
        if ($existe) {
            DB::table('ropa_stock')->where('SEPP_COD', $cod)->where('SEPP_MAR', $mar)
                ->where('SEPP_TAL', $tal)->where('SEPP_DEP', $dep)->increment('SEPP_STOCK', $delta);
        } else {
            self::crear($cod, $des, $mar, $mad, $tal, $tad, $dep, $ded, $delta);
        }
    }

    /** Fija el stock a un valor exacto; si no existe lo crea con ese valor. */
    public static function fijar(int $cod, string $des, int $mar, string $mad, int $tal, string $tad, int $dep, string $ded, int $valor): void
    {
        if (self::existe($cod, $mar, $tal, $dep)) {
            DB::table('ropa_stock')->where('SEPP_COD', $cod)->where('SEPP_MAR', $mar)
                ->where('SEPP_TAL', $tal)->where('SEPP_DEP', $dep)->update(['SEPP_STOCK' => $valor]);
        } else {
            self::crear($cod, $des, $mar, $mad, $tal, $tad, $dep, $ded, $valor);
        }
    }

    private static function existe(int $cod, int $mar, int $tal, int $dep): bool
    {
        return DB::table('ropa_stock')->where('SEPP_COD', $cod)->where('SEPP_MAR', $mar)
            ->where('SEPP_TAL', $tal)->where('SEPP_DEP', $dep)->exists();
    }

    private static function crear(int $cod, string $des, int $mar, string $mad, int $tal, string $tad, int $dep, string $ded, int $stock): void
    {
        DB::table('ropa_stock')->insert([
            'SEPP_COD' => $cod, 'SEPP_DES' => mb_substr($des, 0, 50), 'SEPP_MAR' => $mar,
            'SEPP_MAD' => mb_substr($mad, 0, 30), 'SEPP_TAL' => $tal, 'SEPP_TAD' => mb_substr($tad, 0, 20),
            'SEPP_DEP' => $dep, 'SEPP_DED' => mb_substr($ded, 0, 30), 'SEPP_STOCK' => $stock,
        ]);
    }
}
