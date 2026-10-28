// ============================================================
// SIGRA-ETSR · Suite E2E — Caso 8: separación de roles y vistas de admin
// ============================================================
// e2e_05_roles_admin.spec.js
//
// E2E-8: cada rol ve sólo lo que le corresponde:
//        · el docente no entra al panel del técnico ni al de admin,
//        · el técnico no entra a las páginas de administración,
//        · el admin entra a todas.
//
// La separación de roles está implementada en código (requerirRol en
// includes/auth.php), así que un rol sin permiso recibe un "Acceso
// denegado" y NO una redirección al login.
//

const { test, expect } = require('@playwright/test');
const { login, irA } = require('../e2e_paginas');
const { datos } = require('../e2e_spec');

test.describe('Separación de roles', () => {
    test('E2E-8 · cada rol sólo accede a las páginas de su rol', async ({ page }) => {
        const d = datos();

        // ── El docente no entra a las páginas de técnico ni de admin ──
        await login(page, d.usuarios.docente);
        for (const ruta of [
            '/tecnico/panel.php',
            '/tecnico/historial.php',
            '/tecnico/actualizar-ticket.php',
            '/admin/dashboard.php',
            '/admin/usuarios.php',
            '/admin/salas.php',
            '/admin/estadisticas.php',
        ]) {
            await irA(page, ruta);
            await expect(page.locator('body')).toContainText('Acceso denegado');
            // No es un cierre de sesión: sigue siendo el mismo docente.
            await expect(page).toHaveURL(new RegExp(ruta.replace(/[/.]/g, '\\$&')));
        }

        // ── El técnico entra a su tablero y al historial, no al admin ──
        await login(page, d.usuarios.tecnico);
        await irA(page, '/tecnico/panel.php');
        await expect(page.locator('.contexto h1')).toHaveText('Tickets activos');

        await irA(page, '/tecnico/historial.php');
        await expect(page.locator('.contexto h1')).toHaveText('Historial completo');

        for (const ruta of [
            '/admin/dashboard.php',
            '/admin/usuarios.php',
            '/admin/salas.php',
            '/admin/estadisticas.php',
        ]) {
            await irA(page, ruta);
            await expect(page.locator('body')).toContainText('Acceso denegado');
        }

        // ── El admin entra a todo ──
        await login(page, d.usuarios.admin);
        const paginasAdmin = [
            ['/admin/dashboard.php', 'Panel de administración'],
            ['/admin/usuarios.php', 'Usuarios'],
            ['/admin/salas.php', 'Salas y dispositivos'],
            ['/admin/historial.php', 'Historial completo'],
            ['/admin/estadisticas.php', 'Estadísticas matemáticas'],
        ];
        for (const [ruta, titulo] of paginasAdmin) {
            await irA(page, ruta);
            await expect(page.locator('.contexto h1')).toHaveText(titulo);
            await expect(page.locator('body')).not.toContainText('Acceso denegado');
        }

        // El admin ve los equipos de la sala E2E en la gestión de salas.
        await irA(page, '/admin/salas.php');
        await expect(page.locator('body')).toContainText(d.sala.nombre_sala);
    });
});
