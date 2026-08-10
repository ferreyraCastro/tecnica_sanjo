<?php
// ============================================================
// COLEGIO SAN JOSÉ
// Sistema de Gestión Técnica
// Archivo: /tecnica/horarios.php
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/funciones.php';
require_once __DIR__ . '/includes/auth.php';


// ============================================================
// REQUERIR LOGIN
// ============================================================

requerirLogin();


// ============================================================
// VERIFICAR USUARIO
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
// OBTENER HORARIOS
// ============================================================

$horariosInformatica =
    obtenerHorarios(
        $conexion,
        'Informatica'
    );


$horariosMantenimiento =
    obtenerHorarios(
        $conexion,
        'Mantenimiento'
    );


// ============================================================
// DÍAS
// ============================================================

$dias = [
    'Lunes',
    'Martes',
    'Miercoles',
    'Jueves',
    'Viernes',
    'Sabado'
];


// ============================================================
// AGRUPAR HORARIOS POR DÍA
// ============================================================

$informaticaPorDia = [];

$mantenimientoPorDia = [];


foreach ($dias as $dia) {

    $informaticaPorDia[$dia] = [];

    $mantenimientoPorDia[$dia] = [];
}


foreach (
    $horariosInformatica
    as $horario
) {

    if (
        isset(
            $informaticaPorDia[
                $horario['dia']
            ]
        )
    ) {

        $informaticaPorDia[
            $horario['dia']
        ][] = $horario;
    }
}


foreach (
    $horariosMantenimiento
    as $horario
) {

    if (
        isset(
            $mantenimientoPorDia[
                $horario['dia']
            ]
        )
    ) {

        $mantenimientoPorDia[
            $horario['dia']
        ][] = $horario;
    }
}


// ============================================================
// HEADER
// ============================================================

require_once __DIR__
    . '/includes/header.php';

?>


<style>

:root {

    --sanjo-rojo: #B12626;
    --sanjo-oscuro: #760000;
    --sanjo-blanco: #FFFFFF;
    --sanjo-fondo: #F5F6F8;

}


/* ============================================================
   CONTENEDOR
============================================================ */

.horarios-wrapper {

    max-width: 1350px;

    margin: 0 auto;

    padding:
        5px 12px
        45px;

}


/* ============================================================
   CABECERA
============================================================ */

.horarios-hero {

    position: relative;

    overflow: hidden;

    background:
        linear-gradient(
            135deg,
            #760000 0%,
            #B12626 100%
        );

    color: #FFFFFF;

    border-radius: 21px;

    padding:
        30px;

    margin-bottom: 25px;

    box-shadow:
        0 9px 28px
        rgba(118,0,0,.16);

}


.horarios-hero::after {

    content: "";

    position: absolute;

    width: 260px;

    height: 260px;

    border-radius: 50%;

    background:
        rgba(255,255,255,.06);

    right: -100px;

    top: -120px;

}


.hero-content {

    position: relative;

    z-index: 2;

}


.horarios-hero h1 {

    margin: 0 0 8px;

    font-size: 28px;

    font-weight: 800;

}


.horarios-hero p {

    margin: 0;

    max-width: 700px;

    color:
        rgba(255,255,255,.78);

}


.hero-actions {

    position: relative;

    z-index: 2;

}


.btn-volver-dashboard {

    display: inline-flex;

    align-items: center;

    gap: 7px;

    background: #FFFFFF;

    color: #760000;

    border: none;

    border-radius: 10px;

    padding:
        10px 17px;

    text-decoration: none;

    font-weight: 700;

}


.btn-volver-dashboard:hover {

    background: #F4F4F4;

    color: #B12626;

}


/* ============================================================
   INFORMACIÓN
============================================================ */

.info-general {

    background: #FFFFFF;

    border:
        1px solid #ECECEC;

    border-radius: 16px;

    padding: 18px 20px;

    margin-bottom: 24px;

    box-shadow:
        0 4px 15px
        rgba(0,0,0,.04);

}


.info-general i {

    color: #B12626;

}


/* ============================================================
   TARJETA ÁREA
============================================================ */

.area-card {

    background: #FFFFFF;

    border:
        1px solid #ECECEC;

    border-radius: 20px;

    overflow: hidden;

    box-shadow:
        0 6px 22px
        rgba(0,0,0,.05);

    height: 100%;

}


.area-header {

    padding:
        23px;

    color: #FFFFFF;

    display: flex;

    align-items: center;

    gap: 14px;

}


.area-header.informatica {

    background:
        linear-gradient(
            135deg,
            #760000,
            #B12626
        );

}


