<?php
// ============================================================
// COLEGIO SAN JOSÉ
// Sistema de Gestión Técnica
// Archivo: /tecnica/index.php
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/funciones.php';
require_once __DIR__ . '/includes/auth.php';


// ============================================================
// index.php ES EL PANEL PRINCIPAL DEL SISTEMA
//
// A quien no inició sesión se le muestra la presentación
// habitual de la plataforma. A quien sí inició sesión se le
// suma, más abajo, el estado en vivo de las intervenciones
// (Intervenciones / Pendientes / Finalizadas), tomado
// directamente de la base de datos.
// ============================================================

$mostrarPanelInicio = estaLogueado();

$usuarioActual = $mostrarPanelInicio ? usuarioActual() : null;

$intervencionesActivas = [];
$pendientesInicio = [];
$finalizadasInicio = [];
$sectoresPanelInicio = [];

if ($mostrarPanelInicio) {

    $intervencionesActivas = obtenerIntervencionesActivas($conexion);

    $pendientesInicio = obtenerPendientes($conexion);

    $finalizadasInicio = obtenerFinalizadas($conexion);


    // Lista de aulas/sectores presentes en los datos mostrados,
    // para armar el filtro por curso.

    foreach (
        [
            $intervencionesActivas,
            $pendientesInicio,
            $finalizadasInicio
        ]
        as $listaPanelInicio
    ) {

        foreach ($listaPanelInicio as $filaPanelInicio) {

            $sectorPanelInicio = $filaPanelInicio['sector'] ?? '';

            if ($sectorPanelInicio !== '') {

                $sectoresPanelInicio[$sectorPanelInicio] = true;
            }
        }
    }

    ksort($sectoresPanelInicio);
}
?>
<!doctype html>

<html lang="es">

