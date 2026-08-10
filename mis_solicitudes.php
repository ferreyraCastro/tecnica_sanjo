<?php
// ============================================================
// COLEGIO SAN JOSÉ
// Sistema de Gestión Técnica
// Archivo: /tecnica/mis_solicitudes.php
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
// VERIFICAR USUARIO ACTIVO
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
// USUARIO
// ============================================================

$idUsuario = (int)usuarioId();


// ============================================================
// FILTROS
// ============================================================

$buscar =
    trim(
        $_GET['buscar']
        ?? ''
    );

$estado =
    trim(
        $_GET['estado']
        ?? ''
    );

$tipo =
    trim(
        $_GET['tipo']
        ?? ''
    );

$prioridad =
    trim(
        $_GET['prioridad']
        ?? ''
    );


// ============================================================
// VALORES PERMITIDOS
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


$tiposPermitidos = [

    'Informatica',
    'Mantenimiento'

];


$prioridadesPermitidas = [

    'Baja',
    'Normal',
    'Alta',
    'Urgente'

];


// ============================================================
// VALIDAR FILTROS
// ============================================================

if (
    $estado !== ''
    &&
    !in_array(
        $estado,
        $estadosPermitidos,
        true
    )
) {

    $estado = '';
}


if (
    $tipo !== ''
    &&
    !in_array(
        $tipo,
        $tiposPermitidos,
        true
    )
) {

    $tipo = '';
}


if (
    $prioridad !== ''
    &&
    !in_array(
        $prioridad,
        $prioridadesPermitidas,
        true
    )
) {

    $prioridad = '';
}


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


$porPagina = 10;

$offset =
    ($pagina - 1)
    * $porPagina;


// ============================================================
// CONSTRUIR CONSULTA
// ============================================================

$condiciones = [

    's.id_usuario = ?'

];

$parametros = [

    $idUsuario

];


// ============================================================
// BUSCADOR
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
            c.nombre LIKE ?
            OR
            CAST(s.id_solicitud AS CHAR) LIKE ?
        )
    ";

    $buscarSql =
        '%' .
        $buscar .
        '%';

    $parametros[] =
        $buscarSql;

    $parametros[] =
        $buscarSql;

    $parametros[] =
        $buscarSql;

    $parametros[] =
        $buscarSql;

    $parametros[] =
        $buscarSql;
}


// ============================================================
// ESTADO
// ============================================================

if ($estado !== '') {

    $condiciones[] =
        's.estado = ?';

    $parametros[] =
        $estado;
}


// ============================================================
// TIPO
// ============================================================

if ($tipo !== '') {

    $condiciones[] =
        's.tipo = ?';

    $parametros[] =
        $tipo;
}


// ============================================================
// PRIORIDAD
// ============================================================

if ($prioridad !== '') {

    $condiciones[] =
        's.prioridad = ?';

    $parametros[] =
        $prioridad;
}


$where =
    implode(
        ' AND ',
        $condiciones
    );


// ============================================================
// CONTAR RESULTADOS
// ============================================================

$sqlCantidad = "
    SELECT COUNT(*)

    FROM solicitudes s

    LEFT JOIN sectores sec
        ON s.id_sector = sec.id_sector

    LEFT JOIN categorias c
        ON s.id_categoria = c.id_categoria

    WHERE {$where}
";


$stmtCantidad =
    $conexion->prepare(
        $sqlCantidad
    );


$stmtCantidad->execute(
    $parametros
);


$totalRegistros =
    (int)$stmtCantidad->fetchColumn();


$totalPaginas =
    max(
        1,
        (int)ceil(
            $totalRegistros
            /
            $porPagina
        )
    );


if ($pagina > $totalPaginas) {

    $pagina =
        $totalPaginas;

    $offset =
        ($pagina - 1)
        * $porPagina;
}


// ============================================================
// CONSULTAR SOLICITUDES
// ============================================================

