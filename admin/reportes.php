<?php
// ============================================================
// COLEGIO SAN JOSÉ
// Sistema de Gestión Técnica
// Archivo: /tecnica/admin/reportes.php
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/funciones.php';
require_once __DIR__ . '/../includes/auth.php';


// ============================================================
// SOLO ADMINISTRADORES
// ============================================================

requerirAdministrador();


// ============================================================
// VERIFICAR USUARIO ACTIVO
// ============================================================

if (!verificarUsuarioActivo($conexion)) {

    $_SESSION['mensaje_login'] =
        'Tu sesión finalizó o la cuenta se encuentra inactiva.';

    header(
        'Location: ' . url('login.php')
    );

    exit;
}


// ============================================================
// FECHAS PREDETERMINADAS
//
// Por defecto mostramos los últimos 30 días.
// ============================================================

$fechaHastaDefault =
    date('Y-m-d');

$fechaDesdeDefault =
    date(
        'Y-m-d',
        strtotime('-30 days')
    );


// ============================================================
// RECIBIR FILTROS
// ============================================================

$fechaDesde =
    trim(
        $_GET['desde']
        ?? $fechaDesdeDefault
    );

$fechaHasta =
    trim(
        $_GET['hasta']
        ?? $fechaHastaDefault
    );

$tipo =
    trim(
        $_GET['tipo']
        ?? ''
    );


// ============================================================
// VALIDAR FECHAS
// ============================================================

function fechaValidaReporte(
    string $fecha
): bool {

    $d =
        DateTime::createFromFormat(
            'Y-m-d',
            $fecha
        );

    return $d
        &&
        $d->format('Y-m-d')
        === $fecha;
}


if (
    !fechaValidaReporte(
        $fechaDesde
    )
) {

    $fechaDesde =
        $fechaDesdeDefault;
}


if (
    !fechaValidaReporte(
        $fechaHasta
    )
) {

    $fechaHasta =
        $fechaHastaDefault;
}


// ============================================================
// SI EL RANGO ESTÁ INVERTIDO
// ============================================================

if (
    strtotime($fechaDesde)
    >
    strtotime($fechaHasta)
) {

    $aux =
        $fechaDesde;

    $fechaDesde =
        $fechaHasta;

    $fechaHasta =
        $aux;
}


// ============================================================
// TIPO
// ============================================================

$tiposPermitidos = [
    '',
    'Informatica',
    'Mantenimiento'
];


if (
    !in_array(
        $tipo,
        $tiposPermitidos,
        true
    )
) {

    $tipo = '';
}


// ============================================================
// FECHAS SQL
// ============================================================

$desdeSQL =
    $fechaDesde
    . ' 00:00:00';

$hastaSQL =
    $fechaHasta
    . ' 23:59:59';


// ============================================================
// CONDICIÓN GENERAL
// ============================================================

$whereTipo = '';

$paramsBase = [
    $desdeSQL,
    $hastaSQL
];


if ($tipo !== '') {

    $whereTipo =
        ' AND s.tipo = ? ';

    $paramsBase[] =
        $tipo;
}


// ============================================================
// RESUMEN GENERAL
// ============================================================

$sqlResumen = "
    SELECT

        COUNT(*) AS total,

        SUM(
            CASE
                WHEN s.estado = 'Nueva'
                THEN 1
                ELSE 0
            END
        ) AS nuevas,

        SUM(
            CASE
                WHEN s.estado = 'Asignada'
                THEN 1
                ELSE 0
            END
        ) AS asignadas,

        SUM(
            CASE
                WHEN s.estado = 'En proceso'
                THEN 1
                ELSE 0
            END
        ) AS en_proceso,

        SUM(
            CASE
                WHEN s.estado = 'Pendiente'
                THEN 1
                ELSE 0
            END
        ) AS pendientes,

        SUM(
            CASE
                WHEN s.estado = 'Resuelta'
                THEN 1
                ELSE 0
            END
        ) AS resueltas,

        SUM(
            CASE
                WHEN s.estado = 'Cerrada'
                THEN 1
                ELSE 0
            END
        ) AS cerradas,

        SUM(
            CASE
                WHEN s.estado = 'Cancelada'
                THEN 1
                ELSE 0
            END
        ) AS canceladas,

        SUM(
            CASE
                WHEN s.prioridad = 'Urgente'
                THEN 1
                ELSE 0
            END
        ) AS urgentes,

        SUM(
            CASE
                WHEN s.tipo = 'Informatica'
                THEN 1
                ELSE 0
            END
        ) AS informatica,

        SUM(
            CASE
                WHEN s.tipo = 'Mantenimiento'
                THEN 1
                ELSE 0
            END
        ) AS mantenimiento,

        AVG(
            CASE
                WHEN s.fecha_resolucion IS NOT NULL
                THEN
                    TIMESTAMPDIFF(
                        HOUR,
                        s.fecha_creacion,
                        s.fecha_resolucion
                    )
                ELSE NULL
            END
        ) AS horas_promedio_resolucion

    FROM solicitudes s

    WHERE
        s.fecha_creacion
        BETWEEN ? AND ?

        {$whereTipo}
";


$stmtResumen =
    $conexion->prepare(
        $sqlResumen
    );


$stmtResumen->execute(
    $paramsBase
);


$resumen =
    $stmtResumen->fetch(
        PDO::FETCH_ASSOC
    );


// ============================================================
// NORMALIZAR VALORES
// ============================================================

$totalSolicitudes =
    (int)(
        $resumen['total']
        ?? 0
    );

$totalResueltas =
    (int)(
        $resumen['resueltas']
        ?? 0
    )
    +
    (int)(
        $resumen['cerradas']
        ?? 0
    );


$porcentajeResolucion =
    $totalSolicitudes > 0
        ?
        round(
            (
                $totalResueltas
                /
                $totalSolicitudes
            )
            * 100,
            1
        )
        :
        0;


$horasPromedio =
    $resumen[
        'horas_promedio_resolucion'
    ] !== null
        ?
        round(
            (float)$resumen[
                'horas_promedio_resolucion'
            ],
            1
        )
        :
        0;


// ============================================================
// FORMATEAR TIEMPO PROMEDIO
// ============================================================

function tiempoPromedioReporte(
    float $horas
): string {

    if ($horas <= 0) {

        return '-';
    }

    if ($horas < 24) {

        return
            number_format(
                $horas,
                1,
                ',',
                '.'
            )
            .
            ' h';
    }


    $dias =
        $horas / 24;


    return
        number_format(
            $dias,
            1,
            ',',
            '.'
        )
        .
        ' días';
}


// ============================================================
// SOLICITUDES POR ESTADO
// ============================================================

$sqlEstados = "
    SELECT

        s.estado,

        COUNT(*) AS cantidad

    FROM solicitudes s

    WHERE
        s.fecha_creacion
        BETWEEN ? AND ?

        {$whereTipo}

    GROUP BY
        s.estado

    ORDER BY
        cantidad DESC
";


$stmtEstados =
    $conexion->prepare(
        $sqlEstados
    );


$stmtEstados->execute(
    $paramsBase
);