<head>

    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>
        Gestión Técnica | Colegio San José
    </title>


    <!-- Favicon -->

    <link rel="icon" type="image/x-icon" href="<?= asset('img/favicon.ico') ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= asset('img/favicon-32x32.png') ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= asset('img/favicon-16x16.png') ?>">
    <link rel="apple-touch-icon" href="<?= asset('img/apple-touch-icon.png') ?>">


    <!-- Bootstrap -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >


    <!-- Bootstrap Icons -->

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
    >


    <style>

        :root {

            --sanjo-rojo: #B12626;
            --sanjo-oscuro: #760000;
            --sanjo-blanco: #FFFFFF;

            --fondo: #F5F6F8;
            --texto: #2E2E2E;
        }


        * {

            box-sizing: border-box;

        }


        body {

            margin: 0;

            min-height: 100vh;

            background: var(--fondo);

            color: var(--texto);

            font-family:
                "Segoe UI",
                Arial,
                sans-serif;

        }


        /* =====================================================
           HEADER
        ===================================================== */

        .topbar {

            background:
                linear-gradient(
                    90deg,
                    var(--sanjo-oscuro),
                    var(--sanjo-rojo)
                );

            color: #FFFFFF;

            padding: 15px 0;

            box-shadow:
                0 3px 15px
                rgba(0,0,0,.18);

        }


        .marca {

            display: flex;

            align-items: center;

            gap: 12px;

            color: #FFFFFF;

            text-decoration: none;

        }


        .marca img {

            width: 52px;

            height: 52px;

            object-fit: contain;

        }


        .marca-titulo {

            font-size: 20px;

            font-weight: 700;

            line-height: 1.1;

        }


        .marca-subtitulo {

            font-size: 12px;

            opacity: .8;

        }


        .btn-ingresar-top {

            background: #FFFFFF;

            color: #760000;

            border: none;

            font-weight: 600;

            border-radius: 10px;

            padding:
                9px 18px;

        }


        .btn-ingresar-top:hover {

            background: #F2F2F2;

            color: #B12626;

        }


        .btn-salir-top {

            background: transparent;

            color: #FFFFFF;

            border:
                1px solid rgba(255,255,255,.55);

            font-weight: 600;

            border-radius: 10px;

            padding:
                9px 18px;

        }


        .btn-salir-top:hover {

            background:
                rgba(255,255,255,.12);

            color: #FFFFFF;

        }


        /* =====================================================
           HERO
        ===================================================== */

        .hero {

            min-height: 560px;

            display: flex;

            align-items: center;

            background:

                radial-gradient(
                    circle at 80% 20%,
                    rgba(177,38,38,.10),
                    transparent 35%
                ),

                #FFFFFF;

        }


        .hero-etiqueta {

            display: inline-flex;

            align-items: center;

            gap: 7px;

            background:
                rgba(177,38,38,.09);

            color: #760000;

            font-size: 14px;

            font-weight: 600;

            padding:
                8px 13px;

            border-radius: 30px;

            margin-bottom: 20px;

        }


        .hero h1 {

            color: #760000;

            font-weight: 800;

            font-size:
                clamp(
                    38px,
                    5vw,
                    64px
                );

            line-height: 1.05;

            margin-bottom: 22px;

        }


        .hero h1 span {

            color: #B12626;

        }


        .hero-texto {

            max-width: 650px;

            font-size: 18px;

            line-height: 1.7;

            color: #616161;

        }


        .btn-sanjo {

            background:
                linear-gradient(
                    135deg,
                    #760000,
                    #B12626
                );

            color: #FFFFFF;

            border: none;

            border-radius: 12px;

            font-weight: 600;

            padding:
                13px 25px;

            box-shadow:
                0 6px 18px
                rgba(118,0,0,.20);

        }


        .btn-sanjo:hover {

            background: #760000;

            color: #FFFFFF;

            transform:
                translateY(-1px);

        }


        .btn-horarios {

            border:
                1px solid #B12626;

            color: #B12626;

            background: #FFFFFF;

            border-radius: 12px;

            font-weight: 600;

            padding:
                12px 22px;

        }


        .btn-horarios:hover {

            background: #B12626;

            color: #FFFFFF;

        }


        /* =====================================================
           HERO CARD
        ===================================================== */

        .panel-visual {

            background: #FFFFFF;

            border-radius: 25px;

            padding: 28px;

            box-shadow:
                0 20px 55px
                rgba(0,0,0,.11);

            border:
                1px solid #EEEEEE;

        }


        .panel-header {

            display: flex;

            align-items: center;

            justify-content: space-between;

            margin-bottom: 25px;

        }


        .panel-header h5 {

            color: #760000;

            font-weight: 700;

            margin: 0;

        }


        .estado-online {

            display: inline-flex;

            align-items: center;

            gap: 6px;

            font-size: 12px;

            color: #198754;

            background: #E9F7EF;

            padding:
                6px 10px;

            border-radius: 20px;

        }


        .punto-online {

            width: 8px;

            height: 8px;

            border-radius: 50%;

            background: #198754;

        }


        .servicio {

            display: flex;

            align-items: center;

            gap: 15px;

            padding: 17px;

            border-radius: 15px;

            background: #F8F9FA;

            margin-bottom: 12px;

        }


        .servicio-icono {

            min-width: 46px;

            width: 46px;

            height: 46px;

            border-radius: 13px;

            display: flex;

            align-items: center;

            justify-content: center;

            background:
                linear-gradient(
                    135deg,
                    #760000,
                    #B12626
                );

            color: #FFFFFF;

            font-size: 21px;

        }


        .servicio h6 {

            margin: 0;

            font-weight: 700;

            color: #333333;

        }


        .servicio p {

            margin: 3px 0 0;

            font-size: 13px;

            color: #7A7A7A;

        }


        /* =====================================================
           SERVICIOS
        ===================================================== */

        .seccion {

            padding:
                80px 0;

        }


        .titulo-seccion {

            text-align: center;

            margin-bottom: 45px;

        }


        .titulo-seccion h2 {

            color: #760000;

            font-weight: 800;

        }


        .titulo-seccion p {

            color: #777777;

            max-width: 680px;

            margin:
                12px auto 0;

        }


        .card-servicio {

            background: #FFFFFF;

            height: 100%;

            padding: 28px;

            border-radius: 20px;

            border:
                1px solid #EEEEEE;

            box-shadow:
                0 7px 25px
                rgba(0,0,0,.06);

            transition:
                all .25s ease;

        }


        .card-servicio:hover {

            transform:
                translateY(-5px);

            box-shadow:
                0 14px 35px
                rgba(0,0,0,.10);

        }


        .card-servicio .icono {

            width: 58px;

            height: 58px;

            border-radius: 15px;

            display: flex;

            justify-content: center;

            align-items: center;

            background:
                rgba(177,38,38,.10);

            color: #B12626;

            font-size: 27px;

            margin-bottom: 20px;

        }


        .card-servicio h5 {

            color: #760000;

            font-weight: 700;

        }


        .card-servicio p {

            color: #707070;

            font-size: 14px;

            line-height: 1.6;

        }


        /* =====================================================
           PANEL DE INICIO (Intervenciones / Pendientes / Finalizadas)
        ===================================================== */

        .panel-inicio {

            background: #FFFFFF;

        }


        .panel-inicio-caja {

            background: #FFFFFF;

            border:
                1px solid #EEEEEE;

            border-radius: 20px;

            box-shadow:
                0 7px 25px
                rgba(0,0,0,.06);

            overflow: hidden;

        }


        .panel-inicio-filtro {

            display: flex;

            flex-wrap: wrap;

            align-items: center;

            gap: 12px;

            padding:
                18px 22px;

            border-bottom:
                1px solid #EEEEEE;

            background: #FBFBFB;

        }


        .panel-inicio-filtro label {

            font-size: 13px;

            font-weight: 700;

            color: #760000;

            margin: 0;

        }


        .panel-inicio-filtro select {

            min-height: 42px;

            border-radius: 9px;

            border:
                1px solid #DDDDDD;

            padding:
                6px 12px;

            font-size: 14px;

            max-width: 280px;

        }


        .panel-inicio-tabs {

            display: flex;

            flex-wrap: wrap;

            gap: 6px;

            padding:
                14px 22px 0;

        }


        .panel-inicio-tab {

            border: none;

            background: #F5F6F8;

            color: #616161;

            font-weight: 700;

            font-size: 14px;

            padding:
                10px 18px;

            border-radius:
                10px 10px 0 0;

        }


        .panel-inicio-tab.activo {

            background:
                linear-gradient(
                    135deg,
                    #760000,
                    #B12626
                );

            color: #FFFFFF;

        }


        .panel-inicio-tab .badge {

            margin-left: 6px;

        }


        .panel-inicio-contenido {

            padding:
                20px 22px 26px;

        }


        .panel-inicio-tabla-wrap {

            display: none;

            overflow-x: auto;

        }


        .panel-inicio-tabla-wrap.activo {

            display: block;

        }


        .panel-inicio-tabla-wrap table {

            width: 100%;

            border-collapse: collapse;

            font-size: 13.5px;

        }


        .panel-inicio-tabla-wrap th {

            text-align: left;

            color: #760000;

            font-size: 12px;

            text-transform: uppercase;

            letter-spacing: .03em;

            padding:
                10px 12px;

            border-bottom:
                2px solid #F0F0F0;

            white-space: nowrap;

        }


        .panel-inicio-tabla-wrap td {

            padding:
                12px;

            border-bottom:
                1px solid #F2F2F2;

            vertical-align: top;

        }


        .panel-inicio-tabla-wrap tr:last-child td {

            border-bottom: none;

        }


        .panel-inicio-ticket {

            font-weight: 700;

            color: #760000;

            white-space: nowrap;

        }


        .panel-inicio-vacio {

            padding:
                40px 20px;

            text-align: center;

            color: #999999;

        }


        .panel-inicio-vacio i {

            display: block;

            font-size: 32px;

            color: #DDDDDD;

            margin-bottom: 10px;

        }


        /* =====================================================
           PASOS
        ===================================================== */

        .como-funciona {

            background: #FFFFFF;

        }


        .paso {

            text-align: center;

            padding: 20px;

        }


        .numero-paso {

            width: 50px;

            height: 50px;

            margin:
                0 auto 15px;

            border-radius: 50%;

            display: flex;

            align-items: center;

            justify-content: center;

            background:
                linear-gradient(
                    135deg,
                    #760000,
                    #B12626
                );

            color: #FFFFFF;

            font-size: 20px;

            font-weight: 700;

        }


        .paso h5 {

            color: #760000;

            font-weight: 700;

        }


        .paso p {

            color: #777777;

            font-size: 14px;

        }


        /* =====================================================
           CTA
        ===================================================== */

        .cta {

            padding:
                70px 0;

        }


        .cta-box {

            background:
                linear-gradient(
                    135deg,
                    #760000,
                    #B12626
                );

            color: #FFFFFF;

            border-radius: 25px;

            padding:
                45px;

            box-shadow:
                0 15px 40px
                rgba(118,0,0,.20);

        }


        .cta-box h2 {

            font-weight: 800;

        }


        .cta-box p {

            opacity: .82;

            margin-bottom: 0;

        }


        .btn-cta {

            background: #FFFFFF;

            color: #760000;

            border-radius: 12px;

            padding:
                12px 24px;

            font-weight: 700;

            border: none;

        }


        .btn-cta:hover {

            background: #F0F0F0;

            color: #B12626;

        }


        /* =====================================================
           FOOTER
        ===================================================== */

        footer {

            background: #760000;

            color:
                rgba(255,255,255,.80);

            padding:
                24px 0;

        }


        footer strong {

            color: #FFFFFF;

        }


        /* =====================================================
           RESPONSIVE
        ===================================================== */

        @media
        (max-width: 991px) {

            .hero {

                padding:
                    65px 0;

            }


            .panel-visual {

                margin-top: 45px;

            }

        }


        @media
        (max-width: 575px) {

            .marca-subtitulo {

                display: none;

            }


            .marca-titulo {

                font-size: 17px;

            }


            .marca img {

                width: 43px;

                height: 43px;

            }


            .hero h1 {

                font-size: 40px;

            }


            .hero-texto {

                font-size: 16px;

            }


            .cta-box {

                padding: 30px;

                text-align: center;

            }

        }

    </style>

