<?php
// ============================================================
// COLEGIO SAN JOSÉ
// Sistema de Gestión Técnica
// Archivo: /tecnica/tecnico/agenda.php
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

$error = '';


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
            'Location: ' . url('tecnico/agenda.php')
        );

        exit;
    }

    $accion =
        limpiarTexto(
            $_POST['accion'] ?? ''
        );


    // ========================================================
    // REPROGRAMAR
    // ========================================================

    if ($accion === 'reprogramar') {

        $idTurno =
            (int)(
                $_POST['id_turno']
                ?? 0
            );

        $nuevaFecha =
            limpiarTexto(
                $_POST['nueva_fecha'] ?? ''
            );

        $nuevaHoraDesde =
            limpiarTexto(
                $_POST['nueva_hora_desde'] ?? ''
            );

        $nuevaHoraHasta =
            limpiarTexto(
                $_POST['nueva_hora_hasta'] ?? ''
            );

        $motivo =
            limpiarTexto(
                $_POST['motivo'] ?? ''
            );

        $tipoHoras =
            limpiarTexto(
                $_POST['tipo_horas'] ?? ''
            );

        $semanaCompensar =
            limpiarTexto(
                $_POST['semana_compensar'] ?? ''
            );


        if ($idTurno <= 0) {

            $error =
                'El turno indicado no es válido.';

        } elseif (
            !preg_match('/^\d{4}-\d{2}-\d{2}$/', $nuevaFecha)
        ) {

            $error =
                'Ingresá una nueva fecha válida.';

        } elseif (
            !preg_match('/^\d{2}:\d{2}$/', $nuevaHoraDesde)
            ||
            !preg_match('/^\d{2}:\d{2}$/', $nuevaHoraHasta)
        ) {

            $error =
                'Ingresá correctamente el nuevo horario.';

        } elseif (
            strtotime($nuevaHoraHasta) <= strtotime($nuevaHoraDesde)
        ) {

            $error =
                'La hora de finalización debe ser posterior a la hora de inicio.';

        } elseif ($motivo === '') {

            $error =
                'Indicá el motivo de la reprogramación.';

        } elseif (
            !in_array($tipoHoras, ['Hora extra', 'Compensacion'], true)
        ) {

            $error =
                'Seleccioná el tipo de horas generadas.';

        } elseif (
            $tipoHoras === 'Compensacion'
            &&
            !preg_match('/^\d{4}-\d{2}-\d{2}$/', $semanaCompensar)
        ) {

            $error =
                'Indicá la semana en la que se compensarán las horas.';
        }


        // ====================================================
        // VERIFICAR QUE EL TURNO PERTENECE AL TÉCNICO
        // (excepto administrador, que puede reprogramar
        // cualquier turno)
        // ====================================================

        if ($error === '') {

            $turnoActual = obtenerTurno($conexion, $idTurno);

            if (!$turnoActual) {

                $error = 'El turno no existe.';

            } elseif (
                !esAdministrador()
                &&
                (int)$turnoActual['id_tecnico'] !== $idTecnico
            ) {

                $error = 'Ese turno no pertenece a tu agenda.';
            }
        }


        if ($error === '') {

            $resultado = reprogramarTurno(
                $conexion,
                $idTurno,
                $nuevaFecha,
                $nuevaHoraDesde . ':00',
                $nuevaHoraHasta . ':00',
                $motivo,
                $tipoHoras,
                $tipoHoras === 'Compensacion'
                    ? $semanaCompensar
                    : null,
                $idTecnico
            );

            if ($resultado['ok']) {

                flash(
                    'success',
                    'El turno fue reprogramado correctamente.'
                );

                header(
                    'Location: ' . url('tecnico/agenda.php')
                );

                exit;

            } else {

                $error = $resultado['error'];
            }
        }
    }


    // ========================================================
    // MARCAR COMPLETADO
    // ========================================================

    elseif ($accion === 'completar') {

        $idTurno =
            (int)(
                $_POST['id_turno']
                ?? 0
            );

        if ($idTurno > 0) {

            $turnoActual = obtenerTurno($conexion, $idTurno);

            if (
                $turnoActual
                &&
                (
                    esAdministrador()
                    ||
                    (int)$turnoActual['id_tecnico'] === $idTecnico
                )
            ) {

                actualizarEstadoTurno(
                    $conexion,
                    $idTurno,
                    'Completado'
                );

                flash(
                    'success',
                    'El turno fue marcado como completado.'
                );
            }
        }

        header(
            'Location: ' . url('tecnico/agenda.php')
        );

        exit;
    }
}


