<?php
// ============================================================
// COLEGIO SAN JOSÉ
// Sistema de Gestión Técnica
// Archivo: /tecnica/tecnico/finalizar.php
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/funciones.php';
require_once __DIR__ . '/../includes/auth.php';


// ============================================================
// SOLO TÉCNICOS / ADMINISTRADORES
// ============================================================

requerirTecnico();


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
// DATOS DEL USUARIO
// ============================================================

$idTecnico =
    (int)usuarioId();

$rolActual =
    $_SESSION['usuario']['rol']
    ?? '';

$esAdministrador =
    $rolActual === 'Administrador';


// ============================================================
// ID SOLICITUD
// ============================================================

$idSolicitud =
    (int)(
        $_GET['id']
        ??
        $_POST['id_solicitud']
        ??
        0
    );


if ($idSolicitud <= 0) {

    flash(
        'error',
        'La solicitud indicada no es válida.'
    );

    header(
        'Location: '
        . url('tecnico/solicitudes.php')
    );

    exit;
}


// ============================================================
// OBTENER SOLICITUD
// ============================================================

$stmtSolicitud =
    $conexion->prepare("
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
                AS categoria

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

        WHERE
            s.id_solicitud = ?

        LIMIT 1
    ");


$stmtSolicitud->execute([
    $idSolicitud
]);


$solicitud =
    $stmtSolicitud->fetch(
        PDO::FETCH_ASSOC
    );


if (!$solicitud) {

    flash(
        'error',
        'La solicitud no existe.'
    );

    header(
        'Location: '
        . url('tecnico/solicitudes.php')
    );

    exit;
}


// ============================================================
// ASIGNACIÓN ACTUAL
// ============================================================

$stmtAsignacion =
    $conexion->prepare("
        SELECT

            sa.id_asignacion,
            sa.id_tecnico,
            sa.id_asignado_por,
            sa.observaciones,
            sa.fecha_asignacion,

            CONCAT(
                u.nombre,
                ' ',
                u.apellido
            ) AS tecnico,

            u.correo

        FROM solicitudes_asignaciones sa

        INNER JOIN usuarios u
            ON sa.id_tecnico =
               u.id_usuario

        WHERE
            sa.id_solicitud = ?

        AND
            sa.activo = 1

        ORDER BY
            sa.fecha_asignacion DESC

        LIMIT 1
    ");


$stmtAsignacion->execute([
    $idSolicitud
]);


$asignacion =
    $stmtAsignacion->fetch(
        PDO::FETCH_ASSOC
    );


// ============================================================
// VERIFICAR PERMISOS
//
// Un técnico solamente puede finalizar una solicitud que esté
// asignada actualmente a él.
//
// Un administrador puede realizar el cierre aunque la
// asignación corresponda a otro técnico.
// ============================================================

if (!$esAdministrador) {

    if (
        !$asignacion
        ||
        (int)$asignacion[
            'id_tecnico'
        ] !== $idTecnico
    ) {

        flash(
            'error',
            'No podés finalizar una solicitud que no está asignada a tu usuario.'
        );

        header(
            'Location: '
            . url('tecnico/solicitudes.php')
        );

        exit;
    }
}


// ============================================================
// INTERVENCIONES
// ============================================================

$stmtIntervenciones =
    $conexion->prepare("
        SELECT

            i.id_intervencion,
            i.id_tecnico,

            i.diagnostico,
            i.trabajo_realizado,
            i.materiales,
            i.observaciones,

            i.pendiente,
            i.motivo_pendiente,

            i.fecha_intervencion,

            CONCAT(
                u.nombre,
                ' ',
                u.apellido
            ) AS tecnico,

            (
                SELECT COUNT(*)

                FROM intervencion_imagenes ii

                WHERE
                    ii.id_intervencion =
                    i.id_intervencion
            ) AS imagenes

        FROM intervenciones i

        INNER JOIN usuarios u
            ON i.id_tecnico =
               u.id_usuario

        WHERE
            i.id_solicitud = ?

        ORDER BY
            i.fecha_intervencion DESC
    ");


$stmtIntervenciones->execute([
    $idSolicitud
]);


$intervenciones =
    $stmtIntervenciones->fetchAll(
        PDO::FETCH_ASSOC
    );


$totalIntervenciones =
    count(
        $intervenciones
    );


$ultimaIntervencion =
    $intervenciones[0]
    ?? null;


// ============================================================
// VALIDACIONES PARA PODER CERRAR
// ============================================================

$puedeFinalizar =
    true;

$motivoNoFinaliza =
    '';


// Ya cerrada
if (
    $solicitud['estado']
    === 'Cerrada'
) {

    $puedeFinalizar =
        false;

    $motivoNoFinaliza =
        'Esta solicitud ya se encuentra cerrada.';
}


// Cancelada
elseif (
    $solicitud['estado']
    === 'Cancelada'
) {

    $puedeFinalizar =
        false;

    $motivoNoFinaliza =
        'Una solicitud cancelada no puede finalizarse.';
}


// Sin intervenciones
elseif (
    $totalIntervenciones === 0
) {

    $puedeFinalizar =
        false;

    $motivoNoFinaliza =
        'Primero debés registrar al menos una intervención técnica.';
}


// Pendiente
elseif (
    $solicitud['estado']
    === 'Pendiente'
) {

    $puedeFinalizar =
        false;

    $motivoNoFinaliza =
        'La solicitud está pendiente. Primero debe resolverse el motivo pendiente.';
}


// Última intervención pendiente
elseif (
    $ultimaIntervencion
    &&
    (int)$ultimaIntervencion[
        'pendiente'
    ] === 1
) {

    $puedeFinalizar =
        false;

    $motivoNoFinaliza =
        'La última intervención quedó pendiente y debe resolverse antes del cierre.';
}


// Para cerrar debería estar Resuelta
elseif (
    $solicitud['estado']
    !== 'Resuelta'
) {

    $puedeFinalizar =
        false;

    $motivoNoFinaliza =
        'Antes de finalizar, registrá una intervención marcando el problema como resuelto.';
}


// Sin asignación activa
elseif (
    !$asignacion
) {

    $puedeFinalizar =
        false;

    $motivoNoFinaliza =
        'La solicitud no posee una asignación técnica activa.';
}


// ============================================================
// FORMULARIO
// ============================================================

$error = '';

$observacionFinal =
    trim(
        $_POST['observacion_final']
        ?? ''
    );


$confirmacion =
    isset(
        $_POST['confirmar_cierre']
    );


// ============================================================
// PROCESAR CIERRE
// ============================================================

if (
    $_SERVER['REQUEST_METHOD']
    === 'POST'
) {

    // ========================================================
    // CSRF
    // ========================================================

    if (
        !validarCsrf(
            $_POST['csrf_token']
            ?? ''
        )
    ) {

        $error =
            'La sesión del formulario expiró. Actualizá la página e intentá nuevamente.';

    } elseif (
        !$puedeFinalizar
    ) {

        $error =
            $motivoNoFinaliza;

    } elseif (
        !$confirmacion
    ) {

        $error =
            'Debés confirmar que el trabajo fue verificado antes de cerrar la solicitud.';

    } elseif (
        mb_strlen(
            $observacionFinal
        ) > 3000
    ) {

        $error =
            'La observación final no puede superar los 3000 caracteres.';
    }


    // ========================================================
    // VOLVER A COMPROBAR ESTADO
    //
    // Esto evita cerrar el ticket con datos desactualizados si
    // otro usuario modificó la solicitud mientras el técnico
    // tenía la página abierta.
    // ========================================================

    if ($error === '') {

        $stmtComprobar =
            $conexion->prepare("
                SELECT

                    estado,
                    motivo_pendiente

                FROM solicitudes

                WHERE id_solicitud = ?

                LIMIT 1
            ");


        $stmtComprobar->execute([
            $idSolicitud
        ]);


        $estadoActual =
            $stmtComprobar->fetch(
                PDO::FETCH_ASSOC
            );


        if (
            !$estadoActual
            ||
            $estadoActual['estado']
            !== 'Resuelta'
        ) {

            $error =
                'La solicitud cambió de estado. Actualizá la página antes de continuar.';
        }
    }


    // ========================================================
    // COMPROBAR ASIGNACIÓN
    // ========================================================

    if ($error === '') {

        $stmtComprobarAsignacion =
            $conexion->prepare("
                SELECT

                    id_asignacion,
                    id_tecnico

                FROM solicitudes_asignaciones

                WHERE
                    id_solicitud = ?

                AND
                    activo = 1

                ORDER BY
                    fecha_asignacion DESC

                LIMIT 1
            ");


        $stmtComprobarAsignacion
            ->execute([
                $idSolicitud
            ]);


        $asignacionActual =
            $stmtComprobarAsignacion
                ->fetch(
                    PDO::FETCH_ASSOC
                );


        if (!$asignacionActual) {

            $error =
                'La solicitud ya no tiene una asignación activa.';

        } elseif (
            !$esAdministrador
            &&
            (int)$asignacionActual[
                'id_tecnico'
            ] !== $idTecnico
        ) {

            $error =
                'La solicitud fue reasignada a otro técnico.';
        }
    }


    // ========================================================
    // FINALIZAR
    // ========================================================

    if ($error === '') {

        try {

            $conexion->beginTransaction();


            // =================================================
            // CERRAR SOLICITUD
            // =================================================

            $stmtCerrar =
                $conexion->prepare("
                    UPDATE solicitudes

                    SET
                        estado = 'Cerrada',
                        motivo_pendiente = NULL,
                        fecha_actualizacion = NOW(),

                        fecha_resolucion =
                            COALESCE(
                                fecha_resolucion,
                                NOW()
                            )

                    WHERE id_solicitud = ?

                    AND estado = 'Resuelta'
                ");


            $stmtCerrar->execute([
                $idSolicitud
            ]);


            if (
                $stmtCerrar->rowCount()
                !== 1
            ) {

                throw new RuntimeException(
                    'No fue posible cambiar el estado de la solicitud.'
                );
            }


            // =================================================
            // FINALIZAR ASIGNACIÓN
            // =================================================

            $stmtFinalizarAsignacion =
                $conexion->prepare("
                    UPDATE solicitudes_asignaciones

                    SET
                        activo = 0,
                        fecha_fin = NOW()

                    WHERE
                        id_asignacion = ?

                    AND
                        activo = 1
                ");


            $stmtFinalizarAsignacion
                ->execute([
                    (int)$asignacionActual[
                        'id_asignacion'
                    ]
                ]);


            if (
                $stmtFinalizarAsignacion
                    ->rowCount()
                !== 1
            ) {

                throw new RuntimeException(
                    'No se pudo finalizar la asignación.'
                );
            }


            // =================================================
            // HISTORIAL
            // =================================================

            $descripcionHistorial =
                'El trabajo técnico fue finalizado y la solicitud quedó cerrada.';


            if (
                $observacionFinal !== ''
            ) {

                $descripcionHistorial .=
                    ' Observación final: '
                    .
                    $observacionFinal;
            }


            $stmtHistorial =
                $conexion->prepare("
                    INSERT INTO solicitud_historial
                    (
                        id_solicitud,
                        id_usuario,
                        estado_anterior,
                        estado_nuevo,
                        descripcion
                    )
                    VALUES
                    (
                        ?,
                        ?,
                        'Resuelta',
                        'Cerrada',
                        ?
                    )
                ");


            $stmtHistorial->execute([

                $idSolicitud,

                $idTecnico,

                $descripcionHistorial

            ]);


            // =================================================
            // COMENTARIO FINAL OPCIONAL
            //
            // Si existe observación, también la agregamos a
            // comentarios para que quede visible en el ticket.
            // =================================================

            if (
                $observacionFinal !== ''
            ) {

                $stmtComentario =
                    $conexion->prepare("
                        INSERT INTO comentarios
                        (
                            id_solicitud,
                            id_usuario,
                            comentario,
                            fecha_creacion
                        )
                        VALUES
                        (
                            ?,
                            ?,
                            ?,
                            NOW()
                        )
                    ");


                $stmtComentario->execute([

                    $idSolicitud,

                    $idTecnico,

                    'Cierre técnico: '
                    .
                    $observacionFinal

                ]);
            }


            // =================================================
            // NOTIFICAR AL DOCENTE
            // =================================================

            if (
                (int)$solicitud[
                    'id_usuario'
                ]
                !==
                $idTecnico
            ) {

                $mensaje =
                    'La solicitud '
                    .
                    numeroTicket(
                        $idSolicitud
                    )
                    .
                    ' fue finalizada y cerrada.';


                if (
                    $observacionFinal !== ''
                ) {

                    $mensaje .=
                        ' Observación: '
                        .
                        $observacionFinal;
                }


                crearNotificacion(

                    $conexion,

                    (int)$solicitud[
                        'id_usuario'
                    ],

                    'Solicitud finalizada',

                    $mensaje,

                    'ver_solicitud.php?id='
                    .
                    $idSolicitud

                );
            }


            // =================================================
            // COMMIT
            // =================================================

            $conexion->commit();


            flash(
                'success',
                'La solicitud fue finalizada y cerrada correctamente.'
            );


            header(
                'Location: '
                .
                url(
                    'ver_solicitud.php?id='
                    .
                    $idSolicitud
                )
            );

            exit;


        } catch (Throwable $e) {

            if (
                $conexion->inTransaction()
            ) {

                $conexion->rollBack();
            }


            error_log(
                'Error finalizando solicitud '
                .
                $idSolicitud
                .
                ': '
                .
                $e->getMessage()
            );


            $error =
                'No se pudo finalizar la solicitud. Intentá nuevamente.';
        }
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
    . '/../includes/header.php';

?>


<style>

/* ============================================================
   CONTENEDOR
============================================================ */

.finalizar-wrapper {

    max-width: 1250px;

    margin: 0 auto;

    padding:
        5px 12px
        50px;

}


/* ============================================================
   HERO
============================================================ */

.finalizar-hero {

    position: relative;

    overflow: hidden;

    padding: 29px;

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


.finalizar-hero::after {

    content: "";

    position: absolute;

    width: 290px;

    height: 290px;

    right: -105px;

    top: -140px;

    border-radius: 50%;

    background:
        rgba(255,255,255,.06);

}


.hero-content {

    position: relative;

    z-index: 2;

}


.hero-ticket {

    color:
        rgba(255,255,255,.70);

    font-size: 11px;

    font-weight: 800;

}


.finalizar-hero h1 {

    margin:
        5px 0 7px;

    font-size: 27px;

    font-weight: 800;

}


.finalizar-hero p {

    margin: 0;

    max-width: 720px;

    color:
        rgba(255,255,255,.82);

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
        10px 16px;

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
   CARD
============================================================ */

.finalizar-card {

    overflow: hidden;

    height: 100%;

    border:
        1px solid #ECECEC;

    border-radius: 18px;

    background: #FFFFFF;

    box-shadow:
        0 5px 19px
        rgba(0,0,0,.05);

}


.finalizar-card-header {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 10px;

    padding:
        18px 20px;

    border-bottom:
        1px solid #EEEEEE;

}


.finalizar-card-header h5 {

    margin: 0;

    color: #760000;

    font-size: 16px;

    font-weight: 800;

}


.finalizar-card-body {

    padding: 21px;

}


/* ============================================================
   DATOS
============================================================ */

.ticket-title {

    color: #333333;

    font-size: 19px;

    font-weight: 800;

}


.ticket-description {

    margin-top: 10px;

    padding: 14px;

    border-radius: 10px;

    color: #5C5C5C;

    background: #F8F8F8;

    font-size: 12px;

    line-height: 1.6;

}


.info-grid {

    display: grid;

    grid-template-columns:
        repeat(2, 1fr);

    gap: 12px;

    margin-top: 17px;

}


.info-item {

    padding: 12px;

    border-radius: 9px;

    background: #FAFAFA;

}


.info-label {

    color: #989898;

    font-size: 9px;

    font-weight: 800;

    text-transform: uppercase;

}


.info-value {

    margin-top: 4px;

    color: #444444;

    font-size: 11px;

    font-weight: 700;

}


/* ============================================================
   ÚLTIMA INTERVENCIÓN
============================================================ */

.intervencion-box {

    padding: 14px;

    border:
        1px solid #E9E9E9;

    border-radius: 11px;

    background: #FBFBFB;

}


.intervencion-title {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 10px;

    margin-bottom: 12px;

}


.intervencion-tecnico {

    color: #333333;

    font-size: 12px;

    font-weight: 800;

}


.intervencion-fecha {

    color: #999999;

    font-size: 9px;

}


.intervencion-section {

    margin-top: 10px;

}


.intervencion-label {

    margin-bottom: 3px;

    color: #760000;

    font-size: 9px;

    font-weight: 800;

    text-transform: uppercase;

}


.intervencion-text {

    color: #606060;

    font-size: 11px;

    line-height: 1.55;

}


/* ============================================================
   CHECKLIST
============================================================ */

.check-item {

    display: flex;

    align-items: flex-start;

    gap: 10px;

    padding:
        11px 0;

    border-bottom:
        1px solid #EEEEEE;

}


.check-item:last-child {

    border-bottom: 0;

}


.check-icon {

    min-width: 34px;

    width: 34px;

    height: 34px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 50%;

}


.check-ok {

    color: #198754;

    background: #E5F5EA;

}


.check-error {

    color: #B12626;

    background: #FFE8E8;

}


.check-title {

    color: #404040;

    font-size: 11px;

    font-weight: 800;

}


.check-text {

    margin-top: 2px;

    color: #888888;

    font-size: 9px;

}


/* ============================================================
   FORMULARIO
============================================================ */

.form-label {

    color: #444444;

    font-size: 12px;

    font-weight: 800;

}


.form-control {

    border-radius: 9px;

}


textarea.form-control {

    min-height: 130px;

    resize: vertical;

}


.form-control:focus {

    border-color: #B12626;

    box-shadow:
        0 0 0 .2rem
        rgba(177,38,38,.08);

}


.confirm-box {

    padding: 14px;

    border:
        1px solid #E5D1D1;

    border-radius: 11px;

    background: #FFF8F8;

}


.confirm-box .form-check-label {

    color: #555555;

    font-size: 11px;

    line-height: 1.5;

}


.form-check-input:checked {

    border-color: #B12626;

    background-color: #B12626;

}


.final-warning {

    margin-bottom: 20px;

    padding: 14px;

    border-left:
        4px solid #B12626;

    border-radius: 9px;

    color: #654242;

    background: #FFF5F5;

    font-size: 11px;

    line-height: 1.55;

}


.btn-finalizar {

    width: 100%;

    min-height: 48px;

    border: 0;

    border-radius: 10px;

    color: #FFFFFF;

    background:
        linear-gradient(
            135deg,
            #760000,
            #B12626
        );

    font-weight: 800;

}


.btn-finalizar:hover {

    color: #FFFFFF;

    background: #760000;

}


.btn-finalizar:disabled {

    opacity: .60;

}


/* ============================================================
   BLOQUEADO
============================================================ */

.blocked-box {

    padding: 25px 18px;

    border-radius: 12px;

    background: #F7F7F7;

    text-align: center;

}


.blocked-box i {

    display: block;

    margin-bottom: 10px;

    color: #B12626;

    font-size: 38px;

}


.blocked-box strong {

    color: #444444;

    font-size: 14px;

}


.blocked-box p {

    margin:
        7px auto 0;

    max-width: 500px;

    color: #777777;

    font-size: 11px;

    line-height: 1.55;

}


/* ============================================================
   RESPONSIVE
============================================================ */

@media
(max-width: 767px) {

    .finalizar-hero {

        padding:
            22px 20px;

    }


    .finalizar-hero h1 {

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


    .info-grid {

        grid-template-columns: 1fr;

    }

}

</style>


<div class="finalizar-wrapper">


    <!-- =====================================================
         HERO
    ====================================================== -->

    <section class="finalizar-hero">

        <div class="row align-items-center">


            <div class="col-lg-8">

                <div class="hero-content">


                    <div class="hero-ticket">

                        <?= e(
                            numeroTicket(
                                $idSolicitud
                            )
                        ) ?>

                    </div>


                    <h1>

                        <i class="bi bi-check2-circle me-1"></i>

                        Finalizar trabajo

                    </h1>


                    <p>

                        Revisá la intervención realizada antes
                        de realizar el cierre definitivo de la
                        solicitud.

                    </p>


                </div>

            </div>


            <div class="col-lg-4">

                <div class="hero-actions">


                    <a
                        href="<?= url(
                            'tecnico/intervenir.php?id='
                            .
                            $idSolicitud
                        ) ?>"
                        class="btn-hero btn-hero-outline"
                    >

                        <i class="bi bi-tools"></i>

                        Intervenciones

                    </a>


                    <a
                        href="<?= url(
                            'tecnico/solicitudes.php'
                        ) ?>"
                        class="btn-hero btn-hero-white"
                    >

                        <i class="bi bi-arrow-left"></i>

                        Mis solicitudes

                    </a>


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


    <?php if ($error !== ''): ?>

        <div class="alert alert-danger">

            <i class="bi bi-exclamation-triangle me-1"></i>

            <?= e(
                $error
            ) ?>

        </div>

    <?php endif; ?>


    <div class="row g-4">


        <!-- =================================================
             INFORMACIÓN
        ================================================== -->

        <div class="col-xl-7">


            <div class="finalizar-card mb-4">

                <div class="finalizar-card-header">

                    <h5>

                        <i class="bi bi-ticket-detailed me-2"></i>

                        Solicitud

                    </h5>


                    <div class="d-flex gap-1">

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

                </div>


                <div class="finalizar-card-body">


                    <div class="ticket-title">

                        <?= e(
                            $solicitud[
                                'titulo'
                            ]
                        ) ?>

                    </div>


                    <div class="ticket-description">

                        <?= nl2br(
                            e(
                                $solicitud[
                                    'descripcion'
                                ]
                            )
                        ) ?>

                    </div>


                    <div class="info-grid">


                        <div class="info-item">

                            <div class="info-label">
                                Solicitante
                            </div>

                            <div class="info-value">

                                <i class="bi bi-person me-1"></i>

                                <?= e(
                                    $solicitud[
                                        'solicitante'
                                    ]
                                ) ?>

                            </div>

                        </div>


                        <div class="info-item">

                            <div class="info-label">
                                Área
                            </div>

                            <div class="info-value">

                                <i class="<?= e(
                                    iconoTipo(
                                        $solicitud[
                                            'tipo'
                                        ]
                                    )
                                ) ?> me-1"></i>

                                <?= e(
                                    nombreTipo(
                                        $solicitud[
                                            'tipo'
                                        ]
                                    )
                                ) ?>

                            </div>

                        </div>


                        <div class="info-item">

                            <div class="info-label">
                                Sector
                            </div>

                            <div class="info-value">

                                <i class="bi bi-geo-alt me-1"></i>

                                <?= e(
                                    $solicitud[
                                        'sector'
                                    ]
                                    ?? 'Sin sector'
                                ) ?>

                            </div>

                        </div>


                        <div class="info-item">

                            <div class="info-label">
                                Categoría
                            </div>

                            <div class="info-value">

                                <?= e(
                                    $solicitud[
                                        'categoria'
                                    ]
                                    ?? 'Sin categoría'
                                ) ?>

                            </div>

                        </div>


                        <div class="info-item">

                            <div class="info-label">
                                Fecha de solicitud
                            </div>

                            <div class="info-value">

                                <i class="bi bi-calendar3 me-1"></i>

                                <?= e(
                                    fechaArgentina(
                                        $solicitud[
                                            'fecha_creacion'
                                        ]
                                    )
                                ) ?>

                            </div>

                        </div>


                        <div class="info-item">

                            <div class="info-label">
                                Fecha de resolución
                            </div>

                            <div class="info-value">

                                <?php if (
                                    !empty(
                                        $solicitud[
                                            'fecha_resolucion'
                                        ]
                                    )
                                ): ?>

                                    <i class="bi bi-check-circle me-1"></i>

                                    <?= e(
                                        fechaArgentina(
                                            $solicitud[
                                                'fecha_resolucion'
                                            ]
                                        )
                                    ) ?>

                                <?php else: ?>

                                    -

                                <?php endif; ?>

                            </div>

                        </div>


                    </div>


                </div>

            </div>


            <!-- =============================================
                 ÚLTIMA INTERVENCIÓN
            ============================================== -->

            <div class="finalizar-card">

                <div class="finalizar-card-header">

                    <h5>

                        <i class="bi bi-tools me-2"></i>

                        Última intervención

                    </h5>


                    <span class="badge bg-secondary">

                        <?= $totalIntervenciones ?>

                        <?= $totalIntervenciones === 1
                            ? 'intervención'
                            : 'intervenciones'
                        ?>

                    </span>

                </div>


                <div class="finalizar-card-body">


                    <?php if (
                        !$ultimaIntervencion
                    ): ?>

                        <div class="blocked-box">

                            <i class="bi bi-tools"></i>

                            <strong>
                                No hay intervenciones registradas
                            </strong>

                            <p>

                                Antes del cierre debés registrar
                                el diagnóstico y el trabajo realizado.

                            </p>


                            <a
                                href="<?= url(
                                    'tecnico/intervenir.php?id='
                                    .
                                    $idSolicitud
                                ) ?>"
                                class="btn btn-sanjo mt-3"
                            >

                                Registrar intervención

                            </a>

                        </div>


                    <?php else: ?>

                        <div class="intervencion-box">


                            <div class="intervencion-title">

                                <div class="intervencion-tecnico">

                                    <i class="bi bi-person-gear me-1"></i>

                                    <?= e(
                                        $ultimaIntervencion[
                                            'tecnico'
                                        ]
                                    ) ?>

                                </div>


                                <div class="intervencion-fecha">

                                    <?= e(
                                        fechaArgentina(
                                            $ultimaIntervencion[
                                                'fecha_intervencion'
                                            ]
                                        )
                                    ) ?>

                                </div>

                            </div>


                            <div class="intervencion-section">

                                <div class="intervencion-label">
                                    Diagnóstico
                                </div>

                                <div class="intervencion-text">

                                    <?= nl2br(
                                        e(
                                            $ultimaIntervencion[
                                                'diagnostico'
                                            ]
                                        )
                                    ) ?>

                                </div>

                            </div>


                            <div class="intervencion-section">

                                <div class="intervencion-label">
                                    Trabajo realizado
                                </div>

                                <div class="intervencion-text">

                                    <?= nl2br(
                                        e(
                                            $ultimaIntervencion[
                                                'trabajo_realizado'
                                            ]
                                        )
                                    ) ?>

                                </div>

                            </div>


                            <?php if (
                                !empty(
                                    $ultimaIntervencion[
                                        'materiales'
                                    ]
                                )
                            ): ?>

                                <div class="intervencion-section">

                                    <div class="intervencion-label">
                                        Materiales / repuestos
                                    </div>

                                    <div class="intervencion-text">

                                        <?= nl2br(
                                            e(
                                                $ultimaIntervencion[
                                                    'materiales'
                                                ]
                                            )
                                        ) ?>

                                    </div>

                                </div>

                            <?php endif; ?>


                            <?php if (
                                !empty(
                                    $ultimaIntervencion[
                                        'observaciones'
                                    ]
                                )
                            ): ?>

                                <div class="intervencion-section">

                                    <div class="intervencion-label">
                                        Observaciones
                                    </div>

                                    <div class="intervencion-text">

                                        <?= nl2br(
                                            e(
                                                $ultimaIntervencion[
                                                    'observaciones'
                                                ]
                                            )
                                        ) ?>

                                    </div>

                                </div>

                            <?php endif; ?>


                            <div class="intervencion-section">

                                <span class="small text-muted">

                                    <i class="bi bi-images me-1"></i>

                                    <?= (int)$ultimaIntervencion[
                                        'imagenes'
                                    ] ?>

                                    imágenes adjuntas

                                </span>

                            </div>


                        </div>

                    <?php endif; ?>


                </div>

            </div>


        </div>


        <!-- =================================================
             CIERRE
        ================================================== -->

        <div class="col-xl-5">


            <div class="finalizar-card">

                <div class="finalizar-card-header">

                    <h5>

                        <i class="bi bi-clipboard-check me-2"></i>

                        Cierre técnico

                    </h5>

                </div>


                <div class="finalizar-card-body">


                    <!-- =====================================
                         CHECKLIST
                    ====================================== -->

                    <div class="mb-4">


                        <!-- INTERVENCIÓN -->

                        <div class="check-item">

                            <div
                                class="check-icon <?= $totalIntervenciones > 0
                                    ? 'check-ok'
                                    : 'check-error'
                                ?>"
                            >

                                <i
                                    class="bi <?= $totalIntervenciones > 0
                                        ? 'bi-check-lg'
                                        : 'bi-x-lg'
                                    ?>"
                                ></i>

                            </div>


                            <div>

                                <div class="check-title">
                                    Intervención registrada
                                </div>

                                <div class="check-text">

                                    <?= $totalIntervenciones > 0
                                        ? 'La solicitud posee registro técnico.'
                                        : 'Falta registrar una intervención.'
                                    ?>

                                </div>

                            </div>

                        </div>


                        <!-- RESUELTA -->

                        <div class="check-item">

                            <div
                                class="check-icon <?= $solicitud['estado'] === 'Resuelta'
                                    ? 'check-ok'
                                    : 'check-error'
                                ?>"
                            >

                                <i
                                    class="bi <?= $solicitud['estado'] === 'Resuelta'
                                        ? 'bi-check-lg'
                                        : 'bi-x-lg'
                                    ?>"
                                ></i>

                            </div>


                            <div>

                                <div class="check-title">
                                    Problema resuelto
                                </div>

                                <div class="check-text">

                                    Estado actual:
                                    <?= e(
                                        $solicitud[
                                            'estado'
                                        ]
                                    ) ?>.

                                </div>

                            </div>

                        </div>


                        <!-- PENDIENTES -->

                        <?php

                        $sinPendientes =
                            $solicitud[
                                'estado'
                            ] !== 'Pendiente'
                            &&
                            (
                                !$ultimaIntervencion
                                ||
                                (int)$ultimaIntervencion[
                                    'pendiente'
                                ] === 0
                            );

                        ?>


                        <div class="check-item">

                            <div
                                class="check-icon <?= $sinPendientes
                                    ? 'check-ok'
                                    : 'check-error'
                                ?>"
                            >

                                <i
                                    class="bi <?= $sinPendientes
                                        ? 'bi-check-lg'
                                        : 'bi-x-lg'
                                    ?>"
                                ></i>

                            </div>


                            <div>

                                <div class="check-title">
                                    Sin pendientes
                                </div>

                                <div class="check-text">

                                    <?= $sinPendientes
                                        ? 'No existen motivos pendientes activos.'
                                        : 'Todavía existen pendientes por resolver.'
                                    ?>

                                </div>

                            </div>

                        </div>


                        <!-- ASIGNACIÓN -->

                        <div class="check-item">

                            <div
                                class="check-icon <?= $asignacion
                                    ? 'check-ok'
                                    : 'check-error'
                                ?>"
                            >

                                <i
                                    class="bi <?= $asignacion
                                        ? 'bi-check-lg'
                                        : 'bi-x-lg'
                                    ?>"
                                ></i>

                            </div>


                            <div>

                                <div class="check-title">
                                    Asignación activa
                                </div>

                                <div class="check-text">

                                    <?php if (
                                        $asignacion
                                    ): ?>

                                        Responsable:

                                        <?= e(
                                            $asignacion[
                                                'tecnico'
                                            ]
                                        ) ?>

                                    <?php else: ?>

                                        No existe una asignación activa.

                                    <?php endif; ?>

                                </div>

                            </div>

                        </div>


                    </div>


                    <?php if (
                        !$puedeFinalizar
                    ): ?>

                        <!-- =================================
                             BLOQUEADO
                        ================================== -->

                        <div class="blocked-box">

                            <i class="bi bi-exclamation-circle"></i>

                            <strong>
                                No se puede finalizar todavía
                            </strong>

                            <p>

                                <?= e(
                                    $motivoNoFinaliza
                                ) ?>

                            </p>


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
                                    class="btn btn-sanjo mt-3"
                                >

                                    <i class="bi bi-tools me-1"></i>

                                    Ir a intervención

                                </a>

                            <?php endif; ?>


                        </div>


                    <?php else: ?>


                        <!-- =================================
                             FORMULARIO
                        ================================== -->

                        <div class="final-warning">

                            <i class="bi bi-info-circle me-1"></i>

                            Al finalizar el trabajo, la solicitud
                            cambiará de <strong>Resuelta</strong>
                            a <strong>Cerrada</strong> y tu
                            asignación dejará de estar activa.

                            Las intervenciones y fotografías
                            permanecerán guardadas como historial.

                        </div>


                        <form
                            method="POST"
                            action="<?= url(
                                'tecnico/finalizar.php?id='
                                .
                                $idSolicitud
                            ) ?>"
                            id="formFinalizar"
                        >

                            <?= csrfInput() ?>


                            <input
                                type="hidden"
                                name="id_solicitud"
                                value="<?= $idSolicitud ?>"
                            >


                            <!-- OBSERVACIÓN -->

                            <div class="mb-4">

                                <label
                                    for="observacion_final"
                                    class="form-label"
                                >

                                    <i class="bi bi-journal-check me-1"></i>

                                    Observación final

                                </label>


                                <textarea
                                    name="observacion_final"
                                    id="observacion_final"
                                    class="form-control"
                                    maxlength="3000"
                                    placeholder="Opcional. Ej.: Se realizaron pruebas finales y el equipo quedó funcionando correctamente."
                                ><?= e(
                                    $observacionFinal
                                ) ?></textarea>


                                <div class="form-text">

                                    Esta observación quedará
                                    registrada en el historial
                                    y será visible en el ticket.

                                </div>

                            </div>


                            <!-- CONFIRMAR -->

                            <div class="confirm-box mb-4">

                                <div class="form-check">

                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        name="confirmar_cierre"
                                        id="confirmar_cierre"
                                        value="1"
                                        required
                                    >


                                    <label
                                        class="form-check-label"
                                        for="confirmar_cierre"
                                    >

                                        Confirmo que el trabajo fue
                                        realizado, el funcionamiento
                                        fue verificado y no quedan
                                        tareas técnicas pendientes.

                                    </label>

                                </div>

                            </div>


                            <button
                                type="submit"
                                class="btn btn-finalizar"
                                id="btnFinalizar"
                            >

                                <i class="bi bi-check2-circle me-1"></i>

                                Finalizar y cerrar solicitud

                            </button>


                        </form>


                    <?php endif; ?>


                </div>

            </div>


        </div>


    </div>


</div>


<script>

// ============================================================
// CONFIRMACIÓN DEL CIERRE
// ============================================================

const formFinalizar =
    document.getElementById(
        'formFinalizar'
    );


const btnFinalizar =
    document.getElementById(
        'btnFinalizar'
    );


if (
    formFinalizar
    &&
    btnFinalizar
) {

    formFinalizar.addEventListener(
        'submit',
        function(evento) {

            const confirmar =
                confirm(
                    '¿Confirmás el cierre definitivo de esta solicitud?'
                );


            if (!confirmar) {

                evento.preventDefault();

                return;
            }


            btnFinalizar.disabled =
                true;


            btnFinalizar.innerHTML =
                '<span class="spinner-border '
                +
                'spinner-border-sm me-2"></span>'
                +
                'Finalizando solicitud...';

        }
    );

}

</script>


<?php

require_once __DIR__
    . '/../includes/footer.php';

?>