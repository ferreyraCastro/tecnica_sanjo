<?php
// ============================================================
// COLEGIO SAN JOSÉ
// Sistema de Gestión Técnica
// Archivo: /tecnica/ver_solicitud.php
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/funciones.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/mailer.php';

requerirLogin();


// ============================================================
// ID SOLICITUD
// ============================================================

$idSolicitud = (int)($_GET['id'] ?? 0);

if ($idSolicitud <= 0) {

    header(
        'Location: ' . url('dashboard.php')
    );

    exit;
}


// ============================================================
// CONTROL DE ACCESO
// Docente: solamente sus solicitudes.
// Técnico/Admin: todas.
// ============================================================

requerirAccesoSolicitud(
    $conexion,
    $idSolicitud
);


// ============================================================
// OBTENER SOLICITUD
// ============================================================

$solicitud =
    obtenerSolicitud(
        $conexion,
        $idSolicitud
    );

if (!$solicitud) {

    http_response_code(404);

    die('Solicitud no encontrada.');
}


// ============================================================
// PROCESAR COMENTARIO
// ============================================================

$errorComentario = '';

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    isset($_POST['accion'])
    &&
    $_POST['accion'] === 'comentar'
) {

    if (
        !validarCsrf(
            $_POST['csrf_token'] ?? ''
        )
    ) {

        $errorComentario =
            'La sesión del formulario expiró.';

    } else {

        $comentario =
            limpiarTexto(
                $_POST['comentario'] ?? ''
            );

        if ($comentario === '') {

            $errorComentario =
                'Escribí un comentario.';

        } elseif (
            mb_strlen($comentario) > 2000
        ) {

            $errorComentario =
                'El comentario es demasiado largo.';

        } else {

            if (
                agregarComentario(
                    $conexion,
                    $idSolicitud,
                    (int)usuarioId(),
                    $comentario
                )
            ) {

                // ============================================
                // NOTIFICACIÓN
                // Si comenta el docente, se avisa al técnico
                // asignado. Si comenta el técnico/admin,
                // se avisa al docente dueño de la solicitud.
                // ============================================

                if (
                    (int)$solicitud['id_usuario']
                    !== (int)usuarioId()
                ) {

                    crearNotificacion(
                        $conexion,
                        (int)$solicitud['id_usuario'],
                        'Nuevo comentario',
                        'Se agregó un comentario en la solicitud '
                        . numeroTicket($idSolicitud),
                        'ver_solicitud.php?id=' . $idSolicitud
                    );

                    try {

                        notificarComentario(
                            $idSolicitud,
                            numeroTicket($idSolicitud),
                            (string)$solicitud['titulo'],
                            (string)($solicitud['correo'] ?? ''),
                            trim(
                                ($solicitud['nombre'] ?? '')
                                . ' ' .
                                ($solicitud['apellido'] ?? '')
                            ),
                            usuarioNombre(),
                            $comentario
                        );

                    } catch (Throwable $e) {

                        error_log(
                            'Error enviando correo de comentario: '
                            . $e->getMessage()
                        );
                    }

                } elseif (esDocente()) {

                    $tecnicoNotificar =
                        obtenerTecnicoAsignado(
                            $conexion,
                            $idSolicitud
                        );

                    if (
                        $tecnicoNotificar
                        &&
                        isset($tecnicoNotificar['id_tecnico'])
                    ) {

                        crearNotificacion(
                            $conexion,
                            (int)$tecnicoNotificar['id_tecnico'],
                            'Nuevo comentario en '
                            . numeroTicket($idSolicitud),
                            usuarioNombre()
                            . ' agregó un comentario en la solicitud: '
                            . $solicitud['titulo'],
                            'ver_solicitud.php?id=' . $idSolicitud
                        );

                        try {

                            notificarComentario(
                                $idSolicitud,
                                numeroTicket($idSolicitud),
                                (string)$solicitud['titulo'],
                                (string)($tecnicoNotificar['correo'] ?? ''),
                                trim(
                                    ($tecnicoNotificar['nombre'] ?? '')
                                    . ' ' .
                                    ($tecnicoNotificar['apellido'] ?? '')
                                ),
                                usuarioNombre(),
                                $comentario
                            );

                        } catch (Throwable $e) {

                            error_log(
                                'Error enviando correo de comentario: '
                                . $e->getMessage()
                            );
                        }
                    }
                }


                flash(
                    'success',
                    'Comentario agregado correctamente.'
                );


                header(
                    'Location: '
                    . url(
                        'ver_solicitud.php?id='
                        . $idSolicitud
                    )
                );

                exit;
            }

            $errorComentario =
                'No se pudo guardar el comentario.';
        }
    }
}


// ============================================================
// INFORMACIÓN RELACIONADA
// ============================================================

$imagenes =
    obtenerImagenesSolicitud(
        $conexion,
        $idSolicitud
    );


$comentarios =
    obtenerComentarios(
        $conexion,
        $idSolicitud
    );


$intervenciones =
    obtenerIntervenciones(
        $conexion,
        $idSolicitud
    );


$historial =
    obtenerHistorialSolicitud(
        $conexion,
        $idSolicitud
    );


$tecnicoAsignado =
    obtenerTecnicoAsignado(
        $conexion,
        $idSolicitud
    );


$materiales =
    obtenerMaterialesSolicitud(
        $conexion,
        $idSolicitud
    );


