-- ============================================================
-- COLEGIO SAN JOSE
-- ACTUALIZACIÓN v4
--
-- - Agrega a "solicitudes_asignaciones" las columnas
--   fecha_fin y observaciones, usadas al desasignar/finalizar
--   una intervención (admin/asignar.php, tecnico/finalizar.php,
--   tecnico/solicitudes.php) pero que faltaban en la tabla.
--
-- Ejecutar UNA VEZ en phpMyAdmin sobre la base "tecnica_sanjo",
-- después de actualizacion_v3.sql.
-- ============================================================

USE tecnica_sanjo;


SET @existe_fecha_fin := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'tecnica_sanjo'
    AND TABLE_NAME = 'solicitudes_asignaciones'
    AND COLUMN_NAME = 'fecha_fin'
);

SET @sql_alter_fecha_fin := IF(
    @existe_fecha_fin = 0,
    "ALTER TABLE solicitudes_asignaciones
        ADD COLUMN fecha_fin TIMESTAMP NULL AFTER fecha_asignacion",
    'SELECT 1'
);

PREPARE stmt_alter_fecha_fin FROM @sql_alter_fecha_fin;
EXECUTE stmt_alter_fecha_fin;
DEALLOCATE PREPARE stmt_alter_fecha_fin;


SET @existe_observaciones := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'tecnica_sanjo'
    AND TABLE_NAME = 'solicitudes_asignaciones'
    AND COLUMN_NAME = 'observaciones'
);

SET @sql_alter_observaciones := IF(
    @existe_observaciones = 0,
    "ALTER TABLE solicitudes_asignaciones
        ADD COLUMN observaciones TEXT NULL",
    'SELECT 1'
);

PREPARE stmt_alter_observaciones FROM @sql_alter_observaciones;
EXECUTE stmt_alter_observaciones;
DEALLOCATE PREPARE stmt_alter_observaciones;


-- ============================================================
-- FIN ACTUALIZACIÓN v4
-- ============================================================
