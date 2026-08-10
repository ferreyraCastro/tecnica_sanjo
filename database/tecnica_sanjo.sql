-- ============================================================
-- COLEGIO SAN JOSE
-- SISTEMA DE SOLICITUDES E INTERVENCIONES
-- Base de datos: tecnica_sanjo
-- ============================================================

CREATE DATABASE IF NOT EXISTS tecnica_sanjo
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE tecnica_sanjo;

-- ============================================================
-- USUARIOS
-- Docentes, técnicos y administradores
-- ============================================================

CREATE TABLE usuarios (
    id_usuario INT AUTO_INCREMENT PRIMARY KEY,

    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100) NOT NULL,

    correo VARCHAR(150) NOT NULL UNIQUE,

    -- El DNI NO se guarda directamente como contraseña.
    -- Desde PHP utilizaremos password_hash()
    dni_hash VARCHAR(255) NOT NULL,

    rol ENUM(
        'Docente',
        'Tecnico',
        'Administrador'
    ) NOT NULL DEFAULT 'Docente',

    estado ENUM(
        'Activo',
        'Inactivo'
    ) NOT NULL DEFAULT 'Activo',

    ultimo_acceso DATETIME NULL,

    fecha_creacion TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    fecha_actualizacion TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
);


-- ============================================================
-- SECTORES / AULAS / ESPACIOS
-- ============================================================

CREATE TABLE sectores (
    id_sector INT AUTO_INCREMENT PRIMARY KEY,

    nombre VARCHAR(100) NOT NULL,

    tipo ENUM(
        'Aula',
        'Oficina',
        'Sala',
        'Patio',
        'Baño',
        'Laboratorio',
        'Biblioteca',
        'Otro'
    ) NOT NULL DEFAULT 'Aula',

    descripcion VARCHAR(255),

    activo TINYINT(1) NOT NULL DEFAULT 1,

    fecha_creacion TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP
);


-- ============================================================
-- CATEGORÍAS
-- ============================================================

CREATE TABLE categorias (
    id_categoria INT AUTO_INCREMENT PRIMARY KEY,

    tipo ENUM(
        'Informatica',
        'Mantenimiento'
    ) NOT NULL,

    nombre VARCHAR(100) NOT NULL,

    descripcion VARCHAR(255),

    activo TINYINT(1) NOT NULL DEFAULT 1,

    UNIQUE KEY unique_categoria_tipo (tipo, nombre)
);


-- ============================================================
-- SOLICITUDES
-- ============================================================

CREATE TABLE solicitudes (
    id_solicitud INT AUTO_INCREMENT PRIMARY KEY,

    id_usuario INT NOT NULL,

    id_sector INT NULL,

    id_categoria INT NULL,

    tipo ENUM(
        'Informatica',
        'Mantenimiento'
    ) NOT NULL,

    titulo VARCHAR(200) NOT NULL,

    descripcion TEXT NOT NULL,

    prioridad ENUM(
        'Baja',
        'Normal',
        'Alta',
        'Urgente'
    ) NOT NULL DEFAULT 'Normal',

    estado ENUM(
        'Nueva',
        'Asignada',
        'En proceso',
        'Pendiente',
        'Resuelta',
        'Cerrada',
        'Cancelada'
    ) NOT NULL DEFAULT 'Nueva',

    motivo_pendiente TEXT NULL,

    fecha_creacion TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    fecha_actualizacion TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    fecha_resolucion DATETIME NULL,

    CONSTRAINT fk_solicitud_usuario
        FOREIGN KEY (id_usuario)
        REFERENCES usuarios(id_usuario),

    CONSTRAINT fk_solicitud_sector
        FOREIGN KEY (id_sector)
        REFERENCES sectores(id_sector)
        ON DELETE SET NULL,

    CONSTRAINT fk_solicitud_categoria
        FOREIGN KEY (id_categoria)
        REFERENCES categorias(id_categoria)
        ON DELETE SET NULL
);


-- ============================================================
-- TÉCNICOS ASIGNADOS A SOLICITUDES
-- ============================================================