$sql = "
    SELECT

        s.id_solicitud,
        s.tipo,
        s.titulo,
        s.descripcion,
        s.prioridad,
        s.estado,
        s.motivo_pendiente,
        s.fecha_creacion,
        s.fecha_actualizacion,
        s.fecha_resolucion,

        sec.nombre AS sector,

        c.nombre AS categoria,

        (
            SELECT COUNT(*)

            FROM solicitud_imagenes si

            WHERE
                si.id_solicitud =
                s.id_solicitud
        ) AS cantidad_imagenes,

        (
            SELECT COUNT(*)

            FROM comentarios co

            WHERE
                co.id_solicitud =
                s.id_solicitud
        ) AS cantidad_comentarios,

        (
            SELECT COUNT(*)

            FROM intervenciones i

            WHERE
                i.id_solicitud =
                s.id_solicitud
        ) AS cantidad_intervenciones

    FROM solicitudes s

    LEFT JOIN sectores sec
        ON s.id_sector = sec.id_sector

    LEFT JOIN categorias c
        ON s.id_categoria = c.id_categoria

    WHERE {$where}

    ORDER BY

        CASE s.estado

            WHEN 'Nueva'
                THEN 1

            WHEN 'Asignada'
                THEN 2

            WHEN 'En proceso'
                THEN 3

            WHEN 'Pendiente'
                THEN 4

            WHEN 'Resuelta'
                THEN 5

            WHEN 'Cerrada'
                THEN 6

            WHEN 'Cancelada'
                THEN 7

            ELSE 8

        END,

        s.fecha_creacion DESC

    LIMIT {$porPagina}

    OFFSET {$offset}
";


$stmt =
    $conexion->prepare(
        $sql
    );


$stmt->execute(
    $parametros
);


$solicitudes =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


// ============================================================
// ESTADÍSTICAS
// ============================================================

$estadisticas =
    obtenerEstadisticasUsuario(
        $conexion,
        $idUsuario
    );


// ============================================================
// FLASH
// ============================================================

$flash =
    obtenerFlash();


// ============================================================
// FUNCIÓN PARA PAGINACIÓN
// ============================================================