$porEstado =
    $stmtEstados->fetchAll(
        PDO::FETCH_ASSOC
    );


// ============================================================
// SOLICITUDES POR PRIORIDAD
// ============================================================

$sqlPrioridades = "
    SELECT

        s.prioridad,

        COUNT(*) AS cantidad

    FROM solicitudes s

    WHERE
        s.fecha_creacion
        BETWEEN ? AND ?

        {$whereTipo}

    GROUP BY
        s.prioridad

    ORDER BY

        CASE s.prioridad

            WHEN 'Urgente'
                THEN 1

            WHEN 'Alta'
                THEN 2

            WHEN 'Normal'
                THEN 3

            WHEN 'Baja'
                THEN 4

            ELSE 5

        END
";


$stmtPrioridades =
    $conexion->prepare(
        $sqlPrioridades
    );


$stmtPrioridades->execute(
    $paramsBase
);


$porPrioridad =
    $stmtPrioridades->fetchAll(
        PDO::FETCH_ASSOC
    );


// ============================================================
// SOLICITUDES POR SECTOR
// ============================================================

$sqlSectores = "
    SELECT

        COALESCE(
            sec.nombre,
            'Sin sector'
        ) AS sector,

        COUNT(*) AS cantidad

    FROM solicitudes s

    LEFT JOIN sectores sec
        ON s.id_sector =
           sec.id_sector

    WHERE
        s.fecha_creacion
        BETWEEN ? AND ?

        {$whereTipo}

    GROUP BY
        s.id_sector,
        sec.nombre

    ORDER BY
        cantidad DESC

    LIMIT 10
";


$stmtSectores =
    $conexion->prepare(
        $sqlSectores
    );


$stmtSectores->execute(
    $paramsBase
);


$porSector =
    $stmtSectores->fetchAll(
        PDO::FETCH_ASSOC
    );


// ============================================================
// SOLICITUDES POR CATEGORÍA
// ============================================================

$sqlCategorias = "
    SELECT

        COALESCE(
            c.nombre,
            'Sin categoría'
        ) AS categoria,

        COUNT(*) AS cantidad

    FROM solicitudes s

    LEFT JOIN categorias c
        ON s.id_categoria =
           c.id_categoria

    WHERE
        s.fecha_creacion
        BETWEEN ? AND ?

        {$whereTipo}

    GROUP BY
        s.id_categoria,
        c.nombre

    ORDER BY
        cantidad DESC

    LIMIT 10
";


$stmtCategorias =
    $conexion->prepare(
        $sqlCategorias
    );


$stmtCategorias->execute(
    $paramsBase
);


$porCategoria =
    $stmtCategorias->fetchAll(
        PDO::FETCH_ASSOC
    );


// ============================================================
// EVOLUCIÓN DIARIA
//
// Ideal para el período seleccionado.
// ============================================================

$sqlEvolucion = "
    SELECT

        DATE(
            s.fecha_creacion
        ) AS fecha,

        COUNT(*) AS cantidad

    FROM solicitudes s

    WHERE
        s.fecha_creacion
        BETWEEN ? AND ?

        {$whereTipo}

    GROUP BY
        DATE(
            s.fecha_creacion
        )

    ORDER BY
        fecha ASC
";


$stmtEvolucion =
    $conexion->prepare(
        $sqlEvolucion
    );


$stmtEvolucion->execute(
    $paramsBase
);


$evolucion =
    $stmtEvolucion->fetchAll(
        PDO::FETCH_ASSOC
    );


// ============================================================
// INTERVENCIONES EN EL PERÍODO
// ============================================================

$paramsIntervencion = [
    $desdeSQL,
    $hastaSQL
];


$whereIntervencionTipo = '';


if ($tipo !== '') {

    $whereIntervencionTipo =
        ' AND s.tipo = ? ';

    $paramsIntervencion[] =
        $tipo;
}


$sqlIntervenciones = "
    SELECT

        COUNT(*) AS total_intervenciones,

        COUNT(
            DISTINCT i.id_solicitud
        ) AS solicitudes_intervenidas,

        SUM(
            CASE
                WHEN i.pendiente = 1
                THEN 1
                ELSE 0
            END
        ) AS intervenciones_pendientes

    FROM intervenciones i

    INNER JOIN solicitudes s
        ON i.id_solicitud =
           s.id_solicitud

    WHERE
        i.fecha_intervencion
        BETWEEN ? AND ?

        {$whereIntervencionTipo}
";


$stmtIntervenciones =
    $conexion->prepare(
        $sqlIntervenciones
    );


$stmtIntervenciones->execute(
    $paramsIntervencion
);


$resumenIntervenciones =
    $stmtIntervenciones->fetch(
        PDO::FETCH_ASSOC
    );


// ============================================================
// ACTIVIDAD POR TÉCNICO
// ============================================================

$sqlTecnicos = "
    SELECT

        u.id_usuario,

        CONCAT(
            u.nombre,
            ' ',
            u.apellido
        ) AS tecnico,

        COUNT(
            i.id_intervencion
        ) AS intervenciones,

        COUNT(
            DISTINCT i.id_solicitud
        ) AS solicitudes,

        SUM(
            CASE
                WHEN i.pendiente = 1
                THEN 1
                ELSE 0
            END
        ) AS pendientes

    FROM intervenciones i

    INNER JOIN solicitudes s
        ON i.id_solicitud =
           s.id_solicitud

    INNER JOIN usuarios u
        ON i.id_tecnico =
           u.id_usuario

    WHERE
        i.fecha_intervencion
        BETWEEN ? AND ?

        {$whereIntervencionTipo}

    GROUP BY
        u.id_usuario,
        u.nombre,
        u.apellido

    ORDER BY
        intervenciones DESC
";


$stmtTecnicos =
    $conexion->prepare(
        $sqlTecnicos
    );


$stmtTecnicos->execute(
    $paramsIntervencion
);


$porTecnico =
    $stmtTecnicos->fetchAll(
        PDO::FETCH_ASSOC
    );


// ============================================================
// SOLICITUDES RESUELTAS CON TIEMPOS
// ============================================================

$sqlResueltas = "
    SELECT

        s.id_solicitud,

        s.titulo,

        s.tipo,

        s.prioridad,

        s.estado,

        s.fecha_creacion,

        s.fecha_resolucion,

        TIMESTAMPDIFF(
            HOUR,
            s.fecha_creacion,
            s.fecha_resolucion
        ) AS horas_resolucion,

        CONCAT(
            u.nombre,
            ' ',
            u.apellido
        ) AS solicitante,

        sec.nombre AS sector

    FROM solicitudes s

    INNER JOIN usuarios u
        ON s.id_usuario =
           u.id_usuario

    LEFT JOIN sectores sec
        ON s.id_sector =
           sec.id_sector

    WHERE
        s.fecha_creacion
        BETWEEN ? AND ?

        {$whereTipo}

        AND
        s.fecha_resolucion IS NOT NULL

    ORDER BY
        s.fecha_resolucion DESC

    LIMIT 15
";


$stmtResueltas =
    $conexion->prepare(
        $sqlResueltas
    );


