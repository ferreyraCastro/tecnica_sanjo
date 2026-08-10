<?php
// ============================================================
// COLEGIO SAN JOSÉ
// Sistema de Gestión Técnica
// Archivo: /tecnica/logout.php
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';


// ============================================================
// ELIMINAR DATOS DE SESIÓN
// ============================================================

$_SESSION = [];


// ============================================================
// ELIMINAR COOKIE DE SESIÓN
// ============================================================

if (ini_get('session.use_cookies')) {

    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}


// ============================================================
// DESTRUIR SESIÓN
// ============================================================

session_destroy();


// ============================================================
// NUEVA SESIÓN PARA MENSAJE
// ============================================================

session_start();

$_SESSION['mensaje_login'] = 'La sesión se cerró correctamente.';


// ============================================================
// REDIRECCIONAR AL LOGIN
// ============================================================

header('Location: login.php');
exit;