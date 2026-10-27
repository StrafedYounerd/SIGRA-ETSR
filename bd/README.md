# SIGRA-ETSR · Base de datos (`bd/`)

Scripts de instalación y estructura de la base de datos de SIGRA-ETSR.

La estructura que generan estos scripts **coincide exactamente** con la que
tiene la base del servidor (verificada con `mysqldump --no-data`): mismas
tablas, mismas columnas en el mismo orden, mismos tipos, mismos ENUM, mismos
índices y mismas claves foráneas con sus reglas `ON DELETE` / `ON UPDATE`.

---

## Orden de instalación

Los tres archivos se corren **en este orden**. El segundo y el tercero
dependen de las tablas que crea el primero.

```bash
# 1) estructura base (usuarios, salas, pcs, tickets, historial, sesiones)
mysql -u root -p < bd/D-03_estructura.sql

# 2) tabla de fotos (fallas y control)
mysql -u root -p < bd/migracion_fotos.sql

# 3) fotos.id_sala + sus índices y FK
mysql -u root -p < bd/migracion_fotos_sala.sql
```

Cada script selecciona la base por sí solo (no hace falta pasar `-D`).

### Paso previo: configuración de la base

Antes de levantar la app, copiar la plantilla y completar los datos:

```bash
cp config/db.example.php config/db.php
# editar config/db.php y reemplazar los COMPLETAR_* (o definir las
# variables MYSQL_* en .env / docker-compose.yml)
```

`config/db.php` está ignorado por git; `config/db.example.php` es la
plantilla versionada. Con docker-compose y el `.env` ya cargado,
alcanza con copiar el archivo sin editarlo.

### Instalar en otra base (por ejemplo una de pruebas)

Los tres scripts leen la variable de sesión `@sigra_db`. Si está definida,
trabajan sobre esa base; si no, usan la base ya seleccionada por el cliente
o, en su defecto, `sigra_etsr`.

```bash
# base nueva, se crea si no existe
mysql -u root -p --init-command="SET @sigra_db='sigra_prueba'" < bd/D-03_estructura.sql
mysql -u root -p --init-command="SET @sigra_db='sigra_prueba'" < bd/migracion_fotos.sql
mysql -u root -p --init-command="SET @sigra_db='sigra_prueba'" < bd/migracion_fotos_sala.sql

# o sobre una base ya seleccionada
mysql -u root -p -D sigra_prueba < bd/D-03_estructura.sql
```

---

## Datos de ejemplo y contraseñas

`D-03_estructura.sql` deja cargada la **Sala 210** con sus 13 PCs en la
disposición física real (fila 1: 5 PCs, fila 2: 3 PCs, fila 3: 5 PCs), más
un switch y un TV de la sala, y **tres usuarios de ejemplo**:

| usuario  | rol      | contraseña de ejemplo |
|----------|----------|-----------------------|
| `admin`  | admin    | `ejemplo_admin_123`   |
| `soporte`| tecnico  | `ejemplo_soporte_123` |
| `docente` | docente  | `ejemplo_docente_123` |

> **Solo para instalación inicial: cambiarlas.**
> Estas contraseñas son públicas: no uses estas cuentas en un sistema real
> y cambialas siempre antes de usarlo.

**Contraseñas**: la app hoy usa **bcrypt** (`includes/clave.php`, con
`password_hash()` / `password_verify()`). Los hashes SHA-256 que deja el
seed son **legados**: se aceptan una sola vez y, tras el primer inicio
de sesión correcto de cada cuenta, se reescriben automáticamente a
bcrypt en la tabla `usuarios`. A partir de ahí esa cuenta ya solo
funciona con el código nuevo.

Las etiquetas de las PCs (`PC-7296`, `PC-7288`, …) son las de los equipos de
la sala piloto; el estado inicial de todas es `sin_eval` (todas las PCs
nacen en `sin_eval`, `sin_eval` es el estado por defecto de la tabla).

---

## Notas de mantenimiento

- **Colonna `tipo_equipo` duplicada**: estaba declarada dos veces en
  `pcs` dentro de `D-03_estructura.sql` y hacía fallar la instalación con
  `ERROR 1060: Duplicate column name 'tipo_equipo'`. Ya está corregido: la
  columna aparece una sola vez.

- **Orden de las FKs en `migracion_fotos.sql`**: las claves foráneas se
  agregan con `ALTER TABLE` **después** de crear los índices, a propósito.
  Si se declararan dentro del `CREATE TABLE`, MariaDB crea un índice
  implícito extra por cada FK (por ejemplo `fk_foto_pc` sobre `id_pc`) que
  no existe en la base real del servidor.

- **`migracion_fotos.sql` es aditiva**: no toca ninguna tabla existente.
  Usa `IF NOT EXISTS` en tabla, columnas, índices y constraints, así que
  se puede correr más de una vez sin romper nada.

- **`migracion_fotos_sala.sql`** agrega `fotos.id_sala` (para que la foto de
  control de la sala completa se pueda atribuir a una sala) y su índice
  `idx_fotos_sala (id_sala, tipo, fecha_subida)`. Es idempotente.

- **Contraseñas**: la app usa bcrypt (`includes/clave.php`:
  `password_hash()` / `password_verify()`). Los hashes SHA-256 del seed
  son legados: se aceptan una sola vez y se reescriben a bcrypt al
  primer inicio de sesión de cada cuenta (ver arriba).

- **Collation**: la base real usa `utf8mb4_general_ci`. El script la crea
  con esa misma collation (antes decía `utf8mb4_spanish_ci`).

---

## Comprobación rápida

```bash
# tablas esperadas: fotos, historial_tickets, pcs, salas, sesiones_docente, tickets, usuarios
mysql -u root -p -D sigra_etsr -e "SHOW TABLES;"

# estructura de una tabla
mysql -u root -p -D sigra_etsr -e "SHOW CREATE TABLE pcs\G"
```
