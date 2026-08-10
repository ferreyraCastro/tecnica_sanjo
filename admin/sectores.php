<?php
// ============================================================
// COLEGIO SAN JOSÉ
// Sistema de Gestión Técnica
// Archivo: /tecnica/admin/sectores.php
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

$tiposPermitidos = [
    'Aula',
    'Oficina',
    'Sala',
    'Patio',
    'Baño',
    'Laboratorio',
    'Biblioteca',
    'Otro'
];


// ============================================================
// VARIABLES
// ============================================================

$error = '';

$editarSector = null;


function volverSectoresAdmin(): never
{
    header(
        'Location: ' .
        url('admin/sectores.php')
    );

    exit;
}


// ============================================================
// PROCESAR ACCIONES POST
// ============================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        !validarCsrf(
            $_POST['csrf_token'] ?? ''
        )
    ) {

        flash(
            'error',
            'La sesión del formulario expiró. Intentá nuevamente.'
        );

        volverSectoresAdmin();
    }


    $accion =
        limpiarTexto(
            $_POST['accion'] ?? ''
        );


    // ========================================================
    // CREAR / EDITAR
    // ========================================================

    if ($accion === 'guardar') {

        $idSector =
            (int)(
                $_POST['id_sector']
                ?? 0
            );

        $nombre =
            limpiarTexto(
                $_POST['nombre']
                ?? ''
            );

        $tipo =
            limpiarTexto(
                $_POST['tipo']
                ?? ''
            );

        $descripcion =
            limpiarTexto(
                $_POST['descripcion']
                ?? ''
            );

        $activo =
            isset($_POST['activo'])
                ? 1
                : 0;


        // ====================================================
        // VALIDACIONES
        // ====================================================

        if ($nombre === '') {

            $error =
                'Ingresá el nombre del sector.';

        } elseif (mb_strlen($nombre) > 100) {

            $error =
                'El nombre no puede superar los 100 caracteres.';

        } elseif (
            !in_array(
                $tipo,
                $tiposPermitidos,
                true
            )
        ) {

            $error =
                'Seleccioná un tipo válido.';

        } elseif (mb_strlen($descripcion) > 255) {

            $error =
                'La descripción no puede superar los 255 caracteres.';
        }


        // ====================================================
        // NOMBRE DUPLICADO
        // ====================================================

        if ($error === '') {

            $stmtDuplicado =
                $conexion->prepare("
                    SELECT COUNT(*)
                    FROM sectores
                    WHERE nombre = ?
                    AND id_sector != ?
                ");

            $stmtDuplicado->execute([
                $nombre,
                $idSector
            ]);

            if ((int)$stmtDuplicado->fetchColumn() > 0) {

                $error =
                    'Ya existe un sector con ese nombre.';
            }
        }


        // ====================================================
        // GUARDAR
        // ====================================================

        if ($error === '') {

            $datos = [
                'nombre' => $nombre,
                'tipo' => $tipo,
                'descripcion' => $descripcion,
                'activo' => $activo
            ];

            if ($idSector > 0) {

                if (
                    actualizarSector(
                        $conexion,
                        $idSector,
                        $datos
                    )
                ) {

                    flash(
                        'success',
                        'El sector fue actualizado correctamente.'
                    );

                    volverSectoresAdmin();

                } else {

                    $error =
                        'No se pudo actualizar el sector.';
                }

            } else {

                if (
                    crearSector(
                        $conexion,
                        $datos
                    ) !== false
                ) {

                    flash(
                        'success',
                        'El sector fue agregado correctamente.'
                    );

                    volverSectoresAdmin();

                } else {

                    $error =
                        'No se pudo agregar el sector.';
                }
            }
        }


        $editarSector = [
            'id_sector' => $idSector,
            'nombre' => $nombre,
            'tipo' => $tipo,
            'descripcion' => $descripcion,
            'activo' => $activo
        ];
    }


    // ========================================================
    // ACTIVAR / DESACTIVAR
    // ========================================================

    elseif ($accion === 'estado') {

        $idSector =
            (int)(
                $_POST['id_sector']
                ?? 0
            );

        if ($idSector > 0) {

            $sectorActual =
                obtenerSector(
                    $conexion,
                    $idSector
                );

            if ($sectorActual) {

                $datos = $sectorActual;

                $datos['activo'] =
                    (int)$sectorActual['activo'] === 1
                        ? 0
                        : 1;

                actualizarSector(
                    $conexion,
                    $idSector,
                    $datos
                );

                flash(
                    'success',
                    'El estado del sector fue actualizado.'
                );
            }
        }

        volverSectoresAdmin();
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
    $editarSector === null
) {

    $sectorBD =
        obtenerSector(
            $conexion,
            $idEditar
        );

    if ($sectorBD) {

        $editarSector = [
            'id_sector' => (int)$sectorBD['id_sector'],
            'nombre' => $sectorBD['nombre'],
            'tipo' => $sectorBD['tipo'],
            'descripcion' => $sectorBD['descripcion'] ?? '',
            'activo' => (int)$sectorBD['activo']
        ];
    }
}


$form = $editarSector ?? [
    'id_sector' => 0,
    'nombre' => '',
    'tipo' => 'Aula',
    'descripcion' => '',
    'activo' => 1
];


// ============================================================
// LISTADO
// ============================================================

$sectores =
    $conexion->query("
        SELECT
            s.*,
            (
                SELECT COUNT(*)
                FROM solicitudes so
                WHERE so.id_sector = s.id_sector
            ) AS total_solicitudes
        FROM sectores s
        ORDER BY s.nombre ASC
    ")->fetchAll(PDO::FETCH_ASSOC);


$flash = obtenerFlash();


// ============================================================
// HEADER
// ============================================================

require_once __DIR__
    . '/../includes/header.php';

?>


<style>

.admin-sectores-wrapper {

    max-width: 1350px;

    margin: 0 auto;

    padding: 5px 12px 45px;

}


.sectores-admin-hero {

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


.sectores-admin-hero::after {

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


.sectores-admin-hero h1 {

    margin: 0 0 7px;

    font-size: 28px;

    font-weight: 800;

}


.sectores-admin-hero p {

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


.admin-card {

    background: #FFFFFF;

    border: 1px solid #ECECEC;

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

    padding: 18px 20px;

    border-bottom: 1px solid #EEEEEE;

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
        0 0 0 .2rem
        rgba(177,38,38,.08);

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

    border: 1px solid #DADADA;

    background: #FFFFFF;

    color: #555555;

    border-radius: 9px;

}


.table thead th {

    padding: 13px 15px;

    background: #FAFAFA;

    color: #555555;

    text-transform: uppercase;

    font-size: 10px;

    letter-spacing: .3px;

    white-space: nowrap;

}


.table tbody td {

    padding: 13px 15px;

    vertical-align: middle;

    border-color: #EEEEEE;

}


.tipo-badge {

    display: inline-flex;

    align-items: center;

    gap: 5px;

    padding: 5px 10px;

    border-radius: 20px;

    background: #F1E9FB;

    color: #6F42C1;

    font-size: 11px;

    font-weight: 700;

}


.estado-activo {

    display: inline-flex;

    align-items: center;

    gap: 5px;

    padding: 5px 8px;

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

    padding: 5px 8px;

    border-radius: 20px;

    background: #EEEEEE;

    color: #777777;

    font-size: 10px;

    font-weight: 700;

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


.empty {

    padding: 40px 20px;

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


<div class="admin-sectores-wrapper">


    <section class="sectores-admin-hero">

        <div class="row align-items-center">

            <div class="col-lg-8">

                <div class="hero-content">

                    <h1>

                        <i class="bi bi-building me-1"></i>

                        Sectores y aulas

                    </h1>

                    <p>

                        Administrá los espacios del colegio
                        donde se generan las solicitudes.

                    </p>

                </div>

            </div>


            <div class="col-lg-4 text-lg-end">

                <a
                    href="<?= url('admin/dashboard.php') ?>"
                    class="btn-volver"
                >

                    <i class="bi bi-arrow-left"></i>

                    Panel administrador

                </a>

            </div>

        </div>

    </section>


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

            <?= e($flash['mensaje']) ?>

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


        <div class="col-xl-4">

            <div class="admin-card">

                <div class="admin-card-header">

                    <h5>

                        <i class="bi <?= (int)$form['id_sector'] > 0
                            ? 'bi-pencil-square'
                            : 'bi-plus-circle'
                        ?> me-2"></i>

                        <?= (int)$form['id_sector'] > 0
                            ? 'Editar sector'
                            : 'Agregar sector'
                        ?>

                    </h5>

                </div>


                <div class="admin-card-body">

                    <form
                        method="POST"
                        action="<?= url('admin/sectores.php') ?>"
                    >

                        <?= csrfInput() ?>

                        <input
                            type="hidden"
                            name="accion"
                            value="guardar"
                        >

                        <input
                            type="hidden"
                            name="id_sector"
                            value="<?= (int)$form['id_sector'] ?>"
                        >


                        <div class="mb-3">

                            <label for="nombre" class="form-label">
                                Nombre
                            </label>

                            <input
                                type="text"
                                name="nombre"
                                id="nombre"
                                class="form-control"
                                maxlength="100"
                                value="<?= e($form['nombre']) ?>"
                                placeholder="Ej.: Sala de cinco"
                                required
                            >

                        </div>


                        <div class="mb-3">

                            <label for="tipo" class="form-label">
                                Tipo
                            </label>

                            <select
                                name="tipo"
                                id="tipo"
                                class="form-select"
                                required
                            >

                                <?php foreach (
                                    $tiposPermitidos
                                    as $t
                                ): ?>

                                    <option
                                        value="<?= e($t) ?>"
                                        <?= $form['tipo'] === $t
                                            ? 'selected'
                                            : ''
                                        ?>
                                    >
                                        <?= e($t) ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <div class="mb-3">

                            <label for="descripcion" class="form-label">
                                Descripción
                            </label>

                            <input
                                type="text"
                                name="descripcion"
                                id="descripcion"
                                class="form-control"
                                maxlength="255"
                                value="<?= e($form['descripcion']) ?>"
                                placeholder="Opcional"
                            >

                        </div>


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

                            <label class="form-check-label" for="activo">
                                Sector activo
                            </label>

                        </div>


                        <div class="form-text mt-1">
                            Los sectores inactivos dejan de
                            aparecer al crear una nueva solicitud,
                            pero se conserva el historial de las
                            que ya lo usaron.
                        </div>


                        <div class="d-grid gap-2 mt-4">

                            <button type="submit" class="btn btn-guardar">

                                <i class="bi bi-floppy me-1"></i>

                                <?= (int)$form['id_sector'] > 0
                                    ? 'Guardar cambios'
                                    : 'Agregar sector'
                                ?>

                            </button>


                            <?php if ((int)$form['id_sector'] > 0): ?>

                                <a
                                    href="<?= url('admin/sectores.php') ?>"
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


        <div class="col-xl-8">

            <div class="admin-card">

                <div class="admin-card-header">

                    <h5>

                        <i class="bi bi-list-ul me-2"></i>

                        Sectores cargados

                    </h5>

                </div>


                <?php if (empty($sectores)): ?>

                    <div class="empty">

                        <i class="bi bi-building"></i>

                        No hay sectores cargados.

                    </div>

                <?php else: ?>

                    <div class="table-responsive">

                        <table class="table mb-0">

                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Tipo</th>
                                    <th>Descripción</th>
                                    <th>Solicitudes</th>
                                    <th>Estado</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php foreach (
                                    $sectores
                                    as $sector
                                ): ?>

                                    <tr>

                                        <td>
                                            <strong><?= e($sector['nombre']) ?></strong>
                                        </td>

                                        <td>
                                            <span class="tipo-badge">
                                                <?= e($sector['tipo']) ?>
                                            </span>
                                        </td>

                                        <td>
                                            <?= e($sector['descripcion'] ?? '-') ?>
                                        </td>

                                        <td>
                                            <?= (int)$sector['total_solicitudes'] ?>
                                        </td>

                                        <td>

                                            <?php if ((int)$sector['activo'] === 1): ?>

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

                                        <td>

                                            <div class="d-flex gap-1 justify-content-center">

                                                <a
                                                    href="<?= url(
                                                        'admin/sectores.php?editar='
                                                        . (int)$sector['id_sector']
                                                    ) ?>"
                                                    class="action-button action-edit"
                                                    title="Editar"
                                                >
                                                    <i class="bi bi-pencil"></i>
                                                </a>


                                                <form
                                                    method="POST"
                                                    action="<?= url('admin/sectores.php') ?>"
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
                                                        name="id_sector"
                                                        value="<?= (int)$sector['id_sector'] ?>"
                                                    >

                                                    <button
                                                        type="submit"
                                                        class="action-button action-state"
                                                        title="<?= (int)$sector['activo'] === 1
                                                            ? 'Desactivar'
                                                            : 'Activar'
                                                        ?>"
                                                    >

                                                        <i class="bi <?= (int)$sector['activo'] === 1
                                                            ? 'bi-pause'
                                                            : 'bi-play'
                                                        ?>"></i>

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


<?php

require_once __DIR__
    . '/../includes/footer.php';

?>
