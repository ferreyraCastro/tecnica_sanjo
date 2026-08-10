-- ============================================================
-- COLEGIO SAN JOSE
-- ACTUALIZACIÓN v2
-- Stock de repuestos, horas de técnicos, turnos de reparación
-- y horas extra / compensación.
--
-- Ejecutar este script UNA VEZ sobre la base ya existente
-- "tecnica_sanjo" (por ejemplo, desde phpMyAdmin -> pestaña SQL).
-- No borra ni modifica datos existentes, solo agrega.
--
-- Si tus tablas originales NO son InnoDB vas a ver el error:
-- "#1005 - Error: 150 Foreign key constraint is incorrectly
-- formed" / "Referenced table 'xxx' not found in the data
-- dictionary". En ese caso, correr primero:
--
-- database/fix_engine_innodb.sql
--
-- y recién después este archivo.
-- ============================================================

USE tecnica_sanjo;


-- ============================================================
-- REPUESTOS
-- Catálogo con stock disponible
-- ============================================================

CREATE TABLE IF NOT EXISTS repuestos (

    id_repuesto INT AUTO_INCREMENT PRIMARY KEY,

    nombre VARCHAR(150) NOT NULL,

    descripcion VARCHAR(255),

    categoria ENUM(
        'Informatica',
        'Mantenimiento',
        'General'
    ) NOT NULL DEFAULT 'General',

    unidad VARCHAR(30) NOT NULL DEFAULT 'unidad',

    stock_actual INT NOT NULL DEFAULT 0,

    stock_minimo INT NOT NULL DEFAULT 0,

    costo_unitario DECIMAL(12,2),

    ubicacion VARCHAR(150),

    activo TINYINT(1) NOT NULL DEFAULT 1,

    fecha_creacion TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    fecha_actualizacion TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;


-- ============================================================
-- MOVIMIENTOS DE REPUESTOS
-- Ingresos de stock, usos en reparaciones y ajustes manuales.
-- ============================================================

CREATE TABLE IF NOT EXISTS repuestos_movimientos (

    id_movimiento INT AUTO_INCREMENT PRIMARY KEY,

    id_repuesto INT NOT NULL,

    id_solicitud INT NULL,

    id_intervencion INT NULL,

    id_usuario INT NOT NULL,

    tipo ENUM(
        'Ingreso',
        'Uso',
        'Ajuste'
    ) NOT NULL,

    direccion ENUM(
        'Entrada',
        'Salida'
    ) NOT NULL,

    cantidad INT NOT NULL,

    stock_resultante INT NOT NULL,

    observaciones VARCHAR(255),

    fecha TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_movimiento_repuesto
        FOREIGN KEY (id_repuesto)
        REFERENCES repuestos(id_repuesto)
        ON DELETE CASCADE,

    CONSTRAINT fk_movimiento_solicitud
        FOREIGN KEY (id_solicitud)
        REFERENCES solicitudes(id_solicitud)
        ON DELETE SET NULL,

    CONSTRAINT fk_movimiento_intervencion
        FOREIGN KEY (id_intervencion)
        REFERENCES intervenciones(id_intervencion)
        ON DELETE SET NULL,

    CONSTRAINT fk_movimiento_usuario
        FOREIGN KEY (id_usuario)
        REFERENCES usuarios(id_usuario)
) ENGINE=InnoDB;


-- ============================================================
-- VINCULAR MATERIALES CON EL CATÁLOGO DE REPUESTOS (OPCIONAL)
-- ============================================================

SET @existe_columna := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'tecnica_sanjo'
    AND TABLE_NAME = 'materiales'
    AND COLUMN_NAME = 'id_repuesto'
);

SET @sql_alter_materiales := IF(
    @existe_columna = 0,
    'ALTER TABLE materiales
        ADD COLUMN id_repuesto INT NULL AFTER id_mejora,
        ADD CONSTRAINT fk_material_repuesto
            FOREIGN KEY (id_repuesto)
            REFERENCES repuestos(id_repuesto)
            ON DELETE SET NULL',
    'SELECT 1'
);

