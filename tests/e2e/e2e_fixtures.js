// ============================================================
// SIGRA-ETSR · Suite E2E — Datos compartidos entre casos
// ============================================================
// e2e_fixtures.js
//
// Siembra las cuentas y la sala E2E una vez por corrida y expone
// un objeto `datos` con los ids reales, para que cada caso pueda
// verificar contra la base lo que ve en pantalla.
//
// La semilla se hace antes de todos los casos (globalSetup) y la
// limpieza al terminar (globalTeardown).
//

const { CREDENCIALES, PREFIJO } = require('./e2e_config');
const { sembrarUsuarios, sembrarSala } = require('./e2e_db');

// Se guarda en un archivo para que globalSetup y cada archivo de
// casos compartan la misma instancia sin volver a sembrar.
const fs = require('fs');
const path = require('path');
const { CACHE_E2E } = require('./e2e_config');

const RUTA_DATOS = path.join(CACHE_E2E, 'datos.json');

function guardarDatos(datos) {
    fs.mkdirSync(CACHE_E2E, { recursive: true });
    fs.writeFileSync(RUTA_DATOS, JSON.stringify(datos, null, 2), 'utf8');
}

function leerDatos() {
    return JSON.parse(fs.readFileSync(RUTA_DATOS, 'utf8'));
}

/**
 * Prepara la corrida: limpia la traza anterior, siembra cuentas y
 * sala, y guarda el resultado.
 */
async function prepararCorrida() {
    const { limpiarTraza } = require('./e2e_db');
    limpiarTraza();

    const usuarios = await sembrarUsuarios(CREDENCIALES);
    const sala = await sembrarSala(PREFIJO);

    const datos = {
        usuarios: Object.fromEntries(
            Object.entries(usuarios).map(([k, v]) => [k, {
                id_usuario: v.id_usuario,
                usuario: v.usuario,
                nombre: v.nombre,
                rol: v.rol,
                password: v.password,
            }])
        ),
        sala,
    };

    guardarDatos(datos);
    return datos;
}

module.exports = { prepararCorrida, guardarDatos, leerDatos, RUTA_DATOS };
