<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ============================================================
 * BibliotecaDigitalService
 * ============================================================
 * Equivalente PHP de las tres funciones de la librería DIGITRACK
 * (digitrack.vcx) que operan sobre la tabla BIBLIOTECA_DIGITAL
 * en la base SQL Server DOCUMENTOS_DIGITALES.
 *
 * Funciones originales replicadas:
 *   · Archivo_Digital_Guardar    → archivoDigitalGuardar()
 *   · Archivo_Digital_Recuperar → archivoDigitalRecuperar() / archivoDigitalRecuperarDataUrl()
 *   · Archivo_Digital_Eliminar  → archivoDigitalEliminar()
 *   · Archivo_Digital_Visualizar→ archivoDigitalVisualizar()
 *
 * Uso típico:
 *   $svc = new BibliotecaDigitalService();
 *
 *   // Guardar foto de empleado
 *   $svc->archivoDigitalGuardar('RRHH', 'PERSONAL_FOTO', 'E184.JPG', 'JPG', $binarioRaw, $usuario);
 *
 *   // Recuperar como data URL para el frontend
 *   $url = $svc->archivoDigitalRecuperarDataUrl('RRHH', 'PERSONAL_FOTO', 'E184.JPG');
 *
 *   // Recuperar bytes crudos (para servir como descarga)
 *   [$bytes, $ext] = $svc->archivoDigitalRecuperar('RRHH', 'PERSONAL_FOTO', 'E184.JPG');
 *
 *   // Eliminar
 *   $svc->archivoDigitalEliminar('RRHH', 'PERSONAL_FOTO', 'E184.JPG');
 *
 *   // Visualizar en browser (PDF inline, imagen inline, Excel descarga)
 *   return $svc->archivoDigitalVisualizar('RRHH', 'LICENCIAS_PDF', 'L123.PDF');
 *
 * Nota sobre encoding:
 *   El driver ODBC con charset UTF-8 convierte CP1252↔UTF-8 al leer/escribir
 *   columnas de texto. Este servicio maneja esa conversión automáticamente.
 *
 * @package  App\Services
 * @author   Sistema RRHH.NET
 * @version  1.0.0
 * @since    2026-06-10
 * ============================================================
 */
class BibliotecaDigitalService
{
    /** Conexión Laravel al SQL Server DOCUMENTOS_DIGITALES */
    private const CONN = 'documentos';

    /** Tabla de archivos digitales */
    private const TABLA = 'BIBLIOTECA_DIGITAL';

    // ──────────────────────────────────────────────────────────
    // GUARDAR
    // Equivalente: Archivo_Digital_Guardar(sistema, proceso, id, ruta, extension)
    // Estrategia DIGITRACK: SET ANSI_WARNINGS OFF → DELETE → INSERT
    // ──────────────────────────────────────────────────────────

    /**
     * Guarda o reemplaza un archivo en BIBLIOTECA_DIGITAL.
     *
     * @param  string  $sistema       Nombre del sistema  (ej: 'RRHH')
     * @param  string  $proceso       Nombre del proceso  (ej: 'PERSONAL_FOTO')
     * @param  string  $identificacion Clave del archivo  (ej: 'E184.JPG')
     * @param  string  $extension     Extensión en mayúsculas (ej: 'JPG', 'PDF', 'XLSX')
     * @param  string  $binarioRaw    Contenido del archivo como bytes crudos
     * @param  string  $usuario       Nombre de usuario que guarda
     * @param  string|null $terminal  Identificador de terminal (hostname o IP)
     * @throws \RuntimeException si falla la operación
     */
    public function archivoDigitalGuardar(
        string $sistema,
        string $proceso,
        string $identificacion,
        string $extension,
        string $binarioRaw,
        string $usuario = 'RRHH.NET',
        ?string $terminal = null
    ): void {
        $db       = DB::connection(self::CONN);
        $terminal = $terminal ?? strtoupper(gethostname() ?: '');
        $tamanio  = strlen($binarioRaw);

        // El driver ODBC con charset UTF-8 convierte CP1252→UTF-8 al leer.
        // Para guardar correctamente hacemos la inversa: codificamos en UTF-8
        // de forma que al leerlo de vuelta y convertir CP1252←UTF-8 obtengamos
        // los bytes originales.
        $binarioParaDB = mb_convert_encoding($binarioRaw, 'UTF-8', 'CP1252');

        // SET ANSI_WARNINGS OFF — evita error de truncado en columnas text grandes
        // (FoxPro: cmd = SQLExec(_xConexion_digitales,"set ANSI_WARNINGS OFF"))
        $db->statement('SET ANSI_WARNINGS OFF');

        // DELETE existente — DIGITRACK siempre borra antes de insertar (nunca UPDATE)
        $this->whereClave($db->table(self::TABLA), $sistema, $proceso, $identificacion)
            ->delete();

        // INSERT nuevo registro
        $db->table(self::TABLA)->insert([
            'nombre_del_sistema'          => $sistema,
            'nombre_proceso'              => $proceso,
            'Identificacion_del_Archivo'  => $identificacion,
            'extension'                   => strtoupper($extension),
            'tamanio_original'            => $tamanio,
            'tamanio_codificado'          => 0,   // DIGITRACK: ?0 (sin codificación base64)
            'archivo_binario'             => $binarioParaDB,
            'actualizado'                 => now(),
            'usuario'                     => strtoupper($usuario),
            'terminal'                    => strtoupper($terminal),
        ]);
    }

    // ──────────────────────────────────────────────────────────
    // RECUPERAR
    // Equivalente: Archivo_Digital_Recuperar(sistema, proceso, id, rutaDestino)
    // ──────────────────────────────────────────────────────────