PREPARE stmt_alter_materiales FROM @sql_alter_materiales;
EXECUTE stmt_alter_materiales;
DEALLOCATE PREPARE stmt_alter_materiales;


-- ============================================================
-- HORARIOS DE TRABAJO POR TÉCNICO
-- ============================================================

CREATE TABLE IF NOT EXISTS horarios_tecnicos (

    id_horario_tecnico INT AUTO_INCREMENT PRIMARY KEY,

    id_tecnico INT NOT NULL,

    dia ENUM(
        'Lunes',
        'Martes',
        'Miercoles',
        'Jueves',
        'Viernes',
        'Sabado'
    ) NOT NULL,

    hora_desde TIME NOT NULL,

    hora_hasta TIME NOT NULL,

    activo TINYINT(1) NOT NULL DEFAULT 1,

    fecha_creacion TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_horario_tecnico_usuario
        FOREIGN KEY (id_tecnico)
        REFERENCES usuarios(id_usuario)
        ON DELETE CASCADE
) ENGINE=InnoDB;


-- ============================================================
-- TURNOS DE REPARACIÓN
-- Momento programado, dentro del horario del técnico,
-- para intervenir una solicitud puntual.
-- ============================================================

CREATE TABLE IF NOT EXISTS turnos_reparacion (

    id_turno INT AUTO_INCREMENT PRIMARY KEY,

    id_solicitud INT NOT NULL,

    id_tecnico INT NOT NULL,

    fecha DATE NOT NULL,

    hora_desde TIME NOT NULL,

    hora_hasta TIME NOT NULL,

    horas_estimadas DECIMAL(5,2) NOT NULL DEFAULT 1,

    estado ENUM(
        'Programado',
        'Confirmado',
        'Reprogramado',
        'Completado',
        'Cancelado'
    ) NOT NULL DEFAULT 'Programado',

    motivo_reprogramacion TEXT NULL,

    id_turno_origen INT NULL,

    creado_por INT NOT NULL,

    fecha_creacion TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_turno_solicitud
        FOREIGN KEY (id_solicitud)
        REFERENCES solicitudes(id_solicitud)
        ON DELETE CASCADE,

    CONSTRAINT fk_turno_tecnico
        FOREIGN KEY (id_tecnico)
        REFERENCES usuarios(id_usuario),

    CONSTRAINT fk_turno_creador
        FOREIGN KEY (creado_por)
        REFERENCES usuarios(id_usuario),

    CONSTRAINT fk_turno_origen
        FOREIGN KEY (id_turno_origen)
        REFERENCES turnos_reparacion(id_turno)
        ON DELETE SET NULL
) ENGINE=InnoDB;


-- ============================================================
-- HORAS EXTRA / COMPENSACIÓN
-- Se generan al reprogramar un turno fuera del horario
-- habitual del técnico.
-- ============================================================

CREATE TABLE IF NOT EXISTS horas_extra (

    id_hora_extra INT AUTO_INCREMENT PRIMARY KEY,

    id_tecnico INT NOT NULL,

    id_turno INT NULL,

    id_solicitud INT NULL,

    tipo ENUM(
        'Hora extra',
        'Compensacion'
    ) NOT NULL,

    horas DECIMAL(5,2) NOT NULL,

    motivo VARCHAR(255),

    semana_compensar DATE NULL,

    estado ENUM(
        'Pendiente',
        'Utilizada',
        'Pagada',
        'Cancelada'
    ) NOT NULL DEFAULT 'Pendiente',

    fecha_creacion TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_hora_extra_tecnico
        FOREIGN KEY (id_tecnico)
        REFERENCES usuarios(id_usuario),

    CONSTRAINT fk_hora_extra_turno
        FOREIGN KEY (id_turno)
        REFERENCES turnos_reparacion(id_turno)
        ON DELETE SET NULL,

    CONSTRAINT fk_hora_extra_solicitud
        FOREIGN KEY (id_solicitud)
        REFERENCES solicitudes(id_solicitud)
        ON DELETE SET NULL
) ENGINE=InnoDB;


-- ============================================================
-- CLASIFICAR EL MOTIVO DE "PENDIENTE"
-- ============================================================

