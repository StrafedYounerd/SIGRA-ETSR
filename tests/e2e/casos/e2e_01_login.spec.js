// ============================================================
// SIGRA-ETSR · Suite E2E — Casos 1 a 3: acceso y control de roles
// ============================================================
// e2e_01_login.spec.js
//
// E2E-1: login correcto por rol (docente, técnico, admin).
// E2E-2: login con credenciales incorrectas muestra error y no entra.
// E2E-3: una página protegida sin sesión redirige al login.
//

const { test, expect } = require('@playwright/test');
const { login, irA } = require('../e2e_paginas');
const { datos } = require('../e2e_spec');

// ── E2E-1: login correcto según el rol ──
test('E2E-1 · el login correcto redirige al panel de cada rol', async ({ page }) => {
    const d = datos();

    const casos = [
        { clave: 'docente', esperado: '/docente/seleccionar-sala.php' },
        { clave: 'tecnico', esperado: '/tecnico/panel.php' },
        { clave: 'admin', esperado: '/admin/dashboard.php' },
    ];

    for (const c of casos) {
        const url = await login(page, d.usuarios[c.clave]);
        expect(url, `el rol ${c.clave} debe caer en su panel`).toContain(c.esperado);
    }
});

// ── E2E-2: credenciales incorrectas ──
test('E2E-2 · una contraseña incorrecta no deja entrar y muestra el error', async ({ page }) => {
    const d = datos();

    await irA(page, '/login.php');
    await page.fill('#usuario', d.usuarios.docente.usuario);
    await page.fill('#contraseña', 'contraseña-incorrecta-e2e');
    await page.click('#form-login button[type="submit"]');

    await expect(page.locator('.mensaje-error')).toHaveText(/Usuario o contraseña incorrectos/);
    await expect(page).toHaveURL(/\/login\.php/);

    // La sesión no se creó: una vista protegida sigue redirigiendo al login.
    await irA(page, '/docente/seleccionar-sala.php');
    await expect(page).toHaveURL(/\/login\.php/);
});

// ── E2E-3: acceso directo a una página protegida sin sesión ──
test('E2E-3 · sin sesión, las páginas protegidas redirigen al login', async ({ page }) => {
    const rutas = [
        '/docente/seleccionar-sala.php',
        '/docente/mapa-sala.php',
        '/tecnico/panel.php',
        '/tecnico/historial.php',
        '/admin/dashboard.php',
        '/admin/usuarios.php',
        '/admin/salas.php',
        '/admin/historial.php',
        '/admin/estadisticas.php',
    ];

    for (const ruta of rutas) {
        await irA(page, ruta);
        await expect(page, `${ruta} debe redirigir al login`).toHaveURL(/\/login\.php/);
    }
});
