-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 11-08-2026 a las 00:37:09
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- Evita errores de "no se puede eliminar la tabla porque
-- está referenciada por una clave foránea" al recrear tablas
-- que ya existan (por ejemplo, de una importación anterior
-- que quedó a medio hacer). Se vuelve a activar al final.
SET FOREIGN_KEY_CHECKS = 0;


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `tecnica_sanjo`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `categorias`
--

DROP TABLE IF EXISTS `categorias`;
CREATE TABLE `categorias` (
  `id_categoria` int(11) NOT NULL,
  `tipo` enum('Informatica','Mantenimiento') NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `categorias`
--

INSERT INTO `categorias` (`id_categoria`, `tipo`, `nombre`, `descripcion`, `activo`) VALUES
(1, 'Informatica', 'Computadora', NULL, 1),
(2, 'Informatica', 'Notebook', NULL, 1),
(3, 'Informatica', 'Internet', NULL, 1),
(4, 'Informatica', 'WiFi', NULL, 1),
(5, 'Informatica', 'Red', NULL, 1),
(6, 'Informatica', 'Proyector', NULL, 1),
(7, 'Informatica', 'Pantalla', NULL, 1),
(8, 'Informatica', 'Audio', NULL, 1),
(9, 'Informatica', 'Impresora', NULL, 1),
(10, 'Informatica', 'Software', NULL, 1),
(11, 'Informatica', 'Sistema operativo', NULL, 1),
(12, 'Informatica', 'Cuenta de usuario', NULL, 1),
(13, 'Informatica', 'Correo electrónico', NULL, 1),
(14, 'Informatica', 'Otro', NULL, 1),
(15, 'Mantenimiento', 'Electricidad', NULL, 1),
(16, 'Mantenimiento', 'Iluminacion', NULL, 1),
(17, 'Mantenimiento', 'Mobiliario', NULL, 1),
(18, 'Mantenimiento', 'Puertas', NULL, 1),
(19, 'Mantenimiento', 'Ventanas', NULL, 1),
(20, 'Mantenimiento', 'Agua', NULL, 1),
(21, 'Mantenimiento', 'Baños', NULL, 1),
(22, 'Mantenimiento', 'Pintura', NULL, 1),
(23, 'Mantenimiento', 'Calefaccion', NULL, 1),
(24, 'Mantenimiento', 'Ventilacion', NULL, 1),
(25, 'Mantenimiento', 'Cerrajeria', NULL, 1),
(26, 'Mantenimiento', 'Otro', NULL, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `comentarios`
--

DROP TABLE IF EXISTS `comentarios`;
CREATE TABLE `comentarios` (
  `id_comentario` int(11) NOT NULL,
  `id_solicitud` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `comentario` text NOT NULL,
  `fecha` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `configuracion`
--

DROP TABLE IF EXISTS `configuracion`;
CREATE TABLE `configuracion` (
  `id_configuracion` int(11) NOT NULL,
  `clave` varchar(100) NOT NULL,
  `valor` text DEFAULT NULL,
  `descripcion` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `configuracion`
--

INSERT INTO `configuracion` (`id_configuracion`, `clave`, `valor`, `descripcion`) VALUES
(1, 'nombre_sistema', 'Sistema de Solicitudes e Intervenciones', 'Nombre principal del sistema'),
(2, 'nombre_institucion', 'Colegio San José', 'Nombre de la institución'),
(3, 'max_imagen_mb', '5', 'Tamaño máximo permitido por imagen');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `horarios_mantenimiento`
--

DROP TABLE IF EXISTS `horarios_mantenimiento`;
CREATE TABLE `horarios_mantenimiento` (
  `id_horario` int(11) NOT NULL,
  `tipo` enum('Informatica','Mantenimiento') NOT NULL,
  `dia` enum('Lunes','Martes','Miercoles','Jueves','Viernes','Sabado') NOT NULL,
  `hora_desde` time NOT NULL,
  `hora_hasta` time NOT NULL,
  `responsable` varchar(150) DEFAULT NULL,
  `observaciones` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `horarios_mantenimiento`
--

INSERT INTO `horarios_mantenimiento` (`id_horario`, `tipo`, `dia`, `hora_desde`, `hora_hasta`, `responsable`, `observaciones`, `activo`) VALUES
(1, 'Informatica', 'Lunes', '07:30:00', '09:05:00', NULL, NULL, 1),
(2, 'Informatica', 'Martes', '07:30:00', '09:05:00', NULL, NULL, 1),
(3, 'Informatica', 'Miercoles', '10:35:00', '13:50:00', NULL, NULL, 1),
(4, 'Informatica', 'Viernes', '10:35:00', '14:00:00', NULL, NULL, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `horarios_tecnicos`
--

DROP TABLE IF EXISTS `horarios_tecnicos`;
CREATE TABLE `horarios_tecnicos` (
  `id_horario_tecnico` int(11) NOT NULL,
  `id_tecnico` int(11) NOT NULL,
  `dia` enum('Lunes','Martes','Miercoles','Jueves','Viernes','Sabado') NOT NULL,
  `hora_desde` time NOT NULL,
  `hora_hasta` time NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `horas_extra`
--

DROP TABLE IF EXISTS `horas_extra`;
CREATE TABLE `horas_extra` (
  `id_hora_extra` int(11) NOT NULL,
  `id_tecnico` int(11) NOT NULL,
  `id_turno` int(11) DEFAULT NULL,
  `id_solicitud` int(11) DEFAULT NULL,
  `tipo` enum('Hora extra','Compensacion') NOT NULL,
  `horas` decimal(5,2) NOT NULL,
  `motivo` varchar(255) DEFAULT NULL,
  `semana_compensar` date DEFAULT NULL,
  `estado` enum('Pendiente','Utilizada','Pagada','Cancelada') NOT NULL DEFAULT 'Pendiente',
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `intervenciones`
--

DROP TABLE IF EXISTS `intervenciones`;
CREATE TABLE `intervenciones` (
  `id_intervencion` int(11) NOT NULL,
  `id_solicitud` int(11) NOT NULL,
  `id_tecnico` int(11) NOT NULL,
  `diagnostico` text DEFAULT NULL,
  `trabajo_realizado` text DEFAULT NULL,
  `materiales` text DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `pendiente` tinyint(1) NOT NULL DEFAULT 0,
  `motivo_pendiente` text DEFAULT NULL,
  `tipo_pendiente` enum('Falta de repuesto','Horas insuficientes','Reprogramacion','Otro') DEFAULT NULL,
  `fecha_inicio` datetime DEFAULT NULL,
  `fecha_fin` datetime DEFAULT NULL,
  `fecha_intervencion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `intervencion_imagenes`
--

DROP TABLE IF EXISTS `intervencion_imagenes`;
CREATE TABLE `intervencion_imagenes` (
  `id_imagen` int(11) NOT NULL,
  `id_intervencion` int(11) NOT NULL,
  `archivo` varchar(255) NOT NULL,
  `nombre_original` varchar(255) DEFAULT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `tipo` enum('Antes','Durante','Despues') NOT NULL DEFAULT 'Despues',
  `fecha` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `materiales`
--

DROP TABLE IF EXISTS `materiales`;
CREATE TABLE `materiales` (
  `id_material` int(11) NOT NULL,
  `id_solicitud` int(11) DEFAULT NULL,
  `id_mejora` int(11) DEFAULT NULL,
  `id_repuesto` int(11) DEFAULT NULL,
  `descripcion` varchar(255) NOT NULL,
  `cantidad` int(11) NOT NULL DEFAULT 1,
  `costo_estimado` decimal(12,2) DEFAULT NULL,
  `enlace_compra` text DEFAULT NULL,
  `estado` enum('Necesario','Solicitado','Autorizado','Comprado','Recibido','Utilizado','Cancelado') NOT NULL DEFAULT 'Necesario',
  `observaciones` text DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `mejoras`
--

DROP TABLE IF EXISTS `mejoras`;
CREATE TABLE `mejoras` (
  `id_mejora` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `id_sector` int(11) DEFAULT NULL,
  `tipo` enum('Informatica','Mantenimiento') NOT NULL,
  `titulo` varchar(200) NOT NULL,
  `descripcion` text NOT NULL,
  `justificacion` text DEFAULT NULL,
  `solucion_propuesta` text DEFAULT NULL,
  `cantidad` int(11) DEFAULT NULL,
  `costo_estimado` decimal(12,2) DEFAULT NULL,
  `prioridad` enum('Baja','Normal','Alta','Urgente') NOT NULL DEFAULT 'Normal',
  `estado` enum('Propuesta','En evaluacion','Pendiente autorizacion','Aprobada','En ejecucion','Realizada','Rechazada') NOT NULL DEFAULT 'Propuesta',
  `motivo_pendiente` text DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `mejora_imagenes`
--

DROP TABLE IF EXISTS `mejora_imagenes`;
CREATE TABLE `mejora_imagenes` (
  `id_imagen` int(11) NOT NULL,
  `id_mejora` int(11) NOT NULL,
  `archivo` varchar(255) NOT NULL,
  `nombre_original` varchar(255) DEFAULT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `fecha` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `notificaciones`
--

DROP TABLE IF EXISTS `notificaciones`;
CREATE TABLE `notificaciones` (
  `id_notificacion` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `titulo` varchar(200) NOT NULL,
  `mensaje` text NOT NULL,
  `enlace` varchar(255) DEFAULT NULL,
  `leida` tinyint(1) NOT NULL DEFAULT 0,
  `fecha` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `repuestos`
--

DROP TABLE IF EXISTS `repuestos`;
CREATE TABLE `repuestos` (
  `id_repuesto` int(11) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `categoria` enum('Informatica','Mantenimiento','General') NOT NULL DEFAULT 'General',
  `unidad` varchar(30) NOT NULL DEFAULT 'unidad',
  `stock_actual` int(11) NOT NULL DEFAULT 0,
  `stock_minimo` int(11) NOT NULL DEFAULT 0,
  `costo_unitario` decimal(12,2) DEFAULT NULL,
  `ubicacion` varchar(150) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `repuestos`
--

INSERT INTO `repuestos` (`id_repuesto`, `nombre`, `descripcion`, `foto`, `categoria`, `unidad`, `stock_actual`, `stock_minimo`, `costo_unitario`, `ubicacion`, `activo`, `fecha_creacion`, `fecha_actualizacion`) VALUES
(1, 'Disco Solido Ssd Hiksemi Wave 120gb', 'Capacidad: 120 GB Con tecnología 3D NAND. Útil para guardar programas y documentos con su capacidad de 120 GB. Resistente al agua, polvo y golpes. Tamaño de 2.5 \". Optimizado para configuraciones RAID. Apto para PC y Notebook. Ver características', '20260810182137_6e8626987215547c.webp', 'Informatica', 'Und.', 1, 6, 67000.00, NULL, 1, '2026-08-10 21:21:37', '2026-08-10 21:31:28');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `repuestos_movimientos`
--

DROP TABLE IF EXISTS `repuestos_movimientos`;
CREATE TABLE `repuestos_movimientos` (
  `id_movimiento` int(11) NOT NULL,
  `id_repuesto` int(11) NOT NULL,
  `id_solicitud` int(11) DEFAULT NULL,
  `id_intervencion` int(11) DEFAULT NULL,
  `id_usuario` int(11) NOT NULL,
  `tipo` enum('Ingreso','Uso','Ajuste') NOT NULL,
  `direccion` enum('Entrada','Salida') NOT NULL,
  `cantidad` int(11) NOT NULL,
  `stock_resultante` int(11) NOT NULL,
  `observaciones` varchar(255) DEFAULT NULL,
  `fecha` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `sectores`
--

DROP TABLE IF EXISTS `sectores`;
CREATE TABLE `sectores` (
  `id_sector` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `tipo` enum('Aula','Oficina','Sala','Patio','Baño','Laboratorio','Biblioteca','Otro') NOT NULL DEFAULT 'Aula',
  `descripcion` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `sectores`
--

INSERT INTO `sectores` (`id_sector`, `nombre`, `tipo`, `descripcion`, `activo`, `fecha_creacion`) VALUES
(8, 'Sala de Informatica', 'Sala', NULL, 1, '2026-08-10 20:23:09'),
(9, 'Biblioteca', 'Biblioteca', NULL, 1, '2026-08-10 20:23:09'),
(10, 'Secretaria', 'Oficina', NULL, 1, '2026-08-10 20:23:09'),
(11, 'Direccion', 'Oficina', NULL, 1, '2026-08-10 20:23:09'),
(12, 'Administracion', 'Oficina', NULL, 1, '2026-08-10 20:23:09'),
(13, 'Tutores', 'Oficina', NULL, 1, '2026-08-10 20:23:09'),
(14, 'Tutores', 'Oficina', NULL, 1, '2026-08-10 20:23:09'),
(15, 'Sala de Profesores', 'Otro', NULL, 1, '2026-08-10 20:23:09'),
(16, 'Sala de Profesores', 'Otro', NULL, 1, '2026-08-10 20:23:09'),
(17, 'Sala de cuatro', 'Sala', NULL, 1, '2026-08-10 20:23:09'),
(18, 'Sala de cinco', 'Sala', NULL, 1, '2026-08-10 20:23:09'),
(19, 'Primer grado', 'Aula', NULL, 1, '2026-08-10 20:23:09'),
(20, 'Segundo grado', 'Aula', NULL, 1, '2026-08-10 20:23:09'),
(21, 'Terecer grado', 'Aula', NULL, 1, '2026-08-10 20:23:09'),
(22, 'Cuarto grado', 'Aula', NULL, 1, '2026-08-10 20:23:09'),
(23, 'Quinto grado', 'Aula', NULL, 1, '2026-08-10 20:23:09'),
(24, 'Sexto grado', 'Aula', NULL, 1, '2026-08-10 20:23:09'),
(25, 'Primer Año- A', 'Aula', NULL, 1, '2026-08-10 20:23:09'),
(26, 'Segundo Año- A', 'Aula', NULL, 1, '2026-08-10 20:23:09'),
(27, 'Tercero Año- A', 'Aula', NULL, 1, '2026-08-10 20:23:09'),
(28, 'Cuarto Año- A', 'Aula', NULL, 1, '2026-08-10 20:23:09'),
(29, 'Quinto Año- A', 'Aula', NULL, 1, '2026-08-10 20:23:09'),
(30, 'Sexto Año- A', 'Aula', NULL, 1, '2026-08-10 20:23:09'),
(31, 'Primer Año- B', 'Aula', NULL, 1, '2026-08-10 20:23:09'),
(32, 'Segundo Año- B', 'Aula', NULL, 1, '2026-08-10 20:23:09'),
(33, 'Tercero Año- B', '', NULL, 1, '2026-08-10 20:23:09'),
(34, 'Cuarto Año- B', '', NULL, 1, '2026-08-10 20:23:09'),
(35, 'Quinto Año- B', '', NULL, 1, '2026-08-10 20:23:09'),
(36, 'Sexto Año- B', '', NULL, 1, '2026-08-10 20:23:09'),
(37, 'Patio', 'Patio', NULL, 1, '2026-08-10 20:23:09');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `solicitudes`
--

DROP TABLE IF EXISTS `solicitudes`;
CREATE TABLE `solicitudes` (
  `id_solicitud` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `id_sector` int(11) DEFAULT NULL,
  `id_categoria` int(11) DEFAULT NULL,
  `tipo` enum('Informatica','Mantenimiento') NOT NULL,
  `titulo` varchar(200) NOT NULL,
  `descripcion` text NOT NULL,
  `prioridad` enum('Baja','Normal','Alta','Urgente') NOT NULL DEFAULT 'Normal',
  `estado` enum('Nueva','Asignada','En proceso','Pendiente','Resuelta','Cerrada','Cancelada') NOT NULL DEFAULT 'Nueva',
  `motivo_pendiente` text DEFAULT NULL,
  `tipo_pendiente` enum('Falta de repuesto','Horas insuficientes','Reprogramacion','Otro') DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `fecha_resolucion` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `solicitudes_asignaciones`
--

DROP TABLE IF EXISTS `solicitudes_asignaciones`;
CREATE TABLE `solicitudes_asignaciones` (
  `id_asignacion` int(11) NOT NULL,
  `id_solicitud` int(11) NOT NULL,
  `id_tecnico` int(11) NOT NULL,
  `asignado_por` int(11) DEFAULT NULL,
  `fecha_asignacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_fin` timestamp NULL DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `observaciones` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `solicitud_historial`
--

DROP TABLE IF EXISTS `solicitud_historial`;
CREATE TABLE `solicitud_historial` (
  `id_historial` int(11) NOT NULL,
  `id_solicitud` int(11) NOT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `estado_anterior` varchar(50) DEFAULT NULL,
  `estado_nuevo` varchar(50) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `fecha` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `solicitud_imagenes`
--

DROP TABLE IF EXISTS `solicitud_imagenes`;
CREATE TABLE `solicitud_imagenes` (
  `id_imagen` int(11) NOT NULL,
  `id_solicitud` int(11) NOT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `archivo` varchar(255) NOT NULL,
  `nombre_original` varchar(255) DEFAULT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `tipo` enum('Solicitud','Intervencion','Solucion') NOT NULL DEFAULT 'Solicitud',
  `fecha` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `turnos_reparacion`
--

DROP TABLE IF EXISTS `turnos_reparacion`;
CREATE TABLE `turnos_reparacion` (
  `id_turno` int(11) NOT NULL,
  `id_solicitud` int(11) NOT NULL,
  `id_tecnico` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `hora_desde` time NOT NULL,
  `hora_hasta` time NOT NULL,
  `horas_estimadas` decimal(5,2) NOT NULL DEFAULT 1.00,
  `estado` enum('Programado','Confirmado','Reprogramado','Completado','Cancelado') NOT NULL DEFAULT 'Programado',
  `motivo_reprogramacion` text DEFAULT NULL,
  `id_turno_origen` int(11) DEFAULT NULL,
  `creado_por` int(11) NOT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
CREATE TABLE `usuarios` (
  `id_usuario` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) NOT NULL,
  `correo` varchar(150) NOT NULL,
  `telefono` varchar(30) DEFAULT NULL,
  `whatsapp_apikey` varchar(20) DEFAULT NULL,
  `dni_hash` varchar(255) NOT NULL,
  `rol` enum('Docente','Tecnico','Administrador') NOT NULL DEFAULT 'Docente',
  `estado` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
  `ultimo_acceso` datetime DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id_usuario`, `nombre`, `apellido`, `correo`, `telefono`, `whatsapp_apikey`, `dni_hash`, `rol`, `estado`, `ultimo_acceso`, `fecha_creacion`, `fecha_actualizacion`) VALUES
(1, 'Gastón', 'Admin', 'ferreyragaston351@gmail.com', NULL, NULL, '$2y$10$1vsX2zGtUdxHSMnqAi5YQuo7tN27Oaiuk1ff35jsbn.izKwg7ONPW', 'Administrador', 'Activo', '2026-08-10 17:40:04', '2026-08-10 19:37:08', '2026-08-10 22:32:47'),
(2, 'Gastón', 'Ferreyra', 'gastonferreyra@colegiodesanjose.edu.ar', '3515165960', NULL, '$2y$10$gAm.0Ob.PTSdO4lF84EBB.LNsugM9snF0lHpOTUGtud9wAo0JaXnq', 'Tecnico', 'Activo', '2026-08-10 18:27:34', '2026-08-10 19:37:08', '2026-08-10 21:27:34'),
(3, 'Antonella', 'Casadey', 'antonellacasadey@colegiodesanjose.edu.ar', NULL, NULL, '$2y$12$mCU/KgfvyonGN0BnApkTpeT6RiyUZ.VyNd9sOIryg1hzF75qiAkQy', 'Docente', 'Activo', '2026-08-10 16:40:32', '2026-08-10 19:37:08', '2026-08-10 19:40:32');

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vista_estadisticas`
-- (Véase abajo para la vista actual)
--
DROP TABLE IF EXISTS `vista_estadisticas`;
CREATE TABLE `vista_estadisticas` (
`total` bigint(21)
,`nuevas` decimal(22,0)
,`en_proceso` decimal(22,0)
,`pendientes` decimal(22,0)
,`resueltas` decimal(22,0)
,`cerradas` decimal(22,0)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vista_pendientes`
-- (Véase abajo para la vista actual)
--
DROP TABLE IF EXISTS `vista_pendientes`;
CREATE TABLE `vista_pendientes` (
`id_solicitud` int(11)
,`tipo` enum('Informatica','Mantenimiento')
,`titulo` varchar(200)
,`prioridad` enum('Baja','Normal','Alta','Urgente')
,`motivo_pendiente` text
,`fecha_creacion` timestamp
,`solicitante` varchar(201)
,`sector` varchar(100)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vista_solicitudes`
-- (Véase abajo para la vista actual)
--
DROP TABLE IF EXISTS `vista_solicitudes`;
CREATE TABLE `vista_solicitudes` (
`id_solicitud` int(11)
,`tipo` enum('Informatica','Mantenimiento')
,`titulo` varchar(200)
,`descripcion` text
,`prioridad` enum('Baja','Normal','Alta','Urgente')
,`estado` enum('Nueva','Asignada','En proceso','Pendiente','Resuelta','Cerrada','Cancelada')
,`motivo_pendiente` text
,`fecha_creacion` timestamp
,`fecha_actualizacion` timestamp
,`fecha_resolucion` datetime
,`id_usuario` int(11)
,`docente` varchar(201)
,`correo` varchar(150)
,`sector` varchar(100)
,`categoria` varchar(100)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vista_stock_bajo`
-- (Véase abajo para la vista actual)
--
DROP TABLE IF EXISTS `vista_stock_bajo`;
CREATE TABLE `vista_stock_bajo` (
`id_repuesto` int(11)
,`nombre` varchar(150)
,`descripcion` varchar(255)
,`categoria` enum('Informatica','Mantenimiento','General')
,`unidad` varchar(30)
,`stock_actual` int(11)
,`stock_minimo` int(11)
,`costo_unitario` decimal(12,2)
,`ubicacion` varchar(150)
,`activo` tinyint(1)
,`fecha_creacion` timestamp
,`fecha_actualizacion` timestamp
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vista_turnos`
-- (Véase abajo para la vista actual)
--
DROP TABLE IF EXISTS `vista_turnos`;
CREATE TABLE `vista_turnos` (
`id_turno` int(11)
,`id_solicitud` int(11)
,`id_tecnico` int(11)
,`fecha` date
,`hora_desde` time
,`hora_hasta` time
,`horas_estimadas` decimal(5,2)
,`estado` enum('Programado','Confirmado','Reprogramado','Completado','Cancelado')
,`motivo_reprogramacion` text
,`titulo` varchar(200)
,`tipo` enum('Informatica','Mantenimiento')
,`prioridad` enum('Baja','Normal','Alta','Urgente')
,`estado_solicitud` enum('Nueva','Asignada','En proceso','Pendiente','Resuelta','Cerrada','Cancelada')
,`tecnico` varchar(201)
,`docente` varchar(201)
,`sector` varchar(100)
);

-- --------------------------------------------------------

--
-- Estructura para la vista `vista_estadisticas`
--
DROP TABLE IF EXISTS `vista_estadisticas`;

CREATE VIEW `vista_estadisticas`  AS SELECT count(0) AS `total`, sum(case when `solicitudes`.`estado` = 'Nueva' then 1 else 0 end) AS `nuevas`, sum(case when `solicitudes`.`estado` = 'En proceso' then 1 else 0 end) AS `en_proceso`, sum(case when `solicitudes`.`estado` = 'Pendiente' then 1 else 0 end) AS `pendientes`, sum(case when `solicitudes`.`estado` = 'Resuelta' then 1 else 0 end) AS `resueltas`, sum(case when `solicitudes`.`estado` = 'Cerrada' then 1 else 0 end) AS `cerradas` FROM `solicitudes` ;

-- --------------------------------------------------------

--
-- Estructura para la vista `vista_pendientes`
--
DROP TABLE IF EXISTS `vista_pendientes`;

CREATE VIEW `vista_pendientes`  AS SELECT `s`.`id_solicitud` AS `id_solicitud`, `s`.`tipo` AS `tipo`, `s`.`titulo` AS `titulo`, `s`.`prioridad` AS `prioridad`, `s`.`motivo_pendiente` AS `motivo_pendiente`, `s`.`fecha_creacion` AS `fecha_creacion`, concat(`u`.`nombre`,' ',`u`.`apellido`) AS `solicitante`, `sec`.`nombre` AS `sector` FROM ((`solicitudes` `s` join `usuarios` `u` on(`s`.`id_usuario` = `u`.`id_usuario`)) left join `sectores` `sec` on(`s`.`id_sector` = `sec`.`id_sector`)) WHERE `s`.`estado` = 'Pendiente' ;

-- --------------------------------------------------------

--
-- Estructura para la vista `vista_solicitudes`
--
DROP TABLE IF EXISTS `vista_solicitudes`;

CREATE VIEW `vista_solicitudes`  AS SELECT `s`.`id_solicitud` AS `id_solicitud`, `s`.`tipo` AS `tipo`, `s`.`titulo` AS `titulo`, `s`.`descripcion` AS `descripcion`, `s`.`prioridad` AS `prioridad`, `s`.`estado` AS `estado`, `s`.`motivo_pendiente` AS `motivo_pendiente`, `s`.`fecha_creacion` AS `fecha_creacion`, `s`.`fecha_actualizacion` AS `fecha_actualizacion`, `s`.`fecha_resolucion` AS `fecha_resolucion`, `u`.`id_usuario` AS `id_usuario`, concat(`u`.`nombre`,' ',`u`.`apellido`) AS `docente`, `u`.`correo` AS `correo`, `sec`.`nombre` AS `sector`, `c`.`nombre` AS `categoria` FROM (((`solicitudes` `s` join `usuarios` `u` on(`s`.`id_usuario` = `u`.`id_usuario`)) left join `sectores` `sec` on(`s`.`id_sector` = `sec`.`id_sector`)) left join `categorias` `c` on(`s`.`id_categoria` = `c`.`id_categoria`)) ;

-- --------------------------------------------------------

--
-- Estructura para la vista `vista_stock_bajo`
--
DROP TABLE IF EXISTS `vista_stock_bajo`;

CREATE VIEW `vista_stock_bajo`  AS SELECT `repuestos`.`id_repuesto` AS `id_repuesto`, `repuestos`.`nombre` AS `nombre`, `repuestos`.`descripcion` AS `descripcion`, `repuestos`.`categoria` AS `categoria`, `repuestos`.`unidad` AS `unidad`, `repuestos`.`stock_actual` AS `stock_actual`, `repuestos`.`stock_minimo` AS `stock_minimo`, `repuestos`.`costo_unitario` AS `costo_unitario`, `repuestos`.`ubicacion` AS `ubicacion`, `repuestos`.`activo` AS `activo`, `repuestos`.`fecha_creacion` AS `fecha_creacion`, `repuestos`.`fecha_actualizacion` AS `fecha_actualizacion` FROM `repuestos` WHERE `repuestos`.`activo` = 1 AND `repuestos`.`stock_actual` <= `repuestos`.`stock_minimo` ;

-- --------------------------------------------------------

--
-- Estructura para la vista `vista_turnos`
--
DROP TABLE IF EXISTS `vista_turnos`;

CREATE VIEW `vista_turnos`  AS SELECT `t`.`id_turno` AS `id_turno`, `t`.`id_solicitud` AS `id_solicitud`, `t`.`id_tecnico` AS `id_tecnico`, `t`.`fecha` AS `fecha`, `t`.`hora_desde` AS `hora_desde`, `t`.`hora_hasta` AS `hora_hasta`, `t`.`horas_estimadas` AS `horas_estimadas`, `t`.`estado` AS `estado`, `t`.`motivo_reprogramacion` AS `motivo_reprogramacion`, `s`.`titulo` AS `titulo`, `s`.`tipo` AS `tipo`, `s`.`prioridad` AS `prioridad`, `s`.`estado` AS `estado_solicitud`, concat(`td`.`nombre`,' ',`td`.`apellido`) AS `tecnico`, concat(`ud`.`nombre`,' ',`ud`.`apellido`) AS `docente`, `sec`.`nombre` AS `sector` FROM ((((`turnos_reparacion` `t` join `solicitudes` `s` on(`t`.`id_solicitud` = `s`.`id_solicitud`)) join `usuarios` `td` on(`t`.`id_tecnico` = `td`.`id_usuario`)) join `usuarios` `ud` on(`s`.`id_usuario` = `ud`.`id_usuario`)) left join `sectores` `sec` on(`s`.`id_sector` = `sec`.`id_sector`)) ;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `categorias`
--
ALTER TABLE `categorias`
  ADD PRIMARY KEY (`id_categoria`),
  ADD UNIQUE KEY `unique_categoria_tipo` (`tipo`,`nombre`);

--
-- Indices de la tabla `comentarios`
--
ALTER TABLE `comentarios`
  ADD PRIMARY KEY (`id_comentario`),
  ADD KEY `fk_comentario_solicitud` (`id_solicitud`),
  ADD KEY `fk_comentario_usuario` (`id_usuario`);

--
-- Indices de la tabla `configuracion`
--
ALTER TABLE `configuracion`
  ADD PRIMARY KEY (`id_configuracion`),
  ADD UNIQUE KEY `clave` (`clave`);

--
-- Indices de la tabla `horarios_mantenimiento`
--
ALTER TABLE `horarios_mantenimiento`
  ADD PRIMARY KEY (`id_horario`);

--
-- Indices de la tabla `horarios_tecnicos`
--
ALTER TABLE `horarios_tecnicos`
  ADD PRIMARY KEY (`id_horario_tecnico`),
  ADD KEY `idx_horarios_tecnicos_tecnico` (`id_tecnico`,`dia`);

--
-- Indices de la tabla `horas_extra`
--
ALTER TABLE `horas_extra`
  ADD PRIMARY KEY (`id_hora_extra`),
  ADD KEY `fk_hora_extra_turno` (`id_turno`),
  ADD KEY `fk_hora_extra_solicitud` (`id_solicitud`),
  ADD KEY `idx_horas_extra_tecnico` (`id_tecnico`,`estado`);

--
-- Indices de la tabla `intervenciones`
--
ALTER TABLE `intervenciones`
  ADD PRIMARY KEY (`id_intervencion`),
  ADD KEY `fk_intervencion_tecnico` (`id_tecnico`),
  ADD KEY `idx_intervenciones_solicitud` (`id_solicitud`);

--
-- Indices de la tabla `intervencion_imagenes`
--
ALTER TABLE `intervencion_imagenes`
  ADD PRIMARY KEY (`id_imagen`),
  ADD KEY `fk_intervencion_imagen` (`id_intervencion`);

--
-- Indices de la tabla `materiales`
--
ALTER TABLE `materiales`
  ADD PRIMARY KEY (`id_material`),
  ADD KEY `fk_material_solicitud` (`id_solicitud`),
  ADD KEY `fk_material_mejora` (`id_mejora`),
  ADD KEY `fk_material_repuesto` (`id_repuesto`);

--
-- Indices de la tabla `mejoras`
--
ALTER TABLE `mejoras`
  ADD PRIMARY KEY (`id_mejora`),
  ADD KEY `fk_mejora_usuario` (`id_usuario`),
  ADD KEY `fk_mejora_sector` (`id_sector`),
  ADD KEY `idx_mejoras_estado` (`estado`);

--
-- Indices de la tabla `mejora_imagenes`
--
ALTER TABLE `mejora_imagenes`
  ADD PRIMARY KEY (`id_imagen`),
  ADD KEY `fk_mejora_imagen` (`id_mejora`);

--
-- Indices de la tabla `notificaciones`
--
ALTER TABLE `notificaciones`
  ADD PRIMARY KEY (`id_notificacion`),
  ADD KEY `idx_notificaciones_usuario` (`id_usuario`,`leida`);

--
-- Indices de la tabla `repuestos`
--
ALTER TABLE `repuestos`
  ADD PRIMARY KEY (`id_repuesto`);

--
-- Indices de la tabla `repuestos_movimientos`
--
ALTER TABLE `repuestos_movimientos`
  ADD PRIMARY KEY (`id_movimiento`),
  ADD KEY `fk_movimiento_intervencion` (`id_intervencion`),
  ADD KEY `fk_movimiento_usuario` (`id_usuario`),
  ADD KEY `idx_repmov_repuesto` (`id_repuesto`),
  ADD KEY `idx_repmov_solicitud` (`id_solicitud`);

--
-- Indices de la tabla `sectores`
--
ALTER TABLE `sectores`
  ADD PRIMARY KEY (`id_sector`);

--
-- Indices de la tabla `solicitudes`
--
ALTER TABLE `solicitudes`
  ADD PRIMARY KEY (`id_solicitud`),
  ADD KEY `fk_solicitud_sector` (`id_sector`),
  ADD KEY `fk_solicitud_categoria` (`id_categoria`),
  ADD KEY `idx_solicitudes_estado` (`estado`),
  ADD KEY `idx_solicitudes_tipo` (`tipo`),
  ADD KEY `idx_solicitudes_prioridad` (`prioridad`),
  ADD KEY `idx_solicitudes_usuario` (`id_usuario`),
  ADD KEY `idx_solicitudes_fecha` (`fecha_creacion`);

--
-- Indices de la tabla `solicitudes_asignaciones`
--
ALTER TABLE `solicitudes_asignaciones`
  ADD PRIMARY KEY (`id_asignacion`),
  ADD KEY `fk_asignacion_solicitud` (`id_solicitud`),
  ADD KEY `fk_asignacion_tecnico` (`id_tecnico`),
  ADD KEY `fk_asignacion_usuario` (`asignado_por`);

--
-- Indices de la tabla `solicitud_historial`
--
ALTER TABLE `solicitud_historial`
  ADD PRIMARY KEY (`id_historial`),
  ADD KEY `fk_historial_usuario` (`id_usuario`),
  ADD KEY `idx_historial_solicitud` (`id_solicitud`);

--
-- Indices de la tabla `solicitud_imagenes`
--
ALTER TABLE `solicitud_imagenes`
  ADD PRIMARY KEY (`id_imagen`),
  ADD KEY `fk_imagen_solicitud` (`id_solicitud`),
  ADD KEY `fk_imagen_usuario` (`id_usuario`);

--
-- Indices de la tabla `turnos_reparacion`
--
ALTER TABLE `turnos_reparacion`
  ADD PRIMARY KEY (`id_turno`),
  ADD KEY `fk_turno_creador` (`creado_por`),
  ADD KEY `fk_turno_origen` (`id_turno_origen`),
  ADD KEY `idx_turnos_tecnico_fecha` (`id_tecnico`,`fecha`),
  ADD KEY `idx_turnos_solicitud` (`id_solicitud`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `correo` (`correo`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `categorias`
--
ALTER TABLE `categorias`
  MODIFY `id_categoria` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT de la tabla `comentarios`
--
ALTER TABLE `comentarios`
  MODIFY `id_comentario` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `configuracion`
--
ALTER TABLE `configuracion`
  MODIFY `id_configuracion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `horarios_mantenimiento`
--
ALTER TABLE `horarios_mantenimiento`
  MODIFY `id_horario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `horarios_tecnicos`
--
ALTER TABLE `horarios_tecnicos`
  MODIFY `id_horario_tecnico` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `horas_extra`
--
ALTER TABLE `horas_extra`
  MODIFY `id_hora_extra` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `intervenciones`
--
ALTER TABLE `intervenciones`
  MODIFY `id_intervencion` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `intervencion_imagenes`
--
ALTER TABLE `intervencion_imagenes`
  MODIFY `id_imagen` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `materiales`
--
ALTER TABLE `materiales`
  MODIFY `id_material` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `mejoras`
--
ALTER TABLE `mejoras`
  MODIFY `id_mejora` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `mejora_imagenes`
--
ALTER TABLE `mejora_imagenes`
  MODIFY `id_imagen` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `notificaciones`
--
ALTER TABLE `notificaciones`
  MODIFY `id_notificacion` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `repuestos`
--
ALTER TABLE `repuestos`
  MODIFY `id_repuesto` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `repuestos_movimientos`
--
ALTER TABLE `repuestos_movimientos`
  MODIFY `id_movimiento` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `sectores`
--
ALTER TABLE `sectores`
  MODIFY `id_sector` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT de la tabla `solicitudes`
--
ALTER TABLE `solicitudes`
  MODIFY `id_solicitud` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `solicitudes_asignaciones`
--
ALTER TABLE `solicitudes_asignaciones`
  MODIFY `id_asignacion` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `solicitud_historial`
--
ALTER TABLE `solicitud_historial`
  MODIFY `id_historial` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `solicitud_imagenes`
--
ALTER TABLE `solicitud_imagenes`
  MODIFY `id_imagen` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `turnos_reparacion`
--
ALTER TABLE `turnos_reparacion`
  MODIFY `id_turno` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `comentarios`
--
ALTER TABLE `comentarios`
  ADD CONSTRAINT `fk_comentario_solicitud` FOREIGN KEY (`id_solicitud`) REFERENCES `solicitudes` (`id_solicitud`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_comentario_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE;

--
-- Filtros para la tabla `horarios_tecnicos`
--
ALTER TABLE `horarios_tecnicos`
  ADD CONSTRAINT `fk_horario_tecnico_usuario` FOREIGN KEY (`id_tecnico`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE;

--
-- Filtros para la tabla `horas_extra`
--
ALTER TABLE `horas_extra`
  ADD CONSTRAINT `fk_hora_extra_solicitud` FOREIGN KEY (`id_solicitud`) REFERENCES `solicitudes` (`id_solicitud`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_hora_extra_tecnico` FOREIGN KEY (`id_tecnico`) REFERENCES `usuarios` (`id_usuario`),
  ADD CONSTRAINT `fk_hora_extra_turno` FOREIGN KEY (`id_turno`) REFERENCES `turnos_reparacion` (`id_turno`) ON DELETE SET NULL;

--
-- Filtros para la tabla `intervenciones`
--
ALTER TABLE `intervenciones`
  ADD CONSTRAINT `fk_intervencion_solicitud` FOREIGN KEY (`id_solicitud`) REFERENCES `solicitudes` (`id_solicitud`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_intervencion_tecnico` FOREIGN KEY (`id_tecnico`) REFERENCES `usuarios` (`id_usuario`);

--
-- Filtros para la tabla `intervencion_imagenes`
--
ALTER TABLE `intervencion_imagenes`
  ADD CONSTRAINT `fk_intervencion_imagen` FOREIGN KEY (`id_intervencion`) REFERENCES `intervenciones` (`id_intervencion`) ON DELETE CASCADE;

--
-- Filtros para la tabla `materiales`
--
ALTER TABLE `materiales`
  ADD CONSTRAINT `fk_material_mejora` FOREIGN KEY (`id_mejora`) REFERENCES `mejoras` (`id_mejora`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_material_repuesto` FOREIGN KEY (`id_repuesto`) REFERENCES `repuestos` (`id_repuesto`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_material_solicitud` FOREIGN KEY (`id_solicitud`) REFERENCES `solicitudes` (`id_solicitud`) ON DELETE CASCADE;

--
-- Filtros para la tabla `mejoras`
--
ALTER TABLE `mejoras`
  ADD CONSTRAINT `fk_mejora_sector` FOREIGN KEY (`id_sector`) REFERENCES `sectores` (`id_sector`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_mejora_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`);

--
-- Filtros para la tabla `mejora_imagenes`
--
ALTER TABLE `mejora_imagenes`
  ADD CONSTRAINT `fk_mejora_imagen` FOREIGN KEY (`id_mejora`) REFERENCES `mejoras` (`id_mejora`) ON DELETE CASCADE;

--
-- Filtros para la tabla `notificaciones`
--
ALTER TABLE `notificaciones`
  ADD CONSTRAINT `fk_notificacion_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE;

--
-- Filtros para la tabla `repuestos_movimientos`
--
ALTER TABLE `repuestos_movimientos`
  ADD CONSTRAINT `fk_movimiento_intervencion` FOREIGN KEY (`id_intervencion`) REFERENCES `intervenciones` (`id_intervencion`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_movimiento_repuesto` FOREIGN KEY (`id_repuesto`) REFERENCES `repuestos` (`id_repuesto`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_movimiento_solicitud` FOREIGN KEY (`id_solicitud`) REFERENCES `solicitudes` (`id_solicitud`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_movimiento_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`);

--
-- Filtros para la tabla `solicitudes`
--
ALTER TABLE `solicitudes`
  ADD CONSTRAINT `fk_solicitud_categoria` FOREIGN KEY (`id_categoria`) REFERENCES `categorias` (`id_categoria`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_solicitud_sector` FOREIGN KEY (`id_sector`) REFERENCES `sectores` (`id_sector`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_solicitud_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`);

--
-- Filtros para la tabla `solicitudes_asignaciones`
--
ALTER TABLE `solicitudes_asignaciones`
  ADD CONSTRAINT `fk_asignacion_solicitud` FOREIGN KEY (`id_solicitud`) REFERENCES `solicitudes` (`id_solicitud`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_asignacion_tecnico` FOREIGN KEY (`id_tecnico`) REFERENCES `usuarios` (`id_usuario`),
  ADD CONSTRAINT `fk_asignacion_usuario` FOREIGN KEY (`asignado_por`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL;

--
-- Filtros para la tabla `solicitud_historial`
--
ALTER TABLE `solicitud_historial`
  ADD CONSTRAINT `fk_historial_solicitud` FOREIGN KEY (`id_solicitud`) REFERENCES `solicitudes` (`id_solicitud`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_historial_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL;

--
-- Filtros para la tabla `solicitud_imagenes`
--
ALTER TABLE `solicitud_imagenes`
  ADD CONSTRAINT `fk_imagen_solicitud` FOREIGN KEY (`id_solicitud`) REFERENCES `solicitudes` (`id_solicitud`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_imagen_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL;

--
-- Filtros para la tabla `turnos_reparacion`
--
ALTER TABLE `turnos_reparacion`
  ADD CONSTRAINT `fk_turno_creador` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id_usuario`),
  ADD CONSTRAINT `fk_turno_origen` FOREIGN KEY (`id_turno_origen`) REFERENCES `turnos_reparacion` (`id_turno`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_turno_solicitud` FOREIGN KEY (`id_solicitud`) REFERENCES `solicitudes` (`id_solicitud`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_turno_tecnico` FOREIGN KEY (`id_tecnico`) REFERENCES `usuarios` (`id_usuario`);

SET FOREIGN_KEY_CHECKS = 1;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
