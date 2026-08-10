<?php
// ============================================================
// COLEGIO SAN JOSÉ
// Sistema de Gestión Técnica
// Archivo: /tecnica/tecnico/intervenciones.php
//
// Historial completo de intervenciones.
// - Técnico: ve únicamente las propias.
// - Administrador: ve las de todos los técnicos (solo
//   lectura, no interviene).
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
        'Tu sesión finalizó o la cuenta se encuentra inactiva.';

    header(
        'Location: ' . url('login.php')
    );

    exit;
}


// ============================================================
// DATOS DEL USUARIO
// ============================================================

$idTecnico = (int)usuarioId();

$rolActual =
    $_SESSION['usuario']['rol']
    ?? '';

$esAdministrador =
    $rolActual === 'Administrador';


// ============================================================
// FILTROS
// ============================================================

$busqueda =
    trim(
        (string)($_GET['buscar'] ?? '')
    );

$fechaDesde =
    trim(
        (string)($_GET['desde'] ?? '')
    );

$fechaHasta =
    trim(
        (string)($_GET['hasta'] ?? '')
    );

$soloPendientes =
    isset($_GET['pendientes'])
    && $_GET['pendientes'] === '1';

$filtroTecnico =
    $esAdministrador
        ? (int)($_GET['tecnico'] ?? 0)
        : 0;


// ============================================================
// TÉCNICOS (solo para el filtro del administrador)
// ============================================================

$tecnicosDisponibles =
    $esAdministrador
        ? obtenerTecnicos($conexion)
        : [];


// ============================================================
// ARMAR CONDICIONES
// ============================================================

$condiciones = [];
$parametros = [];

if (!$esAdministrador) {

    $condiciones[] = 'i.id_tecnico = ?';
    $parametros[] = $idTecnico;

} elseif ($filtroTecnico > 0) {

    $condiciones[] = 'i.id_tecnico = ?';
    $parametros[] = $filtroTecnico;
}


if ($busqueda !== '') {

    $condiciones[] =
        '(s.titulo LIKE ? OR i.diagnostico LIKE ? OR i.trabajo_realizado LIKE ? OR s.id_solicitud = ?)';

    $comodin = '%' . $busqueda . '%';

    // Soporta buscar por número de ticket, con o sin el
    // prefijo "SJ-" (ej: "SJ-000001" o simplemente "1").
    $soloDigitos =
        preg_replace('/\D/', '', $busqueda);

    $parametros[] = $comodin;
    $parametros[] = $comodin;
    $parametros[] = $comodin;
    $parametros[] =
        $soloDigitos !== ''
            ? (int)$soloDigitos
            : 0;
}


if ($fechaDesde !== '') {

    $condiciones[] = 'i.fecha_intervencion >= ?';
    $parametros[] = $fechaDesde . ' 00:00:00';
}


if ($fechaHasta !== '') {

    $condiciones[] = 'i.fecha_intervencion <= ?';
    $parametros[] = $fechaHasta . ' 23:59:59';
}


if ($soloPendientes) {

    $condiciones[] = 'i.pendiente = 1';
}


$whereSql =
    empty($condiciones)
        ? ''
        : 'WHERE ' . implode(' AND ', $condiciones);


// ============================================================
// PAGINACIÓN
// ============================================================

$porPagina = 10;

$pagina =
    max(
        1,
        (int)($_GET['pagina'] ?? 1)
    );

$offset = ($pagina - 1) * $porPagina;


// ============================================================
// TOTAL
// ============================================================

