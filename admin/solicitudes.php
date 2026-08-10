<?php
// ============================================================
// COLEGIO SAN JOSÉ
// Sistema de Gestión Técnica
// Archivo: /tecnica/admin/solicitudes.php
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
// FILTROS
// ============================================================

$buscar =
    trim(
        $_GET['buscar']
        ?? ''
    );

$estado =
    trim(
        $_GET['estado']
        ?? ''
    );

$tipo =
    trim(
        $_GET['tipo']
        ?? ''
    );

$prioridad =
    trim(
        $_GET['prioridad']
        ?? ''
    );

$idTecnico =
    (int)(
        $_GET['tecnico']
        ?? 0
    );

$idSector =
    (int)(
        $_GET['sector']
        ?? 0
    );


// ============================================================
// VALORES PERMITIDOS
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


$tiposPermitidos = [

    'Informatica',
    'Mantenimiento'

];


$prioridadesPermitidas = [

    'Baja',
    'Normal',
    'Alta',
    'Urgente'

];


// ============================================================
// VALIDAR FILTROS
// ============================================================

if (
    $estado !== ''
    &&
    !in_array(
        $estado,
        $estadosPermitidos,
        true
    )
) {

    $estado = '';
}


if (
    $tipo !== ''
    &&
    !in_array(
        $tipo,
        $tiposPermitidos,
        true
    )
) {

    $tipo = '';
}


if (
    $prioridad !== ''
    &&
    !in_array(
        $prioridad,
        $prioridadesPermitidas,
        true
    )
) {

    $prioridad = '';
}


// ============================================================
// DATOS PARA FILTROS
// ============================================================

$tecnicos =
    obtenerTecnicos(
        $conexion
    );


$sectores =
    obtenerSectores(
        $conexion
    );


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


$porPagina = 20;

$offset =
    ($pagina - 1)
    *
    $porPagina;


// ============================================================
// CONDICIONES
// ============================================================

$condiciones = [];

$parametros = [];


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
            u.nombre LIKE ?
            OR
            u.apellido LIKE ?
            OR
            u.correo LIKE ?
            OR
            sec.nombre LIKE ?
            OR
            cat.nombre LIKE ?
            OR
            CAST(
                s.id_solicitud AS CHAR
            ) LIKE ?
        )
    ";


    $buscarSQL =
        '%' .
        $buscar .
        '%';


    for (
        $i = 0;
        $i < 8;
        $i++
    ) {

        $parametros[] =
            $buscarSQL;
    }
}


// ============================================================
// ESTADO
// ============================================================

if ($estado !== '') {

    $condiciones[] =
        's.estado = ?';

    $parametros[] =
        $estado;
}


// ============================================================
// TIPO
// ============================================================

if ($tipo !== '') {

    $condiciones[] =
        's.tipo = ?';

    $parametros[] =
        $tipo;
}


// ============================================================
// PRIORIDAD
// ============================================================

if ($prioridad !== '') {

    $condiciones[] =
        's.prioridad = ?';

    $parametros[] =
        $prioridad;
}


// ============================================================
// SECTOR
// ============================================================

if ($idSector > 0) {

    $condiciones[] =
        's.id_sector = ?';

    $parametros[] =
        $idSector;
}


// ============================================================
// TÉCNICO
// ============================================================

