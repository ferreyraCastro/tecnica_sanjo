<?php
// ============================================================
// COLEGIO SAN JOSÉ
// Sistema de Gestión Técnica
// Archivo: /tecnica/turnos.php
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/funciones.php';
require_once __DIR__ . '/includes/auth.php';


// ============================================================
// SOLO TÉCNICOS / ADMINISTRADORES
//
// Esta es una vista de coordinación entre técnicos: NO se
// expone a docentes, que solamente ven sus propios tickets
// (ver puedeVerSolicitud() en includes/auth.php).
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
// FILTROS
// ============================================================

$filtroTecnico =
    (int)(
        $_GET['id_tecnico']
        ?? 0
    );

$filtroDesde =
    limpiarTexto(
        $_GET['desde'] ?? ''
    );

$filtroHasta =
    limpiarTexto(
        $_GET['hasta'] ?? ''
    );


$tecnicos = obtenerTecnicos($conexion);


$turnos = obtenerTurnos(
    $conexion,
    $filtroTecnico > 0
        ? $filtroTecnico
        : null,
    $filtroDesde !== ''
        ? $filtroDesde
        : null,
    $filtroHasta !== ''
        ? $filtroHasta
        : null
);


// ============================================================
// AGRUPAR POR FECHA
// ============================================================

$turnosPorFecha = [];

foreach ($turnos as $turno) {

    $turnosPorFecha[$turno['fecha']][] = $turno;
}


// ============================================================
// HEADER
// ============================================================

require_once __DIR__
    . '/includes/header.php';

?>


<style>

.turnos-wrapper {

    max-width: 1450px;

    margin: 0 auto;

    padding: 5px 12px 45px;

}


.turnos-hero {

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


.turnos-hero::after {

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


.turnos-hero h1 {

    margin: 0 0 7px;

    font-size: 28px;

    font-weight: 800;

}


.turnos-hero p {

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


.dia-card {

    background: #FFFFFF;

    border: 1px solid #ECECEC;

    border-radius: 16px;

    overflow: hidden;

    box-shadow:
        0 5px 18px
        rgba(0,0,0,.05);

    margin-bottom: 20px;

}


.dia-card-header {

    padding: 14px 18px;

    background: #FAFAFA;

    border-bottom: 1px solid #EEEEEE;

    color: #760000;

    font-weight: 800;

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


<div class="turnos-wrapper">


    <section class="turnos-hero">

        <div class="row align-items-center">

            <div class="col-lg-8">

                <div class="hero-content">

                    <h1>

                        <i class="bi bi-calendar-check me-1"></i>

                        Turnos de reparación

                    </h1>

                    <p>

                        Coordiná el trabajo del equipo:
                        consultá los turnos programados de
                        todos los técnicos, sus prioridades
                        y horarios.

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
         FILTROS
    ====================================================== -->

    <div class="filtros-card">

        <form
            method="GET"
            action="<?= url('turnos.php') ?>"
            class="row g-3 align-items-end"
        >

            <div class="col-md-4">

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


            <div class="col-md-3">

                <label for="desde" class="form-label">Desde</label>

                <input
                    type="date"
                    name="desde"
                    id="desde"
                    class="form-control"
                    value="<?= e($filtroDesde) ?>"
                >

            </div>


            <div class="col-md-3">

                <label for="hasta" class="form-label">Hasta</label>

                <input
                    type="date"
                    name="hasta"
                    id="hasta"
                    class="form-control"
                    value="<?= e($filtroHasta) ?>"
                >

            </div>


            <div class="col-md-2">

                <button type="submit" class="btn btn-filtrar w-100">
                    <i class="bi bi-funnel me-1"></i>
                    Filtrar
                </button>

            </div>

        </form>

    </div>


    <!-- =====================================================
         LISTADO
    ====================================================== -->

    <?php if (empty($turnosPorFecha)): ?>

        <div class="empty">

            <i class="bi bi-calendar-x"></i>

            No hay turnos programados con los filtros seleccionados.

        </div>

    <?php else: ?>

        <?php foreach ($turnosPorFecha as $fecha => $turnosDia): ?>

            <div class="dia-card">

                <div class="dia-card-header">

                    <i class="bi bi-calendar3 me-1"></i>

                    <?= e(fechaCorta($fecha)) ?>

                    <span class="text-muted fw-normal">
                        (<?= count($turnosDia) ?>
                        <?= count($turnosDia) === 1 ? 'turno' : 'turnos' ?>)
                    </span>

                </div>


                <div class="table-responsive">

                    <table class="table mb-0">

                        <thead>
                            <tr>
                                <th>Horario</th>
                                <th>Técnico</th>
                                <th>Ticket</th>
                                <th>Tipo</th>
                                <th>Prioridad</th>
                                <th>Sector</th>
                                <th>Docente</th>
                                <th>Estado</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($turnosDia as $turno): ?>

                                <tr>

                                    <td>
                                        <?= e(horaCorta($turno['hora_desde'])) ?>
                                        -
                                        <?= e(horaCorta($turno['hora_hasta'])) ?>
                                    </td>

                                    <td>
                                        <?= e($turno['tecnico']) ?>
                                    </td>

                                    <td>

                                        <a
                                            href="<?= url(
                                                'ver_solicitud.php?id='
                                                . (int)$turno['id_solicitud']
                                            ) ?>"
                                        >

                                            <?= e(
                                                numeroTicket(
                                                    (int)$turno['id_solicitud']
                                                )
                                            ) ?>
                                            -
                                            <?= e($turno['titulo']) ?>

                                        </a>

                                    </td>

                                    <td>

                                        <i class="<?= e(iconoTipo($turno['tipo'])) ?> me-1"></i>

                                        <?= e(nombreTipo($turno['tipo'])) ?>

                                    </td>

                                    <td>

                                        <span
                                            class="badge <?= e(
                                                clasePrioridad($turno['prioridad'])
                                            ) ?>"
                                        >
                                            <?= e($turno['prioridad']) ?>
                                        </span>

                                    </td>

                                    <td>
                                        <?= e($turno['sector'] ?? '-') ?>
                                    </td>

                                    <td>
                                        <?= e($turno['docente']) ?>
                                    </td>

                                    <td>
                                        <?= e($turno['estado']) ?>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            </div>

        <?php endforeach; ?>

    <?php endif; ?>


</div>


<?php

require_once __DIR__
    . '/includes/footer.php';

?>
