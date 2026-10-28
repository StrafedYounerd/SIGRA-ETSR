// ============================================================
// SIGRA-ETSR · Suite E2E — Acceso a la base de datos
// ============================================================
// e2e_db.js
//
// Acceso de SOLO LECTURA y de PREPARACIÓN a la base para poder:
//   · sembrar (seed) las cuentas y la sala que la suite necesita,
//   · leer los ids creados para verificar el estado real,
//   · limpiar al final lo que la suite creó.
//
// La suite NUNCA borra datos que no creó: todo lo que se elimina
// está registrado en una lista de ids creados durante la corrida
// (ver e2e_limpiar.js), de modo que el ids de "creado" y "borrado"
// del informe sale de esa misma traza.
//

const { execFileSync } = require('child_process');
const mysql = require('mysql2/promise');
const bcrypt = require('bcryptjs');
const { DB } = require('./e2e_config');

// ── Resuelve el host de la base ──
// Si el puerto publicado no responde, se busca el IP del contenedor
// en la red de Docker (el compose de este proyecto no siempre
// publica el 3306 hacia el host).
let hostResuelto = null;

function ipContenedor() {
    try {
        const salida = execFileSync(
            'docker',
            ['inspect', '-f', '{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}', DB.contenedor],
            { encoding: 'utf8', timeout: 10000 }
        ).trim();
        return salida || null;
    } catch (e) {
        return null;
    }
}

async function conectar() {
    if (hostResuelto === null) {
        const intentos = [
            { host: DB.host, port: DB.port },
            { host: ipContenedor(), port: 3306 },
        ].filter((x) => x.host);

        let ultimoError = null;
        for (const intento of intentos) {
            try {
                const conn = await mysql.createConnection({
                    host: intento.host,
                    port: intento.port,
                    user: DB.user,
                    password: DB.password,
                    database: DB.database,
                    connectTimeout: 5000,
                });
                hostResuelto = `${intento.host}:${intento.port}`;
                return conn;
            } catch (e) {
                ultimoError = e;
            }
        }
        throw ultimoError || new Error('No se pudo conectar a la base de datos');
    }
    return mysql.createConnection({
        host: hostResuelto.split(':')[0],
        port: Number(hostResuelto.split(':')[1]),
        user: DB.user,
        password: DB.password,
        database: DB.database,
        connectTimeout: 5000,
    });
}

// ── Helpers de consulta ──
async function consultar(sql, params = []) {
    const conn = await conectar();
    try {
        const [filas] = await conn.query(sql, params);
        return filas;
    } finally {
        await conn.end();
    }
}

async function ejecutar(sql, params = []) {
    const conn = await conectar();
    try {
        const [resultado] = await conn.query(sql, params);
        return resultado;
    } finally {
        await conn.end();
    }
}

// ── Traza de todo lo que la suite crea ──
// Se guarda en disco (fuera de git, dentro de node_modules/.e2e)
// para que el informe final pueda listar los ids creados y los
// ids borrados, incluso si la corrida terminó a medias.
const fs = require('fs');
const path = require('path');
const { CACHE_E2E } = require('./e2e_config');

const RUTA_TRAZA = path.join(CACHE_E2E, 'traza.json');

function leerTraza() {
    try {
        return JSON.parse(fs.readFileSync(RUTA_TRAZA, 'utf8'));
    } catch (e) {
        return { creados: [], borrados: [] };
    }
}

function guardarTraza(traza) {
    fs.mkdirSync(CACHE_E2E, { recursive: true });
    fs.writeFileSync(RUTA_TRAZA, JSON.stringify(traza, null, 2), 'utf8');
}

function registrarCreado(objeto) {
    const traza = leerTraza();
    traza.creados.push(objeto);
    guardarTraza(traza);
}

function registrarBorrado(objeto) {
    const traza = leerTraza();
    traza.borrados.push(objeto);
    guardarTraza(traza);
}

function limpiarTraza() {
    guardarTraza({ creados: [], borrados: [] });
}

// ============================================================
// SEMILLA (seed) — prepara los datos que la suite necesita
// ============================================================

/**
 * Genera un hash bcrypt compatible con la app.
 *
 * bcryptjs emite el prefijo "$2b$", pero includes/clave.php sólo
 * reconoce "$2y$" (esHashBcrypt). Un hash "$2b$" se tomaría como
 * un hash SHA-256 legado y el login fallaría, así que se reescribe
 * el prefijo: el resto del hash es idéntico y PHP lo valida bien
 * (password_verify acepta "$2y$" y "$2b$" con el mismo cuerpo).
 */
function hashBcrypt(contrasena) {
    return bcrypt.hashSync(contrasena, 10).replace(/^\$2b\$/, '$2y$');
}

/**
 * Crea (o reutiliza) las tres cuentas E2E con las contraseñas que
 * llegan por variable de entorno, y devuelve sus datos.
 *
 * Los usuarios llevan el prefijo e2e_ para poder identificarlos y
 * borrarlos sin tocar las cuentas reales del sistema.
 */
