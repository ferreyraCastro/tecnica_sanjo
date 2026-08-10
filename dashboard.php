<?php
// ============================================================
// COLEGIO SAN JOSÉ
// Sistema de Gestión Técnica
// Archivo: /tecnica/dashboard.php
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/funciones.php';
require_once __DIR__ . '/includes/auth.php';


// ============================================================
// REQUERIR LOGIN
// ============================================================

requerirLogin();


// ============================================================
// VERIFICAR QUE EL USUARIO SIGA ACTIVO
// ============================================================

if (!verificarUsuarioActivo($conexion)) {

    session_start();

    $_SESSION['mensaje_login'] =
        'Tu sesión finalizó o la cuenta se encuentra inactiva.';

    header(
        'Location: ' . url('login.php')
    );

    exit;
}


// ============================================================
// DATOS DEL USUARIO
// ============================================================

$idUsuario = usuarioId();

$rol = usuarioRol();

$nombreUsuario = usuarioNombre();


// ============================================================
// ESTADÍSTICAS
// ============================================================

if (esDocente()) {

    $estadisticas =
        obtenerEstadisticasUsuario(
            $conexion,
            (int)$idUsuario
        );

    $solicitudes =
        obtenerSolicitudesUsuario(
            $conexion,
            (int)$idUsuario,
            8
        );

} else {

    $estadisticas =
        obtenerEstadisticas(
            $conexion
        );

    $solicitudesTodas =
        obtenerSolicitudes(
            $conexion
        );

    $solicitudes =
        array_slice(
            $solicitudesTodas,
            0,
            10
        );
}


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
// NOTIFICACIONES
// ============================================================

$cantidadNotificaciones =
    contarNotificaciones(
        $conexion,
        (int)$idUsuario
    );


// ============================================================
// MEJORAS
// ============================================================

$mejoras = [];

if (esPersonalTecnico()) {

    $mejorasTodas =
        obtenerMejoras(
            $conexion
        );

    $mejoras =
        array_slice(
            $mejorasTodas,
            0,
            5
        );
}


// ============================================================
// HEADER
// ============================================================

require_once __DIR__ . '/includes/header.php';

?>


