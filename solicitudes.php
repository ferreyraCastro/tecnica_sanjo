<?php
// ============================================================
// COLEGIO SAN JOSÉ
// SISTEMA DE GESTIÓN TÉCNICA
//
// Archivo:
// /tecnica/solicitudes.php
//
// Ubicación física:
// C:\xampp\htdocs\tecnica\solicitudes.php
// ============================================================

declare(strict_types=1);


// ============================================================
// INCLUDES
// ============================================================

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/funciones.php';
require_once __DIR__ . '/includes/auth.php';


// ============================================================
// REQUERIR LOGIN
// ============================================================

requerirLogin();


// ============================================================
// VERIFICAR USUARIO ACTIVO
// ============================================================

if (!verificarUsuarioActivo($conexion)) {

    $_SESSION['mensaje_login'] =
        'Tu sesión finalizó o tu cuenta se encuentra inactiva.';

    header(
        'Location: ' . url('login.php')
    );

    exit;
}


// ============================================================
// USUARIO ACTUAL
// ============================================================

$idUsuario = (int) usuarioId();

$rolActual =
    $_SESSION['usuario_rol']
    ?? 'Docente';


// ============================================================
// ROLES VÁLIDOS
// ============================================================

if (
    !in_array(
        $rolActual,
        [
            'Docente',
            'Tecnico',
            'Administrador'
        ],
        true
    )
) {

    $rolActual = 'Docente';
}


// ============================================================
// CONFIGURACIÓN
// ============================================================

$estadosPermitidos = [
    'Nueva',
    'Asignada',
    'En proceso',
    'Pendiente',
    'Resuelta',
    'Cerrada',
    'Cancelada'
];


$prioridadesPermitidas = [
    'Baja',
    'Normal',
    'Alta',
    'Urgente'
];


$tiposPermitidos = [
    'Informatica',
    'Mantenimiento'
];


// ============================================================
// FILTROS
// ============================================================

$buscar =
    trim(
        $_GET['buscar']
        ?? ''
    );


$estadoFiltro =
    trim(
        $_GET['estado']
        ?? ''
    );


$prioridadFiltro =
    trim(
        $_GET['prioridad']
        ?? ''
    );


$tipoFiltro =
    trim(
        $_GET['tipo']
        ?? ''
    );


// ============================================================
// VALIDAR FILTROS
// ============================================================

if (
    $estadoFiltro !== ''
    &&
    !in_array(
        $estadoFiltro,
        $estadosPermitidos,
        true
    )
) {

    $estadoFiltro = '';
}


if (
    $prioridadFiltro !== ''
    &&
    !in_array(
        $prioridadFiltro,
        $prioridadesPermitidas,
        true
    )
) {

    $prioridadFiltro = '';
}


if (
    $tipoFiltro !== ''
    &&
    !in_array(
        $tipoFiltro,
        $tiposPermitidos,
        true
    )
) {

    $tipoFiltro = '';
}


// ============================================================
// FUNCIÓN LOCAL PARA FECHAS
// ============================================================

function fechaSolicitud(
    ?string $fecha
): string {

    if (
        !$fecha
        ||
        $fecha === '0000-00-00 00:00:00'
    ) {

        return '-';
    }


    $timestamp =
        strtotime(
            $fecha
        );


    if (!$timestamp) {

        return '-';
    }


    return date(
        'd/m/Y H:i',
        $timestamp
    );
}


// ============================================================
// FUNCIÓN LOCAL PARA TIPO
// ============================================================

function textoTipoSolicitud(
    string $tipo
): string {

    return match ($tipo) {

        'Informatica'
            => 'Informática',

        'Mantenimiento'
            => 'Mantenimiento',

        default
            => $tipo
    };
}


// ============================================================
// CONSTRUIR CONDICIONES
// ============================================================

$condiciones = [];

$parametros = [];


// ============================================================
// SEGURIDAD SEGÚN ROL
// ============================================================

// ------------------------------------------------------------
// DOCENTE
// Solo puede ver solicitudes creadas por él.
// ------------------------------------------------------------

if ($rolActual === 'Docente') {

    $condiciones[] =
        's.id_usuario = ?';

    $parametros[] =
        $idUsuario;
}


// ------------------------------------------------------------
// TÉCNICO
// Solo ve solicitudes que están o estuvieron asignadas a él.
// ------------------------------------------------------------

elseif ($rolActual === 'Tecnico') {

    $condiciones[] = "
        EXISTS (
            SELECT 1

            FROM solicitudes_asignaciones sat

            WHERE
                sat.id_solicitud =
                s.id_solicitud

            AND
                sat.id_tecnico = ?
        )
    ";

    $parametros[] =
        $idUsuario;
}


// ------------------------------------------------------------
// ADMINISTRADOR
// No agregamos condición.
// Ve todas.
// ------------------------------------------------------------


// ============================================================
// BUSCADOR
// ============================================================

if ($buscar !== '') {

    $condiciones[] = "
        (
            s.titulo LIKE ?

            OR

            s.descripcion LIKE ?

            OR

            sec.nombre LIKE ?

            OR

            cat.nombre LIKE ?

            OR

            u.nombre LIKE ?

            OR

            u.apellido LIKE ?

            OR

            u.correo LIKE ?

            OR

            CAST(
                s.id_solicitud
                AS CHAR
            ) LIKE ?
        )
    ";


    $buscarSql =
        '%'
        .
        $buscar
        .
        '%';


    for ($i = 0; $i < 8; $i++) {

        $parametros[] =
            $buscarSql;
    }
}


// ============================================================
// ESTADO
// ============================================================

if ($estadoFiltro !== '') {

    $condiciones[] =
        's.estado = ?';

    $parametros[] =
        $estadoFiltro;
}


// ============================================================
// PRIORIDAD
// ============================================================

if ($prioridadFiltro !== '') {

    $condiciones[] =
        's.prioridad = ?';

    $parametros[] =
        $prioridadFiltro;
}


// ============================================================
// ÁREA
// ============================================================

if ($tipoFiltro !== '') {

    $condiciones[] =
        's.tipo = ?';

    $parametros[] =
        $tipoFiltro;
}