$stmtResueltas->execute(
    $paramsBase
);


$ultimasResueltas =
    $stmtResueltas->fetchAll(
        PDO::FETCH_ASSOC
    );


// ============================================================
// PENDIENTES ACTUALES CREADAS EN EL PERÍODO
// ============================================================

$sqlPendientes = "
    SELECT

        s.id_solicitud,

        s.titulo,

        s.tipo,

        s.prioridad,

        s.motivo_pendiente,

        s.fecha_creacion,

        CONCAT(
            u.nombre,
            ' ',
            u.apellido
        ) AS solicitante,

        sec.nombre AS sector

    FROM solicitudes s

    INNER JOIN usuarios u
        ON s.id_usuario =
           u.id_usuario

    LEFT JOIN sectores sec
        ON s.id_sector =
           sec.id_sector

    WHERE
        s.fecha_creacion
        BETWEEN ? AND ?

        {$whereTipo}

        AND
        s.estado = 'Pendiente'

    ORDER BY

        CASE s.prioridad

            WHEN 'Urgente'
                THEN 1

            WHEN 'Alta'
                THEN 2

            WHEN 'Normal'
                THEN 3

            WHEN 'Baja'
                THEN 4

            ELSE 5

        END,

        s.fecha_creacion ASC

    LIMIT 15
";


$stmtPendientes =
    $conexion->prepare(
        $sqlPendientes
    );


$stmtPendientes->execute(
    $paramsBase
);


$pendientesDetalle =
    $stmtPendientes->fetchAll(
        PDO::FETCH_ASSOC
    );


// ============================================================
// MEJORAS CREADAS EN EL PERÍODO
// ============================================================

$paramsMejoras = [
    $desdeSQL,
    $hastaSQL
];


$whereMejorasTipo = '';


if ($tipo !== '') {

    $whereMejorasTipo =
        ' AND m.tipo = ? ';

    $paramsMejoras[] =
        $tipo;
}


$sqlMejoras = "
    SELECT

        COUNT(*) AS total,

        SUM(
            CASE
                WHEN m.estado = 'Propuesta'
                THEN 1
                ELSE 0
            END
        ) AS propuestas,

        SUM(
            CASE
                WHEN m.estado = 'Pendiente autorizacion'
                THEN 1
                ELSE 0
            END
        ) AS pendientes,

        SUM(
            CASE
                WHEN m.estado = 'Aprobada'
                THEN 1
                ELSE 0
            END
        ) AS aprobadas,

        SUM(
            CASE
                WHEN m.estado = 'En ejecucion'
                THEN 1
                ELSE 0
            END
        ) AS ejecucion,

        SUM(
            CASE
                WHEN m.estado = 'Realizada'
                THEN 1
                ELSE 0
            END
        ) AS realizadas,

        SUM(
            CASE
                WHEN m.costo_estimado IS NOT NULL
                THEN m.costo_estimado
                ELSE 0
            END
        ) AS costo_estimado_total

    FROM mejoras m

    WHERE
        m.fecha_creacion
        BETWEEN ? AND ?

        {$whereMejorasTipo}
";


$stmtMejoras =
    $conexion->prepare(
        $sqlMejoras
    );


$stmtMejoras->execute(
    $paramsMejoras
);


$resumenMejoras =
    $stmtMejoras->fetch(
        PDO::FETCH_ASSOC
    );


// ============================================================
// CONVERTIR DATOS PARA CHART.JS
// ============================================================

$labelsEstados = [];
$datosEstados = [];


foreach (
    $porEstado
    as $fila
) {

    $labelsEstados[] =
        $fila['estado'];

    $datosEstados[] =
        (int)$fila[
            'cantidad'
        ];
}


$labelsPrioridad = [];
$datosPrioridad = [];


foreach (
    $porPrioridad
    as $fila
) {

    $labelsPrioridad[] =
        $fila['prioridad'];

    $datosPrioridad[] =
        (int)$fila[
            'cantidad'
        ];
}


$labelsEvolucion = [];
$datosEvolucion = [];


foreach (
    $evolucion
    as $fila
) {

    $labelsEvolucion[] =
        date(
            'd/m',
            strtotime(
                $fila['fecha']
            )
        );

    $datosEvolucion[] =
        (int)$fila[
            'cantidad'
        ];
}


$labelsSectores = [];
$datosSectores = [];


foreach (
    $porSector
    as $fila
) {

    $labelsSectores[] =
        $fila['sector'];

    $datosSectores[] =
        (int)$fila[
            'cantidad'
        ];
}


// ============================================================
// HEADER
// ============================================================

require_once __DIR__
    . '/../includes/header.php';

?>


<!-- =========================================================
     CHART.JS
========================================================= -->

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>


<style>

.reportes-wrapper {

    max-width: 1550px;

    margin: 0 auto;

    padding:
        5px 12px 45px;

}


/* ============================================================
   HERO
============================================================ */

.reportes-hero {

    position: relative;

    overflow: hidden;

    padding: 29px;

    margin-bottom: 23px;

    border-radius: 22px;

    color: #FFFFFF;

    background:
        linear-gradient(
            135deg,
            #760000,
            #B12626
        );

    box-shadow:
        0 9px 28px
        rgba(118,0,0,.16);

}


.reportes-hero::after {

    content: "";

    position: absolute;

    width: 290px;

    height: 290px;

    right: -110px;

    top: -140px;

    border-radius: 50%;

    background:
        rgba(255,255,255,.06);

}


.hero-content {

    position: relative;

    z-index: 2;

}


.reportes-hero h1 {

    margin:
        0 0 7px;

    font-size: 28px;

    font-weight: 800;

}


.reportes-hero p {

    max-width: 780px;

    margin: 0;

    color:
        rgba(255,255,255,.78);

}


.hero-actions {

    position: relative;

    z-index: 2;

    display: flex;

    justify-content: flex-end;

    gap: 8px;

}


.btn-hero {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    gap: 7px;

    padding:
        10px 15px;

    border: 0;

    border-radius: 10px;

    text-decoration: none;

    font-weight: 700;

}


.btn-hero-white {

    background: #FFFFFF;

    color: #760000;

}


.btn-hero-white:hover {

    background: #F4F4F4;

    color: #B12626;

}


.btn-hero-outline {

    background:
        rgba(255,255,255,.10);

    color: #FFFFFF;

    border:
        1px solid
        rgba(255,255,255,.25);

}


.btn-hero-outline:hover {

    color: #FFFFFF;

    background:
        rgba(255,255,255,.18);

}


/* ============================================================
   FILTROS
============================================================ */

.report-filter {

    padding: 19px;

    margin-bottom: 23px;

    border:
        1px solid #ECECEC;

    border-radius: 17px;

    background: #FFFFFF;

    box-shadow:
        0 5px 17px
        rgba(0,0,0,.04);

}


.form-label {

    color: #555555;

    font-size: 12px;

    font-weight: 700;

}


.form-control,
.form-select {

    min-height: 44px;

    border-radius: 9px;

}


.form-control:focus,
.form-select:focus {

    border-color: #B12626;

    box-shadow:
        0 0 0 .2rem
        rgba(177,38,38,.08);

}


