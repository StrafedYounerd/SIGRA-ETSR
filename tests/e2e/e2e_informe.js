// ============================================================
// SIGRA-ETSR · Suite E2E — Informe de la corrida
// ============================================================
// e2e_informe.js
//
// Arma tests/e2e/informes/informe-e2e.md con:
//   · el resultado de cada caso (E2E-1 … E2E-8),
//   · los ids creados por la suite y los ids borrados al terminar,
//   · las consultas SELECT usadas para verificar cada caso.
//
// El informe se escribe al terminar la corrida (globalTeardown) y
// también se puede regenerar a mano con: node e2e_informe.js
//

const fs = require('fs');
const path = require('path');
const { PREFIJO, BASE_URL } = require('./e2e_config');
const { leerDatos } = require('./e2e_fixtures');

const DIR_INFORMES = path.join(__dirname, 'informes');
const RUTA_INFORME = path.join(DIR_INFORMES, 'informe-e2e.md');
const RUTA_JSON = path.join(DIR_INFORMES, 'resultados.json');

function leerResultados() {
    try {
        return JSON.parse(fs.readFileSync(RUTA_JSON, 'utf8'));
    } catch (e) {
        return null;
    }
}

/**
 * Extrae de resultados.json un resumen por caso: título, estado,
 * duración y mensaje de error si lo hubo.
 *
 * El reporter JSON de Playwright agrupa los specs por archivo: cada
 * suite tiene "specs" y, si hay describe(), "suites" anidadas. Se
 * recorren las dos.
 */
function resumirCasos(resultados) {
    if (!resultados) return [];

    const casos = [];
    const recorrer = (suite) => {
        for (const spec of suite.specs || []) {
            for (const prueba of spec.tests || []) {
                const lista = prueba.results || [];
                const resultado = lista[lista.length - 1];
                casos.push({
                    titulo: spec.title,
                    archivo: spec.file,
                    estado: prueba.status || (spec.ok ? 'expected' : 'unexpected'),
                    esperado: prueba.expectedStatus || 'passed',
                    duracionMs: resultado ? resultado.duration : 0,
                    error: resultado && resultado.error ? resultado.error.message : null,
                    intentos: lista.length,
                });
            }
        }
        for (const sub of suite.suites || []) recorrer(sub);
    };

    for (const suite of resultados.suites || []) recorrer(suite);
    return casos;
}

function tablaCasos(casos) {
    if (!casos.length) return '_No se registró ningún caso._\n';

    const lineas = [
        '| Caso | Resultado | Duración | Detalle |',
        '| --- | --- | --- | --- |',
    ];
    for (const c of casos) {
        const ok = c.estado === 'expected' && c.esperado === 'passed';
        const marca = ok ? '✅ OK' : c.estado === 'skipped' ? '⏭️ omitido' : '❌ falló';
        const detalle = (c.error || '').split('\n')[0].replace(/\|/g, '\\|');
        lineas.push(
            `| ${c.titulo} | ${marca} | ${(c.duracionMs / 1000).toFixed(1)} s | ${detalle || '—'} |`
        );
    }
    return lineas.join('\n') + '\n';
}

function tablaIds(lista) {
    if (!lista.length) return '_Ninguno._\n';
    const lineas = ['| Tabla | id | Etiqueta | Caso |', '| --- | --- | --- | --- |'];
    for (const item of lista) {
        lineas.push(
            `| ${item.tabla} | ${item.id} | ${item.etiqueta ?? '—'} | ${item.caso ?? '—'} |`
        );
    }
    return lineas.join('\n') + '\n';
}

/**
 * Escribe el informe en Markdown.
 * @param {object} traza  traza de ids creados/borrados
 * @param {object} config configuración de Playwright (opcional)
 */