SET @existe_tipo_pendiente_solicitud := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'tecnica_sanjo'
    AND TABLE_NAME = 'solicitudes'
    AND COLUMN_NAME = 'tipo_pendiente'
);

SET @sql_alter_solicitudes := IF(
    @existe_tipo_pendiente_solicitud = 0,
    "ALTER TABLE solicitudes
        ADD COLUMN tipo_pendiente ENUM(
            'Falta de repuesto',
            'Horas insuficientes',
            'Reprogramacion',
            'Otro'
        ) NULL AFTER motivo_pendiente",
    'SELECT 1'
);

PREPARE stmt_alter_solicitudes FROM @sql_alter_solicitudes;
EXECUTE stmt_alter_solicitudes;
DEALLOCATE PREPARE stmt_alter_solicitudes;


SET @existe_tipo_pendiente_intervencion := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'tecnica_sanjo'
    AND TABLE_NAME = 'intervenciones'
    AND COLUMN_NAME = 'tipo_pendiente'
);

SET @sql_alter_intervenciones := IF(
    @existe_tipo_pendiente_intervencion = 0,
    "ALTER TABLE intervenciones
        ADD COLUMN tipo_pendiente ENUM(
            'Falta de repuesto',
            'Horas insuficientes',
            'Reprogramacion',
            'Otro'
        ) NULL AFTER motivo_pendiente",
    'SELECT 1'
);

PREPARE stmt_alter_intervenciones FROM @sql_alter_intervenciones;
EXECUTE stmt_alter_intervenciones;
DEALLOCATE PREPARE stmt_alter_intervenciones;


-- ============================================================
-- ÍNDICES
-- Este script está pensado para ejecutarse UNA sola vez.
-- Si lo corrés de nuevo y las tablas ya existían,
-- estas líneas de índices pueden fallar por estar duplicadas;
-- en ese caso se pueden omitir sin problema.
-- ============================================================

CREATE INDEX idx_repmov_repuesto
ON repuestos_movimientos(id_repuesto);

CREATE INDEX idx_repmov_solicitud
ON repuestos_movimientos(id_solicitud);

CREATE INDEX idx_turnos_tecnico_fecha
ON turnos_reparacion(id_tecnico, fecha);

CREATE INDEX idx_turnos_solicitud
ON turnos_reparacion(id_solicitud);

CREATE INDEX idx_horas_extra_tecnico
ON horas_extra(id_tecnico, estado);

CREATE INDEX idx_horarios_tecnicos_tecnico
ON horarios_tecnicos(id_tecnico, dia);


-- ============================================================
-- VISTA: STOCK BAJO EL MÍNIMO
-- ============================================================

CREATE OR REPLACE VIEW vista_stock_bajo AS

SELECT *
FROM repuestos
WHERE activo = 1
AND stock_actual <= stock_minimo;


-- ============================================================
-- VISTA: TURNOS DE REPARACIÓN
-- Usada para que docentes vean horarios y prioridades
-- de todas las solicitudes programadas, no solo las propias.
-- ============================================================

CREATE OR REPLACE VIEW vista_turnos AS

SELECT

    t.id_turno,
    t.id_solicitud,
    t.id_tecnico,
    t.fecha,
    t.hora_desde,
    t.hora_hasta,
    t.horas_estimadas,
    t.estado,
    t.motivo_reprogramacion,

    s.titulo,
    s.tipo,
    s.prioridad,
    s.estado AS estado_solicitud,

    CONCAT(td.nombre, ' ', td.apellido) AS tecnico,
    CONCAT(ud.nombre, ' ', ud.apellido) AS docente,

    sec.nombre AS sector

FROM turnos_reparacion t

INNER JOIN solicitudes s
    ON t.id_solicitud = s.id_solicitud

INNER JOIN usuarios td
    ON t.id_tecnico = td.id_usuario

INNER JOIN usuarios ud
    ON s.id_usuario = ud.id_usuario

LEFT JOIN sectores sec
    ON s.id_sector = sec.id_sector;


-- ============================================================
-- FIN ACTUALIZACIÓN v2
-- ============================================================
