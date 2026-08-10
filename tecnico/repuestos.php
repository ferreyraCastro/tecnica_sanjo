<?php
// ============================================================
// COLEGIO SAN JOSÉ
// Sistema de Gestión Técnica
// Archivo: /tecnica/tecnico/repuestos.php
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/funciones.php';
require_once __DIR__ . '/../includes/auth.php';


// ============================================================
// SOLO TÉCNICOS / ADMINISTRADORES
// ============================================================

requerirRoles([
    'Tecnico',
    'Administrador'
]);


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
// DATOS DEL USUARIO
// ============================================================

$idTecnico = (int)usuarioId();


// ============================================================
// SOLICITUDES ABIERTAS ASIGNADAS AL TÉCNICO ACTUAL
// (para vincular el movimiento a un ticket, opcional)
// ============================================================

$stmtSolicitudesAbiertas =
    $conexion->prepare("
        SELECT
            s.id_solicitud,
            s.titulo

        FROM solicitudes s

        INNER JOIN solicitudes_asignaciones sa
            ON sa.id_solicitud = s.id_solicitud
            AND sa.activo = 1

        WHERE sa.id_tecnico = ?

        AND s.estado NOT IN (
            'Resuelta',
            'Cerrada',
            'Cancelada'
        )

        ORDER BY s.fecha_creacion DESC
    ");

$stmtSolicitudesAbiertas->execute([
    $idTecnico
]);

$solicitudesAbiertas =
    $stmtSolicitudesAbiertas->fetchAll(
        PDO::FETCH_ASSOC
    );


// ============================================================
// PROCESAR MOVIMIENTO
// ============================================================

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        !validarCsrf(
            $_POST['csrf_token'] ?? ''
        )
    ) {

        $error =
            'La sesión del formulario expiró. '
            . 'Actualizá la página e intentá nuevamente.';

    } else {

        $idRepuesto =
            (int)(
                $_POST['id_repuesto']
                ?? 0
            );

        $tipo =
            limpiarTexto(
                $_POST['tipo']
                ?? ''
            );

        $direccion =
            limpiarTexto(
                $_POST['direccion']
                ?? ''
            );

        $cantidad =
            (int)(
                $_POST['cantidad']
                ?? 0
            );

        $idSolicitud =
            (int)(
                $_POST['id_solicitud']
                ?? 0
            );

        $observaciones =
            limpiarTexto(
                $_POST['observaciones']
                ?? ''
            );


        $tiposPermitidos = [
            'Ingreso',
            'Uso',
            'Ajuste'
        ];

        $direccionesPermitidas = [
            'Entrada',
            'Salida'
        ];


        if ($idRepuesto <= 0) {

            $error =
                'Seleccioná un repuesto.';

        } elseif (
            !in_array(
                $tipo,
                $tiposPermitidos,
                true
            )
        ) {

            $error =
                'Seleccioná un tipo de movimiento válido.';

        } elseif (
            !in_array(
                $direccion,
                $direccionesPermitidas,
                true
            )
        ) {

            $error =
                'Seleccioná una dirección de movimiento válida.';

        } elseif ($cantidad <= 0) {

            $error =
                'La cantidad debe ser mayor a cero.';

        } elseif (
            mb_strlen($observaciones) > 255
        ) {

            $error =
                'Las observaciones no pueden superar los 255 caracteres.';

        } else {

            $ok = registrarMovimientoStock(
                $conexion,
                $idRepuesto,
                $tipo,
                $direccion,
                $cantidad,
                $idTecnico,
                $idSolicitud > 0
                    ? $idSolicitud
                    : null,
                null,
                $observaciones !== ''
                    ? $observaciones
                    : null
            );

            if ($ok) {

                flash(
                    'success',
                    'El movimiento de stock fue registrado correctamente.'
                );

                header(
                    'Location: '
                    . url('tecnico/repuestos.php')
                );

                exit;

            } else {

                $error =
                    'No se pudo registrar el movimiento. '
                    . 'Verificá que la cantidad de salida no supere el stock disponible.';
            }
        }
    }
}


// ============================================================
// DATOS PARA LA VISTA
// ============================================================

$repuestos =
    obtenerRepuestos(
        $conexion,
        true
    );

