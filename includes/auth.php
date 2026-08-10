<?php
// ============================================================
// COLEGIO SAN JOSÉ
// Sistema de Solicitudes e Intervenciones
// Archivo: includes/auth.php
// ============================================================

declare(strict_types=1);


// ============================================================
// INICIAR SESIÓN
// ============================================================

if (session_status() === PHP_SESSION_NONE) {

    session_start();
}


// ============================================================
// CONFIGURACIÓN BÁSICA DE SESIÓN
// ============================================================

// Tiempo máximo sin actividad:
// 8 horas

const TIEMPO_SESION = 28800;


// ============================================================
// COMPROBAR SI EXISTE SESIÓN
// ============================================================

function estaLogueado(): bool
{
    return isset(
        $_SESSION['usuario'],
        $_SESSION['usuario']['id_usuario']
    );
}


// ============================================================
// OBTENER USUARIO DE LA SESIÓN
// ============================================================

function usuarioActual(): ?array
{
    if (!estaLogueado()) {
        return null;
    }

    return $_SESSION['usuario'];
}


// ============================================================
// OBTENER ID DEL USUARIO
// ============================================================

function usuarioId(): ?int
{
    if (!estaLogueado()) {
        return null;
    }

    return (int)$_SESSION['usuario']['id_usuario'];
}


// ============================================================
// OBTENER NOMBRE
// ============================================================

function usuarioNombre(): string
{
    if (!estaLogueado()) {
        return '';
    }

    return trim(
        ($_SESSION['usuario']['nombre'] ?? '') .
        ' ' .
        ($_SESSION['usuario']['apellido'] ?? '')
    );
}


// ============================================================
// OBTENER CORREO
// ============================================================

function usuarioCorreo(): string
{
    if (!estaLogueado()) {
        return '';
    }

    return (string)(
        $_SESSION['usuario']['correo'] ?? ''
    );
}


// ============================================================
// OBTENER ROL
// ============================================================

function usuarioRol(): string
{
    if (!estaLogueado()) {
        return '';
    }

    return (string)(
        $_SESSION['usuario']['rol'] ?? ''
    );
}


// ============================================================
// COMPROBAR ROL
// ============================================================

function tieneRol(string $rol): bool
{
    if (!estaLogueado()) {
        return false;
    }

    return usuarioRol() === $rol;
}


// ============================================================
// ¿ES DOCENTE?
// ============================================================

function esDocente(): bool
{
    return tieneRol('Docente');
}


// ============================================================
// ¿ES TÉCNICO?
// ============================================================

function esTecnico(): bool
{
    return tieneRol('Tecnico');
}


// ============================================================
// ¿ES ADMINISTRADOR?
// ============================================================

function esAdministrador(): bool
{
    return tieneRol('Administrador');
}


// ============================================================
// TÉCNICO O ADMINISTRADOR
// ============================================================

function esPersonalTecnico(): bool
{
    return in_array(
        usuarioRol(),
        [
            'Tecnico',
            'Administrador'
        ],
        true
    );
}


// ============================================================
// VERIFICAR UNO DE VARIOS ROLES
//
// Ejemplo:
//
// if (tieneAlgunoDeLosRoles(['Tecnico','Administrador'])) {
//
// }
//
// ============================================================

function tieneAlgunoDeLosRoles(
    array $roles
): bool {

    if (!estaLogueado()) {
        return false;
    }

    return in_array(
        usuarioRol(),
        $roles,
        true
    );
}


// ============================================================
// RUTA BASE
//
// Cambiar solamente si el proyecto está en otra carpeta.
//
// Ejemplo XAMPP:
//
// C:\xampp\htdocs\tecnica\
//
// URL:
//
// http://localhost/tecnica/
//
// ============================================================

function rutaBase(): string
{
    return '/tecnica/';
}


// ============================================================
// REDIRECCIONAR AL LOGIN
// ============================================================

function irAlLogin(): never
{
    header(
        'Location: ' .
        rutaBase() .
        'login.php'
    );

    exit;
}


// ============================================================
// REDIRECCIONAR AL DASHBOARD
// ============================================================

function irAlDashboard(): never
{
    header(
        'Location: ' .
        rutaBase() .
        'dashboard.php'
    );

    exit;
}


// ============================================================
// PROTEGER PÁGINA
//
// Uso:
//
// require_once __DIR__ . '/includes/auth.php';
//
// requerirLogin();
//
// ============================================================

function requerirLogin(): void
{
    if (!estaLogueado()) {

        $_SESSION['url_despues_login'] =
            $_SERVER['REQUEST_URI'] ?? null;

        irAlLogin();
    }


    // ========================================================
    // CONTROL DE EXPIRACIÓN
    // ========================================================

    controlarTiempoSesion();
}


