<?php
// ============================================================
// COLEGIO SAN JOSÉ
// Sistema de Gestión Técnica
// Archivo: /tecnica/admin/horarios_tecnicos.php
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

$tecnicos = obtenerTecnicos($conexion);

$errorHorario = '';

$errorTurno = '';


// ============================================================
// TÉCNICO SELECCIONADO
// ============================================================

$idTecnicoSeleccionado =
    (int)(
        $_GET['id_tecnico']
        ?? $_POST['id_tecnico']
        ?? 0
    );

if (
    $idTecnicoSeleccionado <= 0
    &&
    !empty($tecnicos)
) {

    $idTecnicoSeleccionado =
        (int)$tecnicos[0]['id_usuario'];
}


// ============================================================
// SOLICITUDES ABIERTAS (PARA PROGRAMAR TURNOS)
// ============================================================

$solicitudesAbiertas =
    $conexion->query("
        SELECT
            s.id_solicitud,
            s.titulo,
            s.prioridad

        FROM solicitudes s

        WHERE s.estado NOT IN (
            'Resuelta',
            'Cerrada',
            'Cancelada'
        )

        ORDER BY s.fecha_creacion DESC
    ")->fetchAll(PDO::FETCH_ASSOC);


// ============================================================
// PROCESAR ACCIONES
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

        header(
            'Location: '
            . url(
                'admin/horarios_tecnicos.php?id_tecnico='
                . $idTecnicoSeleccionado
            )
        );

        exit;
    }

    $accion =
        limpiarTexto(
            $_POST['accion'] ?? ''
        );


    // ========================================================
    // AGREGAR HORARIO
    // ========================================================

    if ($accion === 'agregar_horario') {

        $dia =
            limpiarTexto(
                $_POST['dia'] ?? ''
            );

        $horaDesde =
            limpiarTexto(
                $_POST['hora_desde'] ?? ''
            );

        $horaHasta =
            limpiarTexto(
                $_POST['hora_hasta'] ?? ''
            );


        if ($idTecnicoSeleccionado <= 0) {

            $errorHorario =
                'Seleccioná un técnico.';

        } elseif (
            !in_array($dia, $diasPermitidos, true)
        ) {

            $errorHorario =
                'Seleccioná un día válido.';

        } elseif (
            !preg_match('/^\d{2}:\d{2}$/', $horaDesde)
            ||
            !preg_match('/^\d{2}:\d{2}$/', $horaHasta)
        ) {

            $errorHorario =
                'Ingresá correctamente el rango horario.';

        } elseif (
            strtotime($horaHasta) <= strtotime($horaDesde)
        ) {

            $errorHorario =
                'La hora de finalización debe ser posterior a la hora de inicio.';
        }

        if ($errorHorario === '') {

            guardarHorarioTecnico(
                $conexion,
                $idTecnicoSeleccionado,
                $dia,
                $horaDesde . ':00',
                $horaHasta . ':00'
            );

            flash(
                'success',
                'El horario fue agregado correctamente.'
            );

            header(
                'Location: '
                . url(
                    'admin/horarios_tecnicos.php?id_tecnico='
                    . $idTecnicoSeleccionado
                )
            );

            exit;
        }
    }


    // ========================================================
    // ELIMINAR HORARIO
    // ========================================================

    elseif ($accion === 'eliminar_horario') {

        $idHorarioTecnico =
            (int)(
                $_POST['id_horario_tecnico']
                ?? 0
            );

        if ($idHorarioTecnico > 0) {

            eliminarHorarioTecnico(
                $conexion,
                $idHorarioTecnico
            );

            flash(
                'success',
                'El horario fue eliminado correctamente.'
            );
        }

        header(
            'Location: '
            . url(
                'admin/horarios_tecnicos.php?id_tecnico='
                . $idTecnicoSeleccionado
            )
        );

        exit;
    }


    // ========================================================
    // CREAR TURNO
    // ========================================================

    elseif ($accion === 'crear_turno') {

        $idSolicitudTurno =
            (int)(
                $_POST['id_solicitud'] ?? 0
            );

        $idTecnicoTurno =
            (int)(
                $_POST['id_tecnico_turno'] ?? 0
            );

        $fecha =
            limpiarTexto(
                $_POST['fecha'] ?? ''
            );

        $horaDesdeTurno =
            limpiarTexto(
                $_POST['hora_desde_turno'] ?? ''
            );

        $horaHastaTurno =
            limpiarTexto(
                $_POST['hora_hasta_turno'] ?? ''
            );

        $horasEstimadas =
            limpiarTexto(
                $_POST['horas_estimadas'] ?? ''
            );


        if ($idSolicitudTurno <= 0) {

            $errorTurno =
                'Seleccioná la solicitud a programar.';

        } elseif ($idTecnicoTurno <= 0) {

            $errorTurno =
                'Seleccioná el técnico responsable del turno.';

        } elseif (
            !preg_match(
                '/^\d{4}-\d{2}-\d{2}$/',
                $fecha
            )
        ) {

            $errorTurno =
                'Ingresá una fecha válida.';

        } elseif (
            !preg_match('/^\d{2}:\d{2}$/', $horaDesdeTurno)
            ||
            !preg_match('/^\d{2}:\d{2}$/', $horaHastaTurno)
        ) {

            $errorTurno =
                'Ingresá correctamente el rango horario del turno.';

        } elseif (
            strtotime($horaHastaTurno) <= strtotime($horaDesdeTurno)
        ) {

            $errorTurno =
                'La hora de finalización debe ser posterior a la hora de inicio.';

        } elseif (
            !is_numeric($horasEstimadas)
            ||
            (float)$horasEstimadas <= 0
        ) {

            $errorTurno =
                'Ingresá una cantidad de horas estimadas válida.';
        }

        if ($errorTurno === '') {

            $resultado = crearTurno(
                $conexion,
                $idSolicitudTurno,
                $idTecnicoTurno,
                $fecha,
                $horaDesdeTurno . ':00',
                $horaHastaTurno . ':00',
                (float)$horasEstimadas,
                (int)usuarioId()
            );

            if ($resultado['ok']) {

                flash(
                    'success',
                    'El turno fue programado correctamente.'
                );

                header(
                    'Location: '
                    . url(
                        'admin/horarios_tecnicos.php?id_tecnico='
                        . $idTecnicoTurno
                    )
                );

                exit;

            } else {

                $errorTurno = $resultado['error'];

                $idTecnicoSeleccionado = $idTecnicoTurno;
            }
        }
    }
}


