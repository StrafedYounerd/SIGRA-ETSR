# RESUMEN DE CAMBIOS — SIGRA-ETSR v1.0 → v1.1
*Verificado línea por línea contra el código real (no contra lo que se pidió implementar).*

## Nueva funcionalidad: Switches y TVs/Proyectores en el mapa de sala
Antes el sistema solo modelaba PCs. Ahora `pcs.tipo_equipo` acepta `'PC' | 'Switch' | 'TV' | 'Proyector'`
(columna nueva en `bd/D-03_estructura.sql`, `DEFAULT 'PC'` para no romper datos existentes).

- **`public/admin/salas.php`**: alta/edición de sala con checkboxes "Incluir Switch de Red" /
  "Incluir TV / Proyector" (alta automática de un equipo de ese tipo, máx. 1 de cada uno por sala);
  alta/edición/baja de equipos individuales con validación de tipo.
- **`public/docente/mapa-sala.php`**: Switches y TVs se muestran en bloques aparte, debajo del mapa en U.
- **`public/docente/reportar-falla.php`** + **`js/mapa.js`**: tipos de falla específicos para
  Switch/TV ("Sin alimentación", "Roto / Daño físico", etc.) distintos de los de PC; el modal de
  reporte ahora se abre por delegación de eventos global (antes: un listener por celda).
- **`css/mapa.css`**: clase común `.equipo` + modificadores `.pc` / `.switch` / `.tv` en vez de estilos
  sueltos por PC.

## Nuevos módulos de administración
- **`public/admin/estadisticas.php`**: estadísticas con Chart.js (frecuencia de fallas por tipo/sala,
  tiempos de reparación, evolutivo semanal, probabilidad empírica por PC/sala), con selector de período.
- **`public/admin/historial.php`**: historial completo de tickets con filtros (sala, PC, estado, rango
  de fechas), solo para admin.
- **`public/admin/usuarios.php`**: se agregó edición y baja definitiva de usuarios (con reglas: no se
  puede borrar el último admin activo ni un usuario con tickets asociados).

## Otros cambios
- **`includes/auth.php`**: timeout de sesión por inactividad (15 min).
- **`config/db.php`**: sin cambios funcionales tras esta revisión (se había introducido un fallback de
  conexión incorrecto — host/usuario/contraseña que no coinciden con `docker-compose.yml` — y se revirtió).

## Corregido en esta revisión (auditoría v1.0 vs v1.1, 2026-08-20)
- `config/db.php`: fallback de conexión apuntaba a `localhost`/`root`/sin contraseña, incompatible con
  el `docker-compose.yml` real → revertido a los valores correctos.
- `public/admin/salas.php`: el formulario "Agregar PC a la sala" no enviaba `tipo_equipo`, por lo que
  el alta fallaba siempre → agregado el campo oculto.
- `public/admin/salas.php`: inyección SQL en el endpoint AJAX `get_equipos_count` (interpolación directa
  de `$_GET['id_sala']`) → pasado a sentencia preparada.
- `public/admin/salas.php`: los checkboxes de Switch/TV al crear una sala no tenían efecto en el backend
  → implementado.
- `includes/auth.php`: la función `calcularRutaLogin()` asumía un segmento `"public/"` en la URL que
  nunca aparece en el servidor real (Apache sirve `public/` como raíz) → simplificado a ruta absoluta.
- `public/index.php` / `public/login.php`: redirecciones que habían pasado de ruta absoluta a relativa
  → revertidas, por consistencia con la configuración real del servidor.
- `public/admin/estadisticas.php` y `css/mapa.css`: estilos con sombras, bordes redondeados y paleta
  azul que contradecían el criterio "sin sombras, sin bordes redondeados" del resto del sistema
  (`css/estilos.css`) → homologados.
- Eliminadas las carpetas `css/` y `js/` de la raíz del repo: eran copias exactas de `public/css/` y
  `public/js/` que nunca se sirven (Apache tiene `public/` como DocumentRoot), y el symlink roto `SIGRA/`.
