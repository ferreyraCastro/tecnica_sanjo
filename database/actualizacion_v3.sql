-- ============================================================
-- COLEGIO SAN JOSE
-- ACTUALIZACIÓN v3
--
-- - Teléfono de los usuarios (especialmente técnicos), para
--   poder contactarlos por WhatsApp cuando llega un ticket.
--
-- Ejecutar UNA VEZ en phpMyAdmin sobre la base "tecnica_sanjo",
-- después de actualizacion_v2.sql.
-- ============================================================

USE tecnica_sanjo;


SET @existe_telefono := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'tecnica_sanjo'
    AND TABLE_NAME = 'usuarios'
    AND COLUMN_NAME = 'telefono'
);

SET @sql_alter_telefono := IF(
    @existe_telefono = 0,
    "ALTER TABLE usuarios
        ADD COLUMN telefono VARCHAR(30) NULL AFTER correo",
    'SELECT 1'
);

PREPARE stmt_alter_telefono FROM @sql_alter_telefono;
EXECUTE stmt_alter_telefono;
DEALLOCATE PREPARE stmt_alter_telefono;


-- ============================================================
-- FIN ACTUALIZACIÓN v3
-- ============================================================