if ($idTecnico > 0) {

    $condiciones[] = "
        EXISTS
        (
            SELECT 1

            FROM solicitudes_asignaciones saFiltro

            WHERE
                saFiltro.id_solicitud =
                    s.id_solicitud

            AND
                saFiltro.id_tecnico = ?

            AND
                saFiltro.activo = 1
        )
    ";

    $parametros[] =
        $idTecnico;
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
// CONTAR RESULTADOS
// ============================================================

$sqlCantidad = "
    SELECT COUNT(*)

    FROM solicitudes s

    INNER JOIN usuarios u
        ON s.id_usuario =
           u.id_usuario

    LEFT JOIN sectores sec
        ON s.id_sector =
           sec.id_sector

    LEFT JOIN categorias cat
        ON s.id_categoria =
           cat.id_categoria

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


if (
    $pagina >
    $totalPaginas
) {

    $pagina =
        $totalPaginas;

    $offset =
        ($pagina - 1)
        *
        $porPagina;
}


// ============================================================
// CONSULTA PRINCIPAL
// ============================================================

$sql = "
    SELECT

        s.id_solicitud,

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

        u.correo,

        sec.nombre AS sector,

        cat.nombre AS categoria,

        (
            SELECT
                CONCAT(
                    ut.nombre,
                    ' ',
                    ut.apellido
                )

            FROM solicitudes_asignaciones sa

            INNER JOIN usuarios ut
                ON sa.id_tecnico =
                   ut.id_usuario

            WHERE
                sa.id_solicitud =
                    s.id_solicitud

            AND
                sa.activo = 1

            ORDER BY
                sa.fecha_asignacion DESC

            LIMIT 1

        ) AS tecnico,

        (
            SELECT
                sa2.id_tecnico

            FROM solicitudes_asignaciones sa2

            WHERE
                sa2.id_solicitud =
                    s.id_solicitud

            AND
                sa2.activo = 1

            ORDER BY
                sa2.fecha_asignacion DESC

            LIMIT 1

        ) AS id_tecnico,

        (
            SELECT COUNT(*)

            FROM solicitud_imagenes si

            WHERE
                si.id_solicitud =
                    s.id_solicitud

        ) AS fotos,

        (
            SELECT COUNT(*)

            FROM comentarios co

            WHERE
                co.id_solicitud =
                    s.id_solicitud

        ) AS comentarios,

        (
            SELECT COUNT(*)

            FROM intervenciones inter

            WHERE
                inter.id_solicitud =
                    s.id_solicitud

        ) AS intervenciones

    FROM solicitudes s

    INNER JOIN usuarios u
        ON s.id_usuario =
           u.id_usuario

    LEFT JOIN sectores sec
        ON s.id_sector =
           sec.id_sector

    LEFT JOIN categorias cat
        ON s.id_categoria =
           cat.id_categoria

    {$where}

    ORDER BY

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

        s.fecha_creacion DESC

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
// ESTADÍSTICAS GENERALES
// ============================================================

$estadisticas =
    obtenerEstadisticas(
        $conexion
    );


// ============================================================
// FLASH
// ============================================================

$flash =
    obtenerFlash();


// ============================================================
// URL PAGINACIÓN
// ============================================================

function urlPaginaAdminSolicitudes(
    int $pagina
): string {

    $parametros =
        $_GET;

    $parametros['pagina'] =
        $pagina;

    return url(
        'admin/solicitudes.php?'
        .
        http_build_query(
            $parametros
        )
    );
}


// ============================================================
// HEADER
// ============================================================

require_once __DIR__
    . '/../includes/header.php';

?>


<style>

.admin-solicitudes-wrapper {

    max-width: 1600px;

    margin: 0 auto;

    padding:
        5px 12px
        45px;

}


/* ============================================================
   HERO
============================================================ */

.page-hero {

    position: relative;

    overflow: hidden;

    background:
        linear-gradient(
            135deg,
            #760000,
            #B12626
        );

    color: #FFFFFF;

    padding: 28px;

    border-radius: 21px;

    margin-bottom: 23px;

    box-shadow:
        0 8px 26px
        rgba(118,0,0,.16);

}


.page-hero::after {

    content: "";

    position: absolute;

    width: 250px;

    height: 250px;

    right: -100px;

    top: -110px;

    border-radius: 50%;

    background:
        rgba(
            255,
            255,
            255,
            .06
        );

}


.hero-content {

    position: relative;

    z-index: 2;

}


.page-hero h1 {

    margin:
        0 0 6px;

    font-size: 27px;

    font-weight: 800;

}


.page-hero p {

    margin: 0;

    color:
        rgba(
            255,
            255,
            255,
            .78
        );

}


.hero-actions {

    position: relative;

    z-index: 2;

}


.btn-admin-volver {

    display: inline-flex;

    align-items: center;

    gap: 7px;

    padding:
        10px 16px;

    border-radius: 10px;

    background: #FFFFFF;

    color: #760000;

    text-decoration: none;

    font-weight: 700;

}


.btn-admin-volver:hover {

    background: #F5F5F5;

    color: #B12626;

}


/* ============================================================
   ESTADÍSTICAS
============================================================ */

.stat-card {

    background: #FFFFFF;

    border:
        1px solid #ECECEC;

    border-radius: 16px;

    padding: 17px;

    height: 100%;

    box-shadow:
        0 4px 15px
        rgba(
            0,
            0,
            0,
            .04
        );

}


.stat-icon {

    width: 41px;

    height: 41px;

    border-radius: 11px;

    display: flex;

    justify-content: center;

    align-items: center;

    margin-bottom: 9px;

}


.stat-number {

    font-size: 26px;

    line-height: 1;

    font-weight: 800;

    color: #333333;

}


.stat-label {

    margin-top: 6px;

    color: #7B7B7B;

    font-size: 11px;

    font-weight: 700;

}


.icon-total {

    background: #F1E4E4;

    color: #760000;

}


.icon-new {

    background: #E7F0FF;

    color: #0D6EFD;

}


.icon-process {

    background: #FFF3CD;

    color: #8B6800;

}


.icon-pending {

    background: #FFE5E5;

    color: #B12626;

}


.icon-done {

    background: #E1F4E8;

    color: #198754;

}


.icon-urgent {

    background: #760000;

    color: #FFFFFF;

}


/* ============================================================
   FILTROS
============================================================ */

.filters-card {

    margin:
        23px 0;

    background: #FFFFFF;

    border:
        1px solid #ECECEC;

    border-radius: 17px;

    padding: 20px;

    box-shadow:
        0 5px 17px
        rgba(
            0,
            0,
            0,
            .04
        );

}


.form-label {

    color: #555555;

    font-size: 12px;

    font-weight: 700;

}


.form-control,
.form-select {

    min-height: 43px;

    border-radius: 9px;

}


.form-control:focus,
.form-select:focus {

    border-color: #B12626;

    box-shadow:
        0 0 0
        .2rem
        rgba(
            177,
            38,
            38,
            .08
        );

}


.btn-filter {

    min-height: 43px;

    background: #B12626;

    border: none;

    color: #FFFFFF;

    border-radius: 9px;

    font-weight: 700;

}


.btn-filter:hover {

    background: #760000;

    color: #FFFFFF;

}


.btn-clear {

    min-height: 43px;

    border:
        1px solid #DADADA;

    background: #FFFFFF;

    color: #555555;

    border-radius: 9px;

}


/* ============================================================
   TABLA
============================================================ */

.list-card {

    background: #FFFFFF;

    border:
        1px solid #ECECEC;

    border-radius: 18px;

    overflow: hidden;

    box-shadow:
        0 5px 20px
        rgba(
            0,
            0,
            0,
            .05
        );

}


.list-header {

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 15px;

    padding:
        17px 20px;

    border-bottom:
        1px solid #EEEEEE;

}


.list-header h5 {

    color: #760000;

    font-size: 16px;

    font-weight: 800;

    margin: 0;

}


.result-count {

    color: #888888;

    font-size: 11px;

}


.table {

    margin-bottom: 0;

}


.table thead th {

    background: #FAFAFA;

    color: #555555;

    font-size: 10px;

    text-transform: uppercase;

    letter-spacing: .35px;

    padding:
        13px;

    white-space: nowrap;

}


.table tbody td {

    padding:
        14px 13px;

    vertical-align: middle;

    border-color: #EEEEEE;

}


.ticket-number {

    color: #760000;

    font-size: 12px;

    font-weight: 800;

    white-space: nowrap;

}


.ticket-title {

    color: #333333;

    font-size: 13px;

    font-weight: 700;

    text-decoration: none;

}


.ticket-title:hover {

    color: #B12626;

}


.ticket-description {

    display: block;

    max-width: 320px;

    margin-top: 3px;

    color: #8A8A8A;

    font-size: 10px;

    white-space: nowrap;

    overflow: hidden;

    text-overflow: ellipsis;

}


.small-meta {

    color: #777777;

    font-size: 10px;

    margin-top: 3px;

}


.small-meta i {

    color: #B12626;

}


.ticket-icons {

    display: flex;

    gap: 8px;

    margin-top: 7px;

    color: #888888;

    font-size: 10px;

}


.ticket-icons span {

    display: inline-flex;

    align-items: center;

    gap: 3px;

}


/* ============================================================
   TÉCNICO
============================================================ */

.technician {

    display: flex;

    align-items: center;

    gap: 7px;

    min-width: 130px;

}


.technician-icon {

    min-width: 31px;

    width: 31px;

    height: 31px;

    display: flex;

    justify-content: center;

    align-items: center;

    border-radius: 50%;

    background: #F2E5E5;

    color: #760000;

}


.technician strong {

    font-size: 10px;

    color: #444444;

}


.no-technician {

    color: #A0A0A0;

    font-size: 10px;

}


/* ============================================================
   BADGES
============================================================ */

.ticket-badge {

    border-radius: 20px;

    padding:
        5px 8px;

    font-size: 9px;

    font-weight: 700;

    white-space: nowrap;

}


/* ============================================================
   PENDIENTE
============================================================ */

.pending-reason {

    margin-top: 6px;

    padding:
        6px 8px;

    background: #FFF7DD;

    border-left:
        3px solid #E0A800;

    border-radius: 6px;

    color: #665400;

    font-size: 9px;

    max-width: 320px;

}


/* ============================================================
   BOTONES
============================================================ */

.actions {

    display: flex;

    justify-content: center;

    gap: 5px;

}


.action-btn {

    width: 34px;

    height: 34px;

    display: inline-flex;

    justify-content: center;

    align-items: center;

    border-radius: 8px;

    text-decoration: none;

    transition: .2s;

}


.action-view {

    background: #FFF3F3;

    color: #760000;

    border:
        1px solid #F0D7D7;

}


.action-view:hover {

    background: #B12626;

    color: #FFFFFF;

}


.action-assign {

    background: #EEF5FF;

    color: #0D6EFD;

    border:
        1px solid #DCE9FF;

}


.action-assign:hover {

    background: #0D6EFD;

    color: #FFFFFF;

}


.action-work {

    background: #FFF5DB;

    color: #967000;

    border:
        1px solid #F4E5B8;

}


.action-work:hover {

    background: #D7A600;

    color: #FFFFFF;

}


/* ============================================================
   EMPTY
============================================================ */

.empty-state {

    padding:
        60px 20px;

    text-align: center;

    color: #909090;

}


.empty-state i {

    display: block;

    font-size: 47px;

    color: #D0D0D0;

    margin-bottom: 10px;

}


/* ============================================================
   PAGINACIÓN
============================================================ */

.pagination {

    margin-bottom: 0;

}


.page-link {

    color: #760000;

}


.page-item.active
.page-link {

    background: #B12626;

    border-color: #B12626;

}


/* ============================================================
   RESPONSIVE
============================================================ */

.mobile-ticket {

    display: none;

}


@media
(max-width: 1000px) {

    .desktop-list {

        display: none;

    }


    .mobile-ticket {

        display: block;

    }


    .mobile-ticket-item {

        padding: 18px;

        border-bottom:
            1px solid #EEEEEE;

    }


    .mobile-ticket-item:last-child {

        border-bottom: 0;

    }


    .mobile-top {

        display: flex;

        justify-content: space-between;

        align-items: flex-start;

        gap: 10px;

    }


    .mobile-badges {

        display: flex;

        flex-direction: column;

        gap: 5px;

        align-items: flex-end;

    }


    .mobile-details {

        display: flex;

        flex-wrap: wrap;

        gap:
            6px 12px;

        margin-top: 10px;

        color: #777777;

        font-size: 11px;

    }


    .mobile-actions {

        display: grid;

        grid-template-columns:
            repeat(3,1fr);

        gap: 8px;

        margin-top: 15px;

    }


    .mobile-actions
    .action-btn {

        width: 100%;

        height: 40px;

    }

}


@media
(max-width: 767px) {

    .page-hero {

        padding: 22px 19px;

    }


    .page-hero h1 {

        font-size: 23px;

    }


    .hero-actions {

        margin-top: 18px;

    }


    .btn-admin-volver {

        width: 100%;

    }

}

</style>


<div class="admin-solicitudes-wrapper">


    <!-- =====================================================
         HERO
    ====================================================== -->

    <section class="page-hero">

        <div class="row align-items-center">

            <div class="col-lg-8">

                <div class="hero-content">

                    <h1>

                        <i class="bi bi-ticket-detailed me-1"></i>

                        Administración de solicitudes

                    </h1>

                    <p>

                        Consultá y gestioná todos los pedidos
                        de informática y mantenimiento
                        registrados en el colegio.

                    </p>

                </div>

            </div>


            <div
                class="col-lg-4
                       text-lg-end
                       hero-actions"
            >

                <a
                    href="<?= url(
                        'admin/dashboard.php'
                    ) ?>"
                    class="btn-admin-volver"
                >

                    <i class="bi bi-arrow-left"></i>

                    Panel administrador

                </a>

            </div>

        </div>

    </section>


    <!-- =====================================================
         FLASH
    ====================================================== -->

    <?php if ($flash): ?>

        <div
            class="alert alert-<?= e(
                $flash['tipo']
                === 'success'
                ? 'success'
                : 'info'
            ) ?> alert-dismissible fade show"
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

    <div class="row g-3">

        <div class="col-6 col-md-4 col-xl-2">

            <div class="stat-card">

                <div class="stat-icon icon-total">

                    <i class="bi bi-ticket-detailed"></i>

                </div>

                <div class="stat-number">

                    <?= $estadisticas['total'] ?>

                </div>

                <div class="stat-label">
                    Total
                </div>

            </div>

        </div>


        <div class="col-6 col-md-4 col-xl-2">

            <div class="stat-card">

                <div class="stat-icon icon-new">

                    <i class="bi bi-plus-circle"></i>

                </div>

                <div class="stat-number">

                    <?= $estadisticas['nuevas'] ?>

                </div>

                <div class="stat-label">
                    Nuevas
                </div>

            </div>

        </div>


        <div class="col-6 col-md-4 col-xl-2">

            <div class="stat-card">

                <div class="stat-icon icon-process">

                    <i class="bi bi-arrow-repeat"></i>

                </div>

                <div class="stat-number">

                    <?= $estadisticas['en_proceso'] ?>

                </div>

                <div class="stat-label">
                    En proceso
                </div>

            </div>

        </div>


        <div class="col-6 col-md-4 col-xl-2">

            <div class="stat-card">

                <div class="stat-icon icon-pending">

                    <i class="bi bi-hourglass-split"></i>

                </div>

                <div class="stat-number">

                    <?= $estadisticas['pendientes'] ?>

                </div>

                <div class="stat-label">
                    Pendientes
                </div>

            </div>

        </div>


        <div class="col-6 col-md-4 col-xl-2">

            <div class="stat-card">

                <div class="stat-icon icon-done">

                    <i class="bi bi-check-circle"></i>

                </div>

                <div class="stat-number">

                    <?= $estadisticas['resueltas'] ?>

                </div>

                <div class="stat-label">
                    Resueltas
                </div>

            </div>

        </div>


        <div class="col-6 col-md-4 col-xl-2">

            <div class="stat-card">

                <div class="stat-icon icon-urgent">

                    <i class="bi bi-exclamation-triangle"></i>

                </div>

                <div class="stat-number">

                    <?= $estadisticas['urgentes'] ?>

                </div>

                <div class="stat-label">
                    Urgentes abiertas
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
                'admin/solicitudes.php'
            ) ?>"
        >

            <div class="row g-3">


                <!-- BUSCAR -->

                <div class="col-xl-3 col-lg-4">

                    <label class="form-label">

                        Buscar

                    </label>

                    <div class="input-group">

                        <span class="input-group-text">

                            <i class="bi bi-search"></i>

                        </span>

                        <input
                            type="text"
                            name="buscar"
                            class="form-control"
                            value="<?= e($buscar) ?>"
                            placeholder="Ticket, docente, título..."
                        >

                    </div>

                </div>


                <!-- ESTADO -->

                <div class="col-md-4 col-lg-2">

                    <label class="form-label">

                        Estado

                    </label>

                    <select
                        name="estado"
                        class="form-select"
                    >

                        <option value="">
                            Todos
                        </option>

                        <?php foreach (
                            $estadosPermitidos
                            as $opcion
                        ): ?>

                            <option
                                value="<?= e(
                                    $opcion
                                ) ?>"
                                <?= $estado === $opcion
                                    ? 'selected'
                                    : ''
                                ?>
                            >

                                <?= e(
                                    $opcion
                                ) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- TIPO -->

                <div class="col-md-4 col-lg-2">

                    <label class="form-label">

                        Tipo

                    </label>

                    <select
                        name="tipo"
                        class="form-select"
                    >

                        <option value="">
                            Todos
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
                            Mantenimiento
                        </option>

                    </select>

                </div>


                <!-- PRIORIDAD -->

                <div class="col-md-4 col-lg-2">

                    <label class="form-label">

                        Prioridad

                    </label>

                    <select
                        name="prioridad"
                        class="form-select"
                    >

                        <option value="">
                            Todas
                        </option>

                        <?php foreach (
                            $prioridadesPermitidas
                            as $opcion
                        ): ?>

                            <option
                                value="<?= e(
                                    $opcion
                                ) ?>"
                                <?= $prioridad === $opcion
                                    ? 'selected'
                                    : ''
                                ?>
                            >

                                <?= e(
                                    $opcion
                                ) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- SECTOR -->

                <div class="col-md-6 col-lg-3">

                    <label class="form-label">

                        Sector

                    </label>

                    <select
                        name="sector"
                        class="form-select"
                    >

                        <option value="0">
                            Todos
                        </option>

                        <?php foreach (
                            $sectores
                            as $sectorItem
                        ): ?>

                            <option
                                value="<?= (int)$sectorItem[
                                    'id_sector'
                                ] ?>"
                                <?= $idSector ===
                                    (int)$sectorItem[
                                        'id_sector'
                                    ]
                                    ? 'selected'
                                    : ''
                                ?>
                            >

                                <?= e(
                                    $sectorItem[
                                        'nombre'
                                    ]
                                ) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- TÉCNICO -->

                <div class="col-md-6 col-lg-3">

                    <label class="form-label">

                        Técnico asignado

                    </label>

                    <select
                        name="tecnico"
                        class="form-select"
                    >

                        <option value="0">
                            Todos
                        </option>

                        <?php foreach (
                            $tecnicos
                            as $tecnico
                        ): ?>

                            <option
                                value="<?= (int)$tecnico[
                                    'id_usuario'
                                ] ?>"
                                <?= $idTecnico ===
                                    (int)$tecnico[
                                        'id_usuario'
                                    ]
                                    ? 'selected'
                                    : ''
                                ?>
                            >

                                <?= e(
                                    trim(
                                        $tecnico['nombre']
                                        .
                                        ' '
                                        .
                                        $tecnico['apellido']
                                    )
                                ) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- BOTONES -->

                <div
                    class="col-lg-3
                           d-flex
                           align-items-end
                           gap-2"
                >

                    <button
                        type="submit"
                        class="btn btn-filter flex-fill"
                    >

                        <i class="bi bi-funnel me-1"></i>

                        Aplicar filtros

                    </button>


                    <a
                        href="<?= url(
                            'admin/solicitudes.php'
                        ) ?>"
                        class="btn btn-clear"
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

    <div class="list-card">

        <div class="list-header">

            <h5>

                <i class="bi bi-list-check me-2"></i>

                Solicitudes registradas

            </h5>

            <div class="result-count">

                <?= $totalRegistros ?>

                <?= $totalRegistros === 1
                    ? 'solicitud'
                    : 'solicitudes'
                ?>

            </div>

        </div>


        <?php if (
            empty(
                $solicitudes
            )
        ): ?>

            <div class="empty-state">

                <i class="bi bi-inbox"></i>

                <h5>
                    No hay solicitudes
                </h5>

                <p>

                    No se encontraron registros
                    para los filtros seleccionados.

                </p>

            </div>


        <?php else: ?>


            <!-- =================================================
                 DESKTOP
            ================================================== -->

            <div
                class="table-responsive
                       desktop-list"
            >

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
                                Solicitante
                            </th>

                            <th>
                                Sector
                            </th>

                            <th>
                                Técnico
                            </th>

                            <th>
                                Prioridad
                            </th>

                            <th>
                                Estado
                            </th>

                            <th>
                                Fecha
                            </th>

                            <th class="text-center">
                                Acciones
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php foreach (
                        $solicitudes
                        as $solicitud
                    ): ?>


                        <tr>


                            <!-- TICKET -->

                            <td>

                                <span class="ticket-number">

                                    <?= e(
                                        numeroTicket(
                                            (int)$solicitud[
                                                'id_solicitud'
                                            ]
                                        )
                                    ) ?>

                                </span>

                            </td>


                            <!-- SOLICITUD -->

                            <td>

                                <a
                                    href="<?= url(
                                        'ver_solicitud.php?id='
                                        .
                                        (int)$solicitud[
                                            'id_solicitud'
                                        ]
                                    ) ?>"
                                    class="ticket-title"
                                >

                                    <?= e(
                                        $solicitud[
                                            'titulo'
                                        ]
                                    ) ?>

                                </a>


                                <span class="ticket-description">

                                    <?= e(
                                        $solicitud[
                                            'descripcion'
                                        ]
                                    ) ?>

                                </span>


                                <div class="small-meta">

                                    <i class="<?= e(
                                        iconoTipo(
                                            $solicitud[
                                                'tipo'
                                            ]
                                        )
                                    ) ?>"></i>

                                    <?= e(
                                        nombreTipo(
                                            $solicitud[
                                                'tipo'
                                            ]
                                        )
                                    ) ?>


                                    <?php if (
                                        !empty(
                                            $solicitud[
                                                'categoria'
                                            ]
                                        )
                                    ): ?>

                                        ·

                                        <?= e(
                                            $solicitud[
                                                'categoria'
                                            ]
                                        ) ?>

                                    <?php endif; ?>

                                </div>


                                <div class="ticket-icons">


                                    <?php if (
                                        (int)$solicitud[
                                            'fotos'
                                        ] > 0
                                    ): ?>

                                        <span>

                                            <i class="bi bi-image"></i>

                                            <?= (int)$solicitud[
                                                'fotos'
                                            ] ?>

                                        </span>

                                    <?php endif; ?>


                                    <?php if (
                                        (int)$solicitud[
                                            'comentarios'
                                        ] > 0
                                    ): ?>

                                        <span>

                                            <i class="bi bi-chat"></i>

                                            <?= (int)$solicitud[
                                                'comentarios'
                                            ] ?>

                                        </span>

                                    <?php endif; ?>


                                    <?php if (
                                        (int)$solicitud[
                                            'intervenciones'
                                        ] > 0
                                    ): ?>

                                        <span>

                                            <i class="bi bi-tools"></i>

                                            <?= (int)$solicitud[
                                                'intervenciones'
                                            ] ?>

                                        </span>

                                    <?php endif; ?>


                                </div>


                                <?php if (
                                    $solicitud['estado']
                                    === 'Pendiente'
                                    &&
                                    !empty(
                                        $solicitud[
                                            'motivo_pendiente'
                                        ]
                                    )
                                ): ?>

                                    <div class="pending-reason">

                                        <?= e(
                                            $solicitud[
                                                'motivo_pendiente'
                                            ]
                                        ) ?>

                                    </div>

                                <?php endif; ?>

                            </td>


                            <!-- DOCENTE -->

                            <td>

                                <strong class="small">

                                    <?= e(
                                        $solicitud[
                                            'solicitante'
                                        ]
                                    ) ?>

                                </strong>

                                <div class="small-meta">

                                    <?= e(
                                        $solicitud[
                                            'correo'
                                        ]
                                    ) ?>

                                </div>

                            </td>


                            <!-- SECTOR -->

                            <td>

                                <?php if (
                                    !empty(
                                        $solicitud[
                                            'sector'
                                        ]
                                    )
                                ): ?>

                                    <div class="small-meta">

                                        <i class="bi bi-geo-alt"></i>

                                        <?= e(
                                            $solicitud[
                                                'sector'
                                            ]
                                        ) ?>

                                    </div>

                                <?php else: ?>

                                    <span class="text-muted small">
                                        -
                                    </span>

                                <?php endif; ?>

                            </td>


                            <!-- TÉCNICO -->

                            <td>

                                <?php if (
                                    !empty(
                                        $solicitud[
                                            'tecnico'
                                        ]
                                    )
                                ): ?>

                                    <div class="technician">

                                        <div class="technician-icon">

                                            <i class="bi bi-person-gear"></i>

                                        </div>

                                        <strong>

                                            <?= e(
                                                $solicitud[
                                                    'tecnico'
                                                ]
                                            ) ?>

                                        </strong>

                                    </div>

                                <?php else: ?>

                                    <span class="no-technician">

                                        <i class="bi bi-person-dash"></i>

                                        Sin asignar

                                    </span>

                                <?php endif; ?>

                            </td>


                            <!-- PRIORIDAD -->

                            <td>

                                <span
                                    class="badge <?= e(
                                        clasePrioridad(
                                            $solicitud[
                                                'prioridad'
                                            ]
                                        )
                                    ) ?> ticket-badge"
                                >

                                    <?= e(
                                        $solicitud[
                                            'prioridad'
                                        ]
                                    ) ?>

                                </span>

                            </td>


                            <!-- ESTADO -->

                            <td>

                                <span
                                    class="badge <?= e(
                                        claseEstado(
                                            $solicitud[
                                                'estado'
                                            ]
                                        )
                                    ) ?> ticket-badge"
                                >

                                    <?= e(
                                        $solicitud[
                                            'estado'
                                        ]
                                    ) ?>

                                </span>

                            </td>


                            <!-- FECHA -->

                            <td>

                                <div class="small-meta">

                                    <?= e(
                                        fechaCorta(
                                            $solicitud[
                                                'fecha_creacion'
                                            ]
                                        )
                                    ) ?>

                                </div>

                                <div class="small-meta">

                                    <?= e(
                                        tiempoTranscurrido(
                                            $solicitud[
                                                'fecha_creacion'
                                            ]
                                        )
                                    ) ?>

                                </div>

                            </td>


                            <!-- ACCIONES -->

                            <td>

                                <div class="actions">


                                    <!-- VER -->

                                    <a
                                        href="<?= url(
                                            'ver_solicitud.php?id='
                                            .
                                            (int)$solicitud[
                                                'id_solicitud'
                                            ]
                                        ) ?>"
                                        class="action-btn action-view"
                                        title="Ver solicitud"
                                    >

                                        <i class="bi bi-eye"></i>

                                    </a>


                                    <!-- ASIGNAR -->

                                    <a
                                        href="<?= url(
                                            'admin/asignar.php?id='
                                            .
                                            (int)$solicitud[
                                                'id_solicitud'
                                            ]
                                        ) ?>"
                                        class="action-btn action-assign"
                                        title="Asignar técnico"
                                    >

                                        <i class="bi bi-person-check"></i>

                                    </a>


                                    <!-- INTERVENCIÓN -->

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
                                                (int)$solicitud[
                                                    'id_solicitud'
                                                ]
                                            ) ?>"
                                            class="action-btn action-work"
                                            title="Registrar intervención"
                                        >

                                            <i class="bi bi-tools"></i>

                                        </a>

                                    <?php endif; ?>


                                </div>

                            </td>


                        </tr>


                    <?php endforeach; ?>


                    </tbody>

                </table>

            </div>


            <!-- =================================================
                 MÓVIL
            ================================================== -->

            <div class="mobile-ticket">


                <?php foreach (
                    $solicitudes
                    as $solicitud
                ): ?>


                    <article class="mobile-ticket-item">


                        <div class="mobile-top">

                            <div>

                                <div class="ticket-number">

                                    <?= e(
                                        numeroTicket(
                                            (int)$solicitud[
                                                'id_solicitud'
                                            ]
                                        )
                                    ) ?>

                                </div>


                                <a
                                    href="<?= url(
                                        'ver_solicitud.php?id='
                                        .
                                        (int)$solicitud[
                                            'id_solicitud'
                                        ]
                                    ) ?>"
                                    class="ticket-title"
                                >

                                    <?= e(
                                        $solicitud[
                                            'titulo'
                                        ]
                                    ) ?>

                                </a>

                            </div>


                            <div class="mobile-badges">

                                <span
                                    class="badge <?= e(
                                        claseEstado(
                                            $solicitud[
                                                'estado'
                                            ]
                                        )
                                    ) ?> ticket-badge"
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
                                    ) ?> ticket-badge"
                                >

                                    <?= e(
                                        $solicitud[
                                            'prioridad'
                                        ]
                                    ) ?>

                                </span>

                            </div>

                        </div>


                        <div class="mobile-details">

                            <span>

                                <i class="<?= e(
                                    iconoTipo(
                                        $solicitud[
                                            'tipo'
                                        ]
                                    )
                                ) ?>"></i>

                                <?= e(
                                    nombreTipo(
                                        $solicitud[
                                            'tipo'
                                        ]
                                    )
                                ) ?>

                            </span>


                            <span>

                                <i class="bi bi-person"></i>

                                <?= e(
                                    $solicitud[
                                        'solicitante'
                                    ]
                                ) ?>

                            </span>


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


                            <span>

                                <i class="bi bi-calendar3"></i>

                                <?= e(
                                    fechaCorta(
                                        $solicitud[
                                            'fecha_creacion'
                                        ]
                                    )
                                ) ?>

                            </span>

                        </div>


                        <div class="mt-2 small">

                            <strong>
                                Técnico:
                            </strong>

                            <?= !empty(
                                $solicitud[
                                    'tecnico'
                                ]
                            )
                                ? e(
                                    $solicitud[
                                        'tecnico'
                                    ]
                                )
                                : 'Sin asignar'
                            ?>

                        </div>


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

                            <div class="pending-reason">

                                <strong>
                                    Pendiente:
                                </strong>

                                <?= e(
                                    $solicitud[
                                        'motivo_pendiente'
                                    ]
                                ) ?>

                            </div>

                        <?php endif; ?>


                        <div class="mobile-actions">


                            <a
                                href="<?= url(
                                    'ver_solicitud.php?id='
                                    .
                                    (int)$solicitud[
                                        'id_solicitud'
                                    ]
                                ) ?>"
                                class="action-btn action-view"
                                title="Ver"
                            >

                                <i class="bi bi-eye"></i>

                            </a>


                            <a
                                href="<?= url(
                                    'admin/asignar.php?id='
                                    .
                                    (int)$solicitud[
                                        'id_solicitud'
                                    ]
                                ) ?>"
                                class="action-btn action-assign"
                                title="Asignar"
                            >

                                <i class="bi bi-person-check"></i>

                            </a>


                            <a
                                href="<?= url(
                                    'tecnico/intervenir.php?id='
                                    .
                                    (int)$solicitud[
                                        'id_solicitud'
                                    ]
                                ) ?>"
                                class="action-btn action-work"
                                title="Intervenir"
                            >

                                <i class="bi bi-tools"></i>

                            </a>


                        </div>


                    </article>


                <?php endforeach; ?>


            </div>


            <!-- =================================================
                 PAGINACIÓN
            ================================================== -->

            <?php if (
                $totalPaginas > 1
            ): ?>

                <div
                    class="p-3
                           border-top
                           d-flex
                           justify-content-center"
                >

                    <nav>

                        <ul class="pagination">


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
                                            urlPaginaAdminSolicitudes(
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

                            $inicio =
                                max(
                                    1,
                                    $pagina - 2
                                );

                            $fin =
                                min(
                                    $totalPaginas,
                                    $pagina + 2
                                );

                            ?>


                            <?php for (
                                $i = $inicio;
                                $i <= $fin;
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
                                            urlPaginaAdminSolicitudes(
                                                $i
                                            )
                                        ) ?>"
                                    >

                                        <?= $i ?>

                                    </a>

                                </li>

                            <?php endfor; ?>


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
                                            urlPaginaAdminSolicitudes(
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


        <?php endif; ?>


    </div>


</div>


<?php

require_once __DIR__
    . '/../includes/footer.php';

?>