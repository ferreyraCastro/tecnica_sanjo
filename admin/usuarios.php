<?php
// ============================================================
// COLEGIO SAN JOSÉ
// Sistema de Gestión Técnica
// Archivo: /tecnica/admin/usuarios.php
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

$rolesPermitidos = [
    'Docente',
    'Tecnico',
    'Administrador'
];


$estadosPermitidos = [
    'Activo',
    'Inactivo'
];


// ============================================================
// ÁREA DEL TÉCNICO
//
// Solo aplica a usuarios con rol Tecnico. Define si su tarjeta
// en horarios.php se muestra como Informática o Mantenimiento.
// ============================================================

$areasTecnicoPermitidas = [
    'Informatica',
    'Mantenimiento'
];


// ============================================================
// FUNCIÓN REDIRECCIÓN
// ============================================================

function volverUsuariosAdmin(
    int $editar = 0
): never {

    $ruta =
        'admin/usuarios.php';


    if ($editar > 0) {

        $ruta .=
            '?editar='
            .
            $editar;
    }


    header(
        'Location: '
        . url($ruta)
    );

    exit;
}


// ============================================================
// NORMALIZAR DNI
// ============================================================

function normalizarDni(
    string $dni
): string {

    return preg_replace(
        '/\D+/',
        '',
        $dni
    ) ?? '';
}


// ============================================================
// FORMULARIO
// ============================================================

$error = '';

$usuarioFormulario = null;


// ============================================================
// PROCESAR POST
// ============================================================