async function escribirInforme(traza, config) {
    fs.mkdirSync(DIR_INFORMES, { recursive: true });

    const resultados = leerResultados();
    const casos = resumirCasos(resultados);
    const datosCorrida = (() => {
        try {
            return leerDatos();
        } catch (e) {
            return null;
        }
    })();

    const ahora = new Date().toISOString().replace('T', ' ').slice(0, 19);
    const fallidos = casos.filter((c) => c.estado !== 'expected' && c.estado !== 'skipped');

    let md = '';
    md += '# Informe de pruebas E2E — SIGRA-ETSR\n\n';
    md += `- **Fecha:** ${ahora}\n`;
    md += `- **Aplicación bajo prueba:** ${BASE_URL}\n`;
    md += `- **Casos ejecutados:** ${casos.length}\n`;
    md += `- **Resultado general:** ${fallidos.length === 0 && casos.length > 0 ? '✅ todo OK' : `❌ ${fallidos.length} fallo(s)`}\n\n`;

    if (datosCorrida) {
        md += '## Datos de prueba\n\n';
        md += `Sala E2E: **${datosCorrida.sala.nombre_sala}** (id_sala=${datosCorrida.sala.id_sala})\n\n`;
        md += '| Etiqueta PC | id_pc | Fila | Posición |\n| --- | --- | --- | --- |\n';
        for (const pc of datosCorrida.sala.pcs) {
            md += `| ${pc.etiqueta} | ${pc.id_pc} | ${pc.fila} | ${pc.posicion} |\n`;
        }
        md += '\n| Rol | Usuario | id_usuario |\n| --- | --- | --- |\n';
        for (const [clave, u] of Object.entries(datosCorrida.usuarios)) {
            md += `| ${clave} | ${u.usuario} | ${u.id_usuario} |\n`;
        }
        md += '\n';
    }

    md += '## Resultado por caso\n\n';
    md += tablaCasos(casos);

    md += '## Ids creados por la suite\n\n';
    md += 'Estos registros se crean durante la corrida (semilla + efectos de los casos) y se borran al terminar.\n\n';
    md += tablaIds(traza.creados);

    md += '## Ids borrados al terminar\n\n';
    md += 'Verificación con `SELECT` de que en la base ya no queda nada de la corrida.\n\n';
    md += tablaIds(traza.borrados);

    md += '## Consultas SELECT de verificación\n\n';
    md += '```sql\n';
    md += '-- Estados finales de las PCs E2E (deben volver a "sin_eval")\n';
    md += 'SELECT id_pc, etiqueta, estado_actual FROM pcs\n';
    md += ` WHERE etiqueta LIKE '${PREFIJO.etiqueta}%' ORDER BY id_pc;\n\n`;
    md += '-- Tickets creados por la suite (deben ser 0 al terminar)\n';
    md += 'SELECT id_ticket, id_pc, estado FROM tickets\n';
    md += ` WHERE id_pc IN (SELECT id_pc FROM pcs WHERE etiqueta LIKE '${PREFIJO.etiqueta}%')\n`;
    md += ' ORDER BY id_ticket;\n\n';
    md += '-- Movimientos de historial de los tickets E2E (deben ser 0 al terminar)\n';
    md += 'SELECT id_historial, id_ticket, estado_anterior, estado_nuevo\n';
    md += ' FROM historial_tickets\n';
    md += ` WHERE id_ticket IN (SELECT id_ticket FROM tickets WHERE id_pc IN\n`;
    md += `   (SELECT id_pc FROM pcs WHERE etiqueta LIKE '${PREFIJO.etiqueta}%'));\n\n`;
    md += '-- Sesiones de clase de los usuarios E2E (deben ser 0 al terminar)\n';
    md += 'SELECT id_sesion, id_usuario, id_sala, grupo FROM sesiones_docente\n';
    md += ` WHERE id_usuario IN (SELECT id_usuario FROM usuarios WHERE usuario LIKE '${PREFIJO.usuario}%');\n`;
    md += '```\n';

    fs.writeFileSync(RUTA_INFORME, md, 'utf8');
    console.log(`[e2e] Informe escrito en ${RUTA_INFORME}`);
    return RUTA_INFORME;
}

module.exports = { escribirInforme, RUTA_INFORME };

// Regeneración manual: node e2e_informe.js
if (require.main === module) {
    const { leerTraza } = require('./e2e_db');
    escribirInforme(leerTraza()).catch((e) => {
        console.error(e);
        process.exit(1);
    });
}