CREATE TABLE solicitudes_asignaciones (
    id_asignacion INT AUTO_INCREMENT PRIMARY KEY,

    id_solicitud INT NOT NULL,

    id_tecnico INT NOT NULL,

    asignado_por INT NULL,

    fecha_asignacion TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    activo TINYINT(1)
        NOT NULL DEFAULT 1,

    CONSTRAINT fk_asignacion_solicitud
        FOREIGN KEY (id_solicitud)
        REFERENCES solicitudes(id_solicitud)
        ON DELETE CASCADE,

    CONSTRAINT fk_asignacion_tecnico
        FOREIGN KEY (id_tecnico)
        REFERENCES usuarios(id_usuario),

    CONSTRAINT fk_asignacion_usuario
        FOREIGN KEY (asignado_por)
        REFERENCES usuarios(id_usuario)
        ON DELETE SET NULL
);


-- ============================================================
-- IMÁGENES DE LAS SOLICITUDES
-- ============================================================

CREATE TABLE solicitud_imagenes (
    id_imagen INT AUTO_INCREMENT PRIMARY KEY,

    id_solicitud INT NOT NULL,

    id_usuario INT NULL,

    archivo VARCHAR(255) NOT NULL,

    nombre_original VARCHAR(255),

    descripcion VARCHAR(255),

    tipo ENUM(
        'Solicitud',
        'Intervencion',
        'Solucion'
    ) NOT NULL DEFAULT 'Solicitud',

    fecha TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_imagen_solicitud
        FOREIGN KEY (id_solicitud)
        REFERENCES solicitudes(id_solicitud)
        ON DELETE CASCADE,

    CONSTRAINT fk_imagen_usuario
        FOREIGN KEY (id_usuario)
        REFERENCES usuarios(id_usuario)
        ON DELETE SET NULL
);


-- ============================================================
-- COMENTARIOS
-- Docente y técnico pueden conversar dentro de la solicitud
-- ============================================================

CREATE TABLE comentarios (
    id_comentario INT AUTO_INCREMENT PRIMARY KEY,

    id_solicitud INT NOT NULL,

    id_usuario INT NOT NULL,

    comentario TEXT NOT NULL,

    fecha TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_comentario_solicitud
        FOREIGN KEY (id_solicitud)
        REFERENCES solicitudes(id_solicitud)
        ON DELETE CASCADE,

    CONSTRAINT fk_comentario_usuario
        FOREIGN KEY (id_usuario)
        REFERENCES usuarios(id_usuario)
        ON DELETE CASCADE
);


-- ============================================================
-- INTERVENCIONES TÉCNICAS
-- Cada solicitud puede tener varias intervenciones
-- ============================================================

CREATE TABLE intervenciones (
    id_intervencion INT AUTO_INCREMENT PRIMARY KEY,

    id_solicitud INT NOT NULL,

    id_tecnico INT NOT NULL,

    diagnostico TEXT,

    trabajo_realizado TEXT,

    materiales TEXT,

    observaciones TEXT,

    pendiente TINYINT(1)
        NOT NULL DEFAULT 0,

    motivo_pendiente TEXT NULL,

    fecha_inicio DATETIME NULL,

    fecha_fin DATETIME NULL,

    fecha_intervencion TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_intervencion_solicitud
        FOREIGN KEY (id_solicitud)
        REFERENCES solicitudes(id_solicitud)
        ON DELETE CASCADE,

    CONSTRAINT fk_intervencion_tecnico
        FOREIGN KEY (id_tecnico)
        REFERENCES usuarios(id_usuario)
);


-- ============================================================
-- IMÁGENES ESPECÍFICAS DE INTERVENCIONES
-- ============================================================