</head>


<body>


<!-- =========================================================
     HEADER
========================================================= -->

<header class="topbar">

    <div class="container">

        <div
            class="d-flex
                   align-items-center
                   justify-content-between"
        >

            <a
                href="<?= url('index.php') ?>"
                class="marca"
            >

                <img
                    src="<?= asset('img/logo.png') ?>"
                    alt="Colegio San José"
                >

                <div>

                    <div class="marca-titulo">
                        Colegio San José
                    </div>

                    <div class="marca-subtitulo">
                        Sistema de Gestión Técnica
                    </div>

                </div>

            </a>


            <?php if ($mostrarPanelInicio): ?>

                <div
                    class="d-flex
                           align-items-center
                           gap-2"
                >

                    <a
                        href="<?= url(rutaDashboardRol()) ?>"
                        class="btn btn-ingresar-top"
                    >

                        <i class="bi bi-grid me-1"></i>

                        Mi panel

                    </a>

                    <a
                        href="<?= url('logout.php') ?>"
                        class="btn btn-salir-top"
                    >

                        <i class="bi bi-box-arrow-right me-1"></i>

                        Salir

                    </a>

                </div>

            <?php else: ?>

                <a
                    href="<?= url('login.php') ?>"
                    class="btn btn-ingresar-top"
                >

                    <i class="bi bi-person-lock me-1"></i>

                    Ingresar

                </a>

            <?php endif; ?>

        </div>

    </div>