<style>

    /* ========================================================
       DASHBOARD SAN JOSÉ
    ======================================================== */

    .dashboard-wrapper {

        max-width: 1500px;

        margin: 0 auto;

        padding:
            5px 12px 40px;

    }


    /* ========================================================
       BIENVENIDA
    ======================================================== */

    .dashboard-hero {

        position: relative;

        overflow: hidden;

        background:
            linear-gradient(
                135deg,
                #760000 0%,
                #B12626 100%
            );

        color: #FFFFFF;

        border-radius: 22px;

        padding:
            30px 32px;

        margin-bottom: 25px;

        box-shadow:
            0 10px 30px
            rgba(118,0,0,.17);

    }


    .dashboard-hero::after {

        content: "";

        position: absolute;

        width: 240px;

        height: 240px;

        right: -80px;

        top: -100px;

        border-radius: 50%;

        background:
            rgba(255,255,255,.07);

    }


    .dashboard-hero-contenido {

        position: relative;

        z-index: 2;

    }


    .dashboard-hero h1 {

        font-size: 28px;

        font-weight: 800;

        margin-bottom: 7px;

    }


    .dashboard-hero p {

        margin: 0;

        color:
            rgba(255,255,255,.78);

    }


    .rol-usuario {

        display: inline-flex;

        align-items: center;

        gap: 6px;

        margin-top: 12px;

        padding:
            6px 11px;

        border-radius: 30px;

        background:
            rgba(255,255,255,.13);

        font-size: 12px;

        font-weight: 600;

    }


    .btn-nueva-solicitud {

        background: #FFFFFF;

        color: #760000;

        border: none;

        border-radius: 11px;

        padding:
            11px 18px;

        font-weight: 700;

        text-decoration: none;

        display: inline-flex;

        align-items: center;

        gap: 7px;

        transition:
            all .2s ease;

    }


    .btn-nueva-solicitud:hover {

        background: #F7F7F7;

        color: #B12626;

        transform:
            translateY(-2px);

    }


    /* ========================================================
       TARJETAS ESTADÍSTICAS
    ======================================================== */

    .stats-card {

        position: relative;

        overflow: hidden;

        background: #FFFFFF;

        border: 1px solid #EEEEEE;

        border-radius: 17px;

        padding: 21px;

        height: 100%;

        box-shadow:
            0 4px 16px
            rgba(0,0,0,.05);

        transition:
            all .2s ease;

    }


    .stats-card:hover {

        transform:
            translateY(-3px);

        box-shadow:
            0 9px 25px
            rgba(0,0,0,.09);

    }


    .stats-card-top {

        display: flex;

        align-items: center;

        justify-content: space-between;

    }


    .stats-icon {

        width: 47px;

        height: 47px;

        border-radius: 13px;

        display: flex;

        align-items: center;

        justify-content: center;

        font-size: 21px;

    }


    .stats-value {

        font-size: 31px;

        font-weight: 800;

        color: #303030;

        line-height: 1;

        margin-top: 15px;

    }


    .stats-label {

        margin-top: 6px;

        color: #777777;

        font-size: 13px;

        font-weight: 600;

    }


    .icon-total {

        background: #F3E4E4;
        color: #760000;

    }


    .icon-nueva {

        background: #E7F0FF;
        color: #0D6EFD;

    }


    .icon-proceso {

        background: #FFF5D8;
        color: #A66C00;

    }


    .icon-pendiente {

        background: #FFE5E5;
        color: #B12626;

    }


    .icon-resuelta {

        background: #DFF4E7;
        color: #198754;

    }


    .icon-urgente {

        background: #760000;
        color: #FFFFFF;

    }


    /* ========================================================
       CARDS
    ======================================================== */

    .dashboard-card {

        background: #FFFFFF;

        border:
            1px solid #ECECEC;

        border-radius: 18px;

        box-shadow:
            0 5px 18px
            rgba(0,0,0,.05);

        overflow: hidden;

        height: 100%;

    }


    .dashboard-card-header {

        padding:
            18px 21px;

        border-bottom:
            1px solid #EEEEEE;

        display: flex;

        align-items: center;

        justify-content: space-between;

        gap: 15px;

    }


    .dashboard-card-header h5 {

        margin: 0;

        font-size: 16px;

        font-weight: 800;

        color: #760000;

    }


    .dashboard-card-body {

        padding: 20px;

    }


    /* ========================================================
       SOLICITUDES
    ======================================================== */

    .ticket {

        padding:
            16px 0;

        border-bottom:
            1px solid #EEEEEE;

    }


    .ticket:first-child {

        padding-top: 0;

    }


    .ticket:last-child {

        border-bottom: none;

        padding-bottom: 0;

    }


    .ticket-numero {

        font-size: 12px;

        color: #888888;

        font-weight: 600;

    }


    .ticket-titulo {

        font-weight: 700;

        color: #333333;

        margin:
            4px 0 6px;

    }


    .ticket-meta {

        display: flex;

        flex-wrap: wrap;

        gap:
            6px 15px;

        color: #777777;

        font-size: 12px;

    }


    .ticket-meta i {

        color: #B12626;

    }


    .ticket-link {

        color: #760000;

        font-size: 13px;

        font-weight: 700;

        text-decoration: none;

    }


    .ticket-link:hover {

        color: #B12626;

    }


    /* ========================================================
       BADGES
    ======================================================== */

    .badge-dashboard {

        border-radius: 30px;

        padding:
            6px 10px;

        font-size: 11px;

        font-weight: 700;

    }


    /* ========================================================
       HORARIOS
    ======================================================== */

    .horario-item {

        display: flex;

        align-items: center;

        justify-content: space-between;

        gap: 10px;

        padding:
            10px 0;

        border-bottom:
            1px solid #EEEEEE;

        font-size: 13px;

    }


    .horario-item:last-child {

        border-bottom: 0;

    }


    .horario-dia {

        font-weight: 700;

        color: #444444;

    }


    .horario-hora {

        color: #760000;

        font-weight: 700;

    }


    /* ========================================================
       ATAJOS
    ======================================================== */

    .acceso-rapido {

        display: flex;

        align-items: center;

        gap: 14px;

        padding: 14px;

        border-radius: 13px;

        text-decoration: none;

        color: #333333;

        transition:
            all .2s ease;

        margin-bottom: 8px;

        border:
            1px solid transparent;

    }


    .acceso-rapido:hover {

        background: #FFF7F7;

        color: #760000;

        border-color: #F1D5D5;

    }


    .acceso-icon {

        width: 42px;

        height: 42px;

        min-width: 42px;

        border-radius: 11px;

        display: flex;

        align-items: center;

        justify-content: center;

        background:
            linear-gradient(
                135deg,
                #760000,
                #B12626
            );

        color: #FFFFFF;

        font-size: 18px;

    }


    .acceso-rapido strong {

        display: block;

        font-size: 13px;

    }


    .acceso-rapido small {

        color: #888888;

        font-size: 11px;

    }


    /* ========================================================
       VACÍO
    ======================================================== */

    .estado-vacio {

        text-align: center;

        padding:
            32px 15px;

        color: #8A8A8A;

    }


    .estado-vacio i {

        display: block;

        font-size: 40px;

        color: #D0D0D0;

        margin-bottom: 10px;

    }


    /* ========================================================
       RESPONSIVE
    ======================================================== */

    @media
    (max-width: 767px) {

        .dashboard-hero {

            padding:
                25px 20px;

        }


        .dashboard-hero h1 {

            font-size: 23px;

        }


        .hero-boton {

            margin-top: 20px;

        }

    }