CREATE TABLE intervencion_imagenes (
    id_imagen INT AUTO_INCREMENT PRIMARY KEY,

    id_intervencion INT NOT NULL,

    archivo VARCHAR(255) NOT NULL,

    nombre_original VARCHAR(255),

    descripcion VARCHAR(255),

    tipo ENUM(
        'Antes',
        'Durante',
        'Despues'
    ) NOT NULL DEFAULT 'Despues',

    fecha TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_intervencion_imagen
        FOREIGN KEY (id_intervencion)
        REFERENCES intervenciones(id_intervencion)
        ON DELETE CASCADE
);


-- ============================================================
-- HISTORIAL DE ESTADOS
-- Permite tener una línea de tiempo completa
-- ============================================================

CREATE TABLE solicitud_historial (
    id_historial INT AUTO_INCREMENT PRIMARY KEY,

    id_solicitud INT NOT NULL,

    id_usuario INT NULL,

    estado_anterior VARCHAR(50),

    estado_nuevo VARCHAR(50),

    descripcion TEXT,

    fecha TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_historial_solicitud
        FOREIGN KEY (id_solicitud)
        REFERENCES solicitudes(id_solicitud)
        ON DELETE CASCADE,

    CONSTRAINT fk_historial_usuario
        FOREIGN KEY (id_usuario)
        REFERENCES usuarios(id_usuario)
        ON DELETE SET NULL
);


-- ============================================================
-- HORARIOS
-- Informática y mantenimiento general
-- ============================================================

CREATE TABLE horarios_mantenimiento (
    id_horario INT AUTO_INCREMENT PRIMARY KEY,

    tipo ENUM(
        'Informatica',
        'Mantenimiento'
    ) NOT NULL,

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

    responsable VARCHAR(150),

    observaciones VARCHAR(255),

    activo TINYINT(1)
        NOT NULL DEFAULT 1
);


-- ============================================================
-- PROPUESTAS DE MEJORA
-- ============================================================

CREATE TABLE mejoras (
    id_mejora INT AUTO_INCREMENT PRIMARY KEY,

    id_usuario INT NOT NULL,

    id_sector INT NULL,

    tipo ENUM(
        'Informatica',
        'Mantenimiento'
    ) NOT NULL,

    titulo VARCHAR(200) NOT NULL,

    descripcion TEXT NOT NULL,

    justificacion TEXT,

    solucion_propuesta TEXT,

    cantidad INT NULL,

    costo_estimado DECIMAL(12,2) NULL,

    prioridad ENUM(
        'Baja',
        'Normal',
        'Alta',
        'Urgente'
    ) NOT NULL DEFAULT 'Normal',

    estado ENUM(
        'Propuesta',
        'En evaluacion',
        'Pendiente autorizacion',
        'Aprobada',
        'En ejecucion',
        'Realizada',
        'Rechazada'
    ) NOT NULL DEFAULT 'Propuesta',

    motivo_pendiente TEXT NULL,

    fecha_creacion TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    fecha_actualizacion TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_mejora_usuario
        FOREIGN KEY (id_usuario)
        REFERENCES usuarios(id_usuario),

    CONSTRAINT fk_mejora_sector
        FOREIGN KEY (id_sector)
        REFERENCES sectores(id_sector)
        ON DELETE SET NULL
);


-- ============================================================
-- IMÁGENES DE MEJORAS
-- ============================================================

CREATE TABLE mejora_imagenes (
    id_imagen INT AUTO_INCREMENT PRIMARY KEY,

    id_mejora INT NOT NULL,

    archivo VARCHAR(255) NOT NULL,

    nombre_original VARCHAR(255),

    descripcion VARCHAR(255),

    fecha TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_mejora_imagen
        FOREIGN KEY (id_mejora)
        REFERENCES mejoras(id_mejora)
        ON DELETE CASCADE
);


-- ============================================================
-- MATERIALES / REPUESTOS SOLICITADOS
-- ============================================================

