-- ============================================================
-- COLEGIO SAN JOSE
-- ACTUALIZACIÓN v6
--
-- - API key personal de CallMeBot para poder enviar mensajes
--   de WhatsApp automáticos (sin que la persona tenga que
--   apretar un botón). Es gratuito, pero cada usuario tiene
--   que activarlo una vez desde su propio WhatsApp (ver
--   instrucciones en el perfil / alta de usuario).
--
-- Ejecutar UNA VEZ en phpMyAdmin sobre la base "tecnica_sanjo",
-- después de actualizacion_v5.sql.
-- ============================================================

USE tecnica_sanjo;


SET @existe_apikey := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'tecnica_sanjo'
    AND TABLE_NAME = 'usuarios'
    AND COLUMN_NAME = 'whatsapp_apikey'
);

SET @sql_alter_apikey := IF(
    @existe_apikey = 0,
    "ALTER TABLE usuarios
        ADD COLUMN whatsapp_apikey VARCHAR(20) NULL AFTER telefono",
    'SELECT 1'
);

PREPARE stmt_alter_apikey FROM @sql_alter_apikey;
EXECUTE stmt_alter_apikey;
DEALLOCATE PREPARE stmt_alter_apikey;


-- ============================================================
-- FIN ACTUALIZACIÓN v6
-- ============================================================
