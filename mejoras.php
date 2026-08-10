<?php
// ============================================================
// COLEGIO SAN JOSÉ
// Sistema de Gestión Técnica
// Archivo: /tecnica/mejoras.php
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/funciones.php';
require_once __DIR__ . '/includes/auth.php';

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


// ============================================================
// ESTADOS PERMITIDOS
// ============================================================

$estadosPermitidos = [

    'Propuesta',
    'En evaluacion',
    'Pendiente autorizacion',
    'Aprobada',
    'En ejecucion',
    'Realizada',
    'Rechazada'

];


$tiposPermitidos = [

    'Informatica',
    'Mantenimiento'

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


// ============================================================
// CONSULTA
// ============================================================

$condiciones = [];

$parametros = [];


if ($buscar !== '') {

    $condiciones[] = "
        (
            m.titulo LIKE ?
            OR
            m.descripcion LIKE ?
            OR
            m.justificacion LIKE ?
            OR
            sec.nombre LIKE ?
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
}


if ($estado !== '') {

    $condiciones[] =
        'm.estado = ?';

    $parametros[] =
        $estado;
}


if ($tipo !== '') {

    $condiciones[] =
        'm.tipo = ?';

    $parametros[] =
        $tipo;
}


$where = '';

if (!empty($condiciones)) {

    $where =
        'WHERE '
        .
        implode(
            ' AND ',
            $condiciones
        );
}


// ============================================================
// OBTENER MEJORAS
// ============================================================

$sql = "
    SELECT

        m.*,

        CONCAT(
            u.nombre,
            ' ',
            u.apellido
        ) AS usuario,

        sec.nombre AS sector,

        (
            SELECT COUNT(*)

            FROM mejora_imagenes mi

            WHERE
                mi.id_mejora =
                m.id_mejora
        ) AS cantidad_imagenes,

        (
            SELECT COUNT(*)

            FROM materiales mat

            WHERE
                mat.id_mejora =
                m.id_mejora
        ) AS cantidad_materiales

    FROM mejoras m

    INNER JOIN usuarios u
        ON m.id_usuario = u.id_usuario

    LEFT JOIN sectores sec
        ON m.id_sector = sec.id_sector

    {$where}

    ORDER BY

        CASE m.estado

            WHEN 'Pendiente autorizacion'
                THEN 1

            WHEN 'En evaluacion'
                THEN 2

            WHEN 'Aprobada'
                THEN 3

            WHEN 'En ejecucion'
                THEN 4

            WHEN 'Propuesta'
                THEN 5

            WHEN 'Realizada'
                THEN 6

            WHEN 'Rechazada'
                THEN 7

            ELSE 8

        END,

        CASE m.prioridad

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

        m.fecha_creacion DESC
";


$stmt =
    $conexion->prepare(
        $sql
    );


$stmt->execute(
    $parametros
);


$mejoras =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


// ============================================================
// ESTADÍSTICAS
// ============================================================

$stmtStats =
    $conexion->query("
        SELECT

            COUNT(*) AS total,

            SUM(
                CASE
                    WHEN estado = 'Propuesta'
                    THEN 1
                    ELSE 0
                END
            ) AS propuestas,

            SUM(
                CASE
                    WHEN estado = 'Pendiente autorizacion'
                    THEN 1
                    ELSE 0
                END
            ) AS pendientes,

            SUM(
                CASE
                    WHEN estado = 'En ejecucion'
                    THEN 1
                    ELSE 0
                END
            ) AS ejecucion,

            SUM(
                CASE
                    WHEN estado = 'Realizada'
                    THEN 1
                    ELSE 0
                END
            ) AS realizadas

        FROM mejoras
    ");


$statsBD =
    $stmtStats->fetch(
        PDO::FETCH_ASSOC
    );


$estadisticas = [

    'total' =>
        (int)(
            $statsBD['total']
            ?? 0
        ),

    'propuestas' =>
        (int)(
            $statsBD['propuestas']
            ?? 0
        ),

    'pendientes' =>
        (int)(
            $statsBD['pendientes']
            ?? 0
        ),

    'ejecucion' =>
        (int)(
            $statsBD['ejecucion']
            ?? 0
        ),

    'realizadas' =>
        (int)(
            $statsBD['realizadas']
            ?? 0
        )

];


// ============================================================
// FLASH
// ============================================================

$flash =
    obtenerFlash();


// ============================================================
// FUNCIÓN DE CLASE DE ESTADO DE MEJORA
// ============================================================

function claseEstadoMejora(
    string $estado
): string {

    return match ($estado) {

        'Propuesta'
            => 'bg-secondary',

        'En evaluacion'
            => 'bg-info text-dark',

        'Pendiente autorizacion'
            => 'bg-warning text-dark',

        'Aprobada'
            => 'bg-primary',

        'En ejecucion'
            => 'bg-danger',

        'Realizada'
            => 'bg-success',

        'Rechazada'
            => 'bg-dark',

        default
            => 'bg-secondary'
    };
}


// ============================================================
// TEXTO DE ESTADO
// ============================================================

function nombreEstadoMejora(
    string $estado
): string {

    return match ($estado) {

        'En evaluacion'
            => 'En evaluación',

        'Pendiente autorizacion'
            => 'Pendiente de autorización',

        'En ejecucion'
            => 'En ejecución',

        default
            => $estado
    };
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

}


/* ============================================================
   CONTENEDOR
============================================================ */

.mejoras-wrapper {

    max-width: 1450px;

    margin: 0 auto;

    padding:
        5px 12px
        45px;

}


/* ============================================================
   HERO
============================================================ */

.mejoras-hero {

    position: relative;

    overflow: hidden;

    background:
        linear-gradient(
            135deg,
            #760000,
            #B12626
        );

    color: #FFFFFF;

    border-radius: 22px;

    padding: 30px;

    margin-bottom: 23px;

    box-shadow:
        0 9px 28px
        rgba(118,0,0,.16);

}


.mejoras-hero::after {

    content: "";

    position: absolute;

    width: 280px;

    height: 280px;

    border-radius: 50%;

    background:
        rgba(255,255,255,.06);

    right: -110px;

    top: -130px;

}


.hero-content {

    position: relative;

    z-index: 2;

}


.mejoras-hero h1 {

    margin: 0 0 8px;

    font-size: 28px;

    font-weight: 800;

}


.mejoras-hero p {

    margin: 0;

    max-width: 720px;

    color:
        rgba(255,255,255,.78);

}


.btn-nueva-mejora {

    position: relative;

    z-index: 2;

    display: inline-flex;

    align-items: center;

    gap: 7px;

    padding:
        11px 18px;

    border-radius: 10px;

    background: #FFFFFF;

    color: #760000;

    text-decoration: none;

    font-weight: 700;

}


.btn-nueva-mejora:hover {

    background: #F4F4F4;

    color: #B12626;

}


/* ============================================================
   ESTADÍSTICAS
============================================================ */

.stat-card {

    height: 100%;

    padding: 18px;

    background: #FFFFFF;

    border:
        1px solid #ECECEC;

    border-radius: 16px;

    box-shadow:
        0 4px 16px
        rgba(0,0,0,.04);

}


.stat-icon {

    width: 43px;

    height: 43px;

    border-radius: 11px;

    display: flex;

    align-items: center;

    justify-content: center;

    margin-bottom: 10px;

    font-size: 19px;

}


.stat-number {

    font-size: 28px;

    font-weight: 800;

    color: #333333;

}


.stat-label {

    color: #777777;

    font-size: 12px;

    font-weight: 600;

}


.icon-total {

    background: #F2E4E4;

    color: #760000;

}


.icon-propuesta {

    background: #E7F0FF;

    color: #0D6EFD;

}


.icon-pendiente {

    background: #FFF3CD;

    color: #8A6900;

}


.icon-ejecucion {

    background: #FFE4E4;

    color: #B12626;

}


.icon-realizada {

    background: #DFF4E7;

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

    margin:
        24px 0;

    box-shadow:
        0 5px 18px
        rgba(0,0,0,.04);

}


.form-control,
.form-select {

    min-height: 45px;

    border-radius: 9px;

}


.form-control:focus,
.form-select:focus {

    border-color: #B12626;

    box-shadow:
        0 0 0 .2rem
        rgba(177,38,38,.08);

}


.btn-filtrar {

    min-height: 45px;

    background: #B12626;

    border: none;

    color: #FFFFFF;

    border-radius: 9px;

    font-weight: 700;

}


.btn-filtrar:hover {

    background: #760000;

    color: #FFFFFF;

}


.btn-limpiar {

    min-height: 45px;

    border:
        1px solid #D8D8D8;

    background: #FFFFFF;

    color: #555555;

    border-radius: 9px;

}


/* ============================================================
   MEJORAS
============================================================ */

.mejora-card {

    background: #FFFFFF;

    border:
        1px solid #ECECEC;

    border-radius: 18px;

    overflow: hidden;

    box-shadow:
        0 5px 19px
        rgba(0,0,0,.05);

    height: 100%;

    transition:
        all .2s ease;

}


.mejora-card:hover {

    transform:
        translateY(-3px);

    box-shadow:
        0 10px 27px
        rgba(0,0,0,.08);

}


.mejora-top {

    padding: 20px;

    border-bottom:
        1px solid #EEEEEE;

}


.mejora-numero {

    color: #9A9A9A;

    font-size: 11px;

    font-weight: 700;

    margin-bottom: 5px;

}


.mejora-titulo {

    color: #760000;

    font-size: 18px;

    font-weight: 800;

    margin-bottom: 9px;

}


.mejora-descripcion {

    color: #666666;

    font-size: 13px;

    line-height: 1.6;

    display: -webkit-box;

    -webkit-line-clamp: 3;

    -webkit-box-orient: vertical;

    overflow: hidden;

}


.mejora-body {

    padding: 17px 20px;

}


.mejora-meta {

    display: flex;

    flex-wrap: wrap;

    gap:
        7px 14px;

    color: #777777;

    font-size: 11px;

    margin-bottom: 14px;

}


.mejora-meta i {

    color: #B12626;

}


.mejora-info {

    display: grid;

    grid-template-columns:
        repeat(2, 1fr);

    gap: 12px;

    margin-top: 12px;

}


.info-box {

    padding: 10px;

    border-radius: 10px;

    background: #F8F8F8;

}


.info-label {

    color: #999999;

    font-size: 9px;

    text-transform: uppercase;

    font-weight: 700;

    margin-bottom: 3px;

}


.info-value {

    color: #444444;

    font-size: 12px;

    font-weight: 700;

}


.mejora-footer {

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 12px;

    padding:
        14px 20px;

    background: #FAFAFA;

    border-top:
        1px solid #EEEEEE;

}


.estado-badge {

    padding:
        6px 9px;

    border-radius: 25px;

    font-size: 10px;

    font-weight: 700;

}


.btn-ver {

    display: inline-flex;

    align-items: center;

    gap: 6px;

    color: #760000;

    text-decoration: none;

    font-size: 12px;

    font-weight: 800;

}


.btn-ver:hover {

    color: #B12626;

}


/* ============================================================
   PENDIENTE
============================================================ */

.motivo-pendiente {

    margin-top: 13px;

    padding: 10px 12px;

    border-radius: 9px;

    background: #FFF7D9;

    border-left:
        3px solid #D8A300;

    color: #665200;

    font-size: 11px;

}


/* ============================================================
   VACÍO
============================================================ */

.empty-state {

    background: #FFFFFF;

    border:
        1px solid #ECECEC;

    border-radius: 18px;

    padding:
        60px 20px;

    text-align: center;

    color: #888888;

}


.empty-state i {

    display: block;

    font-size: 50px;

    color: #D0D0D0;

    margin-bottom: 10px;

}


.empty-state h5 {

    color: #555555;

    font-weight: 800;

}


/* ============================================================
   INFO
============================================================ */

.info-mejoras {

    background: #FFF7F7;

    border-left:
        4px solid #B12626;

    border-radius: 10px;

    padding: 15px;

    margin-top: 25px;

    color: #606060;

    font-size: 13px;

}


/* ============================================================
   RESPONSIVE
============================================================ */

@media
(max-width: 767px) {

    .mejoras-hero {

        padding: 23px 20px;

    }


    .mejoras-hero h1 {

        font-size: 23px;

    }


    .hero-action {

        margin-top: 20px;

    }


    .btn-nueva-mejora {

        width: 100%;

        justify-content: center;

    }


    .mejora-info {

        grid-template-columns: 1fr;

    }

}

</style>


<div class="mejoras-wrapper">


    <!-- =====================================================
         HERO
    ====================================================== -->

    <section class="mejoras-hero">

        <div class="row align-items-center">

            <div class="col-lg-8">

                <div class="hero-content">

                    <h1>

                        <i class="bi bi-lightbulb me-1"></i>

                        Propuestas de mejora

                    </h1>

                    <p>

                        Espacio para registrar necesidades,
                        mejoras de infraestructura,
                        equipamiento tecnológico
                        y propuestas que permitan optimizar
                        los espacios del colegio.

                    </p>

                </div>

            </div>


            <div
                class="col-lg-4
                       text-lg-end
                       hero-action"
            >

                <a
                    href="<?= url(
                        'nueva_mejora.php'
                    ) ?>"
                    class="btn-nueva-mejora"
                >

                    <i class="bi bi-plus-circle"></i>

                    Nueva propuesta

                </a>

            </div>

        </div>

    </section>



    <!-- =====================================================
         FLASH
    ====================================================== -->

    <?php if ($flash): ?>

        <div
            class="alert alert-<?= e(
                $flash['tipo']
                === 'success'
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


        <div class="col-6 col-lg">

            <div class="stat-card">

                <div class="stat-icon icon-total">

                    <i class="bi bi-lightbulb"></i>

                </div>

                <div class="stat-number">

                    <?= $estadisticas['total'] ?>

                </div>

                <div class="stat-label">

                    Total de mejoras

                </div>

            </div>

        </div>


        <div class="col-6 col-lg">

            <div class="stat-card">

                <div class="stat-icon icon-propuesta">

                    <i class="bi bi-pencil-square"></i>

                </div>

                <div class="stat-number">

                    <?= $estadisticas['propuestas'] ?>

                </div>

                <div class="stat-label">

                    Propuestas

                </div>

            </div>

        </div>


        <div class="col-6 col-lg">

            <div class="stat-card">

                <div class="stat-icon icon-pendiente">

                    <i class="bi bi-hourglass-split"></i>

                </div>

                <div class="stat-number">

                    <?= $estadisticas['pendientes'] ?>

                </div>

                <div class="stat-label">

                    Pend. autorización

                </div>

            </div>

        </div>


        <div class="col-6 col-lg">

            <div class="stat-card">

                <div class="stat-icon icon-ejecucion">

                    <i class="bi bi-tools"></i>

                </div>

                <div class="stat-number">

                    <?= $estadisticas['ejecucion'] ?>

                </div>

                <div class="stat-label">

                    En ejecución

                </div>

            </div>

        </div>


        <div class="col-6 col-lg">

            <div class="stat-card">

                <div class="stat-icon icon-realizada">

                    <i class="bi bi-check-circle"></i>

                </div>

                <div class="stat-number">

                    <?= $estadisticas['realizadas'] ?>

                </div>

                <div class="stat-label">

                    Realizadas

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
                'mejoras.php'
            ) ?>"
        >

            <div class="row g-3">


                <div class="col-lg-5">

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
                            name="buscar"
                            id="buscar"
                            class="form-control"
                            value="<?= e($buscar) ?>"
                            placeholder="Título, descripción, sector..."
                        >

                    </div>

                </div>



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



                <div class="col-md-5 col-lg-3">

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
                                <?= $estado
                                    === $estadoOpcion
                                    ? 'selected'
                                    : ''
                                ?>
                            >

                                <?= e(
                                    nombreEstadoMejora(
                                        $estadoOpcion
                                    )
                                ) ?>

                            </option>

                        <?php endforeach; ?>


                    </select>

                </div>



                <div
                    class="col-md-3 col-lg-2
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
                            'mejoras.php'
                        ) ?>"
                        class="btn btn-limpiar"
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

    <?php if (
        empty(
            $mejoras
        )
    ): ?>


        <div class="empty-state">

            <i class="bi bi-lightbulb"></i>

            <h5>

                No hay propuestas para mostrar

            </h5>

            <p>

                Podés registrar una necesidad
                o propuesta de mejora desde
                el botón Nueva propuesta.

            </p>


            <a
                href="<?= url(
                    'nueva_mejora.php'
                ) ?>"
                class="btn btn-sanjo mt-2"
            >

                <i class="bi bi-plus-circle me-1"></i>

                Nueva propuesta

            </a>

        </div>


    <?php else: ?>


        <div class="row g-4">


            <?php foreach (
                $mejoras
                as $mejora
            ): ?>


                <div
                    class="col-md-6
                           col-xl-4"
                >

                    <article class="mejora-card">


                        <!-- ===============================
                             CABECERA
                        ================================ -->

                        <div class="mejora-top">


                            <div class="mejora-numero">

                                MEJORA
                                #<?= str_pad(
                                    (string)$mejora[
                                        'id_mejora'
                                    ],
                                    5,
                                    '0',
                                    STR_PAD_LEFT
                                ) ?>

                            </div>


                            <div class="mejora-titulo">

                                <?= e(
                                    $mejora['titulo']
                                ) ?>

                            </div>


                            <div class="mejora-descripcion">

                                <?= e(
                                    $mejora[
                                        'descripcion'
                                    ]
                                ) ?>

                            </div>


                            <?php if (
                                !empty(
                                    $mejora[
                                        'motivo_pendiente'
                                    ]
                                )
                            ): ?>

                                <div class="motivo-pendiente">

                                    <strong>

                                        <i class="bi bi-hourglass-split"></i>

                                        Pendiente:

                                    </strong>

                                    <?= e(
                                        $mejora[
                                            'motivo_pendiente'
                                        ]
                                    ) ?>

                                </div>

                            <?php endif; ?>


                        </div>



                        <!-- ===============================
                             DATOS
                        ================================ -->

                        <div class="mejora-body">


                            <div class="mejora-meta">


                                <span>

                                    <i class="<?= e(
                                        iconoTipo(
                                            $mejora['tipo']
                                        )
                                    ) ?>"></i>

                                    <?= e(
                                        nombreTipo(
                                            $mejora['tipo']
                                        )
                                    ) ?>

                                </span>


                                <?php if (
                                    !empty(
                                        $mejora['sector']
                                    )
                                ): ?>

                                    <span>

                                        <i class="bi bi-geo-alt"></i>

                                        <?= e(
                                            $mejora['sector']
                                        ) ?>

                                    </span>

                                <?php endif; ?>


                                <span>

                                    <i class="bi bi-person"></i>

                                    <?= e(
                                        $mejora['usuario']
                                    ) ?>

                                </span>


                                <span>

                                    <i class="bi bi-calendar3"></i>

                                    <?= e(
                                        fechaCorta(
                                            $mejora[
                                                'fecha_creacion'
                                            ]
                                        )
                                    ) ?>

                                </span>


                            </div>



                            <span
                                class="badge <?= e(
                                    clasePrioridad(
                                        $mejora[
                                            'prioridad'
                                        ]
                                    )
                                ) ?>"
                            >

                                Prioridad:
                                <?= e(
                                    $mejora[
                                        'prioridad'
                                    ]
                                ) ?>

                            </span>



                            <div class="mejora-info">


                                <div class="info-box">

                                    <div class="info-label">

                                        Cantidad

                                    </div>

                                    <div class="info-value">

                                        <?= !empty(
                                            $mejora[
                                                'cantidad'
                                            ]
                                        )
                                            ? (int)$mejora[
                                                'cantidad'
                                            ]
                                            : '-'
                                        ?>

                                    </div>

                                </div>



                                <div class="info-box">

                                    <div class="info-label">

                                        Costo estimado

                                    </div>

                                    <div class="info-value">

                                        <?= !empty(
                                            $mejora[
                                                'costo_estimado'
                                            ]
                                        )
                                            ? e(
                                                formatoDinero(
                                                    $mejora[
                                                        'costo_estimado'
                                                    ]
                                                )
                                            )
                                            : '-'
                                        ?>

                                    </div>

                                </div>



                                <div class="info-box">

                                    <div class="info-label">

                                        Imágenes

                                    </div>

                                    <div class="info-value">

                                        <i class="bi bi-images me-1"></i>

                                        <?= (int)$mejora[
                                            'cantidad_imagenes'
                                        ] ?>

                                    </div>

                                </div>



                                <div class="info-box">

                                    <div class="info-label">

                                        Materiales

                                    </div>

                                    <div class="info-value">

                                        <i class="bi bi-box-seam me-1"></i>

                                        <?= (int)$mejora[
                                            'cantidad_materiales'
                                        ] ?>

                                    </div>

                                </div>


                            </div>


                        </div>



                        <!-- ===============================
                             FOOTER
                        ================================ -->

                        <div class="mejora-footer">


                            <span
                                class="badge <?= e(
                                    claseEstadoMejora(
                                        $mejora[
                                            'estado'
                                        ]
                                    )
                                ) ?> estado-badge"
                            >

                                <?= e(
                                    nombreEstadoMejora(
                                        $mejora[
                                            'estado'
                                        ]
                                    )
                                ) ?>

                            </span>


                            <a
                                href="<?= url(
                                    'ver_mejora.php?id='
                                    .
                                    (int)$mejora[
                                        'id_mejora'
                                    ]
                                ) ?>"
                                class="btn-ver"
                            >

                                Ver detalle

                                <i class="bi bi-arrow-right"></i>

                            </a>


                        </div>


                    </article>

                </div>


            <?php endforeach; ?>


        </div>


    <?php endif; ?>



    <!-- =====================================================
         INFORMACIÓN
    ====================================================== -->

    <div class="info-mejoras">

        <i class="bi bi-info-circle me-1"></i>

        <strong>
            ¿Qué debería registrarse como mejora?
        </strong>

        Una propuesta de mejora no necesariamente
        corresponde a una falla. Puede tratarse,
        por ejemplo, de reemplazar discos HDD por SSD,
        mejorar la cobertura WiFi, incorporar un proyector,
        renovar mobiliario, mejorar iluminación
        o realizar una modificación preventiva
        de infraestructura.

    </div>


</div>


<?php

require_once __DIR__
    . '/includes/footer.php';

?>