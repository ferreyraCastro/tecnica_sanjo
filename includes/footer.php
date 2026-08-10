<?php
// ============================================================
// COLEGIO SAN JOSÉ
// Sistema de Solicitudes e Intervenciones
// Archivo: includes/footer.php
// ============================================================

$anioActual = date('Y');
?>

</div>
<!-- FIN container-fluid abierto en header.php -->


<!-- =========================================================
     FOOTER
========================================================= -->

<footer class="footer-sanjo mt-5">

    <div class="container">

        <div class="row align-items-center gy-4">

            <!-- =================================================
                 INSTITUCIÓN
            ================================================== -->

            <div class="col-lg-4 col-md-6 text-center text-md-start">

                <div class="footer-marca">

                    <div class="footer-icono">
                        <i class="bi bi-building"></i>
                    </div>

                    <div>

                        <h5 class="mb-1">
                            Colegio San José
                        </h5>

                        <p class="mb-0 footer-subtitulo">
                            Sistema de Gestión Técnica
                        </p>

                    </div>

                </div>

            </div>


            <!-- =================================================
                 INFORMACIÓN
            ================================================== -->

            <div class="col-lg-4 col-md-6 text-center">

                <div class="footer-info">

                    <div>
                        <i class="bi bi-pc-display-horizontal me-1"></i>
                        Informática
                    </div>

                    <div class="footer-separador">
                        •
                    </div>

                    <div>
                        <i class="bi bi-tools me-1"></i>
                        Mantenimiento
                    </div>

                </div>

                <div class="mt-2 footer-descripcion">

                    Gestión de solicitudes,
                    intervenciones y mejoras

                </div>

            </div>


            <!-- =================================================
                 ACCESOS
            ================================================== -->

            <div class="col-lg-4 text-center text-lg-end">

                <a
                    href="dashboard.php"
                    class="footer-link"
                    title="Dashboard"
                >
                    <i class="bi bi-speedometer2"></i>
                </a>

                <a
                    href="solicitudes.php"
                    class="footer-link"
                    title="Solicitudes"
                >
                    <i class="bi bi-ticket-detailed"></i>
                </a>

                <a
                    href="horarios.php"
                    class="footer-link"
                    title="Horarios"
                >
                    <i class="bi bi-calendar-week"></i>
                </a>

                <a
                    href="mejoras.php"
                    class="footer-link"
                    title="Mejoras"
                >
                    <i class="bi bi-lightbulb"></i>
                </a>

            </div>

        </div>


        <!-- =====================================================
             LÍNEA
        ====================================================== -->

        <div class="footer-linea"></div>


        <!-- =====================================================
             COPYRIGHT
        ====================================================== -->

        <div class="row align-items-center gy-2">

            <div class="col-md-6 text-center text-md-start">

                <small>

                    © <?= $anioActual ?>

                    <strong>
                        Colegio San José
                    </strong>

                    - Todos los derechos reservados

                </small>

            </div>


            <div class="col-md-6 text-center text-md-end">

                <small>

                    <i class="bi bi-code-slash me-1"></i>

                    Sistema de Gestión Técnica

                </small>

            </div>

        </div>

    </div>

</footer>


<!-- =========================================================
     ESTILOS FOOTER
========================================================= -->

<style>

.footer-sanjo {

    background:
        linear-gradient(
            135deg,
            #760000 0%,
            #B12626 100%
        );

    color: #FFFFFF;

    padding: 35px 0 20px 0;

    box-shadow:
        0 -4px 20px
        rgba(0, 0, 0, 0.12);
}


/* =========================================================
   MARCA
========================================================= */

.footer-marca {

    display: flex;

    align-items: center;

    gap: 13px;
}


.footer-icono {

    width: 48px;

    height: 48px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 14px;

    background:
        rgba(255,255,255,.14);

    border:
        1px solid
        rgba(255,255,255,.22);

    font-size: 23px;
}


.footer-marca h5 {

    font-weight: 700;

    margin: 0;

    color: #FFFFFF;
}


.footer-subtitulo {

    color:
        rgba(255,255,255,.75);

    font-size: 14px;
}


/* =========================================================
   INFORMACIÓN CENTRAL
========================================================= */

.footer-info {

    display: flex;

    align-items: center;

    justify-content: center;

    gap: 10px;

    font-weight: 600;
}


.footer-separador {

    color:
        rgba(255,255,255,.55);
}


.footer-descripcion {

    color:
        rgba(255,255,255,.70);

    font-size: 13px;
}


/* =========================================================
   LINKS
========================================================= */

.footer-link {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    width: 40px;

    height: 40px;

    margin-left: 5px;

    border-radius: 10px;

    color: #FFFFFF;

    background:
        rgba(255,255,255,.12);

    border:
        1px solid
        rgba(255,255,255,.18);

    text-decoration: none;

    transition: all .25s ease;
}


.footer-link:hover {

    background: #FFFFFF;

    color: #760000;

    transform:
        translateY(-3px);

    box-shadow:
        0 6px 15px
        rgba(0,0,0,.18);
}


/* =========================================================
   SEPARADOR
========================================================= */

.footer-linea {

    height: 1px;

    background:
        rgba(255,255,255,.20);

    margin:
        25px 0 18px 0;
}


/* =========================================================
   COPYRIGHT
========================================================= */

.footer-sanjo small {

    color:
        rgba(255,255,255,.78);
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media
(max-width: 767px) {

    .footer-sanjo {

        padding-top: 28px;

    }


    .footer-marca {

        justify-content: center;

    }


    .footer-info {

        flex-wrap: wrap;

    }


    .footer-link {

        margin:
            3px;

    }

}

</style>


<!-- =========================================================
     BOOTSTRAP JS
========================================================= -->

<script
src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>


<!-- =========================================================
     JAVASCRIPT GENERAL
========================================================= -->

<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        /*
        ========================================================
        TOOLTIPS BOOTSTRAP
        ========================================================
        */

        const tooltipTriggerList =
            document.querySelectorAll(
                '[data-bs-toggle="tooltip"]'
            );

        [...tooltipTriggerList].map(
            tooltipTriggerEl =>
                new bootstrap.Tooltip(
                    tooltipTriggerEl
                )
        );


        /*
        ========================================================
        CERRAR ALERTAS AUTOMÁTICAMENTE
        ========================================================
        */

        const alertas =
            document.querySelectorAll(
                '.alert-auto'
            );

        alertas.forEach(
            function(alerta) {

                setTimeout(
                    function() {

                        const instancia =
                            bootstrap.Alert
                            .getOrCreateInstance(
                                alerta
                            );

                        instancia.close();

                    },
                    5000
                );

            }
        );

    }
);

</script>


</body>

</html>