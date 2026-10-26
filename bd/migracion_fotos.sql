-- ============================================================
-- SIGRA-ETSR · Migración: fotos de fallas y de control
-- ============================================================
-- Agrega almacenamiento de imágenes:
--   * tipo 'falla'   → foto opcional adjunta a un reporte de falla
--   * tipo 'control' → foto de control del estado de un equipo (o de
--     la sala completa), no crea ticket ni cambia estado.
--
-- ADITIVA: no modifica ninguna tabla existente. Las fotos viven en
-- public/uploads/fotos/ con nombre aleatorio; la DB guarda la ruta.
--
-- NOTA sobre el orden: las claves foráneas se agregan con ALTER TABLE
-- DESPUÉS de crear los índices, a propósito. Si se declararan dentro
-- del CREATE TABLE, MariaDB crea un índice implícito extra por cada FK
-- (por ejemplo 'fk_foto_pc' sobre id_pc) que no existe en la base real
-- del servidor. Declarándolas al final y con los índices ya puestos,
-- MariaDB reutiliza los índices existentes y el resultado coincide
-- exactamente con el servidor.

-- Selecciona la base sobre la que correr la migración. Si el script se
-- lanza con la base ya seleccionada (mysql -D mi_base < este.sql) se
-- respeta esa; si no, se usa @sigra_db; y si tampoco está definida, la
-- base por defecto del proyecto.
SET @sigra_db = IFNULL(@sigra_db, IFNULL(DATABASE(), 'sigra_etsr'));
SET @sql = CONCAT('USE `', @sigra_db, '`');
PREPARE stmt_usar_db FROM @sql;
EXECUTE stmt_usar_db;
DEALLOCATE PREPARE stmt_usar_db;

CREATE TABLE IF NOT EXISTS fotos (
  id_foto      INT(11) NOT NULL AUTO_INCREMENT,
  id_pc        INT(11) NULL,
  id_ticket    INT(11) NULL,
  id_docente   INT(11) NOT NULL,
  tipo         ENUM('falla', 'control') NOT NULL,
  archivo      VARCHAR(64) NOT NULL,
  fecha_subida DATETIME NOT NULL,
  PRIMARY KEY (id_foto)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Índices para los listados del cajón y del tablero.
-- idx_fotos_ticket cubre además la FK hacia tickets.
CREATE INDEX IF NOT EXISTS idx_fotos_pc    ON fotos (id_pc, fecha_subida);
CREATE INDEX IF NOT EXISTS idx_fotos_ticket ON fotos (id_ticket);

-- Claves foráneas (las reglas ON DELETE / ON UPDATE son las del servidor).
ALTER TABLE fotos
  ADD CONSTRAINT fk_foto_pc
    FOREIGN KEY (id_pc) REFERENCES pcs(id_pc)
    ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE fotos
  ADD CONSTRAINT fk_foto_ticket
    FOREIGN KEY (id_ticket) REFERENCES tickets(id_ticket)
    ON DELETE CASCADE ON UPDATE CASCADE;

-- Nota: la regla "id_pc NULL solo con tipo 'control'" se garantiza en el
-- código (reportar-falla.php siempre inserta id_pc; guardar-foto-control.php
-- es el único que permite NULL), porque MariaDB 10.11 no acepta este CHECK
-- con referencia a otra columna de la misma fila.
ALTER TABLE fotos
  ADD CONSTRAINT fk_foto_docente
    FOREIGN KEY (id_docente) REFERENCES usuarios(id_usuario)
    ON DELETE RESTRICT ON UPDATE CASCADE;
