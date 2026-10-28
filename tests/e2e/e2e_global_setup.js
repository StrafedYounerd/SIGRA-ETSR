// ============================================================
// SIGRA-ETSR · Suite E2E — Preparación global
// ============================================================
// e2e_global_setup.js
//
// Se corre una vez antes de todos los casos: valida que la app
// responda, siembra las cuentas y la sala E2E, y deja la traza de
// ids registrados en cero.
//

const { BASE_URL } = require('./e2e_config');
const { prepararCorrida } = require('./e2e_fixtures');

module.exports = async function globalSetup() {
    // La app tiene que estar levantada (docker compose up -d).
    const respuesta = await fetch(`${BASE_URL}/login.php`);
    if (!respuesta.ok) {
        throw new Error(
            `No se pudo acceder a ${BASE_URL}/login.php (HTTP ${respuesta.status}). ` +
            `Levantá la app con "docker compose up -d" antes de correr la suite.`
        );
    }

    const datos = await prepararCorrida();

    console.log('\n[e2e] Datos de la corrida:');
    console.log(`[e2e]   sala: ${datos.sala.nombre_sala} (id_sala=${datos.sala.id_sala})`);
    for (const pc of datos.sala.pcs) {
        console.log(`[e2e]   PC: ${pc.etiqueta} (id_pc=${pc.id_pc})`);
    }
    for (const [clave, u] of Object.entries(datos.usuarios)) {
        console.log(`[e2e]   usuario ${clave}: ${u.usuario} (id_usuario=${u.id_usuario})`);
    }
};
