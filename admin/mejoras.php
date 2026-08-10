<?php
// ============================================================
// COLEGIO SAN JOSÉ
// Sistema de Gestión Técnica
// Archivo: /tecnica/admin/mejoras.php
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/funciones.php';
require_once __DIR__ . '/../includes/auth.php';


// ============================================================
// SOLO ADMINISTRADORES
// ============================================================

requerirAdministrador();


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
// CONFIGURACIÓN
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


$prioridadesPermitidas = [
    'Baja',
    'Normal',
    'Alta',
    'Urgente'
];


// ============================================================
// FUNCIONES LOCALES
// ============================================================

function nombreEstadoAdminMejora(
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


function claseEstadoAdminMejora(
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


function redirigirAdminMejoras(
    int $idMejora = 0
): never {

    $destino =
        'admin/mejoras.php';

    if ($idMejora > 0) {

        $destino .=
            '?editar=' .
            $idMejora;
    }

    header(
        'Location: ' .
        url($destino)
    );

    exit;
}


// ============================================================
// PROCESAR ACCIONES
// ============================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ========================================================
    // CSRF
    // ========================================================

    if (
        !validarCsrf(
            $_POST['csrf_token']
            ?? ''
        )
    ) {

        flash(
            'error',
            'La sesión del formulario expiró. Intentá nuevamente.'
        );

        redirigirAdminMejoras();
    }


    $accion =
        limpiarTexto(
            $_POST['accion']
            ?? ''
        );


    // ========================================================
    // ACTUALIZAR MEJORA
    // ========================================================

    if ($accion === 'actualizar') {

        $idMejora =
            (int)(
                $_POST['id_mejora']
                ?? 0
            );


        $estadoNuevo =
            limpiarTexto(
                $_POST['estado']
                ?? ''
            );


        $prioridad =
            limpiarTexto(
                $_POST['prioridad']
                ?? ''
            );


        $motivoPendiente =
            limpiarTexto(
                $_POST['motivo_pendiente']
                ?? ''
            );


        $observacionesAdmin =
            limpiarTexto(
                $_POST['observaciones_admin']
                ?? ''
            );


        $costoTexto =
            trim(
                $_POST['costo_estimado']
                ?? ''
            );


        // Permitir 150000,00 o 150000.00

        $costoTexto =
            str_replace(
                [
                    ' ',
                    '$'
                ],
                '',
                $costoTexto
            );


        if (
            str_contains(
                $costoTexto,
                ','
            )
        ) {

            $costoTexto =
                str_replace(
                    '.',
                    '',
                    $costoTexto
                );

            $costoTexto =
                str_replace(
                    ',',
                    '.',
                    $costoTexto
                );
        }


        $costoEstimado =
            $costoTexto !== ''
            ? (float)$costoTexto
            : null;


        // ====================================================
        // VALIDACIONES
        // ====================================================

        $errorActualizar = '';


        if ($idMejora <= 0) {

            $errorActualizar =
                'La propuesta seleccionada no es válida.';

        } elseif (
            !in_array(
                $estadoNuevo,
                $estadosPermitidos,
                true
            )
        ) {

            $errorActualizar =
                'El estado seleccionado no es válido.';

        } elseif (
            !in_array(
                $prioridad,
                $prioridadesPermitidas,
                true
            )
        ) {

            $errorActualizar =
                'La prioridad seleccionada no es válida.';

        } elseif (
            $costoEstimado !== null
            &&
            $costoEstimado < 0
        ) {

            $errorActualizar =
                'El costo estimado no puede ser negativo.';

        } elseif (
            mb_strlen(
                $motivoPendiente
            ) > 1000
        ) {

            $errorActualizar =
                'El motivo no puede superar los 1000 caracteres.';

        } elseif (
            mb_strlen(
                $observacionesAdmin
            ) > 2000
        ) {

            $errorActualizar =
                'Las observaciones no pueden superar los 2000 caracteres.';

        } elseif (
            in_array(
                $estadoNuevo,
                [
                    'Pendiente autorizacion',
                    'Rechazada'
                ],
                true
            )
            &&
            $motivoPendiente === ''
        ) {

            $errorActualizar =
                'Indicá el motivo para este estado.';
        }


        // ====================================================
        // COMPROBAR EXISTENCIA
        // ====================================================

        if (
            $errorActualizar === ''
        ) {

            $stmtExiste =
                $conexion->prepare("
                    SELECT
                        id_mejora,
                        id_usuario,
                        estado,
                        titulo

                    FROM mejoras

                    WHERE id_mejora = ?

                    LIMIT 1
                ");


            $stmtExiste->execute([
                $idMejora
            ]);


            $mejoraAnterior =
                $stmtExiste->fetch(
                    PDO::FETCH_ASSOC
                );


            if (!$mejoraAnterior) {

                $errorActualizar =
                    'La propuesta ya no existe.';
            }
        }


        // ====================================================
        // ACTUALIZAR
        // ====================================================

        if (
            $errorActualizar === ''
        ) {

            try {

                $conexion->beginTransaction();


                $stmtActualizar =
                    $conexion->prepare("
                        UPDATE mejoras

                        SET
                            estado = ?,
                            prioridad = ?,
                            costo_estimado = ?,
                            motivo_pendiente = ?,
                            observaciones_admin = ?,
                            fecha_actualizacion = NOW()

                        WHERE id_mejora = ?
                    ");


                $stmtActualizar->execute([

                    $estadoNuevo,

                    $prioridad,

                    $costoEstimado,

                    $motivoPendiente !== ''
                        ? $motivoPendiente
                        : null,

                    $observacionesAdmin !== ''
                        ? $observacionesAdmin
                        : null,

                    $idMejora

                ]);


                // ============================================
                // HISTORIAL DE LA MEJORA
                // ============================================

                $stmtHistorial =
                    $conexion->prepare("
                        INSERT INTO mejora_historial
                        (
                            id_mejora,
                            id_usuario,
                            estado_anterior,
                            estado_nuevo,
                            descripcion
                        )
                        VALUES
                        (
                            ?,
                            ?,
                            ?,
                            ?,
                            ?
                        )
                    ");


                $descripcionHistorial =
                    'Actualización administrativa de la propuesta.';


                if (
                    $mejoraAnterior['estado']
                    !==
                    $estadoNuevo
                ) {

                    $descripcionHistorial =
                        'La propuesta cambió de '
                        .
                        nombreEstadoAdminMejora(
                            $mejoraAnterior['estado']
                        )
                        .
                        ' a '
                        .
                        nombreEstadoAdminMejora(
                            $estadoNuevo
                        )
                        .
                        '.';
                }


                $stmtHistorial->execute([

                    $idMejora,

                    (int)usuarioId(),

                    $mejoraAnterior['estado'],

                    $estadoNuevo,

                    $descripcionHistorial

                ]);


                // ============================================
                // NOTIFICAR AL AUTOR
                // ============================================

                if (
                    (int)$mejoraAnterior['id_usuario']
                    !==
                    (int)usuarioId()
                ) {

                    crearNotificacion(

                        $conexion,

                        (int)$mejoraAnterior['id_usuario'],

                        'Actualización de propuesta',

                        'La propuesta "'
                        .
                        $mejoraAnterior['titulo']
                        .
                        '" cambió a '
                        .
                        nombreEstadoAdminMejora(
                            $estadoNuevo
                        )
                        .
                        '.',

                        'ver_mejora.php?id='
                        .
                        $idMejora

                    );
                }


                $conexion->commit();


                flash(
                    'success',
                    'La propuesta fue actualizada correctamente.'
                );


                redirigirAdminMejoras();


            } catch (Throwable $e) {

                if (
                    $conexion->inTransaction()
                ) {

                    $conexion->rollBack();
                }


                error_log(
                    'Error actualizando mejora: '
                    .
                    $e->getMessage()
                );


                flash(
                    'error',
                    'No se pudo actualizar la propuesta.'
                );


                redirigirAdminMejoras(
                    $idMejora
                );
            }
        }


        flash(
            'error',
            $errorActualizar
        );


        redirigirAdminMejoras(
            $idMejora
        );
    }
}


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


$tipoFiltro =
    trim(
        $_GET['tipo']
        ?? ''
    );


$prioridadFiltro =
    trim(
        $_GET['prioridad']
        ?? ''
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


// ============================================================
// CONDICIONES SQL
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
            u.nombre LIKE ?
            OR
            u.apellido LIKE ?
            OR
            sec.nombre LIKE ?
            OR
            CAST(m.id_mejora AS CHAR) LIKE ?
        )
    ";


    $buscarSql =
        '%' .
        $buscar .
        '%';


    for (
        $i = 0;
        $i < 7;
        $i++
    ) {

        $parametros[] =
            $buscarSql;
    }
}


if ($estadoFiltro !== '') {

    $condiciones[] =
        'm.estado = ?';

    $parametros[] =
        $estadoFiltro;
}


if ($tipoFiltro !== '') {

    $condiciones[] =
        'm.tipo = ?';

    $parametros[] =
        $tipoFiltro;
}


if ($prioridadFiltro !== '') {

    $condiciones[] =
        'm.prioridad = ?';

    $parametros[] =
        $prioridadFiltro;
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


$porPagina = 15;


// ============================================================
// CONTAR
// ============================================================

$sqlCantidad = "
    SELECT COUNT(*)

    FROM mejoras m

    INNER JOIN usuarios u
        ON m.id_usuario =
           u.id_usuario

    LEFT JOIN sectores sec
        ON m.id_sector =
           sec.id_sector

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
// ============================================================

$sql = "
    SELECT

        m.id_mejora,

        m.id_usuario,

        m.tipo,

        m.titulo,

        m.descripcion,

        m.justificacion,

        m.prioridad,

        m.estado,

        m.cantidad,

        m.costo_estimado,

        m.motivo_pendiente,

        m.observaciones_admin,

        m.fecha_creacion,

        m.fecha_actualizacion,

        CONCAT(
            u.nombre,
            ' ',
            u.apellido
        ) AS solicitante,

        u.correo,

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
        ON m.id_usuario =
           u.id_usuario

    LEFT JOIN sectores sec
        ON m.id_sector =
           sec.id_sector

    {$where}

    ORDER BY

        CASE m.estado

            WHEN 'Pendiente autorizacion'
                THEN 1

            WHEN 'En evaluacion'
                THEN 2

            WHEN 'Propuesta'
                THEN 3

            WHEN 'Aprobada'
                THEN 4

            WHEN 'En ejecucion'
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
                    WHEN estado = 'En evaluacion'
                    THEN 1
                    ELSE 0
                END
            ) AS evaluacion,

            SUM(
                CASE
                    WHEN estado = 'Pendiente autorizacion'
                    THEN 1
                    ELSE 0
                END
            ) AS pendientes,

            SUM(
                CASE
                    WHEN estado = 'Aprobada'
                    THEN 1
                    ELSE 0
                END
            ) AS aprobadas,

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


$stats =
    $stmtStats->fetch(
        PDO::FETCH_ASSOC
    );


// ============================================================
// MEJORA A EDITAR
// ============================================================

$idEditar =
    (int)(
        $_GET['editar']
        ?? 0
    );


$mejoraEditar = null;


if ($idEditar > 0) {

    $stmtEditar =
        $conexion->prepare("
            SELECT

                m.*,

                CONCAT(
                    u.nombre,
                    ' ',
                    u.apellido
                ) AS solicitante,

                sec.nombre AS sector

            FROM mejoras m

            INNER JOIN usuarios u
                ON m.id_usuario =
                   u.id_usuario

            LEFT JOIN sectores sec
                ON m.id_sector =
                   sec.id_sector

            WHERE
                m.id_mejora = ?

            LIMIT 1
        ");


    $stmtEditar->execute([
        $idEditar
    ]);


    $mejoraEditar =
        $stmtEditar->fetch(
            PDO::FETCH_ASSOC
        );
}


// ============================================================
// FLASH
// ============================================================

$flash =
    obtenerFlash();


// ============================================================
// URL PAGINACIÓN
// ============================================================

function urlPaginaAdminMejoras(
    int $pagina
): string {

    $query =
        $_GET;

    unset(
        $query['editar']
    );

    $query['pagina'] =
        $pagina;


    return url(
        'admin/mejoras.php?'
        .
        http_build_query(
            $query
        )
    );
}


// ============================================================
// HEADER
// ============================================================

require_once __DIR__
    . '/../includes/header.php';

?>


<style>

.admin-mejoras-wrapper {

    max-width: 1550px;

    margin: 0 auto;

    padding:
        5px 12px 45px;

}


/* ============================================================
   HERO
============================================================ */

.admin-mejoras-hero {

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

    padding: 29px;

    margin-bottom: 24px;

    box-shadow:
        0 9px 28px
        rgba(118,0,0,.16);

}


.admin-mejoras-hero::after {

    content: "";

    position: absolute;

    width: 280px;

    height: 280px;

    right: -110px;

    top: -130px;

    border-radius: 50%;

    background:
        rgba(255,255,255,.06);

}


.hero-content {

    position: relative;

    z-index: 2;

}


.admin-mejoras-hero h1 {

    margin:
        0 0 7px;

    font-size: 28px;

    font-weight: 800;

}


.admin-mejoras-hero p {

    margin: 0;

    color:
        rgba(255,255,255,.78);

}


.btn-panel {

    position: relative;

    z-index: 2;

    display: inline-flex;

    align-items: center;

    justify-content: center;

    gap: 7px;

    padding:
        10px 17px;

    background: #FFFFFF;

    color: #760000;

    border-radius: 10px;

    text-decoration: none;

    font-weight: 700;

}


.btn-panel:hover {

    color: #B12626;

    background: #F4F4F4;

}


/* ============================================================
   STATS
============================================================ */

.stat-card {

    height: 100%;

    background: #FFFFFF;

    border:
        1px solid #ECECEC;

    border-radius: 16px;

    padding: 17px;

    box-shadow:
        0 4px 15px
        rgba(0,0,0,.04);

}


.stat-icon {

    width: 42px;

    height: 42px;

    display: flex;

    justify-content: center;

    align-items: center;

    border-radius: 11px;

    margin-bottom: 9px;

    font-size: 18px;

}


.stat-number {

    color: #333333;

    font-size: 27px;

    line-height: 1;

    font-weight: 800;

}


.stat-label {

    margin-top: 5px;

    color: #777777;

    font-size: 11px;

    font-weight: 700;

}


.stat-total {

    color: #760000;

    background: #F2E5E5;

}


.stat-new {

    color: #0D6EFD;

    background: #E8F1FF;

}


.stat-eval {

    color: #087990;

    background: #DDF4F8;

}


.stat-pending {

    color: #806000;

    background: #FFF3CD;

}


.stat-approved {

    color: #0D6EFD;

    background: #E7F0FF;

}


.stat-running {

    color: #B12626;

    background: #FFE5E5;

}


.stat-done {

    color: #198754;

    background: #E1F4E8;

}


/* ============================================================
   CARD
============================================================ */

.admin-card {

    background: #FFFFFF;

    border:
        1px solid #ECECEC;

    border-radius: 18px;

    overflow: hidden;

    box-shadow:
        0 5px 19px
        rgba(0,0,0,.05);

}


.admin-card-header {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 12px;

    padding:
        17px 20px;

    border-bottom:
        1px solid #EEEEEE;

}


.admin-card-header h5 {

    margin: 0;

    color: #760000;

    font-size: 16px;

    font-weight: 800;

}


.admin-card-body {

    padding: 20px;

}


/* ============================================================
   FILTROS
============================================================ */

.filters-card {

    margin:
        23px 0;

    padding: 20px;

    background: #FFFFFF;

    border:
        1px solid #ECECEC;

    border-radius: 17px;

    box-shadow:
        0 5px 17px
        rgba(0,0,0,.04);

}


.form-label {

    color: #555555;

    font-size: 12px;

    font-weight: 700;

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
        0 0 0 .2rem
        rgba(177,38,38,.08);

}


textarea.form-control {

    min-height: 110px;

    resize: vertical;

}


.btn-filtrar {

    min-height: 44px;

    border: 0;

    border-radius: 9px;

    background: #B12626;

    color: #FFFFFF;

    font-weight: 700;

}


.btn-filtrar:hover {

    background: #760000;

    color: #FFFFFF;

}


.btn-limpiar {

    min-height: 44px;

    border:
        1px solid #DADADA;

    background: #FFFFFF;

    color: #555555;

    border-radius: 9px;

}


/* ============================================================
   MEJORA ITEM
============================================================ */

.mejora-item {

    padding:
        17px 0;

    border-bottom:
        1px solid #EEEEEE;

}


.mejora-item:first-child {

    padding-top: 0;

}


.mejora-item:last-child {

    padding-bottom: 0;

    border-bottom: 0;

}


.mejora-numero {

    color: #929292;

    font-size: 10px;

    font-weight: 700;

}


.mejora-title {

    color: #333333;

    font-size: 15px;

    font-weight: 800;

    text-decoration: none;

}


.mejora-title:hover {

    color: #B12626;

}


.mejora-description {

    max-width: 850px;

    margin-top: 5px;

    color: #727272;

    font-size: 12px;

    line-height: 1.55;

    display: -webkit-box;

    -webkit-line-clamp: 2;

    -webkit-box-orient: vertical;

    overflow: hidden;

}


.mejora-meta {

    display: flex;

    flex-wrap: wrap;

    gap:
        5px 13px;

    margin-top: 9px;

    color: #838383;

    font-size: 11px;

}


.mejora-meta i {

    color: #B12626;

}


.badge-mejora {

    border-radius: 30px;

    padding:
        6px 9px;

    font-size: 10px;

    font-weight: 700;

}


.motivo-box {

    max-width: 900px;

    margin-top: 10px;

    padding:
        8px 11px;

    border-left:
        3px solid #D6A000;

    background: #FFF7D9;

    border-radius: 8px;

    color: #655200;

    font-size: 11px;

}


/* ============================================================
   ACCIONES
============================================================ */

.acciones-mejora {

    display: flex;

    gap: 6px;

}


.btn-accion {

    width: 37px;

    height: 37px;

    display: inline-flex;

    justify-content: center;

    align-items: center;

    border-radius: 9px;

    text-decoration: none;

}


.btn-ver {

    color: #760000;

    background: #FFF2F2;

    border:
        1px solid #F0D8D8;

}


.btn-ver:hover {

    color: #FFFFFF;

    background: #B12626;

}


.btn-editar {

    color: #0D6EFD;

    background: #EEF5FF;

    border:
        1px solid #DDEAFF;

}


.btn-editar:hover {

    color: #FFFFFF;

    background: #0D6EFD;

}


/* ============================================================
   PANEL EDITAR
============================================================ */

.editar-cabecera {

    padding: 15px;

    border-radius: 11px;

    background: #FFF6F6;

    margin-bottom: 20px;

}


.editar-cabecera strong {

    color: #760000;

}


.editar-cabecera small {

    display: block;

    margin-top: 3px;

    color: #777777;

}


.btn-guardar {

    min-height: 45px;

    border: 0;

    border-radius: 9px;

    background:
        linear-gradient(
            135deg,
            #760000,
            #B12626
        );

    color: #FFFFFF;

    font-weight: 700;

}


.btn-guardar:hover {

    background: #760000;

    color: #FFFFFF;

}


/* ============================================================
   EMPTY
============================================================ */

.empty-state {

    padding:
        50px 20px;

    color: #888888;

    text-align: center;

}


.empty-state i {

    display: block;

    margin-bottom: 8px;

    color: #D0D0D0;

    font-size: 45px;

}


/* ============================================================
   PAGINACIÓN
============================================================ */

.page-link {

    color: #760000;

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
(max-width: 767px) {

    .admin-mejoras-hero {

        padding:
            22px 20px;

    }


    .admin-mejoras-hero h1 {

        font-size: 23px;

    }


    .hero-action {

        margin-top: 18px;

    }


    .btn-panel {

        width: 100%;

    }


    .mejora-linea {

        flex-direction: column;

    }


    .acciones-mejora {

        margin-top: 12px;

    }

}

</style>


<div class="admin-mejoras-wrapper">


    <!-- =====================================================
         HERO
    ====================================================== -->

    <section class="admin-mejoras-hero">

        <div class="row align-items-center">

            <div class="col-lg-8">

                <div class="hero-content">

                    <h1>

                        <i class="bi bi-lightbulb me-1"></i>

                        Administración de mejoras

                    </h1>

                    <p>

                        Evaluá propuestas, autorizaciones,
                        prioridades, costos y ejecución
                        de mejoras institucionales.

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
                        'admin/dashboard.php'
                    ) ?>"
                    class="btn-panel"
                >

                    <i class="bi bi-arrow-left"></i>

                    Panel administrador

                </a>

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


        <div class="col-6 col-md-4 col-xl">

            <div class="stat-card">

                <div class="stat-icon stat-total">

                    <i class="bi bi-lightbulb"></i>

                </div>

                <div class="stat-number">

                    <?= (int)(
                        $stats['total']
                        ?? 0
                    ) ?>

                </div>

                <div class="stat-label">
                    Total
                </div>

            </div>

        </div>


        <div class="col-6 col-md-4 col-xl">

            <div class="stat-card">

                <div class="stat-icon stat-new">

                    <i class="bi bi-pencil-square"></i>

                </div>

                <div class="stat-number">

                    <?= (int)(
                        $stats['propuestas']
                        ?? 0
                    ) ?>

                </div>

                <div class="stat-label">
                    Propuestas
                </div>

            </div>

        </div>


        <div class="col-6 col-md-4 col-xl">

            <div class="stat-card">

                <div class="stat-icon stat-eval">

                    <i class="bi bi-search"></i>

                </div>

                <div class="stat-number">

                    <?= (int)(
                        $stats['evaluacion']
                        ?? 0
                    ) ?>

                </div>

                <div class="stat-label">
                    En evaluación
                </div>

            </div>

        </div>


        <div class="col-6 col-md-4 col-xl">

            <div class="stat-card">

                <div class="stat-icon stat-pending">

                    <i class="bi bi-hourglass-split"></i>

                </div>

                <div class="stat-number">

                    <?= (int)(
                        $stats['pendientes']
                        ?? 0
                    ) ?>

                </div>

                <div class="stat-label">
                    Pend. autorización
                </div>

            </div>

        </div>


        <div class="col-6 col-md-4 col-xl">

            <div class="stat-card">

                <div class="stat-icon stat-running">

                    <i class="bi bi-tools"></i>

                </div>

                <div class="stat-number">

                    <?= (int)(
                        $stats['ejecucion']
                        ?? 0
                    ) ?>

                </div>

                <div class="stat-label">
                    En ejecución
                </div>

            </div>

        </div>


        <div class="col-6 col-md-4 col-xl">

            <div class="stat-card">

                <div class="stat-icon stat-done">

                    <i class="bi bi-check-circle"></i>

                </div>

                <div class="stat-number">

                    <?= (int)(
                        $stats['realizadas']
                        ?? 0
                    ) ?>

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

    <div class="filters-card">

        <form
            method="GET"
            action="<?= url(
                'admin/mejoras.php'
            ) ?>"
        >

            <div class="row g-3">


                <div class="col-lg-4">

                    <label class="form-label">
                        Buscar
                    </label>

                    <div class="input-group">

                        <span class="input-group-text">

                            <i class="bi bi-search"></i>

                        </span>

                        <input
                            type="text"
                            name="buscar"
                            class="form-control"
                            value="<?= e($buscar) ?>"
                            placeholder="Título, docente, sector..."
                        >

                    </div>

                </div>


                <div class="col-md-4 col-lg-2">

                    <label class="form-label">
                        Tipo
                    </label>

                    <select
                        name="tipo"
                        class="form-select"
                    >

                        <option value="">
                            Todos
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


                <div class="col-md-4 col-lg-2">

                    <label class="form-label">
                        Prioridad
                    </label>

                    <select
                        name="prioridad"
                        class="form-select"
                    >

                        <option value="">
                            Todas
                        </option>


                        <?php foreach (
                            $prioridadesPermitidas
                            as $prioridadItem
                        ): ?>

                            <option
                                value="<?= e(
                                    $prioridadItem
                                ) ?>"
                                <?= $prioridadFiltro === $prioridadItem
                                    ? 'selected'
                                    : ''
                                ?>
                            >

                                <?= e(
                                    $prioridadItem
                                ) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="col-md-4 col-lg-2">

                    <label class="form-label">
                        Estado
                    </label>

                    <select
                        name="estado"
                        class="form-select"
                    >

                        <option value="">
                            Todos
                        </option>


                        <?php foreach (
                            $estadosPermitidos
                            as $estadoItem
                        ): ?>

                            <option
                                value="<?= e(
                                    $estadoItem
                                ) ?>"
                                <?= $estadoFiltro === $estadoItem
                                    ? 'selected'
                                    : ''
                                ?>
                            >

                                <?= e(
                                    nombreEstadoAdminMejora(
                                        $estadoItem
                                    )
                                ) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


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
                            'admin/mejoras.php'
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
         CONTENIDO
    ====================================================== -->

    <div class="row g-4">


        <!-- =================================================
             LISTADO
        ================================================== -->

        <div class="<?= $mejoraEditar
            ? 'col-xl-8'
            : 'col-12'
        ?>">

            <div class="admin-card">

                <div class="admin-card-header">

                    <h5>

                        <i class="bi bi-list-check me-2"></i>

                        Propuestas registradas

                    </h5>


                    <span class="small text-muted">

                        <?= $totalRegistros ?>

                        <?= $totalRegistros === 1
                            ? 'resultado'
                            : 'resultados'
                        ?>

                    </span>

                </div>


                <div class="admin-card-body">


                    <?php if (
                        empty(
                            $mejoras
                        )
                    ): ?>

                        <div class="empty-state">

                            <i class="bi bi-lightbulb"></i>

                            <strong>
                                No hay propuestas
                            </strong>

                            <div class="mt-1">

                                No se encontraron registros
                                con los filtros seleccionados.

                            </div>

                        </div>


                    <?php else: ?>


                        <?php foreach (
                            $mejoras
                            as $mejora
                        ): ?>

                            <article class="mejora-item">

                                <div
                                    class="d-flex
                                           justify-content-between
                                           align-items-start
                                           gap-3
                                           mejora-linea"
                                >

                                    <div class="flex-grow-1">

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


                                        <a
                                            href="<?= url(
                                                'ver_mejora.php?id='
                                                .
                                                (int)$mejora[
                                                    'id_mejora'
                                                ]
                                            ) ?>"
                                            class="mejora-title"
                                        >

                                            <?= e(
                                                $mejora[
                                                    'titulo'
                                                ]
                                            ) ?>

                                        </a>


                                        <div class="mejora-description">

                                            <?= e(
                                                $mejora[
                                                    'descripcion'
                                                ]
                                            ) ?>

                                        </div>


                                        <div class="mejora-meta">


                                            <span>

                                                <i class="<?= e(
                                                    iconoTipo(
                                                        $mejora[
                                                            'tipo'
                                                        ]
                                                    )
                                                ) ?>"></i>

                                                <?= e(
                                                    nombreTipo(
                                                        $mejora[
                                                            'tipo'
                                                        ]
                                                    )
                                                ) ?>

                                            </span>


                                            <?php if (
                                                !empty(
                                                    $mejora[
                                                        'sector'
                                                    ]
                                                )
                                            ): ?>

                                                <span>

                                                    <i class="bi bi-geo-alt"></i>

                                                    <?= e(
                                                        $mejora[
                                                            'sector'
                                                        ]
                                                    ) ?>

                                                </span>

                                            <?php endif; ?>


                                            <span>

                                                <i class="bi bi-person"></i>

                                                <?= e(
                                                    $mejora[
                                                        'solicitante'
                                                    ]
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


                                            <?php if (
                                                (int)$mejora[
                                                    'cantidad_imagenes'
                                                ] > 0
                                            ): ?>

                                                <span>

                                                    <i class="bi bi-images"></i>

                                                    <?= (int)$mejora[
                                                        'cantidad_imagenes'
                                                    ] ?>
                                                    fotos

                                                </span>

                                            <?php endif; ?>


                                            <?php if (
                                                (int)$mejora[
                                                    'cantidad_materiales'
                                                ] > 0
                                            ): ?>

                                                <span>

                                                    <i class="bi bi-box-seam"></i>

                                                    <?= (int)$mejora[
                                                        'cantidad_materiales'
                                                    ] ?>
                                                    materiales

                                                </span>

                                            <?php endif; ?>


                                            <?php if (
                                                $mejora[
                                                    'costo_estimado'
                                                ] !== null
                                            ): ?>

                                                <span>

                                                    <i class="bi bi-currency-dollar"></i>

                                                    <?= e(
                                                        formatoDinero(
                                                            $mejora[
                                                                'costo_estimado'
                                                            ]
                                                        )
                                                    ) ?>

                                                </span>

                                            <?php endif; ?>


                                        </div>


                                        <?php if (
                                            !empty(
                                                $mejora[
                                                    'motivo_pendiente'
                                                ]
                                            )
                                        ): ?>

                                            <div class="motivo-box">

                                                <strong>
                                                    Motivo:
                                                </strong>

                                                <?= e(
                                                    $mejora[
                                                        'motivo_pendiente'
                                                    ]
                                                ) ?>

                                            </div>

                                        <?php endif; ?>


                                    </div>


                                    <div class="text-end">


                                        <div
                                            class="d-flex
                                                   flex-wrap
                                                   gap-1
                                                   justify-content-end
                                                   mb-2"
                                        >

                                            <span
                                                class="badge <?= e(
                                                    claseEstadoAdminMejora(
                                                        $mejora[
                                                            'estado'
                                                        ]
                                                    )
                                                ) ?> badge-mejora"
                                            >

                                                <?= e(
                                                    nombreEstadoAdminMejora(
                                                        $mejora[
                                                            'estado'
                                                        ]
                                                    )
                                                ) ?>

                                            </span>


                                            <span
                                                class="badge <?= e(
                                                    clasePrioridad(
                                                        $mejora[
                                                            'prioridad'
                                                        ]
                                                    )
                                                ) ?> badge-mejora"
                                            >

                                                <?= e(
                                                    $mejora[
                                                        'prioridad'
                                                    ]
                                                ) ?>

                                            </span>

                                        </div>


                                        <div class="acciones-mejora">

                                            <a
                                                href="<?= url(
                                                    'ver_mejora.php?id='
                                                    .
                                                    (int)$mejora[
                                                        'id_mejora'
                                                    ]
                                                ) ?>"
                                                class="btn-accion btn-ver"
                                                title="Ver propuesta"
                                            >

                                                <i class="bi bi-eye"></i>

                                            </a>


                                            <a
                                                href="<?= url(
                                                    'admin/mejoras.php?editar='
                                                    .
                                                    (int)$mejora[
                                                        'id_mejora'
                                                    ]
                                                ) ?>"
                                                class="btn-accion btn-editar"
                                                title="Administrar"
                                            >

                                                <i class="bi bi-pencil-square"></i>

                                            </a>

                                        </div>

                                    </div>

                                </div>

                            </article>

                        <?php endforeach; ?>


                    <?php endif; ?>


                </div>


                <!-- =========================================
                     PAGINACIÓN
                ========================================== -->

                <?php if (
                    $totalPaginas > 1
                ): ?>

                    <div
                        class="border-top
                               p-3
                               d-flex
                               justify-content-center"
                    >

                        <nav>

                            <ul class="pagination mb-0">


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
                                                urlPaginaAdminMejoras(
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
                                                urlPaginaAdminMejoras(
                                                    $i
                                                )
                                            ) ?>"
                                        >

                                            <?= $i ?>

                                        </a>

                                    </li>

                                <?php endfor; ?>


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
                                                urlPaginaAdminMejoras(
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


        <!-- =================================================
             PANEL ADMINISTRAR
        ================================================== -->

        <?php if (
            $mejoraEditar
        ): ?>

            <div class="col-xl-4">

                <div class="admin-card">

                    <div class="admin-card-header">

                        <h5>

                            <i class="bi bi-pencil-square me-2"></i>

                            Administrar propuesta

                        </h5>


                        <a
                            href="<?= url(
                                'admin/mejoras.php'
                            ) ?>"
                            class="btn-close"
                            aria-label="Cerrar"
                        ></a>

                    </div>


                    <div class="admin-card-body">


                        <div class="editar-cabecera">

                            <strong>

                                MEJORA
                                #<?= str_pad(
                                    (string)$mejoraEditar[
                                        'id_mejora'
                                    ],
                                    5,
                                    '0',
                                    STR_PAD_LEFT
                                ) ?>

                            </strong>

                            <small>

                                <?= e(
                                    $mejoraEditar[
                                        'titulo'
                                    ]
                                ) ?>

                            </small>


                            <?php if (
                                !empty(
                                    $mejoraEditar[
                                        'sector'
                                    ]
                                )
                            ): ?>

                                <small>

                                    <i class="bi bi-geo-alt me-1"></i>

                                    <?= e(
                                        $mejoraEditar[
                                            'sector'
                                        ]
                                    ) ?>

                                </small>

                            <?php endif; ?>

                        </div>


                        <form
                            method="POST"
                            action="<?= url(
                                'admin/mejoras.php'
                            ) ?>"
                        >

                            <?= csrfInput() ?>


                            <input
                                type="hidden"
                                name="accion"
                                value="actualizar"
                            >


                            <input
                                type="hidden"
                                name="id_mejora"
                                value="<?= (int)$mejoraEditar[
                                    'id_mejora'
                                ] ?>"
                            >


                            <!-- ESTADO -->

                            <div class="mb-3">

                                <label
                                    for="estadoEditar"
                                    class="form-label"
                                >
                                    Estado
                                </label>


                                <select
                                    name="estado"
                                    id="estadoEditar"
                                    class="form-select"
                                    required
                                >

                                    <?php foreach (
                                        $estadosPermitidos
                                        as $estadoItem
                                    ): ?>

                                        <option
                                            value="<?= e(
                                                $estadoItem
                                            ) ?>"
                                            <?= $mejoraEditar[
                                                'estado'
                                            ] === $estadoItem
                                                ? 'selected'
                                                : ''
                                            ?>
                                        >

                                            <?= e(
                                                nombreEstadoAdminMejora(
                                                    $estadoItem
                                                )
                                            ) ?>

                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>


                            <!-- PRIORIDAD -->

                            <div class="mb-3">

                                <label
                                    for="prioridadEditar"
                                    class="form-label"
                                >
                                    Prioridad
                                </label>


                                <select
                                    name="prioridad"
                                    id="prioridadEditar"
                                    class="form-select"
                                    required
                                >

                                    <?php foreach (
                                        $prioridadesPermitidas
                                        as $prioridadItem
                                    ): ?>

                                        <option
                                            value="<?= e(
                                                $prioridadItem
                                            ) ?>"
                                            <?= $mejoraEditar[
                                                'prioridad'
                                            ] === $prioridadItem
                                                ? 'selected'
                                                : ''
                                            ?>
                                        >

                                            <?= e(
                                                $prioridadItem
                                            ) ?>

                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>


                            <!-- COSTO -->

                            <div class="mb-3">

                                <label
                                    for="costoEstimado"
                                    class="form-label"
                                >
                                    Costo estimado
                                </label>


                                <div class="input-group">

                                    <span class="input-group-text">
                                        $
                                    </span>

                                    <input
                                        type="text"
                                        name="costo_estimado"
                                        id="costoEstimado"
                                        class="form-control"
                                        value="<?= $mejoraEditar[
                                            'costo_estimado'
                                        ] !== null
                                            ? e(
                                                (string)$mejoraEditar[
                                                    'costo_estimado'
                                                ]
                                            )
                                            : ''
                                        ?>"
                                        placeholder="Ej.: 150000"
                                        inputmode="decimal"
                                    >

                                </div>

                            </div>


                            <!-- MOTIVO -->

                            <div class="mb-3">

                                <label
                                    for="motivoPendiente"
                                    class="form-label"
                                >

                                    Motivo / fundamento

                                </label>


                                <textarea
                                    name="motivo_pendiente"
                                    id="motivoPendiente"
                                    class="form-control"
                                    maxlength="1000"
                                    placeholder="Ej.: Pendiente de autorización de Dirección, compra de repuesto, presupuesto, etc."
                                ><?= e(
                                    $mejoraEditar[
                                        'motivo_pendiente'
                                    ]
                                    ?? ''
                                ) ?></textarea>


                                <div class="form-text">

                                    Es obligatorio cuando
                                    el estado sea Pendiente
                                    de autorización o Rechazada.

                                </div>

                            </div>


                            <!-- OBSERVACIONES -->

                            <div class="mb-3">

                                <label
                                    for="observacionesAdmin"
                                    class="form-label"
                                >
                                    Observaciones administrativas
                                </label>


                                <textarea
                                    name="observaciones_admin"
                                    id="observacionesAdmin"
                                    class="form-control"
                                    maxlength="2000"
                                    placeholder="Notas internas, presupuestos solicitados, autorización, planificación..."
                                ><?= e(
                                    $mejoraEditar[
                                        'observaciones_admin'
                                    ]
                                    ?? ''
                                ) ?></textarea>

                            </div>


                            <div class="d-grid gap-2">

                                <button
                                    type="submit"
                                    class="btn btn-guardar"
                                >

                                    <i class="bi bi-floppy me-1"></i>

                                    Guardar cambios

                                </button>


                                <a
                                    href="<?= url(
                                        'ver_mejora.php?id='
                                        .
                                        (int)$mejoraEditar[
                                            'id_mejora'
                                        ]
                                    ) ?>"
                                    class="btn btn-outline-secondary"
                                >

                                    <i class="bi bi-eye me-1"></i>

                                    Ver detalle completo

                                </a>


                                <a
                                    href="<?= url(
                                        'admin/mejoras.php'
                                    ) ?>"
                                    class="btn btn-light"
                                >

                                    Cancelar

                                </a>

                            </div>


                        </form>

                    </div>

                </div>

            </div>

        <?php endif; ?>


    </div>


</div>


<script>

// ============================================================
// MOSTRAR AYUDA SEGÚN ESTADO
// ============================================================

const estadoEditar =
    document.getElementById(
        'estadoEditar'
    );

const motivoPendiente =
    document.getElementById(
        'motivoPendiente'
    );


if (
    estadoEditar
    &&
    motivoPendiente
) {

    function actualizarMotivo() {

        const requiere =
            estadoEditar.value ===
            'Pendiente autorizacion'
            ||
            estadoEditar.value ===
            'Rechazada';


        motivoPendiente.required =
            requiere;


        if (requiere) {

            motivoPendiente
                .closest('.mb-3')
                .classList
                .add('border-start');

        } else {

            motivoPendiente
                .closest('.mb-3')
                .classList
                .remove('border-start');
        }
    }


    estadoEditar.addEventListener(
        'change',
        actualizarMotivo
    );


    actualizarMotivo();
}

</script>


<?php

require_once __DIR__
    . '/../includes/footer.php';

?>