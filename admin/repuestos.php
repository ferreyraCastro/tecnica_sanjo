<?php
// ============================================================
// COLEGIO SAN JOSÉ
// Sistema de Gestión Técnica
// Archivo: /tecnica/admin/repuestos.php
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

$categoriasPermitidas = [
    'Informatica',
    'Mantenimiento',
    'General'
];


// ============================================================
// VARIABLES
// ============================================================

$error = '';

$editarRepuesto = null;


function volverRepuestosAdmin(): never
{
    header(
        'Location: ' .
        url('admin/repuestos.php')
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

        volverRepuestosAdmin();
    }


    $accion =
        limpiarTexto(
            $_POST['accion'] ?? ''
        );


    // ========================================================
    // CREAR / EDITAR
    // ========================================================

    if ($accion === 'guardar') {

        $idRepuesto =
            (int)(
                $_POST['id_repuesto']
                ?? 0
            );

        $nombre =
            limpiarTexto(
                $_POST['nombre']
                ?? ''
            );

        $descripcion =
            limpiarTexto(
                $_POST['descripcion']
                ?? ''
            );

        $categoria =
            limpiarTexto(
                $_POST['categoria']
                ?? ''
            );

        $unidad =
            limpiarTexto(
                $_POST['unidad']
                ?? 'unidad'
            );

        $stockActual =
            (int)(
                $_POST['stock_actual']
                ?? 0
            );

        $stockMinimo =
            (int)(
                $_POST['stock_minimo']
                ?? 0
            );

        $costoUnitario =
            limpiarTexto(
                $_POST['costo_unitario']
                ?? ''
            );

        $ubicacion =
            limpiarTexto(
                $_POST['ubicacion']
                ?? ''
            );

        $activo =
            isset($_POST['activo'])
                ? 1
                : 0;

        $quitarFoto =
            isset($_POST['quitar_foto']);


        // ====================================================
        // FOTO ACTUAL (si estamos editando)
        // ====================================================

        $fotoActual = '';

        if ($idRepuesto > 0) {

            $repuestoExistente =
                obtenerRepuesto(
                    $conexion,
                    $idRepuesto
                );

            $fotoActual =
                $repuestoExistente['foto']
                ?? '';
        }

        $foto = $fotoActual;


        // ====================================================
        // VALIDACIONES
        // ====================================================

        if ($nombre === '') {

            $error =
                'Ingresá el nombre del repuesto.';

        } elseif (mb_strlen($nombre) > 150) {

            $error =
                'El nombre no puede superar los 150 caracteres.';

        } elseif (
            !in_array(
                $categoria,
                $categoriasPermitidas,
                true
            )
        ) {

            $error =
                'Seleccioná una categoría válida.';

        } elseif ($unidad === '') {

            $error =
                'Ingresá la unidad de medida.';

        } elseif ($stockMinimo < 0) {

            $error =
                'El stock mínimo no puede ser negativo.';

        } elseif (
            $idRepuesto <= 0
            &&
            $stockActual < 0
        ) {

            $error =
                'El stock inicial no puede ser negativo.';
        }


        // ====================================================
        // FOTO NUEVA / QUITAR FOTO
        // ====================================================

        if (
            $error === ''
            &&
            $quitarFoto
            &&
            $foto !== ''
        ) {

            @unlink(
                rtrim(UPLOAD_REPUESTOS, DIRECTORY_SEPARATOR)
                . DIRECTORY_SEPARATOR
                . $foto
            );

            $foto = '';
        }


        if (
            $error === ''
            &&
            !empty($_FILES['foto']['name'] ?? '')
        ) {

            $resultadoFoto =
                subirImagen(
                    $_FILES['foto'],
                    UPLOAD_REPUESTOS,
                    MAX_IMAGEN_MB
                );

            if ($resultadoFoto['ok']) {

                if ($foto !== '') {

                    @unlink(
                        rtrim(UPLOAD_REPUESTOS, DIRECTORY_SEPARATOR)
                        . DIRECTORY_SEPARATOR
                        . $foto
                    );
                }

                $foto = $resultadoFoto['archivo'];

            } else {

                $error = $resultadoFoto['error'];
            }
        }


        // ====================================================
        // GUARDAR
        // ====================================================

        if ($error === '') {

            $datos = [
                'nombre' => $nombre,
                'descripcion' => $descripcion,
                'foto' => $foto,
                'categoria' => $categoria,
                'unidad' => $unidad,
                'stock_actual' => $stockActual,
                'stock_minimo' => $stockMinimo,
                'costo_unitario' => $costoUnitario,
                'ubicacion' => $ubicacion,
                'activo' => $activo
            ];

            if ($idRepuesto > 0) {

                if (
                    actualizarRepuesto(
                        $conexion,
                        $idRepuesto,
                        $datos
                    )
                ) {

                    flash(
                        'success',
                        'El repuesto fue actualizado correctamente.'
                    );

                    volverRepuestosAdmin();

                } else {

                    $error =
                        'No se pudo actualizar el repuesto.';
                }

            } else {

                if (
                    crearRepuesto(
                        $conexion,
                        $datos
                    ) !== false
                ) {

                    flash(
                        'success',
                        'El repuesto fue agregado correctamente.'
                    );

                    volverRepuestosAdmin();

                } else {

                    $error =
                        'No se pudo agregar el repuesto.';
                }
            }
        }


        $editarRepuesto = [
            'id_repuesto' => $idRepuesto,
            'nombre' => $nombre,
            'descripcion' => $descripcion,
            'foto' => $foto,
            'categoria' => $categoria,
            'unidad' => $unidad,
            'stock_actual' => $stockActual,
            'stock_minimo' => $stockMinimo,
            'costo_unitario' => $costoUnitario,
            'ubicacion' => $ubicacion,
            'activo' => $activo
        ];
    }


    // ========================================================
    // ACTIVAR / DESACTIVAR
    // ========================================================

    elseif ($accion === 'estado') {

        $idRepuesto =
            (int)(
                $_POST['id_repuesto']
                ?? 0
            );

        if ($idRepuesto > 0) {

            $repuestoActual =
                obtenerRepuesto(
                    $conexion,
                    $idRepuesto
                );

            if ($repuestoActual) {

                $datos = $repuestoActual;

                $datos['activo'] =
                    (int)$repuestoActual['activo'] === 1
                        ? 0
                        : 1;

                actualizarRepuesto(
                    $conexion,
                    $idRepuesto,
                    $datos
                );

                flash(
                    'success',
                    'El estado del repuesto fue actualizado.'
                );
            }
        }

        volverRepuestosAdmin();
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
    $editarRepuesto === null
) {

    $repuestoBD =
        obtenerRepuesto(
            $conexion,
            $idEditar
        );

    if ($repuestoBD) {

        $editarRepuesto = [
            'id_repuesto' => (int)$repuestoBD['id_repuesto'],
            'nombre' => $repuestoBD['nombre'],
            'descripcion' => $repuestoBD['descripcion'] ?? '',
            'foto' => $repuestoBD['foto'] ?? '',
            'categoria' => $repuestoBD['categoria'],
            'unidad' => $repuestoBD['unidad'],
            'stock_actual' => (int)$repuestoBD['stock_actual'],
            'stock_minimo' => (int)$repuestoBD['stock_minimo'],
            'costo_unitario' => $repuestoBD['costo_unitario'] ?? '',
            'ubicacion' => $repuestoBD['ubicacion'] ?? '',
            'activo' => (int)$repuestoBD['activo']
        ];
    }
}


$form = $editarRepuesto ?? [
    'id_repuesto' => 0,
    'nombre' => '',
    'descripcion' => '',
    'foto' => '',
    'categoria' => 'Informatica',
    'unidad' => 'unidad',
    'stock_actual' => 0,
    'stock_minimo' => 0,
    'costo_unitario' => '',
    'ubicacion' => '',
    'activo' => 1
];


// ============================================================
// LISTADO
// ============================================================

$repuestos =
    $conexion->query("
        SELECT *
        FROM repuestos
        ORDER BY nombre ASC
    ")->fetchAll(PDO::FETCH_ASSOC);


$flash = obtenerFlash();


// ============================================================
// HEADER
// ============================================================

require_once __DIR__
    . '/../includes/header.php';

?>


<style>

.admin-repuestos-wrapper {

    max-width: 1450px;

    margin: 0 auto;

    padding: 5px 12px 45px;

}


.repuestos-admin-hero {

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


.repuestos-admin-hero::after {

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


.repuestos-admin-hero h1 {

    margin: 0 0 7px;

    font-size: 28px;

    font-weight: 800;

}


.repuestos-admin-hero p {

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


.repuesto-foto-actual img {

    width: 100%;

    max-width: 160px;

    border-radius: 10px;

    border: 1px solid #EEEEEE;

    display: block;

}


.repuesto-thumb {

    width: 42px;

    height: 42px;

    border-radius: 8px;

    object-fit: cover;

    border: 1px solid #EEEEEE;

}


.repuesto-thumb-placeholder {

    width: 42px;

    height: 42px;

    border-radius: 8px;

    background: #FAFAFA;

    border: 1px solid #EEEEEE;

    display: inline-flex;

    align-items: center;

    justify-content: center;

    color: #CCCCCC;

    font-size: 18px;

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


<div class="admin-repuestos-wrapper">


    <section class="repuestos-admin-hero">

        <div class="row align-items-center">

            <div class="col-lg-8">

                <div class="hero-content">

                    <h1>

                        <i class="bi bi-box-seam me-1"></i>

                        Catálogo de repuestos

                    </h1>

                    <p>

                        Administrá el catálogo de repuestos
                        e insumos disponibles para reparaciones.

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

                        <i class="bi <?= (int)$form['id_repuesto'] > 0
                            ? 'bi-pencil-square'
                            : 'bi-plus-circle'
                        ?> me-2"></i>

                        <?= (int)$form['id_repuesto'] > 0
                            ? 'Editar repuesto'
                            : 'Agregar repuesto'
                        ?>

                    </h5>

                </div>


                <div class="admin-card-body">

                    <form
                        method="POST"
                        action="<?= url('admin/repuestos.php') ?>"
                        enctype="multipart/form-data"
                    >

                        <?= csrfInput() ?>

                        <input
                            type="hidden"
                            name="accion"
                            value="guardar"
                        >

                        <input
                            type="hidden"
                            name="id_repuesto"
                            value="<?= (int)$form['id_repuesto'] ?>"
                        >


                        <div class="mb-3">

                            <label class="form-label">
                                Foto
                            </label>

                            <?php if (!empty($form['foto'])): ?>

                                <div class="repuesto-foto-actual">

                                    <img
                                        src="<?= e(
                                            UPLOAD_REPUESTOS_URL
                                            . $form['foto']
                                        ) ?>"
                                        alt="Foto actual"
                                    >

                                    <div class="form-check mt-2">

                                        <input
                                            type="checkbox"
                                            name="quitar_foto"
                                            value="1"
                                            id="quitar_foto"
                                            class="form-check-input"
                                        >

                                        <label
                                            class="form-check-label"
                                            for="quitar_foto"
                                            style="font-size:12px;"
                                        >
                                            Quitar foto actual
                                        </label>

                                    </div>

                                </div>

                            <?php endif; ?>

                            <input
                                type="file"
                                name="foto"
                                id="foto"
                                class="form-control mt-2"
                                accept="image/png,image/jpeg,image/webp"
                            >

                            <div class="form-text">
                                JPG, PNG o WEBP. Máximo <?= (int)MAX_IMAGEN_MB ?> MB.
                            </div>

                        </div>


                        <div class="mb-3">

                            <label for="nombre" class="form-label">
                                Nombre
                            </label>

                            <input
                                type="text"
                                name="nombre"
                                id="nombre"
                                class="form-control"
                                maxlength="150"
                                value="<?= e($form['nombre']) ?>"
                                required
                            >

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
                            >

                        </div>


                        <div class="row g-3">

                            <div class="col-6">

                                <label for="categoria" class="form-label">
                                    Categoría
                                </label>

                                <select
                                    name="categoria"
                                    id="categoria"
                                    class="form-select"
                                    required
                                >

                                    <?php foreach (
                                        $categoriasPermitidas
                                        as $cat
                                    ): ?>

                                        <option
                                            value="<?= e($cat) ?>"
                                            <?= $form['categoria'] === $cat
                                                ? 'selected'
                                                : ''
                                            ?>
                                        >
                                            <?= e($cat) ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>


                            <div class="col-6">

                                <label for="unidad" class="form-label">
                                    Unidad
                                </label>

                                <input
                                    type="text"
                                    name="unidad"
                                    id="unidad"
                                    class="form-control"
                                    maxlength="30"
                                    value="<?= e($form['unidad']) ?>"
                                    required
                                >

                            </div>

                        </div>


                        <div class="row g-3 mt-1">

                            <div class="col-6">

                                <label for="stock_actual" class="form-label">
                                    Stock inicial
                                </label>

                                <input
                                    type="number"
                                    name="stock_actual"
                                    id="stock_actual"
                                    class="form-control"
                                    min="0"
                                    step="1"
                                    value="<?= (int)$form['stock_actual'] ?>"
                                    <?= (int)$form['id_repuesto'] > 0
                                        ? 'readonly'
                                        : ''
                                    ?>
                                    required
                                >

                                <?php if ((int)$form['id_repuesto'] > 0): ?>

                                    <div class="form-text">

                                        Para modificar el stock
                                        usá un movimiento de Ajuste
                                        desde la vista del técnico.

                                    </div>

                                <?php endif; ?>

                            </div>


                            <div class="col-6">

                                <label for="stock_minimo" class="form-label">
                                    Stock mínimo
                                </label>

                                <input
                                    type="number"
                                    name="stock_minimo"
                                    id="stock_minimo"
                                    class="form-control"
                                    min="0"
                                    step="1"
                                    value="<?= (int)$form['stock_minimo'] ?>"
                                    required
                                >

                            </div>

                        </div>


                        <div class="mt-3">

                            <label for="costo_unitario" class="form-label">
                                Costo unitario
                            </label>

                            <input
                                type="number"
                                name="costo_unitario"
                                id="costo_unitario"
                                class="form-control"
                                min="0"
                                step="0.01"
                                value="<?= e((string)$form['costo_unitario']) ?>"
                            >

                        </div>


                        <div class="mt-3">

                            <label for="ubicacion" class="form-label">
                                Ubicación
                            </label>

                            <input
                                type="text"
                                name="ubicacion"
                                id="ubicacion"
                                class="form-control"
                                maxlength="150"
                                value="<?= e($form['ubicacion']) ?>"
                                placeholder="Ej.: Depósito de informática"
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
                                Repuesto activo
                            </label>

                        </div>


                        <div class="d-grid gap-2 mt-4">

                            <button type="submit" class="btn btn-guardar">

                                <i class="bi bi-floppy me-1"></i>

                                <?= (int)$form['id_repuesto'] > 0
                                    ? 'Guardar cambios'
                                    : 'Agregar repuesto'
                                ?>

                            </button>


                            <?php if ((int)$form['id_repuesto'] > 0): ?>

                                <a
                                    href="<?= url('admin/repuestos.php') ?>"
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

                        Repuestos cargados

                    </h5>

                    <a
                        href="<?= url('tecnico/repuestos.php') ?>"
                        class="btn btn-sm btn-outline-secondary"
                    >

                        <i class="bi bi-eye me-1"></i>

                        Vista técnico

                    </a>

                </div>


                <?php if (empty($repuestos)): ?>

                    <div class="empty">

                        <i class="bi bi-box"></i>

                        No hay repuestos cargados.

                    </div>

                <?php else: ?>

                    <div class="table-responsive">

                        <table class="table mb-0">

                            <thead>
                                <tr>
                                    <th>Foto</th>
                                    <th>Nombre</th>
                                    <th>Categoría</th>
                                    <th>Stock</th>
                                    <th>Mínimo</th>
                                    <th>Costo</th>
                                    <th>Estado</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php foreach (
                                    $repuestos
                                    as $repuesto
                                ): ?>

                                    <tr>

                                        <td>

                                            <?php if (!empty($repuesto['foto'])): ?>

                                                <img
                                                    src="<?= e(
                                                        UPLOAD_REPUESTOS_URL
                                                        . $repuesto['foto']
                                                    ) ?>"
                                                    class="repuesto-thumb"
                                                    alt=""
                                                >

                                            <?php else: ?>

                                                <span class="repuesto-thumb-placeholder">
                                                    <i class="bi bi-image"></i>
                                                </span>

                                            <?php endif; ?>

                                        </td>

                                        <td>
                                            <strong><?= e($repuesto['nombre']) ?></strong>
                                        </td>

                                        <td>
                                            <?= e($repuesto['categoria']) ?>
                                        </td>

                                        <td>
                                            <?= (int)$repuesto['stock_actual'] ?>
                                            <?= e($repuesto['unidad']) ?>
                                        </td>

                                        <td>
                                            <?= (int)$repuesto['stock_minimo'] ?>
                                        </td>

                                        <td>
                                            <?= e(
                                                formatoDinero($repuesto['costo_unitario'])
                                            ) ?>
                                        </td>

                                        <td>

                                            <?php if ((int)$repuesto['activo'] === 1): ?>

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
                                                        'admin/repuestos.php?editar='
                                                        . (int)$repuesto['id_repuesto']
                                                    ) ?>"
                                                    class="action-button action-edit"
                                                    title="Editar"
                                                >
                                                    <i class="bi bi-pencil"></i>
                                                </a>


                                                <form
                                                    method="POST"
                                                    action="<?= url('admin/repuestos.php') ?>"
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
                                                        name="id_repuesto"
                                                        value="<?= (int)$repuesto['id_repuesto'] ?>"
                                                    >

                                                    <button
                                                        type="submit"
                                                        class="action-button action-state"
                                                        title="<?= (int)$repuesto['activo'] === 1
                                                            ? 'Desactivar'
                                                            : 'Activar'
                                                        ?>"
                                                    >

                                                        <i class="bi <?= (int)$repuesto['activo'] === 1
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