.area-header.mantenimiento {

    background:
        linear-gradient(
            135deg,
            #B12626,
            #760000
        );

}


.area-icon {

    min-width: 52px;

    width: 52px;

    height: 52px;

    border-radius: 14px;

    display: flex;

    align-items: center;

    justify-content: center;

    background:
        rgba(255,255,255,.15);

    border:
        1px solid
        rgba(255,255,255,.20);

    font-size: 23px;

}


.area-header h3 {

    margin: 0;

    font-size: 20px;

    font-weight: 800;

}


.area-header p {

    margin: 4px 0 0;

    color:
        rgba(255,255,255,.72);

    font-size: 12px;

}


.area-body {

    padding: 22px;

}


/* ============================================================
   DÍA
============================================================ */

.dia-card {

    border:
        1px solid #E9E9E9;

    border-radius: 14px;

    margin-bottom: 13px;

    overflow: hidden;

    background: #FFFFFF;

}


.dia-card:last-child {

    margin-bottom: 0;

}


.dia-header {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 12px;

    padding:
        13px 15px;

    background: #FAFAFA;

    border-bottom:
        1px solid #EEEEEE;

}


.dia-nombre {

    display: flex;

    align-items: center;

    gap: 9px;

    color: #760000;

    font-weight: 800;

}


.dia-nombre i {

    color: #B12626;

}


.dia-cantidad {

    color: #8A8A8A;

    font-size: 11px;

}


.horario-lista {

    padding:
        5px 15px;

}


.horario-fila {

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 15px;

    padding:
        13px 0;

    border-bottom:
        1px solid #EFEFEF;

}


.horario-fila:last-child {

    border-bottom: 0;

}


.horario-principal {

    display: flex;

    align-items: flex-start;

    gap: 12px;

}


.hora-icon {

    min-width: 38px;

    width: 38px;

    height: 38px;

    border-radius: 10px;

    background: #F5E7E7;

    color: #760000;

    display: flex;

    justify-content: center;

    align-items: center;

}


.hora-texto {

    font-weight: 800;

    color: #333333;

}


.responsable {

    margin-top: 3px;

    color: #777777;

    font-size: 12px;

}


.observacion {

    margin-top: 5px;

    color: #8B8B8B;

    font-size: 11px;

}


.badge-activo {

    background: #E1F3E7;

    color: #198754;

    padding:
        5px 8px;

    border-radius: 20px;

    font-size: 10px;

    font-weight: 700;

}


/* ============================================================
   SIN HORARIO
============================================================ */

.sin-horario {

    padding:
        17px;

    text-align: center;

    color: #9A9A9A;

    font-size: 12px;

}


.sin-horario i {

    display: block;

    font-size: 24px;

    color: #D1D1D1;

    margin-bottom: 5px;

}


/* ============================================================
   ADMIN
============================================================ */

.admin-card {

    margin-top: 24px;

    background: #FFF7F7;

    border:
        1px solid #F0DADA;

    border-radius: 15px;

    padding: 18px;

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 15px;

}


.admin-card strong {

    color: #760000;

}


.admin-card p {

    margin:
        4px 0 0;

    color: #777777;

    font-size: 13px;

}


.btn-administrar {

    display: inline-flex;

    align-items: center;

    gap: 7px;

    white-space: nowrap;

    padding:
        10px 15px;

    border-radius: 9px;

    background: #B12626;

    color: #FFFFFF;

    text-decoration: none;

    font-weight: 700;

}


.btn-administrar:hover {

    background: #760000;

    color: #FFFFFF;

}


/* ============================================================
   LEYENDA
============================================================ */

.leyenda {

    margin-top: 25px;

    padding: 17px 19px;

    border-radius: 14px;

    background: #FFFFFF;

    border:
        1px solid #ECECEC;

    color: #6B6B6B;

    font-size: 12px;

}


.leyenda strong {

    color: #760000;

}


/* ============================================================
   RESPONSIVE
============================================================ */

@media
(max-width: 767px) {

    .horarios-hero {

        padding: 23px 20px;

    }


    .horarios-hero h1 {

        font-size: 23px;

    }


    .hero-actions {

        margin-top: 20px;

    }


    .horario-fila {

        align-items: flex-start;

        flex-direction: column;

    }


    .admin-card {

        align-items: flex-start;

        flex-direction: column;

    }


    .btn-administrar {

        width: 100%;

        justify-content: center;

    }

}

</style>


