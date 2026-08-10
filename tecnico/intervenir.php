<?php
// ============================================================
// COLEGIO SAN JOSÉ
// Sistema de Gestión Técnica
// Archivo: /tecnica/tecnico/intervenir.php
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
// USUARIO ACTIVO
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
// DATOS USUARIO
// ============================================================

$idTecnico =
    (int)usuarioId();

$rolActual =
    $_SESSION['usuario_rol']
    ?? '';


// ============================================================
// ID DE SOLICITUD
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
        . url('tecnico/dashboard.php')
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
            s.id_sector,
            s.id_categoria,

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

            u.correo AS correo_solicitante,

            sec.nombre AS sector,

            cat.nombre AS categoria

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

        WHERE s.id_solicitud = ?

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
        . url('tecnico/dashboard.php')
    );

    exit;
}


// ============================================================
// VERIFICAR ASIGNACIÓN
//
// Administrador puede intervenir cualquier solicitud.
// Técnico solamente una solicitud actualmente asignada.
// ============================================================

$esAdministrador =
    $rolActual === 'Administrador';


if (!$esAdministrador) {

    $stmtAsignacion =
        $conexion->prepare("
            SELECT COUNT(*)

            FROM solicitudes_asignaciones

            WHERE
                id_solicitud = ?

            AND
                id_tecnico = ?

            AND
                activo = 1
        ");


    $stmtAsignacion->execute([
        $idSolicitud,
        $idTecnico
    ]);


    $estaAsignada =
        (int)$stmtAsignacion
            ->fetchColumn()
        > 0;


    if (!$estaAsignada) {

        flash(
            'error',
            'Esta solicitud no está asignada a tu usuario.'
        );

        header(
            'Location: '
            . url('tecnico/dashboard.php')
        );

        exit;
    }
}


// ============================================================
// ESTADOS EN LOS QUE NO SE PUEDE INTERVENIR
// ============================================================

$solicitudBloqueada =
    in_array(
        $solicitud['estado'],
        [
            'Cerrada',
            'Cancelada'
        ],
        true
    );


// ============================================================
// IMÁGENES DEL PEDIDO ORIGINAL
// ============================================================

$stmtImagenesSolicitud =
    $conexion->prepare("
        SELECT

            id_imagen,
            archivo,
            descripcion,
            fecha_creacion

        FROM solicitud_imagenes

        WHERE id_solicitud = ?

        ORDER BY
            fecha_creacion ASC
    ");


$stmtImagenesSolicitud->execute([
    $idSolicitud
]);


$imagenesSolicitud =
    $stmtImagenesSolicitud->fetchAll(
        PDO::FETCH_ASSOC
    );


// ============================================================
// INTERVENCIONES ANTERIORES
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

            ) AS cantidad_imagenes

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


// ============================================================
// ÚLTIMA INTERVENCIÓN
// ============================================================

$ultimaIntervencion =
    $intervenciones[0]
    ?? null;


// ============================================================
// FORMULARIO
// ============================================================

$error = '';

$diagnostico = '';

$trabajoRealizado = '';

$materiales = '';

$observaciones = '';

$resultado = 'proceso';

$motivoPendiente = '';

$tipoPendiente = '';


// ============================================================
// TIPOS DE PENDIENTE PERMITIDOS
// ============================================================

$tiposPendientePermitidos = [
    'Falta de repuesto',
    'Horas insuficientes',
    'Reprogramacion',
    'Otro'
];


// ============================================================
// PROCESAR INTERVENCIÓN
// ============================================================

if (
    $_SERVER['REQUEST_METHOD']
    === 'POST'
    &&
    !$solicitudBloqueada
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
            'La sesión del formulario expiró. '
            . 'Actualizá la página e intentá nuevamente.';

    } else {

        // ====================================================
        // DATOS
        // ====================================================

        $diagnostico =
            trim(
                $_POST['diagnostico']
                ?? ''
            );


        $trabajoRealizado =
            trim(
                $_POST['trabajo_realizado']
                ?? ''
            );


        $materiales =
            trim(
                $_POST['materiales']
                ?? ''
            );


        $observaciones =
            trim(
                $_POST['observaciones']
                ?? ''
            );


        $resultado =
            trim(
                $_POST['resultado']
                ?? 'proceso'
            );


        $motivoPendiente =
            trim(
                $_POST['motivo_pendiente']
                ?? ''
            );


        $tipoPendiente =
            trim(
                $_POST['tipo_pendiente']
                ?? ''
            );


        // ====================================================
        // RESULTADOS VÁLIDOS
        // ====================================================

        $resultadosPermitidos = [
            'proceso',
            'pendiente',
            'resuelto'
        ];


        // ====================================================
        // VALIDACIONES
        // ====================================================

        if ($diagnostico === '') {

            $error =
                'Ingresá el diagnóstico realizado.';

        } elseif (
            mb_strlen($diagnostico)
            > 5000
        ) {

            $error =
                'El diagnóstico es demasiado extenso.';

        } elseif (
            $trabajoRealizado === ''
        ) {

            $error =
                'Indicá el trabajo realizado durante la intervención.';

        } elseif (
            mb_strlen($trabajoRealizado)
            > 5000
        ) {

            $error =
                'El detalle del trabajo realizado es demasiado extenso.';

        } elseif (
            mb_strlen($materiales)
            > 3000
        ) {

            $error =
                'El detalle de materiales es demasiado extenso.';

        } elseif (
            mb_strlen($observaciones)
            > 3000
        ) {

            $error =
                'Las observaciones son demasiado extensas.';

        } elseif (
            !in_array(
                $resultado,
                $resultadosPermitidos,
                true
            )
        ) {

            $error =
                'Seleccioná un resultado válido.';

        } elseif (
            $resultado === 'pendiente'
            &&
            $motivoPendiente === ''
        ) {

            $error =
                'Indicá por qué la intervención queda pendiente.';

        } elseif (
            mb_strlen($motivoPendiente)
            > 3000
        ) {

            $error =
                'El motivo pendiente es demasiado extenso.';

        } elseif (
            $resultado === 'pendiente'
            &&
            !in_array(
                $tipoPendiente,
                $tiposPendientePermitidos,
                true
            )
        ) {

            $error =
                'Seleccioná el tipo de pendiente.';
        }


        // ====================================================
        // VALIDAR IMÁGENES
        // ====================================================

        $imagenesValidas = [];

        $tiposPermitidosImagen = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp'
        ];


        $maxImagenes = 6;

        $maxTamano =
            5 * 1024 * 1024;


        if (
            $error === ''
            &&
            isset($_FILES['imagenes'])
            &&
            is_array(
                $_FILES['imagenes']['name']
            )
        ) {

            $cantidadArchivos =
                count(
                    $_FILES['imagenes']['name']
                );


            if (
                $cantidadArchivos
                > $maxImagenes
            ) {

                $error =
                    'Podés adjuntar como máximo '
                    . $maxImagenes
                    . ' imágenes por intervención.';

            } else {

                $finfo =
                    new finfo(
                        FILEINFO_MIME_TYPE
                    );


                for (
                    $i = 0;
                    $i < $cantidadArchivos;
                    $i++
                ) {

                    $errorArchivo =
                        $_FILES['imagenes']['error'][$i]
                        ?? UPLOAD_ERR_NO_FILE;


                    // Sin archivo en esa posición
                    if (
                        $errorArchivo
                        === UPLOAD_ERR_NO_FILE
                    ) {

                        continue;
                    }


                    if (
                        $errorArchivo
                        !== UPLOAD_ERR_OK
                    ) {

                        $error =
                            'Ocurrió un error al cargar una de las imágenes.';

                        break;
                    }


                    $tmp =
                        $_FILES['imagenes']['tmp_name'][$i];


                    $tamano =
                        (int)(
                            $_FILES['imagenes']['size'][$i]
                            ?? 0
                        );


                    if (
                        $tamano <= 0
                        ||
                        $tamano > $maxTamano
                    ) {

                        $error =
                            'Cada imagen debe pesar menos de 5 MB.';

                        break;
                    }


                    $mime =
                        $finfo->file(
                            $tmp
                        );


                    if (
                        !isset(
                            $tiposPermitidosImagen[
                                $mime
                            ]
                        )
                    ) {

                        $error =
                            'Solo se permiten imágenes JPG, PNG o WEBP.';

                        break;
                    }


                    $imagenesValidas[] = [

                        'tmp' =>
                            $tmp,

                        'extension' =>
                            $tiposPermitidosImagen[
                                $mime
                            ]

                    ];
                }
            }
        }


        // ====================================================
        // GUARDAR
        // ====================================================

        if ($error === '') {

            // =================================================
            // DETERMINAR ESTADO FINAL
            // =================================================

            $estadoAnterior =
                $solicitud['estado'];


            $estadoNuevo =
                match ($resultado) {

                    'pendiente'
                        => 'Pendiente',

                    'resuelto'
                        => 'Resuelta',

                    default
                        => 'En proceso'

                };


            $pendiente =
                $resultado === 'pendiente'
                    ? 1
                    : 0;


            try {

                $conexion->beginTransaction();


                // =============================================
                // INSERTAR INTERVENCIÓN
                // =============================================

                $stmtInsertar =
                    $conexion->prepare("
                        INSERT INTO intervenciones
                        (
                            id_solicitud,
                            id_tecnico,

                            diagnostico,
                            trabajo_realizado,
                            materiales,
                            observaciones,

                            pendiente,
                            motivo_pendiente,
                            tipo_pendiente,

                            fecha_intervencion
                        )
                        VALUES
                        (
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            NOW()
                        )
                    ");


                $stmtInsertar->execute([

                    $idSolicitud,

                    $idTecnico,

                    $diagnostico,

                    $trabajoRealizado,

                    $materiales !== ''
                        ? $materiales
                        : null,

                    $observaciones !== ''
                        ? $observaciones
                        : null,

                    $pendiente,

                    $resultado === 'pendiente'
                        ? $motivoPendiente
                        : null,

                    $resultado === 'pendiente'
                        ? $tipoPendiente
                        : null

                ]);


                $idIntervencion =
                    (int)$conexion
                        ->lastInsertId();


                // =============================================
                // ACTUALIZAR SOLICITUD
                // =============================================

                if (
                    $resultado === 'resuelto'
                ) {

                    $stmtEstado =
                        $conexion->prepare("
                            UPDATE solicitudes

                            SET
                                estado = 'Resuelta',
                                motivo_pendiente = NULL,
                                tipo_pendiente = NULL,
                                fecha_actualizacion = NOW(),
                                fecha_resolucion = NOW()

                            WHERE id_solicitud = ?
                        ");


                    $stmtEstado->execute([
                        $idSolicitud
                    ]);


                } elseif (
                    $resultado === 'pendiente'
                ) {

                    $stmtEstado =
                        $conexion->prepare("
                            UPDATE solicitudes

                            SET
                                estado = 'Pendiente',
                                motivo_pendiente = ?,
                                tipo_pendiente = ?,
                                fecha_actualizacion = NOW(),
                                fecha_resolucion = NULL

                            WHERE id_solicitud = ?
                        ");


                    $stmtEstado->execute([
                        $motivoPendiente,
                        $tipoPendiente,
                        $idSolicitud
                    ]);


                } else {

                    // =========================================
                    // CONTINÚA EN PROCESO
                    // =========================================

                    $stmtEstado =
                        $conexion->prepare("
                            UPDATE solicitudes

                            SET
                                estado = 'En proceso',
                                motivo_pendiente = NULL,
                                tipo_pendiente = NULL,
                                fecha_actualizacion = NOW(),
                                fecha_resolucion = NULL

                            WHERE id_solicitud = ?
                        ");


                    $stmtEstado->execute([
                        $idSolicitud
                    ]);
                }


                // =============================================
                // HISTORIAL
                // =============================================

                $descripcionHistorial =
                    match ($resultado) {

                        'resuelto'
                            =>
                            'Se registró una intervención técnica '
                            . 'y la solicitud fue marcada como resuelta.',

                        'pendiente'
                            =>
                            'Se registró una intervención técnica. '
                            . 'La solicitud quedó pendiente: '
                            . $motivoPendiente,

                        default
                            =>
                            'Se registró una intervención técnica '
                            . 'y el trabajo continúa en proceso.'

                    };


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

                    $idTecnico,

                    $estadoAnterior,

                    $estadoNuevo,

                    $descripcionHistorial

                ]);


                // =============================================
                // PREPARAR DIRECTORIO DE IMÁGENES
                // =============================================

                $rutaFisica =
                    dirname(__DIR__)
                    . '/uploads/intervenciones/'
                    . $idSolicitud;


                $rutaBDBase =
                    'uploads/intervenciones/'
                    . $idSolicitud
                    . '/';


                if (
                    !empty(
                        $imagenesValidas
                    )
                    &&
                    !is_dir(
                        $rutaFisica
                    )
                ) {

                    if (
                        !mkdir(
                            $rutaFisica,
                            0775,
                            true
                        )
                        &&
                        !is_dir(
                            $rutaFisica
                        )
                    ) {

                        throw new RuntimeException(
                            'No se pudo crear el directorio de imágenes.'
                        );
                    }
                }


                // =============================================
                // GUARDAR IMÁGENES
                // =============================================

                foreach (
                    $imagenesValidas
                    as $imagen
                ) {

                    $nombreArchivo =
                        'intervencion_'
                        .
                        $idIntervencion
                        .
                        '_'
                        .
                        bin2hex(
                            random_bytes(8)
                        )
                        .
                        '.'
                        .
                        $imagen[
                            'extension'
                        ];


                    $destinoFisico =
                        $rutaFisica
                        . '/'
                        . $nombreArchivo;


                    if (
                        !move_uploaded_file(
                            $imagen['tmp'],
                            $destinoFisico
                        )
                    ) {

                        throw new RuntimeException(
                            'No se pudo guardar una imagen.'
                        );
                    }


                    $rutaBD =
                        $rutaBDBase
                        . $nombreArchivo;


                    $stmtImagen =
                        $conexion->prepare("
                            INSERT INTO intervencion_imagenes
                            (
                                id_intervencion,
                                archivo,
                                descripcion,
                                fecha_creacion
                            )
                            VALUES
                            (
                                ?,
                                ?,
                                NULL,
                                NOW()
                            )
                        ");


                    $stmtImagen->execute([
                        $idIntervencion,
                        $rutaBD
                    ]);
                }


                // =============================================
                // NOTIFICAR DOCENTE
                // =============================================

                $mensajeNotificacion =
                    match ($resultado) {

                        'resuelto'
                            =>
                            'La solicitud '
                            .
                            numeroTicket(
                                $idSolicitud
                            )
                            .
                            ' fue resuelta.',

                        'pendiente'
                            =>
                            'La solicitud '
                            .
                            numeroTicket(
                                $idSolicitud
                            )
                            .
                            ' quedó pendiente. Motivo: '
                            .
                            $motivoPendiente,

                        default
                            =>
                            'Se registró una nueva intervención en '
                            .
                            numeroTicket(
                                $idSolicitud
                            )
                            .
                            '.'

                    };


                if (
                    (int)$solicitud[
                        'id_usuario'
                    ]
                    !==
                    $idTecnico
                ) {

                    crearNotificacion(

                        $conexion,

                        (int)$solicitud[
                            'id_usuario'
                        ],

                        'Actualización de solicitud',

                        $mensajeNotificacion,

                        'ver_solicitud.php?id='
                        .
                        $idSolicitud

                    );
                }


                // =============================================
                // CONFIRMAR TRANSACCIÓN
                // =============================================

                $conexion->commit();


                flash(
                    'success',
                    $resultado === 'resuelto'
                        ? 'Intervención registrada. La solicitud quedó resuelta.'
                        : (
                            $resultado === 'pendiente'
                                ? 'Intervención registrada. La solicitud quedó pendiente.'
                                : 'Intervención registrada correctamente.'
                        )
                );


                header(
                    'Location: '
                    .
                    url(
                        'tecnico/intervenir.php?id='
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
                    'Error intervención técnica: '
                    .
                    $e->getMessage()
                );


                $error =
                    'No se pudo registrar la intervención. '
                    . 'Revisá los datos e intentá nuevamente.';
            }
        }
    }
}


// ============================================================
// RECARGAR ESTADO DESPUÉS DE OPERACIONES
// ============================================================

$stmtSolicitud->execute([
    $idSolicitud
]);


$solicitud =
    $stmtSolicitud->fetch(
        PDO::FETCH_ASSOC
    );


// ============================================================
// RECARGAR INTERVENCIONES
// ============================================================

$stmtIntervenciones->execute([
    $idSolicitud
]);


$intervenciones =
    $stmtIntervenciones->fetchAll(
        PDO::FETCH_ASSOC
    );


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

.intervenir-wrapper {

    max-width: 1450px;

    margin: 0 auto;

    padding:
        5px 12px
        50px;

}


/* ============================================================
   HERO
============================================================ */

.intervenir-hero {

    position: relative;

    overflow: hidden;

    padding: 29px;

    margin-bottom: 23px;

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


.intervenir-hero::after {

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
        rgba(255,255,255,.68);

    font-size: 11px;

    font-weight: 800;

}


.intervenir-hero h1 {

    margin:
        5px 0 7px;

    font-size: 27px;

    font-weight: 800;

}


.intervenir-hero p {

    margin: 0;

    max-width: 750px;

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
   CARD
============================================================ */

.tech-card {

    overflow: hidden;

    background: #FFFFFF;

    border:
        1px solid #ECECEC;

    border-radius: 18px;

    box-shadow:
        0 5px 19px
        rgba(0,0,0,.05);

}


.tech-card-header {

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 10px;

    padding:
        18px 20px;

    border-bottom:
        1px solid #EEEEEE;

}


.tech-card-header h5 {

    margin: 0;

    color: #760000;

    font-size: 16px;

    font-weight: 800;

}


.tech-card-body {

    padding: 21px;

}


/* ============================================================
   DATOS DEL PEDIDO
============================================================ */

.ticket-main-title {

    color: #333333;

    font-size: 20px;

    font-weight: 800;

}


.ticket-description {

    margin-top: 12px;

    padding: 15px;

    border-radius: 11px;

    background: #F8F8F8;

    color: #555555;

    font-size: 13px;

    line-height: 1.65;

}


.info-grid {

    display: grid;

    grid-template-columns:
        repeat(2,1fr);

    gap: 15px;

    margin-top: 19px;

}


.info-item {

    padding: 12px;

    border-radius: 10px;

    background: #FAFAFA;

}


.info-label {

    color: #969696;

    font-size: 9px;

    font-weight: 800;

    text-transform: uppercase;

}


.info-value {

    margin-top: 4px;

    color: #3D3D3D;

    font-size: 12px;

    font-weight: 700;

}


/* ============================================================
   FOTOS ORIGINALES
============================================================ */

.image-grid {

    display: grid;

    grid-template-columns:
        repeat(3,1fr);

    gap: 10px;

}


.image-card {

    position: relative;

    overflow: hidden;

    border-radius: 11px;

    background: #F4F4F4;

    aspect-ratio: 4 / 3;

    cursor: pointer;

}


.image-card img {

    width: 100%;

    height: 100%;

    object-fit: cover;

    transition:
        transform .25s ease;

}


.image-card:hover img {

    transform:
        scale(1.04);

}


/* ============================================================
   FORMULARIO
============================================================ */

.form-label {

    color: #444444;

    font-size: 12px;

    font-weight: 800;

}


.form-control,
.form-select {

    border-radius: 9px;

}


.form-control {

    min-height: 44px;

}


textarea.form-control {

    min-height: 115px;

    resize: vertical;

}


.form-control:focus,
.form-select:focus {

    border-color: #B12626;

    box-shadow:
        0 0 0 .2rem
        rgba(177,38,38,.08);

}


.form-help {

    margin-top: 5px;

    color: #8B8B8B;

    font-size: 10px;

}


/* ============================================================
   RESULTADO
============================================================ */

.resultado-grid {

    display: grid;

    grid-template-columns:
        repeat(3,1fr);

    gap: 9px;

}


.resultado-option {

    position: relative;

}


.resultado-option input {

    position: absolute;

    opacity: 0;

    pointer-events: none;

}


.resultado-option label {

    display: flex;

    flex-direction: column;

    align-items: center;

    justify-content: center;

    gap: 6px;

    min-height: 90px;

    padding: 10px;

    text-align: center;

    cursor: pointer;

    border:
        2px solid #EEEEEE;

    border-radius: 11px;

    color: #666666;

    background: #FFFFFF;

    font-size: 11px;

    font-weight: 700;

    transition: .2s ease;

}


.resultado-option label i {

    font-size: 22px;

}


.resultado-option input:checked + label {

    border-color: #B12626;

    color: #760000;

    background: #FFF6F6;

}


.resultado-option
input[value="resuelto"]:checked
+ label {

    border-color: #198754;

    color: #198754;

    background: #F3FBF5;

}


.resultado-option
input[value="pendiente"]:checked
+ label {

    border-color: #D5A000;

    color: #806000;

    background: #FFF9E7;

}


/* ============================================================
   ARCHIVOS
============================================================ */

.upload-box {

    position: relative;

    padding: 25px 15px;

    text-align: center;

    border:
        2px dashed #D9D9D9;

    border-radius: 12px;

    background: #FAFAFA;

    transition: .2s ease;

}


.upload-box:hover {

    border-color: #B12626;

    background: #FFF8F8;

}


.upload-box i {

    display: block;

    margin-bottom: 7px;

    color: #B12626;

    font-size: 30px;

}


.upload-box strong {

    display: block;

    color: #444444;

    font-size: 12px;

}


.upload-box small {

    display: block;

    margin-top: 4px;

    color: #888888;

    font-size: 10px;

}


.preview-grid {

    display: grid;

    grid-template-columns:
        repeat(3,1fr);

    gap: 8px;

    margin-top: 12px;

}


.preview-image {

    overflow: hidden;

    border-radius: 9px;

    aspect-ratio: 4 / 3;

    background: #EEEEEE;

}


.preview-image img {

    width: 100%;

    height: 100%;

    object-fit: cover;

}


/* ============================================================
   BOTÓN
============================================================ */

.btn-registrar {

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


.btn-registrar:hover {

    color: #FFFFFF;

    background: #760000;

}


/* ============================================================
   INTERVENCIONES
============================================================ */

.intervention-item {

    position: relative;

    padding:
        0 0 24px
        27px;

    border-left:
        2px solid #E9E9E9;

}


.intervention-item:last-child {

    padding-bottom: 0;

}


.intervention-item::before {

    content: "";

    position: absolute;

    width: 11px;

    height: 11px;

    left: -6.5px;

    top: 2px;

    border-radius: 50%;

    background: #B12626;

}


.intervention-title {

    display: flex;

    justify-content: space-between;

    gap: 10px;

}


.intervention-tech {

    color: #333333;

    font-size: 12px;

    font-weight: 800;

}


.intervention-date {

    color: #999999;

    font-size: 9px;

}


.intervention-block {

    margin-top: 10px;

}


.intervention-label {

    margin-bottom: 3px;

    color: #760000;

    font-size: 9px;

    font-weight: 800;

    text-transform: uppercase;

}


.intervention-text {

    color: #626262;

    font-size: 11px;

    line-height: 1.55;

}


.intervention-pending {

    margin-top: 9px;

    padding: 8px 10px;

    border-left:
        3px solid #D8A300;

    border-radius: 7px;

    background: #FFF8DE;

    color: #6D5800;

    font-size: 10px;

}


/* ============================================================
   BLOQUEADO
============================================================ */

.closed-box {

    padding: 18px;

    border-radius: 12px;

    background: #F4F4F4;

    color: #606060;

    text-align: center;

}


.closed-box i {

    display: block;

    margin-bottom: 8px;

    color: #888888;

    font-size: 35px;

}


/* ============================================================
   MODAL IMAGEN
============================================================ */

.image-modal {

    display: none;

    position: fixed;

    inset: 0;

    z-index: 9999;

    align-items: center;

    justify-content: center;

    padding: 25px;

    background:
        rgba(0,0,0,.88);

}


.image-modal.active {

    display: flex;

}


.image-modal img {

    max-width: 95vw;

    max-height: 90vh;

    border-radius: 8px;

}


.image-modal-close {

    position: absolute;

    top: 20px;

    right: 25px;

    border: 0;

    color: #FFFFFF;

    background: transparent;

    font-size: 35px;

}


/* ============================================================
   RESPONSIVE
============================================================ */

@media
(max-width: 767px) {

    .intervenir-hero {

        padding:
            22px 20px;

    }


    .intervenir-hero h1 {

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


    .resultado-grid {

        grid-template-columns: 1fr;

    }


    .image-grid,
    .preview-grid {

        grid-template-columns:
            repeat(2,1fr);

    }

}

</style>


<div class="intervenir-wrapper">


    <!-- =====================================================
         HERO
    ====================================================== -->

    <section class="intervenir-hero">

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

                        <i class="bi bi-tools me-1"></i>

                        Intervención técnica

                    </h1>


                    <p>

                        <?= e(
                            $solicitud[
                                'titulo'
                            ]
                        ) ?>

                    </p>

                </div>

            </div>


            <div class="col-lg-4">

                <div class="hero-actions">


                    <a
                        href="<?= url(
                            'ver_solicitud.php?id='
                            .
                            $idSolicitud
                        ) ?>"
                        class="btn-hero btn-hero-outline"
                    >

                        <i class="bi bi-eye"></i>

                        Ver ticket

                    </a>


                    <a
                        href="<?= url(
                            'tecnico/dashboard.php'
                        ) ?>"
                        class="btn-hero btn-hero-white"
                    >

                        <i class="bi bi-arrow-left"></i>

                        Panel técnico

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

            <?= e($error) ?>

        </div>

    <?php endif; ?>


    <div class="row g-4">


        <!-- =================================================
             IZQUIERDA
        ================================================== -->

        <div class="col-xl-5">


            <!-- =============================================
                 PEDIDO ORIGINAL
            ============================================== -->

            <div class="tech-card mb-4">

                <div class="tech-card-header">

                    <h5>

                        <i class="bi bi-ticket-detailed me-2"></i>

                        Pedido original

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


                <div class="tech-card-body">


                    <div class="ticket-main-title">

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


                        <div class="info-item">

                            <div class="info-label">
                                Fecha del pedido
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

                        <div class="alert alert-warning mt-3 mb-0">

                            <strong>

                                <i class="bi bi-hourglass-split me-1"></i>

                                Pendiente actual:

                            </strong>

                            <?= e(
                                $solicitud[
                                    'motivo_pendiente'
                                ]
                            ) ?>

                        </div>

                    <?php endif; ?>


                </div>

            </div>


            <!-- =============================================
                 FOTOS DEL DOCENTE
            ============================================== -->

            <div class="tech-card mb-4">

                <div class="tech-card-header">

                    <h5>

                        <i class="bi bi-images me-2"></i>

                        Fotos del pedido

                    </h5>


                    <span class="badge bg-secondary">

                        <?= count(
                            $imagenesSolicitud
                        ) ?>

                    </span>

                </div>


                <div class="tech-card-body">


                    <?php if (
                        empty(
                            $imagenesSolicitud
                        )
                    ): ?>

                        <div class="text-center text-muted py-3">

                            <i class="bi bi-image fs-2 d-block mb-2"></i>

                            El docente no adjuntó imágenes.

                        </div>


                    <?php else: ?>

                        <div class="image-grid">


                            <?php foreach (
                                $imagenesSolicitud
                                as $imagen
                            ): ?>

                                <div
                                    class="image-card js-imagen"
                                    data-imagen="<?= e(
                                        url(
                                            $imagen[
                                                'archivo'
                                            ]
                                        )
                                    ) ?>"
                                >

                                    <img
                                        src="<?= e(
                                            url(
                                                $imagen[
                                                    'archivo'
                                                ]
                                            )
                                        ) ?>"
                                        alt="Imagen del pedido"
                                        loading="lazy"
                                    >

                                </div>

                            <?php endforeach; ?>


                        </div>

                    <?php endif; ?>


                </div>

            </div>


            <!-- =============================================
                 HISTORIAL
            ============================================== -->

            <div class="tech-card">

                <div class="tech-card-header">

                    <h5>

                        <i class="bi bi-clock-history me-2"></i>

                        Intervenciones anteriores

                    </h5>


                    <span class="badge bg-secondary">

                        <?= count(
                            $intervenciones
                        ) ?>

                    </span>

                </div>


                <div class="tech-card-body">


                    <?php if (
                        empty(
                            $intervenciones
                        )
                    ): ?>

                        <div class="text-center text-muted py-4">

                            <i class="bi bi-tools fs-2 d-block mb-2"></i>

                            Todavía no se registraron intervenciones.

                        </div>


                    <?php else: ?>


                        <?php foreach (
                            $intervenciones
                            as $intervencion
                        ): ?>

                            <div class="intervention-item">


                                <div class="intervention-title">

                                    <div>

                                        <div class="intervention-tech">

                                            <i class="bi bi-person-gear me-1"></i>

                                            <?= e(
                                                $intervencion[
                                                    'tecnico'
                                                ]
                                            ) ?>

                                        </div>

                                    </div>


                                    <div class="intervention-date">

                                        <?= e(
                                            fechaArgentina(
                                                $intervencion[
                                                    'fecha_intervencion'
                                                ]
                                            )
                                        ) ?>

                                    </div>

                                </div>


                                <div class="intervention-block">

                                    <div class="intervention-label">
                                        Diagnóstico
                                    </div>

                                    <div class="intervention-text">

                                        <?= nl2br(
                                            e(
                                                $intervencion[
                                                    'diagnostico'
                                                ]
                                            )
                                        ) ?>

                                    </div>

                                </div>


                                <div class="intervention-block">

                                    <div class="intervention-label">
                                        Trabajo realizado
                                    </div>

                                    <div class="intervention-text">

                                        <?= nl2br(
                                            e(
                                                $intervencion[
                                                    'trabajo_realizado'
                                                ]
                                            )
                                        ) ?>

                                    </div>

                                </div>


                                <?php if (
                                    !empty(
                                        $intervencion[
                                            'materiales'
                                        ]
                                    )
                                ): ?>

                                    <div class="intervention-block">

                                        <div class="intervention-label">
                                            Materiales / repuestos
                                        </div>

                                        <div class="intervention-text">

                                            <?= nl2br(
                                                e(
                                                    $intervencion[
                                                        'materiales'
                                                    ]
                                                )
                                            ) ?>

                                        </div>

                                    </div>

                                <?php endif; ?>


                                <?php if (
                                    (int)$intervencion[
                                        'cantidad_imagenes'
                                    ] > 0
                                ): ?>

                                    <div class="intervention-block">

                                        <a
                                            href="<?= url(
                                                'tecnico/ver_intervencion.php?id='
                                                .
                                                (int)$intervencion[
                                                    'id_intervencion'
                                                ]
                                            ) ?>"
                                            class="small text-decoration-none"
                                        >

                                            <i class="bi bi-images me-1"></i>

                                            <?= (int)$intervencion[
                                                'cantidad_imagenes'
                                            ] ?>

                                            imágenes adjuntas

                                        </a>

                                    </div>

                                <?php endif; ?>


                                <?php if (
                                    (int)$intervencion[
                                        'pendiente'
                                    ] === 1
                                ): ?>

                                    <div class="intervention-pending">

                                        <strong>
                                            Pendiente:
                                        </strong>

                                        <?= e(
                                            $intervencion[
                                                'motivo_pendiente'
                                            ]
                                            ?? ''
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
             FORMULARIO
        ================================================== -->

        <div class="col-xl-7">

            <div class="tech-card">

                <div class="tech-card-header">

                    <h5>

                        <i class="bi bi-wrench-adjustable me-2"></i>

                        Registrar intervención

                    </h5>


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


                <div class="tech-card-body">


                    <?php if (
                        $solicitudBloqueada
                    ): ?>

                        <div class="closed-box">

                            <i class="bi bi-lock"></i>

                            <strong>

                                Solicitud
                                <?= e(
                                    strtolower(
                                        $solicitud[
                                            'estado'
                                        ]
                                    )
                                ) ?>

                            </strong>

                            <div class="mt-2">

                                No pueden registrarse nuevas
                                intervenciones mientras la
                                solicitud se encuentre en este estado.

                            </div>

                        </div>


                    <?php elseif (
                        $solicitud[
                            'estado'
                        ] === 'Resuelta'
                    ): ?>

                        <div class="alert alert-success">

                            <i class="bi bi-check-circle me-1"></i>

                            Esta solicitud ya figura como
                            <strong>Resuelta</strong>.

                            Si el problema volvió a presentarse,
                            podés registrar otra intervención;
                            el ticket pasará nuevamente a
                            En proceso o Pendiente según corresponda.

                        </div>

                    <?php endif; ?>


                    <?php if (
                        !$solicitudBloqueada
                    ): ?>

                        <form
                            method="POST"
                            enctype="multipart/form-data"
                            action="<?= url(
                                'tecnico/intervenir.php?id='
                                .
                                $idSolicitud
                            ) ?>"
                            id="formIntervencion"
                        >

                            <?= csrfInput() ?>


                            <input
                                type="hidden"
                                name="id_solicitud"
                                value="<?= $idSolicitud ?>"
                            >


                            <!-- =================================
                                 DIAGNÓSTICO
                            ================================== -->

                            <div class="mb-4">

                                <label
                                    for="diagnostico"
                                    class="form-label"
                                >

                                    <i class="bi bi-search me-1"></i>

                                    Diagnóstico

                                </label>


                                <textarea
                                    name="diagnostico"
                                    id="diagnostico"
                                    class="form-control"
                                    maxlength="5000"
                                    required
                                    placeholder="Describí qué problema encontraste, qué verificaciones realizaste y cuál fue el diagnóstico técnico."
                                ><?= e(
                                    $diagnostico
                                ) ?></textarea>

                            </div>


                            <!-- =================================
                                 TRABAJO
                            ================================== -->

                            <div class="mb-4">

                                <label
                                    for="trabajo_realizado"
                                    class="form-label"
                                >

                                    <i class="bi bi-tools me-1"></i>

                                    Trabajo realizado

                                </label>


                                <textarea
                                    name="trabajo_realizado"
                                    id="trabajo_realizado"
                                    class="form-control"
                                    maxlength="5000"
                                    required
                                    placeholder="Detallá las tareas realizadas: reparación, configuración, reemplazo, limpieza, instalación, pruebas, etc."
                                ><?= e(
                                    $trabajoRealizado
                                ) ?></textarea>

                            </div>


                            <!-- =================================
                                 MATERIALES
                            ================================== -->

                            <div class="mb-4">

                                <label
                                    for="materiales"
                                    class="form-label"
                                >

                                    <i class="bi bi-box-seam me-1"></i>

                                    Materiales / repuestos utilizados

                                </label>


                                <textarea
                                    name="materiales"
                                    id="materiales"
                                    class="form-control"
                                    maxlength="3000"
                                    placeholder="Ej.: cable de red 3 m, ficha RJ45, fuente 12 V, disco SSD, tornillos, cable HDMI..."
                                ><?= e(
                                    $materiales
                                ) ?></textarea>


                                <div class="form-help">

                                    Si no se utilizó ningún material,
                                    podés dejar este campo vacío.

                                </div>

                            </div>


                            <!-- =================================
                                 OBSERVACIONES
                            ================================== -->

                            <div class="mb-4">

                                <label
                                    for="observaciones"
                                    class="form-label"
                                >

                                    <i class="bi bi-journal-text me-1"></i>

                                    Observaciones técnicas

                                </label>


                                <textarea
                                    name="observaciones"
                                    id="observaciones"
                                    class="form-control"
                                    maxlength="3000"
                                    placeholder="Recomendaciones, controles futuros, información adicional, observaciones del equipo, etc."
                                ><?= e(
                                    $observaciones
                                ) ?></textarea>

                            </div>


                            <!-- =================================
                                 FOTOS
                            ================================== -->

                            <div class="mb-4">

                                <label
                                    for="imagenes"
                                    class="form-label"
                                >

                                    <i class="bi bi-camera me-1"></i>

                                    Fotos de la intervención / solución

                                </label>


                                <div class="upload-box">

                                    <i class="bi bi-cloud-arrow-up"></i>

                                    <strong>

                                        Adjuntar imágenes

                                    </strong>

                                    <small>

                                        JPG, PNG o WEBP · máximo
                                        5 MB por imagen · hasta 6 fotos

                                    </small>


                                    <input
                                        type="file"
                                        name="imagenes[]"
                                        id="imagenes"
                                        class="form-control mt-3"
                                        accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                                        multiple
                                    >

                                </div>


                                <div
                                    id="previewImagenes"
                                    class="preview-grid"
                                ></div>

                            </div>


                            <hr class="my-4">


                            <!-- =================================
                                 RESULTADO
                            ================================== -->

                            <div class="mb-4">

                                <label class="form-label">

                                    <i class="bi bi-clipboard-check me-1"></i>

                                    Resultado de la intervención

                                </label>


                                <div class="resultado-grid">


                                    <!-- EN PROCESO -->

                                    <div class="resultado-option">

                                        <input
                                            type="radio"
                                            name="resultado"
                                            id="resultadoProceso"
                                            value="proceso"
                                            <?= $resultado === 'proceso'
                                                ? 'checked'
                                                : ''
                                            ?>
                                        >

                                        <label for="resultadoProceso">

                                            <i class="bi bi-arrow-repeat"></i>

                                            Continúa
                                            en proceso

                                        </label>

                                    </div>


                                    <!-- PENDIENTE -->

                                    <div class="resultado-option">

                                        <input
                                            type="radio"
                                            name="resultado"
                                            id="resultadoPendiente"
                                            value="pendiente"
                                            <?= $resultado === 'pendiente'
                                                ? 'checked'
                                                : ''
                                            ?>
                                        >

                                        <label for="resultadoPendiente">

                                            <i class="bi bi-hourglass-split"></i>

                                            Queda
                                            pendiente

                                        </label>

                                    </div>


                                    <!-- RESUELTO -->

                                    <div class="resultado-option">

                                        <input
                                            type="radio"
                                            name="resultado"
                                            id="resultadoResuelto"
                                            value="resuelto"
                                            <?= $resultado === 'resuelto'
                                                ? 'checked'
                                                : ''
                                            ?>
                                        >

                                        <label for="resultadoResuelto">

                                            <i class="bi bi-check-circle"></i>

                                            Problema
                                            resuelto

                                        </label>

                                    </div>


                                </div>

                            </div>


                            <!-- =================================
                                 MOTIVO PENDIENTE
                            ================================== -->

                            <div
                                class="mb-4"
                                id="contenedorPendiente"
                                style="display:none;"
                            >

                                <label
                                    for="tipo_pendiente"
                                    class="form-label"
                                >

                                    <i class="bi bi-tag me-1"></i>

                                    Tipo de pendiente

                                </label>


                                <select
                                    name="tipo_pendiente"
                                    id="tipo_pendiente"
                                    class="form-select mb-3"
                                >

                                    <option value="">
                                        Seleccionar tipo...
                                    </option>

                                    <?php foreach (
                                        $tiposPendientePermitidos
                                        as $opcionTipoPendiente
                                    ): ?>

                                        <option
                                            value="<?= e(
                                                $opcionTipoPendiente
                                            ) ?>"
                                            <?= $tipoPendiente === $opcionTipoPendiente
                                                ? 'selected'
                                                : ''
                                            ?>
                                        >

                                            <?= e(
                                                $opcionTipoPendiente
                                            ) ?>

                                        </option>

                                    <?php endforeach; ?>

                                </select>


                                <label
                                    for="motivo_pendiente"
                                    class="form-label"
                                >

                                    <i class="bi bi-exclamation-circle me-1"></i>

                                    Motivo del pendiente

                                </label>


                                <textarea
                                    name="motivo_pendiente"
                                    id="motivo_pendiente"
                                    class="form-control"
                                    maxlength="3000"
                                    placeholder="Ej.: pendiente compra de repuesto, autorización, presupuesto, disponibilidad de aula, espera de proveedor..."
                                ><?= e(
                                    $motivoPendiente
                                ) ?></textarea>


                                <div class="form-help">

                                    Este motivo será visible
                                    en el ticket para informar
                                    por qué no pudo finalizarse
                                    el trabajo.

                                </div>

                            </div>


                            <!-- =================================
                                 BOTÓN
                            ================================== -->

                            <button
                                type="submit"
                                class="btn btn-registrar"
                                id="btnRegistrar"
                            >

                                <i class="bi bi-floppy me-1"></i>

                                Registrar intervención

                            </button>


                        </form>

                    <?php endif; ?>


                </div>

            </div>

        </div>


    </div>


</div>


<!-- =========================================================
     VISUALIZADOR DE IMÁGENES
========================================================= -->

<div
    class="image-modal"
    id="imageModal"
>

    <button
        type="button"
        class="image-modal-close"
        id="cerrarImagen"
    >

        &times;

    </button>


    <img
        src=""
        alt="Vista ampliada"
        id="imagenGrande"
    >

</div>


<script>

// ============================================================
// MOSTRAR MOTIVO PENDIENTE
// ============================================================

const radiosResultado =
    document.querySelectorAll(
        'input[name="resultado"]'
    );


const contenedorPendiente =
    document.getElementById(
        'contenedorPendiente'
    );


const motivoPendiente =
    document.getElementById(
        'motivo_pendiente'
    );


const tipoPendienteSelect =
    document.getElementById(
        'tipo_pendiente'
    );


function actualizarResultado() {

    const seleccionado =
        document.querySelector(
            'input[name="resultado"]:checked'
        );


    if (
        !seleccionado
        ||
        !contenedorPendiente
    ) {

        return;
    }


    const pendiente =
        seleccionado.value
        === 'pendiente';


    contenedorPendiente.style.display =
        pendiente
            ? 'block'
            : 'none';


    if (motivoPendiente) {

        motivoPendiente.required =
            pendiente;

    }


    if (tipoPendienteSelect) {

        tipoPendienteSelect.required =
            pendiente;

    }

}


radiosResultado.forEach(
    function(radio) {

        radio.addEventListener(
            'change',
            actualizarResultado
        );

    }
);


actualizarResultado();


// ============================================================
// PREVISUALIZACIÓN DE IMÁGENES
// ============================================================

const inputImagenes =
    document.getElementById(
        'imagenes'
    );


const previewImagenes =
    document.getElementById(
        'previewImagenes'
    );


if (
    inputImagenes
    &&
    previewImagenes
) {

    inputImagenes.addEventListener(
        'change',
        function() {

            previewImagenes.innerHTML =
                '';


            const archivos =
                Array.from(
                    this.files
                );


            if (
                archivos.length > 6
            ) {

                alert(
                    'Podés adjuntar como máximo 6 imágenes.'
                );

                this.value =
                    '';

                return;
            }


            archivos.forEach(
                function(archivo) {

                    if (
                        !archivo.type.startsWith(
                            'image/'
                        )
                    ) {

                        return;
                    }


                    const lector =
                        new FileReader();


                    lector.onload =
                        function(evento) {

                            const contenedor =
                                document.createElement(
                                    'div'
                                );


                            contenedor.className =
                                'preview-image';


                            const imagen =
                                document.createElement(
                                    'img'
                                );


                            imagen.src =
                                evento.target.result;


                            imagen.alt =
                                'Vista previa';


                            contenedor.appendChild(
                                imagen
                            );


                            previewImagenes.appendChild(
                                contenedor
                            );

                        };


                    lector.readAsDataURL(
                        archivo
                    );

                }
            );

        }
    );

}


// ============================================================
// EVITAR DOBLE ENVÍO
// ============================================================

const formIntervencion =
    document.getElementById(
        'formIntervencion'
    );


const btnRegistrar =
    document.getElementById(
        'btnRegistrar'
    );


if (
    formIntervencion
    &&
    btnRegistrar
) {

    formIntervencion.addEventListener(
        'submit',
        function() {

            btnRegistrar.disabled =
                true;


            btnRegistrar.innerHTML =
                '<span class="spinner-border '
                +
                'spinner-border-sm me-2"></span>'
                +
                'Guardando intervención...';

        }
    );

}


// ============================================================
// VISUALIZADOR DE FOTO ORIGINAL
// ============================================================

const imageModal =
    document.getElementById(
        'imageModal'
    );


const imagenGrande =
    document.getElementById(
        'imagenGrande'
    );


const cerrarImagen =
    document.getElementById(
        'cerrarImagen'
    );


document
    .querySelectorAll(
        '.js-imagen'
    )
    .forEach(
        function(elemento) {

            elemento.addEventListener(
                'click',
                function() {

                    if (
                        !imageModal
                        ||
                        !imagenGrande
                    ) {

                        return;
                    }


                    imagenGrande.src =
                        this.dataset.imagen;


                    imageModal.classList.add(
                        'active'
                    );

                }
            );

        }
    );


function cerrarModalImagen() {

    if (!imageModal) {

        return;
    }


    imageModal.classList.remove(
        'active'
    );


    if (imagenGrande) {

        imagenGrande.src =
            '';

    }

}


if (cerrarImagen) {

    cerrarImagen.addEventListener(
        'click',
        cerrarModalImagen
    );

}


if (imageModal) {

    imageModal.addEventListener(
        'click',
        function(evento) {

            if (
                evento.target
                === imageModal
            ) {

                cerrarModalImagen();

            }

        }
    );

}


document.addEventListener(
    'keydown',
    function(evento) {

        if (
            evento.key
            === 'Escape'
        ) {

            cerrarModalImagen();

        }

    }
);

</script>


<?php

require_once __DIR__
    . '/../includes/footer.php';

?>