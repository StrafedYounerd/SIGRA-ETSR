# SIGRA-ETSR · Base de datos (`bd/`)

Scripts de instalación y estructura de la base de datos de SIGRA-ETSR.

La estructura que generan estos scripts **coincide exactamente** con la que
tiene la base del servidor (verificada con `mysqldump --no-data`): mismas
tablas, mismas columnas en el mismo orden, mismos tipos, mismos ENUM, mismos
índices y mismas claves foráneas con sus reglas `ON DELETE` / `ON UPDATE`.

---

## Orden de instalación

Los tres archivos se corren **en este orden**, **con el usuario `root`**
(el usuario de la app no tiene permisos para crear la base ni las tablas).
El segundo y el tercero dependen de las tablas que crea el primero.

Si la base corre en Docker (lo habitual), el cliente es `mariadb` y los
comandos completos son:

```bash
# 1) estructura base (usuarios, salas, pcs, tickets, historial, sesiones)
docker exec -i sigra-db mariadb -uroot -p < bd/D-03_estructura.sql

# 2) tabla de fotos (fallas y control)
docker exec -i sigra-db mariadb -uroot -p < bd/migracion_fotos.sql

# 3) fotos.id_sala + sus índices y FK
docker exec -i sigra-db mariadb -uroot -p < bd/migracion_fotos_sala.sql
```

Si la base es local (no Docker), los mismos tres scripts con el cliente
`mysql`:

```bash
mysql -u root -p < bd/D-03_estructura.sql
mysql -u root -p < bd/migracion_fotos.sql
mysql -u root -p < bd/migracion_fotos_sala.sql
```

Cada script selecciona la base por sí solo (no hace falta pasar `-D`).

### Paso previo: configuración de la base

Antes de levantar la app, copiar la plantilla y completar los datos:

```bash
cp config/db.example.php config/db.php
```

`config/db.php` está ignorado por git; `config/db.example.php` es la
plantilla versionada. **Nunca subas `config/db.php` al repositorio**:
contiene la contraseña de la base.

Los valores salen de las variables de entorno `MYSQL_*` (que define el
`.env` y que `docker-compose.yml` le pasa tanto al servicio de la base
como al servicio web). Con Docker, **alcanza con copiar el archivo sin
editarlo**: `docker-compose.yml` define `MYSQL_HOST: db` para el web, y
`config/db.php` lo lee con `getenv()`.

Sin Docker, o si las variables no están definidas, hay que editar
`config/db.php` a mano y reemplazar los `COMPLETAR_*` por los valores
reales.

### Permisos de la carpeta de subidas

Las fotos se guardan en `public/uploads/fotos/`, que **no está versionada**
(solo se versiona su `index.html`). En una instalación nueva hay que
dejarla escribible por el servidor web: Apache corre como `www-data`
dentro del contenedor y sin esto la app responde *"No se pudo guardar la
foto en el servidor"*.

El comando exacto (dueño = el usuario de la instalación, grupo `www-data`,
modo `2775` — el `2` activa setgid para que los archivos nuevos hereden
el grupo):

```bash
sudo chown -R <usuario>:www-data public/uploads/fotos
sudo chmod 2775 public/uploads/fotos
```

> `chmod 2775` **necesita `sudo`**: sólo root puede activar el bit
> setgid en una carpeta de un grupo al que el usuario no pertenece. El
> dueño sigue siendo `<usuario>`, no root.

O, lo mismo, con el script que viene en el repo (solo toca esa carpeta y
pide confirmación antes de aplicar):

```bash
sudo scripts/preparar-instalacion.sh
```

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
docker exec -i sigra-db mariadb -uroot -p -D sigra_etsr -e "SHOW TABLES;"

# estructura de una tabla
docker exec -i sigra-db mariadb -uroot -p -D sigra_etsr -e "SHOW CREATE TABLE pcs\G"
```
