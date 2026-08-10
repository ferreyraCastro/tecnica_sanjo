<?php
// ============================================================
// COLEGIO SAN JOSÉ
// Sistema de Gestión Técnica
// Archivo: /tecnica/admin/horas_extra.php
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
// PROCESAR CAMBIO DE ESTADO
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

    } else {

        $idHoraExtra =
            (int)(
                $_POST['id_hora_extra']
                ?? 0
            );

        $nuevoEstado =
            limpiarTexto(
                $_POST['nuevo_estado'] ?? ''
            );

        if (
            $idHoraExtra > 0
            &&
            actualizarEstadoHoraExtra(
                $conexion,
                $idHoraExtra,
                $nuevoEstado
            )
        ) {

            flash(
                'success',
                'El estado fue actualizado correctamente.'
            );

        } else {

            flash(
                'error',
                'No se pudo actualizar el estado.'
            );
        }
    }

    header(
        'Location: ' . url('admin/horas_extra.php')
        . (
            !empty($_GET)
                ? '?' . http_build_query($_GET)
                : ''
        )
    );

    exit;
}


// ============================================================
// FILTROS
// ============================================================

$filtroTecnico =
    (int)(
        $_GET['id_tecnico']
        ?? 0
    );

$filtroEstado =
    limpiarTexto(
        $_GET['estado'] ?? ''
    );

$tecnicos = obtenerTecnicos($conexion);


// ============================================================
// LISTADO
// ============================================================

$horasExtra = obtenerHorasExtra(
    $conexion,
    $filtroEstado !== ''
        ? $filtroEstado
        : null
);

if ($filtroTecnico > 0) {

    $horasExtra = array_values(
        array_filter(
            $horasExtra,
            static fn(array $h): bool =>
                (int)$h['id_tecnico'] === $filtroTecnico
        )
    );
}


$estadosPermitidos = [
    'Pendiente',
    'Utilizada',
    'Pagada',
    'Cancelada'
];


$flash = obtenerFlash();


// ============================================================
// HEADER
// ============================================================

require_once __DIR__
    . '/../includes/header.php';

?>


<style>

.horas-extra-wrapper {

    max-width: 1450px;

    margin: 0 auto;

    padding: 5px 12px 45px;

}