.btn-filter {

    min-height: 44px;

    border: 0;

    border-radius: 9px;

    background: #B12626;

    color: #FFFFFF;

    font-weight: 700;

}


.btn-filter:hover {

    background: #760000;

    color: #FFFFFF;

}


/* ============================================================
   PERÍODO
============================================================ */

.periodo-informe {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 15px;

    margin-bottom: 18px;

    padding: 12px 15px;

    border-left:
        4px solid #B12626;

    border-radius: 9px;

    background: #FFF7F7;

    color: #555555;

    font-size: 12px;

}


.periodo-informe strong {

    color: #760000;

}


/* ============================================================
   ESTADÍSTICAS
============================================================ */

.stat-card {

    height: 100%;

    padding: 18px;

    border:
        1px solid #ECECEC;

    border-radius: 16px;

    background: #FFFFFF;

    box-shadow:
        0 4px 15px
        rgba(0,0,0,.04);

}


.stat-icon {

    width: 43px;

    height: 43px;

    display: flex;

    align-items: center;

    justify-content: center;

    margin-bottom: 10px;

    border-radius: 11px;

    font-size: 18px;

}


.stat-number {

    color: #333333;

    font-size: 27px;

    line-height: 1;

    font-weight: 800;

}


.stat-label {

    margin-top: 6px;

    color: #797979;

    font-size: 11px;

    font-weight: 700;

}


.stat-total {

    color: #760000;

    background: #F2E5E5;

}


.stat-resolved {

    color: #198754;

    background: #E1F4E8;

}


.stat-pending {

    color: #936F00;

    background: #FFF3CD;

}


.stat-urgent {

    color: #FFFFFF;

    background: #B12626;

}


.stat-time {

    color: #0D6EFD;

    background: #E8F1FF;

}


.stat-percent {

    color: #087990;

    background: #DDF4F8;

}


/* ============================================================
   CARDS
============================================================ */

.report-card {

    height: 100%;

    overflow: hidden;

    border:
        1px solid #ECECEC;

    border-radius: 18px;

    background: #FFFFFF;

    box-shadow:
        0 5px 19px
        rgba(0,0,0,.05);

}


.report-card-header {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 12px;

    padding:
        17px 20px;

    border-bottom:
        1px solid #EEEEEE;

}


.report-card-header h5 {

    margin: 0;

    color: #760000;

    font-size: 15px;

    font-weight: 800;

}


.report-card-body {

    padding: 20px;

}


/* ============================================================
   CHARTS
============================================================ */

.chart-container {

    position: relative;

    width: 100%;

    min-height: 300px;

}


.chart-container canvas {

    max-height: 320px;

}


/* ============================================================
   LISTAS
============================================================ */

.rank-item {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 12px;

    padding:
        11px 0;

    border-bottom:
        1px solid #EEEEEE;

}


.rank-item:last-child {

    border-bottom: 0;

}


.rank-position {

    min-width: 29px;

    width: 29px;

    height: 29px;

    display: inline-flex;

    align-items: center;

    justify-content: center;

    border-radius: 8px;

    background: #F3E6E6;

    color: #760000;

    font-size: 11px;

    font-weight: 800;

}


.rank-name {

    flex: 1;

    color: #444444;

    font-size: 12px;

    font-weight: 600;

}


.rank-value {

    color: #B12626;

    font-size: 13px;

    font-weight: 800;

}


/* ============================================================
   TÉCNICOS
============================================================ */

.tecnico-row {

    padding:
        12px 0;

    border-bottom:
        1px solid #EEEEEE;

}


.tecnico-row:last-child {

    border-bottom: 0;

}


.tecnico-nombre {

    color: #333333;

    font-size: 12px;

    font-weight: 800;

}


.tecnico-stats {

    display: flex;

    flex-wrap: wrap;

    gap: 10px;

    margin-top: 5px;

    color: #7B7B7B;

    font-size: 10px;

}


/* ============================================================
   TABLA
============================================================ */

.table {

    margin-bottom: 0;

}


.table thead th {

    padding:
        12px 13px;

    background: #FAFAFA;

    color: #555555;

    font-size: 10px;

    text-transform: uppercase;

    letter-spacing: .3px;

    white-space: nowrap;

}


.table tbody td {

    padding:
        13px;

    border-color: #EEEEEE;

    vertical-align: middle;

}


.ticket-link {

    color: #760000;

    font-size: 11px;

    font-weight: 800;

    text-decoration: none;

}


.ticket-link:hover {

    color: #B12626;

}


.ticket-title {

    color: #333333;

    font-size: 12px;

    font-weight: 700;

}


.meta-small {

    color: #838383;

    font-size: 10px;

}


/* ============================================================
   PENDIENTES
============================================================ */

.pendiente-row {

    padding:
        13px 0;

    border-bottom:
        1px solid #EEEEEE;

}


.pendiente-row:last-child {

    border-bottom: 0;

}


.pendiente-titulo {

    color: #333333;

    font-size: 12px;

    font-weight: 800;

}


.pendiente-motivo {

    margin-top: 7px;

    padding:
        7px 9px;

    border-left:
        3px solid #E0A800;

    border-radius: 7px;

    background: #FFF8DF;

    color: #685600;

    font-size: 10px;

}


/* ============================================================
   MEJORAS
============================================================ */

.mejoras-summary {

    display: grid;

    grid-template-columns:
        repeat(3,1fr);

    gap: 10px;

}


.mejora-stat {

    padding: 13px;

    border-radius: 10px;

    background: #F8F8F8;

}


.mejora-label {

    color: #888888;

    font-size: 9px;

    text-transform: uppercase;

    font-weight: 700;

}


.mejora-value {

    margin-top: 3px;

    color: #333333;

    font-size: 19px;

    font-weight: 800;

}


/* ============================================================
   EMPTY
============================================================ */

.empty-state {

    padding:
        35px 10px;

    text-align: center;

    color: #929292;

    font-size: 12px;

}


.empty-state i {

    display: block;

    margin-bottom: 7px;

    color: #D0D0D0;

    font-size: 37px;

}


/* ============================================================
   PRINT
============================================================ */

.print-title {

    display: none;

}


@media print {

    @page {

        size: A4 landscape;

        margin: 10mm;

    }


    body {

        background: #FFFFFF !important;

    }


    header,
    footer,
    nav,
    .reportes-hero,
    .report-filter,
    .no-print {

        display: none !important;

    }


    .reportes-wrapper {

        max-width: none;

        padding: 0;

    }


    .print-title {

        display: block;

        margin-bottom: 18px;

    }


    .print-title h1 {

        color: #760000;

        font-size: 22px;

        font-weight: 800;

    }


    .report-card,
    .stat-card {

        break-inside: avoid;

        box-shadow: none;

    }


    .chart-container {

        min-height: 240px;

    }

}


/* ============================================================
   RESPONSIVE
============================================================ */

