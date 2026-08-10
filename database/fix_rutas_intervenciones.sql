-- ============================================================
-- COLEGIO SAN JOSE
-- CORRECCIÓN PUNTUAL
--
-- Las fotos subidas al registrar una intervención (antes de
-- este arreglo) se guardaban en una subcarpeta por solicitud
-- (uploads/intervenciones/<id>/archivo.jpg) y así se guardaba
-- la ruta completa en la base, pero el resto del sistema
-- espera el archivo suelto en uploads/intervenciones/ y solo
-- el nombre del archivo en la base. Por eso esas fotos se ven
-- rotas.
--
-- Este script corrige los registros ya guardados con el
-- formato viejo. Es seguro ejecutarlo más de una vez.
--
-- Ejecutar en phpMyAdmin sobre la base "tecnica_sanjo".
-- ============================================================

USE tecnica_sanjo;

UPDATE intervencion_imagenes
SET archivo = SUBSTRING_INDEX(archivo, '/', -1)
WHERE archivo LIKE 'uploads/intervenciones/%';

-- ============================================================
-- FIN
-- ============================================================