if (
    $_SERVER['REQUEST_METHOD']
    === 'POST'
) {

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

        volverUsuariosAdmin();
    }


    $accion =
        limpiarTexto(
            $_POST['accion']
            ?? ''
        );


    // ========================================================
    // GUARDAR USUARIO
    // ========================================================

    if ($accion === 'guardar') {

        $idUsuario =
            (int)(
                $_POST['id_usuario']
                ?? 0
            );


        $nombre =
            limpiarTexto(
                $_POST['nombre']
                ?? ''
            );


        $apellido =
            limpiarTexto(
                $_POST['apellido']
                ?? ''
            );


        $correo =
            mb_strtolower(
                trim(
                    $_POST['correo']
                    ?? ''
                )
            );


        $telefono =
            preg_replace(
                '/[^0-9+\s()\-]/',
                '',
                trim(
                    $_POST['telefono']
                    ?? ''
                )
            )
            ?? '';

        $whatsappApikey =
            limpiarTexto(
                $_POST['whatsapp_apikey']
                ?? ''
            );


        $dni =
            normalizarDni(
                $_POST['dni']
                ?? ''
            );


        $rol =
            limpiarTexto(
                $_POST['rol']
                ?? ''
            );


        $areaTecnico =
            limpiarTexto(
                $_POST['area_tecnico']
                ?? ''
            );

        // El área solo tiene sentido para técnicos.
        if ($rol !== 'Tecnico') {

            $areaTecnico = '';
        }


        $estado =
            limpiarTexto(
                $_POST['estado']
                ?? 'Activo'
            );


        // ====================================================
        // DATOS PARA RECUPERAR FORMULARIO EN CASO DE ERROR
        // ====================================================

        $usuarioFormulario = [

            'id_usuario' =>
                $idUsuario,

            'nombre' =>
                $nombre,

            'apellido' =>
                $apellido,

            'correo' =>
                $correo,

            'telefono' =>
                $telefono,

            'whatsapp_apikey' =>
                $whatsappApikey,

            'rol' =>
                $rol,

            'area_tecnico' =>
                $areaTecnico,

            'estado' =>
                $estado

        ];


        // ====================================================
        // VALIDACIONES
        // ====================================================

        if ($nombre === '') {

            $error =
                'Ingresá el nombre del usuario.';

        } elseif (
            mb_strlen($nombre) > 100
        ) {

            $error =
                'El nombre es demasiado largo.';

        } elseif ($apellido === '') {

            $error =
                'Ingresá el apellido del usuario.';

        } elseif (
            mb_strlen($apellido) > 100
        ) {

            $error =
                'El apellido es demasiado largo.';

        } elseif (
            !filter_var(
                $correo,
                FILTER_VALIDATE_EMAIL
            )
        ) {

            $error =
                'Ingresá una dirección de correo válida.';

        } elseif (
            $telefono !== ''
            &&
            mb_strlen($telefono) > 30
        ) {

            $error =
                'El teléfono no puede superar los 30 caracteres.';

        } elseif (
            $whatsappApikey !== ''
            &&
            mb_strlen($whatsappApikey) > 20
        ) {

            $error =
                'La apikey de WhatsApp no puede superar los 20 caracteres.';

        } elseif (
            !in_array(
                $rol,
                $rolesPermitidos,
                true
            )
        ) {

            $error =
                'Seleccioná un rol válido.';

        } elseif (
            !in_array(
                $estado,
                $estadosPermitidos,
                true
            )
        ) {

            $error =
                'Seleccioná un estado válido.';

        } elseif (
            $idUsuario === 0
            &&
            (
                strlen($dni) < 7
                ||
                strlen($dni) > 9
            )
        ) {

            $error =
                'Ingresá un DNI válido de entre 7 y 9 números.';

        } elseif (
            $idUsuario > 0
            &&
            $dni !== ''
            &&
            (
                strlen($dni) < 7
                ||
                strlen($dni) > 9
            )
        ) {

            $error =
                'El nuevo DNI debe contener entre 7 y 9 números.';
        }


        // ====================================================
        // CORREO DUPLICADO
        // ====================================================

        if ($error === '') {

            if ($idUsuario > 0) {

                $stmtCorreo =
                    $conexion->prepare("
                        SELECT COUNT(*)

                        FROM usuarios

                        WHERE correo = ?

                        AND id_usuario <> ?
                    ");


                $stmtCorreo->execute([
                    $correo,
                    $idUsuario
                ]);

            } else {

                $stmtCorreo =
                    $conexion->prepare("
                        SELECT COUNT(*)

                        FROM usuarios

                        WHERE correo = ?
                    ");


                $stmtCorreo->execute([
                    $correo
                ]);
            }


            if (
                (int)$stmtCorreo->fetchColumn()
                > 0
            ) {

                $error =
                    'Ya existe un usuario registrado con ese correo.';
            }
        }


        // ====================================================
        // NO PERMITIR QUE EL ADMIN SE DESACTIVE A SÍ MISMO
        // ====================================================

        if (
            $error === ''
            &&
            $idUsuario === (int)usuarioId()
            &&
            $estado === 'Inactivo'
        ) {

            $error =
                'No podés desactivar tu propia cuenta.';
        }


        // ====================================================
        // NO PERMITIR SACARSE EL ROL ADMIN
        // ====================================================

        if (
            $error === ''
            &&
            $idUsuario === (int)usuarioId()
            &&
            $rol !== 'Administrador'
        ) {

            $error =
                'No podés quitarte el rol de Administrador desde tu propia cuenta.';
        }


        // ====================================================
        // GUARDAR
        // ====================================================

        if ($error === '') {

            try {

                // =================================================
                // EDITAR
                // =================================================

                if ($idUsuario > 0) {

                    // Si escribió DNI nuevo, actualizamos el hash.

                    if ($dni !== '') {

                        $dniHash =
                            password_hash(
                                $dni,
                                PASSWORD_DEFAULT
                            );


                        $stmt =
                            $conexion->prepare("
                                UPDATE usuarios

                                SET
                                    nombre = ?,
                                    apellido = ?,
                                    correo = ?,
                                    telefono = ?,
                                    whatsapp_apikey = ?,
                                    dni_hash = ?,
                                    rol = ?,
                                    estado = ?,
                                    fecha_actualizacion = NOW()

                                WHERE id_usuario = ?
                            ");


                        $stmt->execute([

                            $nombre,
                            $apellido,
                            $correo,
                            $telefono !== '' ? $telefono : null,
                            $whatsappApikey !== '' ? $whatsappApikey : null,
                            $dniHash,
                            $rol,
                            $estado,
                            $idUsuario

                        ]);

                    } else {

                        // Sin DNI nuevo:
                        // mantenemos el hash existente.

                        $stmt =
                            $conexion->prepare("
                                UPDATE usuarios

                                SET
                                    nombre = ?,
                                    apellido = ?,
                                    correo = ?,
                                    telefono = ?,
                                    whatsapp_apikey = ?,
                                    rol = ?,
                                    estado = ?,
                                    fecha_actualizacion = NOW()

                                WHERE id_usuario = ?
                            ");


                        $stmt->execute([

                            $nombre,
                            $apellido,
                            $correo,
                            $telefono !== '' ? $telefono : null,
                            $whatsappApikey !== '' ? $whatsappApikey : null,
                            $rol,
                            $estado,
                            $idUsuario

                        ]);
                    }


                    flash(
                        'success',
                        'El usuario fue actualizado correctamente.'
                    );


                } else {

                    // =============================================
                    // CREAR
                    // =============================================

                    $dniHash =
                        password_hash(
                            $dni,
                            PASSWORD_DEFAULT
                        );


                    $stmt =
                        $conexion->prepare("
                            INSERT INTO usuarios
                            (
                                nombre,
                                apellido,
                                correo,
                                telefono,
                                whatsapp_apikey,
                                dni_hash,
                                rol,
                                estado,
                                fecha_creacion,
                                fecha_actualizacion
                            )
                            VALUES
                            (
                                ?,
                                ?,
                                ?,
                                ?,
                                ?,
                                ?,
                                ?,
                                ?,
                                NOW(),
                                NOW()
                            )
                        ");


                    $stmt->execute([

                        $nombre,
                        $apellido,
                        $correo,
                        $telefono !== '' ? $telefono : null,
                        $whatsappApikey !== '' ? $whatsappApikey : null,
                        $dniHash,
                        $rol,
                        $estado

                    ]);


                    flash(
                        'success',
                        'El usuario fue creado correctamente.'
                    );
                }


                volverUsuariosAdmin();


            } catch (Throwable $e) {

                error_log(
                    'Error guardar usuario: '
                    .
                    $e->getMessage()
                );


                $error =
                    'No se pudo guardar el usuario.';
            }
        }
    }


    // ========================================================
    // CAMBIAR ESTADO
    // ========================================================

    elseif (
        $accion === 'estado'
    ) {

        $idUsuario =
            (int)(
                $_POST['id_usuario']
                ?? 0
            );


        if ($idUsuario <= 0) {

            flash(
                'error',
                'El usuario indicado no es válido.'
            );

            volverUsuariosAdmin();
        }


        if (
            $idUsuario ===
            (int)usuarioId()
        ) {

            flash(
                'error',
                'No podés desactivar tu propia cuenta.'
            );

            volverUsuariosAdmin();
        }


        try {

            $stmt =
                $conexion->prepare("
                    UPDATE usuarios

                    SET

                        estado =
                            CASE

                                WHEN estado = 'Activo'
                                THEN 'Inactivo'

                                ELSE 'Activo'

                            END,

                        fecha_actualizacion =
                            NOW()

                    WHERE id_usuario = ?
                ");


            $stmt->execute([
                $idUsuario
            ]);


            flash(
                'success',
                'El estado del usuario fue actualizado.'
            );


        } catch (Throwable $e) {

            error_log(
                'Error estado usuario: '
                .
                $e->getMessage()
            );


            flash(
                'error',
                'No se pudo modificar el usuario.'
            );
        }


        volverUsuariosAdmin();
    }


    // ========================================================
    // REINICIAR DNI
    // ========================================================

    elseif (
        $accion === 'dni'
    ) {

        $idUsuario =
            (int)(
                $_POST['id_usuario']
                ?? 0
            );


        $nuevoDni =
            normalizarDni(
                $_POST['nuevo_dni']
                ?? ''
            );


        if ($idUsuario <= 0) {

            flash(
                'error',
                'Usuario no válido.'
            );

            volverUsuariosAdmin();
        }


        if (
            strlen($nuevoDni) < 7
            ||
            strlen($nuevoDni) > 9
        ) {

            flash(
                'error',
                'El DNI debe contener entre 7 y 9 números.'
            );

            volverUsuariosAdmin(
                $idUsuario
            );
        }


        try {

            $dniHash =
                password_hash(
                    $nuevoDni,
                    PASSWORD_DEFAULT
                );


            $stmt =
                $conexion->prepare("
                    UPDATE usuarios

                    SET
                        dni_hash = ?,
                        fecha_actualizacion = NOW()

                    WHERE id_usuario = ?
                ");


            $stmt->execute([
                $dniHash,
                $idUsuario
            ]);


            flash(
                'success',
                'El DNI de acceso fue actualizado correctamente.'
            );


        } catch (Throwable $e) {

            error_log(
                'Error reiniciar DNI: '
                .
                $e->getMessage()
            );


            flash(
                'error',
                'No se pudo actualizar el DNI.'
            );
        }


        volverUsuariosAdmin(
            $idUsuario
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


$rolFiltro =
    trim(
        $_GET['rol']
        ?? ''
    );


$estadoFiltro =
    trim(
        $_GET['estado']
        ?? ''
    );


if (
    $rolFiltro !== ''
    &&
    !in_array(
        $rolFiltro,
        $rolesPermitidos,
        true
    )
) {

    $rolFiltro = '';
}


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


// ============================================================
// CONDICIONES
// ============================================================

$condiciones = [];

$parametros = [];


if ($buscar !== '') {

    $condiciones[] = "
        (
            u.nombre LIKE ?
            OR
            u.apellido LIKE ?
            OR
            u.correo LIKE ?
            OR
            CONCAT(
                u.nombre,
                ' ',
                u.apellido
            ) LIKE ?
        )
    ";


    $buscarSql =
        '%'
        .
        $buscar
        .
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


if ($rolFiltro !== '') {

    $condiciones[] =
        'u.rol = ?';

    $parametros[] =
        $rolFiltro;
}


if ($estadoFiltro !== '') {

    $condiciones[] =
        'u.estado = ?';

    $parametros[] =
        $estadoFiltro;
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


$porPagina = 20;


// ============================================================
// CONTAR USUARIOS
// ============================================================

$sqlCantidad = "
    SELECT COUNT(*)

    FROM usuarios u

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


if ($pagina > $totalPaginas) {

    $pagina =
        $totalPaginas;
}


$offset =
    ($pagina - 1)
    *
    $porPagina;


// ============================================================
// CONSULTA DE USUARIOS
// ============================================================

$sqlUsuarios = "
    SELECT

        u.id_usuario,
        u.nombre,
        u.apellido,
        u.correo,
        u.rol,
        u.estado,
        u.fecha_creacion,
        u.fecha_actualizacion,

        (
            SELECT COUNT(*)

            FROM solicitudes s

            WHERE
                s.id_usuario =
                u.id_usuario

        ) AS solicitudes_creadas,

        (
            SELECT COUNT(*)

            FROM intervenciones i

            WHERE
                i.id_tecnico =
                u.id_usuario

        ) AS intervenciones,

        (
            SELECT COUNT(*)

            FROM solicitudes_asignaciones sa

            WHERE
                sa.id_tecnico =
                u.id_usuario

            AND
                sa.activo = 1

        ) AS asignaciones_activas

    FROM usuarios u

    {$where}

    ORDER BY

        CASE u.estado

            WHEN 'Activo'
                THEN 1

            ELSE 2

        END,

        CASE u.rol

            WHEN 'Administrador'
                THEN 1

            WHEN 'Tecnico'
                THEN 2

            WHEN 'Docente'
                THEN 3

            ELSE 4

        END,

        u.apellido ASC,
        u.nombre ASC

    LIMIT {$porPagina}

    OFFSET {$offset}
";


$stmtUsuarios =
    $conexion->prepare(
        $sqlUsuarios
    );


$stmtUsuarios->execute(
    $parametros
);


$usuarios =
    $stmtUsuarios->fetchAll(
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
                    WHEN rol = 'Docente'
                    THEN 1
                    ELSE 0
                END
            ) AS docentes,

            SUM(
                CASE
                    WHEN rol = 'Tecnico'
                    THEN 1
                    ELSE 0
                END
            ) AS tecnicos,

            SUM(
                CASE
                    WHEN rol = 'Administrador'
                    THEN 1
                    ELSE 0
                END
            ) AS administradores,

            SUM(
                CASE
                    WHEN estado = 'Activo'
                    THEN 1
                    ELSE 0
                END
            ) AS activos,

            SUM(
                CASE
                    WHEN estado = 'Inactivo'
                    THEN 1
                    ELSE 0
                END
            ) AS inactivos

        FROM usuarios
    ");


$stats =
    $stmtStats->fetch(
        PDO::FETCH_ASSOC
    );


// ============================================================
// EDITAR USUARIO
// ============================================================

$idEditar =
    (int)(
        $_GET['editar']
        ?? 0
    );


if (
    $idEditar > 0
    &&
    $usuarioFormulario === null
) {

    $stmtEditar =
        $conexion->prepare("
            SELECT

                id_usuario,
                nombre,
                apellido,
                correo,
                telefono,
                whatsapp_apikey,
                rol,
                estado

            FROM usuarios

            WHERE id_usuario = ?

            LIMIT 1
        ");


    $stmtEditar->execute([
        $idEditar
    ]);


    $usuarioFormulario =
        $stmtEditar->fetch(
            PDO::FETCH_ASSOC
        );
}


// ============================================================
// VALORES FORMULARIO
// ============================================================

$form =
    $usuarioFormulario
    ??
    [

        'id_usuario' => 0,
        'nombre' => '',
        'apellido' => '',
        'correo' => '',
        'telefono' => '',
        'whatsapp_apikey' => '',
        'rol' => 'Docente',
        'area_tecnico' => '',
        'estado' => 'Activo'

    ];


// ============================================================
// FLASH
// ============================================================

$flash =
    obtenerFlash();


// ============================================================
// URL PAGINACIÓN
// ============================================================

function urlPaginaUsuarios(
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
        'admin/usuarios.php?'
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

.usuarios-wrapper {

    max-width: 1550px;
    margin: 0 auto;
    padding: 5px 12px 45px;

}


/* ============================================================
   HERO
============================================================ */

.usuarios-hero {

    position: relative;
    overflow: hidden;

    padding: 29px;
    margin-bottom: 23px;

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


.usuarios-hero::after {

    content: "";

    position: absolute;

    width: 280px;
    height: 280px;

    right: -100px;
    top: -135px;

    border-radius: 50%;

    background:
        rgba(255,255,255,.06);

}


.hero-content {

    position: relative;
    z-index: 2;

}


.usuarios-hero h1 {

    margin: 0 0 7px;

    font-size: 28px;
    font-weight: 800;

}


.usuarios-hero p {

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

    padding: 10px 17px;

    border-radius: 10px;

    color: #760000;
    background: #FFFFFF;

    text-decoration: none;
    font-weight: 700;

}


.btn-panel:hover {

    background: #F4F4F4;
    color: #B12626;

}


/* ============================================================
   STATS
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
    justify-content: center;
    align-items: center;

    margin-bottom: 9px;

    border-radius: 11px;

    font-size: 18px;

}


.stat-number {

    color: #333333;

    font-size: 27px;
    font-weight: 800;

    line-height: 1;

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


.stat-docente {

    color: #0D6EFD;
    background: #E8F1FF;

}


.stat-tecnico {

    color: #B12626;
    background: #FFE5E5;

}


.stat-admin {

    color: #6F42C1;
    background: #F0E9FA;

}


.stat-activo {

    color: #198754;
    background: #E1F4E8;

}


.stat-inactivo {

    color: #6C757D;
    background: #EEEEEE;

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
    justify-content: space-between;
    align-items: center;

    gap: 12px;

    padding: 18px 20px;

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
   FORMULARIO
============================================================ */

.form-label {

    color: #4D4D4D;

    font-size: 12px;
    font-weight: 700;

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


.input-dni {

    letter-spacing: 1px;

}


.form-help {

    margin-top: 5px;

    color: #898989;

    font-size: 10px;

    line-height: 1.4;

}


.btn-guardar {

    min-height: 45px;

    border: 0;

    border-radius: 9px;

    color: #FFFFFF;

    background:
        linear-gradient(
            135deg,
            #760000,
            #B12626
        );

    font-weight: 700;

}


.btn-guardar:hover {

    color: #FFFFFF;
    background: #760000;

}


/* ============================================================
   FILTROS
============================================================ */

.filters-card {

    margin-bottom: 23px;

    padding: 19px;

    border:
        1px solid #ECECEC;

    border-radius: 17px;

    background: #FFFFFF;

    box-shadow:
        0 5px 17px
        rgba(0,0,0,.04);

}


.btn-filter {

    min-height: 45px;

    border: 0;

    border-radius: 9px;

    color: #FFFFFF;

    background: #B12626;

    font-weight: 700;

}


.btn-filter:hover {

    color: #FFFFFF;
    background: #760000;

}


/* ============================================================
   TABLA
============================================================ */

.table {

    margin-bottom: 0;

}


.table thead th {

    padding: 13px;

    background: #FAFAFA;

    color: #555555;

    text-transform: uppercase;

    font-size: 10px;
    letter-spacing: .3px;

    white-space: nowrap;

}


.table tbody td {

    padding: 14px 13px;

    vertical-align: middle;

    border-color: #EEEEEE;

}


.usuario-info {

    display: flex;
    align-items: center;

    gap: 10px;

    min-width: 190px;

}


.usuario-avatar {

    min-width: 40px;

    width: 40px;
    height: 40px;

    display: flex;
    justify-content: center;
    align-items: center;

    border-radius: 50%;

    color: #FFFFFF;

    background:
        linear-gradient(
            135deg,
            #760000,
            #B12626
        );

    font-size: 14px;
    font-weight: 800;

}


.usuario-nombre {

    color: #333333;

    font-size: 12px;
    font-weight: 800;

}


.usuario-correo {

    margin-top: 2px;

    color: #858585;

    font-size: 10px;

}


.rol-badge {

    display: inline-flex;
    align-items: center;

    gap: 4px;

    padding: 5px 8px;

    border-radius: 20px;

    font-size: 9px;
    font-weight: 700;

}


.rol-docente {

    color: #0D6EFD;
    background: #E8F1FF;

}


.rol-tecnico {

    color: #B12626;
    background: #FFE5E5;

}


.rol-administrador {

    color: #6F42C1;
    background: #F0E9FA;

}


.area-badge {

    display: inline-flex;
    align-items: center;

    gap: 4px;

    margin-top: 4px;

    padding: 5px 8px;

    border-radius: 20px;

    font-size: 9px;
    font-weight: 700;

}


.area-informatica {

    color: #0B7285;
    background: #E3F6FA;

}


.area-mantenimiento {

    color: #B15C00;
    background: #FFF1E0;

}


.area-sin-asignar {

    color: #8A8A8A;
    background: #F0F0F0;

}


.estado-activo {

    display: inline-flex;
    align-items: center;

    gap: 4px;

    padding: 5px 8px;

    border-radius: 20px;

    color: #198754;
    background: #E1F4E8;

    font-size: 9px;
    font-weight: 700;

}


.estado-inactivo {

    display: inline-flex;
    align-items: center;

    gap: 4px;

    padding: 5px 8px;

    border-radius: 20px;

    color: #6C757D;
    background: #EEEEEE;

    font-size: 9px;
    font-weight: 700;

}


.activity {

    display: flex;
    flex-wrap: wrap;

    gap: 5px 9px;

    color: #777777;

    font-size: 10px;

}


.activity i {

    color: #B12626;

}


/* ============================================================
   ACCIONES
============================================================ */

.actions {

    display: flex;
    justify-content: center;

    gap: 5px;

}


.action-button {

    width: 35px;
    height: 35px;

    display: inline-flex;
    justify-content: center;
    align-items: center;

    border: none;
    border-radius: 8px;

    text-decoration: none;

}


.action-edit {

    color: #0D6EFD;
    background: #EEF5FF;

}


.action-edit:hover {

    color: #FFFFFF;
    background: #0D6EFD;

}


.action-state {

    color: #916C00;
    background: #FFF5D9;

}


.action-state:hover {

    color: #FFFFFF;
    background: #D29A00;

}


/* ============================================================
   SEGURIDAD
============================================================ */

.security-box {

    margin-top: 20px;

    padding: 14px;

    border-left:
        4px solid #B12626;

    border-radius: 10px;

    background: #FFF7F7;

    color: #606060;

    font-size: 11px;

    line-height: 1.55;

}


/* ============================================================
   EMPTY
============================================================ */

.empty-state {

    padding: 50px 20px;

    text-align: center;

    color: #909090;

}


.empty-state i {

    display: block;

    margin-bottom: 8px;

    color: #D0D0D0;

    font-size: 45px;

}


/* ============================================================
   PAGINATION
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

    .usuarios-hero {

        padding: 22px 20px;

    }


    .usuarios-hero h1 {

        font-size: 23px;

    }


    .hero-action {

        margin-top: 18px;

    }


    .btn-panel {

        width: 100%;

    }

}

</style>


<div class="usuarios-wrapper">


    <!-- =====================================================
         HERO
    ====================================================== -->

    <section class="usuarios-hero">

        <div class="row align-items-center">

            <div class="col-lg-8">

                <div class="hero-content">

                    <h1>

                        <i class="bi bi-people me-1"></i>

                        Administración de usuarios

                    </h1>

                    <p>

                        Gestioná las cuentas de docentes,
                        personal técnico y administradores
                        del sistema de Gestión Técnica.

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


    <?php if ($error !== ''): ?>

        <div class="alert alert-danger">

            <i class="bi bi-exclamation-triangle me-1"></i>

            <?= e($error) ?>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         ESTADÍSTICAS
    ====================================================== -->

    <div class="row g-3 mb-4">


        <div class="col-6 col-md-4 col-xl-2">

            <div class="stat-card">

                <div class="stat-icon stat-total">

                    <i class="bi bi-people"></i>

                </div>

                <div class="stat-number">

                    <?= (int)(
                        $stats['total']
                        ?? 0
                    ) ?>

                </div>

                <div class="stat-label">
                    Usuarios
                </div>

            </div>

        </div>


        <div class="col-6 col-md-4 col-xl-2">

            <div class="stat-card">

                <div class="stat-icon stat-docente">

                    <i class="bi bi-person"></i>

                </div>

                <div class="stat-number">

                    <?= (int)(
                        $stats['docentes']
                        ?? 0
                    ) ?>

                </div>

                <div class="stat-label">
                    Docentes
                </div>

            </div>

        </div>


        <div class="col-6 col-md-4 col-xl-2">

            <div class="stat-card">

                <div class="stat-icon stat-tecnico">

                    <i class="bi bi-person-gear"></i>

                </div>

                <div class="stat-number">

                    <?= (int)(
                        $stats['tecnicos']
                        ?? 0
                    ) ?>

                </div>

                <div class="stat-label">
                    Técnicos
                </div>

            </div>

        </div>


        <div class="col-6 col-md-4 col-xl-2">

            <div class="stat-card">

                <div class="stat-icon stat-admin">

                    <i class="bi bi-shield-check"></i>

                </div>

                <div class="stat-number">

                    <?= (int)(
                        $stats['administradores']
                        ?? 0
                    ) ?>

                </div>

                <div class="stat-label">
                    Administradores
                </div>

            </div>

        </div>


        <div class="col-6 col-md-4 col-xl-2">

            <div class="stat-card">

                <div class="stat-icon stat-activo">

                    <i class="bi bi-check-circle"></i>

                </div>

                <div class="stat-number">

                    <?= (int)(
                        $stats['activos']
                        ?? 0
                    ) ?>

                </div>

                <div class="stat-label">
                    Activos
                </div>

            </div>

        </div>


        <div class="col-6 col-md-4 col-xl-2">

            <div class="stat-card">

                <div class="stat-icon stat-inactivo">

                    <i class="bi bi-person-x"></i>

                </div>

                <div class="stat-number">

                    <?= (int)(
                        $stats['inactivos']
                        ?? 0
                    ) ?>

                </div>

                <div class="stat-label">
                    Inactivos
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
                'admin/usuarios.php'
            ) ?>"
        >

            <div class="row g-3">


                <div class="col-lg-5">

                    <label class="form-label">
                        Buscar usuario
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
                            placeholder="Nombre, apellido o correo..."
                        >

                    </div>

                </div>


                <div class="col-md-4 col-lg-2">

                    <label class="form-label">
                        Rol
                    </label>

                    <select
                        name="rol"
                        class="form-select"
                    >

                        <option value="">
                            Todos
                        </option>


                        <?php foreach (
                            $rolesPermitidos
                            as $rol
                        ): ?>

                            <option
                                value="<?= e($rol) ?>"
                                <?= $rolFiltro === $rol
                                    ? 'selected'
                                    : ''
                                ?>
                            >

                                <?= e(
                                    $rol === 'Tecnico'
                                        ? 'Técnico'
                                        : $rol
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

                        <option
                            value="Activo"
                            <?= $estadoFiltro === 'Activo'
                                ? 'selected'
                                : ''
                            ?>
                        >
                            Activo
                        </option>

                        <option
                            value="Inactivo"
                            <?= $estadoFiltro === 'Inactivo'
                                ? 'selected'
                                : ''
                            ?>
                        >
                            Inactivo
                        </option>

                    </select>

                </div>


                <div
                    class="col-lg-3
                           d-flex
                           align-items-end
                           gap-2"
                >

                    <button
                        type="submit"
                        class="btn btn-filter flex-fill"
                    >

                        <i class="bi bi-funnel me-1"></i>

                        Filtrar

                    </button>


                    <a
                        href="<?= url(
                            'admin/usuarios.php'
                        ) ?>"
                        class="btn btn-outline-secondary"
                    >

                        <i class="bi bi-x-lg"></i>

                    </a>

                </div>


            </div>

        </form>

    </div>


    <!-- =====================================================
         FORM + LISTA
    ====================================================== -->

    <div class="row g-4">


        <!-- =================================================
             FORMULARIO
        ================================================== -->

        <div class="col-xl-4">

            <div class="admin-card">

                <div class="admin-card-header">

                    <h5>

                        <i class="bi <?= (int)$form['id_usuario'] > 0
                            ? 'bi-pencil-square'
                            : 'bi-person-plus'
                        ?> me-2"></i>

                        <?= (int)$form['id_usuario'] > 0
                            ? 'Editar usuario'
                            : 'Nuevo usuario'
                        ?>

                    </h5>

                </div>


                <div class="admin-card-body">

                    <form
                        method="POST"
                        action="<?= url(
                            'admin/usuarios.php'
                        ) ?>"
                    >

                        <?= csrfInput() ?>


                        <input
                            type="hidden"
                            name="accion"
                            value="guardar"
                        >


                        <input
                            type="hidden"
                            name="id_usuario"
                            value="<?= (int)$form[
                                'id_usuario'
                            ] ?>"
                        >


                        <!-- NOMBRE -->

                        <div class="mb-3">

                            <label
                                for="nombre"
                                class="form-label"
                            >
                                Nombre
                            </label>

                            <input
                                type="text"
                                name="nombre"
                                id="nombre"
                                class="form-control"
                                maxlength="100"
                                value="<?= e(
                                    $form['nombre']
                                ) ?>"
                                required
                            >

                        </div>


                        <!-- APELLIDO -->

                        <div class="mb-3">

                            <label
                                for="apellido"
                                class="form-label"
                            >
                                Apellido
                            </label>

                            <input
                                type="text"
                                name="apellido"
                                id="apellido"
                                class="form-control"
                                maxlength="100"
                                value="<?= e(
                                    $form['apellido']
                                ) ?>"
                                required
                            >

                        </div>


                        <!-- CORREO -->

                        <div class="mb-3">

                            <label
                                for="correo"
                                class="form-label"
                            >
                                Correo
                            </label>

                            <input
                                type="email"
                                name="correo"
                                id="correo"
                                class="form-control"
                                maxlength="190"
                                value="<?= e(
                                    $form['correo']
                                ) ?>"
                                placeholder="docente@colegio.edu.ar"
                                autocomplete="off"
                                required
                            >

                            <div class="form-help">

                                El correo será el usuario
                                para ingresar al sistema.

                            </div>

                        </div>


                        <!-- TELÉFONO -->

                        <div class="mb-3">

                            <label
                                for="telefono"
                                class="form-label"
                            >
                                Teléfono / WhatsApp
                            </label>

                            <input
                                type="tel"
                                name="telefono"
                                id="telefono"
                                class="form-control"
                                maxlength="30"
                                placeholder="Ej.: 351 555-1234"
                                value="<?= e(
                                    $form['telefono']
                                    ?? ''
                                ) ?>"
                            >

                            <div class="form-help">

                                Especialmente importante en
                                técnicos: se usa para avisar
                                por WhatsApp cuando llega
                                un nuevo ticket.

                            </div>

                        </div>


                        <!-- WHATSAPP APIKEY -->

                        <div class="mb-3">

                            <label
                                for="whatsapp_apikey"
                                class="form-label"
                            >
                                Apikey de WhatsApp (opcional)
                            </label>

                            <input
                                type="text"
                                name="whatsapp_apikey"
                                id="whatsapp_apikey"
                                class="form-control"
                                maxlength="20"
                                placeholder="Ej.: 123456"
                                value="<?= e(
                                    $form['whatsapp_apikey']
                                    ?? ''
                                ) ?>"
                            >

                            <div class="form-help">

                                Si se completa (junto con el
                                teléfono), los avisos de
                                WhatsApp se envían solos, sin
                                que la persona tenga que
                                apretar nada. Para conseguir la
                                apikey: desde el WhatsApp de
                                esta persona, entrar a
                                callmebot.com/blog/free-api-whatsapp-messages
                                para ver el número de contacto
                                vigente (CallMeBot cambia ese
                                número cuando se satura),
                                agregarlo y enviarle el
                                mensaje "I allow callmebot to
                                send me messages". El bot
                                responde con el número de
                                apikey.

                            </div>

                        </div>


                        <!-- DNI -->

                        <div class="mb-3">

                            <label
                                for="dni"
                                class="form-label"
                            >

                                <?= (int)$form['id_usuario'] > 0
                                    ? 'Nuevo DNI (opcional)'
                                    : 'DNI'
                                ?>

                            </label>


                            <input
                                type="password"
                                name="dni"
                                id="dni"
                                class="form-control input-dni"
                                inputmode="numeric"
                                maxlength="12"
                                autocomplete="new-password"
                                <?= (int)$form['id_usuario'] === 0
                                    ? 'required'
                                    : ''
                                ?>
                            >


                            <div class="form-help">

                                <?php if (
                                    (int)$form[
                                        'id_usuario'
                                    ] > 0
                                ): ?>

                                    Dejalo vacío para mantener
                                    el DNI actual.

                                <?php else: ?>

                                    El DNI se guarda cifrado
                                    mediante un hash seguro.

                                <?php endif; ?>

                            </div>

                        </div>


                        <!-- ROL -->

                        <div class="mb-3">

                            <label
                                for="rol"
                                class="form-label"
                            >
                                Rol
                            </label>


                            <select
                                name="rol"
                                id="rol"
                                class="form-select"
                                required
                            >

                                <option
                                    value="Docente"
                                    <?= $form['rol'] === 'Docente'
                                        ? 'selected'
                                        : ''
                                    ?>
                                >
                                    Docente
                                </option>

                                <option
                                    value="Tecnico"
                                    <?= $form['rol'] === 'Tecnico'
                                        ? 'selected'
                                        : ''
                                    ?>
                                >
                                    Técnico
                                </option>

                                <option
                                    value="Administrador"
                                    <?= $form['rol'] === 'Administrador'
                                        ? 'selected'
                                        : ''
                                    ?>
                                >
                                    Administrador
                                </option>

                            </select>

                        </div>


                        <!-- ESTADO -->

                        <div class="mb-3">

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
                                required
                            >

                                <option
                                    value="Activo"
                                    <?= $form['estado'] === 'Activo'
                                        ? 'selected'
                                        : ''
                                    ?>
                                >
                                    Activo
                                </option>

                                <option
                                    value="Inactivo"
                                    <?= $form['estado'] === 'Inactivo'
                                        ? 'selected'
                                        : ''
                                    ?>
                                >
                                    Inactivo
                                </option>

                            </select>

                        </div>


                        <!-- GUARDAR -->

                        <div class="d-grid gap-2">

                            <button
                                type="submit"
                                class="btn btn-guardar"
                            >

                                <i class="bi bi-floppy me-1"></i>

                                <?= (int)$form['id_usuario'] > 0
                                    ? 'Guardar cambios'
                                    : 'Crear usuario'
                                ?>

                            </button>


                            <?php if (
                                (int)$form['id_usuario']
                                > 0
                            ): ?>

                                <a
                                    href="<?= url(
                                        'admin/usuarios.php'
                                    ) ?>"
                                    class="btn btn-outline-secondary"
                                >
                                    Cancelar edición
                                </a>

                            <?php endif; ?>

                        </div>


                    </form>


                    <!-- =====================================
                         CAMBIAR DNI
                    ====================================== -->

                    <?php if (
                        (int)$form['id_usuario']
                        > 0
                    ): ?>

                        <hr class="my-4">


                        <h6
                            class="fw-bold"
                            style="color:#760000;"
                        >

                            <i class="bi bi-key me-1"></i>

                            Restablecer DNI

                        </h6>


                        <form
                            method="POST"
                            action="<?= url(
                                'admin/usuarios.php'
                            ) ?>"
                            id="formDni"
                        >

                            <?= csrfInput() ?>


                            <input
                                type="hidden"
                                name="accion"
                                value="dni"
                            >


                            <input
                                type="hidden"
                                name="id_usuario"
                                value="<?= (int)$form[
                                    'id_usuario'
                                ] ?>"
                            >


                            <div class="input-group">

                                <input
                                    type="password"
                                    name="nuevo_dni"
                                    id="nuevo_dni"
                                    class="form-control"
                                    inputmode="numeric"
                                    placeholder="Nuevo DNI"
                                    required
                                >


                                <button
                                    type="button"
                                    class="btn btn-outline-secondary"
                                    id="mostrarDni"
                                    title="Mostrar DNI"
                                >

                                    <i class="bi bi-eye"></i>

                                </button>

                            </div>


                            <button
                                type="submit"
                                class="btn btn-outline-danger w-100 mt-2"
                            >

                                <i class="bi bi-key me-1"></i>

                                Actualizar DNI

                            </button>

                        </form>

                    <?php endif; ?>


                    <div class="security-box">

                        <i class="bi bi-shield-lock me-1"></i>

                        <strong>Seguridad:</strong>

                        el DNI no se almacena como texto.
                        Se guarda utilizando
                        <code>password_hash()</code>.
                        Por ese motivo el administrador
                        puede reemplazarlo, pero no consultar
                        el DNI actual.

                    </div>

                </div>

            </div>

        </div>


        <!-- =================================================
             LISTA
        ================================================== -->

        <div class="col-xl-8">

            <div class="admin-card">

                <div class="admin-card-header">

                    <h5>

                        <i class="bi bi-list-ul me-2"></i>

                        Usuarios registrados

                    </h5>

                    <span class="small text-muted">

                        <?= $totalRegistros ?>

                        <?= $totalRegistros === 1
                            ? 'usuario'
                            : 'usuarios'
                        ?>

                    </span>

                </div>


                <?php if (
                    empty($usuarios)
                ): ?>

                    <div class="empty-state">

                        <i class="bi bi-person-x"></i>

                        <strong>
                            No se encontraron usuarios
                        </strong>

                        <div class="mt-1">

                            Modificá los filtros
                            o cargá un nuevo usuario.

                        </div>

                    </div>


                <?php else: ?>

                    <div class="table-responsive">

                        <table class="table">

                            <thead>

                                <tr>

                                    <th>
                                        Usuario
                                    </th>

                                    <th>
                                        Rol
                                    </th>

                                    <th>
                                        Estado
                                    </th>

                                    <th>
                                        Actividad
                                    </th>

                                    <th>
                                        Alta
                                    </th>

                                    <th class="text-center">
                                        Acciones
                                    </th>

                                </tr>

                            </thead>


                            <tbody>


                                <?php foreach (
                                    $usuarios
                                    as $usuario
                                ): ?>

                                    <?php

                                    $iniciales =
                                        mb_strtoupper(
                                            mb_substr(
                                                $usuario['nombre'],
                                                0,
                                                1
                                            )
                                            .
                                            mb_substr(
                                                $usuario['apellido'],
                                                0,
                                                1
                                            )
                                        );

                                    ?>


                                    <tr>


                                        <!-- USUARIO -->

                                        <td>

                                            <div class="usuario-info">

                                                <div class="usuario-avatar">

                                                    <?= e(
                                                        $iniciales
                                                    ) ?>

                                                </div>


                                                <div>

                                                    <div class="usuario-nombre">

                                                        <?= e(
                                                            trim(
                                                                $usuario[
                                                                    'nombre'
                                                                ]
                                                                .
                                                                ' '
                                                                .
                                                                $usuario[
                                                                    'apellido'
                                                                ]
                                                            )
                                                        ) ?>


                                                        <?php if (
                                                            (int)$usuario[
                                                                'id_usuario'
                                                            ]
                                                            ===
                                                            (int)usuarioId()
                                                        ): ?>

                                                            <span
                                                                class="badge bg-dark ms-1"
                                                                style="font-size:8px;"
                                                            >
                                                                Vos
                                                            </span>

                                                        <?php endif; ?>

                                                    </div>


                                                    <div class="usuario-correo">

                                                        <?= e(
                                                            $usuario[
                                                                'correo'
                                                            ]
                                                        ) ?>

                                                    </div>


                                                    <?php if (
                                                        !empty(
                                                            $usuario[
                                                                'telefono'
                                                            ]
                                                        )
                                                    ): ?>

                                                        <div class="usuario-correo">

                                                            <i class="bi bi-telephone"></i>

                                                            <?= e(
                                                                $usuario[
                                                                    'telefono'
                                                                ]
                                                            ) ?>

                                                        </div>

                                                    <?php endif; ?>

                                                </div>

                                            </div>

                                        </td>


                                        <!-- ROL -->

                                        <td>

                                            <?php

                                            $claseRol =
                                                match (
                                                    $usuario['rol']
                                                ) {

                                                    'Docente'
                                                        =>
                                                        'rol-docente',

                                                    'Tecnico'
                                                        =>
                                                        'rol-tecnico',

                                                    'Administrador'
                                                        =>
                                                        'rol-administrador',

                                                    default
                                                        =>
                                                        'rol-docente'

                                                };


                                            $iconoRol =
                                                match (
                                                    $usuario['rol']
                                                ) {

                                                    'Docente'
                                                        =>
                                                        'bi-person',

                                                    'Tecnico'
                                                        =>
                                                        'bi-person-gear',

                                                    'Administrador'
                                                        =>
                                                        'bi-shield-check',

                                                    default
                                                        =>
                                                        'bi-person'

                                                };

                                            ?>


                                            <span
                                                class="rol-badge <?= e(
                                                    $claseRol
                                                ) ?>"
                                            >

                                                <i class="bi <?= e(
                                                    $iconoRol
                                                ) ?>"></i>


                                                <?= e(
                                                    $usuario['rol']
                                                    === 'Tecnico'
                                                        ? 'Técnico'
                                                        : $usuario['rol']
                                                ) ?>

                                            </span>

                                        </td>


                                        <!-- ESTADO -->

                                        <td>

                                            <?php if (
                                                $usuario['estado']
                                                === 'Activo'
                                            ): ?>

                                                <span class="estado-activo">

                                                    <i class="bi bi-check-circle"></i>

                                                    Activo

                                                </span>

                                            <?php else: ?>

                                                <span class="estado-inactivo">

                                                    <i class="bi bi-pause-circle"></i>

                                                    Inactivo

                                                </span>

                                            <?php endif; ?>

                                        </td>


                                        <!-- ACTIVIDAD -->

                                        <td>

                                            <div class="activity">


                                                <?php if (
                                                    $usuario['rol']
                                                    === 'Docente'
                                                ): ?>

                                                    <span>

                                                        <i class="bi bi-ticket"></i>

                                                        <?= (int)$usuario[
                                                            'solicitudes_creadas'
                                                        ] ?>
                                                        solicitudes

                                                    </span>

                                                <?php endif; ?>


                                                <?php if (
                                                    in_array(
                                                        $usuario['rol'],
                                                        [
                                                            'Tecnico',
                                                            'Administrador'
                                                        ],
                                                        true
                                                    )
                                                ): ?>

                                                    <span>

                                                        <i class="bi bi-tools"></i>

                                                        <?= (int)$usuario[
                                                            'intervenciones'
                                                        ] ?>
                                                        interv.

                                                    </span>


                                                    <span>

                                                        <i class="bi bi-person-check"></i>

                                                        <?= (int)$usuario[
                                                            'asignaciones_activas'
                                                        ] ?>
                                                        asignadas

                                                    </span>

                                                <?php endif; ?>


                                                <?php if (
                                                    $usuario['rol']
                                                    === 'Administrador'
                                                ): ?>

                                                    <span>

                                                        <i class="bi bi-shield-check"></i>

                                                        Administración

                                                    </span>

                                                <?php endif; ?>


                                            </div>

                                        </td>


                                        <!-- ALTA -->

                                        <td>

                                            <div class="small text-muted">

                                                <?= e(
                                                    fechaCorta(
                                                        $usuario[
                                                            'fecha_creacion'
                                                        ]
                                                    )
                                                ) ?>

                                            </div>

                                        </td>


                                        <!-- ACCIONES -->

                                        <td>

                                            <div class="actions">


                                                <!-- EDITAR -->

                                                <a
                                                    href="<?= url(
                                                        'admin/usuarios.php?editar='
                                                        .
                                                        (int)$usuario[
                                                            'id_usuario'
                                                        ]
                                                    ) ?>"
                                                    class="action-button action-edit"
                                                    title="Editar usuario"
                                                >

                                                    <i class="bi bi-pencil"></i>

                                                </a>


                                                <!-- WHATSAPP -->

                                                <?php

                                                $enlaceWa =
                                                    enlaceWhatsapp(
                                                        $usuario['telefono']
                                                        ?? null
                                                    );

                                                ?>

                                                <?php if ($enlaceWa): ?>

                                                    <a
                                                        href="<?= e($enlaceWa) ?>"
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        class="action-button"
                                                        style="color:#25D366;background:#E7F9EF;"
                                                        title="Contactar por WhatsApp"
                                                    >

                                                        <i class="bi bi-whatsapp"></i>

                                                    </a>

                                                <?php endif; ?>


                                                <!-- ACTIVAR / DESACTIVAR -->

                                                <?php if (
                                                    (int)$usuario[
                                                        'id_usuario'
                                                    ]
                                                    !==
                                                    (int)usuarioId()
                                                ): ?>

                                                    <form
                                                        method="POST"
                                                        action="<?= url(
                                                            'admin/usuarios.php'
                                                        ) ?>"
                                                        class="m-0 form-estado"
                                                        data-nombre="<?= e(
                                                            trim(
                                                                $usuario[
                                                                    'nombre'
                                                                ]
                                                                .
                                                                ' '
                                                                .
                                                                $usuario[
                                                                    'apellido'
                                                                ]
                                                            )
                                                        ) ?>"
                                                        data-estado="<?= e(
                                                            $usuario[
                                                                'estado'
                                                            ]
                                                        ) ?>"
                                                    >

                                                        <?= csrfInput() ?>


                                                        <input
                                                            type="hidden"
                                                            name="accion"
                                                            value="estado"
                                                        >


                                                        <input
                                                            type="hidden"
                                                            name="id_usuario"
                                                            value="<?= (int)$usuario[
                                                                'id_usuario'
                                                            ] ?>"
                                                        >


                                                        <button
                                                            type="submit"
                                                            class="action-button action-state"
                                                            title="<?= $usuario[
                                                                'estado'
                                                            ] === 'Activo'
                                                                ? 'Desactivar usuario'
                                                                : 'Activar usuario'
                                                            ?>"
                                                        >

                                                            <i
                                                                class="bi <?= $usuario[
                                                                    'estado'
                                                                ] === 'Activo'
                                                                    ? 'bi-pause'
                                                                    : 'bi-play'
                                                                ?>"
                                                            ></i>

                                                        </button>

                                                    </form>

                                                <?php endif; ?>


                                            </div>

                                        </td>


                                    </tr>

                                <?php endforeach; ?>


                            </tbody>

                        </table>

                    </div>


                    <!-- =====================================
                         PAGINACIÓN
                    ====================================== -->

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
                                                    urlPaginaUsuarios(
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
                                                    urlPaginaUsuarios(
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
                                                    urlPaginaUsuarios(
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


                <?php endif; ?>


            </div>

        </div>


    </div>


</div>


<script>

// ============================================================
// SOLO NÚMEROS EN DNI
// ============================================================

document
    .querySelectorAll(
        '#dni, #nuevo_dni'
    )
    .forEach(
        function(input) {

            input.addEventListener(
                'input',
                function() {

                    this.value =
                        this.value.replace(
                            /\D/g,
                            ''
                        );

                }
            );

        }
    );


// ============================================================
// MOSTRAR / OCULTAR NUEVO DNI
// ============================================================

const mostrarDni =
    document.getElementById(
        'mostrarDni'
    );


const nuevoDni =
    document.getElementById(
        'nuevo_dni'
    );


if (
    mostrarDni
    &&
    nuevoDni
) {

    mostrarDni.addEventListener(
        'click',
        function() {

            const oculto =
                nuevoDni.type
                === 'password';


            nuevoDni.type =
                oculto
                    ? 'text'
                    : 'password';


            this.innerHTML =
                oculto
                    ? '<i class="bi bi-eye-slash"></i>'
                    : '<i class="bi bi-eye"></i>';

        }
    );

}


// ============================================================
// CONFIRMAR CAMBIO DE ESTADO
// ============================================================

document
    .querySelectorAll(
        '.form-estado'
    )
    .forEach(
        function(formulario) {

            formulario.addEventListener(
                'submit',
                function(evento) {

                    const nombre =
                        this.dataset.nombre;

                    const estado =
                        this.dataset.estado;


                    const accion =
                        estado === 'Activo'
                            ? 'desactivar'
                            : 'activar';


                    const confirmar =
                        confirm(
                            '¿Seguro que querés '
                            +
                            accion
                            +
                            ' a '
                            +
                            nombre
                            +
                            '?'
                        );


                    if (!confirmar) {

                        evento.preventDefault();

                    }

                }
            );

        }
    );


// ============================================================
// CONFIRMAR CAMBIO DE DNI
// ============================================================

const formDni =
    document.getElementById(
        'formDni'
    );


if (formDni) {

    formDni.addEventListener(
        'submit',
        function(evento) {

            const confirmar =
                confirm(
                    '¿Seguro que querés cambiar el DNI de acceso de este usuario?'
                );


            if (!confirmar) {

                evento.preventDefault();

            }

        }
    );

}

</script>


<?php

require_once __DIR__
    . '/../includes/footer.php';

?>