$stmtTotal = $conexion->prepare("
    SELECT COUNT(*)

    FROM intervenciones i

    INNER JOIN solicitudes s
        ON i.id_solicitud = s.id_solicitud

    {$whereSql}
");

$stmtTotal->execute($parametros);

$totalIntervenciones = (int)$stmtTotal->fetchColumn();

$totalPaginas =
    (int)ceil(
        $totalIntervenciones / $porPagina
    );


// ============================================================
// LISTADO
// ============================================================

$sql = "
    SELECT

        i.id_intervencion,
        i.id_solicitud,
        i.id_tecnico,
        i.diagnostico,
        i.trabajo_realizado,
        i.materiales,
        i.observaciones,
        i.pendiente,
        i.motivo_pendiente,
        i.tipo_pendiente,
        i.fecha_inicio,
        i.fecha_fin,
        i.fecha_intervencion,

        s.titulo,
        s.tipo,
        s.estado,

        sec.nombre AS sector,

        CONCAT(u.nombre, ' ', u.apellido) AS tecnico,

        (
            SELECT COUNT(*)
            FROM intervencion_imagenes ii
            WHERE ii.id_intervencion = i.id_intervencion
        ) AS imagenes

    FROM intervenciones i

    INNER JOIN solicitudes s
        ON i.id_solicitud = s.id_solicitud

    LEFT JOIN sectores sec
        ON s.id_sector = sec.id_sector

    INNER JOIN usuarios u
        ON i.id_tecnico = u.id_usuario

    {$whereSql}

    ORDER BY
        i.fecha_intervencion DESC

    LIMIT {$porPagina}
    OFFSET {$offset}
";

$stmtIntervenciones = $conexion->prepare($sql);
$stmtIntervenciones->execute($parametros);

$intervenciones =
    $stmtIntervenciones->fetchAll(
        PDO::FETCH_ASSOC
    );


// ============================================================
// URL DE PAGINACIÓN / FILTROS
// ============================================================

function urlIntervenciones(array $overrides = []): string
{
    $query = array_merge($_GET, $overrides);

    return url(
        'tecnico/intervenciones.php?'
        . http_build_query($query)
    );
}


// ============================================================
// HEADER
// ============================================================

require_once __DIR__ . '/../includes/header.php';

?>


<style>

.intv-wrapper {

    max-width: 1200px;
    margin: 0 auto;
    padding: 5px 12px 45px;

}


.intv-hero {

    position: relative;
    overflow: hidden;

    background: linear-gradient(135deg, #760000, #B12626);
    color: #FFFFFF;

    border-radius: 21px;
    padding: 29px;
    margin-bottom: 24px;

    box-shadow: 0 9px 28px rgba(118,0,0,.16);

}


.intv-hero::after {

    content: "";
    position: absolute;
    right: -100px;
    top: -130px;
    width: 270px;
    height: 270px;
    border-radius: 50%;
    background: rgba(255,255,255,.06);

}


.intv-hero h1 {

    position: relative;
    z-index: 2;
    margin: 0 0 7px;
    font-size: 28px;
    font-weight: 800;

}


.intv-hero p {

    position: relative;
    z-index: 2;
    margin: 0;
    color: rgba(255,255,255,.78);

}


.btn-volver {

    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 10px 17px;
    border-radius: 10px;
    background: #FFFFFF;
    color: #760000;
    font-weight: 700;
    text-decoration: none;
    position: relative;
    z-index: 2;

}


.btn-volver:hover {

    color: #B12626;
    background: #F4F4F4;

}


.intv-filtros {

    background: #FFFFFF;
    border: 1px solid #ECECEC;
    border-radius: 14px;
    padding: 18px 20px;
    margin-bottom: 20px;
    box-shadow: 0 4px 15px rgba(0,0,0,.04);

}


.intv-filtros label {

    font-size: 12px;
    font-weight: 700;
    color: #666666;
    margin-bottom: 4px;

}


.intv-card {

    background: #FFFFFF;
    border: 1px solid #ECECEC;
    border-left: 4px solid #B12626;
    border-radius: 14px;
    padding: 18px 20px;
    margin-bottom: 16px;
    box-shadow: 0 4px 15px rgba(0,0,0,.04);

}


.intv-card.pendiente {

    border-left-color: #E0A800;

}


.intv-top {

    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    gap: 10px;

}


.intv-ticket {

    color: #333333;
    font-weight: 800;
    font-size: 15px;
    text-decoration: none;

}


.intv-ticket:hover {

    color: #B12626;

}


.intv-meta {

    color: #888888;
    font-size: 12px;
    margin-top: 5px;

}


.intv-texto {

    margin-top: 10px;
    padding: 10px 12px;
    border-radius: 9px;
    background: #FAFAFA;
    color: #555555;
    font-size: 13px;

}


.empty {

    padding: 45px 20px;
    color: #888888;
    text-align: center;

}


.empty i {

    display: block;
    font-size: 40px;
    color: #D0D0D0;
    margin-bottom: 8px;

}


.intv-paginacion {

    display: flex;
    justify-content: center;
    gap: 8px;
    margin-top: 20px;

}

</style>


<div class="intv-wrapper">


    <section class="intv-hero">

        <div class="row align-items-center">

            <div class="col-lg-8">

                <h1>
                    <i class="bi bi-clock-history me-1"></i>
                    <?= $esAdministrador
                        ? 'Historial de intervenciones'
                        : 'Mis intervenciones'
                    ?>
                </h1>

                <p>
                    <?= $esAdministrador
                        ? 'Todas las intervenciones registradas por los técnicos.'
                        : 'Todo lo que registraste al intervenir tus solicitudes.'
                    ?>
                </p>

            </div>

            <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">

                <a
                    href="<?= url('tecnico/dashboard.php') ?>"
                    class="btn-volver"
                >
                    <i class="bi bi-arrow-left"></i>
                    Volver al panel
                </a>

            </div>

        </div>

    </section>


    <form method="get" class="intv-filtros row g-3">

        <div class="col-md-4">

            <label>Buscar (ticket, título, diagnóstico)</label>

            <input
                type="text"
                name="buscar"
                class="form-control"
                value="<?= e($busqueda) ?>"
                placeholder="Ej: SJ-000001, proyector..."
            >

        </div>


        <div class="col-md-2">

            <label>Desde</label>

            <input
                type="date"
                name="desde"
                class="form-control"
                value="<?= e($fechaDesde) ?>"
            >

        </div>


        <div class="col-md-2">

            <label>Hasta</label>

            <input
                type="date"
                name="hasta"
                class="form-control"
                value="<?= e($fechaHasta) ?>"
            >

        </div>


        <?php if ($esAdministrador): ?>

            <div class="col-md-3">

                <label>Técnico</label>

                <select name="tecnico" class="form-select">

                    <option value="0">Todos</option>

                    <?php foreach ($tecnicosDisponibles as $t): ?>

                        <option
                            value="<?= (int)$t['id_usuario'] ?>"
                            <?= $filtroTecnico === (int)$t['id_usuario']
                                ? 'selected'
                                : ''
                            ?>
                        >
                            <?= e($t['nombre'] . ' ' . $t['apellido']) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

        <?php endif; ?>


        <div class="col-md-1 d-flex align-items-end">

            <button type="submit" class="btn btn-sanjo w-100">

                <i class="bi bi-filter"></i>

            </button>

        </div>


        <div class="col-12">

            <div class="form-check">

                <input
                    type="checkbox"
                    name="pendientes"
                    value="1"
                    id="soloPendientes"
                    class="form-check-input"
                    <?= $soloPendientes ? 'checked' : '' ?>
                    onchange="this.form.submit()"
                >

                <label
                    class="form-check-label"
                    for="soloPendientes"
                    style="font-size:13px;"
                >
                    Mostrar solo las que quedaron pendientes
                </label>

            </div>

        </div>


        <?php if (
            $busqueda !== ''
            || $fechaDesde !== ''
            || $fechaHasta !== ''
            || $soloPendientes
            || $filtroTecnico > 0
        ): ?>

            <div class="col-12">

                <a
                    href="<?= url('tecnico/intervenciones.php') ?>"
                    class="small"
                >
                    <i class="bi bi-x-circle me-1"></i>
                    Limpiar filtros
                </a>

            </div>

        <?php endif; ?>

    </form>


    <?php if (empty($intervenciones)): ?>

        <div class="empty">

            <i class="bi bi-tools"></i>

            No hay intervenciones que coincidan con los filtros.

        </div>

    <?php else: ?>

        <?php foreach ($intervenciones as $intervencion): ?>

            <div
                class="intv-card <?= (int)$intervencion['pendiente'] === 1 ? 'pendiente' : '' ?>"
            >

                <div class="intv-top">

                    <div>

                        <a
                            href="<?= url(
                                'ver_solicitud.php?id='
                                . (int)$intervencion['id_solicitud']
                            ) ?>"
                            class="intv-ticket"
                        >
                            <?= e(
                                numeroTicket(
                                    (int)$intervencion['id_solicitud']
                                )
                            ) ?>
                            -
                            <?= e($intervencion['titulo']) ?>
                        </a>

                        <div class="intv-meta">

                            <i class="bi bi-calendar3 me-1"></i>
                            <?= e(fechaArgentina($intervencion['fecha_intervencion'])) ?>

                            <?php if ($esAdministrador): ?>

                                · <i class="bi bi-person-gear me-1"></i>
                                <?= e($intervencion['tecnico']) ?>

                            <?php endif; ?>

                            <?php if (!empty($intervencion['sector'])): ?>

                                · <i class="bi bi-geo-alt me-1"></i>
                                <?= e($intervencion['sector']) ?>

                            <?php endif; ?>

                            <?php if ((int)$intervencion['imagenes'] > 0): ?>

                                · <i class="bi bi-images me-1"></i>
                                <?= (int)$intervencion['imagenes'] ?> fotos

                            <?php endif; ?>

                        </div>

                    </div>


                    <div class="text-end">

                        <span class="badge <?= e(claseEstado($intervencion['estado'])) ?>">
                            <?= e($intervencion['estado']) ?>
                        </span>

                        <?php if (!empty($intervencion['tipo_pendiente'])): ?>

                            <span
                                class="badge <?= e(
                                    claseTipoPendiente($intervencion['tipo_pendiente'])
                                ) ?>"
                            >

                                <i class="bi <?= e(
                                    iconoTipoPendiente($intervencion['tipo_pendiente'])
                                ) ?> me-1"></i>

                                <?= e($intervencion['tipo_pendiente']) ?>

                            </span>

                        <?php endif; ?>

                    </div>

                </div>


                <?php if (!empty($intervencion['trabajo_realizado'])): ?>

                    <div class="intv-texto">
                        <?= e($intervencion['trabajo_realizado']) ?>
                    </div>

                <?php elseif (!empty($intervencion['diagnostico'])): ?>

                    <div class="intv-texto">
                        <?= e($intervencion['diagnostico']) ?>
                    </div>

                <?php endif; ?>


                <?php if (!empty($intervencion['motivo_pendiente'])): ?>

                    <div class="intv-texto">
                        <i class="bi bi-hourglass-split me-1"></i>
                        <?= e($intervencion['motivo_pendiente']) ?>
                    </div>

                <?php endif; ?>

            </div>

        <?php endforeach; ?>


        <?php if ($totalPaginas > 1): ?>

            <nav class="intv-paginacion">

                <?php for ($p = 1; $p <= $totalPaginas; $p++): ?>

                    <a
                        href="<?= urlIntervenciones(['pagina' => $p]) ?>"
                        class="btn btn-sm <?= $p === $pagina ? 'btn-sanjo' : 'btn-outline-secondary' ?>"
                    >
                        <?= $p ?>
                    </a>

                <?php endfor; ?>

            </nav>

        <?php endif; ?>

    <?php endif; ?>


</div>


<?php

require_once __DIR__ . '/../includes/footer.php';

?>
