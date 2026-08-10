<?php
// ============================================================
// COLEGIO SAN JOSÉ
// Sistema de Solicitudes e Intervenciones
// Archivo: includes/funciones.php
// ============================================================

declare(strict_types=1);


// ============================================================
// ESCAPAR TEXTO PARA HTML
// Evita problemas de XSS al mostrar información de la BD
// ============================================================

function e(?string $texto): string
{
    return htmlspecialchars(
        $texto ?? '',
        ENT_QUOTES,
        'UTF-8'
    );
}


// ============================================================
// REDIRECCIONAR
// ============================================================

function redireccionar(string $ruta): never
{
    header("Location: " . $ruta);
    exit;
}


// ============================================================
// VERIFICAR MÉTODO POST
// ============================================================

function esPost(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}


// ============================================================
// LIMPIAR TEXTO
// ============================================================

function limpiarTexto(?string $texto): string
{
    return trim($texto ?? '');
}


// ============================================================
// VALIDAR EMAIL
// ============================================================

function emailValido(string $correo): bool
{
    return filter_var($correo, FILTER_VALIDATE_EMAIL) !== false;
}


// ============================================================
// FORMATEAR FECHA
// ============================================================

function fechaArgentina(?string $fecha): string
{
    if (empty($fecha)) {
        return '-';
    }

    try {

        $date = new DateTime($fecha);

        return $date->format('d/m/Y H:i');

    } catch (Exception $e) {

        return '-';
    }
}


// ============================================================
// FORMATEAR SOLO FECHA
// ============================================================

function fechaCorta(?string $fecha): string
{
    if (empty($fecha)) {
        return '-';
    }

    try {

        $date = new DateTime($fecha);

        return $date->format('d/m/Y');

    } catch (Exception $e) {

        return '-';
    }
}


// ============================================================
// FORMATEAR HORA
// ============================================================

function horaCorta(?string $hora): string
{
    if (empty($hora)) {
        return '-';
    }

    try {

        $date = new DateTime($hora);

        return $date->format('H:i');

    } catch (Exception $e) {

        return '-';
    }
}


// ============================================================
// FORMATEAR DINERO
// ============================================================

function formatoDinero(
    float|int|string|null $importe
): string {

    if ($importe === null || $importe === '') {
        return '-';
    }

    return '$ ' . number_format(
        (float)$importe,
        2,
        ',',
        '.'
    );
}


// ============================================================
// OBTENER USUARIO POR ID
// ============================================================

function obtenerUsuario(
    PDO $conexion,
    int $idUsuario
): ?array {

    $sql = "
        SELECT
            id_usuario,
            nombre,
            apellido,
            correo,
            rol,
            estado,
            ultimo_acceso,
            fecha_creacion
        FROM usuarios
        WHERE id_usuario = ?
        LIMIT 1
    ";

    $stmt = $conexion->prepare($sql);

    $stmt->execute([
        $idUsuario
    ]);

    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    return $usuario ?: null;
}


// ============================================================
// OBTENER USUARIO POR CORREO
// Usado principalmente en login.php
// ============================================================

function obtenerUsuarioPorCorreo(
    PDO $conexion,
    string $correo
): ?array {

    $sql = "
        SELECT *
        FROM usuarios
        WHERE correo = ?
        LIMIT 1
    ";

    $stmt = $conexion->prepare($sql);

    $stmt->execute([
        $correo
    ]);

    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    return $usuario ?: null;
}


// ============================================================
// NOMBRE COMPLETO DEL USUARIO
// ============================================================

function nombreCompleto(array $usuario): string
{
    return trim(
        ($usuario['nombre'] ?? '') .
        ' ' .
        ($usuario['apellido'] ?? '')
    );
}


// ============================================================
// OBTENER SECTORES ACTIVOS
// ============================================================