// ============================================================
// EXIGIR UN ROL ESPECÍFICO
//
// Ejemplo:
//
// requerirRol('Administrador');
//
// ============================================================

function requerirRol(
    string $rol
): void {

    requerirLogin();

    if (!tieneRol($rol)) {

        accesoDenegado();
    }
}


// ============================================================
// EXIGIR VARIOS ROLES
//
// Ejemplo:
//
// requerirRoles([
//     'Tecnico',
//     'Administrador'
// ]);
//
// ============================================================

function requerirRoles(
    array $roles
): void {

    requerirLogin();

    if (
        !tieneAlgunoDeLosRoles(
            $roles
        )
    ) {

        accesoDenegado();
    }
}


// ============================================================
// ACCESO SOLO PARA DOCENTES
// ============================================================

function requerirDocente(): void
{
    requerirRol(
        'Docente'
    );
}


// ============================================================
// ACCESO SOLO PARA TÉCNICOS
// ============================================================

function requerirTecnico(): void
{
    requerirRoles([
        'Tecnico',
        'Administrador'
    ]);
}


// ============================================================
// ACCESO SOLO ADMINISTRADOR
// ============================================================

function requerirAdministrador(): void
{
    requerirRol(
        'Administrador'
    );
}


// ============================================================
// ACCESO DENEGADO
// ============================================================

function accesoDenegado(): never
{
    http_response_code(403);

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
            Acceso denegado | Colegio San José
        </title>

        <link
            href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
            rel="stylesheet"
        >

        <link
            rel="stylesheet"
            href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
        >

        <style>

            body {

                min-height: 100vh;

                display: flex;

                align-items: center;

                justify-content: center;

                background: #f5f6f8;

                font-family:
                    "Segoe UI",
                    Arial,
                    sans-serif;

            }


            .acceso-card {

                max-width: 520px;

                width: 92%;

                background: #ffffff;

                border-radius: 20px;

                overflow: hidden;

                box-shadow:
                    0 12px 40px
                    rgba(0, 0, 0, .12);

            }


            .acceso-header {

                background:
                    linear-gradient(
                        135deg,
                        #760000,
                        #B12626
                    );

                color: #ffffff;

                padding: 35px;

                text-align: center;

            }


            .acceso-header i {

                font-size: 55px;

            }


            .acceso-body {

                padding: 35px;

                text-align: center;

            }


            .btn-sanjo {

                background: #B12626;

                border-color: #B12626;

                color: #ffffff;

            }


            .btn-sanjo:hover {

                background: #760000;

                border-color: #760000;

                color: #ffffff;

            }

        </style>

    </head>

    <body>

        <div class="acceso-card">

            <div class="acceso-header">

                <i class="bi bi-shield-lock"></i>

                <h3 class="mt-3 mb-0">
                    Acceso restringido
                </h3>

            </div>


            <div class="acceso-body">

                <p class="text-muted mb-4">

                    Su cuenta no posee permisos
                    para acceder a esta sección
                    del sistema.

                </p>


                <a
                    href="<?= htmlspecialchars(rutaBase()) ?>dashboard.php"
                    class="btn btn-sanjo px-4"
                >

                    <i class="bi bi-arrow-left me-1"></i>

                    Volver al dashboard

                </a>

            </div>

        </div>

    </body>

    </html>

    <?php

    exit;
}


// ============================================================
// CONTROLAR TIEMPO DE SESIÓN
// ============================================================

function controlarTiempoSesion(): void
{
    if (!estaLogueado()) {
        return;
    }


    $ahora = time();


    if (
        isset($_SESSION['ultima_actividad'])
    ) {

        $tiempoInactivo =
            $ahora -
            (int)$_SESSION['ultima_actividad'];


        if (
            $tiempoInactivo >
            TIEMPO_SESION
        ) {

            cerrarSesion();


            session_start();

            $_SESSION['mensaje_login'] =
                'La sesión expiró por inactividad.';


            irAlLogin();
        }
    }


    $_SESSION['ultima_actividad'] =
        $ahora;
}


// ============================================================
// INICIAR SESIÓN DE USUARIO
//
// Esta función será utilizada desde login.php
//
// ============================================================

function iniciarSesionUsuario(
    array $usuario
): void {

    // Evita session fixation

    session_regenerate_id(true);


    $_SESSION['usuario'] = [

        'id_usuario' =>
            (int)$usuario['id_usuario'],

        'nombre' =>
            $usuario['nombre'] ?? '',

        'apellido' =>
            $usuario['apellido'] ?? '',

        'correo' =>
            $usuario['correo'] ?? '',

        'rol' =>
            $usuario['rol'] ?? 'Docente',

        'estado' =>
            $usuario['estado'] ?? 'Activo'

    ];


    $_SESSION['ultima_actividad'] =
        time();
}