</header>



<!-- =========================================================
     HERO
========================================================= -->

<section class="hero">

    <div class="container">

        <div class="row align-items-center">


            <!-- TEXTO -->

            <div class="col-lg-7">

                <div class="hero-etiqueta">

                    <i class="bi bi-tools"></i>

                    Gestión interna del colegio

                </div>


                <h1>

                    Solicitudes de

                    <span>
                        Informática y Mantenimiento
                    </span>

                </h1>


                <p class="hero-texto">

                    Plataforma destinada a docentes
                    y personal del Colegio San José
                    para registrar necesidades,
                    realizar seguimiento de intervenciones
                    y mantener documentadas
                    las soluciones realizadas.

                </p>


                <div
                    class="d-flex
                           flex-wrap
                           gap-3
                           mt-4"
                >

                    <a
                        href="<?= url('login.php') ?>"
                        class="btn btn-sanjo"
                    >

                        <i class="bi bi-box-arrow-in-right me-2"></i>

                        Ingresar al sistema

                    </a>


                    <a
                        href="<?= url('login.php') ?>"
                        class="btn btn-horarios"
                    >

                        <i class="bi bi-clock me-2"></i>

                        Ver horarios

                    </a>

                </div>

            </div>



            <!-- PANEL -->

            <div class="col-lg-5">

                <div class="panel-visual">

                    <div class="panel-header">

                        <h5>

                            <i class="bi bi-grid me-1"></i>

                            Gestión Técnica

                        </h5>


                        <div class="estado-online">

                            <span class="punto-online"></span>

                            Disponible

                        </div>

                    </div>



                    <div class="servicio">

                        <div class="servicio-icono">

                            <i class="bi bi-pc-display"></i>

                        </div>

                        <div>

                            <h6>
                                Informática
                            </h6>

                            <p>
                                PC, internet, WiFi,
                                proyectores, audio y software.
                            </p>

                        </div>

                    </div>



                    <div class="servicio">

                        <div class="servicio-icono">

                            <i class="bi bi-tools"></i>

                        </div>

                        <div>

                            <h6>
                                Mantenimiento general
                            </h6>

                            <p>
                                Electricidad, mobiliario,
                                iluminación y reparaciones.
                            </p>

                        </div>

                    </div>



                    <div class="servicio">

                        <div class="servicio-icono">

                            <i class="bi bi-camera"></i>

                        </div>

                        <div>

                            <h6>
                                Evidencia fotográfica
                            </h6>

                            <p>
                                Adjuntá imágenes del problema
                                directamente desde el celular.
                            </p>

                        </div>

                    </div>



                    <div class="servicio mb-0">

                        <div class="servicio-icono">

                            <i class="bi bi-check2-circle"></i>

                        </div>

                        <div>

                            <h6>
                                Seguimiento
                            </h6>

                            <p>
                                Consultá el estado
                                y la solución de cada pedido.
                            </p>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>