function urlPaginaMisSolicitudes(
    int $numeroPagina
): string {

    $parametros =
        $_GET;

    $parametros['pagina'] =
        $numeroPagina;

    return url(
        'mis_solicitudes.php?'
        .
        http_build_query(
            $parametros
        )
    );
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

.solicitudes-wrapper {

    max-width: 1450px;

    margin: 0 auto;

    padding:
        5px 12px 45px;

}


/* ============================================================
   CABECERA
============================================================ */

.page-top {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 20px;

    margin-bottom: 24px;

}


.page-title h1 {

    color: #760000;

    font-size: 28px;

    font-weight: 800;

    margin: 0;

}


.page-title p {

    color: #777777;

    margin:
        6px 0 0;

}


.btn-nueva {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    gap: 8px;

    padding:
        11px 18px;

    border-radius: 11px;

    background:
        linear-gradient(
            135deg,
            #760000,
            #B12626
        );

    color: #FFFFFF;

    text-decoration: none;

    font-weight: 700;

    box-shadow:
        0 5px 16px
        rgba(118,0,0,.18);

}


.btn-nueva:hover {

    background: #760000;

    color: #FFFFFF;

}


/* ============================================================
   ESTADÍSTICAS
============================================================ */

.stats-box {

    background: #FFFFFF;

    border:
        1px solid #ECECEC;

    border-radius: 16px;

    padding: 18px;

    height: 100%;

    box-shadow:
        0 4px 15px
        rgba(0,0,0,.04);

}


.stats-number {

    font-size: 27px;

    font-weight: 800;

    color: #333333;

}


.stats-text {

    font-size: 12px;

    font-weight: 600;

    color: #777777;

    margin-top: 3px;

}


.stats-icon {

    width: 42px;

    height: 42px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 11px;

    font-size: 18px;

    margin-bottom: 10px;

}


.stat-total {

    background: #F3E4E4;

    color: #760000;

}


.stat-proceso {

    background: #FFF3CD;

    color: #9B6A00;

}


.stat-pendiente {

    background: #FFE6E6;

    color: #B12626;

}


.stat-resuelta {

    background: #E0F3E8;

    color: #198754;

}


/* ============================================================
   FILTROS
============================================================ */

.filtros-card {

    background: #FFFFFF;

    border:
        1px solid #ECECEC;

    border-radius: 17px;

    padding: 20px;

    margin-top: 25px;

    margin-bottom: 20px;

    box-shadow:
        0 5px 17px
        rgba(0,0,0,.04);

}


.form-control,
.form-select {

    min-height: 44px;

    border-radius: 9px;

    border:
        1px solid #DCDCDC;

}


.form-control:focus,
.form-select:focus {

    border-color: #B12626;

    box-shadow:
        0 0 0 .2rem
        rgba(177,38,38,.08);

}


.btn-filtrar {

    min-height: 44px;

    border: none;

    border-radius: 9px;

    background: #B12626;

    color: #FFFFFF;

    font-weight: 600;

    padding:
        8px 18px;

}


.btn-filtrar:hover {

    background: #760000;

    color: #FFFFFF;

}


.btn-limpiar {

    min-height: 44px;

    border:
        1px solid #D5D5D5;

    border-radius: 9px;

    background: #FFFFFF;

    color: #555555;

    font-weight: 600;

}


.btn-limpiar:hover {

    background: #F5F5F5;

}


/* ============================================================
   TABLA
============================================================ */

.listado-card {

    background: #FFFFFF;

    border:
        1px solid #ECECEC;

    border-radius: 18px;

    overflow: hidden;

    box-shadow:
        0 5px 20px
        rgba(0,0,0,.05);

}


.listado-header {

    padding:
        18px 21px;

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 15px;

    border-bottom:
        1px solid #EEEEEE;

}


.listado-header h5 {

    margin: 0;

    color: #760000;

    font-size: 16px;

    font-weight: 800;

}


.resultados {

    font-size: 12px;

    color: #777777;

}


.table {

    margin-bottom: 0;

}


.table thead th {

    background: #FAFAFA;

    color: #555555;

    font-size: 12px;

    text-transform: uppercase;

    letter-spacing: .3px;

    border-bottom:
        1px solid #E8E8E8;

    padding:
        14px 16px;

    white-space: nowrap;

}


.table tbody td {

    vertical-align: middle;

    padding:
        15px 16px;

    border-color: #EEEEEE;

}


.ticket-numero {

    color: #760000;

    font-weight: 800;

    white-space: nowrap;

}


.ticket-titulo {

    font-weight: 700;

    color: #333333;

    text-decoration: none;

}


.ticket-titulo:hover {

    color: #B12626;

}


.ticket-descripcion {

    display: block;

    margin-top: 4px;

    color: #888888;

    font-size: 12px;

    max-width: 410px;

    white-space: nowrap;

    overflow: hidden;

    text-overflow: ellipsis;

}


.meta {

    font-size: 12px;

    color: #777777;

}


.meta i {

    color: #B12626;

}


/* ============================================================
   BADGES
============================================================ */

.ticket-badge {

    padding:
        6px 9px;

    border-radius: 30px;

    font-size: 11px;

    font-weight: 700;

    white-space: nowrap;

}


/* ============================================================
   ICONOS INFO
============================================================ */

.ticket-info {

    display: flex;

    align-items: center;

    gap: 10px;

    margin-top: 8px;

    color: #888888;

    font-size: 11px;

}


.ticket-info span {

    display: inline-flex;

    align-items: center;

    gap: 4px;

}


/* ============================================================
   BOTÓN VER
============================================================ */

.btn-ver-ticket {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    width: 38px;

    height: 38px;

    border-radius: 9px;

    background: #FFF3F3;

    color: #760000;

    text-decoration: none;

    border:
        1px solid #F0D7D7;

}


.btn-ver-ticket:hover {

    background: #B12626;

    color: #FFFFFF;

}


/* ============================================================
   PENDIENTE
============================================================ */

.motivo-pendiente {

    background: #FFF8E5;

    border-left:
        3px solid #E0A800;

    border-radius: 7px;

    padding:
        7px 10px;

    margin-top: 8px;

    color: #6C5800;

    font-size: 11px;

}


/* ============================================================
   VACÍO
============================================================ */

.estado-vacio {

    padding:
        60px 20px;

    text-align: center;

    color: #888888;

}


.estado-vacio i {

    display: block;

    font-size: 50px;

    color: #D2D2D2;

    margin-bottom: 12px;

}


.estado-vacio h5 {

    color: #555555;

    font-weight: 700;

}


/* ============================================================
   PAGINACIÓN
============================================================ */

.pagination {

    margin-bottom: 0;

}


.page-link {

    color: #760000;

}


.page-link:hover {

    color: #B12626;

}


.page-item.active
.page-link {

    background: #B12626;

    border-color: #B12626;

    color: #FFFFFF;

}


/* ============================================================
   RESPONSIVE CARDS
============================================================ */

.mobile-ticket {

    display: none;

}


@media
(max-width: 850px) {

    .page-top {

        align-items: flex-start;

        flex-direction: column;

    }


    .btn-nueva {

        width: 100%;

    }


    .desktop-table {

        display: none;

    }


    .mobile-ticket {

        display: block;

    }


    .mobile-ticket-card {

        padding: 18px;

        border-bottom:
            1px solid #EEEEEE;

    }


    .mobile-ticket-card:last-child {

        border-bottom: 0;

    }


    .mobile-header {

        display: flex;

        justify-content: space-between;

        align-items: flex-start;

        gap: 10px;

    }


    .mobile-ticket-card
    .ticket-titulo {

        display: block;

        margin:
            7px 0;

    }


    .mobile-meta {

        display: flex;

        flex-wrap: wrap;

        gap:
            6px 12px;

        color: #777777;

        font-size: 12px;

        margin-top: 10px;

    }


    .mobile-actions {

        margin-top: 15px;

    }


    .mobile-actions
    .btn-ver-ticket {

        width: 100%;

        height: 40px;

        gap: 7px;

    }

}

</style>


<div class="solicitudes-wrapper">


    <!-- =====================================================
         CABECERA
    ====================================================== -->

    <div class="page-top">

        <div class="page-title">

            <h1>

                <i class="bi bi-ticket-detailed me-1"></i>

                Mis solicitudes

            </h1>

            <p>

                Consultá el estado y seguimiento
                de los pedidos que registraste.

            </p>

        </div>


        <a
            href="<?= url('nueva_solicitud.php') ?>"
            class="btn-nueva"
        >

            <i class="bi bi-plus-circle"></i>

            Nueva solicitud

        </a>

    </div>



    <!-- =====================================================
         FLASH
    ====================================================== -->

    <?php if ($flash): ?>

        <div
            class="alert alert-<?= e(
                $flash['tipo'] === 'success'
                ? 'success'
                : 'info'
            ) ?> alert-dismissible fade show"
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

    <div class="row g-3">


        <div class="col-6 col-lg-3">

            <div class="stats-box">

                <div class="stats-icon stat-total">

                    <i class="bi bi-ticket-detailed"></i>

                </div>

                <div class="stats-number">

                    <?= $estadisticas['total'] ?>

                </div>

                <div class="stats-text">

                    Total de solicitudes

                </div>

            </div>

        </div>


        <div class="col-6 col-lg-3">

            <div class="stats-box">

                <div class="stats-icon stat-proceso">

                    <i class="bi bi-arrow-repeat"></i>

                </div>

                <div class="stats-number">

                    <?= $estadisticas['en_proceso'] ?>

                </div>

                <div class="stats-text">

                    En proceso

                </div>

            </div>

        </div>


        <div class="col-6 col-lg-3">

            <div class="stats-box">

                <div class="stats-icon stat-pendiente">

                    <i class="bi bi-hourglass-split"></i>

                </div>

                <div class="stats-number">

                    <?= $estadisticas['pendientes'] ?>

                </div>

                <div class="stats-text">

                    Pendientes

                </div>

            </div>

        </div>


        <div class="col-6 col-lg-3">

            <div class="stats-box">

                <div class="stats-icon stat-resuelta">

                    <i class="bi bi-check-circle"></i>

                </div>

                <div class="stats-number">

                    <?= $estadisticas['resueltas'] ?>

                </div>

                <div class="stats-text">

                    Resueltas

                </div>

            </div>

        </div>


    </div>



    <!-- =====================================================
         FILTROS
    ====================================================== -->

    <div class="filtros-card">

        <form
            method="GET"
            action="<?= url(
                'mis_solicitudes.php'
            ) ?>"
        >

            <div class="row g-3">


                <!-- BUSCAR -->

                <div class="col-lg-4">

                    <label
                        for="buscar"
                        class="form-label"
                    >

                        Buscar

                    </label>

                    <div class="input-group">

                        <span class="input-group-text">

                            <i class="bi bi-search"></i>

                        </span>

                        <input
                            type="text"
                            class="form-control"
                            id="buscar"
                            name="buscar"
                            value="<?= e($buscar) ?>"
                            placeholder="Título, sector, categoría..."
                        >

                    </div>

                </div>



                <!-- ESTADO -->

                <div class="col-md-4 col-lg-2">

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
                            as $estadoOpcion
                        ): ?>

                            <option
                                value="<?= e(
                                    $estadoOpcion
                                ) ?>"
                                <?= $estado === $estadoOpcion
                                    ? 'selected'
                                    : ''
                                ?>
                            >

                                <?= e(
                                    $estadoOpcion
                                ) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>



                <!-- TIPO -->

                <div class="col-md-4 col-lg-2">

                    <label
                        for="tipo"
                        class="form-label"
                    >

                        Tipo

                    </label>

                    <select
                        name="tipo"
                        id="tipo"
                        class="form-select"
                    >

                        <option value="">
                            Todos
                        </option>

                        <option
                            value="Informatica"
                            <?= $tipo === 'Informatica'
                                ? 'selected'
                                : ''
                            ?>
                        >

                            Informática

                        </option>

                        <option
                            value="Mantenimiento"
                            <?= $tipo === 'Mantenimiento'
                                ? 'selected'
                                : ''
                            ?>
                        >

                            Mantenimiento

                        </option>

                    </select>

                </div>



                <!-- PRIORIDAD -->

                <div class="col-md-4 col-lg-2">

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
                            as $prioridadOpcion
                        ): ?>

                            <option
                                value="<?= e(
                                    $prioridadOpcion
                                ) ?>"
                                <?= $prioridad === $prioridadOpcion
                                    ? 'selected'
                                    : ''
                                ?>
                            >

                                <?= e(
                                    $prioridadOpcion
                                ) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>



                <!-- BOTONES -->

                <div
                    class="col-lg-2
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
                            'mis_solicitudes.php'
                        ) ?>"
                        class="btn btn-limpiar"
                        title="Limpiar filtros"
                    >

                        <i class="bi bi-x-lg"></i>

                    </a>

                </div>


            </div>

        </form>

    </div>



    <!-- =====================================================
         LISTADO
    ====================================================== -->

    <div class="listado-card">

        <div class="listado-header">

            <h5>

                <i class="bi bi-list-ul me-2"></i>

                Solicitudes registradas

            </h5>

            <div class="resultados">

                <?= $totalRegistros ?>

                <?= $totalRegistros === 1
                    ? 'resultado'
                    : 'resultados'
                ?>

            </div>

        </div>



        <?php if (empty($solicitudes)): ?>


            <div class="estado-vacio">

                <i class="bi bi-inbox"></i>

                <h5>
                    No encontramos solicitudes
                </h5>

                <p>

                    No hay pedidos que coincidan
                    con los filtros seleccionados.

                </p>

                <a
                    href="<?= url(
                        'nueva_solicitud.php'
                    ) ?>"
                    class="btn btn-sanjo mt-2"
                >

                    <i class="bi bi-plus-circle me-1"></i>

                    Crear una solicitud

                </a>

            </div>


        <?php else: ?>


            <!-- =================================================
                 TABLA ESCRITORIO
            ================================================== -->

            <div
                class="table-responsive
                       desktop-table"
            >

                <table class="table">

                    <thead>

                        <tr>

                            <th>
                                Ticket
                            </th>

                            <th>
                                Solicitud
                            </th>

                            <th>
                                Tipo / Sector
                            </th>

                            <th>
                                Prioridad
                            </th>

                            <th>
                                Estado
                            </th>

                            <th>
                                Fecha
                            </th>

                            <th class="text-center">
                                Ver
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php foreach (
                        $solicitudes
                        as $solicitud
                    ): ?>


                        <tr>


                            <!-- TICKET -->

                            <td>

                                <span class="ticket-numero">

                                    <?= e(
                                        numeroTicket(
                                            (int)$solicitud['id_solicitud']
                                        )
                                    ) ?>

                                </span>

                            </td>



                            <!-- SOLICITUD -->

                            <td>

                                <a
                                    href="<?= url(
                                        'ver_solicitud.php?id='
                                        .
                                        (int)$solicitud['id_solicitud']
                                    ) ?>"
                                    class="ticket-titulo"
                                >

                                    <?= e(
                                        $solicitud['titulo']
                                    ) ?>

                                </a>


                                <span class="ticket-descripcion">

                                    <?= e(
                                        $solicitud['descripcion']
                                    ) ?>

                                </span>


                                <div class="ticket-info">


                                    <?php if (
                                        (int)$solicitud['cantidad_imagenes']
                                        > 0
                                    ): ?>

                                        <span>

                                            <i class="bi bi-image"></i>

                                            <?= (int)$solicitud['cantidad_imagenes'] ?>

                                        </span>

                                    <?php endif; ?>


                                    <?php if (
                                        (int)$solicitud['cantidad_comentarios']
                                        > 0
                                    ): ?>

                                        <span>

                                            <i class="bi bi-chat-left-text"></i>

                                            <?= (int)$solicitud['cantidad_comentarios'] ?>

                                        </span>

                                    <?php endif; ?>


                                    <?php if (
                                        (int)$solicitud['cantidad_intervenciones']
                                        > 0
                                    ): ?>

                                        <span>

                                            <i class="bi bi-tools"></i>

                                            <?= (int)$solicitud['cantidad_intervenciones'] ?>

                                        </span>

                                    <?php endif; ?>


                                </div>


                                <?php if (
                                    $solicitud['estado']
                                    === 'Pendiente'
                                    &&
                                    !empty(
                                        $solicitud['motivo_pendiente']
                                    )
                                ): ?>

                                    <div class="motivo-pendiente">

                                        <strong>
                                            Pendiente:
                                        </strong>

                                        <?= e(
                                            $solicitud['motivo_pendiente']
                                        ) ?>

                                    </div>

                                <?php endif; ?>


                            </td>



                            <!-- TIPO / SECTOR -->

                            <td>

                                <div class="meta">

                                    <i class="<?= e(
                                        iconoTipo(
                                            $solicitud['tipo']
                                        )
                                    ) ?>"></i>

                                    <?= e(
                                        nombreTipo(
                                            $solicitud['tipo']
                                        )
                                    ) ?>

                                </div>


                                <?php if (
                                    !empty(
                                        $solicitud['sector']
                                    )
                                ): ?>

                                    <div class="meta mt-1">

                                        <i class="bi bi-geo-alt"></i>

                                        <?= e(
                                            $solicitud['sector']
                                        ) ?>

                                    </div>

                                <?php endif; ?>


                                <?php if (
                                    !empty(
                                        $solicitud['categoria']
                                    )
                                ): ?>

                                    <div class="meta mt-1">

                                        <i class="bi bi-tag"></i>

                                        <?= e(
                                            $solicitud['categoria']
                                        ) ?>

                                    </div>

                                <?php endif; ?>

                            </td>



                            <!-- PRIORIDAD -->

                            <td>

                                <span
                                    class="badge <?= e(
                                        clasePrioridad(
                                            $solicitud['prioridad']
                                        )
                                    ) ?> ticket-badge"
                                >

                                    <?= e(
                                        $solicitud['prioridad']
                                    ) ?>

                                </span>

                            </td>



                            <!-- ESTADO -->

                            <td>

                                <span
                                    class="badge <?= e(
                                        claseEstado(
                                            $solicitud['estado']
                                        )
                                    ) ?> ticket-badge"
                                >

                                    <?= e(
                                        $solicitud['estado']
                                    ) ?>

                                </span>

                            </td>



                            <!-- FECHA -->

                            <td>

                                <div class="meta">

                                    <?= e(
                                        fechaCorta(
                                            $solicitud['fecha_creacion']
                                        )
                                    ) ?>

                                </div>

                                <div class="meta mt-1">

                                    <?= e(
                                        tiempoTranscurrido(
                                            $solicitud['fecha_creacion']
                                        )
                                    ) ?>

                                </div>

                            </td>



                            <!-- VER -->

                            <td class="text-center">

                                <a
                                    href="<?= url(
                                        'ver_solicitud.php?id='
                                        .
                                        (int)$solicitud['id_solicitud']
                                    ) ?>"
                                    class="btn-ver-ticket"
                                    title="Ver solicitud"
                                >

                                    <i class="bi bi-eye"></i>

                                </a>

                            </td>


                        </tr>


                    <?php endforeach; ?>


                    </tbody>

                </table>

            </div>



            <!-- =================================================
                 MÓVIL
            ================================================== -->

            <div class="mobile-ticket">


                <?php foreach (
                    $solicitudes
                    as $solicitud
                ): ?>


                    <div class="mobile-ticket-card">


                        <div class="mobile-header">

                            <div>

                                <div class="ticket-numero">

                                    <?= e(
                                        numeroTicket(
                                            (int)$solicitud['id_solicitud']
                                        )
                                    ) ?>

                                </div>


                                <a
                                    href="<?= url(
                                        'ver_solicitud.php?id='
                                        .
                                        (int)$solicitud['id_solicitud']
                                    ) ?>"
                                    class="ticket-titulo"
                                >

                                    <?= e(
                                        $solicitud['titulo']
                                    ) ?>

                                </a>

                            </div>


                            <span
                                class="badge <?= e(
                                    claseEstado(
                                        $solicitud['estado']
                                    )
                                ) ?> ticket-badge"
                            >

                                <?= e(
                                    $solicitud['estado']
                                ) ?>

                            </span>

                        </div>



                        <div class="mobile-meta">

                            <span>

                                <i class="<?= e(
                                    iconoTipo(
                                        $solicitud['tipo']
                                    )
                                ) ?>"></i>

                                <?= e(
                                    nombreTipo(
                                        $solicitud['tipo']
                                    )
                                ) ?>

                            </span>


                            <?php if (
                                !empty(
                                    $solicitud['sector']
                                )
                            ): ?>

                                <span>

                                    <i class="bi bi-geo-alt"></i>

                                    <?= e(
                                        $solicitud['sector']
                                    ) ?>

                                </span>

                            <?php endif; ?>


                            <span>

                                <i class="bi bi-calendar3"></i>

                                <?= e(
                                    fechaCorta(
                                        $solicitud['fecha_creacion']
                                    )
                                ) ?>

                            </span>

                        </div>



                        <div class="mt-3">

                            <span
                                class="badge <?= e(
                                    clasePrioridad(
                                        $solicitud['prioridad']
                                    )
                                ) ?> ticket-badge"
                            >

                                <?= e(
                                    $solicitud['prioridad']
                                ) ?>

                            </span>

                        </div>



                        <?php if (
                            $solicitud['estado']
                            === 'Pendiente'
                            &&
                            !empty(
                                $solicitud['motivo_pendiente']
                            )
                        ): ?>

                            <div class="motivo-pendiente">

                                <strong>
                                    Motivo pendiente:
                                </strong>

                                <?= e(
                                    $solicitud['motivo_pendiente']
                                ) ?>

                            </div>

                        <?php endif; ?>



                        <div class="ticket-info">


                            <?php if (
                                (int)$solicitud['cantidad_imagenes']
                                > 0
                            ): ?>

                                <span>

                                    <i class="bi bi-image"></i>

                                    <?= (int)$solicitud['cantidad_imagenes'] ?>
                                    fotos

                                </span>

                            <?php endif; ?>


                            <?php if (
                                (int)$solicitud['cantidad_comentarios']
                                > 0
                            ): ?>

                                <span>

                                    <i class="bi bi-chat"></i>

                                    <?= (int)$solicitud['cantidad_comentarios'] ?>
                                    comentarios

                                </span>

                            <?php endif; ?>


                        </div>



                        <div class="mobile-actions">

                            <a
                                href="<?= url(
                                    'ver_solicitud.php?id='
                                    .
                                    (int)$solicitud['id_solicitud']
                                ) ?>"
                                class="btn-ver-ticket"
                            >

                                <i class="bi bi-eye"></i>

                                Ver solicitud

                            </a>

                        </div>


                    </div>


                <?php endforeach; ?>


            </div>


        <?php endif; ?>


        <!-- =================================================
             PAGINACIÓN
        ================================================== -->

        <?php if (
            $totalPaginas > 1
        ): ?>

            <div
                class="p-3
                       border-top
                       d-flex
                       justify-content-center"
            >

                <nav>

                    <ul class="pagination">


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
                                        urlPaginaMisSolicitudes(
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

                        $inicio =
                            max(
                                1,
                                $pagina - 2
                            );

                        $fin =
                            min(
                                $totalPaginas,
                                $pagina + 2
                            );

                        ?>


                        <?php for (
                            $i = $inicio;
                            $i <= $fin;
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
                                        urlPaginaMisSolicitudes(
                                            $i
                                        )
                                    ) ?>"
                                >

                                    <?= $i ?>

                                </a>

                            </li>

                        <?php endfor; ?>



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
                                        urlPaginaMisSolicitudes(
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
    . '/includes/footer.php';

?>