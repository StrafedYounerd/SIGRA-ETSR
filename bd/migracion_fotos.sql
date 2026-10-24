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

CREATE TABLE IF NOT EXISTS fotos (
  id_foto      INT(11) NOT NULL AUTO_INCREMENT,
  id_pc        INT(11) NULL,
  id_ticket    INT(11) NULL,
  id_docente   INT(11) NOT NULL,
  tipo         ENUM('falla', 'control') NOT NULL,
  archivo      VARCHAR(64) NOT NULL,
  fecha_subida DATETIME NOT NULL,
  PRIMARY KEY (id_foto),
  CONSTRAINT fk_foto_pc
    FOREIGN KEY (id_pc) REFERENCES pcs(id_pc)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_foto_ticket
    FOREIGN KEY (id_ticket) REFERENCES tickets(id_ticket)
    ON DELETE CASCADE ON UPDATE CASCADE,
-- Nota: la regla "id_pc NULL solo con tipo 'control'" se garantiza en el
-- código (reportar-falla.php siempre inserta id_pc; guardar-foto-control.php
-- es el único que permite NULL), porque MariaDB 10.11 no acepta este CHECK
-- con referencia a otra columna de la misma fila.
  CONSTRAINT fk_foto_docente
    FOREIGN KEY (id_docente) REFERENCES usuarios(id_usuario)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Índices para los listados del cajón y del tablero.
CREATE INDEX idx_fotos_pc    ON fotos (id_pc, fecha_subida);
CREATE INDEX idx_fotos_ticket ON fotos (id_ticket);
