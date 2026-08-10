-- ============================================================
-- COLEGIO SAN JOSE
-- ACTUALIZACIÓN v5
--
-- - Foto de cada repuesto (catálogo administrado por el
--   administrador en admin/repuestos.php).
--
-- Ejecutar UNA VEZ en phpMyAdmin sobre la base "tecnica_sanjo",
-- después de actualizacion_v4.sql.
-- ============================================================

USE tecnica_sanjo;


SET @existe_foto := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'tecnica_sanjo'
    AND TABLE_NAME = 'repuestos'
    AND COLUMN_NAME = 'foto'
);

SET @sql_alter_foto := IF(
    @existe_foto = 0,
    "ALTER TABLE repuestos
        ADD COLUMN foto VARCHAR(255) NULL AFTER descripcion",
    'SELECT 1'
);

PREPARE stmt_alter_foto FROM @sql_alter_foto;
EXECUTE stmt_alter_foto;
DEALLOCATE PREPARE stmt_alter_foto;


-- ============================================================
-- FIN ACTUALIZACIÓN v5
-- ============================================================
