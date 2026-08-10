<?php
// ============================================================
// COLEGIO SAN JOSÉ
// Sistema de Gestión Técnica
// Archivo: /tecnica/login.php
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/funciones.php';
require_once __DIR__ . '/includes/auth.php';


// ============================================================
// SI YA ESTÁ LOGUEADO
// ============================================================

soloInvitados();


// ============================================================
// VARIABLES
// ============================================================

$error = '';

$correo = '';

$mensaje = $_SESSION['mensaje_login'] ?? '';

unset($_SESSION['mensaje_login']);


// ============================================================
// PROCESAR LOGIN
// ============================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ========================================================
    // CSRF
    // ========================================================

    $csrf =
        $_POST['csrf_token']
        ?? '';


    if (!validarCsrf($csrf)) {

        $error =
            'La sesión del formulario expiró. '
            . 'Actualizá la página e intentá nuevamente.';

    } else {

        // ====================================================
        // RECIBIR DATOS
        // ====================================================

        $correo =
            strtolower(
                trim(
                    $_POST['correo']
                    ?? ''
                )
            );


        $dni =
            trim(
                $_POST['dni']
                ?? ''
            );


        // Eliminar puntos, espacios y guiones del DNI.

        $dni = preg_replace(
            '/[^0-9]/',
            '',
            $dni
        ) ?? '';


        // ====================================================
        // VALIDACIONES
        // ====================================================

        if (
            $correo === ''
            || $dni === ''
        ) {

            $error =
                'Ingresá tu correo electrónico y DNI.';

        } elseif (
            !filter_var(
                $correo,
                FILTER_VALIDATE_EMAIL
            )
        ) {

            $error =
                'El correo electrónico ingresado no es válido.';

        } elseif (
            !preg_match(
                '/^[0-9]{7,9}$/',
                $dni
            )
        ) {

            $error =
                'Ingresá un DNI válido, solamente con números.';

        } else {

            try {

                // ============================================
                // BUSCAR USUARIO
                // ============================================

                $sql = "
                    SELECT
                        id_usuario,
                        nombre,
                        apellido,
                        correo,
                        dni_hash,
                        rol,
                        estado

                    FROM usuarios

                    WHERE correo = ?

                    LIMIT 1
                ";


                $stmt =
                    $conexion->prepare(
                        $sql
                    );


                $stmt->execute([
                    $correo
                ]);


                $usuario =
                    $stmt->fetch(
                        PDO::FETCH_ASSOC
                    );


                // ============================================
                // USUARIO NO EXISTE
                //
                // Usamos un mensaje genérico para no revelar
                // si el correo está registrado.
                // ============================================

                if (!$usuario) {

                    // Pequeña espera para dificultar
                    // intentos automatizados.

                    usleep(250000);

                    $error =
                        'Correo o DNI incorrectos.';

                } elseif (
                    $usuario['estado']
                    !== 'Activo'
                ) {

                    $error =
                        'La cuenta se encuentra inactiva. '
                        . 'Contactá con la administración.';

                } elseif (
                    !password_verify(
                        $dni,
                        $usuario['dni_hash']
                    )
                ) {

                    $error =
                        'Correo o DNI incorrectos.';

                } else {

                    // ========================================
                    // LOGIN CORRECTO
                    // ========================================

                    iniciarSesionUsuario(
                        $usuario
                    );


                    // ========================================
                    // ACTUALIZAR ÚLTIMO ACCESO
                    // ========================================

                    $stmtAcceso =
                        $conexion->prepare("
                            UPDATE usuarios

                            SET ultimo_acceso = NOW()

                            WHERE id_usuario = ?
                        ");


                    $stmtAcceso->execute([
                        (int)$usuario['id_usuario']
                    ]);


                    // ========================================
                    // URL QUE INTENTABA VISITAR
                    // ========================================

                    $destino =
                        $_SESSION['url_despues_login']
                        ?? url('dashboard.php');


                    unset(
                        $_SESSION['url_despues_login']
                    );


                    // Evitamos redirecciones externas.

                    if (
                        !is_string($destino)
                        ||
                        !str_starts_with(
                            $destino,
                            BASE_URL
                        )
                    ) {

                        $destino =
                            url(
                                'dashboard.php'
                            );
                    }


                    header(
                        'Location: '
                        . $destino
                    );

                    exit;
                }


            } catch (Throwable $e) {

                error_log(
                    'Error login: '
                    . $e->getMessage()
                );


                $error =
                    'Ocurrió un problema al intentar ingresar. '
                    . 'Intentá nuevamente.';
            }
        }
    }
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

    <meta
        name="robots"
        content="noindex,nofollow"
    >

    <title>
        Ingresar | Colegio San José
    </title>


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
            --texto: #303030;
            --gris: #747474;

        }


        * {

            box-sizing: border-box;

        }


        body {

            margin: 0;

            min-height: 100vh;

            font-family:
                "Segoe UI",
                Arial,
                sans-serif;

            background:
                radial-gradient(
                    circle at 15% 20%,
                    rgba(177,38,38,.10),
                    transparent 30%
                ),
                radial-gradient(
                    circle at 85% 80%,
                    rgba(118,0,0,.08),
                    transparent 30%
                ),
                #F5F6F8;

            color: var(--texto);

        }


        /* =====================================================
           CONTENEDOR
        ===================================================== */

        .login-wrapper {

            min-height: 100vh;

            display: flex;

            align-items: center;

            justify-content: center;

            padding: 30px 15px;

        }


        .login-card {

            width: 100%;

            max-width: 1000px;

            min-height: 600px;

            display: grid;

            grid-template-columns:
                1.05fr .95fr;

            background: #FFFFFF;

            border-radius: 28px;

            overflow: hidden;

            box-shadow:
                0 25px 70px
                rgba(0,0,0,.15);

        }


        /* =====================================================
           PANEL INSTITUCIONAL
        ===================================================== */

        .login-info {

            position: relative;

            padding: 60px;

            display: flex;

            flex-direction: column;

            justify-content: space-between;

            color: #FFFFFF;

            background:
                linear-gradient(
                    145deg,
                    #760000 0%,
                    #B12626 100%
                );

            overflow: hidden;

        }


        .login-info::before {

            content: "";

            position: absolute;

            width: 340px;

            height: 340px;

            border-radius: 50%;

            background:
                rgba(255,255,255,.06);

            top: -150px;

            right: -130px;

        }


        .login-info::after {

            content: "";

            position: absolute;

            width: 280px;

            height: 280px;

            border-radius: 50%;

            background:
                rgba(255,255,255,.05);

            bottom: -140px;

            left: -100px;

        }


        .institucion {

            position: relative;

            z-index: 2;

        }


        .logo-box {

            display: flex;

            align-items: center;

            gap: 15px;

            margin-bottom: 55px;

        }


        .logo-box img {

            width: 70px;

            height: 70px;

            object-fit: contain;

            background:
                rgba(255,255,255,.10);

            padding: 6px;

            border-radius: 17px;

        }


        .logo-box h4 {

            margin: 0;

            font-weight: 800;

        }


        .logo-box small {

            opacity: .75;

        }


        .login-info h1 {

            font-size:
                clamp(
                    34px,
                    4vw,
                    50px
                );

            line-height: 1.08;

            font-weight: 800;

            margin-bottom: 20px;

        }


        .login-info-texto {

            font-size: 16px;

            line-height: 1.7;

            color:
                rgba(255,255,255,.80);

            max-width: 440px;

        }


        .items {

            margin-top: 35px;

        }


        .item {

            display: flex;

            gap: 12px;

            align-items: center;

            margin-bottom: 14px;

            color:
                rgba(255,255,255,.90);

        }


        .item-icon {

            width: 35px;

            height: 35px;

            min-width: 35px;

            display: flex;

            justify-content: center;

            align-items: center;

            border-radius: 9px;

            background:
                rgba(255,255,255,.12);

        }


        .copyright {

            position: relative;

            z-index: 2;

            margin-top: 40px;

            font-size: 12px;

            color:
                rgba(255,255,255,.60);

        }


        /* =====================================================
           FORMULARIO
        ===================================================== */

        .login-formulario {

            padding:
                65px 55px;

            display: flex;

            flex-direction: column;

            justify-content: center;

        }


        .volver {

            display: inline-flex;

            align-items: center;

            gap: 5px;

            margin-bottom: 35px;

            color: #777777;

            text-decoration: none;

            font-size: 14px;

            width: fit-content;

        }


        .volver:hover {

            color: #B12626;

        }


        .login-formulario h2 {

            color: #760000;

            font-weight: 800;

            margin-bottom: 8px;

        }


        .login-formulario .descripcion {

            color: #777777;

            margin-bottom: 32px;

        }


        /* =====================================================
           FORM
        ===================================================== */

        .form-label {

            font-weight: 600;

            color: #444444;

            font-size: 14px;

        }


        .input-group-text {

            background: #FFFFFF;

            color: #B12626;

            border:
                1px solid #DADADA;

            border-right: none;

            padding-left: 16px;

            padding-right: 13px;

        }


        .form-control {

            min-height: 52px;

            border:
                1px solid #DADADA;

            border-left: none;

            font-size: 15px;

        }


        .form-control:focus {

            border-color: #B12626;

            box-shadow: none;

        }


        .input-group:focus-within
        .input-group-text {

            border-color: #B12626;

        }


        .btn-ver {

            border:
                1px solid #DADADA;

            border-left: none;

            background: #FFFFFF;

            color: #777777;

        }


        .btn-ver:hover {

            background: #F5F5F5;

            color: #760000;

        }


        /* =====================================================
           BOTÓN
        ===================================================== */

        .btn-login {

            width: 100%;

            min-height: 52px;

            border: none;

            border-radius: 12px;

            background:
                linear-gradient(
                    135deg,
                    #760000,
                    #B12626
                );

            color: #FFFFFF;

            font-weight: 700;

            font-size: 16px;

            margin-top: 10px;

            box-shadow:
                0 8px 22px
                rgba(118,0,0,.20);

            transition:
                all .2s ease;

        }


        .btn-login:hover {

            background: #760000;

            color: #FFFFFF;

            transform:
                translateY(-1px);

        }


        /* =====================================================
           ALERTAS
        ===================================================== */

        .alert-error {

            background: #FFF2F2;

            color: #8B1111;

            border:
                1px solid #F2CDCD;

            border-left:
                4px solid #B12626;

            border-radius: 10px;

            padding: 13px 15px;

            margin-bottom: 22px;

            font-size: 14px;

        }


        .alert-ok {

            background: #EFFAF3;

            color: #176536;

            border:
                1px solid #CAE9D5;

            border-left:
                4px solid #198754;

            border-radius: 10px;

            padding: 13px 15px;

            margin-bottom: 22px;

            font-size: 14px;

        }


        /* =====================================================
           AYUDA
        ===================================================== */

        .ayuda-login {

            margin-top: 25px;

            padding-top: 20px;

            border-top:
                1px solid #EEEEEE;

            font-size: 13px;

            color: #7A7A7A;

        }


        .ayuda-login i {

            color: #B12626;

        }


        /* =====================================================
           RESPONSIVE
        ===================================================== */

        @media
        (max-width: 850px) {

            .login-card {

                max-width: 540px;

                grid-template-columns: 1fr;

            }


            .login-info {

                padding:
                    35px;

            }


            .login-info h1 {

                font-size: 32px;

            }


            .items,
            .copyright {

                display: none;

            }


            .logo-box {

                margin-bottom: 25px;

            }


            .login-formulario {

                padding:
                    45px 35px;

            }

        }


        @media
        (max-width: 480px) {

            .login-wrapper {

                padding: 0;

                align-items: stretch;

            }


            .login-card {

                border-radius: 0;

                min-height: 100vh;

            }


            .login-info {

                padding:
                    25px;

            }


            .logo-box img {

                width: 55px;

                height: 55px;

            }


            .login-formulario {

                padding:
                    35px 24px;

            }

        }

    </style>

