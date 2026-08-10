<?php
// ============================================================
// COLEGIO SAN JOSÉ
// Sistema de Gestión Técnica
// Archivo: /tecnica/tecnico/dashboard.php
//
// ADAPTADO A LA BASE tecnica_sanjo ACTUAL
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/funciones.php';
require_once __DIR__ . '/../includes/auth.php';


// ============================================================
// PERMISOS
// ============================================================

requerirTecnico();


// ============================================================
// VERIFICAR USUARIO ACTIVO
// ============================================================

if (!verificarUsuarioActivo($conexion)) {

    $_SESSION['mensaje_login'] =
        'Tu sesión finalizó o tu cuenta se encuentra inactiva.';

    header(
        'Location: ' . url('login.php')
    );

    exit;
}


// ============================================================
// TÉCNICO ACTUAL
// ============================================================

$idTecnico = (int) usuarioId();


// ============================================================
// ESTADÍSTICAS
//
// Usamos EXISTS para evitar duplicados si en algún momento
// hubiera más de una asignación histórica.
// ============================================================

$stmtStats = $conexion->prepare("
    SELECT

        COUNT(*) AS total,

        SUM(
            CASE
                WHEN s.estado = 'Asignada'
                THEN 1
                ELSE 0
            END
        ) AS asignadas,

        SUM(
            CASE
                WHEN s.estado = 'En proceso'
                THEN 1
                ELSE 0
            END
        ) AS en_proceso,

        SUM(
            CASE
                WHEN s.estado = 'Pendiente'
                THEN 1
                ELSE 0
            END
        ) AS pendientes,

        SUM(
            CASE
                WHEN s.estado = 'Resuelta'
                THEN 1
                ELSE 0
            END
        ) AS resueltas,

        SUM(
            CASE
                WHEN
                    s.prioridad = 'Urgente'

                    AND s.estado NOT IN (
                        'Resuelta',
                        'Cerrada',
                        'Cancelada'
                    )

                THEN 1
                ELSE 0
            END
        ) AS urgentes

    FROM solicitudes s

    WHERE EXISTS (

        SELECT 1

        FROM solicitudes_asignaciones sa

        WHERE
            sa.id_solicitud = s.id_solicitud

        AND
            sa.id_tecnico = ?

        AND
            sa.activo = 1
    )
");


$stmtStats->execute([
    $idTecnico
]);


$statsDB = $stmtStats->fetch(
    PDO::FETCH_ASSOC
);


$stats = [

    'total' =>
        (int)($statsDB['total'] ?? 0),

    'asignadas' =>
        (int)($statsDB['asignadas'] ?? 0),

    'en_proceso' =>
        (int)($statsDB['en_proceso'] ?? 0),

    'pendientes' =>
        (int)($statsDB['pendientes'] ?? 0),

    'resueltas' =>
        (int)($statsDB['resueltas'] ?? 0),

    'urgentes' =>
        (int)($statsDB['urgentes'] ?? 0)

];


// ============================================================
// SOLICITUDES ABIERTAS DEL TÉCNICO
//
// IMPORTANTE:
// En tu tabla solicitudes_asignaciones NO existen:
// - observaciones
// - fecha_fin
//
// Por eso no se consultan.
// ============================================================

$stmtSolicitudes = $conexion->prepare("
    SELECT

        s.id_solicitud,
        s.id_usuario,
        s.tipo,
        s.titulo,
        s.descripcion,
        s.prioridad,
        s.estado,
        s.motivo_pendiente,
        s.fecha_creacion,
        s.fecha_actualizacion,
        s.fecha_resolucion,

        CONCAT(
            u.nombre,
            ' ',
            u.apellido
        ) AS solicitante,

        u.correo,

        sec.nombre AS sector,

        cat.nombre AS categoria,

        sa.fecha_asignacion,

        (
            SELECT COUNT(*)

            FROM solicitud_imagenes si

            WHERE
                si.id_solicitud = s.id_solicitud
        ) AS fotos,

        (
            SELECT COUNT(*)

            FROM comentarios c

            WHERE
                c.id_solicitud = s.id_solicitud
        ) AS comentarios,

        (
            SELECT COUNT(*)

            FROM intervenciones i

            WHERE
                i.id_solicitud = s.id_solicitud
        ) AS intervenciones

    FROM solicitudes s

    INNER JOIN usuarios u
        ON s.id_usuario = u.id_usuario

    LEFT JOIN sectores sec
        ON s.id_sector = sec.id_sector

    LEFT JOIN categorias cat
        ON s.id_categoria = cat.id_categoria

    INNER JOIN solicitudes_asignaciones sa
        ON sa.id_solicitud = s.id_solicitud

    WHERE
        sa.id_tecnico = ?

    AND
        sa.activo = 1

    AND
        sa.id_asignacion = (

            SELECT MAX(sa2.id_asignacion)

            FROM solicitudes_asignaciones sa2

            WHERE
                sa2.id_solicitud = s.id_solicitud

            AND
                sa2.id_tecnico = ?

            AND
                sa2.activo = 1
        )

    AND
        s.estado NOT IN (
            'Cerrada',
            'Cancelada'
        )

    ORDER BY

        CASE s.prioridad

            WHEN 'Urgente'
                THEN 1

            WHEN 'Alta'
                THEN 2

            WHEN 'Normal'
                THEN 3

            WHEN 'Baja'
                THEN 4

            ELSE 5

        END,

        CASE s.estado

            WHEN 'En proceso'
                THEN 1

            WHEN 'Asignada'
                THEN 2

            WHEN 'Pendiente'
                THEN 3

            WHEN 'Nueva'
                THEN 4

            WHEN 'Resuelta'
                THEN 5

            ELSE 6

        END,

        s.fecha_creacion ASC

    LIMIT 12
");


$stmtSolicitudes->execute([
    $idTecnico,
    $idTecnico
]);


$misSolicitudes =
    $stmtSolicitudes->fetchAll(
        PDO::FETCH_ASSOC
    );


// ============================================================
// SOLICITUDES URGENTES
// ============================================================

$stmtUrgentes = $conexion->prepare("
    SELECT

        s.id_solicitud,
        s.titulo,
        s.estado,
        s.tipo,
        s.fecha_creacion,

        sec.nombre AS sector

    FROM solicitudes s

    LEFT JOIN sectores sec
        ON s.id_sector = sec.id_sector

    WHERE
        s.prioridad = 'Urgente'

    AND
        s.estado NOT IN (
            'Resuelta',
            'Cerrada',
            'Cancelada'
        )

    AND EXISTS (

        SELECT 1

        FROM solicitudes_asignaciones sa

        WHERE
            sa.id_solicitud = s.id_solicitud

        AND
            sa.id_tecnico = ?

        AND
            sa.activo = 1
    )

    ORDER BY
        s.fecha_creacion ASC

    LIMIT 5
");


$stmtUrgentes->execute([
    $idTecnico
]);


$urgentes =
    $stmtUrgentes->fetchAll(
        PDO::FETCH_ASSOC
    );


// ============================================================
// SOLICITUDES PENDIENTES
// ============================================================

$stmtPendientes = $conexion->prepare("
    SELECT

        s.id_solicitud,
        s.titulo,
        s.tipo,
        s.prioridad,
        s.estado,
        s.motivo_pendiente,
        s.fecha_creacion,
        s.fecha_actualizacion,

        sec.nombre AS sector

    FROM solicitudes s

    LEFT JOIN sectores sec
        ON s.id_sector = sec.id_sector

    WHERE
        s.estado = 'Pendiente'

    AND EXISTS (

        SELECT 1

        FROM solicitudes_asignaciones sa

        WHERE
            sa.id_solicitud = s.id_solicitud

        AND
            sa.id_tecnico = ?

        AND
            sa.activo = 1
    )

    ORDER BY

        CASE s.prioridad

            WHEN 'Urgente'
                THEN 1

            WHEN 'Alta'
                THEN 2

            WHEN 'Normal'
                THEN 3

            WHEN 'Baja'
                THEN 4

            ELSE 5

        END,

        s.fecha_actualizacion DESC

    LIMIT 5
");


$stmtPendientes->execute([
    $idTecnico
]);


$pendientes =
    $stmtPendientes->fetchAll(
        PDO::FETCH_ASSOC
    );


// ============================================================
// ÚLTIMAS INTERVENCIONES DEL TÉCNICO
// ============================================================

$stmtIntervenciones = $conexion->prepare("
    SELECT

        i.id_intervencion,
        i.id_solicitud,
        i.diagnostico,
        i.trabajo_realizado,
        i.materiales,
        i.observaciones,
        i.pendiente,
        i.motivo_pendiente,
        i.fecha_inicio,
        i.fecha_fin,
        i.fecha_intervencion,

        s.titulo,
        s.tipo,
        s.estado,

        sec.nombre AS sector,

        (
            SELECT COUNT(*)

            FROM intervencion_imagenes ii

            WHERE
                ii.id_intervencion = i.id_intervencion
        ) AS imagenes

    FROM intervenciones i

    INNER JOIN solicitudes s
        ON i.id_solicitud = s.id_solicitud

    LEFT JOIN sectores sec
        ON s.id_sector = sec.id_sector

    WHERE
        i.id_tecnico = ?

    ORDER BY
        i.fecha_intervencion DESC

    LIMIT 6
");


$stmtIntervenciones->execute([
    $idTecnico
]);


$ultimasIntervenciones =
    $stmtIntervenciones->fetchAll(
        PDO::FETCH_ASSOC
    );


// ============================================================
// TOTAL DE INTERVENCIONES
// ============================================================

$stmtTotalIntervenciones = $conexion->prepare("
    SELECT COUNT(*)

    FROM intervenciones

    WHERE id_tecnico = ?
");


$stmtTotalIntervenciones->execute([
    $idTecnico
]);


$totalIntervenciones =
    (int)$stmtTotalIntervenciones
        ->fetchColumn();


// ============================================================
// INTERVENCIONES DEL MES ACTUAL
// ============================================================

$stmtMes = $conexion->prepare("
    SELECT COUNT(*)

    FROM intervenciones

    WHERE
        id_tecnico = ?

    AND
        YEAR(fecha_intervencion) =
        YEAR(CURRENT_DATE())

    AND
        MONTH(fecha_intervencion) =
        MONTH(CURRENT_DATE())
");


$stmtMes->execute([
    $idTecnico
]);


$intervencionesMes =
    (int)$stmtMes->fetchColumn();


// ============================================================
// HORARIOS DE MANTENIMIENTO
//
// Consultamos directamente la tabla real:
// horarios_mantenimiento
// ============================================================

$stmtHorariosInformatica = $conexion->prepare("
    SELECT

        id_horario,
        tipo,
        dia,
        hora_desde,
        hora_hasta,
        responsable,
        observaciones,
        activo

    FROM horarios_mantenimiento

    WHERE
        tipo = 'Informatica'

    AND
        activo = 1

    ORDER BY

        FIELD(
            dia,
            'Lunes',
            'Martes',
            'Miercoles',
            'Jueves',
            'Viernes',
            'Sabado'
        ),

        hora_desde ASC
");


$stmtHorariosInformatica->execute();


$horariosInformatica =
    $stmtHorariosInformatica->fetchAll(
        PDO::FETCH_ASSOC
    );


// ============================================================
// HORARIOS MANTENIMIENTO GENERAL
// ============================================================

$stmtHorariosMantenimiento = $conexion->prepare("
    SELECT

        id_horario,
        tipo,
        dia,
        hora_desde,
        hora_hasta,
        responsable,
        observaciones,
        activo

    FROM horarios_mantenimiento

    WHERE
        tipo = 'Mantenimiento'

    AND
        activo = 1

    ORDER BY

        FIELD(
            dia,
            'Lunes',
            'Martes',
            'Miercoles',
            'Jueves',
            'Viernes',
            'Sabado'
        ),

        hora_desde ASC
");


$stmtHorariosMantenimiento->execute();


$horariosMantenimiento =
    $stmtHorariosMantenimiento->fetchAll(
        PDO::FETCH_ASSOC
    );


// ============================================================
// DÍA ACTUAL
// ============================================================

$diasSemana = [

    'Monday' =>
        'Lunes',

    'Tuesday' =>
        'Martes',

    'Wednesday' =>
        'Miercoles',

    'Thursday' =>
        'Jueves',

    'Friday' =>
        'Viernes',

    'Saturday' =>
        'Sabado',

    'Sunday' =>
        'Domingo'

];


$diaActual =
    $diasSemana[date('l')]
    ?? '';


// ============================================================
// FUNCIONES LOCALES
// ============================================================

function mostrarDiaTecnico(
    string $dia
): string {

    return match ($dia) {

        'Miercoles'
            => 'Miércoles',

        'Sabado'
            => 'Sábado',

        default
            => $dia
    };
}


function mostrarHoraTecnico(
    ?string $hora
): string {

    if (!$hora) {

        return '-';
    }


    return substr(
        $hora,
        0,
        5
    );
}


// ============================================================
// FLASH
// ============================================================

$flash =
    obtenerFlash();


// ============================================================
// HEADER
// ============================================================

require_once __DIR__ . '/../includes/header.php';

?>


<style>

.tecnico-dashboard {

    max-width: 1550px;
    margin: 0 auto;
    padding: 5px 12px 50px;

}


/* ============================================================
   HERO
============================================================ */

.tecnico-hero {

    position: relative;
    overflow: hidden;

    padding: 30px;
    margin-bottom: 24px;

    border-radius: 22px;

    color: #FFFFFF;

    background:
        linear-gradient(
            135deg,
            #760000,
            #B12626
        );

    box-shadow:
        0 9px 28px
        rgba(118, 0, 0, .16);

}


.tecnico-hero::after {

    content: "";

    position: absolute;

    width: 300px;
    height: 300px;

    right: -110px;
    top: -150px;

    border-radius: 50%;

    background:
        rgba(255, 255, 255, .06);

}


.hero-content,
.hero-actions {

    position: relative;
    z-index: 2;

}


.tecnico-hero h1 {

    margin: 0 0 7px;

    font-size: 29px;
    font-weight: 800;

}


.tecnico-hero p {

    margin: 0;

    color:
        rgba(255, 255, 255, .80);

}


.tecnico-user {

    display: inline-flex;

    align-items: center;

    gap: 7px;

    margin-top: 13px;

    padding: 6px 11px;

    border-radius: 30px;

    background:
        rgba(255, 255, 255, .13);

    font-size: 11px;
    font-weight: 700;

}


.hero-actions {

    display: flex;

    justify-content: flex-end;

    flex-wrap: wrap;

    gap: 8px;

}


.btn-hero {

    display: inline-flex;

    align-items: center;
    justify-content: center;

    gap: 7px;

    padding: 10px 16px;

    border-radius: 10px;

    text-decoration: none;

    font-weight: 700;

}


.btn-hero-white {

    color: #760000;
    background: #FFFFFF;

}


.btn-hero-white:hover {

    color: #B12626;
    background: #F5F5F5;

}


.btn-hero-outline {

    color: #FFFFFF;

    border:
        1px solid
        rgba(255, 255, 255, .28);

    background:
        rgba(255, 255, 255, .10);

}


.btn-hero-outline:hover {

    color: #FFFFFF;

    background:
        rgba(255, 255, 255, .18);

}


/* ============================================================
   ESTADÍSTICAS
============================================================ */

.stat-card {

    height: 100%;

    padding: 18px;

    border:
        1px solid #ECECEC;

    border-radius: 16px;

    background: #FFFFFF;

    box-shadow:
        0 4px 15px
        rgba(0, 0, 0, .04);

    transition:
        transform .2s ease,
        box-shadow .2s ease;

}


.stat-card:hover {

    transform:
        translateY(-2px);

    box-shadow:
        0 6px 18px
        rgba(0, 0, 0, .07);

}


.stat-icon {

    width: 42px;
    height: 42px;

    display: flex;

    align-items: center;
    justify-content: center;

    margin-bottom: 10px;

    border-radius: 11px;

    font-size: 18px;

}


.stat-number {

    color: #333333;

    font-size: 27px;
    line-height: 1;

    font-weight: 800;

}


.stat-label {

    margin-top: 6px;

    color: #777777;

    font-size: 11px;
    font-weight: 700;

}


.stat-assigned {

    color: #0D6EFD;
    background: #E8F1FF;

}


.stat-process {

    color: #8B6800;
    background: #FFF3CD;

}


.stat-pending {

    color: #B12626;
    background: #FFE5E5;

}


.stat-urgent {

    color: #FFFFFF;
    background: #760000;

}


.stat-done {

    color: #198754;
    background: #E1F4E8;

}


.stat-tools {

    color: #6F42C1;
    background: #F0E9FA;

}


/* ============================================================
   CARDS
============================================================ */

.tech-card {

    height: 100%;

    overflow: hidden;

    border:
        1px solid #ECECEC;

    border-radius: 18px;

    background: #FFFFFF;

    box-shadow:
        0 5px 19px
        rgba(0, 0, 0, .05);

}


.tech-card-header {

    display: flex;

    align-items: center;
    justify-content: space-between;

    gap: 12px;

    padding: 17px 20px;

    border-bottom:
        1px solid #EEEEEE;

}


.tech-card-header h5 {

    margin: 0;

    color: #760000;

    font-size: 16px;
    font-weight: 800;

}


.tech-card-body {

    padding: 20px;

}


.header-link {

    color: #760000;

    text-decoration: none;

    font-size: 11px;
    font-weight: 700;

}


.header-link:hover {

    color: #B12626;

}


/* ============================================================
   SOLICITUDES
============================================================ */

.solicitud-card {

    position: relative;

    height: 100%;

    overflow: hidden;

    border:
        1px solid #ECECEC;

    border-radius: 16px;

    background: #FFFFFF;

    transition: .2s ease;

}


.solicitud-card:hover {

    transform:
        translateY(-2px);

    box-shadow:
        0 7px 20px
        rgba(0, 0, 0, .07);

}


.solicitud-priority {

    position: absolute;

    left: 0;
    top: 0;
    bottom: 0;

    width: 4px;

}


.prioridad-Urgente {

    background: #760000;

}


.prioridad-Alta {

    background: #B12626;

}


.prioridad-Normal {

    background: #D5A500;

}


.prioridad-Baja {

    background: #6C757D;

}


.solicitud-body {

    padding:
        17px 17px
        14px 20px;

}


.ticket-number {

    color: #999999;

    font-size: 10px;
    font-weight: 800;

}


.ticket-title {

    display: inline-block;

    margin-top: 3px;

    color: #333333;

    font-size: 14px;
    font-weight: 800;

    text-decoration: none;

}


.ticket-title:hover {

    color: #B12626;

}


.ticket-description {

    margin-top: 6px;

    color: #747474;

    font-size: 11px;
    line-height: 1.5;

    display: -webkit-box;

    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;

    overflow: hidden;

}


.ticket-meta {

    display: flex;

    flex-wrap: wrap;

    gap:
        5px 12px;

    margin-top: 10px;

    color: #838383;

    font-size: 10px;

}


.ticket-meta i {

    color: #B12626;

}


.ticket-counts {

    display: flex;

    flex-wrap: wrap;

    gap: 9px;

    margin-top: 10px;

}


.ticket-count {

    display: inline-flex;

    align-items: center;

    gap: 4px;

    padding: 5px 7px;

    border-radius: 7px;

    color: #6D6D6D;
    background: #F6F6F6;

    font-size: 9px;

}


.assignment-box {

    margin-top: 10px;

    padding: 8px 10px;

    border-radius: 8px;

    background: #F7F7F7;

    color: #686868;

    font-size: 10px;

}


.pending-box {

    margin-top: 10px;

    padding: 9px 10px;

    border-left:
        3px solid #D6A000;

    border-radius: 7px;

    color: #695500;
    background: #FFF7D9;

    font-size: 10px;

}


.solicitud-footer {

    display: flex;

    align-items: center;
    justify-content: space-between;

    gap: 10px;

    padding:
        12px 16px
        12px 20px;

    border-top:
        1px solid #EEEEEE;

    background: #FAFAFA;

}


.badge-ticket {

    padding: 5px 8px;

    border-radius: 20px;

    font-size: 9px;

}


.ticket-actions {

    display: flex;

    gap: 6px;

}


.btn-ticket {

    min-height: 34px;

    display: inline-flex;

    align-items: center;
    justify-content: center;

    gap: 5px;

    padding: 6px 9px;

    border-radius: 8px;

    text-decoration: none;

    font-size: 10px;
    font-weight: 700;

}


.btn-view {

    color: #760000;
    background: #FFF1F1;

}


.btn-view:hover {

    color: #FFFFFF;
    background: #B12626;

}


.btn-intervenir {

    color: #FFFFFF;

    background:
        linear-gradient(
            135deg,
            #760000,
            #B12626
        );

}


.btn-intervenir:hover {

    color: #FFFFFF;
    background: #760000;

}


.btn-finalizar {

    color: #FFFFFF;
    background: #198754;

}


.btn-finalizar:hover {

    color: #FFFFFF;
    background: #146C43;

}


/* ============================================================
   URGENTES
============================================================ */

.urgente-item {

    padding: 12px;

    margin-bottom: 9px;

    border:
        1px solid #F0D4D4;

    border-left:
        4px solid #B12626;

    border-radius: 10px;

    background: #FFF4F4;

}


.urgente-item:last-child {

    margin-bottom: 0;

}


.urgente-item a {

    color: #760000;

    font-size: 12px;
    font-weight: 800;

    text-decoration: none;

}


.urgente-meta {

    display: flex;

    flex-wrap: wrap;

    gap: 5px 10px;

    margin-top: 5px;

    color: #818181;

    font-size: 10px;

}


/* ============================================================
   PENDIENTES
============================================================ */

.pendiente-item {

    padding: 12px 0;

    border-bottom:
        1px solid #EEEEEE;

}


.pendiente-item:last-child {

    border-bottom: 0;

}


.pendiente-title {

    color: #333333;

    font-size: 12px;
    font-weight: 800;

    text-decoration: none;

}


.pendiente-title:hover {

    color: #B12626;

}


.pendiente-motivo {

    margin-top: 7px;

    padding: 8px;

    border-radius: 7px;

    color: #665400;
    background: #FFF8DF;

    font-size: 10px;

}


/* ============================================================
   INTERVENCIONES
============================================================ */

.intervencion-item {

    position: relative;

    padding:
        0 0 18px
        23px;

    border-left:
        2px solid #EEEEEE;

}


.intervencion-item:last-child {

    padding-bottom: 0;

}


.intervencion-item::before {

    content: "";

    position: absolute;

    width: 10px;
    height: 10px;

    left: -6px;
    top: 3px;

    border-radius: 50%;

    background: #B12626;

}


.intervencion-ticket {

    color: #760000;

    font-size: 10px;
    font-weight: 800;

    text-decoration: none;

}


.intervencion-title {

    margin-top: 2px;

    color: #333333;

    font-size: 12px;
    font-weight: 700;

}


.intervencion-text {

    margin-top: 5px;

    color: #777777;

    font-size: 10px;
    line-height: 1.5;

}


.intervencion-meta {

    display: flex;

    flex-wrap: wrap;

    gap: 5px 10px;

    margin-top: 6px;

    color: #959595;

    font-size: 9px;

}


/* ============================================================
   HORARIOS
============================================================ */

.horario-group-title {

    display: flex;

    align-items: center;

    gap: 8px;

    margin-bottom: 10px;

    color: #760000;

    font-size: 12px;
    font-weight: 800;

}


.horario-item {

    padding: 10px 11px;

    margin-bottom: 7px;

    border:
        1px solid #EEEEEE;

    border-radius: 9px;

    background: #FAFAFA;

}


.horario-main {

    display: flex;

    align-items: center;
    justify-content: space-between;

    gap: 10px;

}


.horario-dia {

    color: #760000;

    font-size: 10px;
    font-weight: 800;

}


.horario-hora {

    color: #555555;

    font-size: 10px;
    font-weight: 700;

}


.horario-extra {

    margin-top: 4px;

    color: #888888;

    font-size: 9px;

}


.horario-hoy {

    border-color: #E0BBBB;

    background: #FFF3F3;

}


/* ============================================================
   VACÍO
============================================================ */

.empty-state {

    padding: 35px 15px;

    text-align: center;

    color: #929292;

    font-size: 11px;

}


.empty-state i {

    display: block;

    margin-bottom: 7px;

    color: #D0D0D0;

    font-size: 38px;

}


/* ============================================================
   RESPONSIVE
============================================================ */

@media (max-width: 767px) {

    .tecnico-hero {

        padding: 22px 20px;

    }


    .tecnico-hero h1 {

        font-size: 23px;

    }


    .hero-actions {

        justify-content: flex-start;

        flex-direction: column;

        margin-top: 18px;

    }


    .btn-hero {

        width: 100%;

    }


    .solicitud-footer {

        align-items: flex-start;

        flex-direction: column;

    }


    .ticket-actions {

        width: 100%;

    }


    .btn-ticket {

        flex: 1;

    }

}

</style>


<div class="tecnico-dashboard">


    <!-- =====================================================
         HERO
    ====================================================== -->

    <section class="tecnico-hero">

        <div class="row align-items-center">


            <div class="col-lg-7">

                <div class="hero-content">


                    <h1>

                        <i class="bi bi-tools me-1"></i>

                        Panel técnico

                    </h1>


                    <p>

                        Consultá tus solicitudes asignadas,
                        registrá intervenciones y gestioná
                        trabajos pendientes.

                    </p>


                    <div class="tecnico-user">

                        <i class="bi bi-person-gear"></i>

                        <?= e(
                            usuarioNombre()
                        ) ?>

                    </div>


                </div>

            </div>


            <div class="col-lg-5">

                <div class="hero-actions">


                    <a
                        href="<?= url(
                            'tecnico/solicitudes.php'
                        ) ?>"
                        class="btn-hero btn-hero-outline"
                    >

                        <i class="bi bi-list-check"></i>

                        Mis solicitudes

                    </a>


                    <a
                        href="<?= url(
                            'dashboard.php'
                        ) ?>"
                        class="btn-hero btn-hero-white"
                    >

                        <i class="bi bi-house"></i>

                        Inicio

                    </a>


                </div>

            </div>


        </div>

    </section>


    <!-- =====================================================
         FLASH
    ====================================================== -->

    <?php if ($flash): ?>

        <div
            class="alert alert-<?=
                $flash['tipo'] === 'success'
                    ? 'success'
                    : (
                        $flash['tipo'] === 'error'
                            ? 'danger'
                            : 'info'
                    )
            ?> alert-dismissible fade show"
        >

            <?= e(
                $flash['mensaje']
            ) ?>


            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
            ></button>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         ESTADÍSTICAS
    ====================================================== -->

    <div class="row g-3 mb-4">


        <div class="col-6 col-md-4 col-xl-2">

            <div class="stat-card">

                <div class="stat-icon stat-assigned">

                    <i class="bi bi-person-check"></i>

                </div>

                <div class="stat-number">

                    <?= $stats[
                        'asignadas'
                    ] ?>

                </div>

                <div class="stat-label">

                    Asignadas

                </div>

            </div>

        </div>


        <div class="col-6 col-md-4 col-xl-2">

            <div class="stat-card">

                <div class="stat-icon stat-process">

                    <i class="bi bi-arrow-repeat"></i>

                </div>

                <div class="stat-number">

                    <?= $stats[
                        'en_proceso'
                    ] ?>

                </div>

                <div class="stat-label">

                    En proceso

                </div>

            </div>

        </div>


        <div class="col-6 col-md-4 col-xl-2">

            <div class="stat-card">

                <div class="stat-icon stat-pending">

                    <i class="bi bi-hourglass-split"></i>

                </div>

                <div class="stat-number">

                    <?= $stats[
                        'pendientes'
                    ] ?>

                </div>

                <div class="stat-label">

                    Pendientes

                </div>

            </div>

        </div>


        <div class="col-6 col-md-4 col-xl-2">

            <div class="stat-card">

                <div class="stat-icon stat-urgent">

                    <i class="bi bi-exclamation-triangle"></i>

                </div>

                <div class="stat-number">

                    <?= $stats[
                        'urgentes'
                    ] ?>

                </div>

                <div class="stat-label">

                    Urgentes

                </div>

            </div>

        </div>


        <div class="col-6 col-md-4 col-xl-2">

            <div class="stat-card">

                <div class="stat-icon stat-done">

                    <i class="bi bi-check-circle"></i>

                </div>

                <div class="stat-number">

                    <?= $stats[
                        'resueltas'
                    ] ?>

                </div>

                <div class="stat-label">

                    Resueltas

                </div>

            </div>

        </div>


        <div class="col-6 col-md-4 col-xl-2">

            <div class="stat-card">

                <div class="stat-icon stat-tools">

                    <i class="bi bi-wrench-adjustable"></i>

                </div>

                <div class="stat-number">

                    <?= $intervencionesMes ?>

                </div>

                <div class="stat-label">

                    Intervenciones este mes

                </div>

            </div>

        </div>


    </div>


    <!-- =====================================================
         SOLICITUDES
    ====================================================== -->

    <div class="tech-card mb-4">


        <div class="tech-card-header">

            <h5>

                <i class="bi bi-ticket-detailed me-2"></i>

                Solicitudes para trabajar

            </h5>


            <a
                href="<?= url(
                    'tecnico/solicitudes.php'
                ) ?>"
                class="header-link"
            >

                Ver todas

                <i class="bi bi-arrow-right"></i>

            </a>

        </div>


        <div class="tech-card-body">


            <?php if (
                empty(
                    $misSolicitudes
                )
            ): ?>


                <div class="empty-state">

                    <i class="bi bi-check-circle"></i>

                    <strong>

                        No tenés solicitudes abiertas asignadas.

                    </strong>


                    <div class="mt-1">

                        Cuando se te asigne un trabajo
                        aparecerá acá.

                    </div>

                </div>


            <?php else: ?>


                <div class="row g-3">


                    <?php foreach (
                        $misSolicitudes
                        as $solicitud
                    ): ?>


                        <div class="col-md-6 col-xl-4">


                            <article class="solicitud-card">


                                <div
                                    class="solicitud-priority prioridad-<?= e(
                                        $solicitud[
                                            'prioridad'
                                        ]
                                    ) ?>"
                                ></div>


                                <div class="solicitud-body">


                                    <div class="ticket-number">

                                        <?= e(
                                            numeroTicket(
                                                (int)$solicitud[
                                                    'id_solicitud'
                                                ]
                                            )
                                        ) ?>

                                    </div>


                                    <a
                                        href="<?= url(
                                            'ver_solicitud.php?id='
                                            .
                                            (int)$solicitud[
                                                'id_solicitud'
                                            ]
                                        ) ?>"
                                        class="ticket-title"
                                    >

                                        <?= e(
                                            $solicitud[
                                                'titulo'
                                            ]
                                        ) ?>

                                    </a>


                                    <div class="ticket-description">

                                        <?= e(
                                            $solicitud[
                                                'descripcion'
                                            ]
                                        ) ?>

                                    </div>


                                    <!-- =================================
                                         INFORMACIÓN
                                    ================================== -->

                                    <div class="ticket-meta">


                                        <span>

                                            <i class="<?= e(
                                                iconoTipo(
                                                    $solicitud[
                                                        'tipo'
                                                    ]
                                                )
                                            ) ?>"></i>

                                            <?= e(
                                                nombreTipo(
                                                    $solicitud[
                                                        'tipo'
                                                    ]
                                                )
                                            ) ?>

                                        </span>


                                        <?php if (
                                            !empty(
                                                $solicitud[
                                                    'sector'
                                                ]
                                            )
                                        ): ?>

                                            <span>

                                                <i class="bi bi-geo-alt"></i>

                                                <?= e(
                                                    $solicitud[
                                                        'sector'
                                                    ]
                                                ) ?>

                                            </span>

                                        <?php endif; ?>


                                        <span>

                                            <i class="bi bi-person"></i>

                                            <?= e(
                                                $solicitud[
                                                    'solicitante'
                                                ]
                                            ) ?>

                                        </span>


                                        <span>

                                            <i class="bi bi-calendar3"></i>

                                            <?= e(
                                                fechaCorta(
                                                    $solicitud[
                                                        'fecha_creacion'
                                                    ]
                                                )
                                            ) ?>

                                        </span>


                                    </div>


                                    <!-- =================================
                                         CONTADORES
                                    ================================== -->

                                    <div class="ticket-counts">


                                        <span class="ticket-count">

                                            <i class="bi bi-images"></i>

                                            <?= (int)$solicitud[
                                                'fotos'
                                            ] ?>

                                            fotos

                                        </span>


                                        <span class="ticket-count">

                                            <i class="bi bi-chat-dots"></i>

                                            <?= (int)$solicitud[
                                                'comentarios'
                                            ] ?>

                                            comentarios

                                        </span>


                                        <span class="ticket-count">

                                            <i class="bi bi-tools"></i>

                                            <?= (int)$solicitud[
                                                'intervenciones'
                                            ] ?>

                                            interv.

                                        </span>


                                    </div>


                                    <!-- =================================
                                         FECHA ASIGNACIÓN
                                    ================================== -->

                                    <div class="assignment-box">

                                        <i class="bi bi-person-check me-1"></i>

                                        <strong>
                                            Asignado:
                                        </strong>

                                        <?= e(
                                            fechaArgentina(
                                                $solicitud[
                                                    'fecha_asignacion'
                                                ]
                                            )
                                        ) ?>

                                    </div>


                                    <!-- =================================
                                         PENDIENTE
                                    ================================== -->

                                    <?php if (
                                        $solicitud[
                                            'estado'
                                        ] === 'Pendiente'

                                        &&
                                        !empty(
                                            $solicitud[
                                                'motivo_pendiente'
                                            ]
                                        )
                                    ): ?>


                                        <div class="pending-box">

                                            <strong>

                                                <i class="bi bi-hourglass-split"></i>

                                                Pendiente:

                                            </strong>


                                            <?= e(
                                                $solicitud[
                                                    'motivo_pendiente'
                                                ]
                                            ) ?>

                                        </div>


                                    <?php endif; ?>


                                </div>


                                <!-- =================================
                                     FOOTER
                                ================================== -->

                                <div class="solicitud-footer">


                                    <div
                                        class="d-flex
                                               flex-wrap
                                               gap-1"
                                    >


                                        <span
                                            class="badge <?= e(
                                                claseEstado(
                                                    $solicitud[
                                                        'estado'
                                                    ]
                                                )
                                            ) ?> badge-ticket"
                                        >

                                            <?= e(
                                                $solicitud[
                                                    'estado'
                                                ]
                                            ) ?>

                                        </span>


                                        <span
                                            class="badge <?= e(
                                                clasePrioridad(
                                                    $solicitud[
                                                        'prioridad'
                                                    ]
                                                )
                                            ) ?> badge-ticket"
                                        >

                                            <?= e(
                                                $solicitud[
                                                    'prioridad'
                                                ]
                                            ) ?>

                                        </span>


                                    </div>


                                    <div class="ticket-actions">


                                        <a
                                            href="<?= url(
                                                'ver_solicitud.php?id='
                                                .
                                                (int)$solicitud[
                                                    'id_solicitud'
                                                ]
                                            ) ?>"
                                            class="btn-ticket btn-view"
                                        >

                                            <i class="bi bi-eye"></i>

                                            Ver

                                        </a>


                                        <?php if (
                                            $solicitud[
                                                'estado'
                                            ] === 'Resuelta'
                                        ): ?>


                                            <a
                                                href="<?= url(
                                                    'tecnico/finalizar.php?id='
                                                    .
                                                    (int)$solicitud[
                                                        'id_solicitud'
                                                    ]
                                                ) ?>"
                                                class="btn-ticket btn-finalizar"
                                            >

                                                <i class="bi bi-check2-circle"></i>

                                                Finalizar

                                            </a>


                                        <?php else: ?>


                                            <a
                                                href="<?= url(
                                                    'tecnico/intervenir.php?id='
                                                    .
                                                    (int)$solicitud[
                                                        'id_solicitud'
                                                    ]
                                                ) ?>"
                                                class="btn-ticket btn-intervenir"
                                            >

                                                <i class="bi bi-tools"></i>

                                                Intervenir

                                            </a>


                                        <?php endif; ?>


                                    </div>


                                </div>


                            </article>


                        </div>


                    <?php endforeach; ?>


                </div>


            <?php endif; ?>


        </div>


    </div>


    <!-- =====================================================
         SEGUNDA FILA
    ====================================================== -->

    <div class="row g-4 mb-4">


        <!-- =================================================
             URGENTES
        ================================================== -->

        <div class="col-lg-6 col-xl-4">


            <div class="tech-card">


                <div class="tech-card-header">

                    <h5>

                        <i class="bi bi-exclamation-triangle me-2"></i>

                        Urgentes

                    </h5>


                    <span class="badge bg-danger">

                        <?= count(
                            $urgentes
                        ) ?>

                    </span>

                </div>


                <div class="tech-card-body">


                    <?php if (
                        empty(
                            $urgentes
                        )
                    ): ?>


                        <div class="empty-state">

                            <i class="bi bi-check-circle"></i>

                            No tenés solicitudes urgentes.

                        </div>


                    <?php else: ?>


                        <?php foreach (
                            $urgentes
                            as $solicitud
                        ): ?>


                            <div class="urgente-item">


                                <a
                                    href="<?= url(
                                        'tecnico/intervenir.php?id='
                                        .
                                        (int)$solicitud[
                                            'id_solicitud'
                                        ]
                                    ) ?>"
                                >

                                    <?= e(
                                        numeroTicket(
                                            (int)$solicitud[
                                                'id_solicitud'
                                            ]
                                        )
                                    ) ?>

                                    -

                                    <?= e(
                                        $solicitud[
                                            'titulo'
                                        ]
                                    ) ?>

                                </a>


                                <div class="urgente-meta">


                                    <?php if (
                                        !empty(
                                            $solicitud[
                                                'sector'
                                            ]
                                        )
                                    ): ?>

                                        <span>

                                            <i class="bi bi-geo-alt"></i>

                                            <?= e(
                                                $solicitud[
                                                    'sector'
                                                ]
                                            ) ?>

                                        </span>

                                    <?php endif; ?>


                                    <span>

                                        <?= e(
                                            nombreTipo(
                                                $solicitud[
                                                    'tipo'
                                                ]
                                            )
                                        ) ?>

                                    </span>


                                    <span>

                                        <?= e(
                                            $solicitud[
                                                'estado'
                                            ]
                                        ) ?>

                                    </span>


                                </div>


                            </div>


                        <?php endforeach; ?>


                    <?php endif; ?>


                </div>


            </div>


        </div>


        <!-- =================================================
             PENDIENTES
        ================================================== -->

        <div class="col-lg-6 col-xl-4">


            <div class="tech-card">


                <div class="tech-card-header">

                    <h5>

                        <i class="bi bi-hourglass-split me-2"></i>

                        Pendientes

                    </h5>


                    <a
                        href="<?= url(
                            'tecnico/solicitudes.php?estado=Pendiente'
                        ) ?>"
                        class="header-link"
                    >

                        Ver todos

                    </a>

                </div>


                <div class="tech-card-body">


                    <?php if (
                        empty(
                            $pendientes
                        )
                    ): ?>


                        <div class="empty-state">

                            <i class="bi bi-check2-circle"></i>

                            No tenés trabajos pendientes.

                        </div>


                    <?php else: ?>


                        <?php foreach (
                            $pendientes
                            as $solicitud
                        ): ?>


                            <div class="pendiente-item">


                                <a
                                    href="<?= url(
                                        'tecnico/intervenir.php?id='
                                        .
                                        (int)$solicitud[
                                            'id_solicitud'
                                        ]
                                    ) ?>"
                                    class="pendiente-title"
                                >

                                    <?= e(
                                        numeroTicket(
                                            (int)$solicitud[
                                                'id_solicitud'
                                            ]
                                        )
                                    ) ?>

                                    -

                                    <?= e(
                                        $solicitud[
                                            'titulo'
                                        ]
                                    ) ?>

                                </a>


                                <div class="ticket-meta">


                                    <?php if (
                                        !empty(
                                            $solicitud[
                                                'sector'
                                            ]
                                        )
                                    ): ?>

                                        <span>

                                            <i class="bi bi-geo-alt"></i>

                                            <?= e(
                                                $solicitud[
                                                    'sector'
                                                ]
                                            ) ?>

                                        </span>

                                    <?php endif; ?>


                                    <span>

                                        <?= e(
                                            $solicitud[
                                                'prioridad'
                                            ]
                                        ) ?>

                                    </span>


                                </div>


                                <?php if (
                                    !empty(
                                        $solicitud[
                                            'motivo_pendiente'
                                        ]
                                    )
                                ): ?>


                                    <div class="pendiente-motivo">

                                        <?= e(
                                            $solicitud[
                                                'motivo_pendiente'
                                            ]
                                        ) ?>

                                    </div>


                                <?php endif; ?>


                            </div>


                        <?php endforeach; ?>


                    <?php endif; ?>


                </div>


            </div>


        </div>


        <!-- =================================================
             ACTIVIDAD
        ================================================== -->

        <div class="col-xl-4">


            <div class="tech-card">


                <div class="tech-card-header">

                    <h5>

                        <i class="bi bi-activity me-2"></i>

                        Mi actividad

                    </h5>

                </div>


                <div class="tech-card-body">


                    <div class="row g-3">


                        <div class="col-6">

                            <div class="p-3 rounded bg-light">

                                <div
                                    class="text-muted"
                                    style="font-size:10px;"
                                >

                                    Intervenciones totales

                                </div>

                                <div
                                    class="fw-bold mt-1"
                                    style="
                                        font-size:25px;
                                        color:#760000;
                                    "
                                >

                                    <?= $totalIntervenciones ?>

                                </div>

                            </div>

                        </div>


                        <div class="col-6">

                            <div class="p-3 rounded bg-light">

                                <div
                                    class="text-muted"
                                    style="font-size:10px;"
                                >

                                    Este mes

                                </div>

                                <div
                                    class="fw-bold mt-1"
                                    style="
                                        font-size:25px;
                                        color:#B12626;
                                    "
                                >

                                    <?= $intervencionesMes ?>

                                </div>

                            </div>

                        </div>


                        <div class="col-6">

                            <div class="p-3 rounded bg-light">

                                <div
                                    class="text-muted"
                                    style="font-size:10px;"
                                >

                                    Trabajos activos

                                </div>

                                <div
                                    class="fw-bold mt-1"
                                    style="
                                        font-size:25px;
                                        color:#333333;
                                    "
                                >

                                    <?= $stats['asignadas']
                                        +
                                        $stats['en_proceso']
                                        +
                                        $stats['pendientes']
                                    ?>

                                </div>

                            </div>

                        </div>


                        <div class="col-6">

                            <div class="p-3 rounded bg-light">

                                <div
                                    class="text-muted"
                                    style="font-size:10px;"
                                >

                                    Resueltas

                                </div>

                                <div
                                    class="fw-bold mt-1"
                                    style="
                                        font-size:25px;
                                        color:#198754;
                                    "
                                >

                                    <?= $stats[
                                        'resueltas'
                                    ] ?>

                                </div>

                            </div>

                        </div>


                    </div>


                    <a
                        href="<?= url(
                            'tecnico/intervenciones.php'
                        ) ?>"
                        class="btn btn-sanjo w-100 mt-3"
                    >

                        <i class="bi bi-clock-history me-1"></i>

                        Ver mis intervenciones

                    </a>


                </div>


            </div>


        </div>


    </div>


    <!-- =====================================================
         ÚLTIMAS INTERVENCIONES + HORARIOS
    ====================================================== -->

    <div class="row g-4">


        <!-- =================================================
             INTERVENCIONES
        ================================================== -->

        <div class="col-xl-8">


            <div class="tech-card">


                <div class="tech-card-header">

                    <h5>

                        <i class="bi bi-clock-history me-2"></i>

                        Últimas intervenciones

                    </h5>


                    <a
                        href="<?= url(
                            'tecnico/intervenciones.php'
                        ) ?>"
                        class="header-link"
                    >

                        Historial

                        <i class="bi bi-arrow-right"></i>

                    </a>

                </div>


                <div class="tech-card-body">


                    <?php if (
                        empty(
                            $ultimasIntervenciones
                        )
                    ): ?>


                        <div class="empty-state">

                            <i class="bi bi-tools"></i>

                            Todavía no registraste intervenciones.

                        </div>


                    <?php else: ?>


                        <?php foreach (
                            $ultimasIntervenciones
                            as $intervencion
                        ): ?>


                            <div class="intervencion-item">


                                <a
                                    href="<?= url(
                                        'ver_solicitud.php?id='
                                        .
                                        (int)$intervencion[
                                            'id_solicitud'
                                        ]
                                    ) ?>"
                                    class="intervencion-ticket"
                                >

                                    <?= e(
                                        numeroTicket(
                                            (int)$intervencion[
                                                'id_solicitud'
                                            ]
                                        )
                                    ) ?>

                                </a>


                                <div class="intervencion-title">

                                    <?= e(
                                        $intervencion[
                                            'titulo'
                                        ]
                                    ) ?>

                                </div>


                                <?php if (
                                    !empty(
                                        $intervencion[
                                            'trabajo_realizado'
                                        ]
                                    )
                                ): ?>


                                    <div class="intervencion-text">

                                        <?= e(
                                            $intervencion[
                                                'trabajo_realizado'
                                            ]
                                        ) ?>

                                    </div>


                                <?php elseif (
                                    !empty(
                                        $intervencion[
                                            'diagnostico'
                                        ]
                                    )
                                ): ?>


                                    <div class="intervencion-text">

                                        <?= e(
                                            $intervencion[
                                                'diagnostico'
                                            ]
                                        ) ?>

                                    </div>


                                <?php endif; ?>


                                <div class="intervencion-meta">


                                    <span>

                                        <i class="bi bi-calendar3"></i>

                                        <?= e(
                                            fechaArgentina(
                                                $intervencion[
                                                    'fecha_intervencion'
                                                ]
                                            )
                                        ) ?>

                                    </span>


                                    <?php if (
                                        !empty(
                                            $intervencion[
                                                'sector'
                                            ]
                                        )
                                    ): ?>

                                        <span>

                                            <i class="bi bi-geo-alt"></i>

                                            <?= e(
                                                $intervencion[
                                                    'sector'
                                                ]
                                            ) ?>

                                        </span>

                                    <?php endif; ?>


                                    <?php if (
                                        (int)$intervencion[
                                            'imagenes'
                                        ] > 0
                                    ): ?>

                                        <span>

                                            <i class="bi bi-images"></i>

                                            <?= (int)$intervencion[
                                                'imagenes'
                                            ] ?>

                                            fotos

                                        </span>

                                    <?php endif; ?>


                                    <?php if (
                                        (int)$intervencion[
                                            'pendiente'
                                        ] === 1
                                    ): ?>

                                        <span class="text-warning">

                                            <i class="bi bi-hourglass-split"></i>

                                            Pendiente

                                        </span>

                                    <?php endif; ?>


                                </div>


                            </div>


                        <?php endforeach; ?>


                    <?php endif; ?>


                </div>


            </div>


        </div>


        <!-- =================================================
             HORARIOS
        ================================================== -->

        <div class="col-xl-4">


            <div class="tech-card">


                <div class="tech-card-header">

                    <h5>

                        <i class="bi bi-calendar-week me-2"></i>

                        Horarios

                    </h5>


                    <a
                        href="<?= url(
                            'horarios.php'
                        ) ?>"
                        class="header-link"
                    >

                        Ver todos

                    </a>

                </div>


                <div class="tech-card-body">


                    <!-- =====================================
                         INFORMÁTICA
                    ====================================== -->

                    <div class="horario-group-title">

                        <i class="bi bi-pc-display"></i>

                        Informática

                    </div>


                    <?php if (
                        empty(
                            $horariosInformatica
                        )
                    ): ?>


                        <div class="small text-muted mb-3">

                            Sin horarios publicados.

                        </div>


                    <?php else: ?>


                        <?php foreach (
                            $horariosInformatica
                            as $horario
                        ): ?>


                            <div
                                class="horario-item <?= $horario[
                                    'dia'
                                ] === $diaActual
                                    ? 'horario-hoy'
                                    : ''
                                ?>"
                            >


                                <div class="horario-main">


                                    <span class="horario-dia">

                                        <?= e(
                                            mostrarDiaTecnico(
                                                $horario[
                                                    'dia'
                                                ]
                                            )
                                        ) ?>


                                        <?php if (
                                            $horario[
                                                'dia'
                                            ] === $diaActual
                                        ): ?>

                                            · Hoy

                                        <?php endif; ?>

                                    </span>


                                    <span class="horario-hora">

                                        <?= e(
                                            mostrarHoraTecnico(
                                                $horario[
                                                    'hora_desde'
                                                ]
                                            )
                                        ) ?>

                                        -

                                        <?= e(
                                            mostrarHoraTecnico(
                                                $horario[
                                                    'hora_hasta'
                                                ]
                                            )
                                        ) ?>

                                    </span>


                                </div>


                                <?php if (
                                    !empty(
                                        $horario[
                                            'responsable'
                                        ]
                                    )
                                ): ?>

                                    <div class="horario-extra">

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

                                    <div class="horario-extra">

                                        <?= e(
                                            $horario[
                                                'observaciones'
                                            ]
                                        ) ?>

                                    </div>

                                <?php endif; ?>


                            </div>


                        <?php endforeach; ?>


                    <?php endif; ?>


                    <hr>


                    <!-- =====================================
                         MANTENIMIENTO
                    ====================================== -->

                    <div class="horario-group-title">

                        <i class="bi bi-hammer"></i>

                        Mantenimiento general

                    </div>


                    <?php if (
                        empty(
                            $horariosMantenimiento
                        )
                    ): ?>


                        <div class="small text-muted">

                            Sin horarios publicados.

                        </div>


                    <?php else: ?>


                        <?php foreach (
                            $horariosMantenimiento
                            as $horario
                        ): ?>


                            <div
                                class="horario-item <?= $horario[
                                    'dia'
                                ] === $diaActual
                                    ? 'horario-hoy'
                                    : ''
                                ?>"
                            >


                                <div class="horario-main">


                                    <span class="horario-dia">

                                        <?= e(
                                            mostrarDiaTecnico(
                                                $horario[
                                                    'dia'
                                                ]
                                            )
                                        ) ?>


                                        <?php if (
                                            $horario[
                                                'dia'
                                            ] === $diaActual
                                        ): ?>

                                            · Hoy

                                        <?php endif; ?>

                                    </span>


                                    <span class="horario-hora">

                                        <?= e(
                                            mostrarHoraTecnico(
                                                $horario[
                                                    'hora_desde'
                                                ]
                                            )
                                        ) ?>

                                        -

                                        <?= e(
                                            mostrarHoraTecnico(
                                                $horario[
                                                    'hora_hasta'
                                                ]
                                            )
                                        ) ?>

                                    </span>


                                </div>


                                <?php if (
                                    !empty(
                                        $horario[
                                            'responsable'
                                        ]
                                    )
                                ): ?>

                                    <div class="horario-extra">

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

                                    <div class="horario-extra">

                                        <?= e(
                                            $horario[
                                                'observaciones'
                                            ]
                                        ) ?>

                                    </div>

                                <?php endif; ?>


                            </div>


                        <?php endforeach; ?>


                    <?php endif; ?>


                </div>


            </div>


        </div>


    </div>


</div>


<?php

require_once __DIR__ . '/../includes/footer.php';

?>