$movimientosStock =
    obtenerMovimientosSolicitud(
        $conexion,
        $idSolicitud
    );


$turnosSolicitud = [];

if (
    esPersonalTecnico()
    &&
    $tecnicoAsignado
) {

    $turnosSolicitud =
        obtenerTurnosSolicitud(
            $conexion,
            $idSolicitud
        );
}


// ============================================================
// IMÁGENES DE INTERVENCIONES
// ============================================================

$imagenesIntervenciones = [];

if (!empty($intervenciones)) {

    $ids = array_map(
        static fn(array $i): int =>
            (int)$i['id_intervencion'],
        $intervenciones
    );

    $placeholders =
        implode(
            ',',
            array_fill(
                0,
                count($ids),
                '?'
            )
        );

    $stmtImagenesIntervenciones =
        $conexion->prepare("
            SELECT *
            FROM intervencion_imagenes
            WHERE id_intervencion
            IN ({$placeholders})
            ORDER BY fecha ASC
        ");

    $stmtImagenesIntervenciones->execute(
        $ids
    );

    foreach (
        $stmtImagenesIntervenciones
            ->fetchAll(PDO::FETCH_ASSOC)
        as $foto
    ) {

        $imagenesIntervenciones[
            (int)$foto['id_intervencion']
        ][] = $foto;
    }
}


// ============================================================
// FLASH
// ============================================================

$flash =
    obtenerFlash();


// ============================================================
// HEADER
// ============================================================

require_once __DIR__
    . '/includes/header.php';

?>


<style>

.ticket-wrapper {

    max-width: 1450px;
    margin: 0 auto;
    padding: 5px 12px 45px;

}


/* ============================================================
   CABECERA
============================================================ */

.ticket-header {

    background:
        linear-gradient(
            135deg,
            #760000,
            #B12626
        );

    color: #FFFFFF;

    padding: 27px;

    border-radius: 20px;

    margin-bottom: 23px;

    box-shadow:
        0 8px 25px
        rgba(118,0,0,.15);

}


.ticket-header h1 {

    font-size: 27px;
    font-weight: 800;
    margin: 8px 0 5px;

}


.ticket-number {

    font-size: 13px;
    font-weight: 700;

    color:
        rgba(255,255,255,.75);

}


.header-meta {

    display: flex;
    flex-wrap: wrap;

    gap: 10px 18px;

    margin-top: 13px;

    font-size: 13px;

    color:
        rgba(255,255,255,.80);

}


.header-meta i {

    margin-right: 3px;

}


.header-badges {

    display: flex;
    flex-wrap: wrap;

    justify-content: flex-end;

    gap: 8px;

}


.header-badges .badge {

    padding: 8px 12px;

    border-radius: 30px;

    font-size: 12px;

}


.btn-volver {

    display: inline-flex;

    align-items: center;

    gap: 6px;

    color:
        rgba(255,255,255,.85);

    text-decoration: none;

    font-size: 13px;

}


.btn-volver:hover {

    color: #FFFFFF;

}


/* ============================================================
   CARDS
============================================================ */

.ticket-card {

    background: #FFFFFF;

    border:
        1px solid #ECECEC;

    border-radius: 18px;

    overflow: hidden;

    box-shadow:
        0 5px 18px
        rgba(0,0,0,.05);

    margin-bottom: 22px;

}


.ticket-card-header {

    padding: 17px 20px;

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 15px;

    border-bottom:
        1px solid #EEEEEE;

}


.ticket-card-header h5 {

    margin: 0;

    color: #760000;

    font-size: 16px;

    font-weight: 800;

}


.ticket-card-body {

    padding: 21px;

}


/* ============================================================
   DATOS
============================================================ */

.info-grid {

    display: grid;

    grid-template-columns:
        repeat(2, 1fr);

    gap: 18px;

}


.info-label {

    color: #898989;

    font-size: 11px;

    font-weight: 700;

    text-transform: uppercase;

    letter-spacing: .4px;

    margin-bottom: 4px;

}


.info-value {

    color: #333333;

    font-weight: 600;

}


.descripcion-ticket {

    white-space: pre-line;

    line-height: 1.7;

    color: #4C4C4C;

}


/* ============================================================
   PENDIENTE
============================================================ */

.pendiente-box {

    background: #FFF6DB;

    border-left:
        4px solid #E0A800;

    border-radius: 10px;

    padding: 14px 16px;

    color: #685500;

    margin-bottom: 20px;

}


/* ============================================================
   FOTOS
============================================================ */

.galeria {

    display: grid;

    grid-template-columns:
        repeat(
            auto-fill,
            minmax(145px,1fr)
        );

    gap: 12px;

}


.foto-ticket {

    position: relative;

    aspect-ratio: 1 / 1;

    overflow: hidden;

    border-radius: 12px;

    background: #EEEEEE;

    cursor: pointer;

    border:
        1px solid #E0E0E0;

}


.foto-ticket img {

    width: 100%;

    height: 100%;

    object-fit: cover;

    transition:
        transform .25s ease;

}


.foto-ticket:hover img {

    transform: scale(1.05);

}


.foto-etiqueta {

    position: absolute;

    left: 7px;

    bottom: 7px;

    padding:
        4px 7px;

    background:
        rgba(0,0,0,.68);

    color: #FFFFFF;

    border-radius: 7px;

    font-size: 10px;

}


/* ============================================================
   TÉCNICO
============================================================ */

.tecnico-box {

    display: flex;

    align-items: center;

    gap: 13px;

}


.tecnico-avatar {

    min-width: 47px;

    width: 47px;

    height: 47px;

    border-radius: 50%;

    display: flex;

    justify-content: center;

    align-items: center;

    background:
        linear-gradient(
            135deg,
            #760000,
            #B12626
        );

    color: #FFFFFF;

    font-size: 20px;

}


.tecnico-box strong {

    display: block;

    color: #333333;

}


.tecnico-box small {

    color: #888888;

}


/* ============================================================
   COMENTARIOS
============================================================ */

.comentario {

    display: flex;

    gap: 12px;

    padding:
        15px 0;

    border-bottom:
        1px solid #EEEEEE;

}


.comentario:last-child {

    border-bottom: 0;

}


.comentario-avatar {

    min-width: 39px;

    width: 39px;

    height: 39px;

    border-radius: 50%;

    display: flex;

    justify-content: center;

    align-items: center;

    background: #F2E5E5;

    color: #760000;

    font-weight: 800;

}


.comentario-top {

    display: flex;

    flex-wrap: wrap;

    align-items: center;

    gap: 7px;

}


.comentario-nombre {

    font-weight: 700;

    color: #333333;

}


.comentario-fecha {

    font-size: 11px;

    color: #999999;

}


.comentario-texto {

    color: #555555;

    line-height: 1.55;

    white-space: pre-line;

    margin-top: 5px;

}


.rol-comentario {

    font-size: 9px;

    text-transform: uppercase;

    border-radius: 20px;

    padding: 3px 6px;

    background: #EEEEEE;

    color: #666666;

}


/* ============================================================
   FORM COMENTARIO
============================================================ */

.comentario-form textarea {

    min-height: 105px;

    resize: vertical;

    border-radius: 10px;

}


.btn-comentar {

    background: #B12626;

    color: #FFFFFF;

    border: none;

    border-radius: 9px;

    padding: 9px 17px;

    font-weight: 700;

}


.btn-comentar:hover {

    background: #760000;

    color: #FFFFFF;

}


/* ============================================================
   INTERVENCIONES
============================================================ */

.intervencion {

    position: relative;

    border-left:
        3px solid #B12626;

    padding-left: 20px;

    margin-bottom: 28px;

}


.intervencion:last-child {

    margin-bottom: 0;

}


.intervencion::before {

    content: "";

    position: absolute;

    left: -7px;

    top: 0;

    width: 11px;

    height: 11px;

    border-radius: 50%;

    background: #B12626;

}


.intervencion-fecha {

    color: #888888;

    font-size: 11px;

    margin-bottom: 7px;

}


.intervencion h6 {

    color: #760000;

    font-weight: 800;

}


.informe-bloque {

    margin-top: 13px;

}


.informe-bloque strong {

    display: block;

    font-size: 12px;

    color: #555555;

    margin-bottom: 4px;

}


.informe-bloque div {

    white-space: pre-line;

    color: #555555;

}


/* ============================================================
   HISTORIAL
============================================================ */

.timeline {

    position: relative;

}


.timeline-item {

    display: flex;

    gap: 13px;

    position: relative;

    padding-bottom: 20px;

}


.timeline-item:last-child {

    padding-bottom: 0;

}


.timeline-icon {

    position: relative;

    z-index: 2;

    min-width: 31px;

    width: 31px;

    height: 31px;

    border-radius: 50%;

    display: flex;

    justify-content: center;

    align-items: center;

    background: #F2E4E4;

    color: #760000;

    font-size: 13px;

}


.timeline-item:not(:last-child)
.timeline-icon::after {

    content: "";

    position: absolute;

    top: 31px;

    left: 15px;

    width: 1px;

    height: calc(100% + 3px);

    background: #E2E2E2;

}


.timeline-content {

    flex: 1;

}


.timeline-estado {

    font-weight: 700;

    color: #333333;

    font-size: 13px;

}


.timeline-descripcion {

    color: #777777;

    font-size: 12px;

    margin-top: 2px;

}


.timeline-fecha {

    color: #A0A0A0;

    font-size: 10px;

    margin-top: 3px;

}


/* ============================================================
   MATERIALES
============================================================ */

.material-item {

    padding:
        11px 0;

    border-bottom:
        1px solid #EEEEEE;

}


.material-item:last-child {

    border-bottom: 0;

}


.material-titulo {

    font-weight: 700;

    color: #444444;

}


.material-meta {

    font-size: 11px;

    color: #888888;

    margin-top: 3px;

}


/* ============================================================
   ACCIONES TÉCNICAS
============================================================ */

.accion-tecnica {

    display: flex;

    align-items: center;

    gap: 12px;

    padding: 13px;

    border:
        1px solid #ECECEC;

    border-radius: 11px;

    text-decoration: none;

    color: #333333;

    margin-bottom: 9px;

    transition: .2s;

}


.accion-tecnica:hover {

    background: #FFF7F7;

    color: #760000;

    border-color: #EED4D4;

}


.accion-icon {

    width: 39px;

    height: 39px;

    border-radius: 10px;

    display: flex;

    align-items: center;

    justify-content: center;

    background: #B12626;

    color: #FFFFFF;

}


/* ============================================================
   VACÍO
============================================================ */

.empty-box {

    text-align: center;

    padding: 25px 10px;

    color: #909090;

}


.empty-box i {

    display: block;

    font-size: 35px;

    color: #D0D0D0;

    margin-bottom: 7px;

}


/* ============================================================
   LIGHTBOX
============================================================ */

.modal-foto
.modal-content {

    background: transparent;

    border: 0;

}


.modal-foto img {

    width: 100%;

    max-height: 85vh;

    object-fit: contain;

    border-radius: 10px;

}


/* ============================================================
   RESPONSIVE
============================================================ */

@media
(max-width: 767px) {

    .ticket-header {

        padding: 21px;

    }

    .ticket-header h1 {

        font-size: 22px;

    }

    .header-badges {

        justify-content: flex-start;

        margin-top: 17px;

    }

    .info-grid {

        grid-template-columns: 1fr;

    }

}

</style>


<div class="ticket-wrapper">


    <!-- =====================================================
         HEADER DEL TICKET
    ====================================================== -->

    <section class="ticket-header">

        <div class="row align-items-center">

            <div class="col-lg-9">


                <a
                    href="<?= url(
                        esDocente()
                        ? 'mis_solicitudes.php'
                        : 'solicitudes.php'
                    ) ?>"
                    class="btn-volver"
                >

                    <i class="bi bi-arrow-left"></i>

                    Volver a solicitudes

                </a>


                <div class="ticket-number mt-3">

                    <?= e(
                        numeroTicket(
                            $idSolicitud
                        )
                    ) ?>

                </div>


                <h1>

                    <?= e(
                        $solicitud['titulo']
                    ) ?>

                </h1>


                <div class="header-meta">

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


                    <span>

                        <i class="bi bi-person"></i>

                        <?= e(
                            trim(
                                $solicitud['nombre']
                                . ' '
                                . $solicitud['apellido']
                            )
                        ) ?>

                    </span>


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


            <div class="col-lg-3">

                <div class="header-badges">

                    <span
                        class="badge <?= e(
                            claseEstado(
                                $solicitud['estado']
                            )
                        ) ?>"
                    >

                        <?= e(
                            $solicitud['estado']
                        ) ?>

                    </span>


                    <span
                        class="badge <?= e(
                            clasePrioridad(
                                $solicitud['prioridad']
                            )
                        ) ?>"
                    >

                        <?= e(
                            $solicitud['prioridad']
                        ) ?>

                    </span>

                </div>

            </div>

        </div>

    </section>



    <!-- =====================================================
         FLASH
    ====================================================== -->

    <?php if ($flash): ?>

        <div
            class="alert alert-<?= $flash['tipo'] === 'success'
                ? 'success'
                : 'info'
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
         PENDIENTE
    ====================================================== -->

    <?php if (
        $solicitud['estado'] === 'Pendiente'
        &&
        !empty(
            $solicitud['motivo_pendiente']
        )
    ): ?>

        <div class="pendiente-box">

            <i class="bi bi-hourglass-split me-1"></i>

            <strong>
                Esta solicitud está pendiente.
            </strong>

            <?php if (
                !empty(
                    $solicitud['tipo_pendiente']
                )
            ): ?>

                <span
                    class="badge ms-2 <?= e(
                        claseTipoPendiente(
                            $solicitud['tipo_pendiente']
                        )
                    ) ?>"
                >

                    <i class="bi <?= e(
                        iconoTipoPendiente(
                            $solicitud['tipo_pendiente']
                        )
                    ) ?> me-1"></i>

                    <?= e(
                        $solicitud['tipo_pendiente']
                    ) ?>

                </span>

            <?php endif; ?>

            <div class="mt-1">

                <?= e(
                    $solicitud['motivo_pendiente']
                ) ?>

            </div>

        </div>

    <?php endif; ?>



    <div class="row g-4">


        <!-- =================================================
             COLUMNA PRINCIPAL
        ================================================== -->

        <div class="col-xl-8">


            <!-- =============================================
                 DESCRIPCIÓN
            ============================================== -->

            <div class="ticket-card">

                <div class="ticket-card-header">

                    <h5>

                        <i class="bi bi-file-text me-2"></i>

                        Solicitud

                    </h5>

                </div>


                <div class="ticket-card-body">


                    <div class="info-grid mb-4">


                        <div>

                            <div class="info-label">
                                Categoría
                            </div>

                            <div class="info-value">

                                <?= e(
                                    $solicitud['categoria']
                                    ?? 'Sin categoría'
                                ) ?>

                            </div>

                        </div>


                        <div>

                            <div class="info-label">
                                Última actualización
                            </div>

                            <div class="info-value">

                                <?= e(
                                    fechaArgentina(
                                        $solicitud['fecha_actualizacion']
                                    )
                                ) ?>

                            </div>

                        </div>


                        <div>

                            <div class="info-label">
                                Correo del solicitante
                            </div>

                            <div class="info-value">

                                <?= e(
                                    $solicitud['correo']
                                ) ?>

                            </div>

                        </div>


                        <div>

                            <div class="info-label">
                                Resolución
                            </div>

                            <div class="info-value">

                                <?= !empty(
                                    $solicitud['fecha_resolucion']
                                )
                                    ? e(
                                        fechaArgentina(
                                            $solicitud['fecha_resolucion']
                                        )
                                    )
                                    : 'Todavía no resuelta'
                                ?>

                            </div>

                        </div>

                    </div>


                    <div class="info-label">
                        Descripción
                    </div>

                    <div class="descripcion-ticket">

                        <?= e(
                            $solicitud['descripcion']
                        ) ?>

                    </div>

                </div>

            </div>



            <!-- =============================================
                 IMÁGENES SOLICITUD
            ============================================== -->

            <?php if (!empty($imagenes)): ?>

                <div class="ticket-card">

                    <div class="ticket-card-header">

                        <h5>

                            <i class="bi bi-images me-2"></i>

                            Fotografías de la solicitud

                        </h5>

                        <small class="text-muted">

                            <?= count($imagenes) ?>
                            imagen/es

                        </small>

                    </div>


                    <div class="ticket-card-body">

                        <div class="galeria">


                            <?php foreach (
                                $imagenes
                                as $foto
                            ): ?>

                                <div
                                    class="foto-ticket"
                                    data-imagen="<?= e(
                                        UPLOAD_SOLICITUDES_URL
                                        .
                                        $foto['archivo']
                                    ) ?>"
                                >

                                    <img
                                        src="<?= e(
                                            UPLOAD_SOLICITUDES_URL
                                            .
                                            $foto['archivo']
                                        ) ?>"
                                        alt="<?= e(
                                            $foto['descripcion']
                                            ??
                                            'Fotografía solicitud'
                                        ) ?>"
                                        loading="lazy"
                                    >

                                    <span class="foto-etiqueta">

                                        <?= e(
                                            $foto['tipo']
                                        ) ?>

                                    </span>

                                </div>

                            <?php endforeach; ?>


                        </div>

                    </div>

                </div>

            <?php endif; ?>



            <!-- =============================================
                 INTERVENCIONES
            ============================================== -->

            <div class="ticket-card">

                <div class="ticket-card-header">

                    <h5>

                        <i class="bi bi-tools me-2"></i>

                        Intervenciones técnicas

                    </h5>

                    <span class="badge bg-secondary">

                        <?= count(
                            $intervenciones
                        ) ?>

                    </span>

                </div>


                <div class="ticket-card-body">


                    <?php if (
                        empty(
                            $intervenciones
                        )
                    ): ?>

                        <div class="empty-box">

                            <i class="bi bi-tools"></i>

                            Todavía no se registraron
                            intervenciones técnicas.

                        </div>


                    <?php else: ?>


                        <?php foreach (
                            $intervenciones
                            as $intervencion
                        ): ?>

                            <article class="intervencion">


                                <div class="intervencion-fecha">

                                    <?= e(
                                        fechaArgentina(
                                            $intervencion[
                                                'fecha_intervencion'
                                            ]
                                        )
                                    ) ?>

                                    ·

                                    <?= e(
                                        $intervencion['tecnico']
                                    ) ?>

                                </div>


                                <h6>

                                    Intervención
                                    #<?= (int)$intervencion[
                                        'id_intervencion'
                                    ] ?>

                                </h6>


                                <?php if (
                                    !empty(
                                        $intervencion['diagnostico']
                                    )
                                ): ?>

                                    <div class="informe-bloque">

                                        <strong>
                                            Diagnóstico
                                        </strong>

                                        <div>

                                            <?= e(
                                                $intervencion[
                                                    'diagnostico'
                                                ]
                                            ) ?>

                                        </div>

                                    </div>

                                <?php endif; ?>


                                <?php if (
                                    !empty(
                                        $intervencion[
                                            'trabajo_realizado'
                                        ]
                                    )
                                ): ?>

                                    <div class="informe-bloque">

                                        <strong>
                                            Trabajo realizado
                                        </strong>

                                        <div>

                                            <?= e(
                                                $intervencion[
                                                    'trabajo_realizado'
                                                ]
                                            ) ?>

                                        </div>

                                    </div>

                                <?php endif; ?>


                                <?php if (
                                    !empty(
                                        $intervencion[
                                            'materiales'
                                        ]
                                    )
                                ): ?>

                                    <div class="informe-bloque">

                                        <strong>
                                            Materiales utilizados
                                        </strong>

                                        <div>

                                            <?= e(
                                                $intervencion[
                                                    'materiales'
                                                ]
                                            ) ?>

                                        </div>

                                    </div>

                                <?php endif; ?>


                                <?php if (
                                    !empty(
                                        $intervencion[
                                            'observaciones'
                                        ]
                                    )
                                ): ?>

                                    <div class="informe-bloque">

                                        <strong>
                                            Observaciones
                                        </strong>

                                        <div>

                                            <?= e(
                                                $intervencion[
                                                    'observaciones'
                                                ]
                                            ) ?>

                                        </div>

                                    </div>

                                <?php endif; ?>


                                <?php if (
                                    (int)$intervencion[
                                        'pendiente'
                                    ] === 1
                                ): ?>

                                    <div
                                        class="pendiente-box mt-3 mb-0"
                                    >

                                        <strong>

                                            <i class="bi bi-hourglass-split"></i>

                                            Trabajo pendiente

                                        </strong>

                                        <?php if (
                                            !empty(
                                                $intervencion[
                                                    'tipo_pendiente'
                                                ]
                                            )
                                        ): ?>

                                            <span
                                                class="badge ms-2 <?= e(
                                                    claseTipoPendiente(
                                                        $intervencion['tipo_pendiente']
                                                    )
                                                ) ?>"
                                            >

                                                <i class="bi <?= e(
                                                    iconoTipoPendiente(
                                                        $intervencion['tipo_pendiente']
                                                    )
                                                ) ?> me-1"></i>

                                                <?= e(
                                                    $intervencion['tipo_pendiente']
                                                ) ?>

                                            </span>

                                        <?php endif; ?>

                                        <?php if (
                                            !empty(
                                                $intervencion[
                                                    'motivo_pendiente'
                                                ]
                                            )
                                        ): ?>

                                            <div class="mt-1">

                                                <?= e(
                                                    $intervencion[
                                                        'motivo_pendiente'
                                                    ]
                                                ) ?>

                                            </div>

                                        <?php endif; ?>

                                    </div>

                                <?php endif; ?>



                                <!-- FOTOS INTERVENCIÓN -->

                                <?php

                                $fotosIntervencion =
                                    $imagenesIntervenciones[
                                        (int)$intervencion[
                                            'id_intervencion'
                                        ]
                                    ]
                                    ?? [];

                                ?>


                                <?php if (
                                    !empty(
                                        $fotosIntervencion
                                    )
                                ): ?>

                                    <div class="galeria mt-3">


                                        <?php foreach (
                                            $fotosIntervencion
                                            as $foto
                                        ): ?>

                                            <div
                                                class="foto-ticket"
                                                data-imagen="<?= e(
                                                    UPLOAD_INTERVENCIONES_URL
                                                    .
                                                    $foto['archivo']
                                                ) ?>"
                                            >

                                                <img
                                                    src="<?= e(
                                                        UPLOAD_INTERVENCIONES_URL
                                                        .
                                                        $foto['archivo']
                                                    ) ?>"
                                                    alt="Fotografía de intervención"
                                                    loading="lazy"
                                                >

                                                <span class="foto-etiqueta">

                                                    <?= e(
                                                        $foto['tipo']
                                                    ) ?>

                                                </span>

                                            </div>

                                        <?php endforeach; ?>


                                    </div>

                                <?php endif; ?>


                            </article>

                        <?php endforeach; ?>


                    <?php endif; ?>


                </div>

            </div>



            <!-- =============================================
                 COMENTARIOS
            ============================================== -->

            <div class="ticket-card">

                <div class="ticket-card-header">

                    <h5>

                        <i class="bi bi-chat-left-text me-2"></i>

                        Comentarios

                    </h5>

                    <span class="badge bg-secondary">

                        <?= count(
                            $comentarios
                        ) ?>

                    </span>

                </div>


                <div class="ticket-card-body">


                    <?php if (
                        $errorComentario !== ''
                    ): ?>

                        <div class="alert alert-danger">

                            <?= e(
                                $errorComentario
                            ) ?>

                        </div>

                    <?php endif; ?>


                    <?php if (
                        empty(
                            $comentarios
                        )
                    ): ?>

                        <div class="empty-box">

                            <i class="bi bi-chat"></i>

                            Todavía no hay comentarios.

                        </div>

                    <?php else: ?>


                        <?php foreach (
                            $comentarios
                            as $comentario
                        ): ?>

                            <div class="comentario">


                                <div class="comentario-avatar">

                                    <?= e(
                                        mb_strtoupper(
                                            mb_substr(
                                                $comentario['usuario'],
                                                0,
                                                1
                                            )
                                        )
                                    ) ?>

                                </div>


                                <div class="flex-grow-1">


                                    <div class="comentario-top">

                                        <span class="comentario-nombre">

                                            <?= e(
                                                $comentario['usuario']
                                            ) ?>

                                        </span>


                                        <span class="rol-comentario">

                                            <?= e(
                                                $comentario['rol']
                                            ) ?>

                                        </span>


                                        <span class="comentario-fecha">

                                            <?= e(
                                                fechaArgentina(
                                                    $comentario['fecha']
                                                )
                                            ) ?>

                                        </span>

                                    </div>


                                    <div class="comentario-texto">

                                        <?= e(
                                            $comentario['comentario']
                                        ) ?>

                                    </div>


                                </div>

                            </div>

                        <?php endforeach; ?>


                    <?php endif; ?>



                    <!-- FORMULARIO COMENTARIO -->

                    <?php if (
                        !in_array(
                            $solicitud['estado'],
                            [
                                'Cerrada',
                                'Cancelada'
                            ],
                            true
                        )
                    ): ?>

                        <form
                            method="POST"
                            class="comentario-form mt-4"
                        >

                            <?= csrfInput() ?>

                            <input
                                type="hidden"
                                name="accion"
                                value="comentar"
                            >


                            <label
                                for="comentario"
                                class="form-label fw-bold"
                            >

                                Agregar comentario

                            </label>


                            <textarea
                                name="comentario"
                                id="comentario"
                                class="form-control"
                                maxlength="2000"
                                placeholder="Escribí un comentario, aclaración o consulta..."
                                required
                            ></textarea>


                            <div class="text-end mt-3">

                                <button
                                    type="submit"
                                    class="btn btn-comentar"
                                >

                                    <i class="bi bi-send me-1"></i>

                                    Enviar comentario

                                </button>

                            </div>

                        </form>

                    <?php endif; ?>


                </div>

            </div>


        </div>



        <!-- =================================================
             LATERAL
        ================================================== -->

        <div class="col-xl-4">


            <!-- =============================================
                 TÉCNICO
            ============================================== -->

            <div class="ticket-card">

                <div class="ticket-card-header">

                    <h5>

                        <i class="bi bi-person-gear me-2"></i>

                        Responsable

                    </h5>

                </div>


                <div class="ticket-card-body">


                    <?php if (
                        $tecnicoAsignado
                    ): ?>

                        <div class="tecnico-box">

                            <div class="tecnico-avatar">

                                <i class="bi bi-person"></i>

                            </div>

                            <div>

                                <strong>

                                    <?= e(
                                        trim(
                                            $tecnicoAsignado['nombre']
                                            . ' '
                                            . $tecnicoAsignado['apellido']
                                        )
                                    ) ?>

                                </strong>

                                <small>

                                    <?= e(
                                        $tecnicoAsignado['correo']
                                    ) ?>

                                </small>

                            </div>

                        </div>

                    <?php else: ?>

                        <div class="empty-box">

                            <i class="bi bi-person-dash"></i>

                            Todavía no hay un técnico asignado.

                        </div>

                    <?php endif; ?>


                </div>

            </div>



            <!-- =============================================
                 ACCIONES TÉCNICAS
            ============================================== -->

            <?php if (
                esPersonalTecnico()
            ): ?>

                <div class="ticket-card">

                    <div class="ticket-card-header">

                        <h5>

                            <i class="bi bi-gear me-2"></i>

                            Gestión técnica

                        </h5>

                    </div>


                    <div class="ticket-card-body">


                        <a
                            href="<?= url(
                                'tecnico/intervenir.php?id='
                                . $idSolicitud
                            ) ?>"
                            class="accion-tecnica"
                        >

                            <div class="accion-icon">

                                <i class="bi bi-tools"></i>

                            </div>

                            <div>

                                <strong>
                                    Registrar intervención
                                </strong>

                                <div class="small text-muted">

                                    Diagnóstico, trabajo y fotos

                                </div>

                            </div>

                        </a>


                        <?php if (
                            esAdministrador()
                        ): ?>

                            <a
                                href="<?= url(
                                    'admin/asignar.php?id='
                                    . $idSolicitud
                                ) ?>"
                                class="accion-tecnica"
                            >

                                <div class="accion-icon">

                                    <i class="bi bi-person-check"></i>

                                </div>

                                <div>

                                    <strong>
                                        Asignar técnico
                                    </strong>

                                    <div class="small text-muted">

                                        Seleccionar responsable

                                    </div>

                                </div>

                            </a>

                        <?php endif; ?>


                    </div>

                </div>

            <?php endif; ?>



            <!-- =============================================
                 MATERIALES
            ============================================== -->

            <div class="ticket-card">

                <div class="ticket-card-header">

                    <h5>

                        <i class="bi bi-box-seam me-2"></i>

                        Materiales / Repuestos

                    </h5>

                </div>


                <div class="ticket-card-body">


                    <?php if (
                        empty(
                            $materiales
                        )
                    ): ?>

                        <div class="empty-box">

                            <i class="bi bi-box"></i>

                            No hay materiales registrados.

                        </div>


                    <?php else: ?>


                        <?php foreach (
                            $materiales
                            as $material
                        ): ?>

                            <div class="material-item">

                                <div class="material-titulo">

                                    <?= e(
                                        $material['descripcion']
                                    ) ?>

                                </div>


                                <div class="material-meta">

                                    Cantidad:
                                    <?= (int)$material['cantidad'] ?>

                                    ·

                                    <?= e(
                                        $material['estado']
                                    ) ?>

                                </div>


                                <?php if (
                                    !empty(
                                        $material['costo_estimado']
                                    )
                                ): ?>

                                    <div class="material-meta">

                                        Costo estimado:

                                        <?= e(
                                            formatoDinero(
                                                $material['costo_estimado']
                                            )
                                        ) ?>

                                    </div>

                                <?php endif; ?>


                                <?php if (
                                    !empty(
                                        $material['enlace_compra']
                                    )
                                ): ?>

                                    <div class="mt-2">

                                        <a
                                            href="<?= e(
                                                $material['enlace_compra']
                                            ) ?>"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="small"
                                        >

                                            <i class="bi bi-box-arrow-up-right"></i>

                                            Ver enlace

                                        </a>

                                    </div>

                                <?php endif; ?>

                            </div>

                        <?php endforeach; ?>


                    <?php endif; ?>


                </div>

            </div>



            <!-- =============================================
                 REPUESTOS UTILIZADOS
            ============================================== -->

            <?php if (!empty($movimientosStock)): ?>

                <div class="ticket-card">

                    <div class="ticket-card-header">

                        <h5>

                            <i class="bi bi-box-seam me-2"></i>

                            Repuestos utilizados

                        </h5>

                    </div>


                    <div class="ticket-card-body">


                        <?php foreach (
                            $movimientosStock
                            as $movimiento
                        ): ?>

                            <div class="material-item">

                                <div class="material-titulo">

                                    <?= e(
                                        $movimiento['repuesto']
                                    ) ?>

                                </div>


                                <div class="material-meta">

                                    <?= e(
                                        $movimiento['direccion']
                                    ) ?>

                                    ·

                                    <?= (int)$movimiento['cantidad'] ?>

                                    <?= e(
                                        $movimiento['unidad']
                                    ) ?>

                                    ·

                                    <?= e(
                                        fechaArgentina(
                                            $movimiento['fecha']
                                        )
                                    ) ?>

                                </div>


                                <?php if (
                                    !empty(
                                        $movimiento['observaciones']
                                    )
                                ): ?>

                                    <div class="material-meta">

                                        <?= e(
                                            $movimiento['observaciones']
                                        ) ?>

                                    </div>

                                <?php endif; ?>

                            </div>

                        <?php endforeach; ?>


                    </div>

                </div>

            <?php endif; ?>



            <!-- =============================================
                 TURNOS PROGRAMADOS
            ============================================== -->

            <?php if (!empty($turnosSolicitud)): ?>

                <div class="ticket-card">

                    <div class="ticket-card-header">

                        <h5>

                            <i class="bi bi-calendar-check me-2"></i>

                            Turnos de reparación

                        </h5>

                    </div>


                    <div class="ticket-card-body">


                        <?php foreach (
                            $turnosSolicitud
                            as $turno
                        ): ?>

                            <div class="material-item">

                                <div class="material-titulo">

                                    <?= e(
                                        fechaCorta(
                                            $turno['fecha']
                                        )
                                    ) ?>

                                    ·

                                    <?= e(
                                        horaCorta(
                                            $turno['hora_desde']
                                        )
                                    ) ?>

                                    a

                                    <?= e(
                                        horaCorta(
                                            $turno['hora_hasta']
                                        )
                                    ) ?>

                                </div>


                                <div class="material-meta">

                                    <?= e(
                                        $turno['tecnico']
                                    ) ?>

                                    ·

                                    <?= e(
                                        $turno['estado']
                                    ) ?>

                                </div>

                            </div>

                        <?php endforeach; ?>


                    </div>

                </div>

            <?php endif; ?>



            <!-- =============================================
                 HISTORIAL
            ============================================== -->

            <div class="ticket-card">

                <div class="ticket-card-header">

                    <h5>

                        <i class="bi bi-clock-history me-2"></i>

                        Historial

                    </h5>

                </div>


                <div class="ticket-card-body">


                    <?php if (
                        empty(
                            $historial
                        )
                    ): ?>

                        <div class="empty-box">

                            <i class="bi bi-clock"></i>

                            Sin movimientos registrados.

                        </div>


                    <?php else: ?>

                        <div class="timeline">


                            <?php foreach (
                                array_reverse(
                                    $historial
                                )
                                as $evento
                            ): ?>

                                <div class="timeline-item">

                                    <div class="timeline-icon">

                                        <i class="bi bi-check"></i>

                                    </div>


                                    <div class="timeline-content">

                                        <div class="timeline-estado">

                                            <?php if (
                                                !empty(
                                                    $evento['estado_nuevo']
                                                )
                                            ): ?>

                                                <?= e(
                                                    $evento['estado_nuevo']
                                                ) ?>

                                            <?php else: ?>

                                                Actualización

                                            <?php endif; ?>

                                        </div>


                                        <?php if (
                                            !empty(
                                                $evento['descripcion']
                                            )
                                        ): ?>

                                            <div class="timeline-descripcion">

                                                <?= e(
                                                    $evento['descripcion']
                                                ) ?>

                                            </div>

                                        <?php endif; ?>


                                        <?php if (
                                            !empty(
                                                $evento['usuario']
                                            )
                                        ): ?>

                                            <div class="timeline-descripcion">

                                                Por:
                                                <?= e(
                                                    $evento['usuario']
                                                ) ?>

                                            </div>

                                        <?php endif; ?>


                                        <div class="timeline-fecha">

                                            <?= e(
                                                fechaArgentina(
                                                    $evento['fecha']
                                                )
                                            ) ?>

                                        </div>

                                    </div>

                                </div>

                            <?php endforeach; ?>


                        </div>

                    <?php endif; ?>


                </div>

            </div>


        </div>

    </div>

