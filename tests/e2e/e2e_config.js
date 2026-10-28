// ============================================================
// SIGRA-ETSR · Suite E2E — Configuración y credenciales
// ============================================================
// e2e_config.js
//
// Lee la configuración de la suite desde variables de entorno.
// NO hay contraseñas escritas en el código: la suite falla con un
// mensaje claro si falta alguna.
//
// Variables obligatorias (contraseñas de las cuentas E2E):
//   E2E_DOCENTE_PASSWORD   contraseña de la cuenta docente E2E
//   E2E_TECNICO_PASSWORD   contraseña de la cuenta técnico E2E
//   E2E_ADMIN_PASSWORD     contraseña de la cuenta admin E2E
//
// Variables opcionales:
//   E2E_BASE_URL           base de la app (por defecto http://localhost:8080)
//   E2E_DB_HOST / _PORT / _USER / _PASSWORD / _NAME  conexión a la base
//   E2E_DB_CONTAINER       contenedor a consultar si el puerto no está
//                          publicado en el host (por defecto sigra-db)
//

const fs = require('fs');
const path = require('path');

const RAIZ_REPO = path.resolve(__dirname, '..', '..');
const CACHE_E2E = path.join(__dirname, 'node_modules', '.e2e');

// ── Lee el .env de la raíz del repo (está ignorado por git) ──
// Sirve para no tener que repetir usuario/clave de MySQL en el
// entorno: si no se define E2E_DB_PASSWORD, se toma MYSQL_PASSWORD
// del .env, que es exactamente lo que usa docker-compose.
function leerEnvRepo() {
    const ruta = path.join(RAIZ_REPO, '.env');
    const valores = {};
    if (!fs.existsSync(ruta)) return valores;

    for (const linea of fs.readFileSync(ruta, 'utf8').split('\n')) {
        const m = linea.match(/^\s*([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*?)\s*$/);
        if (!m) continue;
        let v = m[2];
        if ((v.startsWith("'") && v.endsWith("'")) || (v.startsWith('"') && v.endsWith('"'))) {
            v = v.slice(1, -1);
        }
        valores[m[1]] = v;
    }
    return valores;
}

const ENV_REPO = leerEnvRepo();

// ── Variables de la aplicación bajo prueba ──
const BASE_URL = (process.env.E2E_BASE_URL || 'http://localhost:8080').replace(/\/+$/, '');

// ── Credenciales de las cuentas E2E (solo por variable de entorno) ──
function credencial(nombreVar, descripcion) {
    const valor = process.env[nombreVar];
    if (!valor) {
        throw new Error(
            `Falta la variable de entorno ${nombreVar} (${descripcion}). ` +
            `Exportala antes de correr la suite E2E.`
        );
    }
    return valor;
}

const CREDENCIALES = {
    docente: credencial('E2E_DOCENTE_PASSWORD', 'contraseña del docente E2E'),
    tecnico: credencial('E2E_TECNICO_PASSWORD', 'contraseña del técnico E2E'),
    admin: credencial('E2E_ADMIN_PASSWORD', 'contraseña del administrador E2E'),
};

// ── Conexión a la base ──
// docker-compose publica MySQL en 127.0.0.1:3306, pero si el
// contenedor selevant sin esa publicación el puerto no está
// disponible desde el host: en ese caso se cae al IP de la red de
// Docker, que se obtiene con `docker inspect`.
const DB = {
    host: process.env.E2E_DB_HOST || '127.0.0.1',
    port: Number(process.env.E2E_DB_PORT || 3306),
    user: process.env.E2E_DB_USER || ENV_REPO.MYSQL_USER || 'sigra_user',
    password: process.env.E2E_DB_PASSWORD || ENV_REPO.MYSQL_PASSWORD || '',
    database: process.env.E2E_DB_NAME || ENV_REPO.MYSQL_DATABASE || 'sigra_etsr',
    contenedor: process.env.E2E_DB_CONTAINER || 'sigra-db',
};

// ── Prefijos de todo lo que la suite crea ──
// e2e_ para nombres de usuarios/etiquetas, E2E- para el nombre de la
// sala y para los títulos de caso del informe.
const PREFIJO = {
    usuario: 'e2e_',
    etiqueta: 'E2E-PC-',
    sala: 'E2E-Sala Prueba',
    caso: 'E2E-',
};

module.exports = {
    RAIZ_REPO,
    CACHE_E2E,
    BASE_URL,
    CREDENCIALES,
    DB,
    PREFIJO,
};
