-- ============================================================
-- SIGRA-ETSR · Componente B (Sistema Web)
-- Script de creación de base de datos y datos de prueba
-- D-03 · Fase 4 - Codificación (Green Path)
-- ============================================================

-- Crea la base de datos si no existe, y la configura con codificación
-- UTF-8 para soportar tildes y caracteres especiales del español sin errores.
CREATE DATABASE IF NOT EXISTS sigra_etsr
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_spanish_ci;

-- Selecciona la base de datos recién creada para que todas las
-- sentencias siguientes se ejecuten dentro de ella.
USE sigra_etsr;


-- ============================================================
-- TABLA: usuarios
-- Almacena a los 3 tipos de actores del sistema: docente, técnico, admin.
-- ============================================================
CREATE TABLE usuarios (
  id_usuario   INT(11) NOT NULL AUTO_INCREMENT,
  nombre       VARCHAR(100) NOT NULL,
  usuario      VARCHAR(50) NOT NULL,
  contraseña   VARCHAR(255) NOT NULL,
  rol          ENUM('docente', 'tecnico', 'admin') NOT NULL,
  activo       TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id_usuario),
  UNIQUE KEY uk_usuario (usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Explicación línea por línea:
-- id_usuario   INT AUTO_INCREMENT: clave primaria numérica que MySQL genera sola.
-- nombre       VARCHAR(100) NOT NULL: nombre completo, obligatorio.
-- usuario      VARCHAR(50) NOT NULL: nombre de login, obligatorio.
-- contraseña   VARCHAR(255) NOT NULL: guarda el HASH, nunca la contraseña real.
-- rol          ENUM(...): solo permite 3 valores exactos, ningún otro texto entra.
-- activo       TINYINT(1) DEFAULT 1: por defecto todo usuario nuevo nace activo.
-- PRIMARY KEY: define id_usuario como clave primaria de la tabla.
-- UNIQUE KEY uk_usuario: impide que dos personas tengan el mismo nombre de login
--   (esto es lo que hace cumplir el RF-08 y evita el error "usuario ya existe").


-- ============================================================
-- TABLA: salas
-- Catálogo de salas/laboratorios. Diseñada para soportar más de una
-- sala en el futuro sin modificar el código (escalabilidad, R-05/R-07).
-- ============================================================
CREATE TABLE salas (
  id_sala      INT(11) NOT NULL AUTO_INCREMENT,
  nombre_sala  VARCHAR(80) NOT NULL,
  descripcion  TEXT NULL,
  activa       TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id_sala)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- descripcion TEXT NULL: campo opcional, puede quedar vacío sin romper nada.
-- activa: permite "desactivar" una sala sin borrarla (útil si se da de baja
--   un laboratorio pero se quiere conservar su historial de tickets).


-- ============================================================
-- TABLA: pcs
-- Cada PC física pertenece a una sala (relación 1:N salas → pcs).
-- ============================================================
CREATE TABLE pcs (
  id_pc          INT(11) NOT NULL AUTO_INCREMENT,
  id_sala        INT(11) NOT NULL,
  etiqueta       VARCHAR(20) NOT NULL,
  fila           INT(11) NOT NULL,
  posicion       INT(11) NOT NULL,
  tipo_equipo    ENUM('PC', 'Switch', 'TV', 'Proyector') NOT NULL DEFAULT 'PC',
  estado_actual  ENUM('funcional', 'falla', 'sin_eval') NOT NULL DEFAULT 'sin_eval',
  PRIMARY KEY (id_pc),
  CONSTRAINT fk_pc_sala
    FOREIGN KEY (id_sala) REFERENCES salas(id_sala)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- id_sala INT NOT NULL: toda PC tiene que pertenecer a una sala, sin excepción.
-- etiqueta VARCHAR(20): el identificador visible, ej. "PC-210-01".
-- fila / posicion: coordenadas lógicas para dibujar la PC en su lugar
--   exacto dentro del mapa en forma de U (fila 1, 2 o 3 + su orden).
-- estado_actual DEFAULT 'sin_eval': una PC recién cargada no tiene estado
--   conocido todavía, hasta que se reporte una falla o se confirme que funciona.
-- CONSTRAINT fk_pc_sala: define la clave foránea hacia salas.id_sala.
-- ON DELETE RESTRICT: si alguien intenta borrar una sala que todavía tiene
--   PCs cargadas, MySQL RECHAZA el borrado. Esto refuerza en la base de
--   datos lo que ya pedíamos a nivel de aplicación (no perder PCs huérfanas).
-- ON UPDATE CASCADE: si el id_sala cambiara (poco común, pero por seguridad),
--   se actualiza automáticamente en todas las PCs relacionadas.


-- ============================================================
-- TABLA: tickets
-- El corazón del sistema: cada falla reportada por un docente.
-- ============================================================
CREATE TABLE tickets (
  id_ticket      INT(11) NOT NULL AUTO_INCREMENT,
  id_pc          INT(11) NOT NULL,
  id_docente     INT(11) NOT NULL,
  tipo_falla     VARCHAR(50) NOT NULL,
  detalle_texto  TEXT NULL,
  estado         ENUM('pendiente', 'en_reparacion', 'solucionado') NOT NULL DEFAULT 'pendiente',
  fecha_reporte  DATETIME NOT NULL,
  fecha_cierre   DATETIME NULL,
  PRIMARY KEY (id_ticket),
  CONSTRAINT fk_ticket_pc
    FOREIGN KEY (id_pc) REFERENCES pcs(id_pc)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  CONSTRAINT fk_ticket_docente
    FOREIGN KEY (id_docente) REFERENCES usuarios(id_usuario)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- detalle_texto TEXT NULL: solo se completa cuando tipo_falla es
--   "Falta de software" u "Otro" (esa validación la hace el código PHP,
--   no la base de datos, porque depende de una condición lógica).
-- estado DEFAULT 'pendiente': todo ticket nuevo nace en este estado,
--   coincide con el pseudocódigo de ReportarFalla().
-- fecha_reporte DATETIME NOT NULL: obligatorio, se llena con NOW() desde PHP.
-- fecha_cierre DATETIME NULL: queda vacío hasta que el ticket se soluciona.
-- fk_ticket_pc / fk_ticket_docente: dos claves foráneas, porque un ticket
--   conecta dos entidades distintas: la PC que falló y el docente que reportó.
-- ON DELETE RESTRICT en ambas: protege la integridad — no se puede borrar
--   una PC ni un usuario si tienen tickets asociados, para no perder el
--   historial (esto es justamente lo que pedía la regla de negocio original
--   de "no eliminar salas con tickets activos", aplicado también acá).


-- ============================================================
-- TABLA: historial_tickets
-- Registra cada cambio de estado de un ticket (trazabilidad completa).
-- ============================================================
CREATE TABLE historial_tickets (
  id_historial     INT(11) NOT NULL AUTO_INCREMENT,
  id_ticket        INT(11) NOT NULL,
  id_usuario       INT(11) NOT NULL,
  estado_anterior  VARCHAR(30) NOT NULL,
  estado_nuevo     VARCHAR(30) NOT NULL,
  observacion      TEXT NULL,
  fecha_cambio     DATETIME NOT NULL,
  PRIMARY KEY (id_historial),
  CONSTRAINT fk_historial_ticket
    FOREIGN KEY (id_ticket) REFERENCES tickets(id_ticket)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_historial_usuario
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- estado_anterior / estado_nuevo VARCHAR(30): se guardan como texto plano
--   (no ENUM) porque acá es solo un registro histórico, no necesita
--   restricción de valores tan estricta como la tabla tickets.
-- fk_historial_ticket ON DELETE CASCADE: si alguna vez se borra un ticket
--   (poco común, pero por consistencia), se borra también su historial,
--   porque el historial no tiene sentido sin el ticket que lo originó.
-- fk_historial_usuario ON DELETE RESTRICT: no se puede borrar un técnico
--   que ya hizo cambios de estado, para no perder de vista quién hizo qué.


-- ============================================================
-- TABLA: sesiones_docente
-- Registra cada vez que un docente entra a una sala con su grupo.
-- ============================================================
CREATE TABLE sesiones_docente (
  id_sesion      INT(11) NOT NULL AUTO_INCREMENT,
  id_usuario     INT(11) NOT NULL,
  id_sala        INT(11) NOT NULL,
  grupo          VARCHAR(50) NOT NULL,
  fecha_entrada  DATETIME NOT NULL,
  PRIMARY KEY (id_sesion),
  CONSTRAINT fk_sesion_usuario
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  CONSTRAINT fk_sesion_sala
    FOREIGN KEY (id_sala) REFERENCES salas(id_sala)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- grupo VARCHAR(50): texto libre, ej. "3D TI", para identificar la clase.
-- Esta tabla no se usa todavía en el pseudocódigo de las 3 fases anteriores
-- (Login, Reportar falla, Actualizar ticket), pero queda lista en el MER
-- para cuando se implemente CU-02 (Seleccionar sala) con su registro completo.


-- ============================================================
-- DATOS DE PRUEBA
-- Contraseñas hasheadas con SHA-256 (coincide con RNF-02 de la ESRE).
-- admin    -> contraseña real: admin123
-- soporte  -> contraseña real: soporte123
-- docente  -> contraseña real: docente123
-- ============================================================

INSERT INTO usuarios (nombre, usuario, contraseña, rol, activo) VALUES
('Administrador del Sistema', 'admin',
 '240be518fabd2724ddb6f04eeb1da5967448d7e831c08c8fa822809f74c720a9',
 'admin', 1),
('Soporte ETSR', 'soporte',
 '8f51a8428ee0230c0521b3279c574a774ae0064a902f6deecc396315eec96577',
 'tecnico', 1),
('Docente de Prueba', 'docente',
 '9228105f4ea40ae4b70fde55ee2caf4495c1f188333c41ef566417ab04819f56',
 'docente', 1);

-- Cada fila corresponde a un actor del sistema (RN: separación de roles).
-- Los hashes fueron generados con SHA-256 fuera de la base de datos
-- (en PHP esto se hace con hash('sha256', $contraseña) al crear el usuario).


-- Carga la Sala 210, única sala piloto definida hasta el momento.
INSERT INTO salas (nombre_sala, descripcion, activa) VALUES
('Sala 210', 'Sala piloto del proyecto SIGRA-ETSR. 13 PCs en disposición de U invertida.', 1);

-- Carga las PCs de la Sala 210 respetando la disposición física real:
-- Fila 1: 5 PCs (posiciones 1 a 5)
-- Fila 2: 3 PCs (posiciones 1 a 3)
-- Fila 3: 5 PCs (posiciones 1 a 5)
-- Todas nacen en estado 'sin_eval' porque todavía no se evaluó su estado real.

INSERT INTO pcs (id_sala, etiqueta, fila, posicion, estado_actual) VALUES
(1, 'PC-7296', 1, 1, 'sin_eval'),
(1, 'PC-7288', 1, 2, 'sin_eval'),
(1, 'PC-7291', 1, 3, 'sin_eval'),
(1, 'PC-7283', 1, 4, 'sin_eval'),
(1, 'PC-7286', 1, 5, 'sin_eval'),
(1, 'PC-7287', 2, 1, 'sin_eval'),
(1, 'PC-7292', 2, 2, 'sin_eval'),
(1, 'PC-7284', 2, 3, 'sin_eval'),
(1, 'PC-7290', 3, 1, 'sin_eval'),
(1, 'PC-7282', 3, 2, 'sin_eval'),
(1, 'PC-7285', 3, 3, 'sin_eval'),
(1, 'PC-7289', 3, 4, 'sin_eval'),
(1, 'PC-7293', 3, 5, 'sin_eval');

-- Nota: id_sala = 1 asume que la Sala 210 fue la primera (y única) fila
-- insertada en la tabla salas, por eso su AUTO_INCREMENT generó id_sala = 1.

-- ============================================================
-- FIN DEL SCRIPT
-- ============================================================