</div>



<!-- =========================================================
     MODAL IMÁGENES
========================================================= -->

<div
    class="modal fade modal-foto"
    id="modalFoto"
    tabindex="-1"
>

    <div
        class="modal-dialog
               modal-xl
               modal-dialog-centered"
    >

        <div class="modal-content">

            <div class="text-end mb-2">

                <button
                    type="button"
                    class="btn-close btn-close-white"
                    data-bs-dismiss="modal"
                ></button>

            </div>

            <img
                src=""
                id="imagenModal"
                alt="Imagen ampliada"
            >

        </div>

    </div>

</div>



<script>

// ============================================================
// AMPLIAR FOTOGRAFÍAS
// ============================================================

document
    .querySelectorAll(
        '.foto-ticket'
    )
    .forEach(
        function(elemento) {

            elemento.addEventListener(
                'click',
                function() {

                    const urlImagen =
                        this.dataset.imagen;

                    const imagenModal =
                        document.getElementById(
                            'imagenModal'
                        );

                    imagenModal.src =
                        urlImagen;


                    const modal =
                        new bootstrap.Modal(
                            document.getElementById(
                                'modalFoto'
                            )
                        );

                    modal.show();

                }
            );

        }
    );

</script>


<?php

require_once __DIR__
    . '/includes/footer.php';

?>