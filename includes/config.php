<?php
// ============================================================
// COLEGIO SAN JOSÉ
// Sistema de Solicitudes e Intervenciones
// Archivo: includes/config.php
// ============================================================

declare(strict_types=1);


// ============================================================
// ZONA HORARIA
// ============================================================

date_default_timezone_set(
    'America/Argentina/Cordoba'
);


// ============================================================
// CONFIGURACIÓN DE ERRORES
//
// DESARROLLO LOCAL:
// display_errors = 1
//
// PRODUCCIÓN:
// display_errors = 0
// ============================================================

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

error_reporting(E_ALL);


// ============================================================
// CONFIGURACIÓN DE SESIÓN
// ============================================================

// Evita que JavaScript pueda acceder a la cookie de sesión.

ini_set(
    'session.cookie_httponly',
    '1'
);


// Solo utilizar cookies para manejar la sesión.

ini_set(
    'session.use_only_cookies',
    '1'
);


// Modo estricto de sesiones.

ini_set(
    'session.use_strict_mode',
    '1'
);


// ============================================================
// INICIAR SESIÓN
// ============================================================

if (
    session_status()
    === PHP_SESSION_NONE
) {

    session_start();
}


// ============================================================
// DATOS DE BASE DE DATOS
// XAMPP LOCAL
// ============================================================

$dbHost = 'localhost';

$dbName = 'tecnica_sanjo';

$dbUser = 'root';

$dbPass = '';

$dbCharset = 'utf8mb4';


// ============================================================
// DSN PDO
// ============================================================

$dsn =
    "mysql:host={$dbHost};" .
    "dbname={$dbName};" .
    "charset={$dbCharset}";


// ============================================================
// OPCIONES PDO
// ============================================================

$opcionesPDO = [

    // Lanzar excepciones ante errores SQL.

    PDO::ATTR_ERRMODE =>
        PDO::ERRMODE_EXCEPTION,


    // Devuelve resultados como array asociativo.

    PDO::ATTR_DEFAULT_FETCH_MODE =>
        PDO::FETCH_ASSOC,


    // Utilizar consultas preparadas reales de MySQL.

    PDO::ATTR_EMULATE_PREPARES =>
        false,


    // Evita convertir valores numéricos
    // innecesariamente a cadenas.

    PDO::ATTR_STRINGIFY_FETCHES =>
        false
];


// ============================================================
// CONEXIÓN
// ============================================================

try {

    $conexion =
        new PDO(
            $dsn,
            $dbUser,
            $dbPass,
            $opcionesPDO
        );


    // ========================================================
    // ZONA HORARIA DE MYSQL
    //
    // Argentina = UTC -03:00
    // ========================================================

    $conexion->exec(
        "SET time_zone = '-03:00'"
    );


} catch (PDOException $e) {

    // ========================================================
    // REGISTRAR ERROR
    // ========================================================

    error_log(
        'Error de conexión MySQL: ' .
        $e->getMessage()
    );


    // ========================================================
    // EN DESARROLLO
    // Mostramos información para poder diagnosticar.
    //
    // En producción conviene mostrar solamente:
    //
    // "No se pudo conectar con la base de datos."
    // ========================================================

    http_response_code(500);

    die(
        '<!doctype html>
        <html lang="es">
        <head>

            <meta charset="utf-8">

            <meta
                name="viewport"
                content="width=device-width, initial-scale=1"
            >

            <title>
                Error de conexión
            </title>

            <style>

                * {
                    box-sizing: border-box;
                }

                body {

                    margin: 0;

                    min-height: 100vh;

                    display: flex;

                    align-items: center;

                    justify-content: center;

                    padding: 20px;

                    background: #f5f6f8;

                    font-family:
                        "Segoe UI",
                        Arial,
                        sans-serif;

                }


                .error-box {

                    max-width: 620px;

                    width: 100%;

                    background: #ffffff;

                    border-radius: 18px;

                    overflow: hidden;

                    box-shadow:
                        0 10px 35px
                        rgba(0,0,0,.12);

                }


                .error-header {

                    background:
                        linear-gradient(
                            135deg,
                            #760000,
                            #B12626
                        );

                    color: #ffffff;

                    padding: 28px;

                }


                .error-header h2 {

                    margin: 0;

                }


                .error-body {

                    padding: 30px;

                    color: #333333;

                }


                .error-detalle {

                    margin-top: 20px;

                    padding: 15px;

                    border-radius: 10px;

                    background: #f5f5f5;

                    border-left:
                        4px solid #B12626;

                    word-break: break-word;

                    font-family: monospace;

                    font-size: 13px;

                }

            </style>

        </head>

        <body>

            <div class="error-box">

                <div class="error-header">

                    <h2>
                        Colegio San José
                    </h2>

                    <div>
                        Sistema de Gestión Técnica
                    </div>

                </div>


                <div class="error-body">

                    <h3>
                        No se pudo conectar con la base de datos
                    </h3>

                    <p>
                        Verificá que MySQL esté iniciado
                        y que la base
                        <strong>tecnica_sanjo</strong>
                        exista.
                    </p>

                    <div class="error-detalle">'
                        .
                        htmlspecialchars(
                            $e->getMessage(),
                            ENT_QUOTES,
                            'UTF-8'
                        )
                        .
                    '</div>

                </div>

            </div>

        </body>
        </html>'
    );
}


// ============================================================
// CONSTANTES DEL SISTEMA
// ============================================================

