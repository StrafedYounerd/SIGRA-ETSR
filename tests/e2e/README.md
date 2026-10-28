# Suite E2E — SIGRA-ETSR

Pruebas end-to-end de SIGRA-ETSR con [Playwright](https://playwright.dev/).
La suite **no toca el código de la aplicación**: sólo navega por la app
levantada por `docker compose` y consulta la base para verificar que lo
que se ve en pantalla coincide con lo que quedó guardado.

## Requisitos

- La app levantada: `docker compose up -d` (http://localhost:8080).
- Node.js 20 o superior.
- Chromium de Playwright: `npm run install:browsers` (una sola vez).

## Preparar

```bash
cd tests/e2e
npm install
npm run install:browsers
```

## Correr

Las contraseñas de las cuentas de prueba **se pasan por variable de
entorno**: no hay ninguna contraseña escrita en el repo.

```bash
export E2E_DOCENTE_PASSWORD='...'
export E2E_TECNICO_PASSWORD='...'
export E2E_ADMIN_PASSWORD='...'

npm test
```

Opcionales:

| Variable | Por defecto | Para qué |
| --- | --- | --- |
| `E2E_BASE_URL` | `http://localhost:8080` | base de la app |
| `E2E_DB_HOST` / `E2E_DB_PORT` | `127.0.0.1` / `3306` | conexión a MySQL |
| `E2E_DB_USER` / `E2E_DB_PASSWORD` / `E2E_DB_NAME` | los del `.env` de la raíz | credenciales de la base |
| `E2E_DB_CONTAINER` | `sigra-db` | contenedor a consultar si MySQL no está publicado en el host |

Si `E2E_DB_PASSWORD` no está, la suite toma `MYSQL_PASSWORD` del `.env`
de la raíz del repo (el mismo que usa `docker-compose`).

## Qué hace

1. **Siembra** tres cuentas (`e2e_docente`, `e2e_tecnico`, `e2e_admin`),
   una sala (`E2E-Sala Prueba`) y tres PCs (`E2E-PC-1` a `E2E-PC-3`).
2. Corre los casos de `casos/`.
3. **Limpia** todo lo que creó (tickets, historial, sesiones de clase,
   PCs, sala y cuentas) y verifica con `SELECT` que no queda nada.

Todo lo que la suite crea lleva el prefijo `e2e_` (usuarios, etiquetas
de PC) o `E2E-` (sala, títulos de caso), así nunca se confunde con los
datos reales del sistema.

## Casos

| Caso | Archivo | Qué comprueba |
| --- | --- | --- |
| E2E-1 | `casos/e2e_01_login.spec.js` | el login correcto redirige al panel de cada rol |
| E2E-2 | `casos/e2e_01_login.spec.js` | una contraseña incorrecta no deja entrar |
| E2E-3 | `casos/e2e_01_login.spec.js` | sin sesión, las páginas protegidas van al login |
| E2E-4 | `casos/e2e_02_docente.spec.js` | el docente elige la sala y llega al mapa |
| E2E-5 | `casos/e2e_02_docente.spec.js` | el docente reporta una falla y queda el ticket |
| E2E-6 | `casos/e2e_03_docente_validacion.spec.js` | el servidor exige el detalle y rechaza reportes duplicados |
| E2E-7 | `casos/e2e_04_tecnico.spec.js` | el técnico lleva el ticket de pendiente a solucionado |
| E2E-8 | `casos/e2e_05_roles_admin.spec.js` | cada rol sólo entra a las páginas de su rol |

## Informe

Al terminar la corrida queda en `informes/informe-e2e.md`:

- resultado de cada caso,
- ids creados y ids borrados,
- las consultas `SELECT` para verificar a mano que la base quedó limpia.

Para regenerarlo sin volver a correr los casos: `npm run informe`.

## Archivos

| Archivo | Para qué |
| --- | --- |
| `playwright.config.js` | configuración de la corrida |
| `e2e_config.js` | variables de entorno y prefijos |
| `e2e_db.js` | conexión a MySQL, semilla y traza de ids |
| `e2e_paginas.js` | login, selección de sala, clic en el plano |
| `e2e_spec.js` | helpers de verificación contra la base |
| `e2e_fixtures.js` | datos compartidos entre casos |
| `e2e_limpiar.js` | borrado de lo que creó la corrida |
| `e2e_informe.js` | armado del informe Markdown |
| `e2e_global_setup.js` / `e2e_global_teardown.js` | semilla y limpieza |

## Notas

- **El hash bcrypt.** `bcryptjs` genera hashes con prefijo `$2b$`, pero
  `includes/clave.php` sólo reconoce `$2y$` (ver `esHashBcrypt`). Por eso
  la semilla reescribe el prefijo: si no, la cuenta E2E se toma como un
  hash SHA-256 legado y el login falla.
- **El clic en el plano.** Las PCs con falla tienen una animación CSS
  infinita (el halo) y el plano se redibuja cada 20 s, así que el clic por
  coordenadas de Playwright no es fiable: `e2e_paginas.js` despacha el
  evento sobre el `<g class="eq">`, que es donde `js/mapa.js` engancha su
  manejador.
