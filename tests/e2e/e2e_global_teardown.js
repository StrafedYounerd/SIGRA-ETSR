// ============================================================
// SIGRA-ETSR · Suite E2E — Cierre global
// ============================================================
// e2e_global_teardown.js
//
// Se corre una vez al terminar todos los casos: borra lo que la
// suite creó y deja el informe en tests/e2e/informes/.
//

const { limpiar } = require('./e2e_limpiar');
const { escribirInforme } = require('./e2e_informe');

module.exports = async function globalTeardown(config) {
    const traza = await limpiar();
    console.log('\n[e2e] Registros creados:', traza.creados.length);
    console.log('[e2e] Registros borrados:', traza.borrados.length);

    await escribirInforme(traza, config);
};