</section>



<!-- =========================================================
     SERVICIOS
========================================================= -->

<section class="seccion">

    <div class="container">

        <div class="titulo-seccion">

            <h2>
                ¿Qué podemos registrar?
            </h2>

            <p>
                Cada solicitud queda documentada
                para permitir un mejor seguimiento
                del trabajo técnico y de mantenimiento.
            </p>

        </div>


        <div class="row g-4">


            <div class="col-md-6 col-lg-3">

                <div class="card-servicio">

                    <div class="icono">

                        <i class="bi bi-pc-display"></i>

                    </div>

                    <h5>
                        Informática
                    </h5>

                    <p>
                        Computadoras, impresoras,
                        internet, WiFi, redes,
                        proyectores, software
                        y equipamiento tecnológico.
                    </p>

                </div>

            </div>



            <div class="col-md-6 col-lg-3">

                <div class="card-servicio">

                    <div class="icono">

                        <i class="bi bi-tools"></i>

                    </div>

                    <h5>
                        Mantenimiento
                    </h5>

                    <p>
                        Electricidad, iluminación,
                        puertas, ventanas, mobiliario,
                        pintura y mantenimiento general.
                    </p>

                </div>

            </div>



            <div class="col-md-6 col-lg-3">

                <div class="card-servicio">

                    <div class="icono">

                        <i class="bi bi-chat-left-text"></i>

                    </div>

                    <h5>
                        Seguimiento
                    </h5>

                    <p>
                        Comentarios entre docentes
                        y personal técnico,
                        cambios de estado
                        y motivos de pendientes.
                    </p>

                </div>

            </div>



            <div class="col-md-6 col-lg-3">

                <div class="card-servicio">

                    <div class="icono">

                        <i class="bi bi-lightbulb"></i>

                    </div>

                    <h5>
                        Mejoras
                    </h5>

                    <p>
                        Propuestas para mejorar
                        aulas, equipos, infraestructura
                        tecnológica
                        y espacios del colegio.
                    </p>

                </div>

            </div>

        </div>

    </div>

