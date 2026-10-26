-- ============================================================
-- SIGRA-ETSR · Migración: fotos.id_sala
-- ============================================================
-- Las fotos de control de la SALA COMPLETA se guardaban con id_pc NULL y
-- no había forma de saber a qué sala pertenecían: se mostraban en todas.
-- Esta columna asocia cada foto a su sala. Aditiva e idempotente.
-- Las fotos de control de sala completa que ya existían (id_pc NULL) se atribuyen
-- a la sala de la sesión del docente en ese momento (ver más abajo).
--
-- El script ya trae la columna id_sala en la tabla fotos; este archivo
-- cubre las bases donde la tabla se creó antes de que existiera esa
-- columna, y agrega el índice y la FK que le corresponden.

-- Selecciona la base sobre la que correr la migración (mismo criterio
-- que en migracion_fotos.sql: la base ya seleccionada, si la hay, gana).
SET @sigra_db = IFNULL(@sigra_db, IFNULL(DATABASE(), 'sigra_etsr'));
SET @sql = CONCAT('USE `', @sigra_db, '`');
PREPARE stmt_usar_db FROM @sql;
EXECUTE stmt_usar_db;
DEALLOCATE PREPARE stmt_usar_db;

ALTER TABLE fotos
  ADD COLUMN IF NOT EXISTS id_sala INT(11) NULL AFTER id_pc;

-- Índice compuesto (id_sala, tipo, fecha_subida): es el que usa la
-- consulta del cajón para traer las fotos de control de una sala.
CREATE INDEX IF NOT EXISTS idx_fotos_sala ON fotos (id_sala, tipo, fecha_subida);

ALTER TABLE fotos
  ADD CONSTRAINT fk_foto_sala
    FOREIGN KEY IF NOT EXISTS (id_sala) REFERENCES salas(id_sala)
    ON DELETE CASCADE ON UPDATE CASCADE;

UPDATE fotos f
  JOIN pcs p ON f.id_pc = p.id_pc
   SET f.id_sala = p.id_sala
 WHERE f.id_sala IS NULL;

-- Fotos de la sala completa (sin equipo): se toma la sala de la última sesión del
-- docente anterior a la foto. Si no hay sesión previa, queda sin sala.
UPDATE fotos f
   SET f.id_sala = (SELECT s.id_sala FROM sesiones_docente s
                     WHERE s.id_usuario = f.id_docente AND s.fecha_entrada <= f.fecha_subida
                     ORDER BY s.fecha_entrada DESC LIMIT 1)
 WHERE f.id_sala IS NULL AND f.id_pc IS NULL;
