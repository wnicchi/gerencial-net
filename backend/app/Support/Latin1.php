<?php

namespace App\Support;

/**
 * Latin1 — helpers para los archivos TXT de los bancos (ancho fijo por byte).
 *
 * Los bancos leen los archivos de acreditación por posición de BYTE y esperan
 * codificación Windows-1252 (Latin-1), como los generaba FoxPro. Un acento en
 * UTF-8 ocupa 2 bytes: corre todos los campos siguientes y el banco rechaza el
 * lote. Acá 'í' vuelve a ser 1 byte (0xED).
 */
class Latin1
{
    /** Convierte un texto UTF-8 a Windows-1252 (bytes de 1 por carácter). */
    public static function win(string $valor): string
    {
        $v = function_exists('iconv') ? @iconv('UTF-8', 'Windows-1252//TRANSLIT', $valor) : false;
        if ($v === false) $v = @mb_convert_encoding($valor, 'Windows-1252', 'UTF-8');
        return $v === false ? $valor : $v;
    }

    /**
     * Campo de texto a la izquierda en Windows-1252, completado con espacios a la
     * derecha y recortado a $n BYTES (la conversión va ANTES del recorte para que
     * el ancho coincida con el archivo de FoxPro).
     */
    public static function campo(string $valor, int $n): string
    {
        return substr(self::win($valor) . str_repeat(' ', $n), 0, $n);
    }
}
