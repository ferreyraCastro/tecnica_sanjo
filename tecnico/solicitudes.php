<?php
// ============================================================
// COLEGIO SAN JOSÉ
// Sistema de Gestión Técnica
// Archivo: /tecnica/tecnico/solicitudes.php
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/funciones.php';
require_once __DIR__ . '/../includes/auth.php';


// ============================================================
// SOLO TÉCNICOS / ADMINISTRADORES
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
// USUARIO ACTUAL
// ============================================================

$idTecnico =
    (int)usuarioId();

$rolActual =
    $_SESSION['usuario_rol']
    ?? '';


// ============================================================
// CONFIGURACIÓN
// ============================================================

$estadosPermitidos = [
    'Nueva',
    'Asignada',
    'En proceso',
    'Pendiente',
    'Resuelta',
    'Cerrada',
    'Cancelada'
];


$prioridadesPermitidas = [
    'Baja',
    'Normal',
    'Alta',
    'Urgente'
];


$tiposPermitidos = [
    'Informatica',
    'Mantenimiento'
];


// ============================================================
// FILTROS
// ============================================================

$buscar =
    trim(
        $_GET['buscar']
        ?? ''
    );


$estadoFiltro =
    trim(
        $_GET['estado']
        ?? ''
    );


$prioridadFiltro =
    trim(
        $_GET['prioridad']
        ?? ''
    );


$tipoFiltro =
    trim(
        $_GET['tipo']
        ?? ''
    );


$asignacionFiltro =
    trim(
        $_GET['asignacion']
        ?? 'actuales'
    );


// ============================================================
// VALIDAR FILTROS
// ============================================================

if (
    $estadoFiltro !== ''
    &&
    !in_array(
        $estadoFiltro,
        $estadosPermitidos,
        true
    )
) {

    $estadoFiltro = '';
}


if (
    $prioridadFiltro !== ''
    &&
    !in_array(
        $prioridadFiltro,
        $prioridadesPermitidas,
        true
    )
) {

    $prioridadFiltro = '';
}


if (
    $tipoFiltro !== ''
    &&
    !in_array(
        $tipoFiltro,
        $tiposPermitidos,
        true
    )
) {

    $tipoFiltro = '';
}


if (
    !in_array(
        $asignacionFiltro,
        [
            'actuales',
            'historial',
            'todas'
        ],
        true
    )
) {

    $asignacionFiltro =
        'actuales';
}


// ============================================================
// CONSTRUIR CONDICIONES SQL
// ============================================================

$condiciones = [

    'sa.id_tecnico = ?'

];


$parametros = [

    $idTecnico

];


// ============================================================
// FILTRO DE ASIGNACIÓN
// ============================================================

if (
    $asignacionFiltro ===
    'actuales'
) {

    $condiciones[] =
        'sa.activo = 1';

} elseif (
    $asignacionFiltro ===
    'historial'
) {

    $condiciones[] =
        'sa.activo = 0';
}


// ============================================================
// BÚSQUEDA
// ============================================================

if ($buscar !== '') {

    $condiciones[] = "
        (
            s.titulo LIKE ?
            OR
            s.descripcion LIKE ?
            OR
            sec.nombre LIKE ?
            OR
            cat.nombre LIKE ?
            OR
            u.nombre LIKE ?
            OR
            u.apellido LIKE ?
            OR
            u.correo LIKE ?
            OR
            CAST(
                s.id_solicitud
                AS CHAR
            ) LIKE ?
        )
    ";


    $buscarSQL =
        '%'
        .
        $buscar
        .
        '%';


    for (
        $i = 0;
        $i < 8;
        $i++
    ) {

        $parametros[] =
            $buscarSQL;
    }
}


// ============================================================
// ESTADO
// ============================================================

if ($estadoFiltro !== '') {

    $condiciones[] =
        's.estado = ?';

    $parametros[] =
        $estadoFiltro;
}


// ============================================================
// PRIORIDAD
// ============================================================

if ($prioridadFiltro !== '') {

    $condiciones[] =
        's.prioridad = ?';

    $parametros[] =
        $prioridadFiltro;
}


// ============================================================
// TIPO
// ============================================================

if ($tipoFiltro !== '') {

    $condiciones[] =
        's.tipo = ?';

    $parametros[] =
        $tipoFiltro;
}


// ============================================================
// WHERE
// ============================================================

$where =
    'WHERE '
    .
    implode(
        ' AND ',
        $condiciones
    );


// ============================================================
// ESTADÍSTICAS GENERALES DEL TÉCNICO
//
// Se calculan solamente sobre asignaciones actuales.
// ============================================================

