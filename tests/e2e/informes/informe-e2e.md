# Informe de pruebas E2E — SIGRA-ETSR

- **Fecha:** 2026-10-30 05:05:41
- **Aplicación bajo prueba:** http://localhost:8080
- **Casos ejecutados:** 8
- **Resultado general:** ✅ todo OK

## Datos de prueba

Sala E2E: **E2E-Sala Prueba** (id_sala=986)

| Etiqueta PC | id_pc | Fila | Posición |
| --- | --- | --- | --- |
| E2E-PC-1 | 440 | 1 | 1 |
| E2E-PC-2 | 441 | 1 | 2 |
| E2E-PC-3 | 442 | 1 | 3 |

| Rol | Usuario | id_usuario |
| --- | --- | --- |
| docente | e2e_docente | 106 |
| tecnico | e2e_tecnico | 107 |
| admin | e2e_admin | 108 |

## Resultado por caso

| Caso | Resultado | Duración | Detalle |
| --- | --- | --- | --- |
| E2E-1 · el login correcto redirige al panel de cada rol | ✅ OK | 3.9 s | — |
| E2E-2 · una contraseña incorrecta no deja entrar y muestra el error | ✅ OK | 1.2 s | — |
| E2E-3 · sin sesión, las páginas protegidas redirigen al login | ✅ OK | 0.4 s | — |
| E2E-4 · el docente elige la sala y llega al mapa de la sala | ✅ OK | 2.2 s | — |
| E2E-5 · el docente reporta una falla y queda el ticket en la base | ✅ OK | 2.6 s | — |
| E2E-6 · el servidor rechaza el detalle faltante y el reporte duplicado | ✅ OK | 2.9 s | — |
| E2E-7 · el técnico lleva el ticket de pendiente a solucionado | ✅ OK | 6.1 s | — |
| E2E-8 · cada rol sólo accede a las páginas de su rol | ✅ OK | 3.8 s | — |
## Ids creados por la suite

Estos registros se crean durante la corrida (semilla + efectos de los casos) y se borran al terminar.

| Tabla | id | Etiqueta | Caso |
| --- | --- | --- | --- |
| usuarios | 106 | e2e_docente | E2E-semilla |
| usuarios | 107 | e2e_tecnico | E2E-semilla |
| usuarios | 108 | e2e_admin | E2E-semilla |
| salas | 986 | E2E-Sala Prueba | E2E-semilla |
| pcs | 440 | E2E-PC-1 | E2E-semilla |
| pcs | 441 | E2E-PC-2 | E2E-semilla |
| pcs | 442 | E2E-PC-3 | E2E-semilla |
| tickets | 78 | E2E-PC-1 / No enciende | E2E-5 |
| tickets | 79 | E2E-PC-2 / Falta de software | E2E-6 |
| tickets | 80 | E2E-PC-3 / Sin internet | E2E-7 |
## Ids borrados al terminar

Verificación con `SELECT` de que en la base ya no queda nada de la corrida.

| Tabla | id | Etiqueta | Caso |
| --- | --- | --- | --- |
| tickets | 78 | — | — |
| tickets | 79 | — | — |
| tickets | 80 | — | — |
| sesiones_docente | 146 | — | — |
| sesiones_docente | 147 | — | — |
| sesiones_docente | 148 | — | — |
| sesiones_docente | 149 | — | — |
| pcs | 440 | — | — |
| pcs | 441 | — | — |
| pcs | 442 | — | — |
| salas | 986 | — | — |
| usuarios | 106 | — | — |
| usuarios | 107 | — | — |
| usuarios | 108 | — | — |
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