@media
(max-width: 767px) {

    .reportes-hero {

        padding:
            22px 20px;

    }


    .reportes-hero h1 {

        font-size: 23px;

    }


    .hero-actions {

        margin-top: 18px;

        justify-content: flex-start;

        flex-direction: column;

    }


    .btn-hero {

        width: 100%;

    }


    .mejoras-summary {

        grid-template-columns:
            repeat(2,1fr);

    }


    .periodo-informe {

        align-items: flex-start;

        flex-direction: column;

    }

}

</style>


<div class="reportes-wrapper">


    <!-- =====================================================
         CABECERA PARA IMPRESIÓN
    ====================================================== -->

    <div class="print-title">

        <h1>
            Colegio San José - Informe técnico
        </h1>

        <div>

            Período:
            <?= e(
                date(
                    'd/m/Y',
                    strtotime($fechaDesde)
                )
            ) ?>

            al

            <?= e(
                date(
                    'd/m/Y',
                    strtotime($fechaHasta)
                )
            ) ?>

            <?php if ($tipo !== ''): ?>

                · Área:
                <?= e(
                    nombreTipo(
                        $tipo
                    )
                ) ?>

            <?php endif; ?>

        </div>

    </div>


    <!-- =====================================================
         HERO
    ====================================================== -->

    <section class="reportes-hero">

        <div class="row align-items-center">

            <div class="col-lg-7">

                <div class="hero-content">

                    <h1>

                        <i class="bi bi-bar-chart-line me-1"></i>

                        Reportes e informes

                    </h1>

                    <p>

                        Analizá solicitudes, intervenciones,
                        tiempos de resolución, sectores,
                        prioridades y propuestas de mejora.

                    </p>

                </div>

            </div>


            <div class="col-lg-5">

                <div class="hero-actions">

                    <button
                        type="button"
                        class="btn-hero btn-hero-outline"
                        onclick="window.print()"
                    >

                        <i class="bi bi-printer"></i>

                        Imprimir informe

                    </button>


                    <a
                        href="<?= url(
                            'admin/dashboard.php'
                        ) ?>"
                        class="btn-hero btn-hero-white"
                    >

                        <i class="bi bi-arrow-left"></i>

                        Panel administrador

                    </a>

                </div>

            </div>

        </div>

    </section>


    <!-- =====================================================
         FILTROS
    ====================================================== -->

    <div class="report-filter">

        <form
            method="GET"
            action="<?= url(
                'admin/reportes.php'
            ) ?>"
        >

            <div class="row g-3">


                <div class="col-md-4 col-xl-3">

                    <label
                        for="desde"
                        class="form-label"
                    >
                        Desde
                    </label>

                    <input
                        type="date"
                        name="desde"
                        id="desde"
                        class="form-control"
                        value="<?= e(
                            $fechaDesde
                        ) ?>"
                        required
                    >

                </div>


                <div class="col-md-4 col-xl-3">

                    <label
                        for="hasta"
                        class="form-label"
                    >
                        Hasta
                    </label>

                    <input
                        type="date"
                        name="hasta"
                        id="hasta"
                        class="form-control"
                        value="<?= e(
                            $fechaHasta
                        ) ?>"
                        required
                    >

                </div>


                <div class="col-md-4 col-xl-3">

                    <label
                        for="tipo"
                        class="form-label"
                    >
                        Área
                    </label>

                    <select
                        name="tipo"
                        id="tipo"
                        class="form-select"
                    >

                        <option value="">
                            Todas
                        </option>

                        <option
                            value="Informatica"
                            <?= $tipo === 'Informatica'
                                ? 'selected'
                                : ''
                            ?>
                        >
                            Informática
                        </option>

                        <option
                            value="Mantenimiento"
                            <?= $tipo === 'Mantenimiento'
                                ? 'selected'
                                : ''
                            ?>
                        >
                            Mantenimiento general
                        </option>

                    </select>

                </div>


                <div
                    class="col-xl-3
                           d-flex
                           align-items-end
                           gap-2"
                >

                    <button
                        type="submit"
                        class="btn btn-filter flex-fill"
                    >

                        <i class="bi bi-funnel me-1"></i>

                        Generar informe

                    </button>


                    <a
                        href="<?= url(
                            'admin/reportes.php'
                        ) ?>"
                        class="btn btn-outline-secondary"
                        title="Restablecer"
                    >

                        <i class="bi bi-arrow-counterclockwise"></i>

                    </a>

                </div>


            </div>

        </form>

    </div>


    <!-- =====================================================
         PERÍODO
    ====================================================== -->

    <div class="periodo-informe">

        <div>

            <i class="bi bi-calendar-range me-1"></i>

            <strong>
                Período analizado:
            </strong>

            <?= e(
                date(
                    'd/m/Y',
                    strtotime($fechaDesde)
                )
            ) ?>

            al

            <?= e(
                date(
                    'd/m/Y',
                    strtotime($fechaHasta)
                )
            ) ?>

        </div>


        <div>

            <strong>
                Área:
            </strong>

            <?= $tipo !== ''
                ? e(
                    nombreTipo(
                        $tipo
                    )
                )
                : 'Informática y Mantenimiento'
            ?>

        </div>

    </div>


    <!-- =====================================================
         KPI
    ====================================================== -->

    <div class="row g-3 mb-4">


        <div class="col-6 col-md-4 col-xl-2">

            <div class="stat-card">

                <div class="stat-icon stat-total">

                    <i class="bi bi-ticket-detailed"></i>

                </div>

                <div class="stat-number">

                    <?= $totalSolicitudes ?>

                </div>

                <div class="stat-label">
                    Solicitudes
                </div>

            </div>

        </div>


        <div class="col-6 col-md-4 col-xl-2">

            <div class="stat-card">

                <div class="stat-icon stat-resolved">

                    <i class="bi bi-check-circle"></i>

                </div>

                <div class="stat-number">

                    <?= $totalResueltas ?>

                </div>

                <div class="stat-label">
                    Resueltas / cerradas
                </div>

            </div>

        </div>


        <div class="col-6 col-md-4 col-xl-2">

            <div class="stat-card">

                <div class="stat-icon stat-pending">

                    <i class="bi bi-hourglass-split"></i>

                </div>

                <div class="stat-number">

                    <?= (int)(
                        $resumen[
                            'pendientes'
                        ]
                        ?? 0
                    ) ?>

                </div>

                <div class="stat-label">
                    Pendientes
                </div>

            </div>

        </div>


        <div class="col-6 col-md-4 col-xl-2">

            <div class="stat-card">

                <div class="stat-icon stat-urgent">

                    <i class="bi bi-exclamation-triangle"></i>

                </div>

                <div class="stat-number">

                    <?= (int)(
                        $resumen[
                            'urgentes'
                        ]
                        ?? 0
                    ) ?>

                </div>

                <div class="stat-label">
                    Prioridad urgente
                </div>

            </div>

        </div>


        <div class="col-6 col-md-4 col-xl-2">

            <div class="stat-card">

                <div class="stat-icon stat-percent">

                    <i class="bi bi-percent"></i>

                </div>

                <div class="stat-number">

                    <?= e(
                        number_format(
                            $porcentajeResolucion,
                            1,
                            ',',
                            '.'
                        )
                    ) ?>%

                </div>

                <div class="stat-label">
                    Índice de resolución
                </div>

            </div>

        </div>


        <div class="col-6 col-md-4 col-xl-2">

            <div class="stat-card">

                <div class="stat-icon stat-time">

                    <i class="bi bi-stopwatch"></i>

                </div>

                <div
                    class="stat-number"
                    style="font-size:20px;"
                >

                    <?= e(
                        tiempoPromedioReporte(
                            $horasPromedio
                        )
                    ) ?>

                </div>

                <div class="stat-label">
                    Tiempo promedio
                </div>

            </div>

        </div>


    </div>


    <!-- =====================================================
         GRÁFICOS PRIMERA FILA
    ====================================================== -->

    <div class="row g-4 mb-4">


        <!-- EVOLUCIÓN -->

        <div class="col-xl-8">

            <div class="report-card">

                <div class="report-card-header">

                    <h5>

                        <i class="bi bi-graph-up me-2"></i>

                        Solicitudes registradas por día

                    </h5>

                </div>


                <div class="report-card-body">

                    <?php if (
                        empty(
                            $evolucion
                        )
                    ): ?>

                        <div class="empty-state">

                            <i class="bi bi-bar-chart"></i>

                            Sin datos para el período.

                        </div>

                    <?php else: ?>

                        <div class="chart-container">

                            <canvas id="graficoEvolucion"></canvas>

                        </div>

                    <?php endif; ?>

                </div>

            </div>

        </div>


        <!-- ESTADOS -->

        <div class="col-xl-4">

            <div class="report-card">

                <div class="report-card-header">

                    <h5>

                        <i class="bi bi-pie-chart me-2"></i>

                        Estados

                    </h5>

                </div>


                <div class="report-card-body">

                    <?php if (
                        empty(
                            $porEstado
                        )
                    ): ?>

                        <div class="empty-state">

                            Sin información.

                        </div>

                    <?php else: ?>

                        <div class="chart-container">

                            <canvas id="graficoEstados"></canvas>

                        </div>

                    <?php endif; ?>

                </div>

            </div>

        </div>


    </div>


    <!-- =====================================================
         SEGUNDA FILA
    ====================================================== -->

    <div class="row g-4 mb-4">


        <!-- PRIORIDADES -->

        <div class="col-lg-5">

            <div class="report-card">

                <div class="report-card-header">

                    <h5>

                        <i class="bi bi-exclamation-diamond me-2"></i>

                        Prioridades

                    </h5>

                </div>


                <div class="report-card-body">

                    <?php if (
                        empty(
                            $porPrioridad
                        )
                    ): ?>

                        <div class="empty-state">
                            Sin información.
                        </div>

                    <?php else: ?>

                        <div class="chart-container">

                            <canvas id="graficoPrioridad"></canvas>

                        </div>

                    <?php endif; ?>

                </div>

            </div>

        </div>


        <!-- SECTORES -->

        <div class="col-lg-7">

            <div class="report-card">

                <div class="report-card-header">

                    <h5>

                        <i class="bi bi-building me-2"></i>

                        Sectores con más solicitudes

                    </h5>

                </div>


                <div class="report-card-body">

                    <?php if (
                        empty(
                            $porSector
                        )
                    ): ?>

                        <div class="empty-state">

                            Sin sectores registrados.

                        </div>

                    <?php else: ?>

                        <div class="chart-container">

                            <canvas id="graficoSectores"></canvas>

                        </div>

                    <?php endif; ?>

                </div>

            </div>

        </div>


    </div>


    <!-- =====================================================
         RANKINGS
    ====================================================== -->

    <div class="row g-4 mb-4">


        <!-- CATEGORÍAS -->

        <div class="col-lg-4">

            <div class="report-card">

                <div class="report-card-header">

                    <h5>

                        <i class="bi bi-tags me-2"></i>

                        Categorías frecuentes

                    </h5>

                </div>


                <div class="report-card-body">


                    <?php if (
                        empty(
                            $porCategoria
                        )
                    ): ?>

                        <div class="empty-state">
                            Sin datos.
                        </div>


                    <?php else: ?>


                        <?php foreach (
                            $porCategoria
                            as $index => $fila
                        ): ?>

                            <div class="rank-item">

                                <span class="rank-position">

                                    <?= $index + 1 ?>

                                </span>

                                <span class="rank-name">

                                    <?= e(
                                        $fila[
                                            'categoria'
                                        ]
                                    ) ?>

                                </span>

                                <span class="rank-value">

                                    <?= (int)$fila[
                                        'cantidad'
                                    ] ?>

                                </span>

                            </div>

                        <?php endforeach; ?>


                    <?php endif; ?>


                </div>

            </div>

        </div>


        <!-- TÉCNICOS -->

        <div class="col-lg-4">

            <div class="report-card">

                <div class="report-card-header">

                    <h5>

                        <i class="bi bi-person-gear me-2"></i>

                        Actividad técnica

                    </h5>

                </div>


                <div class="report-card-body">


                    <?php if (
                        empty(
                            $porTecnico
                        )
                    ): ?>

                        <div class="empty-state">

                            <i class="bi bi-tools"></i>

                            Sin intervenciones en el período.

                        </div>


                    <?php else: ?>


                        <?php foreach (
                            $porTecnico
                            as $fila
                        ): ?>

                            <div class="tecnico-row">

                                <div class="tecnico-nombre">

                                    <?= e(
                                        $fila[
                                            'tecnico'
                                        ]
                                    ) ?>

                                </div>


                                <div class="tecnico-stats">

                                    <span>

                                        <i class="bi bi-tools"></i>

                                        <?= (int)$fila[
                                            'intervenciones'
                                        ] ?>
                                        intervenciones

                                    </span>


                                    <span>

                                        <i class="bi bi-ticket"></i>

                                        <?= (int)$fila[
                                            'solicitudes'
                                        ] ?>
                                        tickets

                                    </span>


                                    <?php if (
                                        (int)$fila[
                                            'pendientes'
                                        ] > 0
                                    ): ?>

                                        <span>

                                            <i class="bi bi-hourglass"></i>

                                            <?= (int)$fila[
                                                'pendientes'
                                            ] ?>
                                            pendientes

                                        </span>

                                    <?php endif; ?>

                                </div>

                            </div>

                        <?php endforeach; ?>


                    <?php endif; ?>


                </div>

            </div>

        </div>


        <!-- INTERVENCIONES -->

        <div class="col-lg-4">

            <div class="report-card">

                <div class="report-card-header">

                    <h5>

                        <i class="bi bi-tools me-2"></i>

                        Intervenciones

                    </h5>

                </div>


                <div class="report-card-body">

                    <div class="mejoras-summary">


                        <div class="mejora-stat">

                            <div class="mejora-label">
                                Total
                            </div>

                            <div class="mejora-value">

                                <?= (int)(
                                    $resumenIntervenciones[
                                        'total_intervenciones'
                                    ]
                                    ?? 0
                                ) ?>

                            </div>

                        </div>


                        <div class="mejora-stat">

                            <div class="mejora-label">
                                Tickets
                            </div>

                            <div class="mejora-value">

                                <?= (int)(
                                    $resumenIntervenciones[
                                        'solicitudes_intervenidas'
                                    ]
                                    ?? 0
                                ) ?>

                            </div>

                        </div>


                        <div class="mejora-stat">

                            <div class="mejora-label">
                                Pendientes
                            </div>

                            <div class="mejora-value">

                                <?= (int)(
                                    $resumenIntervenciones[
                                        'intervenciones_pendientes'
                                    ]
                                    ?? 0
                                ) ?>

                            </div>

                        </div>


                    </div>


                    <hr>


                    <div class="small text-muted">

                        Las intervenciones representan
                        trabajos técnicos registrados,
                        por lo que una misma solicitud
                        puede contener más de una intervención.

                    </div>

                </div>

            </div>

        </div>


    </div>


    <!-- =====================================================
         MEJORAS
    ====================================================== -->

    <div class="report-card mb-4">

        <div class="report-card-header">

            <h5>

                <i class="bi bi-lightbulb me-2"></i>

                Propuestas de mejora del período

            </h5>


            <a
                href="<?= url(
                    'admin/mejoras.php'
                ) ?>"
                class="small text-decoration-none no-print"
            >

                Gestionar mejoras

                <i class="bi bi-arrow-right"></i>

            </a>

        </div>


        <div class="report-card-body">

            <div class="mejoras-summary">


                <div class="mejora-stat">

                    <div class="mejora-label">
                        Total
                    </div>

                    <div class="mejora-value">

                        <?= (int)(
                            $resumenMejoras[
                                'total'
                            ]
                            ?? 0
                        ) ?>

                    </div>

                </div>


                <div class="mejora-stat">

                    <div class="mejora-label">
                        Propuestas
                    </div>

                    <div class="mejora-value">

                        <?= (int)(
                            $resumenMejoras[
                                'propuestas'
                            ]
                            ?? 0
                        ) ?>

                    </div>

                </div>


                <div class="mejora-stat">

                    <div class="mejora-label">
                        Pend. autorización
                    </div>

                    <div class="mejora-value">

                        <?= (int)(
                            $resumenMejoras[
                                'pendientes'
                            ]
                            ?? 0
                        ) ?>

                    </div>

                </div>


                <div class="mejora-stat">

                    <div class="mejora-label">
                        Aprobadas
                    </div>

                    <div class="mejora-value">

                        <?= (int)(
                            $resumenMejoras[
                                'aprobadas'
                            ]
                            ?? 0
                        ) ?>

                    </div>

                </div>


                <div class="mejora-stat">

                    <div class="mejora-label">
                        En ejecución
                    </div>

                    <div class="mejora-value">

                        <?= (int)(
                            $resumenMejoras[
                                'ejecucion'
                            ]
                            ?? 0
                        ) ?>

                    </div>

                </div>


                <div class="mejora-stat">

                    <div class="mejora-label">
                        Realizadas
                    </div>

                    <div class="mejora-value">

                        <?= (int)(
                            $resumenMejoras[
                                'realizadas'
                            ]
                            ?? 0
                        ) ?>

                    </div>

                </div>


            </div>


            <div class="mt-3">

                <strong class="text-sanjo">
                    Costo estimado total:
                </strong>

                <?= e(
                    formatoDinero(
                        $resumenMejoras[
                            'costo_estimado_total'
                        ]
                        ?? 0
                    )
                ) ?>

            </div>

        </div>

    </div>


    <!-- =====================================================
         RESUELTAS
    ====================================================== -->

    <div class="report-card mb-4">

        <div class="report-card-header">

            <h5>

                <i class="bi bi-check2-circle me-2"></i>

                Solicitudes resueltas

            </h5>

        </div>


        <?php if (
            empty(
                $ultimasResueltas
            )
        ): ?>

            <div class="empty-state">

                <i class="bi bi-inbox"></i>

                No hay solicitudes resueltas
                en el período seleccionado.

            </div>


        <?php else: ?>

            <div class="table-responsive">

                <table class="table">

                    <thead>

                        <tr>

                            <th>
                                Ticket
                            </th>

                            <th>
                                Solicitud
                            </th>

                            <th>
                                Área
                            </th>

                            <th>
                                Sector
                            </th>

                            <th>
                                Prioridad
                            </th>

                            <th>
                                Creada
                            </th>

                            <th>
                                Resuelta
                            </th>

                            <th>
                                Tiempo
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                        <?php foreach (
                            $ultimasResueltas
                            as $fila
                        ): ?>

                            <tr>

                                <td>

                                    <a
                                        href="<?= url(
                                            'ver_solicitud.php?id='
                                            .
                                            (int)$fila[
                                                'id_solicitud'
                                            ]
                                        ) ?>"
                                        class="ticket-link"
                                    >

                                        <?= e(
                                            numeroTicket(
                                                (int)$fila[
                                                    'id_solicitud'
                                                ]
                                            )
                                        ) ?>

                                    </a>

                                </td>


                                <td>

                                    <div class="ticket-title">

                                        <?= e(
                                            $fila[
                                                'titulo'
                                            ]
                                        ) ?>

                                    </div>

                                    <div class="meta-small">

                                        <?= e(
                                            $fila[
                                                'solicitante'
                                            ]
                                        ) ?>

                                    </div>

                                </td>


                                <td>

                                    <?= e(
                                        nombreTipo(
                                            $fila[
                                                'tipo'
                                            ]
                                        )
                                    ) ?>

                                </td>


                                <td>

                                    <?= e(
                                        $fila[
                                            'sector'
                                        ]
                                        ?? '-'
                                    ) ?>

                                </td>


                                <td>

                                    <span
                                        class="badge <?= e(
                                            clasePrioridad(
                                                $fila[
                                                    'prioridad'
                                                ]
                                            )
                                        ) ?>"
                                    >

                                        <?= e(
                                            $fila[
                                                'prioridad'
                                            ]
                                        ) ?>

                                    </span>

                                </td>


                                <td>

                                    <?= e(
                                        fechaCorta(
                                            $fila[
                                                'fecha_creacion'
                                            ]
                                        )
                                    ) ?>

                                </td>


                                <td>

                                    <?= e(
                                        fechaCorta(
                                            $fila[
                                                'fecha_resolucion'
                                            ]
                                        )
                                    ) ?>

                                </td>


                                <td>

                                    <?= e(
                                        tiempoPromedioReporte(
                                            (float)$fila[
                                                'horas_resolucion'
                                            ]
                                        )
                                    ) ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>


                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </div>


    <!-- =====================================================
         PENDIENTES
    ====================================================== -->

    <div class="report-card">

        <div class="report-card-header">

            <h5>

                <i class="bi bi-hourglass-split me-2"></i>

                Solicitudes pendientes

            </h5>


            <span class="badge bg-warning text-dark">

                <?= count(
                    $pendientesDetalle
                ) ?>

            </span>

        </div>


        <div class="report-card-body">


            <?php if (
                empty(
                    $pendientesDetalle
                )
            ): ?>

                <div class="empty-state">

                    <i class="bi bi-check-circle"></i>

                    No hay solicitudes pendientes
                    dentro del período.

                </div>


            <?php else: ?>


                <?php foreach (
                    $pendientesDetalle
                    as $fila
                ): ?>

                    <div class="pendiente-row">

                        <div
                            class="d-flex
                                   justify-content-between
                                   gap-3"
                        >

                            <div>

                                <a
                                    href="<?= url(
                                        'ver_solicitud.php?id='
                                        .
                                        (int)$fila[
                                            'id_solicitud'
                                        ]
                                    ) ?>"
                                    class="ticket-link"
                                >

                                    <?= e(
                                        numeroTicket(
                                            (int)$fila[
                                                'id_solicitud'
                                            ]
                                        )
                                    ) ?>

                                </a>


                                <div class="pendiente-titulo">

                                    <?= e(
                                        $fila[
                                            'titulo'
                                        ]
                                    ) ?>

                                </div>


                                <div class="meta-small">

                                    <?= e(
                                        $fila[
                                            'solicitante'
                                        ]
                                    ) ?>

                                    <?php if (
                                        !empty(
                                            $fila[
                                                'sector'
                                            ]
                                        )
                                    ): ?>

                                        ·

                                        <?= e(
                                            $fila[
                                                'sector'
                                            ]
                                        ) ?>

                                    <?php endif; ?>

                                </div>

                            </div>


                            <span
                                class="badge <?= e(
                                    clasePrioridad(
                                        $fila[
                                            'prioridad'
                                        ]
                                    )
                                ) ?>"
                            >

                                <?= e(
                                    $fila[
                                        'prioridad'
                                    ]
                                ) ?>

                            </span>

                        </div>


                        <?php if (
                            !empty(
                                $fila[
                                    'motivo_pendiente'
                                ]
                            )
                        ): ?>

                            <div class="pendiente-motivo">

                                <strong>
                                    Motivo pendiente:
                                </strong>

                                <?= e(
                                    $fila[
                                        'motivo_pendiente'
                                    ]
                                ) ?>

                            </div>

                        <?php endif; ?>

                    </div>

                <?php endforeach; ?>


            <?php endif; ?>


        </div>

    </div>


