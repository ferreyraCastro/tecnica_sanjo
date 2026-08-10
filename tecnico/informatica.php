<?php
// ============================================================
// COLEGIO SAN JOSÉ
// SISTEMA DE GESTIÓN TÉCNICA
//
// Archivo:
// /tecnica/tecnico/informatica.php
//
// Panel de solicitudes del área de INFORMÁTICA
// ============================================================

declare(strict_types=1);


// ============================================================
// INCLUDES
// ============================================================

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/funciones.php';
require_once __DIR__ . '/../includes/auth.php';


// ============================================================
// SEGURIDAD
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
    ?? '';


// ============================================================
// SOLO TÉCNICOS / ADMINISTRADORES
// ============================================================

if (
    !in_array(
        $rolActual,
        [
            'Tecnico',
            'Administrador'
        ],
        true
    )
) {

    flash(
        'error',
        'No tenés permisos para acceder al área técnica.'
    );

    header(
        'Location: ' . url('dashboard.php')
    );

    exit;
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


// ============================================================
// FUNCIÓN FECHA
// ============================================================

function fechaInformatica(
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
// CONDICIONES
// ============================================================

$condiciones = [

    "s.tipo = 'Informatica'"

];


$parametros = [];


// ============================================================
// TÉCNICO
//
// Un técnico ve:
// - solicitudes nuevas de informática
// - solicitudes asignadas a él
//
// Esto permite que pueda ver las nuevas solicitudes
// disponibles y las que ya está atendiendo.
// ============================================================

if ($rolActual === 'Tecnico') {

    $condiciones[] = "
        (
            s.estado = 'Nueva'

            OR

            EXISTS (

                SELECT 1

                FROM solicitudes_asignaciones sat

                WHERE
                    sat.id_solicitud =
                    s.id_solicitud

                AND
                    sat.id_tecnico = ?

                AND
                    sat.activo = 1
            )
        )
    ";

    $parametros[] =
        $idUsuario;
}


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

            CAST(
                s.id_solicitud
                AS CHAR
            ) LIKE ?
        )
    ";


    $buscarSQL =
        '%'
        .
        $buscar
        .
        '%';


    for ($i = 0; $i < 7; $i++) {

        $parametros[] =
            $buscarSQL;
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
// WHERE
// ============================================================

$where =
    'WHERE '
    .
    implode(
        ' AND ',
        $condiciones
    );


// ============================================================
// ESTADÍSTICAS
// ============================================================

$condicionTecnicoStats = '';

$paramsStats = [];


if ($rolActual === 'Tecnico') {

    $condicionTecnicoStats = "
        AND
        (
            s.estado = 'Nueva'

            OR

            EXISTS (

                SELECT 1

                FROM solicitudes_asignaciones sat

                WHERE
                    sat.id_solicitud =
                    s.id_solicitud

                AND
                    sat.id_tecnico = ?

                AND
                    sat.activo = 1
            )
        )
    ";

    $paramsStats[] =
        $idUsuario;
}


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

    WHERE
        s.tipo = 'Informatica'

    {$condicionTecnicoStats}
";


$stmtStats =
    $conexion->prepare(
        $sqlStats
    );


$stmtStats->execute(
    $paramsStats
);


$datosStats =
    $stmtStats->fetch(
        PDO::FETCH_ASSOC
    );


$stats = [

    'total' =>
        (int)(
            $datosStats['total']
            ?? 0
        ),

    'nuevas' =>
        (int)(
            $datosStats['nuevas']
            ?? 0
        ),

    'asignadas' =>
        (int)(
            $datosStats['asignadas']
            ?? 0
        ),

    'proceso' =>
        (int)(
            $datosStats['proceso']
            ?? 0
        ),

    'pendientes' =>
        (int)(
            $datosStats['pendientes']
            ?? 0
        ),

    'resueltas' =>
        (int)(
            $datosStats['resueltas']
            ?? 0
        ),

    'urgentes' =>
        (int)(
            $datosStats['urgentes']
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
// ============================================================

$sql = "
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
                i.fecha_intervencion
            )

            FROM intervenciones i

            WHERE
                i.id_solicitud =
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

        ) AS fecha_asignacion

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


$stmt =
    $conexion->prepare(
        $sql
    );


$stmt->execute(
    $parametros
);


$solicitudes =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


// ============================================================
// URL PAGINACIÓN
// ============================================================

function urlPaginaInformatica(
    int $pagina
): string {

    $query =
        $_GET;


    $query['pagina'] =
        $pagina;


    return url(
        'tecnico/informatica.php?'
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
// ============================================================

require_once __DIR__ . '/../includes/header.php';

?>


<style>

/* ============================================================
   CONTENEDOR
============================================================ */

.informatica-wrapper {

    max-width: 1550px;

    margin: 0 auto;

    padding:
        5px 12px
        50px;

}


/* ============================================================
   HERO
============================================================ */

.area-hero {

    position: relative;

    overflow: hidden;

    padding: 30px;

    margin-bottom: 24px;

    border-radius: 22px;

    color: #FFFFFF;

    background:
        linear-gradient(
            135deg,
            #0B3157,
            #1466A3
        );

    box-shadow:
        0 9px 28px
        rgba(11,49,87,.18);

}


.area-hero::after {

    content: "";

    position: absolute;

    width: 330px;
    height: 330px;

    right: -120px;
    top: -170px;

    border-radius: 50%;

    background:
        rgba(255,255,255,.07);

}


.area-hero::before {

    content: "";

    position: absolute;

    width: 170px;
    height: 170px;

    right: 160px;
    bottom: -125px;

    border-radius: 50%;

    background:
        rgba(255,255,255,.04);

}


.hero-content,
.hero-actions {

    position: relative;

    z-index: 2;

}


.area-icon {

    width: 53px;
    height: 53px;

    display: flex;

    align-items: center;
    justify-content: center;

    margin-bottom: 13px;

    border-radius: 15px;

    background:
        rgba(255,255,255,.13);

    font-size: 25px;

}


.area-hero h1 {

    margin: 0 0 6px;

    font-size: 29px;
    font-weight: 800;

}


.area-hero p {

    max-width: 720px;

    margin: 0;

    color:
        rgba(255,255,255,.82);

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

    color: #0B3157;

    background: #FFFFFF;

}


.btn-hero-white:hover {

    color: #1466A3;

    background: #F4F4F4;

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

    color: #0B3157;
    background: #EAF2F8;

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

    border-color: #1466A3;

    box-shadow:
        0 0 0 .2rem
        rgba(20,102,163,.08);

}


.btn-filter {

    min-height: 44px;

    border: 0;

    border-radius: 9px;

    color: #FFFFFF;

    background: #1466A3;

    font-weight: 700;

}


.btn-filter:hover {

    color: #FFFFFF;

    background: #0B3157;

}


/* ============================================================
   CARD PRINCIPAL
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

    color: #0B3157;

    font-size: 16px;
    font-weight: 800;

}


.main-card-body {

    padding:
        0 20px;

}


/* ============================================================
   SOLICITUD
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


.priority-line {

    position: absolute;

    left: -20px;

    top: 20px;
    bottom: 20px;

    width: 4px;

    border-radius:
        0 4px 4px 0;

}


.priority-Urgente {

    background: #760000;

}


.priority-Alta {

    background: #B12626;

}


.priority-Normal {

    background: #D2A100;

}


.priority-Baja {

    background: #777777;

}


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

    color: #1466A3;

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


/* ============================================================
   META
============================================================ */

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

    color: #1466A3;

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

    color: #31546E;

    background: #EEF6FB;

    font-size: 9px;

}


.assignment-box i {

    color: #1466A3;

}


.unassigned {

    color: #856404;

    background: #FFF6D8;

}


.unassigned i {

    color: #B48600;

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

    min-width: 190px;

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


/* ============================================================
   BOTONES
============================================================ */

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

    color: #0B3157;

    background: #EEF6FB;

}


.btn-view:hover {

    color: #FFFFFF;

    background: #1466A3;

}


.btn-intervenir {

    color: #FFFFFF;

    background:
        linear-gradient(
            135deg,
            #0B3157,
            #1466A3
        );

}


.btn-intervenir:hover {

    color: #FFFFFF;

    background: #0B3157;

}


.btn-finalizar {

    color: #FFFFFF;

    background: #198754;

}


.btn-finalizar:hover {

    color: #FFFFFF;

    background: #146C43;

}


/* ============================================================
   VACÍO
============================================================ */

.empty-state {

    padding:
        60px 20px;

    text-align: center;

    color: #888888;

}


.empty-state i {

    display: block;

    margin-bottom: 12px;

    color: #B9C8D3;

    font-size: 48px;

}


.empty-state h6 {

    color: #444444;

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

    color: #0B3157;

}


.page-item.active
.page-link {

    color: #FFFFFF;

    background: #1466A3;

    border-color: #1466A3;

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

    .area-hero {

        padding:
            22px 20px;

    }


    .area-hero h1 {

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


    .priority-line {

        left: -17px;

    }


    .ticket-actions {

        width: 100%;

    }


    .btn-ticket {

        flex: 1;

    }


    .pagination-wrapper {

        flex-direction: column;

        align-items: flex-start;

    }

}

</style>


<div class="informatica-wrapper">


    <!-- =====================================================
         HERO
    ====================================================== -->

    <section class="area-hero">


        <div class="row align-items-center">


            <div class="col-lg-8">


                <div class="hero-content">


                    <div class="area-icon">

                        <i class="bi bi-pc-display"></i>

                    </div>


                    <h1>

                        Informática

                    </h1>


                    <p>

                        Gestión de solicitudes relacionadas con
                        computadoras, redes, internet, software,
                        proyectores, impresoras y demás recursos
                        tecnológicos del colegio.

                    </p>


                </div>


            </div>


            <div class="col-lg-4">


                <div class="hero-actions">


                    <a
                        href="<?= url(
                            'tecnico/solicitudes.php'
                        ) ?>"
                        class="btn-hero btn-hero-outline"
                    >

                        <i class="bi bi-ticket-detailed"></i>

                        Solicitudes

                    </a>


                    <a
                        href="<?= url(
                            'tecnico/dashboard.php'
                        ) ?>"
                        class="btn-hero btn-hero-white"
                    >

                        <i class="bi bi-grid"></i>

                        Panel técnico

                    </a>


                </div>


            </div>


        </div>


    </section>


    <!-- =====================================================
         FLASH
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


        <div class="col-6 col-md-4 col-xl-2">

            <div class="stat-card">

                <div class="stat-icon stat-total">

                    <i class="bi bi-pc-display"></i>

                </div>

                <div class="stat-number">

                    <?= $stats['total'] ?>

                </div>

                <div class="stat-label">

                    Total informática

                </div>

            </div>

        </div>


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
                'tecnico/informatica.php'
            ) ?>"
        >


            <div class="row g-3">


                <div class="col-lg-6">


                    <label
                        for="buscar"
                        class="form-label"
                    >

                        Buscar solicitud

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
                            placeholder="Ticket, título, sector, solicitante..."
                        >


                    </div>


                </div>


                <div class="col-md-4 col-lg-2">


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


                <div class="col-md-4 col-lg-2">


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


                <div
                    class="col-md-4 col-lg-2
                           d-flex
                           align-items-end
                           gap-2"
                >


                    <button
                        type="submit"
                        class="btn btn-filter flex-fill"
                    >

                        <i class="bi bi-funnel"></i>

                        Filtrar

                    </button>


                    <a
                        href="<?= url(
                            'tecnico/informatica.php'
                        ) ?>"
                        class="btn btn-outline-secondary"
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

                <i class="bi bi-pc-display me-2"></i>

                Solicitudes de Informática

            </h5>


            <span class="small text-muted">

                <?= $totalRegistros ?>

                <?= $totalRegistros === 1
                    ? 'solicitud'
                    : 'solicitudes'
                ?>

            </span>


        </div>


        <div class="main-card-body">


            <?php if (
                empty(
                    $solicitudes
                )
            ): ?>


                <div class="empty-state">


                    <i class="bi bi-pc-display"></i>


                    <h6>

                        No hay solicitudes de Informática

                    </h6>


                    <p class="mb-0">

                        No existen solicitudes que coincidan
                        con los filtros seleccionados.

                    </p>


                    <a
                        href="<?= url(
                            'tecnico/informatica.php'
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


                        <div
                            class="priority-line priority-<?= e(
                                $solicitud[
                                    'prioridad'
                                ]
                            ) ?>"
                        ></div>


                        <!-- =============================
                             INFORMACIÓN
                        ============================== -->

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


                            <div class="ticket-description">

                                <?= e(
                                    $solicitud[
                                        'descripcion'
                                    ]
                                ) ?>

                            </div>


                            <!-- =============================
                                 META
                            ============================== -->

                            <div class="ticket-meta">


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


                                <span>

                                    <i class="bi bi-person"></i>

                                    <?= e(
                                        $solicitud[
                                            'solicitante'
                                        ]
                                    ) ?>

                                </span>


                                <span>

                                    <i class="bi bi-calendar3"></i>

                                    <?= e(
                                        fechaInformatica(
                                            $solicitud[
                                                'fecha_creacion'
                                            ]
                                        )
                                    ) ?>

                                </span>


                            </div>


                            <!-- =============================
                                 CONTADORES
                            ============================== -->

                            <div class="ticket-counters">


                                <span class="ticket-counter">

                                    <i class="bi bi-images"></i>

                                    <?= (int)$solicitud[
                                        'cantidad_imagenes'
                                    ] ?>

                                    fotos

                                </span>


                                <span class="ticket-counter">

                                    <i class="bi bi-chat-dots"></i>

                                    <?= (int)$solicitud[
                                        'cantidad_comentarios'
                                    ] ?>

                                    comentarios

                                </span>


                                <span class="ticket-counter">

                                    <i class="bi bi-tools"></i>

                                    <?= (int)$solicitud[
                                        'cantidad_intervenciones'
                                    ] ?>

                                    intervenciones

                                </span>


                            </div>


                            <!-- =============================
                                 ASIGNACIÓN
                            ============================== -->

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

                                            ·

                                            <?= e(
                                                fechaInformatica(
                                                    $solicitud[
                                                        'fecha_asignacion'
                                                    ]
                                                )
                                            ) ?>

                                        </span>


                                    <?php endif; ?>


                                </div>


                            <?php else: ?>


                                <div
                                    class="assignment-box unassigned"
                                >


                                    <i class="bi bi-person-x"></i>


                                    Sin técnico asignado


                                </div>


                            <?php endif; ?>


                            <!-- =============================
                                 ÚLTIMA INTERVENCIÓN
                            ============================== -->

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
                                        fechaInformatica(
                                            $solicitud[
                                                'ultima_intervencion'
                                            ]
                                        )
                                    ) ?>


                                </div>


                            <?php endif; ?>


                            <!-- =============================
                                 MOTIVO PENDIENTE
                            ============================== -->

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

                                        <i class="bi bi-hourglass-split"></i>

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


                        <!-- =============================
                             LATERAL
                        ============================== -->

                        <div class="ticket-side">


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


                            <!-- =============================
                                 ACCIONES
                            ============================== -->

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


                                <!-- =========================
                                     TÉCNICO ASIGNADO
                                ========================== -->

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
                                            class="btn-ticket btn-finalizar"
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
                                            class="btn-ticket btn-intervenir"
                                        >

                                            <i class="bi bi-tools"></i>

                                            Intervenir

                                        </a>


                                    <?php endif; ?>


                                <?php endif; ?>


                                <!-- =========================
                                     ADMINISTRADOR
                                ========================== -->

                                <?php if (
                                    $rolActual ===
                                    'Administrador'

                                    &&
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
                                        class="btn-ticket btn-intervenir"
                                    >

                                        <i class="bi bi-tools"></i>

                                        Intervenir

                                    </a>


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
                                        urlPaginaInformatica(
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


                        <?php if (
                            $inicioPagina > 1
                        ): ?>


                            <li class="page-item">


                                <a
                                    class="page-link"
                                    href="<?= e(
                                        urlPaginaInformatica(
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
                                        urlPaginaInformatica(
                                            $i
                                        )
                                    ) ?>"
                                >

                                    <?= $i ?>

                                </a>


                            </li>


                        <?php endfor; ?>


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
                                        urlPaginaInformatica(
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
                                        urlPaginaInformatica(
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
// ============================================================

require_once __DIR__ . '/../includes/footer.php';

?>