</style>


<div class="dashboard-wrapper">


    <!-- =====================================================
         BIENVENIDA
    ====================================================== -->

    <section class="dashboard-hero">

        <div class="dashboard-hero-contenido">

            <div class="row align-items-center">

                <div class="col-lg-8">

                    <h1>

                        Hola,
                        <?= e($nombreUsuario) ?>

                    </h1>


                    <?php if (esDocente()): ?>

                        <p>

                            Desde este panel podés registrar
                            y seguir tus solicitudes
                            de informática y mantenimiento.

                        </p>

                    <?php else: ?>

                        <p>

                            Panel general de solicitudes,
                            intervenciones, pendientes
                            y mantenimiento del colegio.

                        </p>

                    <?php endif; ?>


                    <div class="rol-usuario">

                        <?php if (esAdministrador()): ?>

                            <i class="bi bi-shield-check"></i>

                        <?php elseif (esTecnico()): ?>

                            <i class="bi bi-tools"></i>

                        <?php else: ?>

                            <i class="bi bi-person-badge"></i>

                        <?php endif; ?>


                        <?= e($rol) ?>

                    </div>

                </div>


                <div
                    class="col-lg-4
                           text-lg-end
                           hero-boton"
                >

                    <a
                        href="<?= url('nueva_solicitud.php') ?>"
                        class="btn-nueva-solicitud"
                    >

                        <i class="bi bi-plus-circle"></i>

                        Nueva solicitud

                    </a>

                </div>

            </div>

        </div>

    </section>



    <!-- =====================================================
         ESTADÍSTICAS DOCENTE
    ====================================================== -->

    <?php if (esDocente()): ?>

        <div class="row g-3 mb-4">


            <div class="col-6 col-lg">

                <div class="stats-card">

                    <div class="stats-card-top">

                        <div class="stats-icon icon-total">

                            <i class="bi bi-ticket-detailed"></i>

                        </div>

                    </div>

                    <div class="stats-value">

                        <?= $estadisticas['total'] ?>

                    </div>

                    <div class="stats-label">

                        Mis solicitudes

                    </div>

                </div>

            </div>


            <div class="col-6 col-lg">

                <div class="stats-card">

                    <div class="stats-icon icon-nueva">

                        <i class="bi bi-plus-circle"></i>

                    </div>

                    <div class="stats-value">

                        <?= $estadisticas['nuevas'] ?>

                    </div>

                    <div class="stats-label">

                        Nuevas

                    </div>

                </div>

            </div>


            <div class="col-6 col-lg">

                <div class="stats-card">

                    <div class="stats-icon icon-proceso">

                        <i class="bi bi-arrow-repeat"></i>

                    </div>

                    <div class="stats-value">

                        <?= $estadisticas['en_proceso'] ?>

                    </div>

                    <div class="stats-label">

                        En proceso

                    </div>

                </div>

            </div>


            <div class="col-6 col-lg">

                <div class="stats-card">

                    <div class="stats-icon icon-pendiente">

                        <i class="bi bi-hourglass-split"></i>

                    </div>

                    <div class="stats-value">

                        <?= $estadisticas['pendientes'] ?>

                    </div>

                    <div class="stats-label">

                        Pendientes

                    </div>

                </div>

            </div>


            <div class="col-6 col-lg">

                <div class="stats-card">

                    <div class="stats-icon icon-resuelta">

                        <i class="bi bi-check2-circle"></i>

                    </div>

                    <div class="stats-value">

                        <?= $estadisticas['resueltas'] ?>

                    </div>

                    <div class="stats-label">

                        Resueltas

                    </div>

                </div>

            </div>

        </div>


    <?php else: ?>


        <!-- =================================================
             ESTADÍSTICAS TÉCNICO / ADMIN
        ================================================== -->

        <div class="row g-3 mb-4">


            <div class="col-6 col-md-4 col-xl-2">

                <div class="stats-card">

                    <div class="stats-icon icon-total">

                        <i class="bi bi-ticket-detailed"></i>

                    </div>

                    <div class="stats-value">

                        <?= $estadisticas['total'] ?>

                    </div>

                    <div class="stats-label">

                        Total

                    </div>

                </div>

            </div>


            <div class="col-6 col-md-4 col-xl-2">

                <div class="stats-card">

                    <div class="stats-icon icon-nueva">

                        <i class="bi bi-plus-circle"></i>

                    </div>

                    <div class="stats-value">

                        <?= $estadisticas['nuevas'] ?>

                    </div>

                    <div class="stats-label">

                        Nuevas

                    </div>

                </div>

            </div>


            <div class="col-6 col-md-4 col-xl-2">

                <div class="stats-card">

                    <div class="stats-icon icon-proceso">

                        <i class="bi bi-arrow-repeat"></i>

                    </div>

                    <div class="stats-value">

                        <?= $estadisticas['en_proceso'] ?>

                    </div>

                    <div class="stats-label">

                        En proceso

                    </div>

                </div>

            </div>


            <div class="col-6 col-md-4 col-xl-2">

                <div class="stats-card">

                    <div class="stats-icon icon-pendiente">

                        <i class="bi bi-hourglass-split"></i>

                    </div>

                    <div class="stats-value">

                        <?= $estadisticas['pendientes'] ?>

                    </div>

                    <div class="stats-label">

                        Pendientes

                    </div>

                </div>

            </div>


            <div class="col-6 col-md-4 col-xl-2">

                <div class="stats-card">

                    <div class="stats-icon icon-resuelta">

                        <i class="bi bi-check2-circle"></i>

                    </div>

                    <div class="stats-value">

                        <?= $estadisticas['resueltas'] ?>

                    </div>

                    <div class="stats-label">

                        Resueltas

                    </div>

                </div>

            </div>


            <div class="col-6 col-md-4 col-xl-2">

                <div class="stats-card">

                    <div class="stats-icon icon-urgente">

                        <i class="bi bi-exclamation-triangle"></i>

                    </div>

                    <div class="stats-value">

                        <?= $estadisticas['urgentes'] ?>

                    </div>

                    <div class="stats-label">

                        Urgentes

                    </div>

                </div>

            </div>


        </div>

    <?php endif; ?>



    <!-- =====================================================
         CONTENIDO PRINCIPAL
    ====================================================== -->

    <div class="row g-4">


        <!-- =================================================
             SOLICITUDES RECIENTES
        ================================================== -->

        <div class="col-xl-8">

            <div class="dashboard-card">

                <div class="dashboard-card-header">

                    <h5>

                        <i class="bi bi-clock-history me-2"></i>

                        <?php if (esDocente()): ?>

                            Mis solicitudes recientes

                        <?php else: ?>

                            Solicitudes recientes

                        <?php endif; ?>

                    </h5>


                    <a
                        href="<?= url(
                            esDocente()
                            ? 'mis_solicitudes.php'
                            : 'solicitudes.php'
                        ) ?>"
                        class="ticket-link"
                    >

                        Ver todas

                        <i class="bi bi-arrow-right"></i>

                    </a>

                </div>


                <div class="dashboard-card-body">


                    <?php if (empty($solicitudes)): ?>

                        <div class="estado-vacio">

                            <i class="bi bi-inbox"></i>

                            <strong>
                                No hay solicitudes todavía
                            </strong>

                            <div class="mt-2">

                                Cuando se registre una solicitud,
                                aparecerá aquí.

                            </div>

                        </div>


                    <?php else: ?>


                        <?php foreach ($solicitudes as $solicitud): ?>


                            <div class="ticket">


                                <div
                                    class="d-flex
                                           justify-content-between
                                           align-items-start
                                           gap-3"
                                >

                                    <div class="flex-grow-1">


                                        <div class="ticket-numero">

                                            <?= e(
                                                numeroTicket(
                                                    (int)$solicitud['id_solicitud']
                                                )
                                            ) ?>

                                        </div>


                                        <div class="ticket-titulo">

                                            <?= e(
                                                $solicitud['titulo']
                                            ) ?>

                                        </div>


                                        <div class="ticket-meta">


                                            <span>

                                                <i class="<?= e(
                                                    iconoTipo(
                                                        $solicitud['tipo']
                                                    )
                                                ) ?>"></i>

                                                <?= e(
                                                    nombreTipo(
                                                        $solicitud['tipo']
                                                    )
                                                ) ?>

                                            </span>


                                            <?php if (
                                                !empty(
                                                    $solicitud['sector']
                                                )
                                            ): ?>

                                                <span>

                                                    <i class="bi bi-geo-alt"></i>

                                                    <?= e(
                                                        $solicitud['sector']
                                                    ) ?>

                                                </span>

                                            <?php endif; ?>


                                            <?php if (
                                                !esDocente()
                                                &&
                                                !empty(
                                                    $solicitud['solicitante']
                                                )
                                            ): ?>

                                                <span>

                                                    <i class="bi bi-person"></i>

                                                    <?= e(
                                                        $solicitud['solicitante']
                                                    ) ?>

                                                </span>

                                            <?php endif; ?>


                                            <span>

                                                <i class="bi bi-calendar3"></i>

                                                <?= e(
                                                    fechaArgentina(
                                                        $solicitud['fecha_creacion']
                                                    )
                                                ) ?>

                                            </span>

                                        </div>

                                    </div>


                                    <div
                                        class="text-end
                                               flex-shrink-0"
                                    >

                                        <span
                                            class="badge <?= e(
                                                claseEstado(
                                                    $solicitud['estado']
                                                )
                                            ) ?> badge-dashboard"
                                        >

                                            <?= e(
                                                $solicitud['estado']
                                            ) ?>

                                        </span>


                                        <div class="mt-2">

                                            <a
                                                href="<?= url(
                                                    'ver_solicitud.php?id='
                                                    .
                                                    (int)$solicitud['id_solicitud']
                                                ) ?>"
                                                class="ticket-link"
                                            >

                                                Ver

                                            </a>

                                        </div>

                                    </div>

                                </div>


                                <?php if (
                                    $solicitud['estado']
                                    === 'Pendiente'
                                    &&
                                    !empty(
                                        $solicitud['motivo_pendiente']
                                    )
                                ): ?>

                                    <div
                                        class="alert
                                               alert-warning
                                               py-2 px-3
                                               mt-3 mb-0
                                               small"
                                    >

                                        <i class="bi bi-hourglass-split me-1"></i>

                                        <strong>
                                            Motivo:
                                        </strong>

                                        <?= e(
                                            $solicitud['motivo_pendiente']
                                        ) ?>

                                    </div>

                                <?php endif; ?>


                            </div>


                        <?php endforeach; ?>


                    <?php endif; ?>


                </div>

            </div>

        </div>



        <!-- =================================================
             ACCESOS RÁPIDOS
        ================================================== -->

        <div class="col-xl-4">

            <div class="dashboard-card">

                <div class="dashboard-card-header">

                    <h5>

                        <i class="bi bi-grid me-2"></i>

                        Accesos rápidos

                    </h5>

                </div>


                <div class="dashboard-card-body">


                    <a
                        href="<?= url('nueva_solicitud.php') ?>"
                        class="acceso-rapido"
                    >

                        <div class="acceso-icon">

                            <i class="bi bi-plus-lg"></i>

                        </div>

                        <div>

                            <strong>
                                Nueva solicitud
                            </strong>

                            <small>
                                Informar un problema
                            </small>

                        </div>

                    </a>


                    <?php if (esDocente()): ?>

                        <a
                            href="<?= url('mis_solicitudes.php') ?>"
                            class="acceso-rapido"
                        >

                            <div class="acceso-icon">

                                <i class="bi bi-ticket-detailed"></i>

                            </div>

                            <div>

                                <strong>
                                    Mis solicitudes
                                </strong>

                                <small>
                                    Consultar mis pedidos
                                </small>

                            </div>

                        </a>

                    <?php else: ?>

                        <a
                            href="<?= url('solicitudes.php') ?>"
                            class="acceso-rapido"
                        >

                            <div class="acceso-icon">

                                <i class="bi bi-ticket-detailed"></i>

                            </div>

                            <div>

                                <strong>
                                    Gestionar solicitudes
                                </strong>

                                <small>
                                    Ver todos los pedidos
                                </small>

                            </div>

                        </a>

                    <?php endif; ?>


                    <a
                        href="<?= url('horarios.php') ?>"
                        class="acceso-rapido"
                    >

                        <div class="acceso-icon">

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
                        href="<?= url('mejoras.php') ?>"
                        class="acceso-rapido"
                    >

                        <div class="acceso-icon">

                            <i class="bi bi-lightbulb"></i>

                        </div>

                        <div>

                            <strong>
                                Propuestas de mejora
                            </strong>

                            <small>
                                Necesidades y mejoras
                            </small>

                        </div>

                    </a>


                    <?php if (esAdministrador()): ?>

                        <a
                            href="<?= url('admin/usuarios.php') ?>"
                            class="acceso-rapido"
                        >

                            <div class="acceso-icon">

                                <i class="bi bi-people"></i>

                            </div>

                            <div>

                                <strong>
                                    Usuarios
                                </strong>

                                <small>
                                    Docentes y técnicos
                                </small>

                            </div>

                        </a>

                    <?php endif; ?>


                    <?php if (
                        $cantidadNotificaciones > 0
                    ): ?>

                        <a
                            href="<?= url('notificaciones.php') ?>"
                            class="acceso-rapido"
                        >

                            <div class="acceso-icon">

                                <i class="bi bi-bell"></i>

                            </div>

                            <div>

                                <strong>

                                    Notificaciones

                                    <span
                                        class="badge bg-danger ms-1"
                                    >
                                        <?= $cantidadNotificaciones ?>
                                    </span>

                                </strong>

                                <small>
                                    Cambios y novedades
                                </small>

                            </div>

                        </a>

                    <?php endif; ?>


                </div>

            </div>

        </div>

    </div>



    <!-- =====================================================
         SEGUNDA FILA
    ====================================================== -->

    <div class="row g-4 mt-1">


        <!-- =================================================
             HORARIOS INFORMÁTICA
        ================================================== -->

        <div class="col-lg-6">

            <div class="dashboard-card">

                <div class="dashboard-card-header">

                    <h5>

                        <i class="bi bi-pc-display me-2"></i>

                        Horarios de informática

                    </h5>


                    <a
                        href="<?= url('horarios.php') ?>"
                        class="ticket-link"
                    >

                        Ver horarios

                    </a>

                </div>


                <div class="dashboard-card-body">


                    <?php if (
                        empty(
                            $horariosInformatica
                        )
                    ): ?>

                        <div class="estado-vacio">

                            <i class="bi bi-calendar-x"></i>

                            No hay horarios cargados.

                        </div>

                    <?php else: ?>


                        <?php foreach (
                            $horariosInformatica
                            as $horario
                        ): ?>

                            <div class="horario-item">

                                <div>

                                    <div class="horario-dia">

                                        <?= e(
                                            $horario['dia']
                                        ) ?>

                                    </div>


                                    <?php if (
                                        !empty(
                                            $horario['responsable']
                                        )
                                    ): ?>

                                        <small class="text-muted">

                                            <?= e(
                                                $horario['responsable']
                                            ) ?>

                                        </small>

                                    <?php endif; ?>

                                </div>


                                <div class="horario-hora">

                                    <?= e(
                                        horaCorta(
                                            $horario['hora_desde']
                                        )
                                    ) ?>

                                    -

                                    <?= e(
                                        horaCorta(
                                            $horario['hora_hasta']
                                        )
                                    ) ?>

                                </div>

                            </div>

                        <?php endforeach; ?>


                    <?php endif; ?>


                </div>

            </div>

        </div>



        <!-- =================================================
             HORARIOS MANTENIMIENTO
        ================================================== -->

        <div class="col-lg-6">

            <div class="dashboard-card">

                <div class="dashboard-card-header">

                    <h5>

                        <i class="bi bi-tools me-2"></i>

                        Horarios de mantenimiento

                    </h5>


                    <a
                        href="<?= url('horarios.php') ?>"
                        class="ticket-link"
                    >

                        Ver horarios

                    </a>

                </div>


                <div class="dashboard-card-body">


                    <?php if (
                        empty(
                            $horariosMantenimiento
                        )
                    ): ?>

                        <div class="estado-vacio">

                            <i class="bi bi-calendar-x"></i>

                            No hay horarios cargados.

                        </div>

                    <?php else: ?>


                        <?php foreach (
                            $horariosMantenimiento
                            as $horario
                        ): ?>

                            <div class="horario-item">

                                <div>

                                    <div class="horario-dia">

                                        <?= e(
                                            $horario['dia']
                                        ) ?>

                                    </div>


                                    <?php if (
                                        !empty(
                                            $horario['responsable']
                                        )
                                    ): ?>

                                        <small class="text-muted">

                                            <?= e(
                                                $horario['responsable']
                                            ) ?>

                                        </small>

                                    <?php endif; ?>

                                </div>


                                <div class="horario-hora">

                                    <?= e(
                                        horaCorta(
                                            $horario['hora_desde']
                                        )
                                    ) ?>

                                    -

                                    <?= e(
                                        horaCorta(
                                            $horario['hora_hasta']
                                        )
                                    ) ?>

                                </div>

                            </div>

                        <?php endforeach; ?>


                    <?php endif; ?>


                </div>

            </div>

        </div>

    </div>



    <!-- =====================================================
         MEJORAS
         SOLAMENTE PERSONAL TÉCNICO
    ====================================================== -->

    <?php if (
        esPersonalTecnico()
        &&
        !empty($mejoras)
    ): ?>

        <div class="row g-4 mt-1">

            <div class="col-12">

                <div class="dashboard-card">

                    <div class="dashboard-card-header">

                        <h5>

                            <i class="bi bi-lightbulb me-2"></i>

                            Mejoras recientes

                        </h5>


                        <a
                            href="<?= url('mejoras.php') ?>"
                            class="ticket-link"
                        >

                            Ver todas

                        </a>

                    </div>


                    <div class="dashboard-card-body">

                        <div class="row g-3">


                            <?php foreach (
                                $mejoras
                                as $mejora
                            ): ?>

                                <div class="col-md-6 col-xl">

                                    <div
                                        class="border
                                               rounded-3
                                               p-3
                                               h-100"
                                    >

                                        <div
                                            class="small
                                                   text-muted
                                                   mb-1"
                                        >

                                            <?= e(
                                                nombreTipo(
                                                    $mejora['tipo']
                                                )
                                            ) ?>

                                        </div>


                                        <strong
                                            class="d-block
                                                   text-dark
                                                   mb-2"
                                        >

                                            <?= e(
                                                $mejora['titulo']
                                            ) ?>

                                        </strong>


                                        <span
                                            class="badge
                                                   bg-secondary"
                                        >

                                            <?= e(
                                                $mejora['estado']
                                            ) ?>

                                        </span>

                                    </div>

                                </div>

                            <?php endforeach; ?>


                        </div>

                    </div>

                </div>

            </div>

        </div>

    <?php endif; ?>


</div>


<?php

require_once __DIR__ . '/includes/footer.php';

?>