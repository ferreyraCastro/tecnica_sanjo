<?php
// ============================================================
// COLEGIO SAN JOSÉ
// Sistema de Gestión Técnica
// Archivo: /tecnica/tecnico/perfil.php
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
// USUARIO ACTUAL
// ============================================================

$idUsuario = (int) usuarioId();


// ============================================================
// OBTENER DATOS DEL USUARIO
// ============================================================

$stmtUsuario = $conexion->prepare("
    SELECT

        id_usuario,
        nombre,
        apellido,
        correo,
        telefono,
        rol,
        estado,
        ultimo_acceso,
        fecha_creacion,
        fecha_actualizacion

    FROM usuarios

    WHERE id_usuario = ?

    LIMIT 1
");


$stmtUsuario->execute([
    $idUsuario
]);


$usuario =
    $stmtUsuario->fetch(
        PDO::FETCH_ASSOC
    );


if (!$usuario) {

    session_destroy();

    header(
        'Location: ' . url('login.php')
    );

    exit;
}


// ============================================================
// VARIABLES FORMULARIO
// ============================================================

$error = '';

$nombre =
    $usuario['nombre'];

$apellido =
    $usuario['apellido'];

$correo =
    $usuario['correo'];

$telefono =
    $usuario['telefono'] ?? '';


// ============================================================
// PROCESAR FORMULARIOS
// ============================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

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

    } else {

        $accion =
            trim(
                $_POST['accion']
                ?? ''
            );


        // ====================================================
        // ACTUALIZAR DATOS PERSONALES
        // ====================================================

        if ($accion === 'actualizar_datos') {

            $nombre =
                trim(
                    $_POST['nombre']
                    ?? ''
                );


            $apellido =
                trim(
                    $_POST['apellido']
                    ?? ''
                );


            $correo =
                mb_strtolower(
                    trim(
                        $_POST['correo']
                        ?? ''
                    )
                );


            $telefono =
                preg_replace(
                    '/[^0-9+\s()\-]/',
                    '',
                    trim(
                        $_POST['telefono']
                        ?? ''
                    )
                )
                ?? '';


            // ================================================
            // VALIDACIONES
            // ================================================

            if ($nombre === '') {

                $error =
                    'Ingresá tu nombre.';

            } elseif (
                mb_strlen($nombre) > 100
            ) {

                $error =
                    'El nombre no puede superar los 100 caracteres.';

            } elseif ($apellido === '') {

                $error =
                    'Ingresá tu apellido.';

            } elseif (
                mb_strlen($apellido) > 100
            ) {

                $error =
                    'El apellido no puede superar los 100 caracteres.';

            } elseif (
                !filter_var(
                    $correo,
                    FILTER_VALIDATE_EMAIL
                )
            ) {

                $error =
                    'Ingresá una dirección de correo válida.';

            } elseif (
                mb_strlen($correo) > 150
            ) {

                $error =
                    'El correo no puede superar los 150 caracteres.';

            } elseif (
                $telefono !== ''
                &&
                mb_strlen($telefono) > 30
            ) {

                $error =
                    'El teléfono no puede superar los 30 caracteres.';
            }


            // ================================================
            // VERIFICAR CORREO DUPLICADO
            // ================================================

            if ($error === '') {

                $stmtCorreo =
                    $conexion->prepare("
                        SELECT COUNT(*)

                        FROM usuarios

                        WHERE
                            correo = ?

                        AND
                            id_usuario <> ?
                    ");


                $stmtCorreo->execute([
                    $correo,
                    $idUsuario
                ]);


                if (
                    (int)$stmtCorreo
                        ->fetchColumn() > 0
                ) {

                    $error =
                        'Ese correo ya pertenece a otro usuario.';
                }
            }


            // ================================================
            // ACTUALIZAR
            // ================================================

            if ($error === '') {

                try {

                    $stmtActualizar =
                        $conexion->prepare("
                            UPDATE usuarios

                            SET
                                nombre = ?,
                                apellido = ?,
                                correo = ?,
                                telefono = ?

                            WHERE id_usuario = ?
                        ");


                    $stmtActualizar->execute([

                        $nombre,
                        $apellido,
                        $correo,
                        $telefono !== '' ? $telefono : null,
                        $idUsuario

                    ]);


                    // ========================================
                    // ACTUALIZAR DATOS EN SESIÓN
                    // ========================================

                    $_SESSION['usuario']['nombre'] =
                        $nombre;


                    $_SESSION['usuario']['apellido'] =
                        $apellido;


                    $_SESSION['usuario']['correo'] =
                        $correo;


                    flash(
                        'success',
                        'Tus datos fueron actualizados correctamente.'
                    );


                    header(
                        'Location: '
                        . url(
                            'tecnico/perfil.php'
                        )
                    );

                    exit;


                } catch (Throwable $e) {

                    error_log(
                        'Error actualizando perfil: '
                        .
                        $e->getMessage()
                    );


                    $error =
                        'No se pudieron actualizar tus datos.';
                }
            }
        }


        // ====================================================
        // CAMBIAR DNI
        // ====================================================

        elseif ($accion === 'cambiar_dni') {

            $dniActual =
                preg_replace(
                    '/\D+/',
                    '',
                    $_POST['dni_actual']
                    ?? ''
                );


            $nuevoDni =
                preg_replace(
                    '/\D+/',
                    '',
                    $_POST['nuevo_dni']
                    ?? ''
                );


            $confirmarDni =
                preg_replace(
                    '/\D+/',
                    '',
                    $_POST['confirmar_dni']
                    ?? ''
                );


            // ================================================
            // VALIDACIONES
            // ================================================

            if (
                strlen($dniActual) < 7
                ||
                strlen($dniActual) > 9
            ) {

                $error =
                    'Ingresá correctamente tu DNI actual.';

            } elseif (
                strlen($nuevoDni) < 7
                ||
                strlen($nuevoDni) > 9
            ) {

                $error =
                    'El nuevo DNI debe contener entre 7 y 9 números.';

            } elseif (
                $nuevoDni !== $confirmarDni
            ) {

                $error =
                    'El nuevo DNI y su confirmación no coinciden.';

            } elseif (
                $dniActual === $nuevoDni
            ) {

                $error =
                    'El nuevo DNI debe ser diferente al actual.';
            }


            // ================================================
            // OBTENER HASH ACTUAL
            // ================================================

            if ($error === '') {

                $stmtHash =
                    $conexion->prepare("
                        SELECT dni_hash

                        FROM usuarios

                        WHERE id_usuario = ?

                        LIMIT 1
                    ");


                $stmtHash->execute([
                    $idUsuario
                ]);


                $hashActual =
                    (string)$stmtHash
                        ->fetchColumn();


                /*
                 * Tu sistema debería tener todos los DNI
                 * almacenados con password_hash().
                 */

                if (
                    !password_verify(
                        $dniActual,
                        $hashActual
                    )
                ) {

                    $error =
                        'El DNI actual ingresado no es correcto.';
                }
            }


            // ================================================
            // ACTUALIZAR DNI
            // ================================================

            if ($error === '') {

                try {

                    $nuevoHash =
                        password_hash(
                            $nuevoDni,
                            PASSWORD_DEFAULT
                        );


                    $stmtDni =
                        $conexion->prepare("
                            UPDATE usuarios

                            SET dni_hash = ?

                            WHERE id_usuario = ?
                        ");


                    $stmtDni->execute([
                        $nuevoHash,
                        $idUsuario
                    ]);


                    flash(
                        'success',
                        'Tu DNI de acceso fue actualizado correctamente.'
                    );


                    header(
                        'Location: '
                        . url(
                            'tecnico/perfil.php'
                        )
                    );

                    exit;


                } catch (Throwable $e) {

                    error_log(
                        'Error cambiando DNI: '
                        .
                        $e->getMessage()
                    );


                    $error =
                        'No se pudo actualizar el DNI.';
                }
            }
        }
    }
}


// ============================================================
// RECARGAR USUARIO
// ============================================================

$stmtUsuario->execute([
    $idUsuario
]);


$usuario =
    $stmtUsuario->fetch(
        PDO::FETCH_ASSOC
    );


// ============================================================
// ESTADÍSTICAS DEL TÉCNICO
// ============================================================

$stmtStats = $conexion->prepare("
    SELECT

        (
            SELECT COUNT(*)

            FROM intervenciones i

            WHERE
                i.id_tecnico = ?
        ) AS total_intervenciones,

        (
            SELECT COUNT(*)

            FROM intervenciones i

            WHERE
                i.id_tecnico = ?

            AND
                YEAR(i.fecha_intervencion)
                =
                YEAR(CURRENT_DATE())

            AND
                MONTH(i.fecha_intervencion)
                =
                MONTH(CURRENT_DATE())
        ) AS intervenciones_mes,

        (
            SELECT COUNT(
                DISTINCT sa.id_solicitud
            )

            FROM solicitudes_asignaciones sa

            INNER JOIN solicitudes s

                ON s.id_solicitud =
                   sa.id_solicitud

            WHERE
                sa.id_tecnico = ?

            AND
                sa.activo = 1

            AND
                s.estado NOT IN (
                    'Resuelta',
                    'Cerrada',
                    'Cancelada'
                )

        ) AS asignaciones_activas,

        (
            SELECT COUNT(
                DISTINCT sa.id_solicitud
            )

            FROM solicitudes_asignaciones sa

            INNER JOIN solicitudes s

                ON s.id_solicitud =
                   sa.id_solicitud

            WHERE
                sa.id_tecnico = ?

            AND
                s.estado IN (
                    'Resuelta',
                    'Cerrada'
                )

        ) AS trabajos_resueltos
");


$stmtStats->execute([

    $idUsuario,
    $idUsuario,
    $idUsuario,
    $idUsuario

]);


$stats =
    $stmtStats->fetch(
        PDO::FETCH_ASSOC
    );


$stats = [

    'total_intervenciones' =>
        (int)(
            $stats['total_intervenciones']
            ?? 0
        ),

    'intervenciones_mes' =>
        (int)(
            $stats['intervenciones_mes']
            ?? 0
        ),

    'asignaciones_activas' =>
        (int)(
            $stats['asignaciones_activas']
            ?? 0
        ),

    'trabajos_resueltos' =>
        (int)(
            $stats['trabajos_resueltos']
            ?? 0
        )

];


// ============================================================
// ÚLTIMAS INTERVENCIONES
// ============================================================

$stmtUltimas = $conexion->prepare("
    SELECT

        i.id_intervencion,
        i.id_solicitud,

        i.trabajo_realizado,
        i.diagnostico,

        i.pendiente,

        i.fecha_intervencion,

        s.titulo,
        s.estado,

        sec.nombre
            AS sector

    FROM intervenciones i

    INNER JOIN solicitudes s

        ON s.id_solicitud =
           i.id_solicitud

    LEFT JOIN sectores sec

        ON sec.id_sector =
           s.id_sector

    WHERE
        i.id_tecnico = ?

    ORDER BY
        i.fecha_intervencion DESC

    LIMIT 5
");


$stmtUltimas->execute([
    $idUsuario
]);


$ultimasIntervenciones =
    $stmtUltimas->fetchAll(
        PDO::FETCH_ASSOC
    );


// ============================================================
// INICIALES
// ============================================================

$iniciales =
    mb_strtoupper(

        mb_substr(
            $usuario['nombre'],
            0,
            1
        )

        .

        mb_substr(
            $usuario['apellido'],
            0,
            1
        )

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

.perfil-wrapper {

    max-width: 1350px;

    margin: 0 auto;

    padding: 5px 12px 50px;

}


/* ============================================================
   HERO
============================================================ */

.perfil-hero {

    position: relative;

    overflow: hidden;

    padding: 30px;

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


.perfil-hero::after {

    content: "";

    position: absolute;

    width: 320px;
    height: 320px;

    right: -120px;
    top: -165px;

    border-radius: 50%;

    background:
        rgba(255,255,255,.07);

}


.perfil-hero-content,
.perfil-hero-actions {

    position: relative;

    z-index: 2;

}


.perfil-hero h1 {

    margin: 0 0 6px;

    font-size: 29px;
    font-weight: 800;

}


.perfil-hero p {

    margin: 0;

    color:
        rgba(255,255,255,.80);

}


.perfil-hero-actions {

    display: flex;

    justify-content: flex-end;

}


.btn-volver {

    display: inline-flex;

    align-items: center;
    justify-content: center;

    gap: 7px;

    padding: 10px 16px;

    border-radius: 10px;

    color: #760000;
    background: #FFFFFF;

    font-weight: 700;

    text-decoration: none;

}


.btn-volver:hover {

    color: #B12626;
    background: #F5F5F5;

}


/* ============================================================
   CARD
============================================================ */

.perfil-card {

    height: 100%;

    overflow: hidden;

    border:
        1px solid #ECECEC;

    border-radius: 18px;

    background: #FFFFFF;

    box-shadow:
        0 5px 19px
        rgba(0,0,0,.05);

}


.perfil-card-header {

    display: flex;

    align-items: center;
    justify-content: space-between;

    gap: 10px;

    padding: 18px 20px;

    border-bottom:
        1px solid #EEEEEE;

}


.perfil-card-header h5 {

    margin: 0;

    color: #760000;

    font-size: 16px;
    font-weight: 800;

}


.perfil-card-body {

    padding: 21px;

}


/* ============================================================
   PERFIL PRINCIPAL
============================================================ */

.profile-box {

    text-align: center;

}


.profile-avatar {

    width: 95px;
    height: 95px;

    display: flex;

    align-items: center;
    justify-content: center;

    margin:
        0 auto
        15px;

    border-radius: 50%;

    color: #FFFFFF;

    background:
        linear-gradient(
            135deg,
            #760000,
            #B12626
        );

    box-shadow:
        0 8px 20px
        rgba(118,0,0,.20);

    font-size: 30px;
    font-weight: 800;

}


.profile-name {

    color: #333333;

    font-size: 20px;
    font-weight: 800;

}


.profile-email {

    margin-top: 4px;

    color: #777777;

    font-size: 11px;

}


.profile-role {

    display: inline-flex;

    align-items: center;

    gap: 5px;

    margin-top: 10px;

    padding: 6px 10px;

    border-radius: 20px;

    color: #760000;

    background: #F8EAEA;

    font-size: 10px;
    font-weight: 800;

}


/* ============================================================
   DATOS
============================================================ */

.profile-info {

    margin-top: 22px;

    border-top:
        1px solid #EEEEEE;

}


.profile-info-item {

    display: flex;

    align-items: center;
    justify-content: space-between;

    gap: 10px;

    padding: 12px 0;

    border-bottom:
        1px solid #EEEEEE;

}


.profile-info-label {

    color: #888888;

    font-size: 10px;

}


.profile-info-value {

    color: #444444;

    text-align: right;

    font-size: 10px;
    font-weight: 700;

}


/* ============================================================
   STATS
============================================================ */

.stat-card {

    height: 100%;

    padding: 15px;

    border:
        1px solid #ECECEC;

    border-radius: 13px;

    background: #FAFAFA;

}


.stat-icon {

    width: 37px;
    height: 37px;

    display: flex;

    align-items: center;
    justify-content: center;

    margin-bottom: 9px;

    border-radius: 9px;

    color: #B12626;

    background: #F8EAEA;

}


.stat-number {

    color: #333333;

    font-size: 23px;
    font-weight: 800;

}


.stat-label {

    margin-top: 3px;

    color: #838383;

    font-size: 9px;
    font-weight: 700;

}


/* ============================================================
   FORMULARIOS
============================================================ */

.form-label {

    color: #444444;

    font-size: 11px;
    font-weight: 800;

}


.form-control {

    min-height: 44px;

    border-radius: 9px;

}


.form-control:focus {

    border-color: #B12626;

    box-shadow:
        0 0 0 .2rem
        rgba(177,38,38,.08);

}


.form-help {

    margin-top: 5px;

    color: #8D8D8D;

    font-size: 9px;

}


.btn-sanjo-profile {

    min-height: 45px;

    border: 0;

    border-radius: 9px;

    color: #FFFFFF;

    background:
        linear-gradient(
            135deg,
            #760000,
            #B12626
        );

    font-weight: 800;

}


.btn-sanjo-profile:hover {

    color: #FFFFFF;

    background: #760000;

}


/* ============================================================
   SEGURIDAD
============================================================ */

.security-box {

    padding: 14px;

    margin-bottom: 20px;

    border-left:
        4px solid #B12626;

    border-radius: 9px;

    color: #604A4A;

    background: #FFF5F5;

    font-size: 10px;
    line-height: 1.55;

}


/* ============================================================
   INTERVENCIONES
============================================================ */

.intervencion-item {

    position: relative;

    padding:
        0 0 18px
        22px;

    border-left:
        2px solid #EEEEEE;

}


.intervencion-item:last-child {

    padding-bottom: 0;

}


.intervencion-item::before {

    content: "";

    position: absolute;

    left: -6px;
    top: 3px;

    width: 10px;
    height: 10px;

    border-radius: 50%;

    background: #B12626;

}


.intervencion-ticket {

    color: #760000;

    font-size: 9px;
    font-weight: 800;

    text-decoration: none;

}


.intervencion-title {

    margin-top: 2px;

    color: #333333;

    font-size: 11px;
    font-weight: 800;

}


.intervencion-text {

    margin-top: 4px;

    color: #777777;

    font-size: 10px;
    line-height: 1.5;

}


.intervencion-meta {

    display: flex;

    flex-wrap: wrap;

    gap: 8px;

    margin-top: 5px;

    color: #999999;

    font-size: 9px;

}


/* ============================================================
   EMPTY
============================================================ */

.empty-state {

    padding: 25px 10px;

    text-align: center;

    color: #999999;

    font-size: 10px;

}


.empty-state i {

    display: block;

    margin-bottom: 7px;

    color: #CCCCCC;

    font-size: 33px;

}


/* ============================================================
   RESPONSIVE
============================================================ */

@media
(max-width: 767px) {

    .perfil-hero {

        padding: 22px 20px;

    }


    .perfil-hero h1 {

        font-size: 23px;

    }


    .perfil-hero-actions {

        justify-content: flex-start;

        margin-top: 18px;

    }


    .btn-volver {

        width: 100%;

    }

}

</style>


<div class="perfil-wrapper">


    <!-- =====================================================
         HERO
    ====================================================== -->

    <section class="perfil-hero">

        <div class="row align-items-center">


            <div class="col-lg-8">

                <div class="perfil-hero-content">

                    <h1>

                        <i class="bi bi-person-circle me-1"></i>

                        Mi perfil

                    </h1>


                    <p>

                        Administrá tus datos personales,
                        información de acceso y consultá
                        tu actividad técnica.

                    </p>

                </div>

            </div>


            <div class="col-lg-4">

                <div class="perfil-hero-actions">

                    <a
                        href="<?= url(
                            'tecnico/dashboard.php'
                        ) ?>"
                        class="btn-volver"
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

            <?= e(
                $error
            ) ?>

        </div>

    <?php endif; ?>


    <div class="row g-4">


        <!-- =================================================
             COLUMNA IZQUIERDA
        ================================================== -->

        <div class="col-lg-4">


            <!-- PERFIL -->

            <div class="perfil-card mb-4">

                <div class="perfil-card-body">


                    <div class="profile-box">


                        <div class="profile-avatar">

                            <?= e(
                                $iniciales
                            ) ?>

                        </div>


                        <div class="profile-name">

                            <?= e(
                                trim(
                                    $usuario['nombre']
                                    .
                                    ' '
                                    .
                                    $usuario['apellido']
                                )
                            ) ?>

                        </div>


                        <div class="profile-email">

                            <?= e(
                                $usuario['correo']
                            ) ?>

                        </div>


                        <div class="profile-role">

                            <i class="bi bi-person-gear"></i>

                            <?= e(
                                $usuario['rol']
                                === 'Tecnico'
                                    ? 'Técnico'
                                    : $usuario['rol']
                            ) ?>

                        </div>


                    </div>


                    <div class="profile-info">


                        <div class="profile-info-item">

                            <span class="profile-info-label">

                                Estado

                            </span>


                            <span class="profile-info-value">

                                <?php if (
                                    $usuario['estado']
                                    === 'Activo'
                                ): ?>

                                    <span class="text-success">

                                        <i class="bi bi-check-circle me-1"></i>

                                        Activo

                                    </span>

                                <?php else: ?>

                                    <span class="text-danger">

                                        Inactivo

                                    </span>

                                <?php endif; ?>

                            </span>

                        </div>


                        <div class="profile-info-item">

                            <span class="profile-info-label">

                                Último acceso

                            </span>


                            <span class="profile-info-value">

                                <?php if (
                                    !empty(
                                        $usuario[
                                            'ultimo_acceso'
                                        ]
                                    )
                                ): ?>

                                    <?= e(
                                        fechaArgentina(
                                            $usuario[
                                                'ultimo_acceso'
                                            ]
                                        )
                                    ) ?>

                                <?php else: ?>

                                    Sin registro

                                <?php endif; ?>

                            </span>

                        </div>


                        <div class="profile-info-item">

                            <span class="profile-info-label">

                                Usuario desde

                            </span>


                            <span class="profile-info-value">

                                <?= e(
                                    fechaArgentina(
                                        $usuario[
                                            'fecha_creacion'
                                        ]
                                        ?? null
                                    )
                                ) ?>

                            </span>

                        </div>


                        <div class="profile-info-item">

                            <span class="profile-info-label">

                                Última actualización

                            </span>


                            <span class="profile-info-value">

                                <?= e(
                                    fechaArgentina(
                                        $usuario[
                                            'fecha_actualizacion'
                                        ]
                                        ?? null
                                    )
                                ) ?>

                            </span>

                        </div>


                    </div>


                </div>

            </div>


            <!-- =============================================
                 ESTADÍSTICAS
            ============================================== -->

            <div class="perfil-card">

                <div class="perfil-card-header">

                    <h5>

                        <i class="bi bi-bar-chart me-2"></i>

                        Mi actividad

                    </h5>

                </div>


                <div class="perfil-card-body">


                    <div class="row g-3">


                        <div class="col-6">

                            <div class="stat-card">

                                <div class="stat-icon">

                                    <i class="bi bi-tools"></i>

                                </div>


                                <div class="stat-number">

                                    <?= $stats[
                                        'total_intervenciones'
                                    ] ?>

                                </div>


                                <div class="stat-label">

                                    Intervenciones

                                </div>

                            </div>

                        </div>


                        <div class="col-6">

                            <div class="stat-card">

                                <div class="stat-icon">

                                    <i class="bi bi-calendar3"></i>

                                </div>


                                <div class="stat-number">

                                    <?= $stats[
                                        'intervenciones_mes'
                                    ] ?>

                                </div>


                                <div class="stat-label">

                                    Este mes

                                </div>

                            </div>

                        </div>


                        <div class="col-6">

                            <div class="stat-card">

                                <div class="stat-icon">

                                    <i class="bi bi-person-check"></i>

                                </div>


                                <div class="stat-number">

                                    <?= $stats[
                                        'asignaciones_activas'
                                    ] ?>

                                </div>


                                <div class="stat-label">

                                    Asignaciones activas

                                </div>

                            </div>

                        </div>


                        <div class="col-6">

                            <div class="stat-card">

                                <div class="stat-icon">

                                    <i class="bi bi-check-circle"></i>

                                </div>


                                <div class="stat-number">

                                    <?= $stats[
                                        'trabajos_resueltos'
                                    ] ?>

                                </div>


                                <div class="stat-label">

                                    Resueltos / cerrados

                                </div>

                            </div>

                        </div>


                    </div>


                </div>

            </div>


        </div>


        <!-- =================================================
             COLUMNA DERECHA
        ================================================== -->

        <div class="col-lg-8">


            <!-- =============================================
                 DATOS PERSONALES
            ============================================== -->

            <div class="perfil-card mb-4">

                <div class="perfil-card-header">

                    <h5>

                        <i class="bi bi-person-lines-fill me-2"></i>

                        Datos personales

                    </h5>

                </div>


                <div class="perfil-card-body">


                    <form
                        method="POST"
                        action="<?= url(
                            'tecnico/perfil.php'
                        ) ?>"
                        data-prevent-double-submit
                    >

                        <?= csrfInput() ?>


                        <input
                            type="hidden"
                            name="accion"
                            value="actualizar_datos"
                        >


                        <div class="row g-3">


                            <!-- NOMBRE -->

                            <div class="col-md-6">

                                <label
                                    for="nombre"
                                    class="form-label"
                                >

                                    Nombre

                                </label>


                                <input
                                    type="text"
                                    name="nombre"
                                    id="nombre"
                                    class="form-control"
                                    maxlength="100"
                                    value="<?= e(
                                        $nombre
                                    ) ?>"
                                    required
                                >

                            </div>


                            <!-- APELLIDO -->

                            <div class="col-md-6">

                                <label
                                    for="apellido"
                                    class="form-label"
                                >

                                    Apellido

                                </label>


                                <input
                                    type="text"
                                    name="apellido"
                                    id="apellido"
                                    class="form-control"
                                    maxlength="100"
                                    value="<?= e(
                                        $apellido
                                    ) ?>"
                                    required
                                >

                            </div>


                            <!-- CORREO -->

                            <div class="col-12">

                                <label
                                    for="correo"
                                    class="form-label"
                                >

                                    Correo electrónico

                                </label>


                                <input
                                    type="email"
                                    name="correo"
                                    id="correo"
                                    class="form-control"
                                    maxlength="150"
                                    value="<?= e(
                                        $correo
                                    ) ?>"
                                    required
                                >


                                <div class="form-help">

                                    Este correo también se utiliza
                                    para ingresar al sistema.

                                </div>

                            </div>


                            <!-- TELÉFONO -->

                            <div class="col-12">

                                <label
                                    for="telefono"
                                    class="form-label"
                                >

                                    Teléfono / WhatsApp

                                </label>


                                <input
                                    type="tel"
                                    name="telefono"
                                    id="telefono"
                                    class="form-control"
                                    maxlength="30"
                                    placeholder="Ej.: 351 555-1234"
                                    value="<?= e(
                                        $telefono
                                    ) ?>"
                                >


                                <div class="form-help">

                                    Se usa para que los docentes
                                    puedan contactarte por WhatsApp
                                    y para que te lleguen avisos
                                    de nuevos tickets a tu celular.

                                </div>

                            </div>


                            <!-- ROL -->

                            <div class="col-md-6">

                                <label class="form-label">

                                    Rol

                                </label>


                                <input
                                    type="text"
                                    class="form-control"
                                    value="<?= e(
                                        $usuario['rol']
                                        === 'Tecnico'
                                            ? 'Técnico'
                                            : $usuario['rol']
                                    ) ?>"
                                    disabled
                                >

                            </div>


                            <!-- ESTADO -->

                            <div class="col-md-6">

                                <label class="form-label">

                                    Estado de la cuenta

                                </label>


                                <input
                                    type="text"
                                    class="form-control"
                                    value="<?= e(
                                        $usuario[
                                            'estado'
                                        ]
                                    ) ?>"
                                    disabled
                                >

                            </div>


                        </div>


                        <button
                            type="submit"
                            class="btn btn-sanjo-profile mt-4"
                            data-loading-text="Guardando..."
                        >

                            <i class="bi bi-floppy me-1"></i>

                            Guardar cambios

                        </button>


                    </form>


                </div>

            </div>


            <!-- =============================================
                 SEGURIDAD
            ============================================== -->

            <div class="perfil-card mb-4">

                <div class="perfil-card-header">

                    <h5>

                        <i class="bi bi-shield-lock me-2"></i>

                        Seguridad

                    </h5>

                </div>


                <div class="perfil-card-body">


                    <div class="security-box">

                        <i class="bi bi-info-circle me-1"></i>

                        El DNI funciona como credencial
                        de acceso al sistema y se almacena
                        utilizando <strong>password_hash()</strong>.

                        El sistema nunca puede mostrar
                        el DNI actual una vez almacenado.

                    </div>


                    <form
                        method="POST"
                        action="<?= url(
                            'tecnico/perfil.php'
                        ) ?>"
                        data-prevent-double-submit
                    >

                        <?= csrfInput() ?>


                        <input
                            type="hidden"
                            name="accion"
                            value="cambiar_dni"
                        >


                        <div class="row g-3">


                            <!-- DNI ACTUAL -->

                            <div class="col-12">

                                <label
                                    for="dni_actual"
                                    class="form-label"
                                >

                                    DNI actual

                                </label>


                                <div class="input-group">

                                    <input
                                        type="password"
                                        name="dni_actual"
                                        id="dni_actual"
                                        class="form-control"
                                        inputmode="numeric"
                                        maxlength="9"
                                        autocomplete="current-password"
                                        data-only-numbers
                                        required
                                    >


                                    <button
                                        type="button"
                                        class="btn btn-outline-secondary"
                                        data-toggle-password="#dni_actual"
                                    >

                                        <i class="bi bi-eye"></i>

                                    </button>

                                </div>

                            </div>


                            <!-- NUEVO DNI -->

                            <div class="col-md-6">

                                <label
                                    for="nuevo_dni"
                                    class="form-label"
                                >

                                    Nuevo DNI

                                </label>


                                <input
                                    type="password"
                                    name="nuevo_dni"
                                    id="nuevo_dni"
                                    class="form-control"
                                    inputmode="numeric"
                                    maxlength="9"
                                    autocomplete="new-password"
                                    data-only-numbers
                                    required
                                >

                            </div>


                            <!-- CONFIRMAR -->

                            <div class="col-md-6">

                                <label
                                    for="confirmar_dni"
                                    class="form-label"
                                >

                                    Repetir nuevo DNI

                                </label>


                                <input
                                    type="password"
                                    name="confirmar_dni"
                                    id="confirmar_dni"
                                    class="form-control"
                                    inputmode="numeric"
                                    maxlength="9"
                                    autocomplete="new-password"
                                    data-only-numbers
                                    required
                                >

                            </div>


                        </div>


                        <button
                            type="submit"
                            class="btn btn-sanjo-profile mt-4"
                            data-loading-text="Actualizando..."
                        >

                            <i class="bi bi-key me-1"></i>

                            Cambiar DNI de acceso

                        </button>


                    </form>


                </div>

            </div>


            <!-- =============================================
                 ÚLTIMAS INTERVENCIONES
            ============================================== -->

            <div class="perfil-card">

                <div class="perfil-card-header">

                    <h5>

                        <i class="bi bi-clock-history me-2"></i>

                        Últimas intervenciones

                    </h5>


                    <a
                        href="<?= url(
                            'tecnico/intervenciones.php'
                        ) ?>"
                        class="small text-decoration-none"
                        style="color:#760000;font-weight:700;"
                    >

                        Ver historial

                    </a>

                </div>


                <div class="perfil-card-body">


                    <?php if (
                        empty(
                            $ultimasIntervenciones
                        )
                    ): ?>


                        <div class="empty-state">

                            <i class="bi bi-tools"></i>

                            Todavía no registraste intervenciones.

                        </div>


                    <?php else: ?>


                        <?php foreach (
                            $ultimasIntervenciones
                            as $intervencion
                        ): ?>


                            <div class="intervencion-item">


                                <a
                                    href="<?= url(
                                        'ver_solicitud.php?id='
                                        .
                                        (int)$intervencion[
                                            'id_solicitud'
                                        ]
                                    ) ?>"
                                    class="intervencion-ticket"
                                >

                                    <?= e(
                                        numeroTicket(
                                            (int)$intervencion[
                                                'id_solicitud'
                                            ]
                                        )
                                    ) ?>

                                </a>


                                <div class="intervencion-title">

                                    <?= e(
                                        $intervencion[
                                            'titulo'
                                        ]
                                    ) ?>

                                </div>


                                <?php

                                $detalleIntervencion =
                                    !empty(
                                        $intervencion[
                                            'trabajo_realizado'
                                        ]
                                    )
                                        ?
                                        $intervencion[
                                            'trabajo_realizado'
                                        ]
                                        :
                                        $intervencion[
                                            'diagnostico'
                                        ];

                                ?>


                                <?php if (
                                    !empty(
                                        $detalleIntervencion
                                    )
                                ): ?>

                                    <div class="intervencion-text">

                                        <?= e(
                                            $detalleIntervencion
                                        ) ?>

                                    </div>

                                <?php endif; ?>


                                <div class="intervencion-meta">


                                    <span>

                                        <i class="bi bi-calendar3 me-1"></i>

                                        <?= e(
                                            fechaArgentina(
                                                $intervencion[
                                                    'fecha_intervencion'
                                                ]
                                            )
                                        ) ?>

                                    </span>


                                    <?php if (
                                        !empty(
                                            $intervencion[
                                                'sector'
                                            ]
                                        )
                                    ): ?>

                                        <span>

                                            <i class="bi bi-geo-alt me-1"></i>

                                            <?= e(
                                                $intervencion[
                                                    'sector'
                                                ]
                                            ) ?>

                                        </span>

                                    <?php endif; ?>


                                    <?php if (
                                        (int)$intervencion[
                                            'pendiente'
                                        ] === 1
                                    ): ?>

                                        <span class="text-warning">

                                            <i class="bi bi-hourglass-split me-1"></i>

                                            Pendiente

                                        </span>

                                    <?php else: ?>

                                        <span class="text-success">

                                            <i class="bi bi-check-circle me-1"></i>

                                            Realizada

                                        </span>

                                    <?php endif; ?>


                                </div>


                            </div>


                        <?php endforeach; ?>


                    <?php endif; ?>


                </div>

            </div>


        </div>


    </div>


</div>


<?php

require_once __DIR__
    . '/../includes/footer.php';

?>