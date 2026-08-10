-- ============================================================
-- COLEGIO SAN JOSE
-- RESET DE SOLICITUDES DE PRUEBA
--
-- Borra todos los tickets (y todo lo que cuelga de ellos:
-- asignaciones, comentarios, intervenciones + sus fotos,
-- historial, fotos del pedido, materiales) y las
-- notificaciones generadas durante las pruebas.
--
-- NO TOCA: usuarios, sectores, categorías, configuración,
-- horarios (informática/mantenimiento y técnicos), catálogo
-- de repuestos ni su historial de movimientos de stock.
--
-- Ejecutar en phpMyAdmin sobre la base "tecnica_sanjo" cuando
-- quieras arrancar de cero con los tickets, conservando
-- usuarios y catálogos reales.
-- ============================================================

USE tecnica_sanjo;

SET FOREIGN_KEY_CHECKS = 0;


-- Sin relación por clave foránea con solicitudes: se limpia aparte.
DELETE FROM notificaciones;


-- Al borrar solicitudes se arrastran automáticamente (ON DELETE
-- CASCADE): comentarios, intervenciones (y sus fotos),
-- solicitudes_asignaciones, solicitud_historial,
-- solicitud_imagenes, materiales y turnos_reparacion.
DELETE FROM solicitudes;


-- Reiniciar los contadores de ticket para que el próximo
-- vuelva a ser SJ-000001.
ALTER TABLE solicitudes AUTO_INCREMENT = 1;
ALTER TABLE solicitudes_asignaciones AUTO_INCREMENT = 1;
ALTER TABLE solicitud_historial AUTO_INCREMENT = 1;
ALTER TABLE solicitud_imagenes AUTO_INCREMENT = 1;
ALTER TABLE comentarios AUTO_INCREMENT = 1;
ALTER TABLE intervenciones AUTO_INCREMENT = 1;
ALTER TABLE intervencion_imagenes AUTO_INCREMENT = 1;
ALTER TABLE materiales AUTO_INCREMENT = 1;
ALTER TABLE turnos_reparacion AUTO_INCREMENT = 1;
ALTER TABLE notificaciones AUTO_INCREMENT = 1;


SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- FIN
-- ============================================================
