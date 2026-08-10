<?php
// ============================================================
// COLEGIO SAN JOSÉ
// Sistema de Gestión Técnica
// Archivo: /tecnica/pendientes.php
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
// OBTENER PENDIENTES SEGÚN ROL
//
// Docente: solamente las propias (respeta el mismo criterio
// que puedeVerSolicitud() en includes/auth.php).
// Técnico / Administrador: todas.
// ============================================================

$pendientes = obtenerPendientes(
    $conexion,
    esDocente()
        ? usuarioId()
        : null
);


// ============================================================
// HEADER
// ============================================================

require_once __DIR__
    . '/includes/header.php';

?>


<style>

.pendientes-wrapper {

    max-width: 1350px;

    margin: 0 auto;

    padding: 5px 12px 45px;

}


.pendientes-hero {

    position: relative;

    overflow: hidden;

    background:
        linear-gradient(
            135deg,
            #760000,
            #B12626
        );

    color: #FFFFFF;

    border-radius: 21px;

    padding: 29px;

    margin-bottom: 24px;

    box-shadow:
        0 9px 28px
        rgba(118,0,0,.16);

}


.pendientes-hero::after {

    content: "";

    position: absolute;

    right: -100px;

    top: -130px;

    width: 270px;

    height: 270px;

    border-radius: 50%;

    background:
        rgba(255,255,255,.06);

}


.hero-content {

    position: relative;

    z-index: 2;

}


.pendientes-hero h1 {

    margin: 0 0 7px;

    font-size: 28px;

    font-weight: 800;

}


.pendientes-hero p {

    margin: 0;

    color:
        rgba(255,255,255,.78);

}


.btn-volver {

    position: relative;

    z-index: 2;

    display: inline-flex;

    align-items: center;

    gap: 7px;

    padding: 10px 17px;

    border-radius: 10px;

    background: #FFFFFF;

    color: #760000;

    font-weight: 700;

    text-decoration: none;

}


.btn-volver:hover {

    color: #B12626;

    background: #F4F4F4;

}


.pendiente-card {

    background: #FFFFFF;

    border: 1px solid #ECECEC;

    border-left: 4px solid #E0A800;

    border-radius: 14px;

    padding: 18px 20px;

    margin-bottom: 16px;

    box-shadow:
        0 4px 15px
        rgba(0,0,0,.04);

}


.pendiente-top {

    display: flex;

    flex-wrap: wrap;

    justify-content: space-between;

    gap: 10px;

}


.pendiente-titulo {

    color: #333333;

    font-weight: 800;

    font-size: 15px;

}


.pendiente-titulo a {

    color: inherit;

    text-decoration: none;

}


.pendiente-titulo a:hover {

    color: #B12626;

}


.pendiente-meta {

    color: #888888;

    font-size: 12px;

    margin-top: 5px;

}


.pendiente-motivo {

    margin-top: 10px;

    padding: 10px 12px;

    border-radius: 9px;

    background: #FAFAFA;

    color: #555555;

    font-size: 13px;

}


.pendiente-acciones {

    margin-top: 10px;

}


.pendiente-acciones a {

    font-size: 12px;

    font-weight: 700;

    text-decoration: none;

    margin-right: 14px;

}


.empty {

    padding: 45px 20px;

    color: #888888;

    text-align: center;

}


.empty i {

    display: block;

    font-size: 40px;

    color: #D0D0D0;

    margin-bottom: 8px;

}

</style>