.he-hero {

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


.he-hero::after {

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


.he-hero h1 {

    margin: 0 0 7px;

    font-size: 28px;

    font-weight: 800;

}


.he-hero p {

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


.filtros-card {

    background: #FFFFFF;

    border: 1px solid #ECECEC;

    border-radius: 16px;

    padding: 18px 20px;

    margin-bottom: 24px;

    box-shadow:
        0 4px 15px
        rgba(0,0,0,.04);

}


.form-label {

    color: #4D4D4D;

    font-size: 12px;

    font-weight: 700;

}


.form-control,
.form-select {

    min-height: 44px;

    border-radius: 9px;

}


.btn-filtrar {

    background:
        linear-gradient(
            135deg,
            #760000,
            #B12626
        );

    color: #FFFFFF;

    border: none;

    min-height: 44px;

    border-radius: 9px;

    font-weight: 700;

}


.btn-filtrar:hover {

    background: #760000;

    color: #FFFFFF;

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


.table thead th {

    padding: 12px 14px;

    background: #FAFAFA;

    color: #555555;

    text-transform: uppercase;

    font-size: 10px;

    letter-spacing: .3px;

    white-space: nowrap;

}


.table tbody td {

    padding: 12px 14px;

    vertical-align: middle;

    border-color: #EEEEEE;

}


.estado-select {

    min-height: 36px;

    font-size: 12px;

    border-radius: 8px;

}


.empty {

    padding: 40px 20px;

    color: #888888;

    text-align: center;

}


.empty i {

    display: block;

    font-size: 38px;

    color: #D0D0D0;

    margin-bottom: 8px;

}

</style>


<div class="horas-extra-wrapper">


    <section class="he-hero">

        <div class="row align-items-center">

            <div class="col-lg-8">

                <div class="hero-content">

                    <h1>

                        <i class="bi bi-clock-history me-1"></i>

                        Horas extra y compensación

                    </h1>

                    <p>

                        Seguimiento de las horas generadas
                        por reprogramación de turnos, para
                        pago o compensación posterior.

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


    <!-- =====================================================
         FILTROS
    ====================================================== -->

    <div class="filtros-card">

        <form
            method="GET"
            action="<?= url('admin/horas_extra.php') ?>"
            class="row g-3 align-items-end"
        >

            <div class="col-md-5">

                <label for="id_tecnico" class="form-label">Técnico</label>

                <select
                    name="id_tecnico"
                    id="id_tecnico"
                    class="form-select"
                >

                    <option value="0">Todos los técnicos</option>

                    <?php foreach ($tecnicos as $tecnico): ?>

                        <option
                            value="<?= (int)$tecnico['id_usuario'] ?>"
                            <?= $filtroTecnico === (int)$tecnico['id_usuario']
                                ? 'selected'
                                : ''
                            ?>
                        >

                            <?= e(
                                trim(
                                    $tecnico['nombre']
                                    . ' '
                                    . $tecnico['apellido']
                                )
                            ) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div class="col-md-5">

                <label for="estado" class="form-label">Estado</label>

                <select
                    name="estado"
                    id="estado"
                    class="form-select"
                >

                    <option value="">Todos los estados</option>

                    <?php foreach ($estadosPermitidos as $estado): ?>

                        <option
                            value="<?= e($estado) ?>"
                            <?= $filtroEstado === $estado
                                ? 'selected'
                                : ''
                            ?>
                        >
                            <?= e($estado) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div class="col-md-2">

                <button type="submit" class="btn btn-filtrar w-100">
                    <i class="bi bi-funnel me-1"></i>
                    Filtrar
                </button>

            </div>

        </form>

    </div>


    <div class="admin-card">

        <?php if (empty($horasExtra)): ?>

            <div class="empty">

                <i class="bi bi-clock-history"></i>

                No hay registros de horas extra / compensación.

            </div>

        <?php else: ?>

            <div class="table-responsive">

                <table class="table mb-0">

                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Técnico</th>
                            <th>Tipo</th>
                            <th>Horas</th>
                            <th>Motivo</th>
                            <th>Semana a compensar</th>
                            <th>Estado</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($horasExtra as $hora): ?>

                            <tr>

                                <td>
                                    <?= e(fechaArgentina($hora['fecha_creacion'])) ?>
                                </td>

                                <td>
                                    <?= e($hora['tecnico']) ?>
                                </td>

                                <td>
                                    <?= e($hora['tipo']) ?>
                                </td>

                                <td>
                                    <?= e((string)$hora['horas']) ?>
                                </td>

                                <td>
                                    <?= e($hora['motivo'] ?? '-') ?>
                                </td>

                                <td>
                                    <?= !empty($hora['semana_compensar'])
                                        ? e(fechaCorta($hora['semana_compensar']))
                                        : '-'
                                    ?>
                                </td>

                                <td>

                                    <form
                                        method="POST"
                                        action="<?= url(
                                            'admin/horas_extra.php'
                                            . (
                                                !empty($_GET)
                                                    ? '?' . http_build_query($_GET)
                                                    : ''
                                            )
                                        ) ?>"
                                        class="d-flex gap-1"
                                    >

                                        <?= csrfInput() ?>

                                        <input
                                            type="hidden"
                                            name="id_hora_extra"
                                            value="<?= (int)$hora['id_hora_extra'] ?>"
                                        >

                                        <select
                                            name="nuevo_estado"
                                            class="form-select estado-select"
                                            onchange="this.form.submit()"
                                        >

                                            <?php foreach ($estadosPermitidos as $estado): ?>

                                                <option
                                                    value="<?= e($estado) ?>"
                                                    <?= $hora['estado'] === $estado
                                                        ? 'selected'
                                                        : ''
                                                    ?>
                                                >
                                                    <?= e($estado) ?>
                                                </option>

                                            <?php endforeach; ?>

                                        </select>

                                    </form>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </div>


</div>


<?php

require_once __DIR__
    . '/../includes/footer.php';

?>
