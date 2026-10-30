# Informe de pruebas E2E — SIGRA-ETSR

- **Fecha:** 2026-10-30 03:54:03
- **Aplicación bajo prueba:** http://localhost:8080
- **Casos ejecutados:** 8
- **Resultado general:** ✅ todo OK

## Datos de prueba

Sala E2E: **E2E-Sala Prueba** (id_sala=982)

| Etiqueta PC | id_pc | Fila | Posición |
| --- | --- | --- | --- |
| E2E-PC-1 | 428 | 1 | 1 |
| E2E-PC-2 | 429 | 1 | 2 |
| E2E-PC-3 | 430 | 1 | 3 |

| Rol | Usuario | id_usuario |
| --- | --- | --- |
| docente | e2e_docente | 94 |
| tecnico | e2e_tecnico | 95 |
| admin | e2e_admin | 96 |

## Resultado por caso

| Caso | Resultado | Duración | Detalle |
| --- | --- | --- | --- |
| E2E-1 · el login correcto redirige al panel de cada rol | ✅ OK | 3.9 s | — |
| E2E-2 · una contraseña incorrecta no deja entrar y muestra el error | ✅ OK | 1.2 s | — |
| E2E-3 · sin sesión, las páginas protegidas redirigen al login | ✅ OK | 0.4 s | — |
| E2E-4 · el docente elige la sala y llega al mapa de la sala | ✅ OK | 2.2 s | — |
| E2E-5 · el docente reporta una falla y queda el ticket en la base | ✅ OK | 2.6 s | — |
| E2E-6 · el servidor rechaza el detalle faltante y el reporte duplicado | ✅ OK | 2.8 s | — |
| E2E-7 · el técnico lleva el ticket de pendiente a solucionado | ✅ OK | 6.1 s | — |
| E2E-8 · cada rol sólo accede a las páginas de su rol | ✅ OK | 3.8 s | — |
## Ids creados por la suite

Estos registros se crean durante la corrida (semilla + efectos de los casos) y se borran al terminar.

| Tabla | id | Etiqueta | Caso |
| --- | --- | --- | --- |
| usuarios | 94 | e2e_docente | E2E-semilla |
| usuarios | 95 | e2e_tecnico | E2E-semilla |
| usuarios | 96 | e2e_admin | E2E-semilla |
| salas | 982 | E2E-Sala Prueba | E2E-semilla |
| pcs | 428 | E2E-PC-1 | E2E-semilla |
| pcs | 429 | E2E-PC-2 | E2E-semilla |
| pcs | 430 | E2E-PC-3 | E2E-semilla |
| tickets | 66 | E2E-PC-1 / No enciende | E2E-5 |
| tickets | 67 | E2E-PC-2 / Falta de software | E2E-6 |
| tickets | 68 | E2E-PC-3 / Sin internet | E2E-7 |
## Ids borrados al terminar

Verificación con `SELECT` de que en la base ya no queda nada de la corrida.

| Tabla | id | Etiqueta | Caso |
| --- | --- | --- | --- |
| tickets | 66 | — | — |
| tickets | 67 | — | — |
| tickets | 68 | — | — |
| sesiones_docente | 130 | — | — |
| sesiones_docente | 131 | — | — |
| sesiones_docente | 132 | — | — |
| sesiones_docente | 133 | — | — |
| pcs | 428 | — | — |
| pcs | 429 | — | — |
| pcs | 430 | — | — |
| salas | 982 | — | — |
| usuarios | 94 | — | — |
| usuarios | 95 | — | — |
| usuarios | 96 | — | — |
## Consultas SELECT de verificación

```sql
-- Estados finales de las PCs E2E (deben volver a "sin_eval")
SELECT id_pc, etiqueta, estado_actual FROM pcs
 WHERE etiqueta LIKE 'E2E-PC-%' ORDER BY id_pc;

-- Tickets creados por la suite (deben ser 0 al terminar)
SELECT id_ticket, id_pc, estado FROM tickets
 WHERE id_pc IN (SELECT id_pc FROM pcs WHERE etiqueta LIKE 'E2E-PC-%')
 ORDER BY id_ticket;

-- Movimientos de historial de los tickets E2E (deben ser 0 al terminar)
SELECT id_historial, id_ticket, estado_anterior, estado_nuevo
 FROM historial_tickets
 WHERE id_ticket IN (SELECT id_ticket FROM tickets WHERE id_pc IN
   (SELECT id_pc FROM pcs WHERE etiqueta LIKE 'E2E-PC-%'));

-- Sesiones de clase de los usuarios E2E (deben ser 0 al terminar)
SELECT id_sesion, id_usuario, id_sala, grupo FROM sesiones_docente
 WHERE id_usuario IN (SELECT id_usuario FROM usuarios WHERE usuario LIKE 'e2e_%');
```