<div class="pendientes-wrapper">


    <section class="pendientes-hero">

        <div class="row align-items-center">

            <div class="col-lg-8">

                <div class="hero-content">

                    <h1>

                        <i class="bi bi-hourglass-split me-1"></i>

                        Solicitudes pendientes

                    </h1>

                    <p>

                        <?= esDocente()
                            ? 'Tus solicitudes que quedaron pendientes de resolución.'
                            : 'Todas las solicitudes pendientes del sistema, con el motivo del bloqueo.'
                        ?>

                    </p>

                </div>

            </div>


            <div class="col-lg-4 text-lg-end">

                <a
                    href="<?= url('dashboard.php') ?>"
                    class="btn-volver"
                >

                    <i class="bi bi-arrow-left"></i>

                    Volver al dashboard

                </a>

            </div>

        </div>

    </section>


    <?php if (empty($pendientes)): ?>

        <div class="empty">

            <i class="bi bi-check2-circle"></i>

            No hay solicitudes pendientes en este momento.

        </div>

    <?php else: ?>

        <?php foreach ($pendientes as $solicitud): ?>

            <div class="pendiente-card">

                <div class="pendiente-top">

                    <div>

                        <div class="pendiente-titulo">

                            <a
                                href="<?= url(
                                    'ver_solicitud.php?id='
                                    . (int)$solicitud['id_solicitud']
                                ) ?>"
                            >

                                <?= e(
                                    numeroTicket(
                                        (int)$solicitud['id_solicitud']
                                    )
                                ) ?>
                                -
                                <?= e($solicitud['titulo']) ?>

                            </a>

                        </div>


                        <div class="pendiente-meta">

                            <i class="bi bi-person me-1"></i>
                            <?= e($solicitud['solicitante']) ?>

                            · <i class="bi bi-geo-alt me-1"></i>
                            <?= e($solicitud['sector'] ?? 'Sin sector') ?>

                            · <i class="bi bi-calendar3 me-1"></i>
                            <?= e(fechaArgentina($solicitud['fecha_actualizacion'])) ?>

                            <?php if (!empty($solicitud['tecnico_asignado'])): ?>

                                · <i class="bi bi-person-gear me-1"></i>
                                <?= e($solicitud['tecnico_asignado']) ?>

                            <?php endif; ?>

                        </div>

                    </div>


                    <div class="text-end">

                        <span
                            class="badge <?= e(clasePrioridad($solicitud['prioridad'])) ?>"
                        >
                            <?= e($solicitud['prioridad']) ?>
                        </span>

                        <?php if (!empty($solicitud['tipo_pendiente'])): ?>

                            <span
                                class="badge <?= e(
                                    claseTipoPendiente($solicitud['tipo_pendiente'])
                                ) ?>"
                            >

                                <i class="bi <?= e(
                                    iconoTipoPendiente($solicitud['tipo_pendiente'])
                                ) ?> me-1"></i>

                                <?= e($solicitud['tipo_pendiente']) ?>

                            </span>

                        <?php endif; ?>

                    </div>

                </div>


                <?php if (!empty($solicitud['motivo_pendiente'])): ?>

                    <div class="pendiente-motivo">
                        <?= e($solicitud['motivo_pendiente']) ?>
                    </div>

                <?php endif; ?>


                <?php if (esPersonalTecnico()): ?>

                    <div class="pendiente-acciones">

                        <a
                            href="<?= url(
                                'ver_solicitud.php?id='
                                . (int)$solicitud['id_solicitud']
                            ) ?>"
                        >
                            <i class="bi bi-eye me-1"></i>
                            Ver ticket
                        </a>

                        <?php if (
                            ($solicitud['tipo_pendiente'] ?? '') === 'Falta de repuesto'
                        ): ?>

                            <a href="<?= url('tecnico/repuestos.php') ?>">
                                <i class="bi bi-box-seam me-1"></i>
                                Ir a repuestos
                            </a>

                        <?php elseif (
                            ($solicitud['tipo_pendiente'] ?? '') === 'Reprogramacion'
                        ): ?>

                            <a href="<?= url('tecnico/agenda.php') ?>">
                                <i class="bi bi-calendar2-week me-1"></i>
                                Ir a mi agenda
                            </a>

                        <?php endif; ?>

                    </div>

                <?php endif; ?>

            </div>

        <?php endforeach; ?>

    <?php endif; ?>


</div>


<?php

require_once __DIR__
    . '/includes/footer.php';

?>
