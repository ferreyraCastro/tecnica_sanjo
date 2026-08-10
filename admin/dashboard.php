<?php
// ============================================================
// COLEGIO SAN JOSÉ
// Sistema de Gestión Técnica
// Archivo: /tecnica/admin/dashboard.php
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
// ESTADÍSTICAS DE SOLICITUDES
// ============================================================

$estadisticas =
    obtenerEstadisticas(
        $conexion
    );


// ============================================================
// ESTADÍSTICAS DE USUARIOS
// ============================================================

$stmtUsuarios =
    $conexion->query("
        SELECT

            COUNT(*) AS total,

            SUM(
                CASE
                    WHEN rol = 'Docente'
                    THEN 1
                    ELSE 0
                END
            ) AS docentes,

            SUM(
                CASE
                    WHEN rol = 'Tecnico'
                    THEN 1
                    ELSE 0
                END
            ) AS tecnicos,

            SUM(
                CASE
                    WHEN rol = 'Administrador'
                    THEN 1
                    ELSE 0
                END
            ) AS administradores,

            SUM(
                CASE
                    WHEN estado = 'Activo'
                    THEN 1
                    ELSE 0
                END
            ) AS activos,

            SUM(
                CASE
                    WHEN estado = 'Inactivo'
                    THEN 1
                    ELSE 0
                END
            ) AS inactivos

        FROM usuarios
    ");


$usuariosStats =
    $stmtUsuarios->fetch(
        PDO::FETCH_ASSOC
    );


// ============================================================
// ESTADÍSTICAS MEJORAS
// ============================================================

$stmtMejoras =
    $conexion->query("
        SELECT

            COUNT(*) AS total,

            SUM(
                CASE
                    WHEN estado = 'Pendiente autorizacion'
                    THEN 1
                    ELSE 0
                END
            ) AS pendientes,

            SUM(
                CASE
                    WHEN estado = 'Aprobada'
                    THEN 1
                    ELSE 0
                END
            ) AS aprobadas,

            SUM(
                CASE
                    WHEN estado = 'En ejecucion'
                    THEN 1
                    ELSE 0
                END
            ) AS ejecucion,

            SUM(
                CASE
                    WHEN estado = 'Realizada'
                    THEN 1
                    ELSE 0
                END
            ) AS realizadas

        FROM mejoras
    ");


$mejorasStats =
    $stmtMejoras->fetch(
        PDO::FETCH_ASSOC
    );


// ============================================================
// SOLICITUDES RECIENTES
// ============================================================

$stmtRecientes =
    $conexion->query("
        SELECT

            s.id_solicitud,
            s.tipo,
            s.titulo,
            s.prioridad,
            s.estado,
            s.fecha_creacion,

            CONCAT(
                u.nombre,
                ' ',
                u.apellido
            ) AS solicitante,

            sec.nombre AS sector,

            c.nombre AS categoria

        FROM solicitudes s

        INNER JOIN usuarios u
            ON s.id_usuario = u.id_usuario

        LEFT JOIN sectores sec
            ON s.id_sector = sec.id_sector

        LEFT JOIN categorias c
            ON s.id_categoria = c.id_categoria

        ORDER BY
            s.fecha_creacion DESC

        LIMIT 8
    ");


$solicitudesRecientes =
    $stmtRecientes->fetchAll(
        PDO::FETCH_ASSOC
    );


// ============================================================
// SOLICITUDES PENDIENTES
// ============================================================

$stmtPendientes =
    $conexion->query("
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
            ON s.id_usuario = u.id_usuario

        LEFT JOIN sectores sec
            ON s.id_sector = sec.id_sector

        WHERE s.estado = 'Pendiente'

        ORDER BY

            CASE s.prioridad

                WHEN 'Urgente' THEN 1
                WHEN 'Alta' THEN 2
                WHEN 'Normal' THEN 3
                WHEN 'Baja' THEN 4

                ELSE 5

            END,

            s.fecha_creacion ASC

        LIMIT 6
    ");


$pendientes =
    $stmtPendientes->fetchAll(
        PDO::FETCH_ASSOC
    );


// ============================================================
// URGENTES ABIERTAS
// ============================================================

$stmtUrgentes =
    $conexion->query("
        SELECT

            s.id_solicitud,
            s.titulo,
            s.tipo,
            s.estado,
            s.fecha_creacion,

            sec.nombre AS sector

        FROM solicitudes s

        LEFT JOIN sectores sec
            ON s.id_sector = sec.id_sector

        WHERE s.prioridad = 'Urgente'

        AND s.estado NOT IN
        (
            'Resuelta',
            'Cerrada',
            'Cancelada'
        )

        ORDER BY
            s.fecha_creacion ASC

        LIMIT 5
    ");


$urgentes =
    $stmtUrgentes->fetchAll(
        PDO::FETCH_ASSOC
    );


// ============================================================
// MEJORAS PENDIENTES DE AUTORIZACIÓN
// ============================================================

$stmtMejorasPendientes =
    $conexion->query("
        SELECT

            m.id_mejora,
            m.titulo,
            m.tipo,
            m.prioridad,
            m.costo_estimado,
            m.fecha_creacion,

            sec.nombre AS sector

        FROM mejoras m

        LEFT JOIN sectores sec
            ON m.id_sector = sec.id_sector

        WHERE
            m.estado = 'Pendiente autorizacion'

        ORDER BY

            CASE m.prioridad

                WHEN 'Urgente' THEN 1
                WHEN 'Alta' THEN 2
                WHEN 'Normal' THEN 3
                WHEN 'Baja' THEN 4

                ELSE 5

            END,

            m.fecha_creacion ASC

        LIMIT 5
    ");


$mejorasPendientes =
    $stmtMejorasPendientes->fetchAll(
        PDO::FETCH_ASSOC
    );


// ============================================================
// HORARIOS
// ============================================================

$horariosInformatica =
    obtenerHorarios(
        $conexion,
        'Informatica'
    );


$horariosMantenimiento =
    obtenerHorarios(
        $conexion,
        'Mantenimiento'
    );


// ============================================================
// FLASH
// ============================================================

$flash =
    obtenerFlash();


// ============================================================
// HEADER GENERAL
// ============================================================

require_once __DIR__
    . '/../includes/header.php';

?>


<style>

.admin-wrapper {

    max-width: 1550px;

    margin: 0 auto;

    padding:
        5px 12px
        45px;

}


/* ============================================================
   HERO
============================================================ */

.admin-hero {

    position: relative;

    overflow: hidden;

    background:
        linear-gradient(
            135deg,
            #760000,
            #B12626
        );

    color: #FFFFFF;

    padding: 30px;

    border-radius: 22px;

    margin-bottom: 24px;

    box-shadow:
        0 9px 30px
        rgba(118,0,0,.16);

}


.admin-hero::after {

    content: "";

    position: absolute;

    width: 300px;

    height: 300px;

    right: -100px;

    top: -150px;

    border-radius: 50%;

    background:
        rgba(255,255,255,.06);

}


.admin-hero-content {

    position: relative;

    z-index: 2;

}


.admin-hero h1 {

    font-size: 29px;

    font-weight: 800;

    margin:
        0 0 7px;

}


.admin-hero p {

    margin: 0;

    color:
        rgba(255,255,255,.78);

}


.admin-badge {

    display: inline-flex;

    align-items: center;

    gap: 6px;

    margin-top: 13px;

    padding:
        6px 11px;

    border-radius: 25px;

    background:
        rgba(255,255,255,.13);

    font-size: 12px;

    font-weight: 700;

}


.btn-sistema {

    position: relative;

    z-index: 2;

    display: inline-flex;

    align-items: center;

    justify-content: center;

    gap: 7px;

    padding:
        10px 17px;

    border-radius: 10px;

    background: #FFFFFF;

    color: #760000;

    text-decoration: none;

    font-weight: 700;

}


.btn-sistema:hover {

    background: #F4F4F4;

    color: #B12626;

}


/* ============================================================
   ESTADÍSTICAS
============================================================ */

.stat-card {

    height: 100%;

    padding: 19px;

    background: #FFFFFF;

    border:
        1px solid #ECECEC;

    border-radius: 17px;

    box-shadow:
        0 5px 17px
        rgba(0,0,0,.045);

    transition:
        transform .2s ease;

}


.stat-card:hover {

    transform:
        translateY(-3px);

}


.stat-icon {

    width: 44px;

    height: 44px;

    border-radius: 12px;

    display: flex;

    justify-content: center;

    align-items: center;

    margin-bottom: 12px;

    font-size: 19px;

}


.stat-number {

    font-size: 29px;

    line-height: 1;

    font-weight: 800;

    color: #333333;

}


.stat-label {

    margin-top: 6px;

    font-size: 12px;

    color: #7A7A7A;

    font-weight: 600;

}


.stat-total {

    background: #F2E4E4;
    color: #760000;

}


.stat-nueva {

    background: #E8F1FF;
    color: #0D6EFD;

}


.stat-proceso {

    background: #FFF3CD;
    color: #8A6700;

}


.stat-pendiente {

    background: #FFE4E4;
    color: #B12626;

}


.stat-resuelta {

    background: #E0F4E8;
    color: #198754;

}


.stat-urgente {

    background: #760000;
    color: #FFFFFF;

}


/* ============================================================
   CARD
============================================================ */

.admin-card {

    height: 100%;

    background: #FFFFFF;

    border:
        1px solid #ECECEC;

    border-radius: 18px;

    overflow: hidden;

    box-shadow:
        0 5px 19px
        rgba(0,0,0,.05);

}


.admin-card-header {

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 12px;

    padding:
        17px 20px;

    border-bottom:
        1px solid #EEEEEE;

}


.admin-card-header h5 {

    margin: 0;

    color: #760000;

    font-size: 16px;

    font-weight: 800;

}


.admin-card-body {

    padding: 20px;

}


.header-link {

    color: #760000;

    text-decoration: none;

    font-size: 12px;

    font-weight: 700;

}


.header-link:hover {

    color: #B12626;

}


/* ============================================================
   ACCESO ADMIN
============================================================ */

.admin-access {

    display: flex;

    align-items: center;

    gap: 13px;

    padding: 14px;

    margin-bottom: 9px;

    border:
        1px solid #ECECEC;

    border-radius: 12px;

    color: #333333;

    text-decoration: none;

    transition:
        .2s ease;

}


.admin-access:last-child {

    margin-bottom: 0;

}


.admin-access:hover {

    background: #FFF7F7;

    border-color: #EACFCF;

    color: #760000;

}


.admin-access-icon {

    min-width: 42px;

    width: 42px;

    height: 42px;

    border-radius: 11px;

    display: flex;

    justify-content: center;

    align-items: center;

    color: #FFFFFF;

    background:
        linear-gradient(
            135deg,
            #760000,
            #B12626
        );

    font-size: 18px;

}


.admin-access strong {

    display: block;

    font-size: 13px;

}


.admin-access small {

    display: block;

    color: #888888;

    font-size: 11px;

    margin-top: 2px;

}


/* ============================================================
   SOLICITUDES
============================================================ */

.ticket-item {

    padding:
        13px 0;

    border-bottom:
        1px solid #EEEEEE;

}


.ticket-item:first-child {

    padding-top: 0;

}


.ticket-item:last-child {

    padding-bottom: 0;

    border-bottom: 0;

}


.ticket-id {

    color: #969696;

    font-size: 10px;

    font-weight: 700;

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


.ticket-meta {

    display: flex;

    flex-wrap: wrap;

    gap:
        5px 12px;

    margin-top: 5px;

    color: #818181;

    font-size: 11px;

}


.ticket-meta i {

    color: #B12626;

}


.ticket-badge {

    border-radius: 20px;

    padding:
        5px 8px;

    font-size: 10px;

}


/* ============================================================
   ALERTA URGENTE
============================================================ */

.urgente-item {

    padding:
        12px;

    margin-bottom: 9px;

    background: #FFF4F4;

    border:
        1px solid #F1D4D4;

    border-left:
        4px solid #B12626;

    border-radius: 10px;

}


.urgente-item:last-child {

    margin-bottom: 0;

}


.urgente-item a {

    color: #760000;

    text-decoration: none;

    font-weight: 700;

    font-size: 13px;

}


.urgente-item a:hover {

    color: #B12626;

}


/* ============================================================
   PENDIENTE
============================================================ */

.pendiente-item {

    padding:
        13px 0;

    border-bottom:
        1px solid #EEEEEE;

}


.pendiente-item:last-child {

    border-bottom: 0;

    padding-bottom: 0;

}


.pendiente-title {

    font-size: 13px;

    font-weight: 700;

    color: #333333;

}


.pendiente-motivo {

    background: #FFF8DF;

    border-radius: 8px;

    padding:
        8px 10px;

    margin-top: 7px;

    color: #685600;

    font-size: 11px;

}


/* ============================================================
   USUARIOS
============================================================ */

.usuario-stats {

    display: grid;

    grid-template-columns:
        repeat(2,1fr);

    gap: 11px;

}


.usuario-stat {

    padding: 13px;

    background: #F8F8F8;

    border-radius: 11px;

}


.usuario-stat-label {

    color: #909090;

    font-size: 10px;

    text-transform: uppercase;

    font-weight: 700;

}


.usuario-stat-value {

    margin-top: 3px;

    color: #333333;

    font-size: 21px;

    font-weight: 800;

}


/* ============================================================
   MEJORA
============================================================ */

.mejora-item {

    padding:
        12px 0;

    border-bottom:
        1px solid #EEEEEE;

}


.mejora-item:last-child {

    border-bottom: 0;

}


.mejora-title {

    color: #333333;

    font-size: 13px;

    font-weight: 700;

}


.mejora-meta {

    margin-top: 4px;

    color: #818181;

    font-size: 11px;

}


/* ============================================================
   EMPTY
============================================================ */

.empty-state {

    padding:
        25px 10px;

    text-align: center;

    color: #909090;

    font-size: 12px;

}


.empty-state i {

    display: block;

    font-size: 34px;

    color: #D0D0D0;

    margin-bottom: 7px;

}


/* ============================================================
   RESPONSIVE
============================================================ */

@media
(max-width: 767px) {

    .admin-hero {

        padding: 23px 20px;

    }


    .admin-hero h1 {

        font-size: 23px;

    }


    .hero-action {

        margin-top: 18px;

    }


    .btn-sistema {

        width: 100%;

    }

}

</style>


<div class="admin-wrapper">


    <!-- =====================================================
         HERO
    ====================================================== -->

    <section class="admin-hero">

        <div class="row align-items-center">

            <div class="col-lg-8">

                <div class="admin-hero-content">

                    <h1>

                        <i class="bi bi-shield-check me-1"></i>

                        Panel de administración

                    </h1>

                    <p>

                        Gestión general de solicitudes,
                        usuarios, intervenciones,
                        horarios y propuestas de mejora.

                    </p>


                    <div class="admin-badge">

                        <i class="bi bi-person-check"></i>

                        <?= e(
                            usuarioNombre()
                        ) ?>

                        · Administrador

                    </div>

                </div>

            </div>


            <div
                class="col-lg-4
                       text-lg-end
                       hero-action"
            >

                <a
                    href="<?= url(
                        'dashboard.php'
                    ) ?>"
                    class="btn-sistema"
                >

                    <i class="bi bi-arrow-left"></i>

                    Dashboard general

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
                $flash['tipo'] === 'success'
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
         ESTADÍSTICAS SOLICITUDES
    ====================================================== -->

    <div class="row g-3 mb-4">


        <div class="col-6 col-md-4 col-xl-2">

            <div class="stat-card">

                <div class="stat-icon stat-total">

                    <i class="bi bi-ticket-detailed"></i>

                </div>

                <div class="stat-number">

                    <?= $estadisticas['total'] ?>

                </div>

                <div class="stat-label">

                    Solicitudes totales

                </div>

            </div>

        </div>


        <div class="col-6 col-md-4 col-xl-2">

            <div class="stat-card">

                <div class="stat-icon stat-nueva">

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

                <div class="stat-icon stat-proceso">

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

                <div class="stat-icon stat-pendiente">

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

                <div class="stat-icon stat-resuelta">

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

                <div class="stat-icon stat-urgente">

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
         PRIMERA FILA
    ====================================================== -->

    <div class="row g-4">


        <!-- =================================================
             ACCESOS ADMINISTRATIVOS
        ================================================== -->

        <div class="col-xl-4">

            <div class="admin-card">

                <div class="admin-card-header">

                    <h5>

                        <i class="bi bi-grid me-2"></i>

                        Administración

                    </h5>

                </div>


                <div class="admin-card-body">


                    <a
                        href="<?= url(
                            'admin/usuarios.php'
                        ) ?>"
                        class="admin-access"
                    >

                        <div class="admin-access-icon">

                            <i class="bi bi-people"></i>

                        </div>

                        <div>

                            <strong>
                                Usuarios
                            </strong>

                            <small>
                                Docentes, técnicos y administradores
                            </small>

                        </div>

                    </a>


                    <a
                        href="<?= url(
                            'solicitudes.php'
                        ) ?>"
                        class="admin-access"
                    >

                        <div class="admin-access-icon">

                            <i class="bi bi-ticket-detailed"></i>

                        </div>

                        <div>

                            <strong>
                                Solicitudes
                            </strong>

                            <small>
                                Gestionar todos los tickets
                            </small>

                        </div>

                    </a>


                    <a
                        href="<?= url(
                            'admin/horarios.php'
                        ) ?>"
                        class="admin-access"
                    >

                        <div class="admin-access-icon">

                            <i class="bi bi-calendar-week"></i>

                        </div>

                        <div>

                            <strong>
                                Horarios
                            </strong>

                            <small>
                                Informática y mantenimiento
                            </small>

                        </div>

                    </a>


                    <a
                        href="<?= url(
                            'mejoras.php'
                        ) ?>"
                        class="admin-access"
                    >

                        <div class="admin-access-icon">

                            <i class="bi bi-lightbulb"></i>

                        </div>

                        <div>

                            <strong>
                                Propuestas de mejora
                            </strong>

                            <small>
                                Evaluación y autorizaciones
                            </small>

                        </div>

                    </a>


                    <a
                        href="<?= url(
                            'admin/reportes.php'
                        ) ?>"
                        class="admin-access"
                    >

                        <div class="admin-access-icon">

                            <i class="bi bi-bar-chart"></i>

                        </div>

                        <div>

                            <strong>
                                Reportes
                            </strong>

                            <small>
                                Estadísticas e informes
                            </small>

                        </div>

                    </a>


                    <a
                        href="<?= url(
                            'admin/sectores.php'
                        ) ?>"
                        class="admin-access"
                    >

                        <div class="admin-access-icon">

                            <i class="bi bi-building"></i>

                        </div>

                        <div>

                            <strong>
                                Sectores y aulas
                            </strong>

                            <small>
                                Administrar espacios del colegio
                            </small>

                        </div>

                    </a>


                    <a
                        href="<?= url(
                            'admin/repuestos.php'
                        ) ?>"
                        class="admin-access"
                    >

                        <div class="admin-access-icon">

                            <i class="bi bi-box-seam"></i>

                        </div>

                        <div>

                            <strong>
                                Catálogo de repuestos
                            </strong>

                            <small>
                                Nombre, stock, foto y costo
                            </small>

                        </div>

                    </a>


                    <a
                        href="<?= url(
                            'admin/horarios_tecnicos.php'
                        ) ?>"
                        class="admin-access"
                    >

                        <div class="admin-access-icon">

                            <i class="bi bi-person-workspace"></i>

                        </div>

                        <div>

                            <strong>
                                Horarios de técnicos
                            </strong>

                            <small>
                                Días y horas de trabajo por técnico
                            </small>

                        </div>

                    </a>


                    <a
                        href="<?= url(
                            'admin/horas_extra.php'
                        ) ?>"
                        class="admin-access"
                    >

                        <div class="admin-access-icon">

                            <i class="bi bi-hourglass-split"></i>

                        </div>

                        <div>

                            <strong>
                                Horas extra
                            </strong>

                            <small>
                                Compensaciones y horas fuera de horario
                            </small>

                        </div>

                    </a>


                </div>

            </div>

        </div>



        <!-- =================================================
             SOLICITUDES RECIENTES
        ================================================== -->

        <div class="col-xl-8">

            <div class="admin-card">

                <div class="admin-card-header">

                    <h5>

                        <i class="bi bi-clock-history me-2"></i>

                        Solicitudes recientes

                    </h5>


                    <a
                        href="<?= url(
                            'solicitudes.php'
                        ) ?>"
                        class="header-link"
                    >

                        Ver todas

                        <i class="bi bi-arrow-right"></i>

                    </a>

                </div>


                <div class="admin-card-body">


                    <?php if (
                        empty(
                            $solicitudesRecientes
                        )
                    ): ?>

                        <div class="empty-state">

                            <i class="bi bi-inbox"></i>

                            No hay solicitudes registradas.

                        </div>


                    <?php else: ?>


                        <?php foreach (
                            $solicitudesRecientes
                            as $solicitud
                        ): ?>

                            <div class="ticket-item">


                                <div
                                    class="d-flex
                                           justify-content-between
                                           align-items-start
                                           gap-3"
                                >

                                    <div class="flex-grow-1">

                                        <div class="ticket-id">

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


                                        <div class="ticket-meta">

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
                                                    fechaArgentina(
                                                        $solicitud[
                                                            'fecha_creacion'
                                                        ]
                                                    )
                                                ) ?>

                                            </span>

                                        </div>

                                    </div>


                                    <div class="text-end">

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


                                        <div class="mt-1">

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

                                </div>


                            </div>

                        <?php endforeach; ?>


                    <?php endif; ?>


                </div>

            </div>

        </div>


    </div>



    <!-- =====================================================
         SEGUNDA FILA
    ====================================================== -->

    <div class="row g-4 mt-1">


        <!-- URGENTES -->

        <div class="col-lg-6 col-xl-4">

            <div class="admin-card">

                <div class="admin-card-header">

                    <h5>

                        <i class="bi bi-exclamation-triangle me-2"></i>

                        Solicitudes urgentes

                    </h5>

                    <span class="badge bg-danger">

                        <?= count(
                            $urgentes
                        ) ?>

                    </span>

                </div>


                <div class="admin-card-body">


                    <?php if (
                        empty(
                            $urgentes
                        )
                    ): ?>

                        <div class="empty-state">

                            <i class="bi bi-check-circle"></i>

                            No hay solicitudes urgentes abiertas.

                        </div>


                    <?php else: ?>


                        <?php foreach (
                            $urgentes
                            as $urgente
                        ): ?>

                            <div class="urgente-item">

                                <a
                                    href="<?= url(
                                        'ver_solicitud.php?id='
                                        .
                                        (int)$urgente[
                                            'id_solicitud'
                                        ]
                                    ) ?>"
                                >

                                    <?= e(
                                        numeroTicket(
                                            (int)$urgente[
                                                'id_solicitud'
                                            ]
                                        )
                                    ) ?>

                                    -

                                    <?= e(
                                        $urgente[
                                            'titulo'
                                        ]
                                    ) ?>

                                </a>


                                <div class="ticket-meta">

                                    <?php if (
                                        !empty(
                                            $urgente[
                                                'sector'
                                            ]
                                        )
                                    ): ?>

                                        <span>

                                            <i class="bi bi-geo-alt"></i>

                                            <?= e(
                                                $urgente[
                                                    'sector'
                                                ]
                                            ) ?>

                                        </span>

                                    <?php endif; ?>


                                    <span>

                                        <?= e(
                                            $urgente[
                                                'estado'
                                            ]
                                        ) ?>

                                    </span>

                                </div>

                            </div>

                        <?php endforeach; ?>


                    <?php endif; ?>


                </div>

            </div>

        </div>



        <!-- PENDIENTES -->

        <div class="col-lg-6 col-xl-4">

            <div class="admin-card">

                <div class="admin-card-header">

                    <h5>

                        <i class="bi bi-hourglass-split me-2"></i>

                        Pendientes

                    </h5>


                    <a
                        href="<?= url(
                            'solicitudes.php?estado=Pendiente'
                        ) ?>"
                        class="header-link"
                    >

                        Ver todos

                    </a>

                </div>


                <div class="admin-card-body">


                    <?php if (
                        empty(
                            $pendientes
                        )
                    ): ?>

                        <div class="empty-state">

                            <i class="bi bi-check-circle"></i>

                            No hay solicitudes pendientes.

                        </div>


                    <?php else: ?>


                        <?php foreach (
                            $pendientes
                            as $pendiente
                        ): ?>

                            <div class="pendiente-item">

                                <div class="pendiente-title">

                                    <a
                                        href="<?= url(
                                            'ver_solicitud.php?id='
                                            .
                                            (int)$pendiente[
                                                'id_solicitud'
                                            ]
                                        ) ?>"
                                        class="ticket-title"
                                    >

                                        <?= e(
                                            numeroTicket(
                                                (int)$pendiente[
                                                    'id_solicitud'
                                                ]
                                            )
                                        ) ?>

                                        -

                                        <?= e(
                                            $pendiente[
                                                'titulo'
                                            ]
                                        ) ?>

                                    </a>

                                </div>


                                <div class="ticket-meta">

                                    <span>

                                        <i class="bi bi-person"></i>

                                        <?= e(
                                            $pendiente[
                                                'solicitante'
                                            ]
                                        ) ?>

                                    </span>


                                    <?php if (
                                        !empty(
                                            $pendiente[
                                                'sector'
                                            ]
                                        )
                                    ): ?>

                                        <span>

                                            <i class="bi bi-geo-alt"></i>

                                            <?= e(
                                                $pendiente[
                                                    'sector'
                                                ]
                                            ) ?>

                                        </span>

                                    <?php endif; ?>

                                </div>


                                <?php if (
                                    !empty(
                                        $pendiente[
                                            'motivo_pendiente'
                                        ]
                                    )
                                ): ?>

                                    <div class="pendiente-motivo">

                                        <?= e(
                                            $pendiente[
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



        <!-- USUARIOS -->

        <div class="col-xl-4">

            <div class="admin-card">

                <div class="admin-card-header">

                    <h5>

                        <i class="bi bi-people me-2"></i>

                        Usuarios

                    </h5>


                    <a
                        href="<?= url(
                            'admin/usuarios.php'
                        ) ?>"
                        class="header-link"
                    >

                        Administrar

                    </a>

                </div>


                <div class="admin-card-body">

                    <div class="usuario-stats">


                        <div class="usuario-stat">

                            <div class="usuario-stat-label">
                                Total
                            </div>

                            <div class="usuario-stat-value">

                                <?= (int)(
                                    $usuariosStats[
                                        'total'
                                    ]
                                    ?? 0
                                ) ?>

                            </div>

                        </div>


                        <div class="usuario-stat">

                            <div class="usuario-stat-label">
                                Activos
                            </div>

                            <div class="usuario-stat-value">

                                <?= (int)(
                                    $usuariosStats[
                                        'activos'
                                    ]
                                    ?? 0
                                ) ?>

                            </div>

                        </div>


                        <div class="usuario-stat">

                            <div class="usuario-stat-label">
                                Docentes
                            </div>

                            <div class="usuario-stat-value">

                                <?= (int)(
                                    $usuariosStats[
                                        'docentes'
                                    ]
                                    ?? 0
                                ) ?>

                            </div>

                        </div>


                        <div class="usuario-stat">

                            <div class="usuario-stat-label">
                                Técnicos
                            </div>

                            <div class="usuario-stat-value">

                                <?= (int)(
                                    $usuariosStats[
                                        'tecnicos'
                                    ]
                                    ?? 0
                                ) ?>

                            </div>

                        </div>


                    </div>


                    <a
                        href="<?= url(
                            'admin/usuarios.php'
                        ) ?>"
                        class="btn btn-sanjo w-100 mt-3"
                    >

                        <i class="bi bi-person-plus me-1"></i>

                        Gestionar usuarios

                    </a>

                </div>

            </div>

        </div>


    </div>



    <!-- =====================================================
         TERCERA FILA
    ====================================================== -->

    <div class="row g-4 mt-1">


        <!-- MEJORAS -->

        <div class="col-lg-7">

            <div class="admin-card">

                <div class="admin-card-header">

                    <h5>

                        <i class="bi bi-lightbulb me-2"></i>

                        Mejoras pendientes de autorización

                    </h5>


                    <a
                        href="<?= url(
                            'mejoras.php?estado=Pendiente+autorizacion'
                        ) ?>"
                        class="header-link"
                    >

                        Ver mejoras

                    </a>

                </div>


                <div class="admin-card-body">


                    <?php if (
                        empty(
                            $mejorasPendientes
                        )
                    ): ?>

                        <div class="empty-state">

                            <i class="bi bi-lightbulb"></i>

                            No hay mejoras pendientes de autorización.

                        </div>


                    <?php else: ?>


                        <?php foreach (
                            $mejorasPendientes
                            as $mejora
                        ): ?>

                            <div class="mejora-item">

                                <div
                                    class="d-flex
                                           justify-content-between
                                           gap-3"
                                >

                                    <div>

                                        <div class="mejora-title">

                                            <?= e(
                                                $mejora[
                                                    'titulo'
                                                ]
                                            ) ?>

                                        </div>


                                        <div class="mejora-meta">

                                            <?= e(
                                                nombreTipo(
                                                    $mejora[
                                                        'tipo'
                                                    ]
                                                )
                                            ) ?>


                                            <?php if (
                                                !empty(
                                                    $mejora[
                                                        'sector'
                                                    ]
                                                )
                                            ): ?>

                                                ·

                                                <?= e(
                                                    $mejora[
                                                        'sector'
                                                    ]
                                                ) ?>

                                            <?php endif; ?>


                                            <?php if (
                                                !empty(
                                                    $mejora[
                                                        'costo_estimado'
                                                    ]
                                                )
                                            ): ?>

                                                ·

                                                <?= e(
                                                    formatoDinero(
                                                        $mejora[
                                                            'costo_estimado'
                                                        ]
                                                    )
                                                ) ?>

                                            <?php endif; ?>

                                        </div>

                                    </div>


                                    <span
                                        class="badge <?= e(
                                            clasePrioridad(
                                                $mejora[
                                                    'prioridad'
                                                ]
                                            )
                                        ) ?> ticket-badge"
                                    >

                                        <?= e(
                                            $mejora[
                                                'prioridad'
                                            ]
                                        ) ?>

                                    </span>

                                </div>

                            </div>

                        <?php endforeach; ?>


                    <?php endif; ?>


                </div>

            </div>

        </div>



        <!-- RESUMEN MEJORAS -->

        <div class="col-lg-5">

            <div class="admin-card">

                <div class="admin-card-header">

                    <h5>

                        <i class="bi bi-graph-up-arrow me-2"></i>

                        Estado de mejoras

                    </h5>

                </div>


                <div class="admin-card-body">

                    <div class="usuario-stats">


                        <div class="usuario-stat">

                            <div class="usuario-stat-label">
                                Total
                            </div>

                            <div class="usuario-stat-value">

                                <?= (int)(
                                    $mejorasStats[
                                        'total'
                                    ]
                                    ?? 0
                                ) ?>

                            </div>

                        </div>


                        <div class="usuario-stat">

                            <div class="usuario-stat-label">
                                Pend. autorización
                            </div>

                            <div class="usuario-stat-value">

                                <?= (int)(
                                    $mejorasStats[
                                        'pendientes'
                                    ]
                                    ?? 0
                                ) ?>

                            </div>

                        </div>


                        <div class="usuario-stat">

                            <div class="usuario-stat-label">
                                En ejecución
                            </div>

                            <div class="usuario-stat-value">

                                <?= (int)(
                                    $mejorasStats[
                                        'ejecucion'
                                    ]
                                    ?? 0
                                ) ?>

                            </div>

                        </div>


                        <div class="usuario-stat">

                            <div class="usuario-stat-label">
                                Realizadas
                            </div>

                            <div class="usuario-stat-value">

                                <?= (int)(
                                    $mejorasStats[
                                        'realizadas'
                                    ]
                                    ?? 0
                                ) ?>

                            </div>

                        </div>


                    </div>


                    <a
                        href="<?= url(
                            'mejoras.php'
                        ) ?>"
                        class="btn btn-sanjo w-100 mt-3"
                    >

                        <i class="bi bi-lightbulb me-1"></i>

                        Gestionar mejoras

                    </a>

                </div>

            </div>

        </div>


    </div>


</div>


<?php

require_once __DIR__
    . '/../includes/footer.php';

?>