    /**
     * Recupera el contenido binario crudo de un archivo.
     *
     * @return array{0:string,1:string}|null  [$binarioRaw, $extension] o null si no existe
     */
    public function archivoDigitalRecuperar(
        string $sistema,
        string $proceso,
        string $identificacion
    ): ?array {
        $row = $this->whereClave(
            DB::connection(self::CONN)->table(self::TABLA),
            $sistema, $proceso, $identificacion
        )->first(['archivo_binario', 'extension']);

        if (!$row || empty($row->archivo_binario)) {
            return null;
        }

        $binario = is_resource($row->archivo_binario)
            ? stream_get_contents($row->archivo_binario)
            : $row->archivo_binario;

        // Revertir la conversión UTF-8 que aplica el driver ODBC al leer (CP1252←UTF-8)
        $binario = mb_convert_encoding($binario, 'CP1252', 'UTF-8');

        return [$binario, strtolower(trim($row->extension ?? 'bin'))];
    }

    /**
     * Recupera el archivo como data URL base64 lista para el frontend.
     * Equivalente visual a la imagen que FoxPro asigna a .Picture
     *
     * @return string|null  "data:image/jpeg;base64,..." o null si no existe
     */
    public function archivoDigitalRecuperarDataUrl(
        string $sistema,
        string $proceso,
        string $identificacion
    ): ?string {
        $resultado = $this->archivoDigitalRecuperar($sistema, $proceso, $identificacion);
        if ($resultado === null) return null;

        [$binario, $ext] = $resultado;

        $mime = $this->extToMime($ext);

        return 'data:' . $mime . ';base64,' . base64_encode($binario);
    }

    // ──────────────────────────────────────────────────────────
    // ELIMINAR
    // Equivalente: Archivo_Digital_Eliminar(sistema, proceso, id)
    // ──────────────────────────────────────────────────────────

    /**
     * Elimina un archivo de BIBLIOTECA_DIGITAL.
     *
     * @return int  Cantidad de registros eliminados (0 si no existía)
     */
    public function archivoDigitalEliminar(
        string $sistema,
        string $proceso,
        string $identificacion
    ): int {
        return $this->whereClave(
            DB::connection(self::CONN)->table(self::TABLA),
            $sistema, $proceso, $identificacion
        )->delete();
    }

    // ──────────────────────────────────────────────────────────
    // VISUALIZAR
    // Equivalente: Archivo_Digital_Visualizar(sistema, proceso, id)
    // FoxPro: recupera → vuelca en carpeta temp del disco → ShellExecute (Windows lo abre)
    // Web:    recupera → devuelve Response con Content-Type correcto → browser lo abre
    //
    // TODO: implementar cuando se trabaje el módulo de Documentación.
    //
    // Uso previsto en el controller:
    //   public function visualizar(...) {
    //       $svc = new BibliotecaDigitalService();
    //       return $svc->visualizar('RRHH', 'LICENCIAS_PDF', 'L123.PDF');
    //   }
    // ──────────────────────────────────────────────────────────

    /**
     * Devuelve un Response HTTP con el archivo para que el browser lo abra directamente.
     * PDF → visor integrado del browser  |  imagen → mostrada inline
     * Excel/Word/ZIP → descarga automática
     *
     * Equivalente FoxPro: Archivo_Digital_Visualizar() que hace ShellExecute sobre el temp file.
     *
     * @return \Illuminate\Http\Response|null  null si el archivo no existe
     */
    public function archivoDigitalVisualizar(
        string $sistema,
        string $proceso,
        string $identificacion
    ): ?\Illuminate\Http\Response {
        $resultado = $this->archivoDigitalRecuperar($sistema, $proceso, $identificacion);
        if ($resultado === null) return null;

        [$binario, $ext] = $resultado;
        $mime = $this->extToMime($ext);

        // inline: browser intenta mostrar (PDF, imágenes)
        // attachment: browser descarga (Excel, Word, ZIP, etc.)
        $inlineMimes = ['image/jpeg','image/png','image/gif','image/webp','application/pdf'];
        $disposition = in_array($mime, $inlineMimes) ? 'inline' : 'attachment';

        return response($binario, 200)
            ->header('Content-Type', $mime)
            ->header('Content-Disposition', $disposition . '; filename="' . $identificacion . '"')
            ->header('Content-Length', strlen($binario));
    }

    // ──────────────────────────────────────────────────────────
    // HELPERS
    // ──────────────────────────────────────────────────────────

    /**
     * Aplica los tres filtros de clave (sistema / proceso / identificacion)
     * con UPPER+LTRIM+RTRIM, igual que DIGITRACK.
     */
    private function whereClave(
        \Illuminate\Database\Query\Builder $query,
        string $sistema,
        string $proceso,
        string $identificacion
    ): \Illuminate\Database\Query\Builder {
        return $query
            ->whereRaw("UPPER(LTRIM(RTRIM(nombre_del_sistema))) = ?",   [strtoupper($sistema)])
            ->whereRaw("UPPER(LTRIM(RTRIM(nombre_proceso)))     = ?",   [strtoupper($proceso)])
            ->whereRaw("UPPER(LTRIM(RTRIM(Identificacion_del_Archivo))) = ?", [strtoupper($identificacion)]);
    }

    /**
     * Mapea extensión de archivo a MIME type.
     */
    public function extToMime(string $ext): string
    {
        return match(strtolower(trim($ext))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png'         => 'image/png',
            'gif'         => 'image/gif',
            'webp'        => 'image/webp',
            'pdf'         => 'application/pdf',
            'xlsx'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'xls'         => 'application/vnd.ms-excel',
            'docx'        => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'doc'         => 'application/msword',
            'zip'         => 'application/zip',
            'txt'         => 'text/plain',
            default       => 'application/octet-stream',
        };
    }
}
