// ============================================================
// SIGRA-ETSR · Suite E2E — Limpieza de lo creado por la corrida
// ============================================================
// e2e_limpiar.js
//
// Borra EXCLUSIVAMENTE los registros que la suite creó durante la
// corrida (la traza de e2e_db.js). Nada que haya estado en la base
// antes de la corrida se toca.
//
// Orden de borrado (por las FK con ON DELETE RESTRICT):
//   historial_tickets → tickets → sesiones_docente → pcs → salas → usuarios
//

const { consultar, ejecutar, leerTraza, registrarBorrado } = require('./e2e_db');

async function borrarPorId(tabla, columna, id) {
    try {
        const info = await ejecutar(`DELETE FROM ${tabla} WHERE ${columna} = ?`, [id]);
        if (info.affectedRows > 0) {
            registrarBorrado({ tabla, id });
            return true;
        }
    } catch (e) {
        // Una FK con ON DELETE RESTRICT impide el borrado: se informa
        // en vez de abortar toda la limpieza.
        console.warn(`[e2e] No se pudo borrar ${tabla}#${id}: ${e.message}`);
    }
    return false;
}

/**
 * Elimina todo lo registrado en la traza.
 * Devuelve el detalle de lo que se borró (para el informe).
 */
async function limpiar() {
    const traza = leerTraza();

    // ── Conjuntos de ids que la suite creó ──
    const idsUsuarios = traza.creados.filter((c) => c.tabla === 'usuarios').map((c) => c.id);
    const idsSalas = traza.creados.filter((c) => c.tabla === 'salas').map((c) => c.id);
    const idsPcs = traza.creados.filter((c) => c.tabla === 'pcs').map((c) => c.id);
    const idsTickets = traza.creados.filter((c) => c.tabla === 'tickets').map((c) => c.id);

    // Los tickets que los casos crean a partir de un clic en la app
    // pueden no quedar anotados en la traza: se recuperan por las PCs
    // de la suite, que son las únicas que los casos tocan.
    const ticketsDePcs = idsPcs.length
        ? await consultar(
            `SELECT id_ticket FROM tickets WHERE id_pc IN (${idsPcs.map(() => '?').join(',')})`,
            idsPcs
        )
        : [];
    const todosTickets = [...new Set([...idsTickets, ...ticketsDePcs.map((t) => t.id_ticket)])];

    // ── Borrado en orden de dependencias ──
    // Las FK del esquema son ON DELETE RESTRICT: hay que borrar los
    // hijos antes que los padres (tickets → PC, sesiones → sala).
    for (const id of todosTickets) {
        await ejecutar('DELETE FROM historial_tickets WHERE id_ticket = ?', [id]);
        await ejecutar('DELETE FROM fotos WHERE id_ticket = ?', [id]);
        await borrarPorId('tickets', 'id_ticket', id);
    }

    // Sesiones de clase: las del docente E2E y las de la sala E2E.
    if (idsUsuarios.length || idsSalas.length) {
        const cond = [];
        const params = [];
        if (idsUsuarios.length) {
            cond.push(`id_usuario IN (${idsUsuarios.map(() => '?').join(',')})`);
            params.push(...idsUsuarios);
        }
        if (idsSalas.length) {
            cond.push(`id_sala IN (${idsSalas.map(() => '?').join(',')})`);
            params.push(...idsSalas);
        }
        const sesiones = await consultar(
            `SELECT id_sesion FROM sesiones_docente WHERE ${cond.join(' OR ')}`,
            params
        );
        for (const s of sesiones) {
            await borrarPorId('sesiones_docente', 'id_sesion', s.id_sesion);
        }
    }

    for (const id of idsPcs) {
        await borrarPorId('pcs', 'id_pc', id);
    }
    for (const id of idsSalas) {
        await borrarPorId('salas', 'id_sala', id);
    }
    for (const id of idsUsuarios) {
        await borrarPorId('usuarios', 'id_usuario', id);
    }

    return leerTraza();
}

module.exports = { limpiar };
