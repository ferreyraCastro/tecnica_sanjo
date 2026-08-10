<?php
// ============================================================
// COLEGIO SAN JOSÉ
// Sistema de Gestión Técnica
// Archivo: /tecnica/admin/asignar.php
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/funciones.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mailer.php';


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
        . url(
            'admin/solicitudes.php'
        )
    );

    exit;
}


// ============================================================
// OBTENER SOLICITUD
// ============================================================

$solicitud =
    obtenerSolicitud(
        $conexion,
        $idSolicitud
    );


if (!$solicitud) {

    flash(
        'error',
        'La solicitud no existe.'
    );

    header(
        'Location: '
        . url(
            'admin/solicitudes.php'
        )
    );

    exit;
}


// ============================================================
// NO PERMITIR MODIFICAR SOLICITUD CERRADA / CANCELADA
// ============================================================

if (
    in_array(
        $solicitud['estado'],
        [
            'Cerrada',
            'Cancelada'
        ],
        true
    )
) {

    flash(
        'error',
        'No se puede modificar la asignación de una solicitud '
        . strtolower(
            $solicitud['estado']
        )
        . '.'
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


// ============================================================
// OBTENER TÉCNICOS
// ============================================================

$tecnicos =
    obtenerTecnicos(
        $conexion
    );


// ============================================================
// ASIGNACIÓN ACTUAL
// ============================================================

$stmtActual =
    $conexion->prepare("
        SELECT

            sa.id_asignacion,
            sa.id_tecnico,
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


$stmtActual->execute([
    $idSolicitud
]);


$asignacionActual =
    $stmtActual->fetch(
        PDO::FETCH_ASSOC
    );


// ============================================================
// PROCESAR ASIGNACIÓN
// ============================================================

$error = '';


if (
    $_SERVER['REQUEST_METHOD']
    === 'POST'
) {

    // ========================================================
    // VALIDAR CSRF
    // ========================================================

    if (
        !validarCsrf(
            $_POST['csrf_token']
            ?? ''
        )
    ) {

        $error =
            'La sesión del formulario expiró. '
            . 'Actualizá la página e intentá nuevamente.';

    } else {

        $accion =
            limpiarTexto(
                $_POST['accion']
                ?? 'asignar'
            );


        // ====================================================
        // QUITAR ASIGNACIÓN
        // ====================================================

        if ($accion === 'quitar') {

            if (!$asignacionActual) {

                $error =
                    'La solicitud no tiene un técnico asignado.';

            } else {

                try {

                    $conexion->beginTransaction();


                    // ========================================
                    // DESACTIVAR ASIGNACIÓN
                    // ========================================

                    $stmt =
                        $conexion->prepare("
                            UPDATE solicitudes_asignaciones

                            SET
                                activo = 0,
                                fecha_fin = NOW()

                            WHERE id_solicitud = ?

                            AND activo = 1
                        ");


                    $stmt->execute([
                        $idSolicitud
                    ]);


                    // ========================================
                    // CAMBIAR ESTADO
                    //
                    // Solamente volvemos a Nueva si estaba
                    // simplemente Asignada.
                    // ========================================

                    if (
                        $solicitud['estado']
                        === 'Asignada'
                    ) {

                        $stmtEstado =
                            $conexion->prepare("
                                UPDATE solicitudes

                                SET
                                    estado = 'Nueva',
                                    fecha_actualizacion = NOW()

                                WHERE id_solicitud = ?
                            ");


                        $stmtEstado->execute([
                            $idSolicitud
                        ]);
                    }


                    // ========================================
                    // HISTORIAL
                    // ========================================

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
                                ?,
                                ?,
                                ?
                            )
                        ");


                    $estadoNuevo =
                        $solicitud['estado']
                        === 'Asignada'
                            ? 'Nueva'
                            : $solicitud['estado'];


                    $stmtHistorial->execute([

                        $idSolicitud,

                        (int)usuarioId(),

                        $solicitud['estado'],

                        $estadoNuevo,

                        'Se quitó la asignación del técnico '
                        .
                        $asignacionActual['tecnico']
                        .
                        '.'

                    ]);


                    // ========================================
                    // NOTIFICAR AL TÉCNICO ANTERIOR
                    // ========================================

                    crearNotificacion(

                        $conexion,

                        (int)$asignacionActual[
                            'id_tecnico'
                        ],

                        'Asignación modificada',

                        'Ya no estás asignado a la solicitud '
                        .
                        numeroTicket(
                            $idSolicitud
                        )
                        .
                        ': '
                        .
                        $solicitud['titulo'],

                        'ver_solicitud.php?id='
                        .
                        $idSolicitud

                    );


                    $conexion->commit();


                    flash(
                        'success',
                        'La asignación fue eliminada correctamente.'
                    );


                    header(
                        'Location: '
                        . url(
                            'admin/asignar.php?id='
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
                        'Error quitando asignación: '
                        .
                        $e->getMessage()
                    );


                    $error =
                        'No se pudo quitar la asignación.';
                }
            }

        } else {

            // =================================================
            // ASIGNAR / REASIGNAR
            // =================================================

            $idTecnico =
                (int)(
                    $_POST['id_tecnico']
                    ?? 0
                );


            $observaciones =
                limpiarTexto(
                    $_POST['observaciones']
                    ?? ''
                );


            // =================================================
            // VALIDAR TÉCNICO
            // =================================================

            if ($idTecnico <= 0) {

                $error =
                    'Seleccioná un técnico responsable.';

            } elseif (
                mb_strlen(
                    $observaciones
                ) > 1000
            ) {

                $error =
                    'Las observaciones no pueden superar '
                    . 'los 1000 caracteres.';

            } else {

                $stmtTecnico =
                    $conexion->prepare("
                        SELECT

                            id_usuario,
                            nombre,
                            apellido,
                            correo,
                            telefono,
                            whatsapp_apikey,
                            rol,
                            estado

                        FROM usuarios

                        WHERE id_usuario = ?

                        AND rol IN
                        (
                            'Tecnico',
                            'Administrador'
                        )

                        AND estado = 'Activo'

                        LIMIT 1
                    ");


                $stmtTecnico->execute([
                    $idTecnico
                ]);


                $tecnicoSeleccionado =
                    $stmtTecnico->fetch(
                        PDO::FETCH_ASSOC
                    );


                if (!$tecnicoSeleccionado) {

                    $error =
                        'El técnico seleccionado no es válido '
                        . 'o se encuentra inactivo.';
                }
            }


            // =================================================
            // YA ESTÁ ASIGNADO
            // =================================================

            if (
                $error === ''
                &&
                $asignacionActual
                &&
                (int)$asignacionActual[
                    'id_tecnico'
                ] === $idTecnico
            ) {

                $error =
                    'Ese técnico ya está asignado '
                    . 'a esta solicitud.';
            }


            // =================================================
            // GUARDAR
            // =================================================

            if ($error === '') {

                try {

                    $conexion->beginTransaction();


                    // =========================================
                    // DESACTIVAR ASIGNACIÓN ANTERIOR
                    // =========================================

                    $stmtCerrar =
                        $conexion->prepare("
                            UPDATE solicitudes_asignaciones

                            SET
                                activo = 0,
                                fecha_fin = NOW()

                            WHERE id_solicitud = ?

                            AND activo = 1
                        ");


                    $stmtCerrar->execute([
                        $idSolicitud
                    ]);


                    // =========================================
                    // NUEVA ASIGNACIÓN
                    // =========================================

                    $stmtAsignar =
                        $conexion->prepare("
                            INSERT INTO solicitudes_asignaciones
                            (
                                id_solicitud,
                                id_tecnico,
                                asignado_por,
                                observaciones,
                                activo,
                                fecha_asignacion
                            )
                            VALUES
                            (
                                ?,
                                ?,
                                ?,
                                ?,
                                1,
                                NOW()
                            )
                        ");


                    $stmtAsignar->execute([

                        $idSolicitud,

                        $idTecnico,

                        (int)usuarioId(),

                        $observaciones !== ''
                            ? $observaciones
                            : null

                    ]);


                    // =========================================
                    // ESTADO DE LA SOLICITUD
                    //
                    // Si está Nueva pasa automáticamente
                    // a Asignada.
                    // =========================================

                    $estadoAnterior =
                        $solicitud['estado'];


                    $estadoNuevo =
                        $estadoAnterior;


                    if (
                        $estadoAnterior
                        === 'Nueva'
                    ) {

                        $estadoNuevo =
                            'Asignada';


                        $stmtEstado =
                            $conexion->prepare("
                                UPDATE solicitudes

                                SET
                                    estado = 'Asignada',
                                    fecha_actualizacion = NOW()

                                WHERE id_solicitud = ?
                            ");


                        $stmtEstado->execute([
                            $idSolicitud
                        ]);

                    } else {

                        $stmtActualizar =
                            $conexion->prepare("
                                UPDATE solicitudes

                                SET
                                    fecha_actualizacion = NOW()

                                WHERE id_solicitud = ?
                            ");


                        $stmtActualizar->execute([
                            $idSolicitud
                        ]);
                    }


                    // =========================================
                    // NOMBRE TÉCNICO
                    // =========================================

                    $nombreTecnico =
                        trim(
                            $tecnicoSeleccionado[
                                'nombre'
                            ]
                            .
                            ' '
                            .
                            $tecnicoSeleccionado[
                                'apellido'
                            ]
                        );


                    // =========================================
                    // HISTORIAL
                    // =========================================

                    $descripcionHistorial =
                        'Solicitud asignada a '
                        .
                        $nombreTecnico
                        .
                        '.';


                    if ($asignacionActual) {

                        $descripcionHistorial =
                            'La solicitud fue reasignada de '
                            .
                            $asignacionActual[
                                'tecnico'
                            ]
                            .
                            ' a '
                            .
                            $nombreTecnico
                            .
                            '.';
                    }


                    if ($observaciones !== '') {

                        $descripcionHistorial .=
                            ' Observación: '
                            .
                            $observaciones;
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
                                ?,
                                ?,
                                ?
                            )
                        ");


                    $stmtHistorial->execute([

                        $idSolicitud,

                        (int)usuarioId(),

                        $estadoAnterior,

                        $estadoNuevo,

                        $descripcionHistorial

                    ]);


                    // =========================================
                    // NOTIFICAR TÉCNICO NUEVO
                    // =========================================

                    crearNotificacion(

                        $conexion,

                        $idTecnico,

                        'Nueva solicitud asignada',

                        'Se te asignó '
                        .
                        numeroTicket(
                            $idSolicitud
                        )
                        .
                        ': '
                        .
                        $solicitud['titulo'],

                        'ver_solicitud.php?id='
                        .
                        $idSolicitud

                    );


                    // =========================================
                    // AVISAR AL TÉCNICO ANTERIOR
                    // =========================================

                    if (
                        $asignacionActual
                        &&
                        (int)$asignacionActual[
                            'id_tecnico'
                        ] !== $idTecnico
                    ) {

                        crearNotificacion(

                            $conexion,

                            (int)$asignacionActual[
                                'id_tecnico'
                            ],

                            'Solicitud reasignada',

                            'La solicitud '
                            .
                            numeroTicket(
                                $idSolicitud
                            )
                            .
                            ' fue reasignada a otro responsable.',

                            'ver_solicitud.php?id='
                            .
                            $idSolicitud

                        );
                    }


                    // =========================================
                    // NOTIFICAR DOCENTE
                    // =========================================

                    if (
                        (int)$solicitud[
                            'id_usuario'
                        ]
                        !==
                        (int)usuarioId()
                    ) {

                        crearNotificacion(

                            $conexion,

                            (int)$solicitud[
                                'id_usuario'
                            ],

                            'Solicitud asignada',

                            'La solicitud '
                            .
                            numeroTicket(
                                $idSolicitud
                            )
                            .
                            ' fue asignada a '
                            .
                            $nombreTecnico
                            .
                            '.',

                            'ver_solicitud.php?id='
                            .
                            $idSolicitud

                        );
                    }


                    // =========================================
                    // COMMIT
                    // =========================================

                    $conexion->commit();


                    // =========================================
                    // AVISAR AL TÉCNICO ASIGNADO
                    // (correo con adjunto + WhatsApp si tiene
                    // apikey cargada). No interrumpe el flujo
                    // si falla.
                    // =========================================

                    try {

                        notificarAsignacion(
                            $conexion,
                            $solicitud,
                            $idSolicitud,
                            $tecnicoSeleccionado
                        );

                        notificarAsignacionWhatsapp(
                            $idSolicitud,
                            $solicitud['titulo'] ?? '',
                            $solicitud['descripcion'] ?? '',
                            $tecnicoSeleccionado['telefono'] ?? null,
                            $tecnicoSeleccionado['whatsapp_apikey'] ?? null
                        );

                    } catch (Throwable $e) {

                        error_log(
                            'Error avisando asignación: '
                            . $e->getMessage()
                        );
                    }


                    flash(
                        'success',
                        $asignacionActual
                            ? 'La solicitud fue reasignada correctamente.'
                            : 'El técnico fue asignado correctamente.'
                    );


                    header(
                        'Location: '
                        . url(
                            'admin/asignar.php?id='
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
                        'Error asignando solicitud: '
                        .
                        $e->getMessage()
                    );


                    $error =
                        'No se pudo realizar la asignación.';
                }
            }
        }
    }
}


// ============================================================
// RECARGAR ASIGNACIÓN ACTUAL
// ============================================================

$stmtActual->execute([
    $idSolicitud
]);


$asignacionActual =
    $stmtActual->fetch(
        PDO::FETCH_ASSOC
    );


// ============================================================
// HISTORIAL DE ASIGNACIONES
// ============================================================

$stmtHistorialAsignaciones =
    $conexion->prepare("
        SELECT

            sa.id_asignacion,
            sa.observaciones,
            sa.activo,
            sa.fecha_asignacion,
            sa.fecha_fin,

            CONCAT(
                t.nombre,
                ' ',
                t.apellido
            ) AS tecnico,

            CONCAT(
                a.nombre,
                ' ',
                a.apellido
            ) AS asignado_por

        FROM solicitudes_asignaciones sa

        INNER JOIN usuarios t
            ON sa.id_tecnico =
               t.id_usuario

        LEFT JOIN usuarios a
            ON sa.asignado_por =
               a.id_usuario

        WHERE
            sa.id_solicitud = ?

        ORDER BY
            sa.fecha_asignacion DESC
    ");


$stmtHistorialAsignaciones
    ->execute([
        $idSolicitud
    ]);


$historialAsignaciones =
    $stmtHistorialAsignaciones
        ->fetchAll(
            PDO::FETCH_ASSOC
        );


// ============================================================
// HEADER
// ============================================================

require_once __DIR__
    . '/../includes/header.php';

?>


<style>

.asignar-wrapper {

    max-width: 1250px;

    margin: 0 auto;

    padding:
        5px 12px
        45px;

}


/* ============================================================
   HERO
============================================================ */

.asignar-hero {

    position: relative;

    overflow: hidden;

    padding: 28px;

    margin-bottom: 24px;

    border-radius: 21px;

    color: #FFFFFF;

    background:
        linear-gradient(
            135deg,
            #760000,
            #B12626
        );

    box-shadow:
        0 8px 27px
        rgba(118,0,0,.16);

}


.asignar-hero::after {

    content: "";

    position: absolute;

    width: 260px;

    height: 260px;

    right: -100px;

    top: -120px;

    border-radius: 50%;

    background:
        rgba(255,255,255,.06);

}


.hero-content {

    position: relative;

    z-index: 2;

}


.ticket-numero {

    color:
        rgba(255,255,255,.70);

    font-size: 12px;

    font-weight: 700;

}


.asignar-hero h1 {

    margin:
        5px 0 8px;

    font-size: 27px;

    font-weight: 800;

}


.asignar-hero p {

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

    padding:
        10px 17px;

    border-radius: 10px;

    color: #760000;

    background: #FFFFFF;

    text-decoration: none;

    font-weight: 700;

}


.btn-volver:hover {

    color: #B12626;

    background: #F4F4F4;

}


/* ============================================================
   CARD
============================================================ */

.asignar-card {

    overflow: hidden;

    border:
        1px solid #ECECEC;

    border-radius: 18px;

    background: #FFFFFF;

    box-shadow:
        0 5px 19px
        rgba(0,0,0,.05);

}


.asignar-card-header {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 12px;

    padding:
        18px 20px;

    border-bottom:
        1px solid #EEEEEE;

}


.asignar-card-header h5 {

    margin: 0;

    color: #760000;

    font-size: 16px;

    font-weight: 800;

}


.asignar-card-body {

    padding: 21px;

}


/* ============================================================
   INFORMACIÓN SOLICITUD
============================================================ */

.info-grid {

    display: grid;

    grid-template-columns:
        repeat(2,1fr);

    gap: 18px;

}


.info-label {

    color: #969696;

    font-size: 10px;

    font-weight: 700;

    text-transform: uppercase;

}


.info-value {

    margin-top: 4px;

    color: #3D3D3D;

    font-size: 13px;

    font-weight: 700;

}


.descripcion {

    margin-top: 20px;

    padding: 15px;

    border-radius: 11px;

    color: #555555;

    background: #F8F8F8;

    font-size: 13px;

    line-height: 1.6;

}


/* ============================================================
   RESPONSABLE ACTUAL
============================================================ */

.responsable-actual {

    display: flex;

    align-items: center;

    gap: 13px;

    padding: 15px;

    margin-bottom: 20px;

    border-radius: 13px;

    border:
        1px solid #DCECDF;

    background: #F3FBF5;

}


.responsable-avatar {

    min-width: 48px;

    width: 48px;

    height: 48px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 50%;

    background: #198754;

    color: #FFFFFF;

    font-size: 20px;

}


.responsable-actual strong {

    display: block;

    color: #2F4A38;

}


.responsable-actual small {

    color: #718277;

}


.sin-responsable {

    padding: 15px;

    margin-bottom: 20px;

    border:
        1px solid #EFE0C2;

    border-radius: 12px;

    background: #FFF8E8;

    color: #735D23;

    font-size: 13px;

}


/* ============================================================
   FORM
============================================================ */

.form-label {

    color: #4D4D4D;

    font-size: 12px;

    font-weight: 700;

}


.form-select,
.form-control {

    min-height: 46px;

    border-radius: 9px;

}


.form-select:focus,
.form-control:focus {

    border-color: #B12626;

    box-shadow:
        0 0 0 .2rem
        rgba(177,38,38,.08);

}


textarea.form-control {

    min-height: 100px;

    resize: vertical;

}


.btn-asignar {

    width: 100%;

    min-height: 46px;

    border: 0;

    border-radius: 9px;

    color: #FFFFFF;

    background:
        linear-gradient(
            135deg,
            #760000,
            #B12626
        );

    font-weight: 700;

}


.btn-asignar:hover {

    background: #760000;

    color: #FFFFFF;

}


.btn-quitar {

    width: 100%;

    min-height: 43px;

    margin-top: 9px;

    border:
        1px solid #E1BEBE;

    border-radius: 9px;

    color: #B12626;

    background: #FFF6F6;

    font-weight: 700;

}


.btn-quitar:hover {

    background: #B12626;

    color: #FFFFFF;

}


/* ============================================================
   TÉCNICOS
============================================================ */

.tecnico-item {

    display: flex;

    align-items: center;

    gap: 12px;

    padding:
        12px 0;

    border-bottom:
        1px solid #EEEEEE;

}


.tecnico-item:last-child {

    border-bottom: 0;

}


.tecnico-avatar {

    min-width: 38px;

    width: 38px;

    height: 38px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 50%;

    background: #F2E5E5;

    color: #760000;

}


.tecnico-info strong {

    display: block;

    color: #444444;

    font-size: 12px;

}


.tecnico-info small {

    color: #888888;

    font-size: 10px;

}


/* ============================================================
   HISTORIAL
============================================================ */

.historial-item {

    position: relative;

    padding:
        0 0 20px
        24px;

    border-left:
        2px solid #EEEEEE;

}


.historial-item:last-child {

    padding-bottom: 0;

}


.historial-item::before {

    content: "";

    position: absolute;

    left: -6px;

    top: 2px;

    width: 10px;

    height: 10px;

    border-radius: 50%;

    background: #B12626;

}


.historial-tecnico {

    color: #333333;

    font-size: 12px;

    font-weight: 800;

}


.historial-meta {

    margin-top: 3px;

    color: #8A8A8A;

    font-size: 10px;

}


.historial-observacion {

    margin-top: 6px;

    padding: 8px;

    border-radius: 7px;

    color: #666666;

    background: #F8F8F8;

    font-size: 10px;

}


/* ============================================================
   ESTADO
============================================================ */

.estado-activo {

    display: inline-block;

    padding:
        4px 7px;

    border-radius: 20px;

    background: #E1F4E8;

    color: #198754;

    font-size: 9px;

    font-weight: 700;

}


.estado-finalizado {

    display: inline-block;

    padding:
        4px 7px;

    border-radius: 20px;

    background: #EEEEEE;

    color: #777777;

    font-size: 9px;

    font-weight: 700;

}


/* ============================================================
   RESPONSIVE
============================================================ */

@media
(max-width: 767px) {

    .asignar-hero {

        padding: 22px 20px;

    }


    .asignar-hero h1 {

        font-size: 22px;

    }


    .hero-actions {

        margin-top: 18px;

    }


    .btn-volver {

        width: 100%;

        justify-content: center;

    }


    .info-grid {

        grid-template-columns: 1fr;

    }

}

</style>


<div class="asignar-wrapper">


    <!-- =====================================================
         HERO
    ====================================================== -->

    <section class="asignar-hero">

        <div class="row align-items-center">

            <div class="col-lg-8">

                <div class="hero-content">

                    <div class="ticket-numero">

                        <?= e(
                            numeroTicket(
                                $idSolicitud
                            )
                        ) ?>

                    </div>


                    <h1>

                        <i class="bi bi-person-check me-1"></i>

                        Asignar responsable

                    </h1>


                    <p>

                        <?= e(
                            $solicitud['titulo']
                        ) ?>

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
                        'admin/solicitudes.php'
                    ) ?>"
                    class="btn-volver"
                >

                    <i class="bi bi-arrow-left"></i>

                    Volver a solicitudes

                </a>

            </div>

        </div>

    </section>


    <!-- =====================================================
         ERROR
    ====================================================== -->

    <?php if ($error !== ''): ?>

        <div class="alert alert-danger">

            <i class="bi bi-exclamation-triangle me-1"></i>

            <?= e($error) ?>

        </div>

    <?php endif; ?>


    <?php

    $flash =
        obtenerFlash();

    ?>


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


    <div class="row g-4">


        <!-- =================================================
             COLUMNA IZQUIERDA
        ================================================== -->

        <div class="col-xl-7">


            <!-- =============================================
                 INFORMACIÓN
            ============================================== -->

            <div class="asignar-card mb-4">

                <div class="asignar-card-header">

                    <h5>

                        <i class="bi bi-ticket-detailed me-2"></i>

                        Datos de la solicitud

                    </h5>


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

                </div>


                <div class="asignar-card-body">

                    <div class="info-grid">


                        <div>

                            <div class="info-label">
                                Solicitante
                            </div>

                            <div class="info-value">

                                <?= e(
                                    trim(
                                        $solicitud[
                                            'nombre'
                                        ]
                                        .
                                        ' '
                                        .
                                        $solicitud[
                                            'apellido'
                                        ]
                                    )
                                ) ?>

                            </div>

                        </div>


                        <div>

                            <div class="info-label">
                                Tipo
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


                        <div>

                            <div class="info-label">
                                Sector
                            </div>

                            <div class="info-value">

                                <?= e(
                                    $solicitud[
                                        'sector'
                                    ]
                                    ?? 'Sin sector'
                                ) ?>

                            </div>

                        </div>


                        <div>

                            <div class="info-label">
                                Prioridad
                            </div>

                            <div class="info-value">

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


                        <div>

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


                        <div>

                            <div class="info-label">
                                Fecha
                            </div>

                            <div class="info-value">

                                <?= e(
                                    fechaArgentina(
                                        $solicitud[
                                            'fecha_creacion'
                                        ]
                                    )
                                ) ?>

                            </div>

                        </div>


                    </div>


                    <div class="descripcion">

                        <?= nl2br(
                            e(
                                $solicitud[
                                    'descripcion'
                                ]
                            )
                        ) ?>

                    </div>


                    <div class="mt-3">

                        <a
                            href="<?= url(
                                'ver_solicitud.php?id='
                                .
                                $idSolicitud
                            ) ?>"
                            class="btn btn-sm btn-outline-secondary"
                        >

                            <i class="bi bi-eye me-1"></i>

                            Ver ticket completo

                        </a>

                    </div>

                </div>

            </div>


            <!-- =============================================
                 ASIGNAR
            ============================================== -->

            <div class="asignar-card mt-4">

                <div class="asignar-card-header">

                    <h5>

                        <i class="bi bi-person-gear me-2"></i>

                        Responsable técnico

                    </h5>

                </div>


                <div class="asignar-card-body">


                    <?php if (
                        $asignacionActual
                    ): ?>

                        <div class="responsable-actual">

                            <div class="responsable-avatar">

                                <i class="bi bi-person-check"></i>

                            </div>


                            <div>

                                <strong>

                                    <?= e(
                                        $asignacionActual[
                                            'tecnico'
                                        ]
                                    ) ?>

                                </strong>


                                <small>

                                    <?= e(
                                        $asignacionActual[
                                            'correo'
                                        ]
                                    ) ?>

                                </small>


                                <small class="d-block">

                                    Asignado:
                                    <?= e(
                                        fechaArgentina(
                                            $asignacionActual[
                                                'fecha_asignacion'
                                            ]
                                        )
                                    ) ?>

                                </small>

                            </div>

                        </div>


                    <?php else: ?>

                        <div class="sin-responsable">

                            <i class="bi bi-exclamation-circle me-1"></i>

                            Esta solicitud todavía
                            no tiene un responsable asignado.

                        </div>

                    <?php endif; ?>


                    <form
                        method="POST"
                        action="<?= url(
                            'admin/asignar.php?id='
                            .
                            $idSolicitud
                        ) ?>"
                    >

                        <?= csrfInput() ?>


                        <input
                            type="hidden"
                            name="accion"
                            value="asignar"
                        >


                        <input
                            type="hidden"
                            name="id_solicitud"
                            value="<?= $idSolicitud ?>"
                        >


                        <div class="mb-3">

                            <label
                                for="id_tecnico"
                                class="form-label"
                            >

                                <?= $asignacionActual
                                    ? 'Reasignar a'
                                    : 'Seleccionar responsable'
                                ?>

                            </label>


                            <select
                                name="id_tecnico"
                                id="id_tecnico"
                                class="form-select"
                                required
                            >

                                <option value="">
                                    Seleccionar técnico...
                                </option>


                                <?php foreach (
                                    $tecnicos
                                    as $tecnico
                                ): ?>

                                    <option
                                        value="<?= (int)$tecnico[
                                            'id_usuario'
                                        ] ?>"
                                    >

                                        <?= e(
                                            trim(
                                                $tecnico[
                                                    'nombre'
                                                ]
                                                .
                                                ' '
                                                .
                                                $tecnico[
                                                    'apellido'
                                                ]
                                            )
                                        ) ?>

                                        <?php if (
                                            !empty(
                                                $tecnico[
                                                    'rol'
                                                ]
                                            )
                                        ): ?>

                                            -
                                            <?= e(
                                                $tecnico[
                                                    'rol'
                                                ]
                                            ) ?>

                                        <?php endif; ?>

                                    </option>

                                <?php endforeach; ?>


                            </select>

                        </div>


                        <div class="mb-3">

                            <label
                                for="observaciones"
                                class="form-label"
                            >
                                Observaciones de asignación
                            </label>


                            <textarea
                                name="observaciones"
                                id="observaciones"
                                class="form-control"
                                maxlength="1000"
                                placeholder="Ej.: Revisar durante el horario de mantenimiento del martes."
                            ></textarea>

                        </div>


                        <button
                            type="submit"
                            class="btn btn-asignar"
                        >

                            <i class="bi bi-person-check me-1"></i>

                            <?= $asignacionActual
                                ? 'Reasignar responsable'
                                : 'Asignar responsable'
                            ?>

                        </button>

                    </form>


                    <?php if (
                        $asignacionActual
                    ): ?>

                        <form
                            method="POST"
                            action="<?= url(
                                'admin/asignar.php?id='
                                .
                                $idSolicitud
                            ) ?>"
                            id="formQuitar"
                        >

                            <?= csrfInput() ?>

                            <input
                                type="hidden"
                                name="accion"
                                value="quitar"
                            >

                            <input
                                type="hidden"
                                name="id_solicitud"
                                value="<?= $idSolicitud ?>"
                            >


                            <button
                                type="submit"
                                class="btn btn-quitar"
                            >

                                <i class="bi bi-person-dash me-1"></i>

                                Quitar asignación

                            </button>

                        </form>

                    <?php endif; ?>


                </div>

            </div>


        </div>


        <!-- =================================================
             LATERAL
        ================================================== -->

        <div class="col-xl-5">


            <!-- =============================================
                 TÉCNICOS DISPONIBLES
            ============================================== -->

            <div class="asignar-card mb-4">

                <div class="asignar-card-header">

                    <h5>

                        <i class="bi bi-people me-2"></i>

                        Personal disponible

                    </h5>

                    <span class="badge bg-secondary">

                        <?= count($tecnicos) ?>

                    </span>

                </div>


                <div class="asignar-card-body">


                    <?php if (
                        empty(
                            $tecnicos
                        )
                    ): ?>

                        <div class="text-center text-muted py-4">

                            <i class="bi bi-person-x fs-2 d-block mb-2"></i>

                            No existen técnicos activos.

                        </div>


                    <?php else: ?>


                        <?php foreach (
                            $tecnicos
                            as $tecnico
                        ): ?>

                            <div class="tecnico-item">

                                <div class="tecnico-avatar">

                                    <i class="bi bi-person"></i>

                                </div>


                                <div class="tecnico-info">

                                    <strong>

                                        <?= e(
                                            trim(
                                                $tecnico[
                                                    'nombre'
                                                ]
                                                .
                                                ' '
                                                .
                                                $tecnico[
                                                    'apellido'
                                                ]
                                            )
                                        ) ?>

                                    </strong>


                                    <small>

                                        <?= e(
                                            $tecnico[
                                                'correo'
                                            ]
                                            ?? ''
                                        ) ?>

                                    </small>


                                    <small class="d-block">

                                        <?= e(
                                            $tecnico[
                                                'rol'
                                            ]
                                            ?? 'Técnico'
                                        ) ?>

                                    </small>

                                </div>

                            </div>

                        <?php endforeach; ?>


                    <?php endif; ?>


                </div>

            </div>


            <!-- =============================================
                 HISTORIAL
            ============================================== -->

            <div class="asignar-card mt-4">

                <div class="asignar-card-header">

                    <h5>

                        <i class="bi bi-clock-history me-2"></i>

                        Historial de asignaciones

                    </h5>

                </div>


                <div class="asignar-card-body">


                    <?php if (
                        empty(
                            $historialAsignaciones
                        )
                    ): ?>

                        <div class="text-center text-muted py-4">

                            <i class="bi bi-clock fs-2 d-block mb-2"></i>

                            No existen asignaciones anteriores.

                        </div>


                    <?php else: ?>


                        <?php foreach (
                            $historialAsignaciones
                            as $asignacion
                        ): ?>

                            <div class="historial-item">

                                <div class="historial-tecnico">

                                    <?= e(
                                        $asignacion[
                                            'tecnico'
                                        ]
                                    ) ?>

                                </div>


                                <div class="historial-meta">

                                    Asignado:

                                    <?= e(
                                        fechaArgentina(
                                            $asignacion[
                                                'fecha_asignacion'
                                            ]
                                        )
                                    ) ?>

                                </div>


                                <?php if (
                                    !empty(
                                        $asignacion[
                                            'asignado_por'
                                        ]
                                    )
                                ): ?>

                                    <div class="historial-meta">

                                        Por:

                                        <?= e(
                                            $asignacion[
                                                'asignado_por'
                                            ]
                                        ) ?>

                                    </div>

                                <?php endif; ?>


                                <?php if (
                                    !empty(
                                        $asignacion[
                                            'fecha_fin'
                                        ]
                                    )
                                ): ?>

                                    <div class="historial-meta">

                                        Finalizó:

                                        <?= e(
                                            fechaArgentina(
                                                $asignacion[
                                                    'fecha_fin'
                                                ]
                                            )
                                        ) ?>

                                    </div>

                                <?php endif; ?>


                                <div class="mt-1">

                                    <?php if (
                                        (int)$asignacion[
                                            'activo'
                                        ] === 1
                                    ): ?>

                                        <span class="estado-activo">

                                            <i class="bi bi-check-circle me-1"></i>

                                            Responsable actual

                                        </span>

                                    <?php else: ?>

                                        <span class="estado-finalizado">

                                            Asignación finalizada

                                        </span>

                                    <?php endif; ?>

                                </div>


                                <?php if (
                                    !empty(
                                        $asignacion[
                                            'observaciones'
                                        ]
                                    )
                                ): ?>

                                    <div class="historial-observacion">

                                        <?= e(
                                            $asignacion[
                                                'observaciones'
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


    </div>


</div>


<script>

// ============================================================
// CONFIRMAR QUITAR ASIGNACIÓN
// ============================================================

const formQuitar =
    document.getElementById(
        'formQuitar'
    );


if (formQuitar) {

    formQuitar.addEventListener(
        'submit',
        function(evento) {

            const confirmar =
                confirm(
                    '¿Seguro que querés quitar el responsable de esta solicitud?'
                );


            if (!confirmar) {

                evento.preventDefault();

            }

        }
    );

}

</script>


<?php

require_once __DIR__
    . '/../includes/footer.php';

?>