</div>


<script>

// ============================================================
// DATOS
// ============================================================

const labelsEstados =
    <?= json_encode(
        $labelsEstados,
        JSON_UNESCAPED_UNICODE
    ) ?>;

const datosEstados =
    <?= json_encode(
        $datosEstados
    ) ?>;


const labelsPrioridad =
    <?= json_encode(
        $labelsPrioridad,
        JSON_UNESCAPED_UNICODE
    ) ?>;

const datosPrioridad =
    <?= json_encode(
        $datosPrioridad
    ) ?>;


const labelsEvolucion =
    <?= json_encode(
        $labelsEvolucion,
        JSON_UNESCAPED_UNICODE
    ) ?>;

const datosEvolucion =
    <?= json_encode(
        $datosEvolucion
    ) ?>;


const labelsSectores =
    <?= json_encode(
        $labelsSectores,
        JSON_UNESCAPED_UNICODE
    ) ?>;

const datosSectores =
    <?= json_encode(
        $datosSectores
    ) ?>;


// ============================================================
// CONFIGURACIÓN GENERAL
// ============================================================

Chart.defaults.font.family =
    'Arial, sans-serif';

Chart.defaults.color =
    '#666666';


// ============================================================
// EVOLUCIÓN
// ============================================================

const evolucionCanvas =
    document.getElementById(
        'graficoEvolucion'
    );


