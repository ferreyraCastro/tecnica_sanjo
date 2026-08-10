<?php
// ============================================================
// COLEGIO SAN JOSÉ
// Sistema de Gestión Técnica
// Archivo: /tecnica/admin/horarios.php
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
// CONFIGURACIÓN
// ============================================================

$diasPermitidos = [
    'Lunes',
    'Martes',
    'Miercoles',
    'Jueves',
    'Viernes',
    'Sabado'
];

$tiposPermitidos = [
    'Informatica',
    'Mantenimiento'
];


// ============================================================
// VARIABLES
// ============================================================

$error = '';

$editarHorario = null;


// ============================================================
// FUNCIÓN AUXILIAR PARA REDIRECCIONAR
// ============================================================

function volverHorariosAdmin(): never
{
    header(
        'Location: ' .
        url('admin/horarios.php')
    );

    exit;
}


// ============================================================
// PROCESAR ACCIONES POST
// ============================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ========================================================
    // VALIDAR CSRF
    // ========================================================

    if (
        !validarCsrf(
            $_POST['csrf_token'] ?? ''
        )
    ) {

        flash(
            'error',
            'La sesión del formulario expiró. Intentá nuevamente.'
        );

        volverHorariosAdmin();
    }


    $accion =
        limpiarTexto(
            $_POST['accion'] ?? ''
        );


    // ========================================================
    // CREAR / EDITAR HORARIO
    // ========================================================

    if (
        $accion === 'guardar'
    ) {

        $idHorario =
            (int)(
                $_POST['id_horario']
                ?? 0
            );

        $tipo =
            limpiarTexto(
                $_POST['tipo']
                ?? ''
            );

        $dia =
            limpiarTexto(
                $_POST['dia']
                ?? ''
            );

        $horaDesde =
            limpiarTexto(
                $_POST['hora_desde']
                ?? ''
            );

        $horaHasta =
            limpiarTexto(
                $_POST['hora_hasta']
                ?? ''
            );

        $responsable =
            limpiarTexto(
                $_POST['responsable']
                ?? ''
            );

        $observaciones =
            limpiarTexto(
                $_POST['observaciones']
                ?? ''
            );

        $activo =
            isset(
                $_POST['activo']
            )
                ? 1
                : 0;


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
                'Seleccioná un tipo de mantenimiento válido.';

        } elseif (
            !in_array(
                $dia,
                $diasPermitidos,
                true
            )
        ) {

            $error =
                'Seleccioná un día válido.';

        } elseif (
            !preg_match(
                '/^\d{2}:\d{2}$/',
                $horaDesde
            )
        ) {

            $error =
                'Ingresá correctamente la hora de inicio.';

        } elseif (
            !preg_match(
                '/^\d{2}:\d{2}$/',
                $horaHasta
            )
        ) {

            $error =
                'Ingresá correctamente la hora de finalización.';

        } elseif (
            strtotime($horaHasta)
            <=
            strtotime($horaDesde)
        ) {

            $error =
                'La hora de finalización debe ser posterior a la hora de inicio.';

        } elseif (
            mb_strlen(
                $responsable
            ) > 150
        ) {

            $error =
                'El responsable no puede superar los 150 caracteres.';

        } elseif (
            mb_strlen(
                $observaciones
            ) > 500
        ) {

            $error =
                'Las observaciones no pueden superar los 500 caracteres.';
        }


        // ====================================================
        // VALIDAR SUPERPOSICIÓN
        //
        // Evita que existan dos horarios activos del mismo
        // tipo y día que se superpongan.
        // ====================================================

        if (
            $error === ''
            &&
            $activo === 1
        ) {

            $sqlSolapamiento = "
                SELECT COUNT(*)

                FROM horarios_mantenimiento

                WHERE tipo = ?

                AND dia = ?

                AND activo = 1

                AND hora_desde < ?

                AND hora_hasta > ?
            ";

            $paramsSolapamiento = [
                $tipo,
                $dia,
                $horaHasta . ':00',
                $horaDesde . ':00'
            ];


            if ($idHorario > 0) {

                $sqlSolapamiento .= "
                    AND id_horario <> ?
                ";

                $paramsSolapamiento[] =
                    $idHorario;
            }


            $stmtSolapamiento =
                $conexion->prepare(
                    $sqlSolapamiento
                );

            $stmtSolapamiento->execute(
                $paramsSolapamiento
            );


            if (
                (int)$stmtSolapamiento->fetchColumn()
                > 0
            ) {

                $error =
                    'Ya existe otro horario activo del mismo tipo '
                    . 'que se superpone con el horario seleccionado.';
            }
        }


        // ====================================================
        // GUARDAR
        // ====================================================

        if ($error === '') {

            try {

                if ($idHorario > 0) {

                    // ========================================
                    // EDITAR
                    // ========================================

                    $stmt =
                        $conexion->prepare("
                            UPDATE horarios_mantenimiento

                            SET
                                tipo = ?,
                                dia = ?,
                                hora_desde = ?,
                                hora_hasta = ?,
                                responsable = ?,
                                observaciones = ?,
                                activo = ?

                            WHERE id_horario = ?
                        ");


                    $stmt->execute([
                        $tipo,
                        $dia,
                        $horaDesde . ':00',
                        $horaHasta . ':00',
                        $responsable !== ''
                            ? $responsable
                            : null,
                        $observaciones !== ''
                            ? $observaciones
                            : null,
                        $activo,
                        $idHorario
                    ]);


                    flash(
                        'success',
                        'El horario fue actualizado correctamente.'
                    );


                } else {

                    // ========================================
                    // CREAR
                    // ========================================

                    $stmt =
                        $conexion->prepare("
                            INSERT INTO horarios_mantenimiento
                            (
                                tipo,
                                dia,
                                hora_desde,
                                hora_hasta,
                                responsable,
                                observaciones,
                                activo
                            )
                            VALUES
                            (
                                ?,
                                ?,
                                ?,
                                ?,
                                ?,
                                ?,
                                ?
                            )
                        ");


                    $stmt->execute([
                        $tipo,
                        $dia,
                        $horaDesde . ':00',
                        $horaHasta . ':00',
                        $responsable !== ''
                            ? $responsable
                            : null,
                        $observaciones !== ''
                            ? $observaciones
                            : null,
                        $activo
                    ]);


                    flash(
                        'success',
                        'El horario fue agregado correctamente.'
                    );
                }


                volverHorariosAdmin();


            } catch (Throwable $e) {

                error_log(
                    'Error horarios admin: '
                    . $e->getMessage()
                );

                $error =
                    'No se pudo guardar el horario.';
            }
        }


        // Si hubo error mantenemos los datos cargados.

        $editarHorario = [

            'id_horario' =>
                $idHorario,

            'tipo' =>
                $tipo,

            'dia' =>
                $dia,

            'hora_desde' =>
                $horaDesde,

            'hora_hasta' =>
                $horaHasta,

            'responsable' =>
                $responsable,

            'observaciones' =>
                $observaciones,

            'activo' =>
                $activo

        ];
    }


    // ========================================================
    // ACTIVAR / DESACTIVAR
    // ========================================================

    elseif (
        $accion === 'estado'
    ) {

        $idHorario =
            (int)(
                $_POST['id_horario']
                ?? 0
            );


        if ($idHorario > 0) {

            try {

                $stmt =
                    $conexion->prepare("
                        UPDATE horarios_mantenimiento

                        SET activo =
                            CASE
                                WHEN activo = 1
                                THEN 0
                                ELSE 1
                            END

                        WHERE id_horario = ?
                    ");


                $stmt->execute([
                    $idHorario
                ]);


                flash(
                    'success',
                    'El estado del horario fue actualizado.'
                );


            } catch (Throwable $e) {

                error_log(
                    'Error cambiar horario: '
                    . $e->getMessage()
                );

                flash(
                    'error',
                    'No se pudo modificar el horario.'
                );
            }
        }


        volverHorariosAdmin();
    }


    // ========================================================
    // ELIMINAR
    // ========================================================

    elseif (
        $accion === 'eliminar'
    ) {

        $idHorario =
            (int)(
                $_POST['id_horario']
                ?? 0
            );


        if ($idHorario > 0) {

            try {

                $stmt =
                    $conexion->prepare("
                        DELETE FROM horarios_mantenimiento

                        WHERE id_horario = ?
                    ");


                $stmt->execute([
                    $idHorario
                ]);


                flash(
                    'success',
                    'El horario fue eliminado correctamente.'
                );


            } catch (Throwable $e) {

                error_log(
                    'Error eliminar horario: '
                    . $e->getMessage()
                );


                flash(
                    'error',
                    'No se pudo eliminar el horario.'
                );
            }
        }


        volverHorariosAdmin();
    }
}


// ============================================================
// EDITAR DESDE GET
// ============================================================

$idEditar =
    (int)(
        $_GET['editar']
        ?? 0
    );


if (
    $idEditar > 0
    &&
    $editarHorario === null
) {

    $stmtEditar =
        $conexion->prepare("
            SELECT *
            FROM horarios_mantenimiento

            WHERE id_horario = ?

            LIMIT 1
        ");


    $stmtEditar->execute([
        $idEditar
    ]);


    $horarioBD =
        $stmtEditar->fetch(
            PDO::FETCH_ASSOC
        );


    if ($horarioBD) {

        $editarHorario = [

            'id_horario' =>
                (int)$horarioBD[
                    'id_horario'
                ],

            'tipo' =>
                $horarioBD[
                    'tipo'
                ],

            'dia' =>
                $horarioBD[
                    'dia'
                ],

            'hora_desde' =>
                substr(
                    $horarioBD[
                        'hora_desde'
                    ],
                    0,
                    5
                ),

            'hora_hasta' =>
                substr(
                    $horarioBD[
                        'hora_hasta'
                    ],
                    0,
                    5
                ),

            'responsable' =>
                $horarioBD[
                    'responsable'
                ]
                ?? '',

            'observaciones' =>
                $horarioBD[
                    'observaciones'
                ]
                ?? '',

            'activo' =>
                (int)$horarioBD[
                    'activo'
                ]

        ];
    }
}


// ============================================================
// VALORES FORMULARIO
// ============================================================

$form = $editarHorario ?? [

    'id_horario' => 0,

    'tipo' => 'Informatica',

    'dia' => 'Lunes',

    'hora_desde' => '07:30',

    'hora_hasta' => '09:00',

    'responsable' => '',

    'observaciones' => '',

    'activo' => 1

];


// ============================================================
// CONSULTAR TODOS LOS HORARIOS
// ============================================================

$stmtHorarios =
    $conexion->query("
        SELECT *

        FROM horarios_mantenimiento

        ORDER BY

            CASE tipo

                WHEN 'Informatica'
                THEN 1

                WHEN 'Mantenimiento'
                THEN 2

                ELSE 3

            END,

            CASE dia

                WHEN 'Lunes'
                THEN 1

                WHEN 'Martes'
                THEN 2

                WHEN 'Miercoles'
                THEN 3

                WHEN 'Jueves'
                THEN 4

                WHEN 'Viernes'
                THEN 5

                WHEN 'Sabado'
                THEN 6

                ELSE 7

            END,

            hora_desde ASC
    ");


$horarios =
    $stmtHorarios->fetchAll(
        PDO::FETCH_ASSOC
    );


// ============================================================
// ESTADÍSTICAS
// ============================================================

$stmtStats =
    $conexion->query("
        SELECT

            COUNT(*) AS total,

            SUM(
                CASE
                    WHEN activo = 1
                    THEN 1
                    ELSE 0
                END
            ) AS activos,

            SUM(
                CASE
                    WHEN tipo = 'Informatica'
                    AND activo = 1
                    THEN 1
                    ELSE 0
                END
            ) AS informatica,

            SUM(
                CASE
                    WHEN tipo = 'Mantenimiento'
                    AND activo = 1
                    THEN 1
                    ELSE 0
                END
            ) AS mantenimiento

        FROM horarios_mantenimiento
    ");


$stats =
    $stmtStats->fetch(
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

.admin-horarios-wrapper {

    max-width: 1450px;

    margin: 0 auto;

    padding:
        5px 12px 45px;

}


/* ============================================================
   HERO
============================================================ */

.horarios-admin-hero {

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


.horarios-admin-hero::after {

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


.horarios-admin-hero h1 {

    margin:
        0 0 7px;

    font-size: 28px;

    font-weight: 800;

}


.horarios-admin-hero p {

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

    background: #FFFFFF;

    color: #760000;

    font-weight: 700;

    text-decoration: none;

}


.btn-volver:hover {

    color: #B12626;

    background: #F4F4F4;

}


/* ============================================================
   ESTADÍSTICAS
============================================================ */

.stat-card {

    height: 100%;

    padding: 18px;

    border:
        1px solid #ECECEC;

    border-radius: 16px;

    background: #FFFFFF;

    box-shadow:
        0 4px 16px
        rgba(0,0,0,.04);

}


.stat-icon {

    width: 43px;

    height: 43px;

    border-radius: 11px;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 18px;

    margin-bottom: 10px;

}


.stat-number {

    color: #333333;

    font-size: 27px;

    font-weight: 800;

}


.stat-label {

    color: #777777;

    font-size: 11px;

    font-weight: 700;

    margin-top: 3px;

}


.icon-total {

    color: #760000;

    background: #F2E4E4;

}


.icon-active {

    color: #198754;

    background: #E1F4E8;

}


.icon-it {

    color: #0D6EFD;

    background: #E8F1FF;

}


.icon-maintenance {

    color: #B12626;

    background: #FFE5E5;

}


/* ============================================================
   CARDS
============================================================ */

.admin-card {

    background: #FFFFFF;

    border:
        1px solid #ECECEC;

    border-radius: 18px;

    overflow: hidden;

    box-shadow:
        0 5px 20px
        rgba(0,0,0,.05);

}


.admin-card-header {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 12px;

    padding:
        18px 20px;

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

    padding: 21px;

}


/* ============================================================
   FORM
============================================================ */

.form-label {

    color: #4D4D4D;

    font-size: 12px;

    font-weight: 700;

}


.form-control,
.form-select {

    min-height: 45px;

    border-radius: 9px;

}


.form-control:focus,
.form-select:focus {

    border-color: #B12626;

    box-shadow:
        0 0 0
        .2rem
        rgba(177,38,38,.08);

}


textarea.form-control {

    min-height: 95px;

}


.btn-guardar {

    background:
        linear-gradient(
            135deg,
            #760000,
            #B12626
        );

    color: #FFFFFF;

    border: none;

    min-height: 45px;

    border-radius: 9px;

    font-weight: 700;

}


.btn-guardar:hover {

    background: #760000;

    color: #FFFFFF;

}


.btn-cancelar {

    min-height: 45px;

    border:
        1px solid #DADADA;

    background: #FFFFFF;

    color: #555555;

    border-radius: 9px;

}


/* ============================================================
   TABLA
============================================================ */

.table {

    margin-bottom: 0;

}


.table thead th {

    padding:
        13px 15px;

    background: #FAFAFA;

    color: #555555;

    text-transform: uppercase;

    font-size: 10px;

    letter-spacing: .3px;

    white-space: nowrap;

}


.table tbody td {

    padding:
        14px 15px;

    vertical-align: middle;

    border-color: #EEEEEE;

}


.tipo-info {

    display: flex;

    align-items: center;

    gap: 9px;

}


.tipo-icon {

    min-width: 35px;

    width: 35px;

    height: 35px;

    border-radius: 9px;

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

}


.tipo-info strong {

    color: #444444;

    font-size: 12px;

}


.dia {

    color: #760000;

    font-weight: 800;

}


.hora {

    white-space: nowrap;

    font-weight: 700;

    color: #333333;

}


.responsable {

    color: #555555;

    font-size: 12px;

}


.observaciones {

    max-width: 300px;

    color: #777777;

    font-size: 11px;

}


/* ============================================================
   BADGE
============================================================ */

.estado-activo {

    display: inline-flex;

    align-items: center;

    gap: 5px;

    padding:
        5px 8px;

    border-radius: 20px;

    background: #E1F4E8;

    color: #198754;

    font-size: 10px;

    font-weight: 700;

}


.estado-inactivo {

    display: inline-flex;

    align-items: center;

    gap: 5px;

    padding:
        5px 8px;

    border-radius: 20px;

    background: #EEEEEE;

    color: #777777;

    font-size: 10px;

    font-weight: 700;

}


/* ============================================================
   ACCIONES
============================================================ */

.actions {

    display: flex;

    gap: 5px;

    justify-content: center;

}


.action-button {

    width: 34px;

    height: 34px;

    border-radius: 8px;

    display: inline-flex;

    align-items: center;

    justify-content: center;

    text-decoration: none;

    border: none;

}


.action-edit {

    color: #0D6EFD;

    background: #EEF5FF;

}


.action-edit:hover {

    color: #FFFFFF;

    background: #0D6EFD;

}


.action-state {

    color: #916C00;

    background: #FFF5D9;

}


.action-state:hover {

    color: #FFFFFF;

    background: #D29A00;

}


.action-delete {

    color: #B12626;

    background: #FFF0F0;

}


.action-delete:hover {

    color: #FFFFFF;

    background: #B12626;

}


/* ============================================================
   EMPTY
============================================================ */

.empty {

    padding:
        40px 20px;

    color: #888888;

    text-align: center;

}


.empty i {

    display: block;

    font-size: 40px;

    color: #D0D0D0;

    margin-bottom: 8px;

}


/* ============================================================
   RESPONSIVE
============================================================ */

@media
(max-width: 767px) {

    .horarios-admin-hero {

        padding:
            22px 20px;

    }


    .horarios-admin-hero h1 {

        font-size: 23px;

    }


    .hero-action {

        margin-top: 18px;

    }


    .btn-volver {

        width: 100%;

        justify-content: center;

    }

}

</style>


<div class="admin-horarios-wrapper">


    <!-- =====================================================
         HERO
    ====================================================== -->

    <section class="horarios-admin-hero">

        <div class="row align-items-center">

            <div class="col-lg-8">

                <div class="hero-content">

                    <h1>

                        <i class="bi bi-calendar-week me-1"></i>

                        Administración de horarios

                    </h1>

                    <p>

                        Configurá los horarios publicados
                        de Informática y Mantenimiento general.

                    </p>

                </div>

            </div>


            <div
                class="col-lg-4
                       text-lg-end
                       hero-action"
            >

                <a
                    href="<?= url(
                        'admin/dashboard.php'
                    ) ?>"
                    class="btn-volver"
                >

                    <i class="bi bi-arrow-left"></i>

                    Panel administrador

                </a>

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


    <!-- =====================================================
         ESTADÍSTICAS
    ====================================================== -->

    <div class="row g-3 mb-4">


        <div class="col-6 col-lg-3">

            <div class="stat-card">

                <div class="stat-icon icon-total">

                    <i class="bi bi-calendar3"></i>

                </div>

                <div class="stat-number">

                    <?= (int)(
                        $stats['total']
                        ?? 0
                    ) ?>

                </div>

                <div class="stat-label">

                    Horarios cargados

                </div>

            </div>

        </div>


        <div class="col-6 col-lg-3">

            <div class="stat-card">

                <div class="stat-icon icon-active">

                    <i class="bi bi-check-circle"></i>

                </div>

                <div class="stat-number">

                    <?= (int)(
                        $stats['activos']
                        ?? 0
                    ) ?>

                </div>

                <div class="stat-label">

                    Horarios activos

                </div>

            </div>

        </div>


        <div class="col-6 col-lg-3">

            <div class="stat-card">

                <div class="stat-icon icon-it">

                    <i class="bi bi-pc-display"></i>

                </div>

                <div class="stat-number">

                    <?= (int)(
                        $stats['informatica']
                        ?? 0
                    ) ?>

                </div>

                <div class="stat-label">

                    Informática

                </div>

            </div>

        </div>


        <div class="col-6 col-lg-3">

            <div class="stat-card">

                <div class="stat-icon icon-maintenance">

                    <i class="bi bi-tools"></i>

                </div>

                <div class="stat-number">

                    <?= (int)(
                        $stats['mantenimiento']
                        ?? 0
                    ) ?>

                </div>

                <div class="stat-label">

                    Mantenimiento

                </div>

            </div>

        </div>


    </div>


    <!-- =====================================================
         FORMULARIO
    ====================================================== -->

    <div class="row g-4">


        <div class="col-xl-4">

            <div class="admin-card">

                <div class="admin-card-header">

                    <h5>

                        <i class="bi <?= (int)$form['id_horario'] > 0
                            ? 'bi-pencil-square'
                            : 'bi-plus-circle'
                        ?> me-2"></i>

                        <?= (int)$form['id_horario'] > 0
                            ? 'Editar horario'
                            : 'Agregar horario'
                        ?>

                    </h5>

                </div>


                <div class="admin-card-body">

                    <form
                        method="POST"
                        action="<?= url(
                            'admin/horarios.php'
                        ) ?>"
                    >

                        <?= csrfInput() ?>


                        <input
                            type="hidden"
                            name="accion"
                            value="guardar"
                        >


                        <input
                            type="hidden"
                            name="id_horario"
                            value="<?= (int)$form[
                                'id_horario'
                            ] ?>"
                        >


                        <!-- TIPO -->

                        <div class="mb-3">

                            <label
                                for="tipo"
                                class="form-label"
                            >
                                Área
                            </label>

                            <select
                                name="tipo"
                                id="tipo"
                                class="form-select"
                                required
                            >

                                <option
                                    value="Informatica"
                                    <?= $form['tipo']
                                        === 'Informatica'
                                        ? 'selected'
                                        : ''
                                    ?>
                                >
                                    Informática
                                </option>

                                <option
                                    value="Mantenimiento"
                                    <?= $form['tipo']
                                        === 'Mantenimiento'
                                        ? 'selected'
                                        : ''
                                    ?>
                                >
                                    Mantenimiento general
                                </option>

                            </select>

                        </div>


                        <!-- DÍA -->

                        <div class="mb-3">

                            <label
                                for="dia"
                                class="form-label"
                            >
                                Día
                            </label>

                            <select
                                name="dia"
                                id="dia"
                                class="form-select"
                                required
                            >

                                <?php foreach (
                                    $diasPermitidos
                                    as $dia
                                ): ?>

                                    <option
                                        value="<?= e($dia) ?>"
                                        <?= $form['dia']
                                            === $dia
                                            ? 'selected'
                                            : ''
                                        ?>
                                    >

                                        <?= e(
                                            $dia === 'Miercoles'
                                            ? 'Miércoles'
                                            : $dia
                                        ) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <!-- HORAS -->

                        <div class="row g-3">

                            <div class="col-6">

                                <label
                                    for="hora_desde"
                                    class="form-label"
                                >
                                    Desde
                                </label>

                                <input
                                    type="time"
                                    name="hora_desde"
                                    id="hora_desde"
                                    class="form-control"
                                    value="<?= e(
                                        $form[
                                            'hora_desde'
                                        ]
                                    ) ?>"
                                    required
                                >

                            </div>


                            <div class="col-6">

                                <label
                                    for="hora_hasta"
                                    class="form-label"
                                >
                                    Hasta
                                </label>

                                <input
                                    type="time"
                                    name="hora_hasta"
                                    id="hora_hasta"
                                    class="form-control"
                                    value="<?= e(
                                        $form[
                                            'hora_hasta'
                                        ]
                                    ) ?>"
                                    required
                                >

                            </div>

                        </div>


                        <!-- RESPONSABLE -->

                        <div class="mt-3">

                            <label
                                for="responsable"
                                class="form-label"
                            >
                                Responsable
                            </label>

                            <input
                                type="text"
                                name="responsable"
                                id="responsable"
                                class="form-control"
                                maxlength="150"
                                value="<?= e(
                                    $form[
                                        'responsable'
                                    ]
                                ) ?>"
                                placeholder="Ej.: Área de Informática"
                            >

                        </div>


                        <!-- OBSERVACIONES -->

                        <div class="mt-3">

                            <label
                                for="observaciones"
                                class="form-label"
                            >
                                Observaciones
                            </label>

                            <textarea
                                name="observaciones"
                                id="observaciones"
                                class="form-control"
                                maxlength="500"
                                placeholder="Información adicional..."
                            ><?= e(
                                $form[
                                    'observaciones'
                                ]
                            ) ?></textarea>

                        </div>


                        <!-- ACTIVO -->

                        <div class="form-check form-switch mt-3">

                            <input
                                class="form-check-input"
                                type="checkbox"
                                role="switch"
                                name="activo"
                                id="activo"
                                value="1"
                                <?= (int)$form['activo'] === 1
                                    ? 'checked'
                                    : ''
                                ?>
                            >

                            <label
                                class="form-check-label"
                                for="activo"
                            >
                                Publicar este horario
                            </label>

                        </div>


                        <!-- BOTONES -->

                        <div class="d-grid gap-2 mt-4">

                            <button
                                type="submit"
                                class="btn btn-guardar"
                            >

                                <i class="bi bi-floppy me-1"></i>

                                <?= (int)$form['id_horario'] > 0
                                    ? 'Guardar cambios'
                                    : 'Agregar horario'
                                ?>

                            </button>


                            <?php if (
                                (int)$form['id_horario']
                                > 0
                            ): ?>

                                <a
                                    href="<?= url(
                                        'admin/horarios.php'
                                    ) ?>"
                                    class="btn btn-cancelar"
                                >

                                    Cancelar edición

                                </a>

                            <?php endif; ?>

                        </div>


                    </form>

                </div>

            </div>

        </div>


        <!-- =================================================
             LISTADO
        ================================================== -->

        <div class="col-xl-8">

            <div class="admin-card">

                <div class="admin-card-header">

                    <h5>

                        <i class="bi bi-list-ul me-2"></i>

                        Horarios configurados

                    </h5>


                    <a
                        href="<?= url(
                            'horarios.php'
                        ) ?>"
                        target="_blank"
                        class="btn btn-sm btn-outline-secondary"
                    >

                        <i class="bi bi-eye me-1"></i>

                        Vista pública

                    </a>

                </div>


                <?php if (
                    empty(
                        $horarios
                    )
                ): ?>

                    <div class="empty">

                        <i class="bi bi-calendar-x"></i>

                        No hay horarios configurados.

                    </div>


                <?php else: ?>

                    <div class="table-responsive">

                        <table class="table">

                            <thead>

                                <tr>

                                    <th>
                                        Área
                                    </th>

                                    <th>
                                        Día
                                    </th>

                                    <th>
                                        Horario
                                    </th>

                                    <th>
                                        Responsable
                                    </th>

                                    <th>
                                        Observaciones
                                    </th>

                                    <th>
                                        Estado
                                    </th>

                                    <th class="text-center">
                                        Acciones
                                    </th>

                                </tr>

                            </thead>


                            <tbody>


                                <?php foreach (
                                    $horarios
                                    as $horario
                                ): ?>

                                    <tr>


                                        <!-- ÁREA -->

                                        <td>

                                            <div class="tipo-info">

                                                <div class="tipo-icon">

                                                    <i class="<?= e(
                                                        iconoTipo(
                                                            $horario[
                                                                'tipo'
                                                            ]
                                                        )
                                                    ) ?>"></i>

                                                </div>

                                                <strong>

                                                    <?= e(
                                                        nombreTipo(
                                                            $horario[
                                                                'tipo'
                                                            ]
                                                        )
                                                    ) ?>

                                                </strong>

                                            </div>

                                        </td>


                                        <!-- DÍA -->

                                        <td>

                                            <span class="dia">

                                                <?= e(
                                                    $horario[
                                                        'dia'
                                                    ]
                                                    === 'Miercoles'
                                                    ? 'Miércoles'
                                                    : $horario[
                                                        'dia'
                                                    ]
                                                ) ?>

                                            </span>

                                        </td>


                                        <!-- HORA -->

                                        <td>

                                            <span class="hora">

                                                <?= e(
                                                    horaCorta(
                                                        $horario[
                                                            'hora_desde'
                                                        ]
                                                    )
                                                ) ?>

                                                -

                                                <?= e(
                                                    horaCorta(
                                                        $horario[
                                                            'hora_hasta'
                                                        ]
                                                    )
                                                ) ?>

                                            </span>

                                        </td>


                                        <!-- RESPONSABLE -->

                                        <td>

                                            <span class="responsable">

                                                <?= !empty(
                                                    $horario[
                                                        'responsable'
                                                    ]
                                                )
                                                    ? e(
                                                        $horario[
                                                            'responsable'
                                                        ]
                                                    )
                                                    : '-'
                                                ?>

                                            </span>

                                        </td>


                                        <!-- OBS -->

                                        <td>

                                            <div class="observaciones">

                                                <?= !empty(
                                                    $horario[
                                                        'observaciones'
                                                    ]
                                                )
                                                    ? e(
                                                        $horario[
                                                            'observaciones'
                                                        ]
                                                    )
                                                    : '-'
                                                ?>

                                            </div>

                                        </td>


                                        <!-- ESTADO -->

                                        <td>

                                            <?php if (
                                                (int)$horario[
                                                    'activo'
                                                ] === 1
                                            ): ?>

                                                <span class="estado-activo">

                                                    <i class="bi bi-check-circle"></i>

                                                    Activo

                                                </span>

                                            <?php else: ?>

                                                <span class="estado-inactivo">

                                                    <i class="bi bi-pause-circle"></i>

                                                    Inactivo

                                                </span>

                                            <?php endif; ?>

                                        </td>


                                        <!-- ACCIONES -->

                                        <td>

                                            <div class="actions">


                                                <!-- EDITAR -->

                                                <a
                                                    href="<?= url(
                                                        'admin/horarios.php?editar='
                                                        .
                                                        (int)$horario[
                                                            'id_horario'
                                                        ]
                                                    ) ?>"
                                                    class="action-button action-edit"
                                                    title="Editar"
                                                >

                                                    <i class="bi bi-pencil"></i>

                                                </a>


                                                <!-- ESTADO -->

                                                <form
                                                    method="POST"
                                                    action="<?= url(
                                                        'admin/horarios.php'
                                                    ) ?>"
                                                    class="m-0"
                                                >

                                                    <?= csrfInput() ?>

                                                    <input
                                                        type="hidden"
                                                        name="accion"
                                                        value="estado"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="id_horario"
                                                        value="<?= (int)$horario[
                                                            'id_horario'
                                                        ] ?>"
                                                    >

                                                    <button
                                                        type="submit"
                                                        class="action-button action-state"
                                                        title="<?= (int)$horario['activo'] === 1
                                                            ? 'Desactivar'
                                                            : 'Activar'
                                                        ?>"
                                                    >

                                                        <i class="bi <?= (int)$horario['activo'] === 1
                                                            ? 'bi-pause'
                                                            : 'bi-play'
                                                        ?>"></i>

                                                    </button>

                                                </form>


                                                <!-- ELIMINAR -->

                                                <form
                                                    method="POST"
                                                    action="<?= url(
                                                        'admin/horarios.php'
                                                    ) ?>"
                                                    class="m-0 form-eliminar"
                                                >

                                                    <?= csrfInput() ?>

                                                    <input
                                                        type="hidden"
                                                        name="accion"
                                                        value="eliminar"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="id_horario"
                                                        value="<?= (int)$horario[
                                                            'id_horario'
                                                        ] ?>"
                                                    >

                                                    <button
                                                        type="submit"
                                                        class="action-button action-delete"
                                                        title="Eliminar"
                                                    >

                                                        <i class="bi bi-trash"></i>

                                                    </button>

                                                </form>


                                            </div>

                                        </td>


                                    </tr>

                                <?php endforeach; ?>


                            </tbody>

                        </table>

                    </div>

                <?php endif; ?>


            </div>

        </div>


    </div>


</div>


<script>

// ============================================================
// CONFIRMAR ELIMINACIÓN
// ============================================================

document
    .querySelectorAll(
        '.form-eliminar'
    )
    .forEach(
        function(formulario) {

            formulario.addEventListener(
                'submit',
                function(evento) {

                    const confirmar =
                        confirm(
                            '¿Seguro que querés eliminar este horario?'
                        );

                    if (!confirmar) {

                        evento.preventDefault();

                    }

                }
            );

        }
    );

</script>


<?php

require_once __DIR__
    . '/../includes/footer.php';

?>