// ============================================================
// DATOS DE LA VISTA
// ============================================================

$misTurnos = obtenerTurnos(
    $conexion,
    $idTecnico
);

$flash = obtenerFlash();


// ============================================================
// HEADER
// ============================================================

require_once __DIR__
    . '/../includes/header.php';

?>


<style>

.agenda-wrapper {

    max-width: 1300px;

    margin: 0 auto;

    padding: 5px 12px 45px;

}


.agenda-hero {

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


.agenda-hero::after {

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


.agenda-hero h1 {

    margin: 0 0 7px;

    font-size: 28px;

    font-weight: 800;

}


.agenda-hero p {

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


.turno-card {

    background: #FFFFFF;

    border: 1px solid #ECECEC;

    border-radius: 16px;

    padding: 18px 20px;

    margin-bottom: 16px;

    box-shadow:
        0 4px 15px
        rgba(0,0,0,.04);

}


.turno-fecha {

    color: #760000;

    font-weight: 800;

    font-size: 15px;

}


.turno-hora {

    color: #555555;

    font-weight: 700;

}


.turno-meta {

    color: #888888;

    font-size: 12px;

    margin-top: 4px;

}


.turno-acciones {

    display: flex;

    gap: 8px;

    margin-top: 12px;

}


.btn-reprogramar {

    border: 1px solid #E1BEBE;

    color: #B12626;

    background: #FFF6F6;

    border-radius: 8px;

    padding: 7px 12px;

    font-size: 12px;

    font-weight: 700;

}


.btn-reprogramar:hover {

    background: #B12626;

    color: #FFFFFF;

}


.btn-completar {

    border: none;

    color: #FFFFFF;

    background: #198754;

    border-radius: 8px;

    padding: 7px 12px;

    font-size: 12px;

    font-weight: 700;

}


.btn-completar:hover {

    background: #146c43;

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


<div class="agenda-wrapper">


    <section class="agenda-hero">

        <div class="row align-items-center">

            <div class="col-lg-8">

                <div class="hero-content">

                    <h1>

                        <i class="bi bi-calendar2-week me-1"></i>

                        Mi agenda

                    </h1>

                    <p>

                        Tus próximos turnos de reparación.
                        Podés reprogramarlos o marcarlos
                        como completados.

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


    <?php if (empty($misTurnos)): ?>

        <div class="empty">

            <i class="bi bi-calendar-x"></i>

            No tenés turnos programados.

        </div>

    <?php else: ?>

        <?php foreach ($misTurnos as $turno): ?>

            <div class="turno-card">

                <div class="d-flex flex-wrap justify-content-between gap-2">

                    <div>

                        <div class="turno-fecha">
                            <?= e(fechaCorta($turno['fecha'])) ?>
                        </div>

                        <div class="turno-hora">
                            <?= e(horaCorta($turno['hora_desde'])) ?>
                            a
                            <?= e(horaCorta($turno['hora_hasta'])) ?>
                        </div>

                        <div class="turno-meta">

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

                            · <?= e($turno['sector'] ?? '-') ?>
                            · <?= e($turno['docente']) ?>

                        </div>

                    </div>


                    <div class="text-end">

                        <span
                            class="badge <?= e(clasePrioridad($turno['prioridad'])) ?>"
                        >
                            <?= e($turno['prioridad']) ?>
                        </span>

                        <div class="turno-meta mt-1">
                            <?= e($turno['estado']) ?>
                        </div>

                    </div>

                </div>


                <?php if (
                    in_array($turno['estado'], ['Programado', 'Confirmado'], true)
                ): ?>

                    <div class="turno-acciones">

                        <button
                            type="button"
                            class="btn-reprogramar"
                            data-bs-toggle="modal"
                            data-bs-target="#modalReprogramar<?= (int)$turno['id_turno'] ?>"
                        >

                            <i class="bi bi-calendar-x me-1"></i>
                            Reprogramar

                        </button>


                        <form
                            method="POST"
                            action="<?= url('tecnico/agenda.php') ?>"
                            class="m-0"
                        >

                            <?= csrfInput() ?>

                            <input type="hidden" name="accion" value="completar">

                            <input
                                type="hidden"
                                name="id_turno"
                                value="<?= (int)$turno['id_turno'] ?>"
                            >

                            <button type="submit" class="btn-completar">
                                <i class="bi bi-check2-circle me-1"></i>
                                Marcar completado
                            </button>

                        </form>

                    </div>


                    <!-- =============================================
                         MODAL REPROGRAMAR
                    ============================================== -->

                    <div
                        class="modal fade"
                        id="modalReprogramar<?= (int)$turno['id_turno'] ?>"
                        tabindex="-1"
                    >

                        <div class="modal-dialog">

                            <div class="modal-content">

                                <form
                                    method="POST"
                                    action="<?= url('tecnico/agenda.php') ?>"
                                >

                                    <?= csrfInput() ?>

                                    <input type="hidden" name="accion" value="reprogramar">

                                    <input
                                        type="hidden"
                                        name="id_turno"
                                        value="<?= (int)$turno['id_turno'] ?>"
                                    >


                                    <div class="modal-header">

                                        <h5 class="modal-title">
                                            Reprogramar turno
                                        </h5>

                                        <button
                                            type="button"
                                            class="btn-close"
                                            data-bs-dismiss="modal"
                                        ></button>

                                    </div>


                                    <div class="modal-body">

                                        <div class="row g-3">

                                            <div class="col-6">

                                                <label class="form-label">Nueva fecha</label>

                                                <input
                                                    type="date"
                                                    name="nueva_fecha"
                                                    class="form-control"
                                                    required
                                                >

                                            </div>


                                            <div class="col-3">

                                                <label class="form-label">Desde</label>

                                                <input
                                                    type="time"
                                                    name="nueva_hora_desde"
                                                    class="form-control"
                                                    required
                                                >

                                            </div>


                                            <div class="col-3">

                                                <label class="form-label">Hasta</label>

                                                <input
                                                    type="time"
                                                    name="nueva_hora_hasta"
                                                    class="form-control"
                                                    required
                                                >

                                            </div>


                                            <div class="col-12">

                                                <label class="form-label">
                                                    Motivo de la reprogramación
                                                </label>

                                                <textarea
                                                    name="motivo"
                                                    class="form-control"
                                                    maxlength="500"
                                                    required
                                                ></textarea>

                                            </div>


                                            <div class="col-12">

                                                <label class="form-label d-block">
                                                    Tipo de horas generadas
                                                </label>

                                                <div class="form-check form-check-inline">

                                                    <input
                                                        class="form-check-input tipo-horas-radio"
                                                        type="radio"
                                                        name="tipo_horas"
                                                        value="Hora extra"
                                                        id="horaExtra<?= (int)$turno['id_turno'] ?>"
                                                        checked
                                                    >

                                                    <label
                                                        class="form-check-label"
                                                        for="horaExtra<?= (int)$turno['id_turno'] ?>"
                                                    >
                                                        Hora extra
                                                    </label>

                                                </div>


                                                <div class="form-check form-check-inline">

                                                    <input
                                                        class="form-check-input tipo-horas-radio"
                                                        type="radio"
                                                        name="tipo_horas"
                                                        value="Compensacion"
                                                        id="compensacion<?= (int)$turno['id_turno'] ?>"
                                                    >

                                                    <label
                                                        class="form-check-label"
                                                        for="compensacion<?= (int)$turno['id_turno'] ?>"
                                                    >
                                                        Compensación
                                                    </label>

                                                </div>

                                            </div>


                                            <div
                                                class="col-12 semana-compensar-box"
                                                style="display:none;"
                                            >

                                                <label class="form-label">
                                                    Semana a compensar
                                                    (lunes de esa semana)
                                                </label>

                                                <input
                                                    type="date"
                                                    name="semana_compensar"
                                                    class="form-control"
                                                >

                                            </div>

                                        </div>

                                    </div>


                                    <div class="modal-footer">

                                        <button
                                            type="button"
                                            class="btn btn-secondary"
                                            data-bs-dismiss="modal"
                                        >
                                            Cancelar
                                        </button>

                                        <button type="submit" class="btn btn-sanjo">
                                            Confirmar reprogramación
                                        </button>

                                    </div>

                                </form>

                            </div>

                        </div>

                    </div>

                <?php endif; ?>

            </div>

        <?php endforeach; ?>

    <?php endif; ?>


</div>


<script>

document.querySelectorAll('.modal').forEach(function (modal) {

    const radios = modal.querySelectorAll('.tipo-horas-radio');

    const box = modal.querySelector('.semana-compensar-box');

    if (!box) {
        return;
    }

    function actualizar() {

        const seleccionado = modal.querySelector(
            'input[name="tipo_horas"]:checked'
        );

        box.style.display =
            seleccionado && seleccionado.value === 'Compensacion'
                ? 'block'
                : 'none';

    }

    radios.forEach(function (radio) {
        radio.addEventListener('change', actualizar);
    });

    actualizar();

});

</script>


<?php

require_once __DIR__
    . '/../includes/footer.php';

?>