// ============================================================
// CERRAR SESIÓN
// ============================================================

function cerrarSesion(): void
{
    $_SESSION = [];


    // ========================================================
    // BORRAR COOKIE DE SESIÓN
    // ========================================================

    if (
        ini_get('session.use_cookies')
    ) {

        $parametros =
            session_get_cookie_params();


        setcookie(

            session_name(),

            '',

            time() - 42000,

            $parametros['path'],

            $parametros['domain'],

            $parametros['secure'],

            $parametros['httponly']

        );
    }


    // ========================================================
    // DESTRUIR SESIÓN
    // ========================================================

    if (
        session_status()
        === PHP_SESSION_ACTIVE
    ) {

        session_destroy();
    }
}


// ============================================================
// VERIFICAR QUE EL USUARIO SIGUE ACTIVO EN LA BASE DE DATOS
//
// Requiere $conexion de includes/config.php
//
// ============================================================

function verificarUsuarioActivo(
    PDO $conexion
): bool {

    if (!estaLogueado()) {
        return false;
    }


    $sql = "
        SELECT
            estado,
            rol,
            nombre,
            apellido,
            correo

        FROM usuarios

        WHERE id_usuario = ?

        LIMIT 1
    ";


    $stmt =
        $conexion->prepare($sql);


    $stmt->execute([
        usuarioId()
    ]);


    $usuario =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );


    if (!$usuario) {

        cerrarSesion();

        return false;
    }


    if (
        $usuario['estado']
        !== 'Activo'
    ) {

        cerrarSesion();

        return false;
    }


    // ========================================================
    // ACTUALIZAR DATOS DE SESIÓN
    // POR SI EL ADMINISTRADOR CAMBIÓ ROL O NOMBRE
    // ========================================================

    $_SESSION['usuario']['nombre'] =
        $usuario['nombre'];

    $_SESSION['usuario']['apellido'] =
        $usuario['apellido'];

    $_SESSION['usuario']['correo'] =
        $usuario['correo'];

    $_SESSION['usuario']['rol'] =
        $usuario['rol'];

    $_SESSION['usuario']['estado'] =
        $usuario['estado'];


    return true;
}


// ============================================================
// VERIFICAR PROPIEDAD DE SOLICITUD
//
// Permite que:
//
// Docente:
// solamente vea sus propias solicitudes.
//
// Técnico / Administrador:
// pueda ver todas.
//
// ============================================================

function puedeVerSolicitud(
    PDO $conexion,
    int $idSolicitud
): bool {

    if (!estaLogueado()) {
        return false;
    }


    // Técnicos y administradores
    // pueden ver todas

    if (esPersonalTecnico()) {

        return true;
    }


    // ========================================================
    // DOCENTE
    // ========================================================

    $sql = "
        SELECT COUNT(*)

        FROM solicitudes

        WHERE id_solicitud = ?

        AND id_usuario = ?
    ";


    $stmt =
        $conexion->prepare($sql);


    $stmt->execute([

        $idSolicitud,

        usuarioId()

    ]);


    return
        (int)$stmt->fetchColumn()
        > 0;
}


// ============================================================
// EXIGIR PERMISO PARA VER SOLICITUD
// ============================================================

function requerirAccesoSolicitud(
    PDO $conexion,
    int $idSolicitud
): void {

    requerirLogin();


    if (
        !puedeVerSolicitud(
            $conexion,
            $idSolicitud
        )
    ) {

        accesoDenegado();
    }
}


// ============================================================
// DOCENTE PUEDE MODIFICAR SOLICITUD
//
// Solo mientras se encuentre en estado Nueva.
//
// ============================================================

function puedeEditarSolicitud(
    PDO $conexion,
    int $idSolicitud
): bool {

    if (!estaLogueado()) {
        return false;
    }


    // Administrador puede editar

    if (esAdministrador()) {

        return true;
    }


    // Técnico no modifica
    // la solicitud original

    if (esTecnico()) {

        return false;
    }


    // ========================================================
    // DOCENTE
    // ========================================================

    $sql = "
        SELECT COUNT(*)

        FROM solicitudes

        WHERE id_solicitud = ?

        AND id_usuario = ?

        AND estado = 'Nueva'
    ";


    $stmt =
        $conexion->prepare($sql);


    $stmt->execute([

        $idSolicitud,

        usuarioId()

    ]);


    return
        (int)$stmt->fetchColumn()
        > 0;
}


// ============================================================
// EVITAR QUE USUARIO LOGUEADO ENTRE AL LOGIN
// ============================================================

function soloInvitados(): void
{
    if (estaLogueado()) {

        irAlDashboard();
    }
}