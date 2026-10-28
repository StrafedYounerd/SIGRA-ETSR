// ============================================================
// SIGRA-ETSR · Suite E2E — Helpers de navegación
// ============================================================
// e2e_paginas.js
//
// Funciones de alto nivel para moverse por la aplicación: login,
// selección de sala, elección de una PC en el plano, etc.
// Todas usan los datos de la sesión E2E (variable de entorno) y
// los ids sembrados en la base.
//

const { BASE_URL } = require('./e2e_config');

/** Espera a que la app termine de redirigir y muestra la URL final. */
async function irA(page, ruta) {
    const url = ruta.startsWith('http') ? ruta : `${BASE_URL}${ruta}`;
    await page.goto(url, { waitUntil: 'domcontentloaded' });
    return page.url();
}

/**
 * Login por el formulario real de /login.php.
 * Devuelve la URL a la que redirigió según el rol.
 *
 * Primero cierra cualquier sesión previa: si ya hay sesión, /login.php
 * redirige al panel y el formulario no existe.
 */
async function login(page, { usuario, password }) {
    await logout(page);
    await irA(page, '/login.php');
    await page.fill('#usuario', usuario);
    await page.fill('#contraseña', password);
    await Promise.all([
        page.waitForURL((u) => !u.pathname.endsWith('/login.php'), { timeout: 15000 }),
        page.click('#form-login button[type="submit"]'),
    ]);
    return page.url();
}

/** Cierra la sesión desde el menú de usuario (si la vista lo tiene). */
async function logout(page) {
    const menu = page.locator('.usermenu__btn');
    if (await menu.count()) {
        await menu.click();
        const enlace = page.locator('#um-panel a[href="/logout.php"]');
        if (await enlace.count()) {
            await Promise.all([
                page.waitForURL((u) => u.pathname.endsWith('/login.php'), { timeout: 15000 }),
                enlace.click(),
            ]);
            return;
        }
    }
    // Sin sesión abierta (o sin menú): se fuerza el cierre por la URL.
    await irA(page, '/logout.php');
}

/**
 * El docente elige la sala E2E e informa un grupo.
 * Deja la sesión del docente con la sala activa (clave para el mapa).
 */
async function elegirSala(page, { nombreSala, grupo }) {
    await irA(page, '/docente/seleccionar-sala.php');
    // El radio no tiene id propio: se busca por el label que contiene
    // el nombre de la sala.
    const radio = page.locator('label.room', { hasText: nombreSala }).locator('input[type="radio"]');
    await radio.check();
    await page.fill('#grupo', grupo);
    await Promise.all([
        page.waitForURL((u) => u.pathname.includes('mapa-sala.php'), { timeout: 15000 }),
        page.click('form.form-elegir-sala button[type="submit"]'),
    ]);
}

/**
 * Hace clic en el equipo del plano SVG por su etiqueta y espera a
 * que el cajón lateral se actualice.
 *
 * El clic se despacha sobre el <g class="eq">, que es donde js/mapa.js
 * engancha su manejador. No se usa el click() de Playwright porque en
 * este SVG el clic no es fiable: las PCs con falla tienen una animación
 * CSS infinita (el halo) y el plano se redibuja cada 20 s con el
 * polling, así que el bounding box del elemento nunca queda quieto y
 * el clic por coordenadas termina en otro equipo (o en el vacío).
 * El evento se despacha sobre el elemento, que es lo mismo que hace
 * el usuario al tocar el equipo.
 *
 * @param {boolean} esperarFormulario  si el equipo ya tiene un reporte
 *   abierto, el cajón no muestra el formulario: se espera sólo a que
 *   aparezca el bloque de estado del equipo.
 */
async function elegirEquipo(page, etiqueta, { esperarFormulario = true } = {}) {
    const equipo = page.locator(`g.eq[data-etiqueta="${etiqueta}"]`);
    await equipo.dispatchEvent('click');
    if (esperarFormulario) {
        await page.locator('#campo-tipo-falla').waitFor({ state: 'visible', timeout: 10000 });
    } else {
        await page.locator('#cajon .codigo').waitFor({ state: 'visible', timeout: 10000 });
    }
}

/** Reporta una falla desde el cajón del mapa (sin foto). */
async function reportarFalla(page, { tipo, detalle }) {
    await page.selectOption('#campo-tipo-falla', tipo);
    if (detalle !== undefined) {
        await page.waitForSelector('#contenedor-detalle:not([hidden])', { timeout: 5000 });
        await page.fill('#campo-detalle', detalle);
    }
    await page.click('#btn-confirmar-reporte');
}

module.exports = {
    irA,
    login,
    logout,
    elegirSala,
    elegirEquipo,
    reportarFalla,
};