if (evolucionCanvas) {

    new Chart(
        evolucionCanvas,
        {
            type: 'line',

            data: {

                labels:
                    labelsEvolucion,

                datasets: [
                    {
                        label:
                            'Solicitudes',

                        data:
                            datosEvolucion,

                        borderColor:
                            '#B12626',

                        backgroundColor:
                            'rgba(177,38,38,.10)',

                        fill:
                            true,

                        tension:
                            0.25,

                        pointBackgroundColor:
                            '#760000',

                        pointRadius:
                            3
                    }
                ]

            },

            options: {

                responsive:
                    true,

                maintainAspectRatio:
                    false,

                plugins: {

                    legend: {

                        display:
                            false
                    }

                },

                scales: {

                    y: {

                        beginAtZero:
                            true,

                        ticks: {

                            precision:
                                0
                        }

                    }

                }

            }

        }
    );

}


// ============================================================
// ESTADOS
// ============================================================

const estadosCanvas =
    document.getElementById(
        'graficoEstados'
    );


if (estadosCanvas) {

    new Chart(
        estadosCanvas,
        {
            type: 'doughnut',

            data: {

                labels:
                    labelsEstados,

                datasets: [
                    {
                        data:
                            datosEstados,

                        backgroundColor: [
                            '#0d6efd',
                            '#6f42c1',
                            '#ffc107',
                            '#fd7e14',
                            '#198754',
                            '#20c997',
                            '#6c757d'
                        ],

                        borderWidth:
                            2,

                        borderColor:
                            '#FFFFFF'
                    }
                ]

            },

            options: {

                responsive:
                    true,

                maintainAspectRatio:
                    false,

                plugins: {

                    legend: {

                        position:
                            'bottom',

                        labels: {

                            boxWidth:
                                12,

                            padding:
                                12
                        }

                    }

                }

            }

        }
    );

}


