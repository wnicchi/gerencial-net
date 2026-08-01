<?php

/**
 * ============================================================
 * Personal.php — Modelo Eloquent de Empleados
 * ============================================================
 * Representa la tabla `personal`, que es la tabla principal
 * de empleados del Sistema RRHH.NET.
 *
 * Origen: migrada desde Visual FoxPro + SQL Server.
 * Los nombres de columnas conservan el prefijo PER_ del sistema
 * original para mantener compatibilidad con el resto de las
 * tablas relacionadas (vacaciones, liquidaciones, reloj, etc.).
 *
 * Campos clave:
 *   PER_COD  → Código único del empleado (PK, sin auto_increment)
 *   PER_NOM  → Nombre completo
 *   PER_LEG  → Número de legajo
 *   PER_NDO  → Número de documento
 *   PER_CUI  → CUIL
 *   ACTIVO   → 1 = activo, 0 = dado de baja
 *   PER_ING  → Fecha de ingreso
 *   PER_BAJ  → Fecha de baja (1900-01-01 = sin baja)
 *
 * Relaciones con otras tablas:
 *   PER_EMP → empresas.EMP_COD
 *   PER_SEC → sector.SEC_COD
 *   PER_CAT → categori.CAT_COD
 *   PER_CON → convenio.CON_COD
 *   PER_ECI → estadocivil.ECI_COD
 *
 * @package  App\Models
 * @author   Sistema RRHH.NET
 * @version  1.0.0
 * @since    2026-06-08
 * ============================================================
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Personal extends Model
{
    // ══════════════════════════════════════════════════════════
    // EVENTOS DEL MODELO
    // ══════════════════════════════════════════════════════════

    /**
     * Registra el listener "saving" que sanitiza nulls antes de cualquier
     * operación de escritura (INSERT o UPDATE).
     *
     * Convención FoxPro / RRHH.NET:
     *   - Los campos de texto   jamás deben ser NULL → ''
     *   - Los campos numéricos  jamás deben ser NULL → 0
     *   - Los booleanos         jamás deben ser NULL → false
     *   - Las fechas            jamás deben ser NULL → '1900-01-01'
     *
     * Esto actúa como red de contención final: aunque un controlador
     * omita la sanitización manual, la BD nunca recibirá NULLs.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (Personal $model): void {
            $casts = $model->getCasts();

            foreach ($model->getAttributes() as $campo => $valor) {
                if (!is_null($valor)) {
                    continue;
                }

                $tipo = strtolower($casts[$campo] ?? 'string');

                $model->$campo = match (true) {
                    str_starts_with($tipo, 'int')                              => 0,
                    str_starts_with($tipo, 'decimal')
                        || in_array($tipo, ['float', 'double', 'real'])       => 0,
                    $tipo === 'boolean' || $tipo === 'bool'                   => false,
                    str_contains($tipo, 'date') || str_contains($tipo, 'time') => '1900-01-01',
                    default                                                     => '',
                };
            }
        });
    }


    /**
     * Nombre de la tabla en MySQL.
     * @var string
     */
    protected $table = 'personal';

    /**
     * Clave primaria de la tabla.
     * No es auto_increment — se genera manualmente como MAX + 1.
     * @var string
     */
    protected $primaryKey = 'PER_COD';

    /**
     * La PK no es auto-incremental (herencia FoxPro).
     * @var bool
     */
    public $incrementing = false;

    /**
     * La PK es de tipo entero.
     * @var string
     */
    protected $keyType = 'int';

    /**
     * La tabla no tiene columnas created_at / updated_at de Laravel.
     * @var bool
     */
    public $timestamps = false;

    /**
     * Campos asignables masivamente.
     * Se listan los campos del formulario ABM de empleados.
     *
     * @var array<string>
     */
    protected $fillable = [
        'PER_COD',
        // ── Datos personales ─────────────────────────────────
        'PER_NOM',      // Nombre completo
        'PER_LEG',      // Legajo
        'PER_SEX',      // Sexo: M / F
        'PER_FNA',      // Fecha de nacimiento
        'PER_LNA',      // Lugar de nacimiento
        'PER_TDO',      // Tipo documento (código texto: DNI, PAS, etc.)
        'PER_NDO',      // Número de documento
        'PER_CUI',      // CUIL (xx-xxxxxxxx-x)
        'PER_ECI',      // Estado civil (FK → estadocivil.ECI_COD)
        'PER_HIJ',      // Cantidad de hijos
        'PER_DOM',      // Domicilio
        'PER_LOC',      // Localidad
        'PER_CPA',      // Código postal
        'PER_TEL',      // Teléfono fijo
        'PER_CEL',      // Celular
        'PER_CONTACTO', // Contacto de emergencia
        'PER_EME',      // Email de emergencia
        'PER_OBS',      // Observaciones generales
        // ── Datos personales extra ────────────────────────────
        'PER_HIM',       // Hijos mujeres
        'PER_HID',       // Hijos discapacitados
        'PER_EMA',       // Email personal
        'PER_NES',       // Nombre cónyuge
        'PER_PADRE',     // Nombre padre
        'PER_MADRE',     // Nombre madre
        'PER_HOB',       // Hobbies
        'PER_FACAD',     // Título académico
        // ── Datos laborales ──────────────────────────────────
        'ACTIVO',        // 1 = activo, 0 = dado de baja (campo MySQL)
        'PER_AOP',       // Estado FoxPro: "A" = Activo, "P" = Pasivo
        'PER_ING',       // Fecha de ingreso
        'PER_BAJ',       // Fecha de baja
        'PER_BAJ_RAZON', // Motivo de baja
        'PER_FEFECTIVO', // Fecha efectivo
        'PER_EMP',       // Empresa (FK → empresas.EMP_COD)
        'PER_EMD',       // Descripción empresa (desnormalizado)
        'PER_CONTRA',    // Tipo contratación (0=planta)
        'PER_LUGAR',     // Lugar de trabajo (FK → lugar.LUG_COD)
        'PER_SEC',       // Sector (FK → sector.SEC_COD)
        'PER_SED',       // Descripción sector (desnormalizado)
        'PER_CAT',       // Categoría (FK → categori.CAT_COD)
        'PER_CAD',       // Descripción categoría (desnormalizado)
        'PER_CON',       // Convenio (FK → convenio.CON_COD)
        'PER_JOM',       // Jornada: M=Mensual, J=Jornalizado
        'PER_HORAS',     // Horas semanales
        'PER_HNORMAL',   // Horas normales
        'PER_HSABADO',   // Horas sábado
        'PER_ALM',       // Almuerzo: S=Sí, N=No
        'PER_COMN',      // Número de comedor
        'PER_COMD',      // Descripción comedor
        'PER_GRU',       // Grupo de reloj
        'PER_COS',       // Centro de costo
        'PER_NOMARCA',   // No marca reloj (bool)
        'PER_NMRAZON',   // Razón no marca
        'PER_RENTACAR',  // Tiene rent-a-car
        'PER_TIENEPC',   // Tiene PC
        'PER_ADI_ENC',   // Adicional encargado
        'PER_ADI_SUS',   // Adicional supervisor
        // ── Reporte automático / sistema ──────────────────────
        'PARTE_ENV',     // Enviar reporte automático (bool)
        'PARTE_PRG',     // Programa/reloj envío (FK → reloj_envios.rdp_cod)
        'PENDIENTE_SISTEMA', // Pendiente de sistema (bool)
        'REQUIERE_SISTEMA',  // Descripción requerimiento de sistema
        // ── Remuneración ──────────────────────────────────────
        'PER_SUE',       // Sueldo básico
        'PER_REMU',      // Sueldo convenio
        'PER_NREM',      // Sueldo convenio nuevo
        'PER_DESCUENTO', // Descuento % para cálculo bruto
        'PER_CBU',       // CBU bancario
        'PER_BAN',       // Banco (código)
        'PER_BAD',       // Banco (descripción)
        'PER_SUC',       // Sucursal banco
        'PER_SUD',       // Sucursal descripción
        'OBRASOCIAL',    // Obra social (descripción libre)
        'PER_AOS',       // Código obra social (campo legacy)
        // ── Obra Social y Otros ───────────────────────────────
        'PER_PMP',       // Prestador médico predeterminado
        'PER_FAS',       // Fecha de alta obra social
        'PER_PRM',       // Promedio último semestre
        'PER_JRE',       // Jornada reducida: S/N
        'PER_JRC',       // Coeficiente jornada reducida
        'PER_SIN',       // Sindicato: S/N
        'PER_AFILIADO',  // Afiliado sindicato (bool)
        'PER_PSN',       // Promovido: S/N
        'PER_JSN',       // Jubilado: S/N
        'PER_CAR',       // Con cargo: S/N
        'PER_CHE',       // Horas extras habilitadas: S/N
        'PER_PPR',       // Porcentaje promedio (50 o 100)
        'PER_EMBSN',     // Embargo judicial: S/N
        'PER_EMBNOM',    // Nombre embargante
        'PER_EMBCBU',    // CBU para embargo
        // ── Licencia de conducir ──────────────────────────────
        'PER_LIC',       // Tiene licencia: S/N
        'PER_LCA',       // Categoría principal
        'PER_LVE',       // Vencimiento
        'PER_LN1', 'PER_LC1', 'PER_LF1', // Licencia 1
        'PER_LN2', 'PER_LC2', 'PER_LF2', // Licencia 2
        'PER_LN3', 'PER_LC3', 'PER_LF3', // Licencia 3
        'PER_LSF',       // Sin fines de semana: S/N
        'PER_LSG',       // Sin grúa: S/N
        'PER_LDO',       // Domicilio obligatorio: S/N
        'PER_LOB',       // Observaciones licencia
        'PER_LRE',       // Restricciones
        'PER_LCD',       // Conductor
    ];

    /**
     * Conversiones de tipo automáticas.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'PER_FNA'      => 'datetime',
        'PER_ING'      => 'datetime',
        'PER_BAJ'      => 'datetime',
        'PER_LVE'      => 'datetime',
        'PER_LF1'      => 'datetime',
        'PER_LF2'      => 'datetime',
        'PER_LF3'      => 'datetime',
        'PER_FEFECTIVO'=> 'datetime',
        'PER_FAS'      => 'datetime',
        'ACTIVO'       => 'boolean',
        'PER_NOMARCA'  => 'boolean',
        'PER_RENTACAR' => 'boolean',
        'PER_TIENEPC'  => 'boolean',
        'PER_ADI_ENC'  => 'boolean',
        'PER_ADI_SUS'  => 'boolean',
        'PARTE_ENV'    => 'boolean',
        'PENDIENTE_SISTEMA' => 'boolean',
        'PER_LUGAR'    => 'integer',
        'PARTE_PRG'    => 'integer',
        'PER_AFILIADO' => 'boolean',
        'PER_SUE'      => 'decimal:2',
        'PER_REMU'     => 'decimal:2',
        'PER_NREM'     => 'decimal:2',
        'PER_DESCUENTO'=> 'decimal:2',
        'PER_JRC'      => 'decimal:4',
        'PER_NDO'      => 'integer',
        'PER_HIJ'      => 'integer',
        'PER_HIM'      => 'integer',
        'PER_HID'      => 'integer',
        'PER_ECI'      => 'integer',
        'PER_EMP'      => 'integer',
        'PER_SEC'      => 'integer',
        'PER_CAT'      => 'integer',
        'PER_CON'      => 'integer',
        'PER_PPR'      => 'integer',
        'PER_HORAS'    => 'integer',
        'PER_GRU'      => 'integer',
        'PER_COS'      => 'integer',
        'PER_AOS'      => 'integer',
        'PER_BAN'      => 'integer',
        'PER_COMN'     => 'integer',
        'PER_CONTRA'   => 'integer',
    ];

    // ── Scopes de consulta ────────────────────────────────────────────────

    /**
     * Scope: solo empleados activos.
     * PER_AOP: "A" = Activo, "P" = Pasivo (de baja)
     */
    public function scopeActivos($query)
    {
        return $query->where('PER_AOP', 'A');
    }

    /**
     * Scope: empleados dados de baja (PER_AOP = "P" = Pasivo).
     */
    public function scopeBajas($query)
    {
        return $query->where('PER_AOP', 'P');
    }

    /**
     * Scope: buscar por código, legajo, nombre, domicilio, sexo,
     * nombre de padre/madre, hijos, DNI y CUIL.
     */
    public function scopeBuscar($query, string $termino)
    {
        $termino = trim($termino);
        $like    = "%{$termino}%";

        return $query->where(function ($q) use ($termino, $like) {
            $q->where('PER_COD', 'LIKE', $like)        // Código
              ->orWhere('PER_LEG', 'LIKE', $like)      // Legajo
              ->orWhere('PER_NOM', 'LIKE', $like)      // Nombre
              ->orWhere('PER_DOM', 'LIKE', $like)      // Domicilio
              ->orWhere('PER_PADRE', 'LIKE', $like)    // Nombre del padre
              ->orWhere('PER_MADRE', 'LIKE', $like)    // Nombre de la madre
              ->orWhere('PER_NDO', 'LIKE', $like)      // DNI
              ->orWhere('PER_CUI', 'LIKE', $like);     // CUIL

            // Sexo: el usuario escribe "masculino"/"femenino"; la BD guarda 'M'/'F'
            $t = mb_strtolower($termino);
            if (mb_strlen($t) >= 2) {
                if (str_starts_with('masculino', $t)) {
                    $q->orWhere('PER_SEX', 'M');
                } elseif (str_starts_with('femenino', $t)) {
                    $q->orWhere('PER_SEX', 'F');
                }
            }

            // Hijos: coincidencia por nombre del hijo en la tabla per_hijo
            $q->orWhereExists(function ($sub) use ($like) {
                $sub->selectRaw('1')
                    ->from('per_hijo')
                    ->whereColumn('per_hijo.PER_COD', 'personal.PER_COD')
                    ->where('per_hijo.PER_NOM', 'LIKE', $like);
            });
        });
    }
}