</section>



<?php if ($mostrarPanelInicio): ?>

<!-- =========================================================
     PANEL DE INICIO
     Intervenciones / Pendientes / Finalizadas
========================================================= -->

<section class="seccion panel-inicio">

    <div class="container">

        <div class="titulo-seccion">

            <h2>
                Estado de las intervenciones
            </h2>

            <p>
                Seguimiento en vivo de los pedidos registrados,
                actualizado directamente desde la base de datos.
                Filtrá por aula o curso para ver si ya existe
                un reclamo activo antes de cargar uno nuevo.
            </p>

        </div>


        <div class="panel-inicio-caja">

            <div class="panel-inicio-filtro">

                <label for="panelInicioFiltroAula">
                    <i class="bi bi-funnel me-1"></i>
                    Filtrar por aula / curso
                </label>

                <select
                    id="panelInicioFiltroAula"
                    onchange="panelInicioFiltrar()"
                >

                    <option value="">
                        Todas las aulas / sectores
                    </option>

                    <?php foreach (array_keys($sectoresPanelInicio) as $sectorOpcion): ?>

                        <option value="<?= e($sectorOpcion) ?>">
                            <?= e($sectorOpcion) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div class="panel-inicio-tabs">

                <button
                    type="button"
                    class="panel-inicio-tab activo"
                    data-panel="intervenciones"
                    onclick="panelInicioMostrar('intervenciones')"
                >
                    Intervenciones
                    <span class="badge bg-light text-dark">
                        <?= count($intervencionesActivas) ?>
                    </span>
                </button>

                <button
                    type="button"
                    class="panel-inicio-tab"
                    data-panel="pendientes"
                    onclick="panelInicioMostrar('pendientes')"
                >
                    Pendientes
                    <span class="badge bg-light text-dark">
                        <?= count($pendientesInicio) ?>
                    </span>
                </button>

                <button
                    type="button"
                    class="panel-inicio-tab"
                    data-panel="finalizadas"
                    onclick="panelInicioMostrar('finalizadas')"
                >
                    Finalizadas
                    <span class="badge bg-light text-dark">
                        <?= count($finalizadasInicio) ?>
                    </span>
                </button>

            </div>


            <div class="panel-inicio-contenido">


                <!-- INTERVENCIONES -->

                <div
                    class="panel-inicio-tabla-wrap activo"
                    data-panel="intervenciones"
                >

                    <?php if (empty($intervencionesActivas)): ?>

                        <div class="panel-inicio-vacio">
                            <i class="bi bi-check2-circle"></i>
                            No hay intervenciones en curso en este momento.
                        </div>

                    <?php else: ?>

                        <table>

                            <thead>
                                <tr>
                                    <th>N°</th>
                                    <th>Problema</th>
                                    <th>Aula / sector</th>
                                    <th>Equipo</th>
                                    <th>Técnico</th>
                                    <th>Fecha</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php foreach ($intervencionesActivas as $fila): ?>

                                    <tr data-sector="<?= e($fila['sector'] ?? '') ?>">

                                        <td class="panel-inicio-ticket">
                                            <?= e(numeroTicket((int)$fila['id_solicitud'])) ?>
                                        </td>

                                        <td>
                                            <?= e($fila['titulo']) ?>
                                        </td>

                                        <td>
                                            <?= e($fila['sector'] ?? '-') ?>
                                        </td>

                                        <td>
                                            <?= e($fila['equipo'] ?? '-') ?>
                                        </td>

                                        <td>
                                            <?= e(
                                                trim($fila['tecnico_asignado'] ?? '') !== ''
                                                    ? $fila['tecnico_asignado']
                                                    : 'Sin asignar'
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= e(fechaCorta($fila['fecha_actualizacion'])) ?>
                                        </td>

                                        <td>
                                            <span class="badge <?= claseEstado($fila['estado']) ?>">
                                                <?= e($fila['estado']) ?>
                                            </span>
                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    <?php endif; ?>

                </div>


                <!-- PENDIENTES -->

                <div
                    class="panel-inicio-tabla-wrap"
                    data-panel="pendientes"
                >

                    <?php if (empty($pendientesInicio)): ?>

                        <div class="panel-inicio-vacio">
                            <i class="bi bi-hourglass-split"></i>
                            No hay solicitudes pendientes en este momento.
                        </div>

                    <?php else: ?>

                        <table>

                            <thead>
                                <tr>
                                    <th>N°</th>
                                    <th>Problema</th>
                                    <th>Aula / sector</th>
                                    <th>Motivo</th>
                                    <th>Técnico</th>
                                    <th>Fecha</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php foreach ($pendientesInicio as $fila): ?>

                                    <tr data-sector="<?= e($fila['sector'] ?? '') ?>">

                                        <td class="panel-inicio-ticket">
                                            <?= e(numeroTicket((int)$fila['id_solicitud'])) ?>
                                        </td>

                                        <td>
                                            <?= e($fila['titulo']) ?>
                                        </td>

                                        <td>
                                            <?= e($fila['sector'] ?? '-') ?>
                                        </td>

                                        <td>
                                            <?= e(
                                                $fila['tipo_pendiente']
                                                    ?? $fila['motivo_pendiente']
                                                    ?? 'Sin especificar'
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= e(
                                                trim($fila['tecnico_asignado'] ?? '') !== ''
                                                    ? $fila['tecnico_asignado']
                                                    : 'Sin asignar'
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= e(fechaCorta($fila['fecha_actualizacion'])) ?>
                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    <?php endif; ?>

                </div>


                <!-- FINALIZADAS -->

                <div
                    class="panel-inicio-tabla-wrap"
                    data-panel="finalizadas"
                >

                    <?php if (empty($finalizadasInicio)): ?>

                        <div class="panel-inicio-vacio">
                            <i class="bi bi-clipboard-check"></i>
                            Todavía no hay intervenciones finalizadas.
                        </div>

                    <?php else: ?>

                        <table>

                            <thead>
                                <tr>
                                    <th>N°</th>
                                    <th>Problema informado</th>
                                    <th>Aula / sector</th>
                                    <th>Trabajo realizado</th>
                                    <th>Técnico</th>
                                    <th>Fecha de finalización</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php foreach ($finalizadasInicio as $fila): ?>

                                    <tr data-sector="<?= e($fila['sector'] ?? '') ?>">

                                        <td class="panel-inicio-ticket">
                                            <?= e(numeroTicket((int)$fila['id_solicitud'])) ?>
                                        </td>

                                        <td>
                                            <?= e($fila['titulo']) ?>
                                        </td>

                                        <td>
                                            <?= e($fila['sector'] ?? '-') ?>
                                        </td>

                                        <td>
                                            <?= e($fila['trabajo_realizado'] ?? 'Sin detalle registrado') ?>
                                        </td>

                                        <td>
                                            <?= e(
                                                trim($fila['tecnico_responsable'] ?? '') !== ''
                                                    ? $fila['tecnico_responsable']
                                                    : '-'
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= e(
                                                fechaCorta(
                                                    $fila['fecha_fin']
                                                        ?? $fila['fecha_resolucion']
                                                        ?? $fila['fecha_actualizacion']
                                                )
                                            ) ?>
                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    <?php endif; ?>

                </div>


            </div>

        </div>

    </div>

</section>


<script>

function panelInicioMostrar(nombre) {

    document
        .querySelectorAll('.panel-inicio-tab')
        .forEach(function (boton) {
            boton.classList.toggle(
                'activo',
                boton.dataset.panel === nombre
            );
        });

    document
        .querySelectorAll('.panel-inicio-tabla-wrap')
        .forEach(function (tabla) {
            tabla.classList.toggle(
                'activo',
                tabla.dataset.panel === nombre
            );
        });
}


function panelInicioFiltrar() {

    var sector = document
        .getElementById('panelInicioFiltroAula')
        .value;

    document
        .querySelectorAll('.panel-inicio-tabla-wrap tbody tr')
        .forEach(function (fila) {

            var coincide =
                sector === '' ||
                fila.dataset.sector === sector;

            fila.style.display = coincide ? '' : 'none';
        });
}

</script>

<?php endif; ?>



<!-- =========================================================
     CÓMO FUNCIONA
========================================================= -->

<section class="seccion como-funciona">

    <div class="container">

        <div class="titulo-seccion">

            <h2>
                ¿Cómo funciona?
            </h2>

            <p>
                El pedido queda registrado desde
                que el docente informa el problema
                hasta que se documenta su solución.
            </p>

        </div>


        <div class="row g-4">


            <div class="col-md-3">

                <div class="paso">

                    <div class="numero-paso">
                        1
                    </div>

                    <h5>
                        Ingresá
                    </h5>

                    <p>
                        Accedé utilizando
                        tu correo y DNI.
                    </p>

                </div>

            </div>



            <div class="col-md-3">

                <div class="paso">

                    <div class="numero-paso">
                        2
                    </div>

                    <h5>
                        Registrá
                    </h5>

                    <p>
                        Describí el problema
                        y agregá fotografías
                        si es necesario.
                    </p>

                </div>

            </div>



            <div class="col-md-3">

                <div class="paso">

                    <div class="numero-paso">
                        3
                    </div>

                    <h5>
                        Seguimiento
                    </h5>

                    <p>
                        Consultá si está
                        pendiente, asignado
                        o en proceso.
                    </p>

                </div>

            </div>



            <div class="col-md-3">

                <div class="paso">

                    <div class="numero-paso">
                        4
                    </div>

                    <h5>
                        Solución
                    </h5>

                    <p>
                        El personal técnico
                        registra el informe
                        y las imágenes finales.
                    </p>

                </div>

            </div>

        </div>

    </div>

</section>



<!-- =========================================================
     CTA
========================================================= -->

<section class="cta">

    <div class="container">

        <div class="cta-box">

            <div class="row align-items-center">

                <div class="col-lg-8">

                    <h2>
                        ¿Necesitás una intervención?
                    </h2>

                    <p>
                        Ingresá al sistema y registrá
                        tu solicitud para comenzar
                        el seguimiento.
                    </p>

                </div>


                <div
                    class="col-lg-4
                           text-lg-end
                           mt-4 mt-lg-0"
                >

                    <a
                        href="<?= url('login.php') ?>"
                        class="btn btn-cta"
                    >

                        <i class="bi bi-box-arrow-in-right me-2"></i>

                        Ingresar

                    </a>

                </div>

            </div>

        </div>

    </div>

</section>



<!-- =========================================================
     FOOTER
========================================================= -->

<footer>

    <div class="container">

        <div
            class="d-flex
                   flex-column
                   flex-md-row
                   justify-content-between
                   align-items-center
                   gap-2"
        >

            <div>

                © <?= date('Y') ?>

                <strong>
                    Colegio San José
                </strong>

            </div>


            <div>

                <i class="bi bi-tools me-1"></i>

                Sistema de Gestión Técnica

            </div>

        </div>

    </div>

</footer>



<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>


</body>

</html>