define(
    'NOMBRE_SISTEMA',
    'Sistema de Gestión Técnica'
);

define(
    'NOMBRE_INSTITUCION',
    'Colegio San José'
);


// ============================================================
// RUTA FÍSICA DEL PROYECTO
//
// Ejemplo:
//
// C:\xampp\htdocs\tecnica
// ============================================================

define(
    'BASE_PATH',
    dirname(__DIR__)
);


// ============================================================
// URL BASE
//
// Si la carpeta en htdocs es:
//
// C:\xampp\htdocs\tecnica
//
// URL:
//
// http://localhost/tecnica/
//
// Si le ponés otro nombre a la carpeta,
// cambiar esta constante.
// ============================================================

define(
    'BASE_URL',
    '/tecnica/'
);


// ============================================================
// DIRECTORIOS DE IMÁGENES
// ============================================================

define(
    'UPLOAD_PATH',
    BASE_PATH . '/uploads'
);


define(
    'UPLOAD_SOLICITUDES',
    UPLOAD_PATH .
    '/solicitudes'
);


define(
    'UPLOAD_INTERVENCIONES',
    UPLOAD_PATH .
    '/intervenciones'
);


define(
    'UPLOAD_MEJORAS',
    UPLOAD_PATH .
    '/mejoras'
);


define(
    'UPLOAD_REPUESTOS',
    UPLOAD_PATH .
    '/repuestos'
);


// ============================================================
// URL DE IMÁGENES
// ============================================================

define(
    'UPLOAD_URL',
    BASE_URL .
    'uploads/'
);


define(
    'UPLOAD_SOLICITUDES_URL',
    UPLOAD_URL .
    'solicitudes/'
);


define(
    'UPLOAD_INTERVENCIONES_URL',
    UPLOAD_URL .
    'intervenciones/'
);


define(
    'UPLOAD_MEJORAS_URL',
    UPLOAD_URL .
    'mejoras/'
);


define(
    'UPLOAD_REPUESTOS_URL',
    UPLOAD_URL .
    'repuestos/'
);


// ============================================================
// TAMAÑO MÁXIMO DE IMÁGENES
// 5 MB
// ============================================================

define(
    'MAX_IMAGEN_MB',
    5
);


// ============================================================
// COLORES INSTITUCIONALES
// ============================================================

define(
    'COLOR_PRINCIPAL',
    '#B12626'
);


define(
    'COLOR_OSCURO',
    '#760000'
);


define(
    'COLOR_BLANCO',
    '#FFFFFF'
);


// ============================================================
// CONFIGURACIÓN DE CORREO (SMTP)
//
// Completar estos valores para habilitar el envío
// automático de correos (nuevo ticket y mensajes
// con el técnico). Mientras SMTP_HOST esté vacío,
// el sistema sigue funcionando normalmente pero
// no se envían correos (queda solo el registro
// interno de notificaciones).
//
// Ejemplo Gmail (cuenta del colegio):
//
// SMTP_HOST     -> smtp.gmail.com
// SMTP_PUERTO   -> 587
// SMTP_SEGURIDAD -> 'tls'
// SMTP_USUARIO  -> cuenta@colegiodesanjose.edu.ar
// SMTP_CLAVE    -> contraseña de aplicación (NO la contraseña normal)
//
// Cómo generar una contraseña de aplicación en Gmail:
// Cuenta de Google -> Seguridad -> Verificación en dos pasos
// -> Contraseñas de aplicaciones.
// ============================================================

define(
    'SMTP_HOST',
    ''
);

define(
    'SMTP_PUERTO',
    587
);

// 'tls' (puerto 587, el más común) o 'ssl' (puerto 465).

define(
    'SMTP_SEGURIDAD',
    'tls'
);

define(
    'SMTP_USUARIO',
    ''
);

define(
    'SMTP_CLAVE',
    ''
);

define(
    'SMTP_DESDE_EMAIL',
    SMTP_USUARIO
);

define(
    'SMTP_DESDE_NOMBRE',
    'Sistema Técnico - Colegio San José'
);

// Se activa solo cuando host/usuario/clave están completos.

define(
    'SMTP_HABILITADO',
    SMTP_HOST !== ''
    && SMTP_USUARIO !== ''
    && SMTP_CLAVE !== ''
);


// ============================================================
// CREAR DIRECTORIOS DE UPLOAD SI NO EXISTEN
// ============================================================

$directoriosUpload = [

    UPLOAD_PATH,

    UPLOAD_SOLICITUDES,

    UPLOAD_INTERVENCIONES,

    UPLOAD_MEJORAS,

    UPLOAD_REPUESTOS

];


foreach (
    $directoriosUpload
    as $directorio
) {

    if (!is_dir($directorio)) {

        @mkdir(
            $directorio,
            0755,
            true
        );
    }
}


// ============================================================
// FUNCIÓN PARA GENERAR URL DEL SISTEMA
//
// Ejemplo:
//
// url('dashboard.php')
//
// devuelve:
//
// /tecnica/dashboard.php
// ============================================================

function url(
    string $ruta = ''
): string {

    return BASE_URL .
        ltrim(
            $ruta,
            '/'
        );
}


// ============================================================
// FUNCIÓN PARA ASSETS
//
// Ejemplo:
//
// asset('img/logo.png')
//
// devuelve:
//
// /tecnica/assets/img/logo.png
// ============================================================

function asset(
    string $ruta
): string {

    return BASE_URL .
        'assets/' .
        ltrim(
            $ruta,
            '/'
        );
}