$stmtStats =
    $conexion->prepare("
        SELECT

            COUNT(
                DISTINCT s.id_solicitud
            ) AS total,

            COUNT(
                DISTINCT
                CASE

                    WHEN s.estado = 'Asignada'
                    THEN s.id_solicitud

                END
            ) AS asignadas,

            COUNT(
                DISTINCT
                CASE

                    WHEN s.estado = 'En proceso'
                    THEN s.id_solicitud

                END
            ) AS proceso,

            COUNT(
                DISTINCT
                CASE

                    WHEN s.estado = 'Pendiente'
                    THEN s.id_solicitud

                END
            ) AS pendientes,

            COUNT(
                DISTINCT
                CASE

                    WHEN s.estado = 'Resuelta'
                    THEN s.id_solicitud

                END
            ) AS resueltas,

            COUNT(
                DISTINCT
                CASE

                    WHEN
                        s.prioridad = 'Urgente'

                    AND
                        s.estado NOT IN
                        (
                            'Resuelta',
                            'Cerrada',
                            'Cancelada'
                        )

                    THEN s.id_solicitud

                END
            ) AS urgentes

        FROM solicitudes s

        INNER JOIN solicitudes_asignaciones sa

            ON sa.id_solicitud =
               s.id_solicitud

        WHERE
            sa.id_tecnico = ?

        AND
            sa.activo = 1
    ");


$stmtStats->execute([
    $idTecnico
]);


$stats =
    $stmtStats->fetch(
        PDO::FETCH_ASSOC
    );


$stats = [

    'total' =>
        (int)(
            $stats['total']
            ?? 0
        ),

    'asignadas' =>
        (int)(
            $stats['asignadas']
            ?? 0
        ),

    'proceso' =>
        (int)(
            $stats['proceso']
            ?? 0
        ),

    'pendientes' =>
        (int)(
            $stats['pendientes']
            ?? 0
        ),

    'resueltas' =>
        (int)(
            $stats['resueltas']
            ?? 0
        ),

    'urgentes' =>
        (int)(
            $stats['urgentes']
            ?? 0
        )

];


// ============================================================
// PAGINACIÓN
// ============================================================

$pagina =
    max(
        1,
        (int)(
            $_GET['pagina']
            ?? 1
        )
    );


$porPagina =
    15;


// ============================================================
// CONTAR RESULTADOS
//
// DISTINCT evita repetir un ticket si hubo más de una
// asignación histórica al mismo técnico.
// ============================================================

$sqlCantidad = "
    SELECT

        COUNT(
            DISTINCT s.id_solicitud
        )

    FROM solicitudes s

    INNER JOIN solicitudes_asignaciones sa

        ON sa.id_solicitud =
           s.id_solicitud

    INNER JOIN usuarios u

        ON s.id_usuario =
           u.id_usuario

    LEFT JOIN sectores sec

        ON s.id_sector =
           sec.id_sector

    LEFT JOIN categorias cat

        ON s.id_categoria =
           cat.id_categoria

    {$where}
";


$stmtCantidad =
    $conexion->prepare(
        $sqlCantidad
    );


$stmtCantidad->execute(
    $parametros
);


$totalRegistros =
    (int)$stmtCantidad
        ->fetchColumn();


$totalPaginas =
    max(
        1,
        (int)ceil(
            $totalRegistros
            /
            $porPagina
        )
    );


if (
    $pagina >
    $totalPaginas
) {

    $pagina =
        $totalPaginas;
}


$offset =
    ($pagina - 1)
    *
    $porPagina;


// ============================================================
// CONSULTA PRINCIPAL
//
// Usamos una subconsulta para obtener la última asignación
// que tuvo este técnico sobre cada solicitud.
// ============================================================

$sqlSolicitudes = "
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

        u.correo
            AS correo_solicitante,

        sec.nombre
            AS sector,

        cat.nombre
            AS categoria,

        sa.id_asignacion,

        sa.activo
            AS asignacion_activa,

        sa.fecha_asignacion,

        sa.fecha_fin,

        sa.observaciones
            AS observaciones_asignacion,

        (
            SELECT COUNT(*)

            FROM solicitud_imagenes si

            WHERE
                si.id_solicitud =
                s.id_solicitud

        ) AS cantidad_imagenes,

        (
            SELECT COUNT(*)

            FROM comentarios c

            WHERE
                c.id_solicitud =
                s.id_solicitud

        ) AS cantidad_comentarios,

        (
            SELECT COUNT(*)

            FROM intervenciones i

            WHERE
                i.id_solicitud =
                s.id_solicitud

        ) AS cantidad_intervenciones,

        (
            SELECT MAX(
                i2.fecha_intervencion
            )

            FROM intervenciones i2

            WHERE
                i2.id_solicitud =
                s.id_solicitud

        ) AS ultima_intervencion,

        (
            SELECT COUNT(*)

            FROM intervenciones i3

            WHERE
                i3.id_solicitud =
                s.id_solicitud

            AND
                i3.id_tecnico = ?

        ) AS mis_intervenciones

    FROM solicitudes s

    INNER JOIN solicitudes_asignaciones sa

        ON sa.id_solicitud =
           s.id_solicitud

    INNER JOIN usuarios u

        ON s.id_usuario =
           u.id_usuario

    LEFT JOIN sectores sec

        ON s.id_sector =
           sec.id_sector

    LEFT JOIN categorias cat

        ON s.id_categoria =
           cat.id_categoria

    {$where}

    AND
        sa.id_asignacion =
        (
            SELECT
                MAX(sa2.id_asignacion)

            FROM solicitudes_asignaciones sa2

            WHERE
                sa2.id_solicitud =
                s.id_solicitud

            AND
                sa2.id_tecnico =
                sa.id_tecnico

            " .
            (
                $asignacionFiltro === 'actuales'
                    ? "AND sa2.activo = 1"
                    : (
                        $asignacionFiltro === 'historial'
                            ? "AND sa2.activo = 0"
                            : ""
                    )
            )
            . "
        )

    ORDER BY

        CASE

            WHEN
                s.prioridad = 'Urgente'

            AND
                s.estado NOT IN
                (
                    'Resuelta',
                    'Cerrada',
                    'Cancelada'
                )

            THEN 1

            ELSE 2

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

            WHEN 'Cerrada'
                THEN 6

            WHEN 'Cancelada'
                THEN 7

            ELSE 8

        END,

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

        COALESCE(
            s.fecha_actualizacion,
            s.fecha_creacion
        ) DESC

    LIMIT {$porPagina}

    OFFSET {$offset}
";


// ============================================================
// PRIMER PARÁMETRO EXTRA
//
// La consulta utiliza primero id_tecnico en
// "mis_intervenciones", antes de los parámetros del WHERE.
// ============================================================

$parametrosConsulta = [

    $idTecnico,
    ...$parametros

];


$stmtSolicitudes =
    $conexion->prepare(
        $sqlSolicitudes
    );


$stmtSolicitudes->execute(
    $parametrosConsulta
);


$solicitudes =
    $stmtSolicitudes->fetchAll(
        PDO::FETCH_ASSOC
    );


// ============================================================
// URL PARA PAGINACIÓN
// ============================================================

function urlPaginaTecnicoSolicitudes(
    int $pagina
): string {

    $query =
        $_GET;


    $query['pagina'] =
        $pagina;


    return url(
        'tecnico/solicitudes.php?'
        .
        http_build_query(
            $query
        )
    );
}


// ============================================================
// DETERMINAR SI SE PUEDE INTERVENIR
// ============================================================

function puedeIntervenirSolicitudTecnico(
    array $solicitud,
    bool $esAdministrador
): bool {

    if (
        in_array(
            $solicitud['estado'],
            [
                'Cerrada',
                'Cancelada'
            ],
            true
        )
    ) {

        return false;
    }


    if ($esAdministrador) {

        return true;
    }


    return
        (int)$solicitud[
            'asignacion_activa'
        ] === 1;
}


// ============================================================
// TEXTO DEL ÁREA
// ============================================================

function nombreAreaTecnico(
    string $tipo
): string {

    return match ($tipo) {

        'Informatica'
            => 'Informática',

        'Mantenimiento'
            => 'Mantenimiento',

        default
            => $tipo

    };
}


// ============================================================
// FLASH
// ============================================================

$flash =
    obtenerFlash();


// ============================================================
// HEADER
// ============================================================

require_once __DIR__
    . '/../includes/header.php';

?>


<style>

/* ============================================================
   CONTENEDOR
============================================================ */

.tecnico-solicitudes-wrapper {

    max-width: 1550px;

    margin: 0 auto;

    padding:
        5px 12px
        50px;

}


/* ============================================================
   HERO
============================================================ */

.tecnico-solicitudes-hero {

    position: relative;

    overflow: hidden;

    margin-bottom: 23px;

    padding: 30px;

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
        rgba(118,0,0,.16);

}


.tecnico-solicitudes-hero::after {

    content: "";

    position: absolute;

    width: 300px;

    height: 300px;

    right: -110px;

    top: -150px;

    border-radius: 50%;

    background:
        rgba(255,255,255,.06);

}


.hero-content {

    position: relative;

    z-index: 2;

}


.tecnico-solicitudes-hero h1 {

    margin:
        0 0 7px;

    font-size: 29px;

    font-weight: 800;

}


.tecnico-solicitudes-hero p {

    margin: 0;

    max-width: 780px;

    color:
        rgba(255,255,255,.80);

}


.hero-user {

    display: inline-flex;

    align-items: center;

    gap: 7px;

    margin-top: 12px;

    padding:
        6px 11px;

    border-radius: 30px;

    background:
        rgba(255,255,255,.13);

    font-size: 11px;

    font-weight: 700;

}


.hero-actions {

    position: relative;

    z-index: 2;

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

    padding:
        10px 16px;

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
        rgba(255,255,255,.28);

    background:
        rgba(255,255,255,.10);

}


.btn-hero-outline:hover {

    color: #FFFFFF;

    background:
        rgba(255,255,255,.18);

}


/* ============================================================
   ESTADÍSTICAS
============================================================ */

.stat-card {

    height: 100%;

    padding: 17px;

    border:
        1px solid #ECECEC;

    border-radius: 16px;

    background: #FFFFFF;

    box-shadow:
        0 4px 15px
        rgba(0,0,0,.04);

}


.stat-icon {

    width: 42px;

    height: 42px;

    display: flex;

    align-items: center;

    justify-content: center;

    margin-bottom: 9px;

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


.stat-total {

    color: #760000;

    background: #F3E6E6;

}


.stat-assigned {

    color: #0D6EFD;

    background: #E8F1FF;

}


.stat-process {

    color: #805F00;

    background: #FFF3CD;

}


.stat-pending {

    color: #B12626;

    background: #FFE8E8;

}


.stat-done {

    color: #198754;

    background: #E1F4E8;

}


.stat-urgent {

    color: #FFFFFF;

    background: #760000;

}


/* ============================================================
   FILTROS
============================================================ */

.filters-card {

    margin-bottom: 23px;

    padding: 20px;

    border:
        1px solid #ECECEC;

    border-radius: 17px;

    background: #FFFFFF;

    box-shadow:
        0 5px 17px
        rgba(0,0,0,.04);

}


.form-label {

    color: #555555;

    font-size: 11px;

    font-weight: 800;

}


.form-control,
.form-select {

    min-height: 44px;

    border-radius: 9px;

}


.form-control:focus,
.form-select:focus {

    border-color: #B12626;

    box-shadow:
        0 0 0
        .2rem
        rgba(177,38,38,.08);

}


.btn-filtrar {

    min-height: 44px;

    border: 0;

    border-radius: 9px;

    color: #FFFFFF;

    background: #B12626;

    font-weight: 700;

}


.btn-filtrar:hover {

    color: #FFFFFF;

    background: #760000;

}


.btn-limpiar {

    min-height: 44px;

    border:
        1px solid #DDDDDD;

    border-radius: 9px;

    color: #555555;

    background: #FFFFFF;

}


/* ============================================================
   CARD GENERAL
============================================================ */

.tech-card {

    overflow: hidden;

    border:
        1px solid #ECECEC;

    border-radius: 18px;

    background: #FFFFFF;

    box-shadow:
        0 5px 19px
        rgba(0,0,0,.05);

}


.tech-card-header {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 12px;

    padding:
        18px 20px;

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


/* ============================================================
   SOLICITUD
============================================================ */

.solicitud-item {

    position: relative;

    display: grid;

    grid-template-columns:
        minmax(0,1fr)
        auto;

    gap: 22px;

    padding:
        20px 0;

    border-bottom:
        1px solid #EEEEEE;

}


.solicitud-item:first-child {

    padding-top: 0;

}


.solicitud-item:last-child {

    padding-bottom: 0;

    border-bottom: 0;

}


.solicitud-item::before {

    content: "";

    position: absolute;

    left: -20px;

    top: 20px;

    bottom: 20px;

    width: 4px;

    border-radius: 0 5px 5px 0;

    background: #CCCCCC;

}


.solicitud-item.prioridad-Urgente::before {

    background: #760000;

}


.solicitud-item.prioridad-Alta::before {

    background: #B12626;

}


.solicitud-item.prioridad-Normal::before {

    background: #D4A100;

}


.solicitud-item.prioridad-Baja::before {

    background: #6C757D;

}


/* ============================================================
   TICKET
============================================================ */

.ticket-top {

    display: flex;

    flex-wrap: wrap;

    align-items: center;

    gap: 7px;

    margin-bottom: 4px;

}


.ticket-numero {

    color: #8C8C8C;

    font-size: 10px;

    font-weight: 800;

}


.asignacion-actual {

    display: inline-flex;

    align-items: center;

    gap: 4px;

    padding:
        4px 7px;

    border-radius: 20px;

    color: #198754;

    background: #E7F5EC;

    font-size: 8px;

    font-weight: 800;

}


.asignacion-finalizada {

    display: inline-flex;

    align-items: center;

    gap: 4px;

    padding:
        4px 7px;

    border-radius: 20px;

    color: #6C757D;

    background: #EEEEEE;

    font-size: 8px;

    font-weight: 800;

}


.ticket-title {

    display: inline-block;

    color: #333333;

    font-size: 15px;

    font-weight: 800;

    text-decoration: none;

}


.ticket-title:hover {

    color: #B12626;

}


.ticket-description {

    max-width: 950px;

    margin-top: 6px;

    color: #707070;

    font-size: 11px;

    line-height: 1.55;

    display: -webkit-box;

    -webkit-line-clamp: 2;

    -webkit-box-orient: vertical;

    overflow: hidden;

}


/* ============================================================
   META
============================================================ */

.ticket-meta {

    display: flex;

    flex-wrap: wrap;

    gap:
        6px 14px;

    margin-top: 10px;

    color: #808080;

    font-size: 10px;

}


.ticket-meta span {

    display: inline-flex;

    align-items: center;

    gap: 4px;

}


.ticket-meta i {

    color: #B12626;

}


.ticket-stats {

    display: flex;

    flex-wrap: wrap;

    gap: 8px;

    margin-top: 10px;

}


.ticket-stat {

    display: inline-flex;

    align-items: center;

    gap: 4px;

    padding:
        5px 8px;

    border-radius: 7px;

    color: #666666;

    background: #F5F5F5;

    font-size: 9px;

}


.ticket-stat strong {

    color: #333333;

}


/* ============================================================
   ASIGNACIÓN
============================================================ */

.asignacion-nota {

    margin-top: 10px;

    max-width: 900px;

    padding:
        8px 10px;

    border-radius: 8px;

    color: #646464;

    background: #F8F8F8;

    font-size: 10px;

}


.asignacion-fecha {

    margin-top: 7px;

    color: #979797;

    font-size: 9px;

}


/* ============================================================
   PENDIENTE
============================================================ */

.pendiente-box {

    max-width: 900px;

    margin-top: 10px;

    padding:
        9px 11px;

    border-left:
        3px solid #D4A000;

    border-radius: 7px;

    color: #6D5800;

    background: #FFF8DE;

    font-size: 10px;

}


/* ============================================================
   ACCIONES
============================================================ */

.ticket-side {

    min-width: 165px;

    display: flex;

    flex-direction: column;

    align-items: flex-end;

}


.badges {

    display: flex;

    justify-content: flex-end;

    flex-wrap: wrap;

    gap: 5px;

}


.badge-ticket {

    padding:
        6px 9px;

    border-radius: 20px;

    font-size: 9px;

    font-weight: 700;

}


.ticket-actions {

    display: flex;

    justify-content: flex-end;

    flex-wrap: wrap;

    gap: 6px;

    margin-top: 15px;

}


.btn-ticket {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    gap: 5px;

    min-height: 37px;

    padding:
        7px 10px;

    border-radius: 8px;

    text-decoration: none;

    font-size: 10px;

    font-weight: 700;

}


.btn-ver {

    color: #760000;

    background: #FFF2F2;

    border:
        1px solid #F0D7D7;

}


.btn-ver:hover {

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


.btn-intervenir-disabled {

    color: #888888;

    background: #EEEEEE;

    cursor: not-allowed;

}


/* ============================================================
   VACÍO
============================================================ */

.empty-state {

    padding:
        50px 20px;

    text-align: center;

    color: #909090;

}


.empty-state i {

    display: block;

    margin-bottom: 10px;

    color: #D0D0D0;

    font-size: 45px;

}


.empty-state strong {

    display: block;

    color: #555555;

    font-size: 14px;

}


/* ============================================================
   PAGINACIÓN
============================================================ */

.page-link {

    color: #760000;

}


.page-link:hover {

    color: #B12626;

}


.page-item.active
.page-link {

    color: #FFFFFF;

    background: #B12626;

    border-color: #B12626;

}


/* ============================================================
   RESPONSIVE
============================================================ */

@media
(max-width: 991px) {

    .solicitud-item {

        grid-template-columns: 1fr;

        gap: 13px;

    }


    .ticket-side {

        min-width: 0;

        align-items: flex-start;

    }


    .badges,
    .ticket-actions {

        justify-content: flex-start;

    }

}


@media
(max-width: 767px) {

    .tecnico-solicitudes-hero {

        padding:
            22px 20px;

    }


    .tecnico-solicitudes-hero h1 {

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


    .tech-card-body {

        padding:
            17px;

    }


    .solicitud-item::before {

        left: -17px;

    }


    .ticket-actions {

        width: 100%;

    }


    .btn-ticket {

        flex: 1;

    }

}

</style>


<div class="tecnico-solicitudes-wrapper">


    <!-- =====================================================
         HERO
    ====================================================== -->

    <section class="tecnico-solicitudes-hero">

        <div class="row align-items-center">


            <div class="col-lg-7">

                <div class="hero-content">

                    <h1>

                        <i class="bi bi-list-check me-1"></i>

                        Mis solicitudes

                    </h1>


                    <p>

                        Consultá tus trabajos asignados,
                        pendientes, intervenciones en proceso
                        y solicitudes ya resueltas.

                    </p>


                    <div class="hero-user">

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
                            'tecnico/intervenciones.php'
                        ) ?>"
                        class="btn-hero btn-hero-outline"
                    >

                        <i class="bi bi-clock-history"></i>

                        Mis intervenciones

                    </a>


                    <a
                        href="<?= url(
                            'tecnico/dashboard.php'
                        ) ?>"
                        class="btn-hero btn-hero-white"
                    >

                        <i class="bi bi-arrow-left"></i>

                        Panel técnico

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

                <div class="stat-icon stat-total">

                    <i class="bi bi-ticket-detailed"></i>

                </div>


                <div class="stat-number">

                    <?= $stats[
                        'total'
                    ] ?>

                </div>


                <div class="stat-label">
                    Asignaciones actuales
                </div>

            </div>

        </div>


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
                        'proceso'
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

                <div class="stat-icon stat-urgent">

                    <i class="bi bi-exclamation-triangle"></i>

                </div>


                <div class="stat-number">

                    <?= $stats[
                        'urgentes'
                    ] ?>

                </div>


                <div class="stat-label">
                    Urgentes abiertas
                </div>

            </div>

        </div>


    </div>


    <!-- =====================================================
         FILTROS
    ====================================================== -->

    <div class="filters-card">

        <form
            method="GET"
            action="<?= url(
                'tecnico/solicitudes.php'
            ) ?>"
        >

            <div class="row g-3">


                <!-- BUSCAR -->

                <div class="col-lg-4">

                    <label
                        for="buscar"
                        class="form-label"
                    >
                        Buscar solicitud
                    </label>


                    <div class="input-group">

                        <span class="input-group-text">

                            <i class="bi bi-search"></i>

                        </span>


                        <input
                            type="text"
                            name="buscar"
                            id="buscar"
                            class="form-control"
                            value="<?= e(
                                $buscar
                            ) ?>"
                            placeholder="Ticket, título, sector, docente..."
                        >

                    </div>

                </div>


                <!-- ESTADO -->

                <div class="col-md-6 col-lg-2">

                    <label
                        for="estado"
                        class="form-label"
                    >
                        Estado
                    </label>


                    <select
                        name="estado"
                        id="estado"
                        class="form-select"
                    >

                        <option value="">
                            Todos
                        </option>


                        <?php foreach (
                            $estadosPermitidos
                            as $estado
                        ): ?>

                            <option
                                value="<?= e(
                                    $estado
                                ) ?>"
                                <?= $estadoFiltro === $estado
                                    ? 'selected'
                                    : ''
                                ?>
                            >

                                <?= e(
                                    $estado
                                ) ?>

                            </option>

                        <?php endforeach; ?>


                    </select>

                </div>


                <!-- PRIORIDAD -->

                <div class="col-md-6 col-lg-2">

                    <label
                        for="prioridad"
                        class="form-label"
                    >
                        Prioridad
                    </label>


                    <select
                        name="prioridad"
                        id="prioridad"
                        class="form-select"
                    >

                        <option value="">
                            Todas
                        </option>


                        <?php foreach (
                            $prioridadesPermitidas
                            as $prioridad
                        ): ?>

                            <option
                                value="<?= e(
                                    $prioridad
                                ) ?>"
                                <?= $prioridadFiltro === $prioridad
                                    ? 'selected'
                                    : ''
                                ?>
                            >

                                <?= e(
                                    $prioridad
                                ) ?>

                            </option>

                        <?php endforeach; ?>


                    </select>

                </div>


                <!-- ÁREA -->

                <div class="col-md-6 col-lg-2">

                    <label
                        for="tipo"
                        class="form-label"
                    >
                        Área
                    </label>


                    <select
                        name="tipo"
                        id="tipo"
                        class="form-select"
                    >

                        <option value="">
                            Todas
                        </option>


                        <option
                            value="Informatica"
                            <?= $tipoFiltro === 'Informatica'
                                ? 'selected'
                                : ''
                            ?>
                        >
                            Informática
                        </option>


                        <option
                            value="Mantenimiento"
                            <?= $tipoFiltro === 'Mantenimiento'
                                ? 'selected'
                                : ''
                            ?>
                        >
                            Mantenimiento
                        </option>


                    </select>

                </div>


                <!-- FILTRAR -->

                <div
                    class="col-md-6 col-lg-2
                           d-flex
                           align-items-end
                           gap-2"
                >

                    <button
                        type="submit"
                        class="btn btn-filtrar flex-fill"
                    >

                        <i class="bi bi-funnel me-1"></i>

                        Filtrar

                    </button>


                    <a
                        href="<?= url(
                            'tecnico/solicitudes.php'
                        ) ?>"
                        class="btn btn-limpiar"
                        title="Limpiar filtros"
                    >

                        <i class="bi bi-x-lg"></i>

                    </a>

                </div>


                <!-- ASIGNACIÓN -->

                <div class="col-12">

                    <div
                        class="d-flex
                               flex-wrap
                               gap-2
                               align-items-center"
                    >

                        <span
                            class="small fw-bold"
                            style="color:#555555;"
                        >
                            Mostrar:
                        </span>


                        <?php

                        $filtrosAsignacion = [

                            'actuales'
                                =>
                                'Asignaciones actuales',

                            'historial'
                                =>
                                'Asignaciones finalizadas',

                            'todas'
                                =>
                                'Todas'

                        ];

                        ?>


                        <?php foreach (
                            $filtrosAsignacion
                            as $valor => $texto
                        ): ?>


                            <?php

                            $queryAsignacion =
                                $_GET;


                            unset(
                                $queryAsignacion[
                                    'pagina'
                                ]
                            );


                            $queryAsignacion[
                                'asignacion'
                            ] =
                                $valor;


                            $urlAsignacion =
                                url(
                                    'tecnico/solicitudes.php?'
                                    .
                                    http_build_query(
                                        $queryAsignacion
                                    )
                                );

                            ?>


                            <a
                                href="<?= e(
                                    $urlAsignacion
                                ) ?>"
                                class="btn btn-sm <?= $asignacionFiltro === $valor
                                    ? 'btn-dark'
                                    : 'btn-outline-secondary'
                                ?>"
                            >

                                <?= e(
                                    $texto
                                ) ?>

                            </a>


                        <?php endforeach; ?>


                    </div>

                </div>


            </div>

        </form>

    </div>


    <!-- =====================================================
         LISTADO
    ====================================================== -->

    <div class="tech-card">

        <div class="tech-card-header">

            <h5>

                <i class="bi bi-list-ul me-2"></i>

                Solicitudes encontradas

            </h5>


            <span class="small text-muted">

                <?= $totalRegistros ?>

                <?= $totalRegistros === 1
                    ? 'resultado'
                    : 'resultados'
                ?>

            </span>

        </div>


        <div class="tech-card-body">


            <?php if (
                empty(
                    $solicitudes
                )
            ): ?>

                <div class="empty-state">

                    <i class="bi bi-inbox"></i>

                    <strong>
                        No se encontraron solicitudes
                    </strong>


                    <div class="mt-2">

                        No existen trabajos que coincidan
                        con los filtros seleccionados.

                    </div>


                    <a
                        href="<?= url(
                            'tecnico/solicitudes.php'
                        ) ?>"
                        class="btn btn-outline-secondary mt-3"
                    >

                        Limpiar filtros

                    </a>

                </div>


            <?php else: ?>


                <?php foreach (
                    $solicitudes
                    as $solicitud
                ): ?>


                    <?php

                    $puedeIntervenir =
                        puedeIntervenirSolicitudTecnico(
                            $solicitud,
                            $rolActual ===
                                'Administrador'
                        );

                    ?>


                    <article
                        class="solicitud-item prioridad-<?= e(
                            $solicitud[
                                'prioridad'
                            ]
                        ) ?>"
                    >


                        <!-- =================================
                             CONTENIDO
                        ================================== -->

                        <div>


                            <!-- CABECERA -->

                            <div class="ticket-top">


                                <span class="ticket-numero">

                                    <?= e(
                                        numeroTicket(
                                            (int)$solicitud[
                                                'id_solicitud'
                                            ]
                                        )
                                    ) ?>

                                </span>


                                <?php if (
                                    (int)$solicitud[
                                        'asignacion_activa'
                                    ] === 1
                                ): ?>

                                    <span class="asignacion-actual">

                                        <i class="bi bi-person-check"></i>

                                        Asignación actual

                                    </span>


                                <?php else: ?>

                                    <span class="asignacion-finalizada">

                                        <i class="bi bi-clock-history"></i>

                                        Asignación finalizada

                                    </span>

                                <?php endif; ?>


                            </div>


                            <!-- TÍTULO -->

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


                            <!-- DESCRIPCIÓN -->

                            <div class="ticket-description">

                                <?= e(
                                    $solicitud[
                                        'descripcion'
                                    ]
                                ) ?>

                            </div>


                            <!-- META -->

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
                                        nombreAreaTecnico(
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


                                <?php if (
                                    !empty(
                                        $solicitud[
                                            'categoria'
                                        ]
                                    )
                                ): ?>

                                    <span>

                                        <i class="bi bi-tag"></i>

                                        <?= e(
                                            $solicitud[
                                                'categoria'
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
                                        fechaArgentina(
                                            $solicitud[
                                                'fecha_creacion'
                                            ]
                                        )
                                    ) ?>

                                </span>


                            </div>


                            <!-- CONTADORES -->

                            <div class="ticket-stats">


                                <span class="ticket-stat">

                                    <i class="bi bi-tools"></i>

                                    <strong>

                                        <?= (int)$solicitud[
                                            'cantidad_intervenciones'
                                        ] ?>

                                    </strong>

                                    intervenciones

                                </span>


                                <span class="ticket-stat">

                                    <i class="bi bi-person-gear"></i>

                                    <strong>

                                        <?= (int)$solicitud[
                                            'mis_intervenciones'
                                        ] ?>

                                    </strong>

                                    realizadas por mí

                                </span>


                                <?php if (
                                    (int)$solicitud[
                                        'cantidad_imagenes'
                                    ] > 0
                                ): ?>

                                    <span class="ticket-stat">

                                        <i class="bi bi-images"></i>

                                        <strong>

                                            <?= (int)$solicitud[
                                                'cantidad_imagenes'
                                            ] ?>

                                        </strong>

                                        fotos

                                    </span>

                                <?php endif; ?>


                                <?php if (
                                    (int)$solicitud[
                                        'cantidad_comentarios'
                                    ] > 0
                                ): ?>

                                    <span class="ticket-stat">

                                        <i class="bi bi-chat-dots"></i>

                                        <strong>

                                            <?= (int)$solicitud[
                                                'cantidad_comentarios'
                                            ] ?>

                                        </strong>

                                        comentarios

                                    </span>

                                <?php endif; ?>


                            </div>


                            <!-- ASIGNACIÓN -->

                            <?php if (
                                !empty(
                                    $solicitud[
                                        'observaciones_asignacion'
                                    ]
                                )
                            ): ?>

                                <div class="asignacion-nota">

                                    <strong>

                                        <i class="bi bi-person-check me-1"></i>

                                        Observación de asignación:

                                    </strong>


                                    <?= e(
                                        $solicitud[
                                            'observaciones_asignacion'
                                        ]
                                    ) ?>

                                </div>

                            <?php endif; ?>


                            <div class="asignacion-fecha">

                                Asignado:

                                <?= e(
                                    fechaArgentina(
                                        $solicitud[
                                            'fecha_asignacion'
                                        ]
                                    )
                                ) ?>


                                <?php if (
                                    !empty(
                                        $solicitud[
                                            'fecha_fin'
                                        ]
                                    )
                                ): ?>

                                    · Finalizó:

                                    <?= e(
                                        fechaArgentina(
                                            $solicitud[
                                                'fecha_fin'
                                            ]
                                        )
                                    ) ?>

                                <?php endif; ?>


                                <?php if (
                                    !empty(
                                        $solicitud[
                                            'ultima_intervencion'
                                        ]
                                    )
                                ): ?>

                                    · Última intervención:

                                    <?= e(
                                        fechaArgentina(
                                            $solicitud[
                                                'ultima_intervencion'
                                            ]
                                        )
                                    ) ?>

                                <?php endif; ?>


                            </div>


                            <!-- PENDIENTE -->

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

                                <div class="pendiente-box">

                                    <strong>

                                        <i class="bi bi-hourglass-split me-1"></i>

                                        Motivo pendiente:

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
                             LATERAL
                        ================================== -->

                        <div class="ticket-side">


                            <div class="badges">


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


                                <!-- VER -->

                                <a
                                    href="<?= url(
                                        'ver_solicitud.php?id='
                                        .
                                        (int)$solicitud[
                                            'id_solicitud'
                                        ]
                                    ) ?>"
                                    class="btn-ticket btn-ver"
                                >

                                    <i class="bi bi-eye"></i>

                                    Ver

                                </a>


                                <!-- INTERVENIR -->

                                <?php if (
                                    $puedeIntervenir
                                ): ?>

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

                                        <?= $solicitud[
                                            'estado'
                                        ] === 'Resuelta'
                                            ? 'Nueva intervención'
                                            : 'Intervenir'
                                        ?>

                                    </a>


                                <?php else: ?>

                                    <span
                                        class="btn-ticket btn-intervenir-disabled"
                                        title="La asignación ya no está activa"
                                    >

                                        <i class="bi bi-lock"></i>

                                        Sin acceso

                                    </span>

                                <?php endif; ?>


                            </div>


                        </div>


                    </article>


                <?php endforeach; ?>


            <?php endif; ?>


        </div>


        <!-- =================================================
             PAGINACIÓN
        ================================================== -->

        <?php if (
            $totalPaginas > 1
        ): ?>

            <div
                class="border-top
                       p-3
                       d-flex
                       justify-content-between
                       align-items-center
                       flex-wrap
                       gap-3"
            >


                <div class="small text-muted">

                    Página

                    <strong>
                        <?= $pagina ?>
                    </strong>

                    de

                    <strong>
                        <?= $totalPaginas ?>
                    </strong>

                </div>


                <nav>

                    <ul class="pagination mb-0">


                        <!-- ANTERIOR -->

                        <li
                            class="page-item <?= $pagina <= 1
                                ? 'disabled'
                                : ''
                            ?>"
                        >

                            <a
                                class="page-link"
                                href="<?= $pagina > 1
                                    ? e(
                                        urlPaginaTecnicoSolicitudes(
                                            $pagina - 1
                                        )
                                    )
                                    : '#'
                                ?>"
                            >

                                <i class="bi bi-chevron-left"></i>

                            </a>

                        </li>


                        <?php

                        $paginaInicio =
                            max(
                                1,
                                $pagina - 2
                            );


                        $paginaFin =
                            min(
                                $totalPaginas,
                                $pagina + 2
                            );

                        ?>


                        <!-- PRIMERA -->

                        <?php if (
                            $paginaInicio > 1
                        ): ?>

                            <li class="page-item">

                                <a
                                    class="page-link"
                                    href="<?= e(
                                        urlPaginaTecnicoSolicitudes(
                                            1
                                        )
                                    ) ?>"
                                >
                                    1
                                </a>

                            </li>


                            <?php if (
                                $paginaInicio > 2
                            ): ?>

                                <li class="page-item disabled">

                                    <span class="page-link">
                                        ...
                                    </span>

                                </li>

                            <?php endif; ?>

                        <?php endif; ?>


                        <!-- PÁGINAS -->

                        <?php for (
                            $i = $paginaInicio;
                            $i <= $paginaFin;
                            $i++
                        ): ?>

                            <li
                                class="page-item <?= $i === $pagina
                                    ? 'active'
                                    : ''
                                ?>"
                            >

                                <a
                                    class="page-link"
                                    href="<?= e(
                                        urlPaginaTecnicoSolicitudes(
                                            $i
                                        )
                                    ) ?>"
                                >

                                    <?= $i ?>

                                </a>

                            </li>

                        <?php endfor; ?>


                        <!-- ÚLTIMA -->

                        <?php if (
                            $paginaFin < $totalPaginas
                        ): ?>


                            <?php if (
                                $paginaFin
                                <
                                $totalPaginas - 1
                            ): ?>

                                <li class="page-item disabled">

                                    <span class="page-link">
                                        ...
                                    </span>

                                </li>

                            <?php endif; ?>


                            <li class="page-item">

                                <a
                                    class="page-link"
                                    href="<?= e(
                                        urlPaginaTecnicoSolicitudes(
                                            $totalPaginas
                                        )
                                    ) ?>"
                                >

                                    <?= $totalPaginas ?>

                                </a>

                            </li>


                        <?php endif; ?>


                        <!-- SIGUIENTE -->

                        <li
                            class="page-item <?= $pagina >= $totalPaginas
                                ? 'disabled'
                                : ''
                            ?>"
                        >

                            <a
                                class="page-link"
                                href="<?= $pagina < $totalPaginas
                                    ? e(
                                        urlPaginaTecnicoSolicitudes(
                                            $pagina + 1
                                        )
                                    )
                                    : '#'
                                ?>"
                            >

                                <i class="bi bi-chevron-right"></i>

                            </a>

                        </li>


                    </ul>

                </nav>


            </div>

        <?php endif; ?>


    </div>


</div>


<?php

require_once __DIR__
    . '/../includes/footer.php';

?>