CREATE TABLE materiales (
    id_material INT AUTO_INCREMENT PRIMARY KEY,

    id_solicitud INT NULL,

    id_mejora INT NULL,

    descripcion VARCHAR(255) NOT NULL,

    cantidad INT NOT NULL DEFAULT 1,

    costo_estimado DECIMAL(12,2),

    enlace_compra TEXT,

    estado ENUM(
        'Necesario',
        'Solicitado',
        'Autorizado',
        'Comprado',
        'Recibido',
        'Utilizado',
        'Cancelado'
    ) NOT NULL DEFAULT 'Necesario',

    observaciones TEXT,

    fecha_creacion TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_material_solicitud
        FOREIGN KEY (id_solicitud)
        REFERENCES solicitudes(id_solicitud)
        ON DELETE CASCADE,

    CONSTRAINT fk_material_mejora
        FOREIGN KEY (id_mejora)
        REFERENCES mejoras(id_mejora)
        ON DELETE CASCADE
);


-- ============================================================
-- NOTIFICACIONES INTERNAS
-- ============================================================

CREATE TABLE notificaciones (
    id_notificacion INT AUTO_INCREMENT PRIMARY KEY,

    id_usuario INT NOT NULL,

    titulo VARCHAR(200) NOT NULL,

    mensaje TEXT NOT NULL,

    enlace VARCHAR(255),

    leida TINYINT(1)
        NOT NULL DEFAULT 0,

    fecha TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_notificacion_usuario
        FOREIGN KEY (id_usuario)
        REFERENCES usuarios(id_usuario)
        ON DELETE CASCADE
);


-- ============================================================
-- CONFIGURACIÓN GENERAL DEL SISTEMA
-- ============================================================

CREATE TABLE configuracion (
    id_configuracion INT AUTO_INCREMENT PRIMARY KEY,

    clave VARCHAR(100) NOT NULL UNIQUE,

    valor TEXT,

    descripcion VARCHAR(255)
);


-- ============================================================
-- DATOS INICIALES
-- ============================================================

INSERT INTO configuracion
(clave, valor, descripcion)
VALUES
(
    'nombre_sistema',
    'Sistema de Solicitudes e Intervenciones',
    'Nombre principal del sistema'
),
(
    'nombre_institucion',
    'Colegio San José',
    'Nombre de la institución'
),
(
    'max_imagen_mb',
    '5',
    'Tamaño máximo permitido por imagen'
);


-- ============================================================
-- CATEGORÍAS INFORMÁTICA
-- ============================================================

INSERT INTO categorias
(tipo, nombre)
VALUES

('Informatica', 'Computadora'),

('Informatica', 'Notebook'),

('Informatica', 'Internet'),

('Informatica', 'WiFi'),

('Informatica', 'Red'),

('Informatica', 'Proyector'),

('Informatica', 'Pantalla'),

('Informatica', 'Audio'),

('Informatica', 'Impresora'),

('Informatica', 'Software'),

('Informatica', 'Sistema operativo'),

('Informatica', 'Cuenta de usuario'),

('Informatica', 'Correo electrónico'),

('Informatica', 'Otro');


-- ============================================================
-- CATEGORÍAS MANTENIMIENTO
-- ============================================================

INSERT INTO categorias
(tipo, nombre)
VALUES

('Mantenimiento', 'Electricidad'),

('Mantenimiento', 'Iluminacion'),

('Mantenimiento', 'Mobiliario'),

('Mantenimiento', 'Puertas'),

('Mantenimiento', 'Ventanas'),

('Mantenimiento', 'Agua'),

('Mantenimiento', 'Baños'),

('Mantenimiento', 'Pintura'),

('Mantenimiento', 'Calefaccion'),

('Mantenimiento', 'Ventilacion'),

('Mantenimiento', 'Cerrajeria'),

('Mantenimiento', 'Otro');


-- ============================================================
-- SECTORES INICIALES
-- Después pueden agregarse/modificarse desde administración
-- ============================================================

INSERT INTO sectores
(nombre, tipo)
VALUES

('Sala de Informatica', 'Sala'),

('Biblioteca', 'Biblioteca'),

('Secretaria', 'Oficina'),

('Direccion', 'Oficina'),

('Administracion', 'Oficina'),

('Sala de Profesores', 'Sala'),

('Patio', 'Patio');


-- ============================================================
-- ÍNDICES
-- Mejoran el rendimiento de búsquedas
-- ============================================================

