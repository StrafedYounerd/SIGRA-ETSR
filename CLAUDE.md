# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

SIGRA-ETSR: plain PHP 8.2 + MariaDB app (no framework, no Composer, no build step, no test suite) for classroom equipment fault reporting. Docentes report faults on a room's PCs/switch/TV via an SVG map, técnicos work the tickets, admins manage users/rooms/stats. UI text and code comments are in Spanish — match that.

## Running

Everything runs through Docker:

```
cp .env.example .env        # fill in MYSQL_* values
docker compose up -d --build
```

- App: http://localhost:8080 (phpMyAdmin on 127.0.0.1:8081)
- The whole repo is bind-mounted at `/var/www/html`; Apache's DocumentRoot is rewritten to `/var/www/html/public` (see `Dockerfile`). Edits are live, no rebuild needed.
- Schema + seed data: `bd/D-03_estructura.sql`, then `bd/migracion_fotos.sql` (adds the `fotos` table). Load manually via phpMyAdmin or `docker exec -i sigra-db mariadb -u... -p... sigra_etsr < bd/file.sql`.
- The `SIGRA` symlink in the repo root points to `/var/www/html` (local-machine artifact, untracked).
- `config/db.php` and `.env` are git-ignored. `db.php` reads `MYSQL_HOST/DATABASE/USER/PASSWORD` via `getenv()` (host defaults to `sigra-db`) and exposes both `$conn` and `$conexion` (same `mysqli` object) — note it currently has a literal password as the `getenv` fallback; don't commit it.

## Architecture

**DocumentRoot is `public/`, so all URLs/redirects are absolute from there** (`/login.php`, `/docente/mapa-sala.php`). `includes/` and `config/` live outside `public/`; pages reach them with relative `require '../../includes/...'`. Static assets: `css/` and `js/` sit in the repo root and are exposed through the symlinks `public/css -> ../css` and `public/js -> ../js` (tracked in git, same as the server); `public/img/` and `public/uploads/fotos/` are real dirs. Reference all of them with absolute URLs (`/css/theme.css`) (uploads dir must be non-executable).

**Page-per-action, server-rendered.** No router or controllers: each file in `public/{docente,tecnico,admin}/` is both handler and view. Typical flow: `require auth.php` + `config/db.php` → `requerirRol([...])` → process POST with prepared `mysqli` statements → Post-Redirect-Get with `?tipo=...&msg=...` (see `tecnico/actualizar-ticket.php`) → render via `ui_head()` / `ui_foot()`.

**Auth (`includes/auth.php`)**: session-based. `verificarSesion()` (redirect to login, 10-minute inactivity timeout via `$_SESSION['last_activity']`) and `requerirRol(['tecnico','admin'])` must be called at the top of every protected page — role enforcement is only in code, so new pages must call it. Session keys: `id_usuario`, `rol`, `nombre`. Roles: `docente`, `tecnico`, `admin`. Passwords are SHA-256 hashes (per the project's RNF-02), not `password_hash`.

**Shared UI (`includes/ui.php`)**: `ui_head([titulo, activo, datos, vivo])` / `ui_foot()` render the sidebar rail and context strip; the per-role nav menu is defined inside `ui_head`. Adding a page to the nav means editing that array (the third element lists extra URLs that keep the item highlighted). `login.php` uses `css/login.css`; internal views use `css/theme.css` + `estilos.css`/`mapa.css`.

**Room map (`docente/mapa-sala.php` + `js/mapa.js`)**: PHP prints room/equipment data as JSON in `#datos-sala`; `js/mapa.js` draws the SVG plan and the report drawer. **The fault-type catalogs (`FALLAS_PC`, `FALLAS_SWITCH_TV`, details-required types) in `mapa.js` must match `$tipos_validos` in `docente/reportar-falla.php` exactly** — the server is the real validator; the JS only drives the UI.

**Photos (`includes/fotos.php`)**: `validar_y_guardar_foto()` is the single upload path (used by `reportar-falla.php` for optional fault photos and `guardar-foto-control.php` for control photos). It validates real type with `finfo` + `getimagesize`, 5 MB cap, random filenames. Stored in `public/uploads/fotos/`, tracked in `fotos` table (`tipo` = `falla`|`control`).

**Data model (`bd/D-03_estructura.sql`)**: `usuarios`, `salas`, `pcs` (equipment; `estado_actual` = funcional/falla/sin_eval), `tickets` (`estado` = pendiente/en_reparacion/solucionado), `historial_tickets` (audit log of state changes — technician updates should write to it), `sesiones_docente` (room entry log per docente+grupo). FKs use `ON DELETE RESTRICT`.

**Admin stats (`admin/estadisticas.php`)**: large page that runs several aggregate queries and renders results as tables plus CSS-only bar charts via the local `graf_barras()` helper (no Chart.js/CDN: the intranet has no internet).
