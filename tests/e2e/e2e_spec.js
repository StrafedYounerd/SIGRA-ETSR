// ============================================================
// SIGRA-ETSR · Suite E2E — Utilidades para los casos
// ============================================================
// e2e_spec.js
//
// Funciones compartidas por los archivos de casos: leer los datos
// sembrados, registrar los ids que cada caso va creando, y
// comprobar el estado real en la base.
//

const { leerDatos } = require('./e2e_fixtures');
const { consultar, registrarCreado } = require('./e2e_db');

/** Datos de la corrida (cuentas y sala E2E). */
function datos() {
    return leerDatos();
}

/**
 * Registra un id creado por un caso, para que la limpieza final lo
 * borre y para que el informe lo liste.
 */
function registrarId(tabla, id, etiqueta, caso) {
    registrarCreado({ tabla, id, etiqueta, caso });
}

/** Estado real de un ticket en la base. */
async function estadoTicket(idTicket) {
    const filas = await consultar(
        'SELECT id_ticket, id_pc, estado, tipo_falla, detalle_texto, fecha_cierre FROM tickets WHERE id_ticket = ?',
        [idTicket]
    );
    return filas[0] || null;
}

/** Estado real de una PC en la base. */
async function estadoPc(idPc) {
    const filas = await consultar('SELECT id_pc, etiqueta, estado_actual FROM pcs WHERE id_pc = ?', [idPc]);
    return filas[0] || null;
}

/** Movimientos registrados en historial_tickets para un ticket. */
async function historialDe(idTicket) {
    return consultar(
        'SELECT id_historial, id_usuario, estado_anterior, estado_nuevo, fecha_cambio FROM historial_tickets WHERE id_ticket = ? ORDER BY id_historial',
        [idTicket]
    );
}

/** Último ticket creado para una PC (para el caso de reporte duplicado). */
async function ticketDePc(idPc) {
    const filas = await consultar(
        'SELECT id_ticket, estado, tipo_falla FROM tickets WHERE id_pc = ? ORDER BY id_ticket DESC',
        [idPc]
    );
    return filas[0] || null;
}

module.exports = {
    datos,
    registrarId,
    estadoTicket,
    estadoPc,
    historialDe,
    ticketDePc,
};
