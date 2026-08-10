<?php
// ============================================================
// COLEGIO SAN JOSÉ
// Sistema de Gestión Técnica
// Archivo: /tecnica/tecnico/horario.php
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
            'Location: ' . url('tecnico/horario.php')
        );

        exit;
    }

    $accion =
        limpiarTexto(
            $_POST['accion'] ?? ''
        );


    if ($accion === 'agregar') {

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


        if (
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
            <= strtotime($horaDesde)
        ) {

            $error =
                'La hora de finalización debe ser posterior a la hora de inicio.';
        }


        if ($error === '') {

            $ok = guardarHorarioTecnico(
                $conexion,
                $idTecnico,
                $dia,
                $horaDesde . ':00',
                $horaHasta . ':00'
            );

            if ($ok !== false) {

                flash(
                    'success',
                    'El horario fue agregado correctamente.'
                );

                header(
                    'Location: ' . url('tecnico/horario.php')
                );

                exit;

            } else {

                $error =
                    'No se pudo agregar el horario.';
            }
        }

    } elseif ($accion === 'eliminar') {

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
            'Location: ' . url('tecnico/horario.php')
        );

        exit;
    }
}


// ============================================================
// DATOS DE LA VISTA
// ============================================================

$horario =
    obtenerHorarioTecnico(
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

.horario-tecnico-wrapper {

    max-width: 1000px;

    margin: 0 auto;

    padding: 5px 12px 45px;

}


.horario-hero {

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


.horario-hero::after {

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


.horario-hero h1 {

    margin: 0 0 7px;

    font-size: 28px;

    font-weight: 800;

}


.horario-hero p {

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


.horario-card {

    background: #FFFFFF;

    border: 1px solid #ECECEC;

    border-radius: 18px;

    overflow: hidden;

    box-shadow:
        0 5px 19px
        rgba(0,0,0,.05);

    margin-bottom: 24px;

}


.horario-card-header {

    padding: 18px 20px;

    border-bottom: 1px solid #EEEEEE;

}


.horario-card-header h5 {

    margin: 0;

    color: #760000;

    font-size: 16px;

    font-weight: 800;

}


.horario-card-body {

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

    padding: 13px 15px;

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


.hora-texto {

    font-weight: 700;

    color: #333333;

}


.btn-eliminar {

    width: 34px;

    height: 34px;

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


.empty {

    padding: 30px 20px;

    color: #888888;

    text-align: center;

}


.empty i {

    display: block;

    font-size: 34px;

    color: #D0D0D0;

    margin-bottom: 8px;

}

</style>


<div class="horario-tecnico-wrapper">


    <section class="horario-hero">

        <div class="row align-items-center">

            <div class="col-lg-8">

                <div class="hero-content">

                    <h1>

                        <i class="bi bi-calendar2-week me-1"></i>

                        Mi horario de trabajo

                    </h1>

                    <p>

                        Configurá tus horarios semanales
                        habituales. Los turnos de reparación
                        solamente podrán programarse dentro
                        de estos rangos.

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


    <div class="horario-card">

        <div class="horario-card-header">

            <h5>

                <i class="bi bi-plus-circle me-2"></i>

                Agregar horario

            </h5>

        </div>


        <div class="horario-card-body">

            <form
                method="POST"
                action="<?= url('tecnico/horario.php') ?>"
                class="row g-3 align-items-end"
            >

                <?= csrfInput() ?>

                <input type="hidden" name="accion" value="agregar">


                <div class="col-md-4">

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


                <div class="col-md-3">

                    <label for="hora_desde" class="form-label">Desde</label>

                    <input
                        type="time"
                        name="hora_desde"
                        id="hora_desde"
                        class="form-control"
                        required
                    >

                </div>


                <div class="col-md-3">

                    <label for="hora_hasta" class="form-label">Hasta</label>

                    <input
                        type="time"
                        name="hora_hasta"
                        id="hora_hasta"
                        class="form-control"
                        required
                    >

                </div>


                <div class="col-md-2">

                    <button type="submit" class="btn btn-guardar w-100">
                        <i class="bi bi-plus-lg"></i>
                        Agregar
                    </button>

                </div>

            </form>

        </div>

    </div>


    <div class="horario-card mb-0">

        <div class="horario-card-header">

            <h5>

                <i class="bi bi-list-ul me-2"></i>

                Mis horarios cargados

            </h5>

        </div>


        <?php if (empty($horario)): ?>

            <div class="empty">

                <i class="bi bi-calendar-x"></i>

                Todavía no cargaste tus horarios de trabajo.

            </div>

        <?php else: ?>

            <?php foreach ($horario as $fila): ?>

                <div class="dia-fila">

                    <div class="dia-nombre">

                        <?= e(
                            $fila['dia'] === 'Miercoles'
                                ? 'Miércoles'
                                : $fila['dia']
                        ) ?>

                    </div>


                    <div class="hora-texto">

                        <?= e(horaCorta($fila['hora_desde'])) ?>
                        a
                        <?= e(horaCorta($fila['hora_hasta'])) ?>

                    </div>


                    <form
                        method="POST"
                        action="<?= url('tecnico/horario.php') ?>"
                        class="m-0 form-eliminar"
                    >

                        <?= csrfInput() ?>

                        <input type="hidden" name="accion" value="eliminar">

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


<script>

document
    .querySelectorAll('.form-eliminar')
    .forEach(function (formulario) {

        formulario.addEventListener('submit', function (evento) {

            const confirmar = confirm(
                '¿Seguro que querés eliminar este horario?'
            );

            if (!confirmar) {
                evento.preventDefault();
            }

        });

    });

</script>


<?php

require_once __DIR__
    . '/../includes/footer.php';

?>
