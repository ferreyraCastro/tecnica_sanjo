<?php
// ============================================================
// COLEGIO SAN JOSÉ
// Sistema de Gestión Técnica
// Archivo: /tecnica/nueva_solicitud.php
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
// DATOS GENERALES
// ============================================================

$idUsuario = (int)usuarioId();

$sectores = obtenerSectores($conexion);

$todasCategorias =
    obtenerTodasCategorias($conexion);


// ============================================================
// VARIABLES FORMULARIO
// ============================================================

$error = '';

$tipo = '';

$idSector = 0;

$idCategoria = 0;

$titulo = '';

$descripcion = '';

$prioridad = 'Normal';


// ============================================================
// VALORES PERMITIDOS
// ============================================================

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
// PROCESAR FORMULARIO
// ============================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ========================================================
    // CSRF
    // ========================================================

    $csrf =
        $_POST['csrf_token']
        ?? '';

    if (!validarCsrf($csrf)) {

        $error =
            'La sesión del formulario expiró. '
            . 'Actualizá la página e intentá nuevamente.';

    } else {

        // ====================================================
        // RECIBIR DATOS
        // ====================================================

        $tipo =
            limpiarTexto(
                $_POST['tipo']
                ?? ''
            );


        $idSector =
            (int)(
                $_POST['id_sector']
                ?? 0
            );


        $idCategoria =
            (int)(
                $_POST['id_categoria']
                ?? 0
            );


        $titulo =
            limpiarTexto(
                $_POST['titulo']
                ?? ''
            );


        $descripcion =
            limpiarTexto(
                $_POST['descripcion']
                ?? ''
            );


        $prioridad =
            limpiarTexto(
                $_POST['prioridad']
                ?? 'Normal'
            );


        // ====================================================
        // VALIDACIONES
        // ====================================================

        if (
            !in_array(
                $tipo,
                $tiposPermitidos,
                true
            )
        ) {

            $error =
                'Seleccioná el tipo de intervención.';

        } elseif (
            $idSector <= 0
        ) {

            $error =
                'Seleccioná el aula o sector donde se encuentra el problema.';

        } elseif (
            $idCategoria <= 0
        ) {

            $error =
                'Seleccioná una categoría.';

        } elseif (
            $titulo === ''
        ) {

            $error =
                'Ingresá un título para la solicitud.';

        } elseif (
            mb_strlen($titulo) < 5
        ) {

            $error =
                'El título debe tener al menos 5 caracteres.';

        } elseif (
            mb_strlen($titulo) > 200
        ) {

            $error =
                'El título no puede superar los 200 caracteres.';

        } elseif (
            $descripcion === ''
        ) {

            $error =
                'Describí el problema o necesidad.';

        } elseif (
            mb_strlen($descripcion) < 10
        ) {

            $error =
                'Agregá un poco más de información sobre el problema.';

        } elseif (
            !in_array(
                $prioridad,
                $prioridadesPermitidas,
                true
            )
        ) {

            $error =
                'La prioridad seleccionada no es válida.';
        }


        // ====================================================
        // VALIDAR SECTOR EN LA BASE
        // ====================================================

        if ($error === '') {

            $stmtSector =
                $conexion->prepare("
                    SELECT COUNT(*)

                    FROM sectores

                    WHERE id_sector = ?

                    AND activo = 1
                ");

            $stmtSector->execute([
                $idSector
            ]);

            if (
                (int)$stmtSector->fetchColumn()
                === 0
            ) {

                $error =
                    'El sector seleccionado no es válido.';
            }
        }


        // ====================================================
        // VALIDAR CATEGORÍA
        // También comprobamos que pertenezca al tipo elegido.
        // ====================================================

        if ($error === '') {

            $stmtCategoria =
                $conexion->prepare("
                    SELECT COUNT(*)

                    FROM categorias

                    WHERE id_categoria = ?

                    AND tipo = ?

                    AND activo = 1
                ");

            $stmtCategoria->execute([
                $idCategoria,
                $tipo
            ]);

            if (
                (int)$stmtCategoria->fetchColumn()
                === 0
            ) {

                $error =
                    'La categoría seleccionada no corresponde '
                    . 'al tipo de intervención.';
            }
        }


        // ====================================================
        // NORMALIZAR IMÁGENES
        // ====================================================

        $imagenes = [];

        if (
            isset($_FILES['imagenes'])
            &&
            isset($_FILES['imagenes']['name'])
            &&
            is_array($_FILES['imagenes']['name'])
        ) {

            $cantidadArchivos =
                count(
                    $_FILES['imagenes']['name']
                );

            for (
                $i = 0;
                $i < $cantidadArchivos;
                $i++
            ) {

                // Ignorar campos vacíos.

                if (
                    ($_FILES['imagenes']['error'][$i] ?? UPLOAD_ERR_NO_FILE)
                    === UPLOAD_ERR_NO_FILE
                ) {

                    continue;
                }

                $imagenes[] = [

                    'name' =>
                        $_FILES['imagenes']['name'][$i]
                        ?? '',

                    'type' =>
                        $_FILES['imagenes']['type'][$i]
                        ?? '',

                    'tmp_name' =>
                        $_FILES['imagenes']['tmp_name'][$i]
                        ?? '',

                    'error' =>
                        $_FILES['imagenes']['error'][$i]
                        ?? UPLOAD_ERR_NO_FILE,

                    'size' =>
                        $_FILES['imagenes']['size'][$i]
                        ?? 0

                ];
            }
        }


        // ====================================================
        // MÁXIMO 6 FOTOS
        // ====================================================

        if (
            $error === ''
            &&
            count($imagenes) > 6
        ) {

            $error =
                'Podés adjuntar un máximo de 6 fotografías.';
        }


        // ====================================================
        // CREAR SOLICITUD
        // ====================================================

        if ($error === '') {

            $archivosGuardados = [];

            try {

                $conexion->beginTransaction();


                // ============================================
                // INSERT SOLICITUD
                // ============================================

                $stmt =
                    $conexion->prepare("
                        INSERT INTO solicitudes
                        (
                            id_usuario,
                            id_sector,
                            id_categoria,
                            tipo,
                            titulo,
                            descripcion,
                            prioridad,
                            estado
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
                            'Nueva'
                        )
                    ");


                $stmt->execute([

                    $idUsuario,

                    $idSector,

                    $idCategoria,

                    $tipo,

                    $titulo,

                    $descripcion,

                    $prioridad

                ]);


                $idSolicitud =
                    (int)$conexion->lastInsertId();


                // ============================================
                // HISTORIAL INICIAL
                // ============================================

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
                            'Nueva',
                            ?
                        )
                    ");


                $stmtHistorial->execute([

                    $idSolicitud,

                    $idUsuario,

                    'Solicitud creada por '
                    . usuarioNombre()
                    . '.'

                ]);


                // ============================================
                // SUBIR IMÁGENES
                // ============================================

                foreach (
                    $imagenes as $imagen
                ) {

                    $resultado =
                        subirImagen(
                            $imagen,
                            UPLOAD_SOLICITUDES,
                            MAX_IMAGEN_MB
                        );


                    if (
                        !$resultado['ok']
                    ) {

                        throw new RuntimeException(
                            $resultado['error']
                            ?? 'No se pudo cargar una fotografía.'
                        );
                    }


                    $nombreArchivo =
                        $resultado['archivo'];


                    $archivosGuardados[] =
                        UPLOAD_SOLICITUDES
                        . DIRECTORY_SEPARATOR
                        . $nombreArchivo;


                    // ========================================
                    // GUARDAR FOTO EN BD
                    // ========================================

                    $stmtImagen =
                        $conexion->prepare("
                            INSERT INTO solicitud_imagenes
                            (
                                id_solicitud,
                                id_usuario,
                                archivo,
                                nombre_original,
                                tipo
                            )
                            VALUES
                            (
                                ?,
                                ?,
                                ?,
                                ?,
                                'Solicitud'
                            )
                        ");


                    $stmtImagen->execute([

                        $idSolicitud,

                        $idUsuario,

                        $nombreArchivo,

                        $resultado['nombre_original']
                        ?? null

                    ]);
                }


                // ============================================
                // COMMIT
                // ============================================

                $conexion->commit();


                // ============================================
                // CORREOS (confirmación docente + alerta técnicos)
                // No interrumpe el flujo si falla el envío.
                // ============================================

                try {

                    notificarNuevoTicket(
                        $conexion,
                        [
                            'correo' =>
                                usuarioCorreo(),

                            'nombre' =>
                                $_SESSION['usuario']['nombre']
                                ?? '',

                            'apellido' =>
                                $_SESSION['usuario']['apellido']
                                ?? '',

                            'titulo' =>
                                $titulo,

                            'prioridad' =>
                                $prioridad
                        ],
                        $idSolicitud
                    );

                } catch (Throwable $e) {

                    error_log(
                        'Error enviando correo de nuevo ticket: '
                        . $e->getMessage()
                    );
                }


                // ============================================
                // WHATSAPP A LOS TÉCNICOS
                // Solo se envía a quienes ya cargaron su
                // teléfono + apikey en su perfil. No interrumpe
                // el flujo si falla.
                // ============================================

                try {

                    notificarNuevoTicketWhatsapp(
                        $conexion,
                        $idSolicitud,
                        $titulo,
                        $prioridad
                    );

                } catch (Throwable $e) {

                    error_log(
                        'Error enviando WhatsApp de nuevo ticket: '
                        . $e->getMessage()
                    );
                }


                // ============================================
                // MENSAJE FLASH
                // ============================================

                flash(
                    'success',
                    'La solicitud '
                    . numeroTicket($idSolicitud)
                    . ' fue registrada correctamente.'
                );


                // ============================================
                // REDIRECCIÓN
                // ============================================

                header(
                    'Location: '
                    . url(
                        'ver_solicitud.php?id='
                        . $idSolicitud
                    )
                );

                exit;


            } catch (Throwable $e) {

                // ============================================
                // ROLLBACK
                // ============================================

                if (
                    $conexion->inTransaction()
                ) {

                    $conexion->rollBack();
                }


                // ============================================
                // BORRAR FOTOS QUE HAYAN SIDO SUBIDAS
                // ============================================

                foreach (
                    $archivosGuardados
                    as $archivoGuardado
                ) {

                    if (
                        is_file(
                            $archivoGuardado
                        )
                    ) {

                        @unlink(
                            $archivoGuardado
                        );
                    }
                }


                error_log(
                    'Error nueva solicitud: '
                    . $e->getMessage()
                );


                if (
                    $e instanceof RuntimeException
                ) {

                    $error =
                        $e->getMessage();

                } else {

                    $error =
                        'No se pudo registrar la solicitud. '
                        . 'Intentá nuevamente.';
                }
            }
        }
    }
}


// ============================================================
// HEADER
// ============================================================

require_once __DIR__ . '/includes/header.php';

?>


<style>

    /* ========================================================
       NUEVA SOLICITUD
    ======================================================== */

    .solicitud-wrapper {

        max-width: 1100px;

        margin: 0 auto;

        padding:
            5px 10px
            40px;

    }


    /* ========================================================
       ENCABEZADO
    ======================================================== */

    .page-header {

        display: flex;

        align-items: center;

        justify-content: space-between;

        gap: 20px;

        margin-bottom: 25px;

    }


    .page-header h1 {

        margin: 0;

        color: #760000;

        font-size: 27px;

        font-weight: 800;

    }


    .page-header p {

        margin:
            6px 0 0;

        color: #777777;

    }


    .btn-volver {

        display: inline-flex;

        align-items: center;

        gap: 7px;

        padding:
            9px 15px;

        color: #760000;

        background: #FFFFFF;

        border:
            1px solid #E3D5D5;

        border-radius: 10px;

        text-decoration: none;

        font-size: 14px;

        font-weight: 600;

    }


    .btn-volver:hover {

        background: #FFF5F5;

        color: #B12626;

    }


    /* ========================================================
       CARD PRINCIPAL
    ======================================================== */

    .form-card {

        background: #FFFFFF;

        border:
            1px solid #ECECEC;

        border-radius: 20px;

        overflow: hidden;

        box-shadow:
            0 7px 25px
            rgba(0,0,0,.06);

    }


    .form-card-header {

        background:
            linear-gradient(
                135deg,
                #760000,
                #B12626
            );

        color: #FFFFFF;

        padding:
            22px 25px;

    }


    .form-card-header h5 {

        margin: 0;

        font-weight: 700;

    }


    .form-card-header p {

        color:
            rgba(255,255,255,.75);

        font-size: 13px;

        margin:
            6px 0 0;

    }


    .form-card-body {

        padding: 28px;

    }


    /* ========================================================
       LABELS
    ======================================================== */

    .form-label {

        color: #444444;

        font-size: 14px;

        font-weight: 700;

        margin-bottom: 7px;

    }


    .campo-obligatorio {

        color: #B12626;

    }


    .form-control,
    .form-select {

        min-height: 49px;

        border:
            1px solid #DCDCDC;

        border-radius: 10px;

    }


    textarea.form-control {

        min-height: 150px;

        resize: vertical;

    }


    .form-control:focus,
    .form-select:focus {

        border-color: #B12626;

        box-shadow:
            0 0 0 .2rem
            rgba(177,38,38,.08);

    }


    /* ========================================================
       TIPO
    ======================================================== */

    .tipo-selector {

        display: grid;

        grid-template-columns:
            repeat(
                2,
                1fr
            );

        gap: 14px;

    }


    .tipo-opcion {

        position: relative;

    }


    .tipo-opcion input {

        position: absolute;

        opacity: 0;

        pointer-events: none;

    }


    .tipo-opcion label {

        width: 100%;

        min-height: 95px;

        display: flex;

        align-items: center;

        gap: 14px;

        padding: 17px;

        border:
            2px solid #E7E7E7;

        border-radius: 15px;

        cursor: pointer;

        transition:
            all .2s ease;

        background: #FFFFFF;

    }


    .tipo-opcion label:hover {

        border-color:
            rgba(177,38,38,.40);

        background: #FFF9F9;

    }


    .tipo-opcion input:checked
    + label {

        border-color: #B12626;

        background: #FFF5F5;

        box-shadow:
            0 0 0 3px
            rgba(177,38,38,.07);

    }


    .tipo-icon {

        min-width: 51px;

        width: 51px;

        height: 51px;

        border-radius: 13px;

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

        font-size: 23px;

    }


    .tipo-opcion strong {

        display: block;

        color: #333333;

        margin-bottom: 3px;

    }


    .tipo-opcion small {

        color: #888888;

        font-size: 12px;

    }


    /* ========================================================
       PRIORIDAD
    ======================================================== */

    .prioridad-ayuda {

        background: #F8F8F8;

        border-radius: 10px;

        padding:
            11px 14px;

        color: #6D6D6D;

        font-size: 12px;

        margin-top: 8px;

    }


    /* ========================================================
       FOTOS
    ======================================================== */

    .upload-box {

        position: relative;

        border:
            2px dashed #D7D7D7;

        background: #FAFAFA;

        border-radius: 15px;

        padding:
            27px 20px;

        text-align: center;

        transition:
            all .2s ease;

    }


    .upload-box:hover {

        border-color: #B12626;

        background: #FFF8F8;

    }


    .upload-box i {

        display: block;

        color: #B12626;

        font-size: 34px;

        margin-bottom: 7px;

    }


    .upload-box strong {

        display: block;

        color: #444444;

        margin-bottom: 4px;

    }


    .upload-box small {

        color: #888888;

    }


    .upload-input {

        margin-top: 15px;

    }


    .preview-container {

        display: grid;

        grid-template-columns:
            repeat(
                auto-fill,
                minmax(125px,1fr)
            );

        gap: 12px;

        margin-top: 17px;

    }


    .preview-item {

        position: relative;

        aspect-ratio: 1 / 1;

        overflow: hidden;

        border-radius: 12px;

        background: #EEEEEE;

        border:
            1px solid #DDDDDD;

    }


    .preview-item img {

        width: 100%;

        height: 100%;

        object-fit: cover;

    }


    /* ========================================================
       INFORMACIÓN
    ======================================================== */

    .info-box {

        background: #FFF7F7;

        border-left:
            4px solid #B12626;

        border-radius: 10px;

        padding: 15px;

        font-size: 13px;

        color: #5F5F5F;

    }


    /* ========================================================
       ERROR
    ======================================================== */

    .alert-error {

        background: #FFF1F1;

        color: #8A1111;

        border:
            1px solid #F1CDCD;

        border-left:
            4px solid #B12626;

        border-radius: 11px;

        padding:
            14px 16px;

        margin-bottom: 22px;

    }


    /* ========================================================
       BOTONES
    ======================================================== */

    .form-actions {

        display: flex;

        justify-content: flex-end;

        gap: 10px;

        padding-top: 24px;

        margin-top: 25px;

        border-top:
            1px solid #EEEEEE;

    }


    .btn-cancelar {

        border:
            1px solid #D7D7D7;

        background: #FFFFFF;

        color: #555555;

        border-radius: 10px;

        padding:
            11px 20px;

        font-weight: 600;

    }


    .btn-cancelar:hover {

        background: #F5F5F5;

    }


    .btn-guardar {

        border: none;

        background:
            linear-gradient(
                135deg,
                #760000,
                #B12626
            );

        color: #FFFFFF;

        border-radius: 10px;

        padding:
            11px 22px;

        font-weight: 700;

        box-shadow:
            0 6px 16px
            rgba(118,0,0,.18);

    }


    .btn-guardar:hover {

        background: #760000;

        color: #FFFFFF;

    }


    /* ========================================================
       RESPONSIVE
    ======================================================== */

    @media
    (max-width: 700px) {

        .page-header {

            align-items: flex-start;

            flex-direction: column;

        }


        .tipo-selector {

            grid-template-columns: 1fr;

        }


        .form-card-body {

            padding: 20px;

        }


        .form-actions {

            flex-direction: column-reverse;

        }


        .form-actions a,
        .form-actions button {

            width: 100%;

        }

    }

</style>



<div class="solicitud-wrapper">


    <!-- =====================================================
         ENCABEZADO
    ====================================================== -->

    <div class="page-header">

        <div>

            <h1>

                <i class="bi bi-plus-circle me-1"></i>

                Nueva solicitud

            </h1>

            <p>

                Informá una necesidad de informática
                o mantenimiento general.

            </p>

        </div>


        <a
            href="<?= url('dashboard.php') ?>"
            class="btn-volver"
        >

            <i class="bi bi-arrow-left"></i>

            Volver al dashboard

        </a>

    </div>



    <!-- =====================================================
         ERROR
    ====================================================== -->

    <?php if ($error !== ''): ?>

        <div class="alert-error">

            <i class="bi bi-exclamation-triangle me-2"></i>

            <?= e($error) ?>

        </div>

    <?php endif; ?>



    <!-- =====================================================
         FORMULARIO
    ====================================================== -->

    <div class="form-card">

        <div class="form-card-header">

            <h5>

                <i class="bi bi-ticket-detailed me-2"></i>

                Datos de la solicitud

            </h5>

            <p>

                Completá la mayor cantidad de información posible
                para facilitar la intervención.

            </p>

        </div>


        <div class="form-card-body">


            <form
                method="POST"
                action="<?= url('nueva_solicitud.php') ?>"
                enctype="multipart/form-data"
                id="formSolicitud"
            >


                <?= csrfInput() ?>


                <!-- =================================================
                     TIPO
                ================================================== -->

                <div class="mb-4">

                    <label class="form-label">

                        Tipo de intervención

                        <span class="campo-obligatorio">
                            *
                        </span>

                    </label>


                    <div class="tipo-selector">


                        <!-- INFORMÁTICA -->

                        <div class="tipo-opcion">

                            <input
                                type="radio"
                                name="tipo"
                                id="tipoInformatica"
                                value="Informatica"
                                <?= $tipo === 'Informatica'
                                    ? 'checked'
                                    : ''
                                ?>
                                required
                            >

                            <label for="tipoInformatica">

                                <div class="tipo-icon">

                                    <i class="bi bi-pc-display"></i>

                                </div>

                                <div>

                                    <strong>
                                        Informática
                                    </strong>

                                    <small>
                                        PC, WiFi, internet,
                                        proyector, audio,
                                        impresoras o software.
                                    </small>

                                </div>

                            </label>

                        </div>



                        <!-- MANTENIMIENTO -->

                        <div class="tipo-opcion">

                            <input
                                type="radio"
                                name="tipo"
                                id="tipoMantenimiento"
                                value="Mantenimiento"
                                <?= $tipo === 'Mantenimiento'
                                    ? 'checked'
                                    : ''
                                ?>
                                required
                            >

                            <label for="tipoMantenimiento">

                                <div class="tipo-icon">

                                    <i class="bi bi-tools"></i>

                                </div>

                                <div>

                                    <strong>
                                        Mantenimiento general
                                    </strong>

                                    <small>
                                        Electricidad, iluminación,
                                        mobiliario, puertas,
                                        agua o reparaciones.
                                    </small>

                                </div>

                            </label>

                        </div>


                    </div>

                </div>



                <div class="row g-3">


                    <!-- =================================================
                         SECTOR
                    ================================================== -->

                    <div class="col-md-6">

                        <label
                            for="id_sector"
                            class="form-label"
                        >

                            Aula / Sector

                            <span class="campo-obligatorio">
                                *
                            </span>

                        </label>


                        <select
                            name="id_sector"
                            id="id_sector"
                            class="form-select"
                            required
                        >

                            <option value="">
                                Seleccionar sector...
                            </option>


                            <?php foreach (
                                $sectores
                                as $sector
                            ): ?>

                                <option
                                    value="<?= (int)$sector['id_sector'] ?>"
                                    <?= $idSector
                                        === (int)$sector['id_sector']
                                        ? 'selected'
                                        : ''
                                    ?>
                                >

                                    <?= e(
                                        $sector['nombre']
                                    ) ?>

                                    <?php if (
                                        !empty(
                                            $sector['tipo']
                                        )
                                    ): ?>

                                        -
                                        <?= e(
                                            $sector['tipo']
                                        ) ?>

                                    <?php endif; ?>

                                </option>

                            <?php endforeach; ?>


                        </select>

                    </div>



                    <!-- =================================================
                         CATEGORÍA
                    ================================================== -->

                    <div class="col-md-6">

                        <label
                            for="id_categoria"
                            class="form-label"
                        >

                            Categoría

                            <span class="campo-obligatorio">
                                *
                            </span>

                        </label>


                        <select
                            name="id_categoria"
                            id="id_categoria"
                            class="form-select"
                            required
                            disabled
                        >

                            <option value="">
                                Primero seleccioná el tipo...
                            </option>

                        </select>

                    </div>



                    <!-- =================================================
                         PRIORIDAD
                    ================================================== -->

                    <div class="col-md-6">

                        <label
                            for="prioridad"
                            class="form-label"
                        >

                            Prioridad

                            <span class="campo-obligatorio">
                                *
                            </span>

                        </label>


                        <select
                            name="prioridad"
                            id="prioridad"
                            class="form-select"
                            required
                        >

                            <option
                                value="Baja"
                                <?= $prioridad === 'Baja'
                                    ? 'selected'
                                    : ''
                                ?>
                            >
                                Baja
                            </option>


                            <option
                                value="Normal"
                                <?= $prioridad === 'Normal'
                                    ? 'selected'
                                    : ''
                                ?>
                            >
                                Normal
                            </option>


                            <option
                                value="Alta"
                                <?= $prioridad === 'Alta'
                                    ? 'selected'
                                    : ''
                                ?>
                            >
                                Alta
                            </option>


                            <option
                                value="Urgente"
                                <?= $prioridad === 'Urgente'
                                    ? 'selected'
                                    : ''
                                ?>
                            >
                                Urgente
                            </option>

                        </select>


                        <div class="prioridad-ayuda">

                            <i class="bi bi-info-circle me-1"></i>

                            Utilizá <strong>Urgente</strong>
                            solamente cuando el problema impida
                            desarrollar normalmente una actividad.

                        </div>

                    </div>



                    <!-- =================================================
                         SOLICITANTE
                    ================================================== -->

                    <div class="col-md-6">

                        <label class="form-label">
                            Solicitante
                        </label>


                        <input
                            type="text"
                            class="form-control"
                            value="<?= e(
                                usuarioNombre()
                            ) ?>"
                            disabled
                        >

                    </div>


                </div>



                <!-- =================================================
                     TÍTULO
                ================================================== -->

                <div class="mt-4">

                    <label
                        for="titulo"
                        class="form-label"
                    >

                        Título del problema

                        <span class="campo-obligatorio">
                            *
                        </span>

                    </label>


                    <input
                        type="text"
                        name="titulo"
                        id="titulo"
                        class="form-control"
                        value="<?= e($titulo) ?>"
                        maxlength="200"
                        placeholder="Ej.: La computadora del aula no enciende"
                        required
                    >

                </div>



                <!-- =================================================
                     DESCRIPCIÓN
                ================================================== -->

                <div class="mt-4">

                    <label
                        for="descripcion"
                        class="form-label"
                    >

                        Descripción

                        <span class="campo-obligatorio">
                            *
                        </span>

                    </label>


                    <textarea
                        name="descripcion"
                        id="descripcion"
                        class="form-control"
                        placeholder="Describí qué ocurre, cuándo comenzó el problema y cualquier información que pueda ayudar al personal técnico."
                        required
                    ><?= e($descripcion) ?></textarea>


                    <div class="form-text mt-2">

                        Por ejemplo:
                        “Al encender la computadora queda
                        la pantalla negra y no permite ingresar”.

                    </div>

                </div>



                <!-- =================================================
                     FOTOGRAFÍAS
                ================================================== -->

                <div class="mt-4">

                    <label class="form-label">

                        Fotografías

                        <span class="text-muted fw-normal">
                            (opcional)
                        </span>

                    </label>


                    <div class="upload-box">

                        <i class="bi bi-images"></i>

                        <strong>
                            Adjuntá fotografías del problema
                        </strong>

                        <small>

                            Podés seleccionar hasta 6 imágenes
                            en formato JPG, PNG o WEBP.
                            Máximo <?= MAX_IMAGEN_MB ?> MB
                            por fotografía.

                        </small>


                        <input
                            type="file"
                            name="imagenes[]"
                            id="imagenes"
                            class="form-control upload-input"
                            accept="image/jpeg,image/png,image/webp"
                            multiple
                        >


                        <div
                            class="preview-container"
                            id="previewContainer"
                        ></div>

                    </div>

                </div>



                <!-- =================================================
                     AVISO
                ================================================== -->

                <div class="info-box mt-4">

                    <i class="bi bi-info-circle me-1"></i>

                    Una vez registrada, la solicitud recibirá
                    un número de ticket y podrás consultar
                    desde el sistema si está
                    <strong>Nueva</strong>,
                    <strong>En proceso</strong>,
                    <strong>Pendiente</strong>
                    o <strong>Resuelta</strong>.

                </div>



                <!-- =================================================
                     BOTONES
                ================================================== -->

                <div class="form-actions">


                    <a
                        href="<?= url('dashboard.php') ?>"
                        class="btn btn-cancelar"
                    >

                        Cancelar

                    </a>


                    <button
                        type="submit"
                        class="btn btn-guardar"
                        id="btnGuardar"
                    >

                        <i class="bi bi-send me-2"></i>

                        Registrar solicitud

                    </button>


                </div>


            </form>


        </div>

    </div>

</div>



<script>

// ============================================================
// CATEGORÍAS DISPONIBLES DESDE PHP
// ============================================================

const categorias = <?= json_encode(
    $todasCategorias,
    JSON_UNESCAPED_UNICODE
    | JSON_UNESCAPED_SLASHES
) ?>;


// Categoría seleccionada en caso de error de validación.

const categoriaSeleccionadaInicial =
    <?= (int)$idCategoria ?>;


// ============================================================
// ELEMENTOS
// ============================================================

const radiosTipo =
    document.querySelectorAll(
        'input[name="tipo"]'
    );


const selectCategoria =
    document.getElementById(
        'id_categoria'
    );


const inputImagenes =
    document.getElementById(
        'imagenes'
    );


const previewContainer =
    document.getElementById(
        'previewContainer'
    );


const formulario =
    document.getElementById(
        'formSolicitud'
    );


const btnGuardar =
    document.getElementById(
        'btnGuardar'
    );


// ============================================================
// CARGAR CATEGORÍAS SEGÚN TIPO
// ============================================================

function cargarCategorias(
    tipo,
    categoriaSeleccionada = 0
) {

    selectCategoria.innerHTML = '';

    if (!tipo) {

        const option =
            document.createElement(
                'option'
            );

        option.value = '';

        option.textContent =
            'Primero seleccioná el tipo...';

        selectCategoria.appendChild(
            option
        );

        selectCategoria.disabled =
            true;

        return;
    }


    selectCategoria.disabled =
        false;


    const optionInicial =
        document.createElement(
            'option'
        );

    optionInicial.value = '';

    optionInicial.textContent =
        'Seleccionar categoría...';

    selectCategoria.appendChild(
        optionInicial
    );


    categorias
        .filter(
            categoria =>
                categoria.tipo === tipo
        )
        .forEach(
            categoria => {

                const option =
                    document.createElement(
                        'option'
                    );

                option.value =
                    categoria.id_categoria;

                option.textContent =
                    categoria.nombre;


                if (
                    parseInt(
                        categoria.id_categoria
                    ) ===
                    parseInt(
                        categoriaSeleccionada
                    )
                ) {

                    option.selected =
                        true;
                }


                selectCategoria.appendChild(
                    option
                );

            }
        );
}


// ============================================================
// EVENTO CAMBIO DE TIPO
// ============================================================

radiosTipo.forEach(
    radio => {

        radio.addEventListener(
            'change',
            function () {

                cargarCategorias(
                    this.value
                );

            }
        );

    }
);


// ============================================================
// CARGAR ESTADO INICIAL
// ============================================================

const tipoInicial =
    document.querySelector(
        'input[name="tipo"]:checked'
    );


if (tipoInicial) {

    cargarCategorias(
        tipoInicial.value,
        categoriaSeleccionadaInicial
    );

}


// ============================================================
// PREVISUALIZAR IMÁGENES
// ============================================================

inputImagenes.addEventListener(
    'change',
    function () {

        previewContainer.innerHTML =
            '';


        const archivos =
            Array.from(
                this.files
            );


        if (
            archivos.length > 6
        ) {

            alert(
                'Podés adjuntar como máximo 6 fotografías.'
            );

            this.value = '';

            return;
        }


        archivos.forEach(
            archivo => {

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
                    function (evento) {

                        const item =
                            document.createElement(
                                'div'
                            );


                        item.className =
                            'preview-item';


                        const imagen =
                            document.createElement(
                                'img'
                            );


                        imagen.src =
                            evento.target.result;


                        imagen.alt =
                            'Vista previa';


                        item.appendChild(
                            imagen
                        );


                        previewContainer.appendChild(
                            item
                        );

                    };


                lector.readAsDataURL(
                    archivo
                );

            }
        );

    }
);


// ============================================================
// EVITAR DOBLE ENVÍO
// ============================================================

formulario.addEventListener(
    'submit',
    function () {

        btnGuardar.disabled =
            true;


        btnGuardar.innerHTML =
            '<span class="spinner-border spinner-border-sm me-2"></span>'
            +
            'Registrando...';

    }
);

</script>


<?php

require_once __DIR__ . '/includes/footer.php';

?>