// ============================================================
// PRIORIDAD
// ============================================================

const prioridadCanvas =
    document.getElementById(
        'graficoPrioridad'
    );


if (prioridadCanvas) {

    new Chart(
        prioridadCanvas,
        {
            type: 'bar',

            data: {

                labels:
                    labelsPrioridad,

                datasets: [
                    {
                        label:
                            'Solicitudes',

                        data:
                            datosPrioridad,

                        backgroundColor: [
                            '#760000',
                            '#B12626',
                            '#d8a700',
                            '#6c757d'
                        ],

                        borderRadius:
                            6
                    }
                ]

            },

            options: {

                responsive:
                    true,

                maintainAspectRatio:
                    false,

                plugins: {

                    legend: {

                        display:
                            false
                    }

                },

                scales: {

                    y: {

                        beginAtZero:
                            true,

                        ticks: {

                            precision:
                                0
                        }

                    }

                }

            }

        }
    );

}


// ============================================================
// SECTORES
// ============================================================

const sectoresCanvas =
    document.getElementById(
        'graficoSectores'
    );


if (sectoresCanvas) {

    new Chart(
        sectoresCanvas,
        {
            type: 'bar',

            data: {

                labels:
                    labelsSectores,

                datasets: [
                    {
                        label:
                            'Solicitudes',

                        data:
                            datosSectores,

                        backgroundColor:
                            '#B12626',

                        borderRadius:
                            6
                    }
                ]

            },

            options: {

                indexAxis:
                    'y',

                responsive:
                    true,

                maintainAspectRatio:
                    false,

                plugins: {

                    legend: {

                        display:
                            false
                    }

                },

                scales: {

                    x: {

                        beginAtZero:
                            true,

                        ticks: {

                            precision:
                                0
                        }

                    }

                }

            }

        }
    );

}

</script>


<?php

require_once __DIR__
    . '/../includes/footer.php';

?>