async function sembrarUsuarios(credenciales) {
    const usuarios = [
        { clave: 'docente', usuario: 'e2e_docente', nombre: 'Docente E2E', rol: 'docente' },
        { clave: 'tecnico', usuario: 'e2e_tecnico', nombre: 'Técnico E2E', rol: 'tecnico' },
        { clave: 'admin', usuario: 'e2e_admin', nombre: 'Administrador E2E', rol: 'admin' },
    ];

    const resultado = {};

    for (const u of usuarios) {
        const hash = hashBcrypt(credenciales[u.clave]);
        const [existente] = await consultar(
            'SELECT id_usuario, usuario FROM usuarios WHERE usuario = ?',
            [u.usuario]
        );

        if (existente) {
            // El usuario ya existe de una corrida anterior: se le
            // actualiza el hash para que la contraseña del entorno
            // sea siempre la vigente. Igual se registra en la traza:
            // el prefijo e2e_ marca la cuenta como propiedad de la
            // suite, así que la limpieza final la borra siempre.
            await ejecutar(
                'UPDATE usuarios SET nombre = ?, contraseña = ?, rol = ?, activo = 1 WHERE id_usuario = ?',
                [u.nombre, hash, u.rol, existente.id_usuario]
            );
            registrarCreado({
                tabla: 'usuarios',
                id: existente.id_usuario,
                etiqueta: u.usuario,
                caso: 'E2E-semilla',
            });
            resultado[u.clave] = {
                id_usuario: existente.id_usuario,
                usuario: u.usuario,
                nombre: u.nombre,
                rol: u.rol,
                password: credenciales[u.clave],
            };
        } else {
            const r = await ejecutar(
                'INSERT INTO usuarios (nombre, usuario, contraseña, rol, activo) VALUES (?, ?, ?, ?, 1)',
                [u.nombre, u.usuario, hash, u.rol]
            );
            resultado[u.clave] = {
                id_usuario: r.insertId,
                usuario: u.usuario,
                nombre: u.nombre,
                rol: u.rol,
                password: credenciales[u.clave],
            };
            registrarCreado({
                tabla: 'usuarios',
                id: r.insertId,
                etiqueta: u.usuario,
                caso: 'E2E-semilla',
            });
        }
    }

    return resultado;
}

/**
 * Crea (o reutiliza) la sala E2E con 3 PCs de prueba, sin switch ni
 * TV (los casos de esta tanda no usan foto ni editor de plano).
 * Devuelve la sala y las PCs.
 */
async function sembrarSala(prefijo) {
    const [existente] = await consultar(
        'SELECT id_sala FROM salas WHERE nombre_sala = ?',
        [prefijo.sala]
    );

    let idSala;
    if (existente) {
        idSala = existente.id_sala;
        registrarCreado({
            tabla: 'salas',
            id: idSala,
            etiqueta: prefijo.sala,
            caso: 'E2E-semilla',
        });
    } else {
        const r = await ejecutar(
            'INSERT INTO salas (nombre_sala, descripcion, activa) VALUES (?, ?, 1)',
            [prefijo.sala, 'Sala creada por la suite E2E de SIGRA-ETSR']
        );
        idSala = r.insertId;
        registrarCreado({ tabla: 'salas', id: idSala, etiqueta: prefijo.sala, caso: 'E2E-semilla' });
    }

    const pcs = [];
    for (let i = 1; i <= 3; i++) {
        const etiqueta = `${prefijo.etiqueta}${i}`;
        const [pc] = await consultar(
            'SELECT id_pc FROM pcs WHERE id_sala = ? AND etiqueta = ?',
            [idSala, etiqueta]
        );
        if (pc) {
            pcs.push({ id_pc: pc.id_pc, etiqueta, fila: 1, posicion: i });
            registrarCreado({
                tabla: 'pcs',
                id: pc.id_pc,
                etiqueta,
                caso: 'E2E-semilla',
            });
        } else {
            const r = await ejecutar(
                'INSERT INTO pcs (id_sala, etiqueta, tipo_equipo, fila, posicion, estado_actual) VALUES (?, ?, ?, ?, ?, ?)',
                [idSala, etiqueta, 'PC', 1, i, 'sin_eval']
            );
            pcs.push({ id_pc: r.insertId, etiqueta, fila: 1, posicion: i });
            registrarCreado({
                tabla: 'pcs',
                id: r.insertId,
                etiqueta,
                caso: 'E2E-semilla',
            });
        }
    }

    return { id_sala: idSala, nombre_sala: prefijo.sala, pcs };
}

module.exports = {
    conectar,
    consultar,
    ejecutar,
    leerTraza,
    guardarTraza,
    registrarCreado,
    registrarBorrado,
    limpiarTraza,
    sembrarUsuarios,
    sembrarSala,
};