// ============================================================
// DATOS DE LA VISTA
// ============================================================

$horarioTecnico = [];

if ($idTecnicoSeleccionado > 0) {

    $horarioTecnico =
        obtenerHorarioTecnico(
            $conexion,
            $idTecnicoSeleccionado
        );
}

$turnosTecnico = [];

if ($idTecnicoSeleccionado > 0) {

    $turnosTecnico =
        obtenerTurnos(
            $conexion,
            $idTecnicoSeleccionado
        );
}

$flash = obtenerFlash();


// ============================================================
// HEADER
// ============================================================

require_once __DIR__
    . '/../includes/header.php';

?>


<style>

.admin-ht-wrapper {

    max-width: 1450px;

    margin: 0 auto;

    padding: 5px 12px 45px;

}


.ht-hero {

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


.ht-hero::after {

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


.ht-hero h1 {

    margin: 0 0 7px;

    font-size: 28px;

    font-weight: 800;

}


.ht-hero p {

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

    margin-bottom: 24px;

}


.admin-card-header {

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


.dia-fila {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 12px;

    padding: 12px 15px;

    border-bottom: 1px solid #EFEFEF;

}


.dia-fila:last-child {

    border-bottom: 0;

}


.dia-nombre {

    color: #760000;

    font-weight: 800;

    min-width: 90px;

}


.btn-eliminar {

    width: 32px;

    height: 32px;

    border-radius: 8px;

    border: none;

    display: inline-flex;

    align-items: center;

    justify-content: center;

    color: #B12626;

    background: #FFF0F0;

}


.btn-eliminar:hover {

    color: #FFFFFF;

    background: #B12626;

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

    padding: 30px 20px;

    color: #888888;

    text-align: center;

}


.empty i {

    display: block;

    font-size: 32px;

    color: #D0D0D0;

    margin-bottom: 8px;

}

</style>


<div class="admin-ht-wrapper">


    <section class="ht-hero">

        <div class="row align-items-center">

            <div class="col-lg-8">

                <div class="hero-content">

                    <h1>

                        <i class="bi bi-calendar-week me-1"></i>

                        Horarios de técnicos y turnos

                    </h1>

                    <p>

                        Configurá el horario semanal de cada
                        técnico y programá turnos de reparación
                        dentro de su disponibilidad.

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


    <!-- =====================================================
         SELECCIONAR TÉCNICO
    ====================================================== -->

    <div class="admin-card">

        <div class="admin-card-body">

            <form
                method="GET"
                action="<?= url('admin/horarios_tecnicos.php') ?>"
                class="row g-3 align-items-end"
            >

                <div class="col-md-8">

                    <label for="id_tecnico" class="form-label">
                        Técnico
                    </label>

                    <select
                        name="id_tecnico"
                        id="id_tecnico"
                        class="form-select"
                        onchange="this.form.submit()"
                    >

                        <?php foreach ($tecnicos as $tecnico): ?>

                            <option
                                value="<?= (int)$tecnico['id_usuario'] ?>"
                                <?= $idTecnicoSeleccionado === (int)$tecnico['id_usuario']
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


                <div class="col-md-4">

                    <button type="submit" class="btn btn-guardar w-100">
                        <i class="bi bi-search me-1"></i>
                        Ver horario
                    </button>

                </div>

            </form>

        </div>

    </div>


    <?php if (empty($tecnicos)): ?>

        <div class="empty">
            No hay técnicos activos registrados.
        </div>

    <?php else: ?>


        <div class="row g-4">


            <!-- =============================================
                 HORARIO DEL TÉCNICO
            ============================================== -->

            <div class="col-xl-5">

                <div class="admin-card">

                    <div class="admin-card-header">

                        <h5>

                            <i class="bi bi-clock me-2"></i>

                            Horario semanal

                        </h5>

                    </div>


                    <div class="admin-card-body">


                        <?php if ($errorHorario !== ''): ?>

                            <div class="alert alert-danger">
                                <?= e($errorHorario) ?>
                            </div>

                        <?php endif; ?>


                        <form
                            method="POST"
                            action="<?= url('admin/horarios_tecnicos.php') ?>"
                            class="row g-3 align-items-end mb-4"
                        >

                            <?= csrfInput() ?>

                            <input type="hidden" name="accion" value="agregar_horario">

                            <input
                                type="hidden"
                                name="id_tecnico"
                                value="<?= $idTecnicoSeleccionado ?>"
                            >


                            <div class="col-12">

                                <label for="dia" class="form-label">Día</label>

                                <select
                                    name="dia"
                                    id="dia"
                                    class="form-select"
                                    required
                                >

                                    <?php foreach ($diasPermitidos as $dia): ?>

                                        <option value="<?= e($dia) ?>">
                                            <?= e(
                                                $dia === 'Miercoles'
                                                    ? 'Miércoles'
                                                    : $dia
                                            ) ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>


                            <div class="col-6">

                                <label for="hora_desde" class="form-label">Desde</label>

                                <input
                                    type="time"
                                    name="hora_desde"
                                    id="hora_desde"
                                    class="form-control"
                                    required
                                >

                            </div>


                            <div class="col-6">

                                <label for="hora_hasta" class="form-label">Hasta</label>

                                <input
                                    type="time"
                                    name="hora_hasta"
                                    id="hora_hasta"
                                    class="form-control"
                                    required
                                >

                            </div>


                            <div class="col-12">

                                <button type="submit" class="btn btn-guardar w-100">
                                    <i class="bi bi-plus-lg"></i>
                                    Agregar horario
                                </button>

                            </div>

                        </form>


                        <?php if (empty($horarioTecnico)): ?>

                            <div class="empty">

                                <i class="bi bi-calendar-x"></i>

                                Este técnico todavía no tiene
                                horarios cargados.

                            </div>

                        <?php else: ?>

                            <?php foreach ($horarioTecnico as $fila): ?>

                                <div class="dia-fila">

                                    <div class="dia-nombre">

                                        <?= e(
                                            $fila['dia'] === 'Miercoles'
                                                ? 'Miércoles'
                                                : $fila['dia']
                                        ) ?>

                                    </div>


                                    <div>

                                        <?= e(horaCorta($fila['hora_desde'])) ?>
                                        a
                                        <?= e(horaCorta($fila['hora_hasta'])) ?>

                                    </div>


                                    <form
                                        method="POST"
                                        action="<?= url('admin/horarios_tecnicos.php') ?>"
                                        class="m-0"
                                    >

                                        <?= csrfInput() ?>

                                        <input type="hidden" name="accion" value="eliminar_horario">

                                        <input
                                            type="hidden"
                                            name="id_tecnico"
                                            value="<?= $idTecnicoSeleccionado ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="id_horario_tecnico"
                                            value="<?= (int)$fila['id_horario_tecnico'] ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="btn-eliminar"
                                            title="Eliminar"
                                        >
                                            <i class="bi bi-trash"></i>
                                        </button>

                                    </form>

                                </div>

                            <?php endforeach; ?>

                        <?php endif; ?>

                    </div>

                </div>

            </div>


            <!-- =============================================
                 TURNOS
            ============================================== -->

            <div class="col-xl-7">

                <div class="admin-card">

                    <div class="admin-card-header">

                        <h5>

                            <i class="bi bi-calendar-check me-2"></i>

                            Programar turno de reparación

                        </h5>

                    </div>


                    <div class="admin-card-body">


                        <?php if ($errorTurno !== ''): ?>

                            <div class="alert alert-danger">
                                <?= e($errorTurno) ?>
                            </div>

                        <?php endif; ?>


                        <form
                            method="POST"
                            action="<?= url('admin/horarios_tecnicos.php') ?>"
                            class="row g-3"
                        >

                            <?= csrfInput() ?>

                            <input type="hidden" name="accion" value="crear_turno">


                            <div class="col-md-6">

                                <label for="id_solicitud" class="form-label">
                                    Solicitud
                                </label>

                                <select
                                    name="id_solicitud"
                                    id="id_solicitud"
                                    class="form-select"
                                    required
                                >

                                    <option value="">
                                        Seleccionar solicitud...
                                    </option>

                                    <?php foreach ($solicitudesAbiertas as $sol): ?>

                                        <option value="<?= (int)$sol['id_solicitud'] ?>">

                                            <?= e(
                                                numeroTicket((int)$sol['id_solicitud'])
                                            ) ?>
                                            -
                                            <?= e($sol['titulo']) ?>
                                            (<?= e($sol['prioridad']) ?>)

                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>


                            <div class="col-md-6">

                                <label for="id_tecnico_turno" class="form-label">
                                    Técnico
                                </label>

                                <select
                                    name="id_tecnico_turno"
                                    id="id_tecnico_turno"
                                    class="form-select"
                                    required
                                >

                                    <?php foreach ($tecnicos as $tecnico): ?>

                                        <option
                                            value="<?= (int)$tecnico['id_usuario'] ?>"
                                            <?= $idTecnicoSeleccionado === (int)$tecnico['id_usuario']
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


                            <div class="col-md-4">

                                <label for="fecha" class="form-label">Fecha</label>

                                <input
                                    type="date"
                                    name="fecha"
                                    id="fecha"
                                    class="form-control"
                                    required
                                >

                            </div>


                            <div class="col-md-3">

                                <label for="hora_desde_turno" class="form-label">Desde</label>

                                <input
                                    type="time"
                                    name="hora_desde_turno"
                                    id="hora_desde_turno"
                                    class="form-control"
                                    required
                                >

                            </div>


                            <div class="col-md-3">

                                <label for="hora_hasta_turno" class="form-label">Hasta</label>

                                <input
                                    type="time"
                                    name="hora_hasta_turno"
                                    id="hora_hasta_turno"
                                    class="form-control"
                                    required
                                >

                            </div>


                            <div class="col-md-2">

                                <label for="horas_estimadas" class="form-label">Horas</label>

                                <input
                                    type="number"
                                    name="horas_estimadas"
                                    id="horas_estimadas"
                                    class="form-control"
                                    min="0.5"
                                    step="0.5"
                                    value="1"
                                    required
                                >

                            </div>


                            <div class="col-12">

                                <button type="submit" class="btn btn-guardar w-100">
                                    <i class="bi bi-calendar-plus me-1"></i>
                                    Programar turno
                                </button>

                            </div>

                        </form>

                    </div>

                </div>


                <div class="admin-card mb-0">

                    <div class="admin-card-header">

                        <h5>

                            <i class="bi bi-list-ul me-2"></i>

                            Turnos del técnico seleccionado

                        </h5>

                    </div>


                    <?php if (empty($turnosTecnico)): ?>

                        <div class="empty">

                            <i class="bi bi-calendar-x"></i>

                            No hay turnos programados para
                            este técnico.

                        </div>

                    <?php else: ?>

                        <div class="table-responsive">

                            <table class="table mb-0">

                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Horario</th>
                                        <th>Ticket</th>
                                        <th>Prioridad</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>

                                <tbody>

                                    <?php foreach ($turnosTecnico as $turno): ?>

                                        <tr>

                                            <td>
                                                <?= e(fechaCorta($turno['fecha'])) ?>
                                            </td>

                                            <td>
                                                <?= e(horaCorta($turno['hora_desde'])) ?>
                                                -
                                                <?= e(horaCorta($turno['hora_hasta'])) ?>
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

                                                </a>

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
                                                <?= e($turno['estado']) ?>
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

    <?php endif; ?>


</div>


<?php

require_once __DIR__
    . '/../includes/footer.php';

?>
