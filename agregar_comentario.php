<?php
// ============================================================
// COLEGIO SAN JOSÉ
// Sistema de Gestión Técnica
// Archivo: /tecnica/agregar_comentario.php
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/funciones.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/mailer.php';


// ============================================================
// REQUERIR LOGIN
// ============================================================

requerirLogin();


// ============================================================
// SOLO POST
// ============================================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header(
        'Location: ' . url('dashboard.php')
    );

    exit;
}


// ============================================================
// OBTENER DATOS
// ============================================================

$idSolicitud =
    (int)(
        $_POST['id_solicitud']
        ?? 0
    );


$comentario =
    limpiarTexto(
        $_POST['comentario']
        ?? ''
    );


$csrf =
    $_POST['csrf_token']
    ?? '';


// ============================================================
// VALIDAR ID
// ============================================================

if ($idSolicitud <= 0) {

    flash(
        'error',
        'La solicitud indicada no es válida.'
    );

    header(
        'Location: ' . url('dashboard.php')
    );

    exit;
}


// ============================================================
// VERIFICAR ACCESO A LA SOLICITUD
//
// Docente:
// solamente puede comentar en sus propias solicitudes.
//
// Técnico / Administrador:
// puede comentar en cualquier solicitud.
// ============================================================

if (
    !puedeVerSolicitud(
        $conexion,
        $idSolicitud
    )
) {

    accesoDenegado();
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
        'Location: ' . url('dashboard.php')
    );

    exit;
}


// ============================================================
// VALIDAR CSRF
// ============================================================

if (!validarCsrf($csrf)) {

    flash(
        'error',
        'La sesión del formulario expiró. Intentá nuevamente.'
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
// NO PERMITIR COMENTARIOS EN TICKETS CERRADOS
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
        'No se pueden agregar comentarios a una solicitud '
        . strtolower($solicitud['estado'])
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
// VALIDAR COMENTARIO
// ============================================================

if ($comentario === '') {

    flash(
        'error',
        'Escribí un comentario antes de enviarlo.'
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


if (
    mb_strlen(
        $comentario
    ) < 2
) {

    flash(
        'error',
        'El comentario es demasiado corto.'
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


if (
    mb_strlen(
        $comentario
    ) > 2000
) {

    flash(
        'error',
        'El comentario no puede superar los 2000 caracteres.'
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
// GUARDAR COMENTARIO
// ============================================================

try {

    $conexion->beginTransaction();


    $stmt =
        $conexion->prepare("
            INSERT INTO comentarios
            (
                id_solicitud,
                id_usuario,
                comentario
            )
            VALUES
            (
                ?,
                ?,
                ?
            )
        ");


    $stmt->execute([

        $idSolicitud,

        (int)usuarioId(),

        $comentario

    ]);


    // ========================================================
    // REGISTRAR MOVIMIENTO EN HISTORIAL
    // ========================================================

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
                NULL,
                NULL,
                ?
            )
        ");


    $stmtHistorial->execute([

        $idSolicitud,

        (int)usuarioId(),

        'Se agregó un comentario a la solicitud.'

    ]);


    // ========================================================
    // NOTIFICACIONES
    // ========================================================

    $idAutor =
        (int)usuarioId();


    // ========================================================
    // SI COMENTA UN TÉCNICO / ADMINISTRADOR
    // NOTIFICAR AL DOCENTE
    // ========================================================

    if (
        esPersonalTecnico()
        &&
        (int)$solicitud['id_usuario']
        !== $idAutor
    ) {

        crearNotificacion(

            $conexion,

            (int)$solicitud['id_usuario'],

            'Nuevo comentario en '
            . numeroTicket(
                $idSolicitud
            ),

            usuarioNombre()
            . ' agregó un comentario en tu solicitud: '
            . $solicitud['titulo'],

            'ver_solicitud.php?id='
            . $idSolicitud

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
    }


    // ========================================================
    // SI COMENTA EL DOCENTE
    // NOTIFICAR AL TÉCNICO ASIGNADO
    // ========================================================

    if (esDocente()) {

        $tecnico =
            obtenerTecnicoAsignado(
                $conexion,
                $idSolicitud
            );


        if (
            $tecnico
            &&
            isset(
                $tecnico['id_tecnico']
            )
        ) {

            $idTecnico =
                (int)$tecnico[
                    'id_tecnico'
                ];


            if (
                $idTecnico !==
                $idAutor
            ) {

                crearNotificacion(

                    $conexion,

                    $idTecnico,

                    'Nuevo comentario en '
                    . numeroTicket(
                        $idSolicitud
                    ),

                    usuarioNombre()
                    . ' agregó un comentario en la solicitud: '
                    . $solicitud['titulo'],

                    'ver_solicitud.php?id='
                    . $idSolicitud

                );


                try {

                    notificarComentario(
                        $idSolicitud,
                        numeroTicket($idSolicitud),
                        (string)$solicitud['titulo'],
                        (string)($tecnico['correo'] ?? ''),
                        trim(
                            ($tecnico['nombre'] ?? '')
                            . ' ' .
                            ($tecnico['apellido'] ?? '')
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
    }


    // ========================================================
    // CONFIRMAR TRANSACCIÓN
    // ========================================================

    $conexion->commit();


    flash(
        'success',
        'El comentario fue agregado correctamente.'
    );


} catch (Throwable $e) {

    if (
        $conexion->inTransaction()
    ) {

        $conexion->rollBack();
    }


    error_log(
        'Error agregar comentario: '
        . $e->getMessage()
    );


    flash(
        'error',
        'No se pudo guardar el comentario. Intentá nuevamente.'
    );
}


// ============================================================
// VOLVER AL TICKET
// ============================================================

header(
    'Location: '
    . url(
        'ver_solicitud.php?id='
        . $idSolicitud
    )
);

exit;