$stockBajo =
    obtenerStockBajo(
        $conexion
    );

$stmtMovimientos =
    $conexion->query("
        SELECT
            m.*,

            r.nombre AS repuesto,
            r.unidad,

            CONCAT(
                u.nombre,
                ' ',
                u.apellido
            ) AS usuario

        FROM repuestos_movimientos m

        INNER JOIN repuestos r
            ON m.id_repuesto = r.id_repuesto

        INNER JOIN usuarios u
            ON m.id_usuario = u.id_usuario

        ORDER BY m.fecha DESC

        LIMIT 30
    ");

$movimientosRecientes =
    $stmtMovimientos->fetchAll(
        PDO::FETCH_ASSOC
    );


$flash = obtenerFlash();


// ============================================================
// HEADER
// ============================================================

require_once __DIR__
    . '/../includes/header.php';

?>


<style>

.repuestos-wrapper {

    max-width: 1450px;

    margin: 0 auto;

    padding:
        5px 12px 45px;

}


/* ============================================================
   HERO
============================================================ */

.repuestos-hero {

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


.repuestos-hero::after {

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


.repuestos-hero h1 {

    margin: 0 0 7px;

    font-size: 28px;

    font-weight: 800;

}


.repuestos-hero p {

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


/* ============================================================
   ALERTA STOCK BAJO
============================================================ */

.stock-bajo-alert {

    background: #FFF6DB;

    border-left: 4px solid #E0A800;

    border-radius: 12px;

    padding: 16px 18px;

    margin-bottom: 22px;

    color: #685500;

}


.stock-bajo-alert strong {

    display: block;

    margin-bottom: 6px;

}


.stock-bajo-chip {

    display: inline-flex;

    align-items: center;

    gap: 5px;

    background: #FFFFFF;

    border: 1px solid #F0DE9E;

    border-radius: 20px;

    padding: 5px 10px;

    font-size: 11px;

    font-weight: 700;

    margin: 3px 4px 0 0;

}


/* ============================================================
   CARDS
============================================================ */

.repuestos-card {

    background: #FFFFFF;

    border: 1px solid #ECECEC;

    border-radius: 18px;

    overflow: hidden;

    box-shadow:
        0 5px 19px
        rgba(0,0,0,.05);

    margin-bottom: 24px;

}


.repuestos-card-header {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 12px;

    padding: 18px 20px;

    border-bottom: 1px solid #EEEEEE;

}


.repuestos-card-header h5 {

    margin: 0;

    color: #760000;

    font-size: 16px;

    font-weight: 800;

}


.repuestos-card-body {

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


.fila-stock-bajo {

    background: #FFFBF0;

}


.badge-stock-ok {

    background: #E1F4E8;

    color: #198754;

}


.badge-stock-bajo {

    background: #FFE5E5;

    color: #B12626;

}


.empty {

    padding: 35px 20px;

    color: #888888;

    text-align: center;

}


.empty i {

    display: block;

    font-size: 38px;

    color: #D0D0D0;

    margin-bottom: 8px;

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

</style>


<div class="repuestos-wrapper">


    <!-- =====================================================
         HERO
    ====================================================== -->

    <section class="repuestos-hero">

        <div class="row align-items-center">

            <div class="col-lg-8">

                <div class="hero-content">

                    <h1>

                        <i class="bi bi-box-seam me-1"></i>

                        Repuestos y stock

                    </h1>

                    <p>

                        Consultá el stock disponible y
                        registrá los repuestos utilizados
                        en tus reparaciones.

                    </p>

                </div>

            </div>


            <div class="col-lg-4 text-lg-end">

                <a
                    href="<?= url('dashboard.php') ?>"
                    class="btn-volver"
                >

                    <i class="bi bi-arrow-left"></i>

                    Volver al dashboard

                </a>

            </div>

        </div>

    </section>


    <!-- =====================================================
         FLASH / ERROR
    ====================================================== -->

    <?php if ($flash): ?>

        <div
            class="alert alert-<?= $flash['tipo'] === 'success'
                ? 'success'
                : 'danger'
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


    <!-- =====================================================
         ALERTA STOCK BAJO
    ====================================================== -->

    <?php if (!empty($stockBajo)): ?>

        <div class="stock-bajo-alert">

            <strong>

                <i class="bi bi-exclamation-triangle me-1"></i>

                Hay <?= count($stockBajo) ?>
                repuesto(s) con stock al mínimo o por debajo:

            </strong>

            <?php foreach ($stockBajo as $item): ?>

                <span class="stock-bajo-chip">

                    <i class="bi bi-box"></i>

                    <?= e($item['nombre']) ?>
                    (<?= (int)$item['stock_actual'] ?>
                    / <?= (int)$item['stock_minimo'] ?>)

                </span>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>


    <div class="row g-4">


        <!-- =================================================
             FORMULARIO MOVIMIENTO
        ================================================== -->

        <div class="col-xl-4">

            <div class="repuestos-card">

                <div class="repuestos-card-header">

                    <h5>

                        <i class="bi bi-arrow-left-right me-2"></i>

                        Registrar movimiento

                    </h5>

                </div>


                <div class="repuestos-card-body">

                    <form
                        method="POST"
                        action="<?= url('tecnico/repuestos.php') ?>"
                    >

                        <?= csrfInput() ?>


                        <div class="mb-3">

                            <label
                                for="id_repuesto"
                                class="form-label"
                            >
                                Repuesto
                            </label>

                            <select
                                name="id_repuesto"
                                id="id_repuesto"
                                class="form-select"
                                required
                            >

                                <option value="">
                                    Seleccionar repuesto...
                                </option>

                                <?php foreach (
                                    $repuestos
                                    as $repuesto
                                ): ?>

                                    <option
                                        value="<?= (int)$repuesto['id_repuesto'] ?>"
                                    >

                                        <?= e($repuesto['nombre']) ?>
                                        (stock:
                                        <?= (int)$repuesto['stock_actual'] ?>
                                        <?= e($repuesto['unidad']) ?>)

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <div class="row g-3">

                            <div class="col-6">

                                <label
                                    for="tipo"
                                    class="form-label"
                                >
                                    Tipo
                                </label>

                                <select
                                    name="tipo"
                                    id="tipo"
                                    class="form-select"
                                    required
                                >
                                    <option value="Uso">Uso</option>
                                    <option value="Ingreso">Ingreso</option>
                                    <option value="Ajuste">Ajuste</option>
                                </select>

                            </div>


                            <div class="col-6">

                                <label
                                    for="direccion"
                                    class="form-label"
                                >
                                    Dirección
                                </label>

                                <select
                                    name="direccion"
                                    id="direccion"
                                    class="form-select"
                                    required
                                >
                                    <option value="Salida">Salida</option>
                                    <option value="Entrada">Entrada</option>
                                </select>

                            </div>

                        </div>


                        <div class="mt-3">

                            <label
                                for="cantidad"
                                class="form-label"
                            >
                                Cantidad
                            </label>

                            <input
                                type="number"
                                name="cantidad"
                                id="cantidad"
                                class="form-control"
                                min="1"
                                step="1"
                                required
                            >

                        </div>


                        <div class="mt-3">

                            <label
                                for="id_solicitud"
                                class="form-label"
                            >
                                Solicitud relacionada
                                <span class="text-muted fw-normal">
                                    (opcional)
                                </span>
                            </label>

                            <select
                                name="id_solicitud"
                                id="id_solicitud"
                                class="form-select"
                            >

                                <option value="0">
                                    Sin vincular a un ticket
                                </option>

                                <?php foreach (
                                    $solicitudesAbiertas
                                    as $solicitudAbierta
                                ): ?>

                                    <option
                                        value="<?= (int)$solicitudAbierta['id_solicitud'] ?>"
                                    >

                                        <?= e(
                                            numeroTicket(
                                                (int)$solicitudAbierta['id_solicitud']
                                            )
                                        ) ?>
                                        -
                                        <?= e($solicitudAbierta['titulo']) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


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
                                maxlength="255"
                                placeholder="Detalle opcional del movimiento..."
                            ></textarea>

                        </div>


                        <div class="d-grid mt-4">

                            <button
                                type="submit"
                                class="btn btn-guardar"
                            >

                                <i class="bi bi-check2 me-1"></i>

                                Registrar movimiento

                            </button>

                        </div>

                    </form>

                </div>

            </div>

        </div>


        <!-- =================================================
             LISTADO DE REPUESTOS
        ================================================== -->

        <div class="col-xl-8">

            <div class="repuestos-card">

                <div class="repuestos-card-header">

                    <h5>

                        <i class="bi bi-list-ul me-2"></i>

                        Catálogo de repuestos

                    </h5>

                    <span class="badge bg-secondary">

                        <?= count($repuestos) ?>

                    </span>

                </div>


                <?php if (empty($repuestos)): ?>

                    <div class="empty">

                        <i class="bi bi-box"></i>

                        No hay repuestos cargados en el catálogo.

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
                                    <th>Ubicación</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php foreach (
                                    $repuestos
                                    as $repuesto
                                ): ?>

                                    <?php
                                    $bajo =
                                        (int)$repuesto['stock_actual']
                                        <=
                                        (int)$repuesto['stock_minimo'];
                                    ?>

                                    <tr class="<?= $bajo ? 'fila-stock-bajo' : '' ?>">

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

                                            <strong>
                                                <?= e($repuesto['nombre']) ?>
                                            </strong>

                                            <?php if (
                                                !empty($repuesto['descripcion'])
                                            ): ?>

                                                <div class="small text-muted">
                                                    <?= e($repuesto['descripcion']) ?>
                                                </div>

                                            <?php endif; ?>

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
                                            <?= !empty($repuesto['ubicacion'])
                                                ? e($repuesto['ubicacion'])
                                                : '-'
                                            ?>
                                        </td>

                                        <td>

                                            <span
                                                class="badge <?= $bajo
                                                    ? 'badge-stock-bajo'
                                                    : 'badge-stock-ok'
                                                ?>"
                                            >

                                                <?= $bajo
                                                    ? 'Stock bajo'
                                                    : 'OK'
                                                ?>

                                            </span>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php endif; ?>

            </div>


            <!-- =============================================
                 MOVIMIENTOS RECIENTES
            ============================================== -->

            <div class="repuestos-card mb-0">

                <div class="repuestos-card-header">

                    <h5>

                        <i class="bi bi-clock-history me-2"></i>

                        Movimientos recientes

                    </h5>

                </div>


                <?php if (empty($movimientosRecientes)): ?>

                    <div class="empty">

                        <i class="bi bi-arrow-left-right"></i>

                        Todavía no se registraron movimientos.

                    </div>

                <?php else: ?>

                    <div class="table-responsive">

                        <table class="table mb-0">

                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Repuesto</th>
                                    <th>Tipo</th>
                                    <th>Cantidad</th>
                                    <th>Stock resultante</th>
                                    <th>Ticket</th>
                                    <th>Usuario</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php foreach (
                                    $movimientosRecientes
                                    as $movimiento
                                ): ?>

                                    <tr>

                                        <td>
                                            <?= e(
                                                fechaArgentina($movimiento['fecha'])
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= e($movimiento['repuesto']) ?>
                                        </td>

                                        <td>

                                            <?= e($movimiento['tipo']) ?>

                                            <span class="text-muted">
                                                (<?= e($movimiento['direccion']) ?>)
                                            </span>

                                        </td>

                                        <td>
                                            <?= (int)$movimiento['cantidad'] ?>
                                            <?= e($movimiento['unidad']) ?>
                                        </td>

                                        <td>
                                            <?= (int)$movimiento['stock_resultante'] ?>
                                        </td>

                                        <td>

                                            <?php if (!empty($movimiento['id_solicitud'])): ?>

                                                <a
                                                    href="<?= url(
                                                        'ver_solicitud.php?id='
                                                        . (int)$movimiento['id_solicitud']
                                                    ) ?>"
                                                >
                                                    <?= e(
                                                        numeroTicket(
                                                            (int)$movimiento['id_solicitud']
                                                        )
                                                    ) ?>
                                                </a>

                                            <?php else: ?>

                                                <span class="text-muted">-</span>

                                            <?php endif; ?>

                                        </td>

                                        <td>
                                            <?= e($movimiento['usuario']) ?>
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