</head>


<body>


<div class="login-wrapper">

    <div class="login-card">


        <!-- =================================================
             PANEL IZQUIERDO
        ================================================== -->

        <section class="login-info">

            <div class="institucion">


                <div class="logo-box">

                    <img
                        src="<?= asset('img/logo.png') ?>"
                        alt="Colegio San José"
                    >

                    <div>

                        <h4>
                            Colegio San José
                        </h4>

                        <small>
                            Sistema de Gestión Técnica
                        </small>

                    </div>

                </div>


                <h1>
                    Gestión de solicitudes e intervenciones
                </h1>


                <p class="login-info-texto">

                    Registrá solicitudes de informática
                    o mantenimiento y consultá
                    el seguimiento de cada intervención.

                </p>


                <div class="items">


                    <div class="item">

                        <div class="item-icon">

                            <i class="bi bi-pc-display"></i>

                        </div>

                        <span>
                            Intervenciones de informática
                        </span>

                    </div>


                    <div class="item">

                        <div class="item-icon">

                            <i class="bi bi-tools"></i>

                        </div>

                        <span>
                            Mantenimiento general
                        </span>

                    </div>


                    <div class="item">

                        <div class="item-icon">

                            <i class="bi bi-images"></i>

                        </div>

                        <span>
                            Fotografías del problema y solución
                        </span>

                    </div>


                    <div class="item">

                        <div class="item-icon">

                            <i class="bi bi-clock-history"></i>

                        </div>

                        <span>
                            Seguimiento e historial de trabajos
                        </span>

                    </div>


                </div>

            </div>


            <div class="copyright">

                © <?= date('Y') ?>
                Colegio San José

            </div>

        </section>



        <!-- =================================================
             LOGIN
        ================================================== -->

        <section class="login-formulario">


            <a
                href="<?= url('index.php') ?>"
                class="volver"
            >

                <i class="bi bi-arrow-left"></i>

                Volver al inicio

            </a>


            <h2>
                Iniciar sesión
            </h2>


            <p class="descripcion">

                Ingresá con tu correo electrónico
                y tu número de DNI.

            </p>



            <!-- MENSAJE -->

            <?php if ($mensaje !== ''): ?>

                <div class="alert-ok">

                    <i class="bi bi-check-circle me-1"></i>

                    <?= e($mensaje) ?>

                </div>

            <?php endif; ?>



            <!-- ERROR -->

            <?php if ($error !== ''): ?>

                <div class="alert-error">

                    <i class="bi bi-exclamation-triangle me-1"></i>

                    <?= e($error) ?>

                </div>

            <?php endif; ?>



            <!-- FORMULARIO -->

            <form
                method="POST"
                action="<?= url('login.php') ?>"
                autocomplete="off"
            >


                <?= csrfInput() ?>


                <!-- CORREO -->

                <div class="mb-4">

                    <label
                        for="correo"
                        class="form-label"
                    >
                        Correo electrónico
                    </label>


                    <div class="input-group">

                        <span class="input-group-text">

                            <i class="bi bi-envelope"></i>

                        </span>


                        <input
                            type="email"
                            class="form-control"
                            id="correo"
                            name="correo"
                            value="<?= e($correo) ?>"
                            placeholder="nombre@colegio..."
                            maxlength="150"
                            autocomplete="username"
                            required
                            autofocus
                        >

                    </div>

                </div>



                <!-- DNI -->

                <div class="mb-4">

                    <label
                        for="dni"
                        class="form-label"
                    >
                        DNI
                    </label>


                    <div class="input-group">

                        <span class="input-group-text">

                            <i class="bi bi-person-vcard"></i>

                        </span>


                        <input
                            type="password"
                            class="form-control"
                            id="dni"
                            name="dni"
                            placeholder="Ingresá tu DNI"
                            inputmode="numeric"
                            pattern="[0-9.\-\s]{7,12}"
                            maxlength="12"
                            autocomplete="current-password"
                            required
                        >


                        <button
                            type="button"
                            class="btn btn-ver"
                            id="mostrarDni"
                            aria-label="Mostrar DNI"
                        >

                            <i
                                class="bi bi-eye"
                                id="iconoDni"
                            ></i>

                        </button>

                    </div>


                    <div class="form-text mt-2">

                        Podés escribirlo con
                        o sin puntos.

                    </div>

                </div>



                <!-- INGRESAR -->

                <button
                    type="submit"
                    class="btn btn-login"
                >

                    <i class="bi bi-box-arrow-in-right me-2"></i>

                    Ingresar al sistema

                </button>


            </form>



            <!-- AYUDA -->

            <div class="ayuda-login">

                <i class="bi bi-info-circle me-1"></i>

                Si no podés ingresar o tus datos
                no están registrados,
                comunicate con el responsable
                del sistema.

            </div>


        </section>

    </div>

</div>



<!-- =========================================================
     JS BOOTSTRAP
========================================================= -->

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>


<script>

// ============================================================
// MOSTRAR / OCULTAR DNI
// ============================================================

const campoDni =
    document.getElementById(
        'dni'
    );


const botonDni =
    document.getElementById(
        'mostrarDni'
    );


const iconoDni =
    document.getElementById(
        'iconoDni'
    );


botonDni.addEventListener(
    'click',
    function () {

        if (
            campoDni.type
            === 'password'
        ) {

            campoDni.type =
                'text';

            iconoDni.className =
                'bi bi-eye-slash';

            botonDni.setAttribute(
                'aria-label',
                'Ocultar DNI'
            );

        } else {

            campoDni.type =
                'password';

            iconoDni.className =
                'bi bi-eye';

            botonDni.setAttribute(
                'aria-label',
                'Mostrar DNI'
            );
        }

    }
);

</script>


</body>

</html>