<div class="horarios-wrapper">


    <!-- =====================================================
         HERO
    ====================================================== -->

    <section class="horarios-hero">

        <div class="row align-items-center">

            <div class="col-lg-8">

                <div class="hero-content">

                    <h1>

                        <i class="bi bi-calendar-week me-1"></i>

                        Horarios de atención

                    </h1>

                    <p>

                        Consultá los horarios disponibles
                        para intervenciones de informática
                        y mantenimiento general.

                    </p>

                </div>

            </div>


            <div
                class="col-lg-4
                       text-lg-end
                       hero-actions"
            >

                <a
                    href="<?= url('dashboard.php') ?>"
                    class="btn-volver-dashboard"
                >

                    <i class="bi bi-arrow-left"></i>

                    Volver al dashboard

                </a>

            </div>

        </div>

    </section>



    <!-- =====================================================
         INFORMACIÓN
    ====================================================== -->

    <div class="info-general">

        <i class="bi bi-info-circle me-1"></i>

        Los horarios publicados indican los momentos
        habituales destinados a atención e intervenciones.
        Ante una situación urgente, registrá igualmente
        la solicitud desde el sistema para que quede
        documentada y pueda ser evaluada.

    </div>



    <div class="row g-4">


        <!-- =================================================
             INFORMÁTICA
        ================================================== -->

        <div class="col-xl-6">

            <section class="area-card">


                <div class="area-header informatica">

                    <div class="area-icon">

                        <i class="bi bi-pc-display"></i>

                    </div>

                    <div>

                        <h3>
                            Informática
                        </h3>

                        <p>

                            Computadoras, red, WiFi,
                            software, proyectores,
                            impresoras y equipamiento.

                        </p>

                    </div>

                </div>


                <div class="area-body">


                    <?php foreach (
                        $dias
                        as $dia
                    ): ?>

                        <?php

                        $horariosDia =
                            $informaticaPorDia[
                                $dia
                            ]
                            ?? [];

                        ?>


                        <div class="dia-card">


                            <div class="dia-header">

                                <div class="dia-nombre">

                                    <i class="bi bi-calendar3"></i>

                                    <?= e(
                                        $dia === 'Miercoles'
                                        ? 'Miércoles'
                                        : $dia
                                    ) ?>

                                </div>


                                <div class="dia-cantidad">

                                    <?= count(
                                        $horariosDia
                                    ) ?>

                                    <?= count(
                                        $horariosDia
                                    ) === 1
                                        ? 'horario'
                                        : 'horarios'
                                    ?>

                                </div>

                            </div>


                            <?php if (
                                empty(
                                    $horariosDia
                                )
                            ): ?>

                                <div class="sin-horario">

                                    <i class="bi bi-dash-circle"></i>

                                    Sin horario publicado

                                </div>


                            <?php else: ?>


                                <div class="horario-lista">


                                    <?php foreach (
                                        $horariosDia
                                        as $horario
                                    ): ?>

                                        <div class="horario-fila">


                                            <div class="horario-principal">

                                                <div class="hora-icon">

                                                    <i class="bi bi-clock"></i>

                                                </div>


                                                <div>

                                                    <div class="hora-texto">

                                                        <?= e(
                                                            horaCorta(
                                                                $horario[
                                                                    'hora_desde'
                                                                ]
                                                            )
                                                        ) ?>

                                                        a

                                                        <?= e(
                                                            horaCorta(
                                                                $horario[
                                                                    'hora_hasta'
                                                                ]
                                                            )
                                                        ) ?>

                                                    </div>


                                                    <?php if (
                                                        !empty(
                                                            $horario[
                                                                'responsable'
                                                            ]
                                                        )
                                                    ): ?>

                                                        <div class="responsable">

                                                            <i class="bi bi-person me-1"></i>

                                                            <?= e(
                                                                $horario[
                                                                    'responsable'
                                                                ]
                                                            ) ?>

                                                        </div>

                                                    <?php endif; ?>


                                                    <?php if (
                                                        !empty(
                                                            $horario[
                                                                'observaciones'
                                                            ]
                                                        )
                                                    ): ?>

                                                        <div class="observacion">

                                                            <?= e(
                                                                $horario[
                                                                    'observaciones'
                                                                ]
                                                            ) ?>

                                                        </div>

                                                    <?php endif; ?>

                                                </div>

                                            </div>


                                            <span class="badge-activo">

                                                Disponible

                                            </span>


                                        </div>

                                    <?php endforeach; ?>


                                </div>

                            <?php endif; ?>


                        </div>

                    <?php endforeach; ?>


                </div>

            </section>

        </div>



        <!-- =================================================
             MANTENIMIENTO
        ================================================== -->

        <div class="col-xl-6">

            <section class="area-card">


                <div class="area-header mantenimiento">

                    <div class="area-icon">

                        <i class="bi bi-tools"></i>

                    </div>

                    <div>

                        <h3>
                            Mantenimiento general
                        </h3>

                        <p>

                            Electricidad, iluminación,
                            mobiliario, puertas, ventanas,
                            agua y reparaciones generales.

                        </p>

                    </div>

                </div>


                <div class="area-body">


                    <?php foreach (
                        $dias
                        as $dia
                    ): ?>

                        <?php

                        $horariosDia =
                            $mantenimientoPorDia[
                                $dia
                            ]
                            ?? [];

                        ?>


                        <div class="dia-card">


                            <div class="dia-header">

                                <div class="dia-nombre">

                                    <i class="bi bi-calendar3"></i>

                                    <?= e(
                                        $dia === 'Miercoles'
                                        ? 'Miércoles'
                                        : $dia
                                    ) ?>

                                </div>


                                <div class="dia-cantidad">

                                    <?= count(
                                        $horariosDia
                                    ) ?>

                                    <?= count(
                                        $horariosDia
                                    ) === 1
                                        ? 'horario'
                                        : 'horarios'
                                    ?>

                                </div>

                            </div>


                            <?php if (
                                empty(
                                    $horariosDia
                                )
                            ): ?>

                                <div class="sin-horario">

                                    <i class="bi bi-dash-circle"></i>

                                    Sin horario publicado

                                </div>


                            <?php else: ?>


                                <div class="horario-lista">


                                    <?php foreach (
                                        $horariosDia
                                        as $horario
                                    ): ?>

                                        <div class="horario-fila">


                                            <div class="horario-principal">

                                                <div class="hora-icon">

                                                    <i class="bi bi-clock"></i>

                                                </div>


                                                <div>

                                                    <div class="hora-texto">

                                                        <?= e(
                                                            horaCorta(
                                                                $horario[
                                                                    'hora_desde'
                                                                ]
                                                            )
                                                        ) ?>

                                                        a

                                                        <?= e(
                                                            horaCorta(
                                                                $horario[
                                                                    'hora_hasta'
                                                                ]
                                                            )
                                                        ) ?>

                                                    </div>


                                                    <?php if (
                                                        !empty(
                                                            $horario[
                                                                'responsable'
                                                            ]
                                                        )
                                                    ): ?>

                                                        <div class="responsable">

                                                            <i class="bi bi-person me-1"></i>

                                                            <?= e(
                                                                $horario[
                                                                    'responsable'
                                                                ]
                                                            ) ?>

                                                        </div>

                                                    <?php endif; ?>


                                                    <?php if (
                                                        !empty(
                                                            $horario[
                                                                'observaciones'
                                                            ]
                                                        )
                                                    ): ?>

                                                        <div class="observacion">

                                                            <?= e(
                                                                $horario[
                                                                    'observaciones'
                                                                ]
                                                            ) ?>

                                                        </div>

                                                    <?php endif; ?>

                                                </div>

                                            </div>


                                            <span class="badge-activo">

                                                Disponible

                                            </span>


                                        </div>

                                    <?php endforeach; ?>


                                </div>

                            <?php endif; ?>


                        </div>

                    <?php endforeach; ?>


                </div>

            </section>

        </div>


    </div>



    <!-- =====================================================
         ADMINISTRACIÓN
    ====================================================== -->

    <?php if (
        esAdministrador()
    ): ?>

        <div class="admin-card">

            <div>

                <strong>

                    <i class="bi bi-gear me-1"></i>

                    Administración de horarios

                </strong>

                <p>

                    Podés agregar, modificar o desactivar
                    los horarios publicados desde
                    el panel administrativo.

                </p>

            </div>


            <a
                href="<?= url(
                    'admin/horarios.php'
                ) ?>"
                class="btn-administrar"
            >

                <i class="bi bi-pencil-square"></i>

                Administrar horarios

            </a>

        </div>

    <?php endif; ?>



    <!-- =====================================================
         LEYENDA
    ====================================================== -->

    <div class="leyenda">

        <strong>
            Importante:
        </strong>

        los horarios pueden modificarse por actividades
        institucionales, reuniones, urgencias
        o necesidades operativas. El registro de una solicitud
        no implica necesariamente que la intervención
        se realice de manera inmediata.

    </div>


</div>


<?php

require_once __DIR__
    . '/includes/footer.php';

?>