CREATE INDEX idx_solicitudes_estado
ON solicitudes(estado);

CREATE INDEX idx_solicitudes_tipo
ON solicitudes(tipo);

CREATE INDEX idx_solicitudes_prioridad
ON solicitudes(prioridad);

CREATE INDEX idx_solicitudes_usuario
ON solicitudes(id_usuario);

CREATE INDEX idx_solicitudes_fecha
ON solicitudes(fecha_creacion);

CREATE INDEX idx_intervenciones_solicitud
ON intervenciones(id_solicitud);

CREATE INDEX idx_historial_solicitud
ON solicitud_historial(id_solicitud);

CREATE INDEX idx_mejoras_estado
ON mejoras(estado);

CREATE INDEX idx_notificaciones_usuario
ON notificaciones(id_usuario, leida);


-- ============================================================
-- VISTA: RESUMEN DE SOLICITUDES
-- Facilita consultas desde dashboard.php
-- ============================================================

CREATE VIEW vista_solicitudes AS

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

    u.id_usuario,

    CONCAT(u.nombre, ' ', u.apellido)
        AS docente,

    u.correo,

    sec.nombre AS sector,

    c.nombre AS categoria

FROM solicitudes s

INNER JOIN usuarios u
    ON s.id_usuario = u.id_usuario

LEFT JOIN sectores sec
    ON s.id_sector = sec.id_sector

LEFT JOIN categorias c
    ON s.id_categoria = c.id_categoria;


-- ============================================================
-- VISTA: SOLICITUDES PENDIENTES
-- ============================================================

CREATE VIEW vista_pendientes AS

SELECT

    s.id_solicitud,

    s.tipo,

    s.titulo,

    s.prioridad,

    s.motivo_pendiente,

    s.fecha_creacion,

    CONCAT(u.nombre, ' ', u.apellido)
        AS solicitante,

    sec.nombre AS sector

FROM solicitudes s

INNER JOIN usuarios u
    ON s.id_usuario = u.id_usuario

LEFT JOIN sectores sec
    ON s.id_sector = sec.id_sector

WHERE s.estado = 'Pendiente';


-- ============================================================
-- VISTA: ESTADÍSTICAS DEL DASHBOARD
-- ============================================================

CREATE VIEW vista_estadisticas AS

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
    ) AS resueltas,

    SUM(
        CASE
            WHEN estado = 'Cerrada'
            THEN 1
            ELSE 0
        END
    ) AS cerradas

FROM solicitudes;


-- ============================================================
-- ACTUALIZACIÓN v2
--
-- Para instalaciones nuevas, después de este archivo
-- ejecutar también:
--
-- database/actualizacion_v2.sql
--
-- Agrega: stock de repuestos, horarios y turnos de técnicos,
-- horas extra / compensación, y clasificación del motivo
-- de "Pendiente".
-- ============================================================


-- ============================================================
-- FIN BASE DE DATOS
-- ============================================================


    INSERT INTO usuarios (
        nombre,
        apellido,
        correo,
        dni_hash,
        rol,
        estado
    ) VALUES (
        'Gastón',
        'Ferreyra',
        'ferreyragaston351@gmail.com',
        '28115617',
        'Administrador',
        'Activo'
    );

    INSERT INTO usuarios (
        nombre,
        apellido,
        correo,
        dni_hash,
        rol,
        estado
    ) VALUES (
        'Gastón',
        'Ferreyra',
        'gastonferreyra@colegiodesanjose.edu.ar',
        '28115617',
        'Tecnico',
        'Activo'
    );

 INSERT INTO usuarios (
    nombre,
    apellido,
    correo,
    dni_hash,
    rol,
    estado
) VALUES (
    'Antonella',
    'Casadey',
    'antonellacasadey@colegiodesanjose.edu.ar',
    '$2y$12$mCU/KgfvyonGN0BnApkTpeT6RiyUZ.VyNd9sOIryg1hzF75qiAkQy',
    'Docente',
    'Activo'
);