function obtenerSectores(PDO $conexion): array
{
    $sql = "
        SELECT *
        FROM sectores
        WHERE activo = 1
        ORDER BY nombre ASC
    ";

    $stmt = $conexion->query($sql);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


// ============================================================
// OBTENER UN SECTOR
// ============================================================

function obtenerSector(
    PDO $conexion,
    int $idSector
): ?array {

    $sql = "
        SELECT *
        FROM sectores
        WHERE id_sector = ?
        LIMIT 1
    ";

    $stmt = $conexion->prepare($sql);

    $stmt->execute([
        $idSector
    ]);

    $sector = $stmt->fetch(PDO::FETCH_ASSOC);

    return $sector ?: null;
}


// ============================================================
// OBTENER CATEGORÍAS POR TIPO
// Informatica / Mantenimiento
// ============================================================

function obtenerCategorias(
    PDO $conexion,
    string $tipo
): array {

    $sql = "
        SELECT *
        FROM categorias
        WHERE tipo = ?
        AND activo = 1
        ORDER BY nombre ASC
    ";

    $stmt = $conexion->prepare($sql);

    $stmt->execute([
        $tipo
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


// ============================================================
// OBTENER TODAS LAS CATEGORÍAS
// ============================================================

function obtenerTodasCategorias(PDO $conexion): array
{
    $sql = "
        SELECT *
        FROM categorias
        WHERE activo = 1
        ORDER BY tipo ASC, nombre ASC
    ";

    $stmt = $conexion->query($sql);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


// ============================================================
// OBTENER SOLICITUD
// ============================================================

function obtenerSolicitud(
    PDO $conexion,
    int $idSolicitud
): ?array {

    $sql = "
        SELECT
            s.*,

            u.nombre,
            u.apellido,
            u.correo,

            sec.nombre AS sector,

            c.nombre AS categoria

        FROM solicitudes s

        INNER JOIN usuarios u
            ON s.id_usuario = u.id_usuario

        LEFT JOIN sectores sec
            ON s.id_sector = sec.id_sector

        LEFT JOIN categorias c
            ON s.id_categoria = c.id_categoria

        WHERE s.id_solicitud = ?

        LIMIT 1
    ";

    $stmt = $conexion->prepare($sql);

    $stmt->execute([
        $idSolicitud
    ]);

    $solicitud = $stmt->fetch(PDO::FETCH_ASSOC);

    return $solicitud ?: null;
}


// ============================================================
// OBTENER SOLICITUDES DE UN DOCENTE
// ============================================================

function obtenerSolicitudesUsuario(
    PDO $conexion,
    int $idUsuario,
    int $limite = 50
): array {

    $limite = max(1, min($limite, 200));

    $sql = "
        SELECT
            s.*,

            sec.nombre AS sector,

            c.nombre AS categoria

        FROM solicitudes s

        LEFT JOIN sectores sec
            ON s.id_sector = sec.id_sector

        LEFT JOIN categorias c
            ON s.id_categoria = c.id_categoria

        WHERE s.id_usuario = ?

        ORDER BY
            s.fecha_creacion DESC

        LIMIT {$limite}
    ";

    $stmt = $conexion->prepare($sql);

    $stmt->execute([
        $idUsuario
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


// ============================================================
// OBTENER TODAS LAS SOLICITUDES
// Técnico / Administrador
// ============================================================

function obtenerSolicitudes(
    PDO $conexion,
    ?string $estado = null,
    ?string $tipo = null
): array {

    $condiciones = [];
    $parametros = [];

    if (!empty($estado)) {

        $condiciones[] = "s.estado = ?";

        $parametros[] = $estado;
    }

    if (!empty($tipo)) {

        $condiciones[] = "s.tipo = ?";

        $parametros[] = $tipo;
    }

    $where = '';

    if (!empty($condiciones)) {

        $where = "
            WHERE " .
            implode(
                " AND ",
                $condiciones
            );
    }

    $sql = "
        SELECT
            s.*,

            CONCAT(
                u.nombre,
                ' ',
                u.apellido
            ) AS solicitante,

            u.correo,

            sec.nombre AS sector,

            c.nombre AS categoria

        FROM solicitudes s

        INNER JOIN usuarios u
            ON s.id_usuario = u.id_usuario

        LEFT JOIN sectores sec
            ON s.id_sector = sec.id_sector

        LEFT JOIN categorias c
            ON s.id_categoria = c.id_categoria

        {$where}

        ORDER BY

            CASE s.prioridad

                WHEN 'Urgente' THEN 1
                WHEN 'Alta' THEN 2
                WHEN 'Normal' THEN 3
                WHEN 'Baja' THEN 4

                ELSE 5

            END,

            s.fecha_creacion DESC
    ";

    $stmt = $conexion->prepare($sql);

    $stmt->execute($parametros);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


// ============================================================
// CONTAR SOLICITUDES DE UN USUARIO
// ============================================================

function contarSolicitudesUsuario(
    PDO $conexion,
    int $idUsuario,
    ?string $estado = null
): int {

    $sql = "
        SELECT COUNT(*)
        FROM solicitudes
        WHERE id_usuario = ?
    ";

    $parametros = [
        $idUsuario
    ];

    if (!empty($estado)) {

        $sql .= "
            AND estado = ?
        ";

        $parametros[] = $estado;
    }

    $stmt = $conexion->prepare($sql);

    $stmt->execute($parametros);

    return (int)$stmt->fetchColumn();
}


// ============================================================
// CONTAR TODAS LAS SOLICITUDES POR ESTADO
// ============================================================

function contarSolicitudes(
    PDO $conexion,
    ?string $estado = null
): int {

    if ($estado === null) {

        $stmt = $conexion->query("
            SELECT COUNT(*)
            FROM solicitudes
        ");

        return (int)$stmt->fetchColumn();
    }

    $stmt = $conexion->prepare("
        SELECT COUNT(*)
        FROM solicitudes
        WHERE estado = ?
    ");

    $stmt->execute([
        $estado
    ]);

    return (int)$stmt->fetchColumn();
}


// ============================================================
// ESTADÍSTICAS DASHBOARD
// ============================================================

function obtenerEstadisticas(PDO $conexion): array
{
    $sql = "
        SELECT

            COUNT(*) AS total,

            SUM(
                CASE
                    WHEN estado = 'Nueva'
                    THEN 1
                    ELSE 0
                END
            ) AS nuevas,

            SUM(
                CASE
                    WHEN estado = 'Asignada'
                    THEN 1
                    ELSE 0
                END
            ) AS asignadas,

            SUM(
                CASE
                    WHEN estado = 'En proceso'
                    THEN 1
                    ELSE 0
                END
            ) AS en_proceso,

            SUM(
                CASE
                    WHEN estado = 'Pendiente'
                    THEN 1
                    ELSE 0
                END
            ) AS pendientes,

            SUM(
                CASE
                    WHEN estado = 'Resuelta'
                    THEN 1
                    ELSE 0
                END
            ) AS resueltas,

            SUM(
                CASE
                    WHEN estado = 'Cerrada'
                    THEN 1
                    ELSE 0
                END
            ) AS cerradas,

            SUM(
                CASE
                    WHEN prioridad = 'Urgente'
                    AND estado NOT IN (
                        'Resuelta',
                        'Cerrada',
                        'Cancelada'
                    )
                    THEN 1
                    ELSE 0
                END
            ) AS urgentes

        FROM solicitudes
    ";

    $stmt = $conexion->query($sql);

    $datos = $stmt->fetch(PDO::FETCH_ASSOC);

    return [
        'total'       => (int)($datos['total'] ?? 0),
        'nuevas'      => (int)($datos['nuevas'] ?? 0),
        'asignadas'   => (int)($datos['asignadas'] ?? 0),
        'en_proceso'  => (int)($datos['en_proceso'] ?? 0),
        'pendientes'  => (int)($datos['pendientes'] ?? 0),
        'resueltas'   => (int)($datos['resueltas'] ?? 0),
        'cerradas'    => (int)($datos['cerradas'] ?? 0),
        'urgentes'    => (int)($datos['urgentes'] ?? 0)
    ];
}


// ============================================================
// ESTADÍSTICAS DEL DOCENTE
// ============================================================

function obtenerEstadisticasUsuario(
    PDO $conexion,
    int $idUsuario
): array {

    $sql = "
        SELECT

            COUNT(*) AS total,

            SUM(
                CASE
                    WHEN estado = 'Nueva'
                    THEN 1
                    ELSE 0
                END
            ) AS nuevas,

            SUM(
                CASE
                    WHEN estado = 'En proceso'
                    THEN 1
                    ELSE 0
                END
            ) AS en_proceso,

            SUM(
                CASE
                    WHEN estado = 'Pendiente'
                    THEN 1
                    ELSE 0
                END
            ) AS pendientes,

            SUM(
                CASE
                    WHEN estado = 'Resuelta'
                    THEN 1
                    ELSE 0
                END
            ) AS resueltas

        FROM solicitudes

        WHERE id_usuario = ?
    ";

    $stmt = $conexion->prepare($sql);

    $stmt->execute([
        $idUsuario
    ]);

    $datos = $stmt->fetch(PDO::FETCH_ASSOC);

    return [
        'total'      => (int)($datos['total'] ?? 0),
        'nuevas'     => (int)($datos['nuevas'] ?? 0),
        'en_proceso' => (int)($datos['en_proceso'] ?? 0),
        'pendientes' => (int)($datos['pendientes'] ?? 0),
        'resueltas'  => (int)($datos['resueltas'] ?? 0)
    ];
}


// ============================================================
// CAMBIAR ESTADO DE UNA SOLICITUD
// También registra el cambio en el historial
// ============================================================

function cambiarEstadoSolicitud(
    PDO $conexion,
    int $idSolicitud,
    string $nuevoEstado,
    int $idUsuario,
    ?string $descripcion = null,
    ?string $tipoPendiente = null
): bool {

    $estadosPermitidos = [
        'Nueva',
        'Asignada',
        'En proceso',
        'Pendiente',
        'Resuelta',
        'Cerrada',
        'Cancelada'
    ];

    if (!in_array(
        $nuevoEstado,
        $estadosPermitidos,
        true
    )) {

        return false;
    }

    $solicitud = obtenerSolicitud(
        $conexion,
        $idSolicitud
    );

    if (!$solicitud) {
        return false;
    }

    $estadoAnterior = $solicitud['estado'];

    try {

        $conexion->beginTransaction();


        // ====================================================
        // Actualizar solicitud
        //
        // Si se indica $tipoPendiente, también se guarda en
        // solicitudes.tipo_pendiente (uso opcional y
        // retrocompatible: los llamados existentes que no
        // pasan este argumento siguen funcionando igual).
        // ====================================================

        if ($nuevoEstado === 'Resuelta') {

            $sql = "
                UPDATE solicitudes
                SET
                    estado = ?,
                    fecha_resolucion = NOW()
                    " . ($tipoPendiente !== null
                        ? ", tipo_pendiente = ?"
                        : ""
                    ) . "
                WHERE id_solicitud = ?
            ";

        } else {

            $sql = "
                UPDATE solicitudes
                SET estado = ?
                    " . ($tipoPendiente !== null
                        ? ", tipo_pendiente = ?"
                        : ""
                    ) . "
                WHERE id_solicitud = ?
            ";
        }

        $parametros = [
            $nuevoEstado
        ];

        if ($tipoPendiente !== null) {
            $parametros[] = $tipoPendiente;
        }

        $parametros[] = $idSolicitud;

        $stmt = $conexion->prepare($sql);

        $stmt->execute($parametros);


        // ====================================================
        // Registrar historial
        // ====================================================

        $sqlHistorial = "
            INSERT INTO solicitud_historial
            (
                id_solicitud,
                id_usuario,
                estado_anterior,
                estado_nuevo,
                descripcion
            )
            VALUES (?, ?, ?, ?, ?)
        ";

        $stmtHistorial = $conexion->prepare(
            $sqlHistorial
        );

        $stmtHistorial->execute([
            $idSolicitud,
            $idUsuario,
            $estadoAnterior,
            $nuevoEstado,
            $descripcion
        ]);


        $conexion->commit();

        return true;

    } catch (Throwable $e) {

        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }

        return false;
    }
}


// ============================================================
// OBTENER HISTORIAL DE UNA SOLICITUD
// ============================================================

function obtenerHistorialSolicitud(
    PDO $conexion,
    int $idSolicitud
): array {

    $sql = "
        SELECT
            h.*,

            CONCAT(
                u.nombre,
                ' ',
                u.apellido
            ) AS usuario

        FROM solicitud_historial h

        LEFT JOIN usuarios u
            ON h.id_usuario = u.id_usuario

        WHERE h.id_solicitud = ?

        ORDER BY
            h.fecha ASC,
            h.id_historial ASC
    ";

    $stmt = $conexion->prepare($sql);

    $stmt->execute([
        $idSolicitud
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


// ============================================================
// OBTENER COMENTARIOS
// ============================================================

function obtenerComentarios(
    PDO $conexion,
    int $idSolicitud
): array {

    $sql = "
        SELECT
            c.*,

            CONCAT(
                u.nombre,
                ' ',
                u.apellido
            ) AS usuario,

            u.rol

        FROM comentarios c

        INNER JOIN usuarios u
            ON c.id_usuario = u.id_usuario

        WHERE c.id_solicitud = ?

        ORDER BY c.fecha ASC
    ";

    $stmt = $conexion->prepare($sql);

    $stmt->execute([
        $idSolicitud
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


// ============================================================
// AGREGAR COMENTARIO
// ============================================================

function agregarComentario(
    PDO $conexion,
    int $idSolicitud,
    int $idUsuario,
    string $comentario
): bool {

    $comentario = limpiarTexto(
        $comentario
    );

    if ($comentario === '') {
        return false;
    }

    $sql = "
        INSERT INTO comentarios
        (
            id_solicitud,
            id_usuario,
            comentario
        )
        VALUES (?, ?, ?)
    ";

    $stmt = $conexion->prepare($sql);

    return $stmt->execute([
        $idSolicitud,
        $idUsuario,
        $comentario
    ]);
}


// ============================================================
// OBTENER IMÁGENES DE SOLICITUD
// ============================================================

function obtenerImagenesSolicitud(
    PDO $conexion,
    int $idSolicitud
): array {

    $sql = "
        SELECT *
        FROM solicitud_imagenes
        WHERE id_solicitud = ?
        ORDER BY fecha ASC
    ";

    $stmt = $conexion->prepare($sql);

    $stmt->execute([
        $idSolicitud
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


// ============================================================
// OBTENER INTERVENCIONES
// ============================================================

function obtenerIntervenciones(
    PDO $conexion,
    int $idSolicitud
): array {

    $sql = "
        SELECT
            i.*,

            CONCAT(
                u.nombre,
                ' ',
                u.apellido
            ) AS tecnico

        FROM intervenciones i

        INNER JOIN usuarios u
            ON i.id_tecnico = u.id_usuario

        WHERE i.id_solicitud = ?

        ORDER BY
            i.fecha_intervencion ASC
    ";

    $stmt = $conexion->prepare($sql);

    $stmt->execute([
        $idSolicitud
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


// ============================================================
// OBTENER TÉCNICOS
// ============================================================

function obtenerTecnicos(PDO $conexion): array
{
    $sql = "
        SELECT
            id_usuario,
            nombre,
            apellido,
            correo

        FROM usuarios

        WHERE rol IN (
            'Tecnico',
            'Administrador'
        )

        AND estado = 'Activo'

        ORDER BY
            apellido ASC,
            nombre ASC
    ";

    $stmt = $conexion->query($sql);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


// ============================================================
// OBTENER TÉCNICO ACTUAL ASIGNADO
// ============================================================

function obtenerTecnicoAsignado(
    PDO $conexion,
    int $idSolicitud
): ?array {

    $sql = "
        SELECT

            a.*,

            u.nombre,
            u.apellido,
            u.correo

        FROM solicitudes_asignaciones a

        INNER JOIN usuarios u
            ON a.id_tecnico = u.id_usuario

        WHERE a.id_solicitud = ?

        AND a.activo = 1

        ORDER BY
            a.fecha_asignacion DESC

        LIMIT 1
    ";

    $stmt = $conexion->prepare($sql);

    $stmt->execute([
        $idSolicitud
    ]);

    $datos = $stmt->fetch(PDO::FETCH_ASSOC);

    return $datos ?: null;
}


// ============================================================
// ASIGNAR SOLICITUD A TÉCNICO
// ============================================================

function asignarTecnico(
    PDO $conexion,
    int $idSolicitud,
    int $idTecnico,
    int $asignadoPor
): bool {

    try {

        $conexion->beginTransaction();


        // Desactivar asignaciones anteriores

        $stmt = $conexion->prepare("
            UPDATE solicitudes_asignaciones
            SET activo = 0
            WHERE id_solicitud = ?
        ");

        $stmt->execute([
            $idSolicitud
        ]);


        // Nueva asignación

        $stmt = $conexion->prepare("
            INSERT INTO solicitudes_asignaciones
            (
                id_solicitud,
                id_tecnico,
                asignado_por
            )
            VALUES (?, ?, ?)
        ");

        $stmt->execute([
            $idSolicitud,
            $idTecnico,
            $asignadoPor
        ]);


        // Obtener estado actual

        $solicitud = obtenerSolicitud(
            $conexion,
            $idSolicitud
        );

        $estadoAnterior =
            $solicitud['estado'] ?? 'Nueva';


        // Cambiar estado

        $stmt = $conexion->prepare("
            UPDATE solicitudes
            SET estado = 'Asignada'
            WHERE id_solicitud = ?
        ");

        $stmt->execute([
            $idSolicitud
        ]);


        // Registrar historial

        $stmt = $conexion->prepare("
            INSERT INTO solicitud_historial
            (
                id_solicitud,
                id_usuario,
                estado_anterior,
                estado_nuevo,
                descripcion
            )
            VALUES (?, ?, ?, 'Asignada', ?)
        ");

        $stmt->execute([
            $idSolicitud,
            $asignadoPor,
            $estadoAnterior,
            'Solicitud asignada a un técnico.'
        ]);


        $conexion->commit();

        return true;

    } catch (Throwable $e) {

        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }

        return false;
    }
}


// ============================================================
// OBTENER HORARIOS
// ============================================================

function obtenerHorarios(
    PDO $conexion,
    ?string $tipo = null
): array {

    $diasOrden = "
        CASE dia
            WHEN 'Lunes' THEN 1
            WHEN 'Martes' THEN 2
            WHEN 'Miercoles' THEN 3
            WHEN 'Jueves' THEN 4
            WHEN 'Viernes' THEN 5
            WHEN 'Sabado' THEN 6
            ELSE 7
        END
    ";

    if ($tipo !== null) {

        $sql = "
            SELECT *
            FROM horarios_mantenimiento
            WHERE activo = 1
            AND tipo = ?
            ORDER BY
                {$diasOrden},
                hora_desde ASC
        ";

        $stmt = $conexion->prepare($sql);

        $stmt->execute([
            $tipo
        ]);

    } else {

        $sql = "
            SELECT *
            FROM horarios_mantenimiento
            WHERE activo = 1
            ORDER BY
                tipo ASC,
                {$diasOrden},
                hora_desde ASC
        ";

        $stmt = $conexion->query($sql);
    }

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


// ============================================================
// OBTENER MEJORAS
// ============================================================

function obtenerMejoras(
    PDO $conexion,
    ?string $estado = null
): array {

    $parametros = [];

    $where = '';

    if (!empty($estado)) {

        $where = "
            WHERE m.estado = ?
        ";

        $parametros[] = $estado;
    }

    $sql = "
        SELECT

            m.*,

            CONCAT(
                u.nombre,
                ' ',
                u.apellido
            ) AS usuario,

            sec.nombre AS sector

        FROM mejoras m

        INNER JOIN usuarios u
            ON m.id_usuario = u.id_usuario

        LEFT JOIN sectores sec
            ON m.id_sector = sec.id_sector

        {$where}

        ORDER BY
            m.fecha_creacion DESC
    ";

    $stmt = $conexion->prepare($sql);

    $stmt->execute($parametros);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


// ============================================================
// OBTENER MEJORA POR ID
// ============================================================

function obtenerMejora(
    PDO $conexion,
    int $idMejora
): ?array {

    $sql = "
        SELECT

            m.*,

            CONCAT(
                u.nombre,
                ' ',
                u.apellido
            ) AS usuario,

            sec.nombre AS sector

        FROM mejoras m

        INNER JOIN usuarios u
            ON m.id_usuario = u.id_usuario

        LEFT JOIN sectores sec
            ON m.id_sector = sec.id_sector

        WHERE m.id_mejora = ?

        LIMIT 1
    ";

    $stmt = $conexion->prepare($sql);

    $stmt->execute([
        $idMejora
    ]);

    $datos = $stmt->fetch(PDO::FETCH_ASSOC);

    return $datos ?: null;
}


// ============================================================
// OBTENER MATERIALES DE SOLICITUD
// ============================================================

function obtenerMaterialesSolicitud(
    PDO $conexion,
    int $idSolicitud
): array {

    $sql = "
        SELECT *
        FROM materiales
        WHERE id_solicitud = ?
        ORDER BY fecha_creacion ASC
    ";

    $stmt = $conexion->prepare($sql);

    $stmt->execute([
        $idSolicitud
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


// ============================================================
// OBTENER NOTIFICACIONES DE USUARIO
// ============================================================

function obtenerNotificaciones(
    PDO $conexion,
    int $idUsuario,
    int $limite = 20
): array {

    $limite = max(
        1,
        min($limite, 100)
    );

    $sql = "
        SELECT *
        FROM notificaciones

        WHERE id_usuario = ?

        ORDER BY
            leida ASC,
            fecha DESC

        LIMIT {$limite}
    ";

    $stmt = $conexion->prepare($sql);

    $stmt->execute([
        $idUsuario
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


// ============================================================
// CONTAR NOTIFICACIONES NO LEÍDAS
// ============================================================

function contarNotificaciones(
    PDO $conexion,
    int $idUsuario
): int {

    $sql = "
        SELECT COUNT(*)
        FROM notificaciones

        WHERE id_usuario = ?

        AND leida = 0
    ";

    $stmt = $conexion->prepare($sql);

    $stmt->execute([
        $idUsuario
    ]);

    return (int)$stmt->fetchColumn();
}


// ============================================================
// CREAR NOTIFICACIÓN
// ============================================================

function crearNotificacion(
    PDO $conexion,
    int $idUsuario,
    string $titulo,
    string $mensaje,
    ?string $enlace = null
): bool {

    $sql = "
        INSERT INTO notificaciones
        (
            id_usuario,
            titulo,
            mensaje,
            enlace
        )
        VALUES (?, ?, ?, ?)
    ";

    $stmt = $conexion->prepare($sql);

    return $stmt->execute([
        $idUsuario,
        $titulo,
        $mensaje,
        $enlace
    ]);
}


// ============================================================
// GENERAR TOKEN CSRF
// Protección para formularios
// ============================================================

function csrfToken(): string
{
    if (
        !isset($_SESSION['csrf_token']) ||
        empty($_SESSION['csrf_token'])
    ) {

        $_SESSION['csrf_token'] =
            bin2hex(
                random_bytes(32)
            );
    }

    return $_SESSION['csrf_token'];
}


// ============================================================
// INPUT OCULTO CSRF
// Uso:
//
//
// ============================================================

function csrfInput(): string
{
    return '<input type="hidden" name="csrf_token" value="' .
        e(csrfToken()) .
        '">';
}


// ============================================================
// VALIDAR TOKEN CSRF
// ============================================================

function validarCsrf(
    ?string $token
): bool {

    if (
        !isset($_SESSION['csrf_token']) ||
        empty($token)
    ) {

        return false;
    }

    return hash_equals(
        $_SESSION['csrf_token'],
        $token
    );
}


// ============================================================
// CLASE CSS PARA ESTADO
// Bootstrap
// ============================================================

function claseEstado(string $estado): string
{
    return match ($estado) {

        'Nueva'
            => 'bg-primary',

        'Asignada'
            => 'bg-info text-dark',

        'En proceso'
            => 'bg-warning text-dark',

        'Pendiente'
            => 'bg-danger',

        'Resuelta'
            => 'bg-success',

        'Cerrada'
            => 'bg-secondary',

        'Cancelada'
            => 'bg-dark',

        default
            => 'bg-secondary'
    };
}


// ============================================================
// CLASE CSS PARA PRIORIDAD
// ============================================================

function clasePrioridad(string $prioridad): string
{
    return match ($prioridad) {

        'Urgente'
            => 'bg-danger',

        'Alta'
            => 'bg-warning text-dark',

        'Normal'
            => 'bg-primary',

        'Baja'
            => 'bg-secondary',

        default
            => 'bg-secondary'
    };
}


// ============================================================
// ÍCONO SEGÚN TIPO
// Bootstrap Icons
// ============================================================

function iconoTipo(string $tipo): string
{
    return match ($tipo) {

        'Informatica'
            => 'bi-pc-display',

        'Mantenimiento'
            => 'bi-tools',

        default
            => 'bi-gear'
    };
}


// ============================================================
// TEXTO AMIGABLE DEL TIPO
// ============================================================

function nombreTipo(string $tipo): string
{
    return match ($tipo) {

        'Informatica'
            => 'Informática',

        'Mantenimiento'
            => 'Mantenimiento general',

        default
            => $tipo
    };
}


// ============================================================
// TIEMPO TRANSCURRIDO
// Ejemplo:
// Hace 3 horas
// Hace 2 días
// ============================================================

function tiempoTranscurrido(
    string $fecha
): string {

    try {

        $fechaInicio = new DateTime($fecha);

        $ahora = new DateTime();

        $diferencia = $ahora->diff(
            $fechaInicio
        );

        if ($diferencia->y > 0) {

            return 'Hace ' .
                $diferencia->y .
                (
                    $diferencia->y === 1
                    ? ' año'
                    : ' años'
                );
        }

        if ($diferencia->m > 0) {

            return 'Hace ' .
                $diferencia->m .
                (
                    $diferencia->m === 1
                    ? ' mes'
                    : ' meses'
                );
        }

        if ($diferencia->d > 0) {

            return 'Hace ' .
                $diferencia->d .
                (
                    $diferencia->d === 1
                    ? ' día'
                    : ' días'
                );
        }

        if ($diferencia->h > 0) {

            return 'Hace ' .
                $diferencia->h .
                (
                    $diferencia->h === 1
                    ? ' hora'
                    : ' horas'
                );
        }

        if ($diferencia->i > 0) {

            return 'Hace ' .
                $diferencia->i .
                (
                    $diferencia->i === 1
                    ? ' minuto'
                    : ' minutos'
                );
        }

        return 'Hace unos segundos';

    } catch (Exception $e) {

        return '';
    }
}


// ============================================================
// GENERAR NÚMERO VISUAL DE TICKET
//
// Ejemplo:
// Solicitud 8 -> SJ-000008
// ============================================================

function numeroTicket(
    int $idSolicitud
): string {

    return 'SJ-' .
        str_pad(
            (string)$idSolicitud,
            6,
            '0',
            STR_PAD_LEFT
        );
}


// ============================================================
// VALIDAR EXTENSIÓN DE IMAGEN
// ============================================================

function extensionImagenPermitida(
    string $extension
): bool {

    $permitidas = [
        'jpg',
        'jpeg',
        'png',
        'webp'
    ];

    return in_array(
        strtolower($extension),
        $permitidas,
        true
    );
}


// ============================================================
// VALIDAR MIME DE IMAGEN
// Más seguro que revisar solamente la extensión
// ============================================================

function mimeImagenPermitido(
    string $archivoTemporal
): bool {

    if (!is_file($archivoTemporal)) {
        return false;
    }

    $finfo = new finfo(
        FILEINFO_MIME_TYPE
    );

    $mime = $finfo->file(
        $archivoTemporal
    );

    $permitidos = [
        'image/jpeg',
        'image/png',
        'image/webp'
    ];

    return in_array(
        $mime,
        $permitidos,
        true
    );
}


// ============================================================
// GENERAR NOMBRE SEGURO DE IMAGEN
// ============================================================

function nombreImagenSeguro(
    string $nombreOriginal
): string {

    $extension = strtolower(
        pathinfo(
            $nombreOriginal,
            PATHINFO_EXTENSION
        )
    );

    return date('YmdHis') .
        '_' .
        bin2hex(
            random_bytes(8)
        ) .
        '.' .
        $extension;
}


// ============================================================
// SUBIR UNA IMAGEN
//
// Retorna:
//
// [
//    'ok' => true,
//    'archivo' => 'nombre.jpg'
// ]
//
// o
//
// [
//    'ok' => false,
//    'error' => '...'
// ]
//
// ============================================================

function subirImagen(
    array $archivo,
    string $directorio,
    int $maxMb = 5
): array {

    if (
        !isset($archivo['error']) ||
        $archivo['error'] !== UPLOAD_ERR_OK
    ) {

        return [
            'ok' => false,
            'error' => 'No se pudo cargar la imagen.'
        ];
    }


    // ========================================================
    // Tamaño máximo
    // ========================================================

    $maxBytes =
        $maxMb *
        1024 *
        1024;

    if (
        ($archivo['size'] ?? 0) >
        $maxBytes
    ) {

        return [
            'ok' => false,
            'error' =>
                'La imagen supera el tamaño máximo de ' .
                $maxMb .
                ' MB.'
        ];
    }


    // ========================================================
    // Extensión
    // ========================================================

    $nombreOriginal =
        $archivo['name'] ?? '';

    $extension = strtolower(
        pathinfo(
            $nombreOriginal,
            PATHINFO_EXTENSION
        )
    );

    if (
        !extensionImagenPermitida(
            $extension
        )
    ) {

        return [
            'ok' => false,
            'error' =>
                'Formato no permitido. Use JPG, PNG o WEBP.'
        ];
    }


    // ========================================================
    // MIME real
    // ========================================================

    if (
        !mimeImagenPermitido(
            $archivo['tmp_name']
        )
    ) {

        return [
            'ok' => false,
            'error' =>
                'El archivo seleccionado no es una imagen válida.'
        ];
    }


    // ========================================================
    // Crear directorio si no existe
    // ========================================================

    if (!is_dir($directorio)) {

        if (
            !mkdir(
                $directorio,
                0755,
                true
            )
        ) {

            return [
                'ok' => false,
                'error' =>
                    'No se pudo crear el directorio de imágenes.'
            ];
        }
    }


    // ========================================================
    // Nombre aleatorio
    // ========================================================

    $nombreNuevo =
        nombreImagenSeguro(
            $nombreOriginal
        );

    $destino =
        rtrim(
            $directorio,
            DIRECTORY_SEPARATOR
        ) .
        DIRECTORY_SEPARATOR .
        $nombreNuevo;


    // ========================================================
    // Mover archivo
    // ========================================================

    if (
        !move_uploaded_file(
            $archivo['tmp_name'],
            $destino
        )
    ) {

        return [
            'ok' => false,
            'error' =>
                'No se pudo guardar la imagen.'
        ];
    }


    return [
        'ok'              => true,
        'archivo'         => $nombreNuevo,
        'nombre_original' => $nombreOriginal
    ];
}


// ============================================================
// CONFIGURACIÓN DEL SISTEMA
// ============================================================

function obtenerConfiguracion(
    PDO $conexion,
    string $clave,
    ?string $default = null
): ?string {

    $sql = "
        SELECT valor
        FROM configuracion
        WHERE clave = ?
        LIMIT 1
    ";

    $stmt = $conexion->prepare($sql);

    $stmt->execute([
        $clave
    ]);

    $valor = $stmt->fetchColumn();

    if ($valor === false) {
        return $default;
    }

    return (string)$valor;
}


// ============================================================
// GENERAR MENSAJE FLASH
//
// Ejemplo:
//
// flash(
//     'success',
//     'Solicitud creada correctamente.'
// );
//
// ============================================================

function flash(
    string $tipo,
    string $mensaje
): void {

    $_SESSION['flash'] = [
        'tipo'    => $tipo,
        'mensaje' => $mensaje
    ];
}


// ============================================================
// OBTENER MENSAJE FLASH
// Se elimina automáticamente luego de mostrarlo
// ============================================================

function obtenerFlash(): ?array
{
    if (
        !isset($_SESSION['flash'])
    ) {

        return null;
    }

    $flash =
        $_SESSION['flash'];

    unset(
        $_SESSION['flash']
    );

    return $flash;
}


// ============================================================
// ============================================================
// REPUESTOS / STOCK
// ============================================================
// ============================================================


// ============================================================
// OBTENER REPUESTOS
// ============================================================

function obtenerRepuestos(
    PDO $conexion,
    bool $soloActivos = true
): array {

    $sql = "
        SELECT *
        FROM repuestos
    ";

    if ($soloActivos) {

        $sql .= "
            WHERE activo = 1
        ";
    }

    $sql .= "
        ORDER BY nombre ASC
    ";

    $stmt = $conexion->query($sql);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


// ============================================================
// OBTENER UN REPUESTO
// ============================================================

function obtenerRepuesto(
    PDO $conexion,
    int $idRepuesto
): ?array {

    $sql = "
        SELECT *
        FROM repuestos
        WHERE id_repuesto = ?
        LIMIT 1
    ";

    $stmt = $conexion->prepare($sql);

    $stmt->execute([
        $idRepuesto
    ]);

    $repuesto = $stmt->fetch(PDO::FETCH_ASSOC);

    return $repuesto ?: null;
}


// ============================================================
// CREAR REPUESTO
// ============================================================

function crearRepuesto(
    PDO $conexion,
    array $datos
): int|false {

    $sql = "
        INSERT INTO repuestos
        (
            nombre,
            descripcion,
            categoria,
            unidad,
            stock_actual,
            stock_minimo,
            costo_unitario,
            ubicacion,
            activo
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
            ?
        )
    ";

    $stmt = $conexion->prepare($sql);

    $ok = $stmt->execute([

        $datos['nombre'],

        (($datos['descripcion'] ?? '') !== '')
            ? $datos['descripcion']
            : null,

        $datos['categoria'],

        $datos['unidad'],

        (int)($datos['stock_actual'] ?? 0),

        (int)($datos['stock_minimo'] ?? 0),

        (($datos['costo_unitario'] ?? '') !== '')
            ? (float)$datos['costo_unitario']
            : null,

        (($datos['ubicacion'] ?? '') !== '')
            ? $datos['ubicacion']
            : null,

        (int)($datos['activo'] ?? 1)

    ]);

    if (!$ok) {
        return false;
    }

    return (int)$conexion->lastInsertId();
}


// ============================================================
// ACTUALIZAR REPUESTO
//
// No modifica stock_actual: los cambios de stock siempre
// quedan trazados a través de registrarMovimientoStock().
// ============================================================

function actualizarRepuesto(
    PDO $conexion,
    int $idRepuesto,
    array $datos
): bool {

    $sql = "
        UPDATE repuestos
        SET
            nombre = ?,
            descripcion = ?,
            categoria = ?,
            unidad = ?,
            stock_minimo = ?,
            costo_unitario = ?,
            ubicacion = ?,
            activo = ?
        WHERE id_repuesto = ?
    ";

    $stmt = $conexion->prepare($sql);

    return $stmt->execute([

        $datos['nombre'],

        (($datos['descripcion'] ?? '') !== '')
            ? $datos['descripcion']
            : null,

        $datos['categoria'],

        $datos['unidad'],

        (int)($datos['stock_minimo'] ?? 0),

        (($datos['costo_unitario'] ?? '') !== '')
            ? (float)$datos['costo_unitario']
            : null,

        (($datos['ubicacion'] ?? '') !== '')
            ? $datos['ubicacion']
            : null,

        (int)($datos['activo'] ?? 1),

        $idRepuesto

    ]);
}


// ============================================================
// OBTENER STOCK POR DEBAJO DEL MÍNIMO
// ============================================================

function obtenerStockBajo(PDO $conexion): array
{
    $sql = "
        SELECT *
        FROM vista_stock_bajo
        ORDER BY nombre ASC
    ";

    $stmt = $conexion->query($sql);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


// ============================================================
// REGISTRAR MOVIMIENTO DE STOCK
//
// Entrada: suma al stock actual.
// Salida: resta al stock actual (nunca puede quedar negativo,
// en ese caso se aborta la operación y se devuelve false).
// ============================================================

function registrarMovimientoStock(
    PDO $conexion,
    int $idRepuesto,
    string $tipo,
    string $direccion,
    int $cantidad,
    int $idUsuario,
    ?int $idSolicitud = null,
    ?int $idIntervencion = null,
    ?string $observaciones = null
): bool {

    $tiposPermitidos = [
        'Ingreso',
        'Uso',
        'Ajuste'
    ];

    $direccionesPermitidas = [
        'Entrada',
        'Salida'
    ];

    if (
        !in_array($tipo, $tiposPermitidos, true)
        ||
        !in_array($direccion, $direccionesPermitidas, true)
        ||
        $cantidad <= 0
    ) {

        return false;
    }

    try {

        $conexion->beginTransaction();


        // ====================================================
        // LEER STOCK ACTUAL (con bloqueo de fila)
        // ====================================================

        $stmtStock = $conexion->prepare("
            SELECT stock_actual
            FROM repuestos
            WHERE id_repuesto = ?
            FOR UPDATE
        ");

        $stmtStock->execute([
            $idRepuesto
        ]);

        $stockActual = $stmtStock->fetchColumn();

        if ($stockActual === false) {

            $conexion->rollBack();

            return false;
        }

        $stockActual = (int)$stockActual;


        // ====================================================
        // CALCULAR NUEVO STOCK
        // ====================================================

        $stockNuevo =
            $direccion === 'Entrada'
                ? $stockActual + $cantidad
                : $stockActual - $cantidad;

        if ($stockNuevo < 0) {

            $conexion->rollBack();

            return false;
        }


        // ====================================================
        // ACTUALIZAR STOCK
        // ====================================================

        $stmtActualizar = $conexion->prepare("
            UPDATE repuestos
            SET stock_actual = ?
            WHERE id_repuesto = ?
        ");

        $stmtActualizar->execute([
            $stockNuevo,
            $idRepuesto
        ]);


        // ====================================================
        // REGISTRAR MOVIMIENTO
        // ====================================================

        $stmtMovimiento = $conexion->prepare("
            INSERT INTO repuestos_movimientos
            (
                id_repuesto,
                id_solicitud,
                id_intervencion,
                id_usuario,
                tipo,
                direccion,
                cantidad,
                stock_resultante,
                observaciones
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
                ?
            )
        ");

        $stmtMovimiento->execute([

            $idRepuesto,

            $idSolicitud,

            $idIntervencion,

            $idUsuario,

            $tipo,

            $direccion,

            $cantidad,

            $stockNuevo,

            ($observaciones !== null && $observaciones !== '')
                ? $observaciones
                : null

        ]);


        $conexion->commit();

        return true;

    } catch (Throwable $e) {

        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }

        error_log(
            'Error registrando movimiento de stock: '
            . $e->getMessage()
        );

        return false;
    }
}


// ============================================================
// OBTENER MOVIMIENTOS DE UN REPUESTO
// ============================================================

function obtenerMovimientosRepuesto(
    PDO $conexion,
    int $idRepuesto,
    int $limite = 50
): array {

    $limite = max(1, min($limite, 200));

    $sql = "
        SELECT
            m.*,

            CONCAT(
                u.nombre,
                ' ',
                u.apellido
            ) AS usuario

        FROM repuestos_movimientos m

        INNER JOIN usuarios u
            ON m.id_usuario = u.id_usuario

        WHERE m.id_repuesto = ?

        ORDER BY m.fecha DESC

        LIMIT {$limite}
    ";

    $stmt = $conexion->prepare($sql);

    $stmt->execute([
        $idRepuesto
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


// ============================================================
// OBTENER MOVIMIENTOS DE UNA SOLICITUD
// Repuestos utilizados en una reparación puntual.
// ============================================================

function obtenerMovimientosSolicitud(
    PDO $conexion,
    int $idSolicitud
): array {

    $sql = "
        SELECT
            m.*,

            r.nombre AS repuesto,
            r.unidad,

            CONCAT(
                u.nombre,
                ' ',
                u.apellido
            ) AS usuario

        FROM repuestos_movimientos m

        INNER JOIN repuestos r
            ON m.id_repuesto = r.id_repuesto

        INNER JOIN usuarios u
            ON m.id_usuario = u.id_usuario

        WHERE m.id_solicitud = ?

        ORDER BY m.fecha DESC
    ";

    $stmt = $conexion->prepare($sql);

    $stmt->execute([
        $idSolicitud
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


// ============================================================
// ============================================================
// HORARIOS DE TÉCNICOS
// ============================================================
// ============================================================


// ============================================================
// DÍA DE LA SEMANA EN ESPAÑOL A PARTIR DE UNA FECHA
// Lunes .. Sabado (domingo se devuelve como 'Domingo' aunque
// no se utilice en los horarios de técnicos).
// ============================================================

function diaSemanaEspanol(string $fecha): string
{
    $dias = [
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miercoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sabado',
        7 => 'Domingo'
    ];

    try {

        $date = new DateTime($fecha);

        $numero = (int)$date->format('N');

        return $dias[$numero] ?? '';

    } catch (Exception $e) {

        return '';
    }
}


// ============================================================
// OBTENER HORARIO SEMANAL DE UN TÉCNICO
// ============================================================

function obtenerHorarioTecnico(
    PDO $conexion,
    int $idTecnico
): array {

    $diasOrden = "
        CASE dia
            WHEN 'Lunes' THEN 1
            WHEN 'Martes' THEN 2
            WHEN 'Miercoles' THEN 3
            WHEN 'Jueves' THEN 4
            WHEN 'Viernes' THEN 5
            WHEN 'Sabado' THEN 6
            ELSE 7
        END
    ";

    $sql = "
        SELECT *
        FROM horarios_tecnicos
        WHERE id_tecnico = ?
        ORDER BY
            {$diasOrden},
            hora_desde ASC
    ";

    $stmt = $conexion->prepare($sql);

    $stmt->execute([
        $idTecnico
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


// ============================================================
// GUARDAR HORARIO DE UN TÉCNICO
// ============================================================

function guardarHorarioTecnico(
    PDO $conexion,
    int $idTecnico,
    string $dia,
    string $horaDesde,
    string $horaHasta
): int|false {

    $sql = "
        INSERT INTO horarios_tecnicos
        (
            id_tecnico,
            dia,
            hora_desde,
            hora_hasta
        )
        VALUES
        (
            ?,
            ?,
            ?,
            ?
        )
    ";

    $stmt = $conexion->prepare($sql);

    $ok = $stmt->execute([
        $idTecnico,
        $dia,
        $horaDesde,
        $horaHasta
    ]);

    if (!$ok) {
        return false;
    }

    return (int)$conexion->lastInsertId();
}


// ============================================================
// ELIMINAR HORARIO DE UN TÉCNICO
// ============================================================

function eliminarHorarioTecnico(
    PDO $conexion,
    int $idHorarioTecnico
): bool {

    $sql = "
        DELETE FROM horarios_tecnicos
        WHERE id_horario_tecnico = ?
    ";

    $stmt = $conexion->prepare($sql);

    return $stmt->execute([
        $idHorarioTecnico
    ]);
}


// ============================================================
// ¿EL TÉCNICO TRABAJA EN ESE RANGO HORARIO?
//
// Verdadero solamente si [horaDesde, horaHasta] queda
// completamente dentro de alguno de los horarios activos
// del técnico para el día que corresponde a esa fecha.
// ============================================================

function tecnicoTrabajaEn(
    PDO $conexion,
    int $idTecnico,
    string $fecha,
    string $horaDesde,
    string $horaHasta
): bool {

    $dia = diaSemanaEspanol($fecha);

    if ($dia === '' || $dia === 'Domingo') {
        return false;
    }

    $sql = "
        SELECT COUNT(*)
        FROM horarios_tecnicos
        WHERE id_tecnico = ?
        AND dia = ?
        AND activo = 1
        AND hora_desde <= ?
        AND hora_hasta >= ?
    ";

    $stmt = $conexion->prepare($sql);

    $stmt->execute([
        $idTecnico,
        $dia,
        $horaDesde,
        $horaHasta
    ]);

    return (int)$stmt->fetchColumn() > 0;
}


// ============================================================
// ============================================================
// TURNOS DE REPARACIÓN
// ============================================================
// ============================================================


// ============================================================
// OBTENER TURNOS
// ============================================================

function obtenerTurnos(
    PDO $conexion,
    ?int $idTecnico = null,
    ?string $desde = null,
    ?string $hasta = null
): array {

    $condiciones = [];
    $parametros = [];

    if ($idTecnico !== null) {

        $condiciones[] = "id_tecnico = ?";

        $parametros[] = $idTecnico;
    }

    if (!empty($desde)) {

        $condiciones[] = "fecha >= ?";

        $parametros[] = $desde;
    }

    if (!empty($hasta)) {

        $condiciones[] = "fecha <= ?";

        $parametros[] = $hasta;
    }

    $where = '';

    if (!empty($condiciones)) {

        $where = "
            WHERE " .
            implode(
                " AND ",
                $condiciones
            );
    }

    $sql = "
        SELECT *
        FROM vista_turnos
        {$where}
        ORDER BY
            fecha ASC,
            hora_desde ASC
    ";

    $stmt = $conexion->prepare($sql);

    $stmt->execute($parametros);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


// ============================================================
// OBTENER UN TURNO
// ============================================================

function obtenerTurno(
    PDO $conexion,
    int $idTurno
): ?array {

    $sql = "
        SELECT *
        FROM vista_turnos
        WHERE id_turno = ?
        LIMIT 1
    ";

    $stmt = $conexion->prepare($sql);

    $stmt->execute([
        $idTurno
    ]);

    $turno = $stmt->fetch(PDO::FETCH_ASSOC);

    return $turno ?: null;
}


// ============================================================
// OBTENER TURNOS DE UNA SOLICITUD
// ============================================================

function obtenerTurnosSolicitud(
    PDO $conexion,
    int $idSolicitud
): array {

    $sql = "
        SELECT *
        FROM vista_turnos
        WHERE id_solicitud = ?
        ORDER BY
            fecha DESC,
            hora_desde DESC
    ";

    $stmt = $conexion->prepare($sql);

    $stmt->execute([
        $idSolicitud
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


// ============================================================
// ¿EXISTE SUPERPOSICIÓN DE TURNOS?
// Mismo técnico, misma fecha, rango horario que se cruza,
// comparado contra turnos activos (Programado / Confirmado).
// ============================================================

function existeSuperposicionTurno(
    PDO $conexion,
    int $idTecnico,
    string $fecha,
    string $horaDesde,
    string $horaHasta,
    ?int $idTurnoExcluir = null
): bool {

    $sql = "
        SELECT COUNT(*)
        FROM turnos_reparacion
        WHERE id_tecnico = ?
        AND fecha = ?
        AND estado IN ('Programado', 'Confirmado')
        AND hora_desde < ?
        AND hora_hasta > ?
    ";

    $parametros = [
        $idTecnico,
        $fecha,
        $horaHasta,
        $horaDesde
    ];

    if ($idTurnoExcluir !== null) {

        $sql .= "
            AND id_turno <> ?
        ";

        $parametros[] = $idTurnoExcluir;
    }

    $stmt = $conexion->prepare($sql);

    $stmt->execute($parametros);

    return (int)$stmt->fetchColumn() > 0;
}


// ============================================================
// CREAR TURNO
// ============================================================

function crearTurno(
    PDO $conexion,
    int $idSolicitud,
    int $idTecnico,
    string $fecha,
    string $horaDesde,
    string $horaHasta,
    float $horasEstimadas,
    int $creadoPor
): array {

    if (
        !tecnicoTrabajaEn(
            $conexion,
            $idTecnico,
            $fecha,
            $horaDesde,
            $horaHasta
        )
    ) {

        return [
            'ok' => false,
            'error' =>
                'El horario seleccionado está fuera del horario habitual del técnico.'
        ];
    }

    if (
        existeSuperposicionTurno(
            $conexion,
            $idTecnico,
            $fecha,
            $horaDesde,
            $horaHasta
        )
    ) {

        return [
            'ok' => false,
            'error' =>
                'El técnico ya tiene otro turno programado que se superpone con ese horario.'
        ];
    }

    try {

        $conexion->beginTransaction();

        $stmt = $conexion->prepare("
            INSERT INTO turnos_reparacion
            (
                id_solicitud,
                id_tecnico,
                fecha,
                hora_desde,
                hora_hasta,
                horas_estimadas,
                creado_por
            )
            VALUES
            (
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?
            )
        ");

        $stmt->execute([
            $idSolicitud,
            $idTecnico,
            $fecha,
            $horaDesde,
            $horaHasta,
            $horasEstimadas,
            $creadoPor
        ]);

        $idTurno = (int)$conexion->lastInsertId();


        // ====================================================
        // HISTORIAL
        // ====================================================

        $solicitud = obtenerSolicitud(
            $conexion,
            $idSolicitud
        );

        $stmtHistorial = $conexion->prepare("
            INSERT INTO solicitud_historial
            (
                id_solicitud,
                id_usuario,
                estado_anterior,
                estado_nuevo,
                descripcion
            )
            VALUES (?, ?, ?, ?, ?)
        ");

        $stmtHistorial->execute([
            $idSolicitud,
            $creadoPor,
            $solicitud['estado'] ?? null,
            $solicitud['estado'] ?? null,
            'Se programó un turno para el '
            . fechaCorta($fecha)
            . ' de '
            . horaCorta($horaDesde)
            . ' a '
            . horaCorta($horaHasta)
            . '.'
        ]);


        $conexion->commit();

        return [
            'ok' => true,
            'id_turno' => $idTurno
        ];

    } catch (Throwable $e) {

        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }

        error_log(
            'Error creando turno: '
            . $e->getMessage()
        );

        return [
            'ok' => false,
            'error' => 'No se pudo crear el turno.'
        ];
    }
}


// ============================================================
// REPROGRAMAR TURNO
//
// Marca el turno anterior como Reprogramado, crea uno nuevo
// vinculado (id_turno_origen) y registra las horas generadas
// como Hora extra o Compensación. También deja la solicitud
// en estado Pendiente con tipo_pendiente = 'Reprogramacion'.
// ============================================================

function reprogramarTurno(
    PDO $conexion,
    int $idTurno,
    string $nuevaFecha,
    string $nuevaHoraDesde,
    string $nuevaHoraHasta,
    string $motivo,
    string $tipoHoras,
    ?string $semanaCompensar,
    int $idUsuario
): array {

    $tiposHorasPermitidos = [
        'Hora extra',
        'Compensacion'
    ];

    if (
        !in_array(
            $tipoHoras,
            $tiposHorasPermitidos,
            true
        )
    ) {

        return [
            'ok' => false,
            'error' => 'El tipo de horas indicado no es válido.'
        ];
    }

    $turnoOriginal = obtenerTurno(
        $conexion,
        $idTurno
    );

    if (!$turnoOriginal) {

        return [
            'ok' => false,
            'error' => 'El turno indicado no existe.'
        ];
    }

    $idTecnico = (int)$turnoOriginal['id_tecnico'];

    $idSolicitud = (int)$turnoOriginal['id_solicitud'];


    // ============================================================
    // VALIDAR NUEVO HORARIO
    // ============================================================

    if (
        !tecnicoTrabajaEn(
            $conexion,
            $idTecnico,
            $nuevaFecha,
            $nuevaHoraDesde,
            $nuevaHoraHasta
        )
    ) {

        return [
            'ok' => false,
            'error' =>
                'El nuevo horario está fuera del horario habitual del técnico.'
        ];
    }

    if (
        existeSuperposicionTurno(
            $conexion,
            $idTecnico,
            $nuevaFecha,
            $nuevaHoraDesde,
            $nuevaHoraHasta,
            $idTurno
        )
    ) {

        return [
            'ok' => false,
            'error' =>
                'El técnico ya tiene otro turno programado que se superpone con el nuevo horario.'
        ];
    }

    try {

        $conexion->beginTransaction();


        // ========================================================
        // MARCAR TURNO ANTERIOR COMO REPROGRAMADO
        // ========================================================

        $stmtAnterior = $conexion->prepare("
            UPDATE turnos_reparacion
            SET
                estado = 'Reprogramado',
                motivo_reprogramacion = ?
            WHERE id_turno = ?
        ");

        $stmtAnterior->execute([
            $motivo,
            $idTurno
        ]);


        // ========================================================
        // CREAR NUEVO TURNO
        // ========================================================

        $stmtNuevo = $conexion->prepare("
            INSERT INTO turnos_reparacion
            (
                id_solicitud,
                id_tecnico,
                fecha,
                hora_desde,
                hora_hasta,
                horas_estimadas,
                id_turno_origen,
                creado_por
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
                ?
            )
        ");

        $stmtNuevo->execute([
            $idSolicitud,
            $idTecnico,
            $nuevaFecha,
            $nuevaHoraDesde,
            $nuevaHoraHasta,
            $turnoOriginal['horas_estimadas'],
            $idTurno,
            $idUsuario
        ]);

        $idTurnoNuevo = (int)$conexion->lastInsertId();


        // ========================================================
        // REGISTRAR HORAS EXTRA / COMPENSACIÓN
        // ========================================================

        $stmtHoras = $conexion->prepare("
            INSERT INTO horas_extra
            (
                id_tecnico,
                id_turno,
                id_solicitud,
                tipo,
                horas,
                motivo,
                semana_compensar
            )
            VALUES
            (
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?
            )
        ");

        $stmtHoras->execute([
            $idTecnico,
            $idTurnoNuevo,
            $idSolicitud,
            $tipoHoras,
            $turnoOriginal['horas_estimadas'],
            $motivo,
            $tipoHoras === 'Compensacion'
                ? $semanaCompensar
                : null
        ]);


        // ========================================================
        // MARCAR SOLICITUD COMO PENDIENTE
        // ========================================================

        $solicitud = obtenerSolicitud(
            $conexion,
            $idSolicitud
        );

        $estadoAnterior = $solicitud['estado'] ?? 'Nueva';

        $stmtEstado = $conexion->prepare("
            UPDATE solicitudes
            SET
                estado = 'Pendiente',
                motivo_pendiente = ?,
                tipo_pendiente = 'Reprogramacion'
            WHERE id_solicitud = ?
        ");

        $stmtEstado->execute([
            $motivo,
            $idSolicitud
        ]);

        $stmtHistorial = $conexion->prepare("
            INSERT INTO solicitud_historial
            (
                id_solicitud,
                id_usuario,
                estado_anterior,
                estado_nuevo,
                descripcion
            )
            VALUES (?, ?, ?, 'Pendiente', ?)
        ");

        $stmtHistorial->execute([
            $idSolicitud,
            $idUsuario,
            $estadoAnterior,
            'El turno del '
            . fechaCorta($turnoOriginal['fecha'])
            . ' fue reprogramado para el '
            . fechaCorta($nuevaFecha)
            . '. Motivo: '
            . $motivo
        ]);


        $conexion->commit();

        return [
            'ok' => true,
            'id_turno' => $idTurnoNuevo
        ];

    } catch (Throwable $e) {

        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }

        error_log(
            'Error reprogramando turno: '
            . $e->getMessage()
        );

        return [
            'ok' => false,
            'error' => 'No se pudo reprogramar el turno.'
        ];
    }
}


// ============================================================
// ACTUALIZAR ESTADO DE UN TURNO
// ============================================================

function actualizarEstadoTurno(
    PDO $conexion,
    int $idTurno,
    string $nuevoEstado
): bool {

    $estadosPermitidos = [
        'Programado',
        'Confirmado',
        'Reprogramado',
        'Completado',
        'Cancelado'
    ];

    if (
        !in_array(
            $nuevoEstado,
            $estadosPermitidos,
            true
        )
    ) {

        return false;
    }

    $sql = "
        UPDATE turnos_reparacion
        SET estado = ?
        WHERE id_turno = ?
    ";

    $stmt = $conexion->prepare($sql);

    return $stmt->execute([
        $nuevoEstado,
        $idTurno
    ]);
}


// ============================================================
// ============================================================
// HORAS EXTRA / COMPENSACIÓN
// ============================================================
// ============================================================


// ============================================================
// OBTENER HORAS EXTRA DE UN TÉCNICO
// ============================================================

function obtenerHorasExtraTecnico(
    PDO $conexion,
    int $idTecnico,
    ?string $estado = null
): array {

    $sql = "
        SELECT *
        FROM horas_extra
        WHERE id_tecnico = ?
    ";

    $parametros = [
        $idTecnico
    ];

    if (!empty($estado)) {

        $sql .= "
            AND estado = ?
        ";

        $parametros[] = $estado;
    }

    $sql .= "
        ORDER BY fecha_creacion DESC
    ";

    $stmt = $conexion->prepare($sql);

    $stmt->execute($parametros);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


// ============================================================
// OBTENER HORAS EXTRA DE TODOS LOS TÉCNICOS
// ============================================================

function obtenerHorasExtra(
    PDO $conexion,
    ?string $estado = null
): array {

    $sql = "
        SELECT
            h.*,

            CONCAT(
                u.nombre,
                ' ',
                u.apellido
            ) AS tecnico

        FROM horas_extra h

        INNER JOIN usuarios u
            ON h.id_tecnico = u.id_usuario
    ";

    $parametros = [];

    if (!empty($estado)) {

        $sql .= "
            WHERE h.estado = ?
        ";

        $parametros[] = $estado;
    }

    $sql .= "
        ORDER BY h.fecha_creacion DESC
    ";

    $stmt = $conexion->prepare($sql);

    $stmt->execute($parametros);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


// ============================================================
// ACTUALIZAR ESTADO DE UNA HORA EXTRA
// ============================================================

function actualizarEstadoHoraExtra(
    PDO $conexion,
    int $idHoraExtra,
    string $nuevoEstado
): bool {

    $estadosPermitidos = [
        'Pendiente',
        'Utilizada',
        'Pagada',
        'Cancelada'
    ];

    if (
        !in_array(
            $nuevoEstado,
            $estadosPermitidos,
            true
        )
    ) {

        return false;
    }

    $sql = "
        UPDATE horas_extra
        SET estado = ?
        WHERE id_hora_extra = ?
    ";

    $stmt = $conexion->prepare($sql);

    return $stmt->execute([
        $nuevoEstado,
        $idHoraExtra
    ]);
}


// ============================================================
// ============================================================
// PENDIENTES
// ============================================================
// ============================================================


// ============================================================
// OBTENER SOLICITUDES PENDIENTES
// ============================================================

function obtenerPendientes(
    PDO $conexion,
    ?int $idUsuarioDocente = null
): array {

    $sql = "
        SELECT
            s.*,

            CONCAT(
                u.nombre,
                ' ',
                u.apellido
            ) AS solicitante,

            sec.nombre AS sector,

            c.nombre AS categoria,

            CONCAT(
                t.nombre,
                ' ',
                t.apellido
            ) AS tecnico_asignado

        FROM solicitudes s

        INNER JOIN usuarios u
            ON s.id_usuario = u.id_usuario

        LEFT JOIN sectores sec
            ON s.id_sector = sec.id_sector

        LEFT JOIN categorias c
            ON s.id_categoria = c.id_categoria

        LEFT JOIN solicitudes_asignaciones sa
            ON sa.id_solicitud = s.id_solicitud
            AND sa.activo = 1

        LEFT JOIN usuarios t
            ON sa.id_tecnico = t.id_usuario

        WHERE s.estado = 'Pendiente'
    ";

    $parametros = [];

    if ($idUsuarioDocente !== null) {

        $sql .= "
            AND s.id_usuario = ?
        ";

        $parametros[] = $idUsuarioDocente;
    }

    $sql .= "
        ORDER BY

            CASE s.prioridad
                WHEN 'Urgente' THEN 1
                WHEN 'Alta' THEN 2
                WHEN 'Normal' THEN 3
                WHEN 'Baja' THEN 4
                ELSE 5
            END,

            s.fecha_actualizacion DESC
    ";

    $stmt = $conexion->prepare($sql);

    $stmt->execute($parametros);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


// ============================================================
// CLASE CSS PARA TIPO DE PENDIENTE
// ============================================================

function claseTipoPendiente(string $tipoPendiente): string
{
    return match ($tipoPendiente) {

        'Falta de repuesto'
            => 'bg-warning text-dark',

        'Horas insuficientes'
            => 'bg-info text-dark',

        'Reprogramacion'
            => 'bg-secondary',

        'Otro'
            => 'bg-dark',

        default
            => 'bg-secondary'
    };
}


// ============================================================
// ÍCONO PARA TIPO DE PENDIENTE
// ============================================================

function iconoTipoPendiente(string $tipoPendiente): string
{
    return match ($tipoPendiente) {

        'Falta de repuesto'
            => 'bi-box-seam',

        'Horas insuficientes'
            => 'bi-clock-history',

        'Reprogramacion'
            => 'bi-calendar-x',

        'Otro'
            => 'bi-question-circle',

        default
            => 'bi-question-circle'
    };
}