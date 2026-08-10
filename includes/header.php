<?php
if(session_status()===PHP_SESSION_NONE){
    session_start();
}

$usuario=$_SESSION["usuario"] ?? null;

$__rolNav = $usuario['rol'] ?? '';
$__esTecnicoAdminNav = in_array($__rolNav, ['Tecnico', 'Administrador'], true);

$__hrefDashboard = url(function_exists('rutaDashboardRol') ? rutaDashboardRol() : 'dashboard.php');

$__hrefInformatica = $__esTecnicoAdminNav
    ? url('tecnico/informatica.php')
    : url('solicitudes.php?tipo=Informatica');

$__hrefMantenimiento = $__esTecnicoAdminNav
    ? url('tecnico/mantenimiento.php')
    : url('solicitudes.php?tipo=Mantenimiento');
?>
<!doctype html>
<html lang="es">

<head>

<meta charset="utf-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1">

<title>
Sistema Técnico | Colegio San José
</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<link
rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

<style>

:root{

--rojo:#B12626;
--rojoOscuro:#760000;
--blanco:#FFFFFF;
--gris:#F5F6F8;
--gris2:#ECECEC;

}

body{

background:var(--gris);

font-family:'Segoe UI',sans-serif;

}


.navbar-sanjo{

background:linear-gradient(
90deg,
var(--rojoOscuro),
var(--rojo)
);

box-shadow:0 3px 15px rgba(0,0,0,.25);

}

.navbar-brand{

color:#fff!important;

font-size:22px;

font-weight:bold;

}

.navbar-brand img{

height:48px;

margin-right:12px;

}

.nav-link{

color:#fff!important;

font-weight:500;

padding-left:15px!important;

padding-right:15px!important;

transition:.25s;

border-radius:8px;

}

.nav-link:hover{

background:rgba(255,255,255,.15);

}

.dropdown-menu{

border-radius:12px;

border:none;

box-shadow:0 8px 20px rgba(0,0,0,.15);

}

.card{

border:none;

border-radius:18px;

box-shadow:0 5px 18px rgba(0,0,0,.08);

}

.card-header{

background:var(--rojo);

color:white;

font-weight:bold;

}

.badge-sanjo{

background:#760000;

}

.btn-sanjo{

background:#B12626;

color:white;

}

.btn-sanjo:hover{

background:#760000;

color:white;

}

.titulo{

font-size:24px;

font-weight:700;

color:#760000;

margin-bottom:25px;

}

</style>

</head>

<body>


<nav class="navbar navbar-expand-lg navbar-dark navbar-sanjo">

<div class="container-fluid">

<a class="navbar-brand" href="<?= htmlspecialchars($__hrefDashboard) ?>">

<img src="<?= htmlspecialchars(asset('img/logo.png')) ?>">

Colegio San José

</a>

<button
class="navbar-toggler"
type="button"
data-bs-toggle="collapse"
data-bs-target="#menu">

<span class="navbar-toggler-icon"></span>

</button>

<div
class="collapse navbar-collapse"
id="menu">

<ul class="navbar-nav me-auto">

<li class="nav-item">
<a class="nav-link" href="<?= htmlspecialchars($__hrefDashboard) ?>">
<i class="bi bi-speedometer2"></i>
Dashboard
</a>
</li>

<?php if ($__esTecnicoAdminNav): ?>

<li class="nav-item">
<a class="nav-link" href="<?= htmlspecialchars(url('solicitudes.php')) ?>">
<i class="bi bi-ticket-detailed"></i>
Solicitudes
</a>
</li>

<li class="nav-item">
<a class="nav-link" href="<?= htmlspecialchars($__hrefInformatica) ?>">
<i class="bi bi-pc-display"></i>
Informática
</a>
</li>

<li class="nav-item">
<a class="nav-link" href="<?= htmlspecialchars($__hrefMantenimiento) ?>">
<i class="bi bi-tools"></i>
Mantenimiento
</a>
</li>

<?php endif; ?>

<li class="nav-item">
<a class="nav-link" href="<?= htmlspecialchars(url('horarios.php')) ?>">
<i class="bi bi-calendar-week"></i>
Horarios
</a>
</li>

<li class="nav-item">
<a class="nav-link" href="<?= htmlspecialchars(url('mejoras.php')) ?>">
<i class="bi bi-lightbulb"></i>
Mejoras
</a>
</li>

<?php if ($usuario): ?>

<li class="nav-item">
<a class="nav-link" href="<?= htmlspecialchars(url('pendientes.php')) ?>">
<i class="bi bi-hourglass-split"></i>
Pendientes
</a>
</li>

<?php endif; ?>

<?php if (in_array($usuario['rol'] ?? '', ['Tecnico', 'Administrador'], true)): ?>

<li class="nav-item">
<a class="nav-link" href="<?= htmlspecialchars(url('turnos.php')) ?>">
<i class="bi bi-calendar-check"></i>
Turnos
</a>
</li>

<li class="nav-item">
<a class="nav-link" href="<?= htmlspecialchars(url('tecnico/agenda.php')) ?>">
<i class="bi bi-calendar2-week"></i>
Mi agenda
</a>
</li>

<li class="nav-item">
<a class="nav-link" href="<?= htmlspecialchars(url('tecnico/repuestos.php')) ?>">
<i class="bi bi-box-seam"></i>
Repuestos
</a>
</li>

<?php endif; ?>

<?php if (($usuario['rol'] ?? '') === 'Administrador'): ?>

<li class="nav-item dropdown">

<a
class="nav-link dropdown-toggle"
href="#"
data-bs-toggle="dropdown">

<i class="bi bi-gear-wide-connected"></i>
Administración

</a>

<ul class="dropdown-menu">

<li>
<a
class="dropdown-item"
href="<?= htmlspecialchars(url('admin/horarios_tecnicos.php')) ?>">
<i class="bi bi-calendar-week"></i>
Horarios técnicos
</a>
</li>

<li>
<a
class="dropdown-item"
href="<?= htmlspecialchars(url('admin/repuestos.php')) ?>">
<i class="bi bi-box-seam"></i>
Catálogo repuestos
</a>
</li>

<li>
<a
class="dropdown-item"
href="<?= htmlspecialchars(url('admin/horas_extra.php')) ?>">
<i class="bi bi-clock-history"></i>
Horas extra
</a>
</li>

</ul>

</li>

<?php endif; ?>

</ul>

<ul class="navbar-nav">

<li class="nav-item dropdown">

<a
class="nav-link dropdown-toggle"
href="#"
data-bs-toggle="dropdown">

<i class="bi bi-person-circle"></i>

<?= htmlspecialchars($usuario["nombre"] ?? "Invitado") ?>

</a>

<ul class="dropdown-menu dropdown-menu-end">

<?php if ($__esTecnicoAdminNav): ?>

<li>

<a
class="dropdown-item"
href="<?= htmlspecialchars(url('tecnico/perfil.php')) ?>">

<i class="bi bi-person"></i>

Mi perfil

</a>

</li>

<?php endif; ?>

<li>

<a
class="dropdown-item"
href="<?= htmlspecialchars(url('mis_solicitudes.php')) ?>">

<i class="bi bi-ticket"></i>

Mis solicitudes

</a>

</li>

<li><hr></li>

<li>

<a
class="dropdown-item text-danger"
href="<?= htmlspecialchars(url('logout.php')) ?>">

<i class="bi bi-box-arrow-right"></i>

Cerrar sesión

</a>

</li>

</ul>

</li>

</ul>

</div>

</div>

</nav>

<div class="container-fluid mt-4">