// ============================================================
// WHERE
// ============================================================

$where = '';


if (!empty($condiciones)) {

    $where =
        'WHERE '
        .
        implode(
            ' AND ',
            $condiciones
        );
}


// ============================================================
// CONDICIÓN PARA ESTADÍSTICAS SEGÚN ROL
// ============================================================

$whereStats = '';

$paramsStats = [];


if ($rolActual === 'Docente') {

    $whereStats =
        'WHERE s.id_usuario = ?';

    $paramsStats[] =
        $idUsuario;

} elseif ($rolActual === 'Tecnico') {

    $whereStats = "
        WHERE EXISTS (
            SELECT 1

            FROM solicitudes_asignaciones sat

            WHERE
                sat.id_solicitud =
                s.id_solicitud

            AND
                sat.id_tecnico = ?
        )
    ";

    $paramsStats[] =
        $idUsuario;
}


// ============================================================
// ESTADÍSTICAS
// ============================================================

$sqlStats = "
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
        ) AS proceso,

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

                WHEN
                    s.prioridad = 'Urgente'

                AND
                    s.estado NOT IN (
                        'Resuelta',
                        'Cerrada',
                        'Cancelada'
                    )

                THEN 1

                ELSE 0

            END
        ) AS urgentes

    FROM solicitudes s

    {$whereStats}
";


$stmtStats =
    $conexion->prepare(
        $sqlStats
    );


$stmtStats->execute(
    $paramsStats
);


$statsDB =
    $stmtStats->fetch(
        PDO::FETCH_ASSOC
    );


$stats = [

    'total' =>
        (int)(
            $statsDB['total']
            ?? 0
        ),

    'nuevas' =>
        (int)(
            $statsDB['nuevas']
            ?? 0
        ),

    'asignadas' =>
        (int)(
            $statsDB['asignadas']
            ?? 0
        ),

    'proceso' =>
        (int)(
            $statsDB['proceso']
            ?? 0
        ),

    'pendientes' =>
        (int)(
            $statsDB['pendientes']
            ?? 0
        ),

    'resueltas' =>
        (int)(
            $statsDB['resueltas']
            ?? 0
        ),

    'urgentes' =>
        (int)(
            $statsDB['urgentes']
            ?? 0
        )

];


// ============================================================
// PAGINACIÓN
// ============================================================

$pagina =
    max(
        1,
        (int)(
            $_GET['pagina']
            ?? 1
        )
    );


$porPagina = 15;


// ============================================================
// CONTAR RESULTADOS
// ============================================================

$sqlCantidad = "
    SELECT
        COUNT(*)

    FROM solicitudes s

    INNER JOIN usuarios u
        ON u.id_usuario =
           s.id_usuario

    LEFT JOIN sectores sec
        ON sec.id_sector =
           s.id_sector

    LEFT JOIN categorias cat
        ON cat.id_categoria =
           s.id_categoria

    {$where}
";


$stmtCantidad =
    $conexion->prepare(
        $sqlCantidad
    );


$stmtCantidad->execute(
    $parametros
);


$totalRegistros =
    (int)$stmtCantidad
        ->fetchColumn();


$totalPaginas =
    max(
        1,
        (int)ceil(
            $totalRegistros
            /
            $porPagina
        )
    );


if ($pagina > $totalPaginas) {

    $pagina =
        $totalPaginas;
}


$offset =
    ($pagina - 1)
    *
    $porPagina;


// ============================================================
// CONSULTA PRINCIPAL
//
// IMPORTANTE:
//
// solicitudes_asignaciones:
//
// id_asignacion
// id_solicitud
// id_tecnico
// asignado_por
// fecha_asignacion
// activo
//
// NO utilizamos:
// sa.fecha_fin
// sa.observaciones
// ============================================================

$sqlSolicitudes = "
    SELECT

        s.id_solicitud,
        s.id_usuario,

        s.tipo,

        s.titulo,

        s.descripcion,

        s.prioridad,

        s.estado,

        s.motivo_pendiente,

        s.fecha_creacion,

        s.fecha_actualizacion,

        s.fecha_resolucion,

        CONCAT(
            u.nombre,
            ' ',
            u.apellido
        ) AS solicitante,

        u.correo
            AS correo_solicitante,

        sec.nombre
            AS sector,

        cat.nombre
            AS categoria,

        (
            SELECT COUNT(*)

            FROM solicitud_imagenes si

            WHERE
                si.id_solicitud =
                s.id_solicitud
        ) AS cantidad_imagenes,

        (
            SELECT COUNT(*)

            FROM comentarios c

            WHERE
                c.id_solicitud =
                s.id_solicitud
        ) AS cantidad_comentarios,

        (
            SELECT COUNT(*)

            FROM intervenciones i

            WHERE
                i.id_solicitud =
                s.id_solicitud
        ) AS cantidad_intervenciones,

        (
            SELECT MAX(
                i2.fecha_intervencion
            )

            FROM intervenciones i2

            WHERE
                i2.id_solicitud =
                s.id_solicitud
        ) AS ultima_intervencion,

        (
            SELECT sa.id_tecnico

            FROM solicitudes_asignaciones sa

            WHERE
                sa.id_solicitud =
                s.id_solicitud

            AND
                sa.activo = 1

            ORDER BY
                sa.id_asignacion DESC

            LIMIT 1

        ) AS id_tecnico_actual,

        (
            SELECT CONCAT(
                ut.nombre,
                ' ',
                ut.apellido
            )

            FROM solicitudes_asignaciones sa

            INNER JOIN usuarios ut

                ON ut.id_usuario =
                   sa.id_tecnico

            WHERE
                sa.id_solicitud =
                s.id_solicitud

            AND
                sa.activo = 1

            ORDER BY
                sa.id_asignacion DESC

            LIMIT 1

        ) AS tecnico_actual,

        (
            SELECT sa.fecha_asignacion

            FROM solicitudes_asignaciones sa

            WHERE
                sa.id_solicitud =
                s.id_solicitud

            AND
                sa.activo = 1

            ORDER BY
                sa.id_asignacion DESC

            LIMIT 1

        ) AS fecha_asignacion,

        (
            SELECT COUNT(*)

            FROM solicitudes_asignaciones sax

            WHERE
                sax.id_solicitud =
                s.id_solicitud

            AND
                sax.activo = 1

        ) AS tiene_asignacion

    FROM solicitudes s

    INNER JOIN usuarios u

        ON u.id_usuario =
           s.id_usuario

    LEFT JOIN sectores sec

        ON sec.id_sector =
           s.id_sector

    LEFT JOIN categorias cat

        ON cat.id_categoria =
           s.id_categoria

    {$where}

    ORDER BY

        CASE

            WHEN
                s.prioridad = 'Urgente'

            AND
                s.estado NOT IN (
                    'Resuelta',
                    'Cerrada',
                    'Cancelada'
                )

            THEN 1

            ELSE 2

        END,

        CASE s.estado

            WHEN 'Nueva'
                THEN 1

            WHEN 'Asignada'
                THEN 2

            WHEN 'En proceso'
                THEN 3

            WHEN 'Pendiente'
                THEN 4

            WHEN 'Resuelta'
                THEN 5

            WHEN 'Cerrada'
                THEN 6

            WHEN 'Cancelada'
                THEN 7

            ELSE 8

        END,

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

        COALESCE(
            s.fecha_actualizacion,
            s.fecha_creacion
        ) DESC

    LIMIT {$porPagina}

    OFFSET {$offset}
