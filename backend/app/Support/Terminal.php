<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Terminal — nombre de la terminal cliente para auditoría.
 *
 * Orden de resolución: header X-Terminal (si la terminal lo declara),
 * DNS inverso de la IP (solo si hay TERMINAL_DNS configurado), o la IP.
 *
 * NUNCA usa gethostbyaddr(): en Windows, para una IP sin registro PTR ese
 * lookup cae en LLMNR/NetBIOS y tarda 30-35 segundos SIN timeout posible;
 * en producción colgó el guardado de Horas Extras Diarias ("Maximum
 * execution time of 30 seconds exceeded" en RegistroActividad, que audita
 * cada escritura). Acá la consulta PTR se hace por UDP directo al DNS de
 * TERMINAL_DNS con timeout de 0.5 s, y el resultado se cachea 1 día.
 */
class Terminal
{
    /** @var array<string, string> resultado por IP dentro del mismo request */
    private static array $porIp = [];

    public static function nombre(?Request $request): string
    {
        if (!$request) return '';
        $declarado = trim((string) $request->header('X-Terminal', ''));
        if ($declarado !== '') return strtoupper($declarado);

        $ip = (string) $request->ip();
        if ($ip === '') return '';
        if (isset(self::$porIp[$ip])) return self::$porIp[$ip];

        $host = '';
        $dns = trim((string) config('app.terminal_dns', ''));
        if ($dns !== '' && preg_match('/^\d{1,3}(\.\d{1,3}){3}$/', $ip)) {
            try {
                $host = cache()->remember('terminal_host_' . $ip, 86400, fn () => self::ptr($ip, $dns));
            } catch (\Throwable) {
                $host = '';   // sin cache disponible: no insistir con el DNS
            }
        }

        $nombre = ($host !== '' && $host !== $ip) ? strtoupper(explode('.', $host)[0]) : strtoupper($ip);
        return self::$porIp[$ip] = $nombre;
    }

    /** Consulta PTR por UDP con timeout corto. Devuelve el hostname o ''. */
    private static function ptr(string $ip, string $dns, float $timeout = 0.5): string
    {
        try {
            $rev = implode('.', array_reverse(explode('.', $ip))) . '.in-addr.arpa';
            $id = random_int(0, 0xffff);
            $pkt = pack('n6', $id, 0x0100, 1, 0, 0, 0);
            foreach (explode('.', $rev) as $lbl) $pkt .= chr(strlen($lbl)) . $lbl;
            $pkt .= "\x00" . pack('n2', 12, 1);   // QTYPE=PTR, QCLASS=IN

            $sock = @stream_socket_client("udp://$dns:53", $errNo, $errStr, $timeout);
            if (!$sock) return '';
            stream_set_timeout($sock, 0, (int) ($timeout * 1_000_000));
            fwrite($sock, $pkt);
            $resp = fread($sock, 512);
            fclose($sock);

            if (!is_string($resp) || strlen($resp) < 12) return '';
            if (unpack('n', substr($resp, 0, 2))[1] !== $id) return '';
            if (unpack('n', substr($resp, 6, 2))[1] < 1) return '';   // ANCOUNT

            // Saltear la pregunta (labels hasta el 0 + QTYPE/QCLASS).
            $o = 12;
            while ($o < strlen($resp) && ord($resp[$o]) !== 0) $o += ord($resp[$o]) + 1;
            $o += 5;
            // Primera respuesta: nombre + tipo(2) clase(2) ttl(4) rdlength(2) + rdata.
            [, $o] = self::leerNombre($resp, $o);
            if ($o + 10 > strlen($resp)) return '';
            $tipo = unpack('n', substr($resp, $o, 2))[1];
            $o += 10;
            if ($tipo !== 12) return '';
            [$nombre] = self::leerNombre($resp, $o);
            return $nombre;
        } catch (\Throwable) {
            return '';
        }
    }

    /** Lee un nombre DNS (con punteros de compresión). Devuelve [nombre, offsetSiguiente]. */
    private static function leerNombre(string $d, int $o): array
    {
        $partes = []; $saltos = 0; $fin = null;
        while ($o < strlen($d) && $saltos < 10) {
            $len = ord($d[$o]);
            if ($len === 0) { $o++; break; }
            if (($len & 0xC0) === 0xC0) {                       // puntero
                if ($fin === null) $fin = $o + 2;
                $o = (($len & 0x3F) << 8) | ord($d[$o + 1]);
                $saltos++;
                continue;
            }
            $partes[] = substr($d, $o + 1, $len);
            $o += $len + 1;
        }
        return [implode('.', $partes), $fin ?? $o];
    }
}