";


$stmtSolicitudes =
    $conexion->prepare(
        $sqlSolicitudes
    );


$stmtSolicitudes->execute(
    $parametros
);


$solicitudes =
    $stmtSolicitudes->fetchAll(
        PDO::FETCH_ASSOC
    );


// ============================================================
// FUNCIÓN PAGINACIÓN
// ============================================================

function urlPaginaSolicitudes(
    int $pagina
): string {

    $query =
        $_GET;


    $query['pagina'] =
        $pagina;


    return url(
        'solicitudes.php?'
        .
        http_build_query(
            $query
        )
    );
}


// ============================================================
// FLASH
// ============================================================

$flash =
    obtenerFlash();


// ============================================================
// HEADER
//
// IMPORTANTE:
// Estamos en /tecnica/
//
// Por eso NO usamos ../includes/
// ============================================================

require_once __DIR__ . '/includes/header.php';

?>


<style>

/* ============================================================
   CONTENEDOR
============================================================ */

.solicitudes-wrapper {

    max-width: 1550px;

    margin: 0 auto;

    padding:
        5px 12px
        50px;

}


/* ============================================================
   HERO
============================================================ */

.solicitudes-hero {

    position: relative;

    overflow: hidden;

    padding: 30px;

    margin-bottom: 24px;

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


.solicitudes-hero::after {

    content: "";

    position: absolute;

    width: 320px;
    height: 320px;

    right: -120px;
    top: -165px;

    border-radius: 50%;

    background:
        rgba(255,255,255,.07);

}


.hero-content,
.hero-actions {

    position: relative;

    z-index: 2;

}


.solicitudes-hero h1 {

    margin: 0 0 7px;

    font-size: 29px;
    font-weight: 800;

}


.solicitudes-hero p {

    max-width: 760px;

    margin: 0;

    color:
        rgba(255,255,255,.82);

}


.hero-role {

    display: inline-flex;

    align-items: center;

    gap: 6px;

    margin-top: 12px;

    padding:
        6px 11px;

    border-radius: 30px;

    background:
        rgba(255,255,255,.14);

    font-size: 10px;
    font-weight: 700;

}


.hero-actions {

    display: flex;

    justify-content: flex-end;

    flex-wrap: wrap;

    gap: 8px;

}


.btn-hero {

    display: inline-flex;

    align-items: center;
    justify-content: center;

    gap: 7px;

    min-height: 42px;

    padding:
        9px 15px;

    border-radius: 10px;

    text-decoration: none;

    font-weight: 700;

}


.btn-hero-white {

    color: #760000;

    background: #FFFFFF;

}


.btn-hero-white:hover {

    color: #B12626;

    background: #F5F5F5;

}


.btn-hero-outline {

    color: #FFFFFF;

    border:
        1px solid
        rgba(255,255,255,.28);

    background:
        rgba(255,255,255,.10);

}


.btn-hero-outline:hover {

    color: #FFFFFF;

    background:
        rgba(255,255,255,.18);

}


/* ============================================================
   ESTADÍSTICAS
============================================================ */

.stat-card {

    height: 100%;

    padding: 17px;

    border:
        1px solid #ECECEC;

    border-radius: 16px;

    background: #FFFFFF;

    box-shadow:
        0 4px 15px
        rgba(0,0,0,.04);

}


.stat-icon {

    width: 40px;
    height: 40px;

    display: flex;

    align-items: center;
    justify-content: center;

    margin-bottom: 9px;

    border-radius: 10px;

    font-size: 17px;

}


.stat-number {

    color: #333333;

    font-size: 26px;
    line-height: 1;

    font-weight: 800;

}


.stat-label {

    margin-top: 5px;

    color: #777777;

    font-size: 10px;
    font-weight: 700;

}


.stat-total {

    color: #760000;
    background: #F5EAEA;

}


.stat-new {

    color: #0D6EFD;
    background: #EAF2FF;

}


.stat-process {

    color: #8A6700;
    background: #FFF4D4;

}


.stat-pending {

    color: #B12626;
    background: #FFE9E9;

}


.stat-resolved {

    color: #198754;
    background: #E6F5EB;

}


.stat-urgent {

    color: #FFFFFF;
    background: #760000;

}


/* ============================================================
   FILTROS
============================================================ */

.filters-card {

    padding: 20px;

    margin-bottom: 24px;

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

    font-size: 11px;
    font-weight: 800;

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

    color: #FFFFFF;

    background: #B12626;

    font-weight: 700;

}


.btn-filter:hover {

    color: #FFFFFF;

    background: #760000;

}


/* ============================================================
   CARD
============================================================ */

.main-card {

    overflow: hidden;

    border:
        1px solid #ECECEC;

    border-radius: 18px;

    background: #FFFFFF;

    box-shadow:
        0 5px 19px
        rgba(0,0,0,.05);

}


.main-card-header {

    display: flex;

    align-items: center;
    justify-content: space-between;

    gap: 12px;

    padding:
        18px 20px;

    border-bottom:
        1px solid #EEEEEE;

}


.main-card-header h5 {

    margin: 0;

    color: #760000;

    font-size: 16px;
    font-weight: 800;

}


.main-card-body {

    padding:
        0 20px;

}


/* ============================================================
   TICKET
============================================================ */

.ticket {

    position: relative;

    display: grid;

    grid-template-columns:
        minmax(0, 1fr)
        auto;

    gap: 22px;

    padding:
        20px 0;

    border-bottom:
        1px solid #EEEEEE;

}


.ticket:last-child {

    border-bottom: 0;

}


.ticket-priority {

    position: absolute;

    left: -20px;

    top: 20px;
    bottom: 20px;

    width: 4px;

    border-radius:
        0 4px 4px 0;

}


.ticket-priority-Urgente {

    background: #760000;

}


.ticket-priority-Alta {

    background: #B12626;

}


.ticket-priority-Normal {

    background: #D2A100;

}


.ticket-priority-Baja {

    background: #777777;

}


/* ============================================================
   TICKET INFO
============================================================ */

.ticket-number {

    color: #999999;

    font-size: 10px;
    font-weight: 800;

}


.ticket-title {

    display: inline-block;

    margin-top: 3px;

    color: #333333;

    font-size: 15px;
    font-weight: 800;

    text-decoration: none;

}


.ticket-title:hover {

    color: #B12626;

}


.ticket-description {

    max-width: 950px;

    margin-top: 6px;

    color: #717171;

    font-size: 11px;
    line-height: 1.55;

    display: -webkit-box;

    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;

    overflow: hidden;

}


.ticket-meta {

    display: flex;

    flex-wrap: wrap;

    gap:
        6px 14px;

    margin-top: 10px;

    color: #818181;

    font-size: 10px;

}


.ticket-meta span {

    display: inline-flex;

    align-items: center;

    gap: 4px;

}


.ticket-meta i {

    color: #B12626;

}


/* ============================================================
   CONTADORES
============================================================ */

.ticket-counters {

    display: flex;

    flex-wrap: wrap;

    gap: 7px;

    margin-top: 10px;

}


.ticket-counter {

    display: inline-flex;

    align-items: center;

    gap: 5px;

    padding:
        5px 8px;

    border-radius: 7px;

    color: #666666;

    background: #F5F5F5;

    font-size: 9px;

}


/* ============================================================
   ASIGNACIÓN
============================================================ */

.assignment-box {

    display: inline-flex;

    flex-wrap: wrap;

    align-items: center;

    gap: 5px;

    margin-top: 10px;

    padding:
        7px 10px;

    border-radius: 8px;

    color: #606060;

    background: #F8F8F8;

    font-size: 9px;

}


.assignment-box i {

    color: #B12626;

}


/* ============================================================
   PENDIENTE
============================================================ */

.pending-box {

    max-width: 900px;

    margin-top: 10px;

    padding:
        9px 11px;

    border-left:
        3px solid #D3A000;

    border-radius: 7px;

    color: #685500;

    background: #FFF8DE;

    font-size: 10px;

}


/* ============================================================
   LATERAL
============================================================ */

.ticket-side {

    min-width: 180px;

    display: flex;

    flex-direction: column;

    align-items: flex-end;

}


.ticket-badges {

    display: flex;

    flex-wrap: wrap;

    justify-content: flex-end;

    gap: 5px;

}


.ticket-actions {

    display: flex;

    flex-wrap: wrap;

    justify-content: flex-end;

    gap: 6px;

    margin-top: 15px;

}


.btn-ticket {

    display: inline-flex;

    align-items: center;
    justify-content: center;

    gap: 5px;

    min-height: 37px;

    padding:
        7px 10px;

    border-radius: 8px;

    text-decoration: none;

    font-size: 10px;
    font-weight: 700;

}


.btn-view {

    color: #760000;

    background: #FFF0F0;

}


.btn-view:hover {

    color: #FFFFFF;

    background: #B12626;

}


.btn-tech {

    color: #FFFFFF;

    background:
        linear-gradient(
            135deg,
            #760000,
            #B12626
        );

}


.btn-tech:hover {

    color: #FFFFFF;

    background: #760000;

}


.btn-finish {

    color: #FFFFFF;

    background: #198754;

}


.btn-finish:hover {

    color: #FFFFFF;

    background: #146C43;

}


/* ============================================================
   VACÍO
============================================================ */

.empty-state {

    padding:
        55px 20px;

    text-align: center;

    color: #8F8F8F;

}


.empty-state i {

    display: block;

    margin-bottom: 10px;

    color: #CCCCCC;

    font-size: 45px;

}


.empty-state h6 {

    color: #555555;

    font-weight: 800;

}


/* ============================================================
   PAGINACIÓN
============================================================ */

.pagination-wrapper {

    display: flex;

    align-items: center;
    justify-content: space-between;

    gap: 15px;

    padding: 15px 20px;

    border-top:
        1px solid #EEEEEE;

}


.page-link {

    color: #760000;

}


.page-link:hover {

    color: #B12626;

}


.page-item.active
.page-link {

    color: #FFFFFF;

    background: #B12626;

    border-color: #B12626;

}


/* ============================================================
   RESPONSIVE
============================================================ */

@media
(max-width: 991px) {

    .ticket {

        grid-template-columns: 1fr;

    }


    .ticket-side {

        min-width: 0;

        align-items: flex-start;

    }


    .ticket-badges,
    .ticket-actions {

        justify-content: flex-start;

    }

}


@media
(max-width: 767px) {

    .solicitudes-hero {

        padding:
            22px 20px;

    }


    .solicitudes-hero h1 {

        font-size: 23px;

    }


    .hero-actions {

        justify-content: flex-start;

        flex-direction: column;

        margin-top: 18px;

    }


    .btn-hero {

        width: 100%;

    }


    .main-card-body {

        padding:
            0 17px;

    }


    .ticket-priority {

        left: -17px;

    }


    .ticket-actions {

        width: 100%;

    }


    .btn-ticket {

        flex: 1;

    }


    .pagination-wrapper {

        align-items: flex-start;

        flex-direction: column;

    }

}

</style>


<div class="solicitudes-wrapper">


    <!-- =====================================================
         HERO
    ====================================================== -->

    <section class="solicitudes-hero">

        <div class="row align-items-center">


            <div class="col-lg-8">


                <div class="hero-content">


                    <h1>

                        <i class="bi bi-ticket-detailed me-1"></i>

                        Solicitudes

                    </h1>


                    <p>

                        <?php if (
                            $rolActual === 'Administrador'
                        ): ?>

                            Consultá y gestioná todas las solicitudes
                            de Informática y Mantenimiento del colegio.

                        <?php elseif (
                            $rolActual === 'Tecnico'
                        ): ?>

                            Consultá las solicitudes que fueron
                            asignadas a tu usuario técnico.

                        <?php else: ?>

                            Consultá el estado y seguimiento
                            de las solicitudes que registraste.

                        <?php endif; ?>

                    </p>


                    <div class="hero-role">

                        <?php if (
                            $rolActual === 'Administrador'
                        ): ?>

                            <i class="bi bi-shield-check"></i>

                            Administrador

                        <?php elseif (
                            $rolActual === 'Tecnico'
                        ): ?>

                            <i class="bi bi-person-gear"></i>

                            Técnico

                        <?php else: ?>

                            <i class="bi bi-person"></i>

                            Docente

                        <?php endif; ?>

                    </div>


                </div>


            </div>


            <div class="col-lg-4">


                <div class="hero-actions">


                    <?php if (
                        $rolActual === 'Docente'
                    ): ?>

                        <a
                            href="<?= url(
                                'nueva_solicitud.php'
                            ) ?>"
                            class="btn-hero btn-hero-outline"
                        >

                            <i class="bi bi-plus-circle"></i>

                            Nueva solicitud

                        </a>

                    <?php endif; ?>


                    <?php if (
                        $rolActual === 'Tecnico'
                    ): ?>

                        <a
                            href="<?= url(
                                'tecnico/dashboard.php'
                            ) ?>"
                            class="btn-hero btn-hero-white"
                        >

                            <i class="bi bi-tools"></i>

                            Panel técnico

                        </a>

                    <?php else: ?>

                        <a
                            href="<?= url(
                                'dashboard.php'
                            ) ?>"
                            class="btn-hero btn-hero-white"
                        >

                            <i class="bi bi-house"></i>

                            Inicio

                        </a>

                    <?php endif; ?>


                </div>


            </div>


        </div>

    </section>


    <!-- =====================================================
         MENSAJES
    ====================================================== -->

    <?php if ($flash): ?>

        <div
            class="alert alert-<?=
                $flash['tipo'] === 'success'
                    ? 'success'
                    : (
                        $flash['tipo'] === 'error'
                            ? 'danger'
                            : 'info'
                    )
            ?> alert-dismissible fade show"
            data-auto-close="5000"
        >

            <?= e(
                $flash['mensaje']
            ) ?>


            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
            ></button>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         ESTADÍSTICAS
    ====================================================== -->

    <div class="row g-3 mb-4">


        <!-- TOTAL -->

        <div class="col-6 col-md-4 col-xl-2">

            <div class="stat-card">

                <div class="stat-icon stat-total">

                    <i class="bi bi-ticket-detailed"></i>

                </div>


                <div class="stat-number">

                    <?= $stats['total'] ?>

                </div>


                <div class="stat-label">

                    Total

                </div>

            </div>

        </div>


        <!-- NUEVAS -->

        <div class="col-6 col-md-4 col-xl-2">

            <div class="stat-card">

                <div class="stat-icon stat-new">

                    <i class="bi bi-plus-circle"></i>

                </div>


                <div class="stat-number">

                    <?= $stats['nuevas'] ?>

                </div>


                <div class="stat-label">

                    Nuevas

                </div>

            </div>

        </div>


        <!-- PROCESO -->

        <div class="col-6 col-md-4 col-xl-2">

            <div class="stat-card">

                <div class="stat-icon stat-process">

                    <i class="bi bi-arrow-repeat"></i>

                </div>


                <div class="stat-number">

                    <?= $stats['proceso'] ?>

                </div>


                <div class="stat-label">

                    En proceso

                </div>

            </div>

        </div>


        <!-- PENDIENTES -->

        <div class="col-6 col-md-4 col-xl-2">

            <div class="stat-card">

                <div class="stat-icon stat-pending">

                    <i class="bi bi-hourglass-split"></i>

                </div>


                <div class="stat-number">

                    <?= $stats['pendientes'] ?>

                </div>


                <div class="stat-label">

                    Pendientes

                </div>

            </div>

        </div>


        <!-- RESUELTAS -->

        <div class="col-6 col-md-4 col-xl-2">

            <div class="stat-card">

                <div class="stat-icon stat-resolved">

                    <i class="bi bi-check-circle"></i>

                </div>


                <div class="stat-number">

                    <?= $stats['resueltas'] ?>

                </div>


                <div class="stat-label">

                    Resueltas

                </div>

            </div>

        </div>


        <!-- URGENTES -->

        <div class="col-6 col-md-4 col-xl-2">

            <div class="stat-card">

                <div class="stat-icon stat-urgent">

                    <i class="bi bi-exclamation-triangle"></i>

                </div>


                <div class="stat-number">

                    <?= $stats['urgentes'] ?>

                </div>


                <div class="stat-label">

                    Urgentes

                </div>

            </div>

        </div>


    </div>


    <!-- =====================================================
         FILTROS
    ====================================================== -->

    <div class="filters-card">


        <form
            method="GET"
            action="<?= url(
                'solicitudes.php'
            ) ?>"
        >


            <div class="row g-3">


                <!-- BUSCAR -->

                <div class="col-lg-4">

                    <label
                        for="buscar"
                        class="form-label"
                    >

                        Buscar

                    </label>


                    <div class="input-group">


                        <span class="input-group-text">

                            <i class="bi bi-search"></i>

                        </span>


                        <input
                            type="text"
                            name="buscar"
                            id="buscar"
                            class="form-control"
                            value="<?= e(
                                $buscar
                            ) ?>"
                            placeholder="Ticket, título, sector, persona..."
                        >


                    </div>

                </div>


                <!-- ESTADO -->

                <div class="col-md-6 col-lg-2">

                    <label
                        for="estado"
                        class="form-label"
                    >

                        Estado

                    </label>


                    <select
                        name="estado"
                        id="estado"
                        class="form-select"
                    >

                        <option value="">

                            Todos

                        </option>


                        <?php foreach (
                            $estadosPermitidos
                            as $estado
                        ): ?>

                            <option
                                value="<?= e(
                                    $estado
                                ) ?>"
                                <?= $estadoFiltro === $estado
                                    ? 'selected'
                                    : ''
                                ?>
                            >

                                <?= e(
                                    $estado
                                ) ?>

                            </option>

                        <?php endforeach; ?>


                    </select>

                </div>


                <!-- PRIORIDAD -->

                <div class="col-md-6 col-lg-2">

                    <label
                        for="prioridad"
                        class="form-label"
                    >

                        Prioridad

                    </label>


                    <select
                        name="prioridad"
                        id="prioridad"
                        class="form-select"
                    >

                        <option value="">

                            Todas

                        </option>


                        <?php foreach (
                            $prioridadesPermitidas
                            as $prioridad
                        ): ?>

                            <option
                                value="<?= e(
                                    $prioridad
                                ) ?>"
                                <?= $prioridadFiltro === $prioridad
                                    ? 'selected'
                                    : ''
                                ?>
                            >

                                <?= e(
                                    $prioridad
                                ) ?>

                            </option>

                        <?php endforeach; ?>


                    </select>

                </div>


                <!-- ÁREA -->

                <div class="col-md-6 col-lg-2">

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
                            <?= $tipoFiltro === 'Informatica'
                                ? 'selected'
                                : ''
                            ?>
                        >

                            Informática

                        </option>


                        <option
                            value="Mantenimiento"
                            <?= $tipoFiltro === 'Mantenimiento'
                                ? 'selected'
                                : ''
                            ?>
                        >

                            Mantenimiento

                        </option>


                    </select>

                </div>


                <!-- BOTONES -->

                <div
                    class="col-md-6 col-lg-2
                           d-flex
                           align-items-end
                           gap-2"
                >


                    <button
                        type="submit"
                        class="btn btn-filter flex-fill"
                    >

                        <i class="bi bi-funnel me-1"></i>

                        Filtrar

                    </button>


                    <a
                        href="<?= url(
                            'solicitudes.php'
                        ) ?>"
                        class="btn btn-outline-secondary"
                        title="Limpiar filtros"
                    >

                        <i class="bi bi-x-lg"></i>

                    </a>


                </div>


            </div>


        </form>


    </div>


    <!-- =====================================================
         LISTADO
    ====================================================== -->

    <div class="main-card">


        <div class="main-card-header">


            <h5>

                <i class="bi bi-list-ul me-2"></i>

                Listado de solicitudes

            </h5>


            <span class="small text-muted">

                <?= $totalRegistros ?>

                <?= $totalRegistros === 1
                    ? 'resultado'
                    : 'resultados'
                ?>

            </span>


        </div>


        <div class="main-card-body">


            <?php if (
                empty(
                    $solicitudes
                )
            ): ?>


                <!-- =========================================
                     SIN RESULTADOS
                ========================================== -->

                <div class="empty-state">


                    <i class="bi bi-inbox"></i>


                    <h6>

                        No se encontraron solicitudes

                    </h6>


                    <p class="mb-0">

                        No existen solicitudes que coincidan
                        con los filtros seleccionados.

                    </p>


                    <a
                        href="<?= url(
                            'solicitudes.php'
                        ) ?>"
                        class="btn btn-outline-secondary mt-3"
                    >

                        Limpiar filtros

                    </a>


                </div>


            <?php else: ?>


                <?php foreach (
                    $solicitudes
                    as $solicitud
                ): ?>


                    <?php

                    $idSolicitud =
                        (int)$solicitud[
                            'id_solicitud'
                        ];


                    $idTecnicoActual =
                        (int)(
                            $solicitud[
                                'id_tecnico_actual'
                            ]
                            ?? 0
                        );


                    $soyTecnicoAsignado =
                        $rolActual === 'Tecnico'
                        &&
                        $idTecnicoActual ===
                        $idUsuario;

                    ?>


                    <article class="ticket">


                        <!-- =================================
                             BARRA PRIORIDAD
                        ================================== -->

                        <div
                            class="ticket-priority ticket-priority-<?= e(
                                $solicitud[
                                    'prioridad'
                                ]
                            ) ?>"
                        ></div>


                        <!-- =================================
                             INFORMACIÓN
                        ================================== -->

                        <div>


                            <div class="ticket-number">

                                <?= e(
                                    numeroTicket(
                                        $idSolicitud
                                    )
                                ) ?>

                            </div>


                            <a
                                href="<?= url(
                                    'ver_solicitud.php?id='
                                    .
                                    $idSolicitud
                                ) ?>"
                                class="ticket-title"
                            >

                                <?= e(
                                    $solicitud[
                                        'titulo'
                                    ]
                                ) ?>

                            </a>


                            <!-- DESCRIPCIÓN -->

                            <div class="ticket-description">

                                <?= e(
                                    $solicitud[
                                        'descripcion'
                                    ]
                                ) ?>

                            </div>


                            <!-- =================================
                                 META
                            ================================== -->

                            <div class="ticket-meta">


                                <!-- ÁREA -->

                                <span>

                                    <i class="<?= e(
                                        iconoTipo(
                                            $solicitud[
                                                'tipo'
                                            ]
                                        )
                                    ) ?>"></i>

                                    <?= e(
                                        textoTipoSolicitud(
                                            $solicitud[
                                                'tipo'
                                            ]
                                        )
                                    ) ?>

                                </span>


                                <!-- SECTOR -->

                                <?php if (
                                    !empty(
                                        $solicitud[
                                            'sector'
                                        ]
                                    )
                                ): ?>

                                    <span>

                                        <i class="bi bi-geo-alt"></i>

                                        <?= e(
                                            $solicitud[
                                                'sector'
                                            ]
                                        ) ?>

                                    </span>

                                <?php endif; ?>


                                <!-- CATEGORÍA -->

                                <?php if (
                                    !empty(
                                        $solicitud[
                                            'categoria'
                                        ]
                                    )
                                ): ?>

                                    <span>

                                        <i class="bi bi-tag"></i>

                                        <?= e(
                                            $solicitud[
                                                'categoria'
                                            ]
                                        ) ?>

                                    </span>

                                <?php endif; ?>


                                <!-- SOLICITANTE -->

                                <span>

                                    <i class="bi bi-person"></i>

                                    <?= e(
                                        $solicitud[
                                            'solicitante'
                                        ]
                                    ) ?>

                                </span>


                                <!-- FECHA -->

                                <span>

                                    <i class="bi bi-calendar3"></i>

                                    <?= e(
                                        fechaSolicitud(
                                            $solicitud[
                                                'fecha_creacion'
                                            ]
                                        )
                                    ) ?>

                                </span>


                            </div>


                            <!-- =================================
                                 CONTADORES
                            ================================== -->

                            <div class="ticket-counters">


                                <!-- FOTOS -->

                                <span class="ticket-counter">

                                    <i class="bi bi-images"></i>

                                    <strong>

                                        <?= (int)$solicitud[
                                            'cantidad_imagenes'
                                        ] ?>

                                    </strong>

                                    fotos

                                </span>


                                <!-- COMENTARIOS -->

                                <span class="ticket-counter">

                                    <i class="bi bi-chat-dots"></i>

                                    <strong>

                                        <?= (int)$solicitud[
                                            'cantidad_comentarios'
                                        ] ?>

                                    </strong>

                                    comentarios

                                </span>


                                <!-- INTERVENCIONES -->

                                <span class="ticket-counter">

                                    <i class="bi bi-tools"></i>

                                    <strong>

                                        <?= (int)$solicitud[
                                            'cantidad_intervenciones'
                                        ] ?>

                                    </strong>

                                    intervenciones

                                </span>


                            </div>


                            <!-- =================================
                                 TÉCNICO ASIGNADO
                            ================================== -->

                            <?php if (
                                !empty(
                                    $solicitud[
                                        'tecnico_actual'
                                    ]
                                )
                            ): ?>


                                <div class="assignment-box">


                                    <i class="bi bi-person-gear"></i>


                                    <strong>

                                        Técnico:

                                    </strong>


                                    <?= e(
                                        $solicitud[
                                            'tecnico_actual'
                                        ]
                                    ) ?>


                                    <?php if (
                                        !empty(
                                            $solicitud[
                                                'fecha_asignacion'
                                            ]
                                        )
                                    ): ?>


                                        <span>

                                            · Asignado

                                            <?= e(
                                                fechaSolicitud(
                                                    $solicitud[
                                                        'fecha_asignacion'
                                                    ]
                                                )
                                            ) ?>

                                        </span>


                                    <?php endif; ?>


                                </div>


                            <?php elseif (
                                !in_array(
                                    $solicitud[
                                        'estado'
                                    ],
                                    [
                                        'Cerrada',
                                        'Cancelada'
                                    ],
                                    true
                                )
                            ): ?>


                                <div class="assignment-box">


                                    <i class="bi bi-person-x"></i>


                                    Sin técnico asignado


                                </div>


                            <?php endif; ?>


                            <!-- =================================
                                 ÚLTIMA INTERVENCIÓN
                            ================================== -->

                            <?php if (
                                !empty(
                                    $solicitud[
                                        'ultima_intervencion'
                                    ]
                                )
                            ): ?>


                                <div class="small text-muted mt-2">

                                    <i class="bi bi-clock-history me-1"></i>

                                    Última intervención:

                                    <?= e(
                                        fechaSolicitud(
                                            $solicitud[
                                                'ultima_intervencion'
                                            ]
                                        )
                                    ) ?>

                                </div>


                            <?php endif; ?>


                            <!-- =================================
                                 PENDIENTE
                            ================================== -->

                            <?php if (
                                $solicitud[
                                    'estado'
                                ] === 'Pendiente'

                                &&
                                !empty(
                                    $solicitud[
                                        'motivo_pendiente'
                                    ]
                                )
                            ): ?>


                                <div class="pending-box">


                                    <strong>

                                        <i class="bi bi-hourglass-split me-1"></i>

                                        Motivo pendiente:

                                    </strong>


                                    <?= e(
                                        $solicitud[
                                            'motivo_pendiente'
                                        ]
                                    ) ?>


                                </div>


                            <?php endif; ?>


                        </div>


                        <!-- =================================
                             LATERAL
                        ================================== -->

                        <div class="ticket-side">


                            <!-- BADGES -->

                            <div class="ticket-badges">


                                <span
                                    class="badge <?= e(
                                        claseEstado(
                                            $solicitud[
                                                'estado'
                                            ]
                                        )
                                    ) ?>"
                                >

                                    <?= e(
                                        $solicitud[
                                            'estado'
                                        ]
                                    ) ?>

                                </span>


                                <span
                                    class="badge <?= e(
                                        clasePrioridad(
                                            $solicitud[
                                                'prioridad'
                                            ]
                                        )
                                    ) ?>"
                                >

                                    <?= e(
                                        $solicitud[
                                            'prioridad'
                                        ]
                                    ) ?>

                                </span>


                            </div>


                            <!-- =================================
                                 ACCIONES
                            ================================== -->

                            <div class="ticket-actions">


                                <!-- VER -->

                                <a
                                    href="<?= url(
                                        'ver_solicitud.php?id='
                                        .
                                        $idSolicitud
                                    ) ?>"
                                    class="btn-ticket btn-view"
                                >

                                    <i class="bi bi-eye"></i>

                                    Ver

                                </a>


                                <!-- =================================
                                     TÉCNICO
                                ================================== -->

                                <?php if (
                                    $soyTecnicoAsignado
                                ): ?>


                                    <?php if (
                                        $solicitud[
                                            'estado'
                                        ] === 'Resuelta'
                                    ): ?>


                                        <a
                                            href="<?= url(
                                                'tecnico/finalizar.php?id='
                                                .
                                                $idSolicitud
                                            ) ?>"
                                            class="btn-ticket btn-finish"
                                        >

                                            <i class="bi bi-check2-circle"></i>

                                            Finalizar

                                        </a>


                                    <?php elseif (
                                        !in_array(
                                            $solicitud[
                                                'estado'
                                            ],
                                            [
                                                'Cerrada',
                                                'Cancelada'
                                            ],
                                            true
                                        )
                                    ): ?>


                                        <a
                                            href="<?= url(
                                                'tecnico/intervenir.php?id='
                                                .
                                                $idSolicitud
                                            ) ?>"
                                            class="btn-ticket btn-tech"
                                        >

                                            <i class="bi bi-tools"></i>

                                            Intervenir

                                        </a>


                                    <?php endif; ?>


                                <?php endif; ?>


                                <!-- =================================
                                     ADMINISTRADOR
                                ================================== -->

                                <?php if (
                                    $rolActual ===
                                    'Administrador'
                                ): ?>


                                    <?php if (
                                        !in_array(
                                            $solicitud[
                                                'estado'
                                            ],
                                            [
                                                'Cerrada',
                                                'Cancelada'
                                            ],
                                            true
                                        )
                                    ): ?>


                                        <a
                                            href="<?= url(
                                                'tecnico/intervenir.php?id='
                                                .
                                                $idSolicitud
                                            ) ?>"
                                            class="btn-ticket btn-tech"
                                        >

                                            <i class="bi bi-tools"></i>

                                            Intervenir

                                        </a>


                                    <?php endif; ?>


                                <?php endif; ?>


                            </div>


                        </div>


                    </article>


                <?php endforeach; ?>


            <?php endif; ?>


        </div>


        <!-- =================================================
             PAGINACIÓN
        ================================================== -->

        <?php if (
            $totalPaginas > 1
        ): ?>


            <div class="pagination-wrapper">


                <div class="small text-muted">


                    Página

                    <strong>

                        <?= $pagina ?>

                    </strong>

                    de

                    <strong>

                        <?= $totalPaginas ?>

                    </strong>


                </div>


                <nav>


                    <ul class="pagination mb-0">


                        <!-- ANTERIOR -->

                        <li
                            class="page-item <?= $pagina <= 1
                                ? 'disabled'
                                : ''
                            ?>"
                        >


                            <a
                                class="page-link"
                                href="<?= $pagina > 1
                                    ? e(
                                        urlPaginaSolicitudes(
                                            $pagina - 1
                                        )
                                    )
                                    : '#'
                                ?>"
                            >

                                <i class="bi bi-chevron-left"></i>

                            </a>


                        </li>


                        <?php

                        $inicioPagina =
                            max(
                                1,
                                $pagina - 2
                            );


                        $finPagina =
                            min(
                                $totalPaginas,
                                $pagina + 2
                            );

                        ?>


                        <!-- PRIMERA -->

                        <?php if (
                            $inicioPagina > 1
                        ): ?>


                            <li class="page-item">


                                <a
                                    class="page-link"
                                    href="<?= e(
                                        urlPaginaSolicitudes(
                                            1
                                        )
                                    ) ?>"
                                >

                                    1

                                </a>


                            </li>


                            <?php if (
                                $inicioPagina > 2
                            ): ?>


                                <li class="page-item disabled">

                                    <span class="page-link">

                                        ...

                                    </span>

                                </li>


                            <?php endif; ?>


                        <?php endif; ?>


                        <!-- PÁGINAS -->

                        <?php for (
                            $i = $inicioPagina;
                            $i <= $finPagina;
                            $i++
                        ): ?>


                            <li
                                class="page-item <?= $i === $pagina
                                    ? 'active'
                                    : ''
                                ?>"
                            >


                                <a
                                    class="page-link"
                                    href="<?= e(
                                        urlPaginaSolicitudes(
                                            $i
                                        )
                                    ) ?>"
                                >

                                    <?= $i ?>

                                </a>


                            </li>


                        <?php endfor; ?>


                        <!-- ÚLTIMA -->

                        <?php if (
                            $finPagina <
                            $totalPaginas
                        ): ?>


                            <?php if (
                                $finPagina
                                <
                                $totalPaginas - 1
                            ): ?>


                                <li class="page-item disabled">

                                    <span class="page-link">

                                        ...

                                    </span>

                                </li>


                            <?php endif; ?>


                            <li class="page-item">


                                <a
                                    class="page-link"
                                    href="<?= e(
                                        urlPaginaSolicitudes(
                                            $totalPaginas
                                        )
                                    ) ?>"
                                >

                                    <?= $totalPaginas ?>

                                </a>


                            </li>


                        <?php endif; ?>


                        <!-- SIGUIENTE -->

                        <li
                            class="page-item <?= $pagina >= $totalPaginas
                                ? 'disabled'
                                : ''
                            ?>"
                        >


                            <a
                                class="page-link"
                                href="<?= $pagina < $totalPaginas
                                    ? e(
                                        urlPaginaSolicitudes(
                                            $pagina + 1
                                        )
                                    )
                                    : '#'
                                ?>"
                            >

                                <i class="bi bi-chevron-right"></i>

                            </a>


                        </li>


                    </ul>


                </nav>


            </div>


        <?php endif; ?>


    </div>


</div>


<?php
// ============================================================
// FOOTER
//
// Estamos directamente en /tecnica/
// ============================================================

require_once __DIR__ . '/includes/footer.php';
?>