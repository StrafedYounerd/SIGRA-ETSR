// ============================================================
// SIGRA-ETSR · Suite E2E — Casos 4 y 5: flujo del docente
// ============================================================
// e2e_02_docente.spec.js
//
// E2E-4: el docente elige la sala y entra al mapa (queda registrada
//        la sesión de clase).
// E2E-5: el docente reporta una falla desde el plano: se crea el
//        ticket, la PC pasa a "falla" y el plano lo muestra.
//
// Todos los ids que estos casos crean quedan registrados para que la
// limpieza final los borre.
//

const { test, expect } = require('@playwright/test');
const { login, irA, elegirSala, elegirEquipo, reportarFalla } = require('../e2e_paginas');
const { datos, registrarId, estadoTicket, estadoPc } = require('../e2e_spec');
const { consultar } = require('../e2e_db');

test.describe('Flujo del docente', () => {
    // ── E2E-4: seleccionar sala ──
    test('E2E-4 · el docente elige la sala y llega al mapa de la sala', async ({ page }) => {
        const d = datos();

        await login(page, d.usuarios.docente);
        await elegirSala(page, {
            nombreSala: d.sala.nombre_sala,
            grupo: 'E2E-Grupo 1',
        });

        await expect(page).toHaveURL(/mapa-sala\.php/);
        await expect(page.locator('.contexto h1')).toHaveText(d.sala.nombre_sala);
        await expect(page.locator('.contexto .dato')).toContainText('E2E-Grupo 1');

        // El plano se dibujó con las PCs de la sala.
        await expect(page.locator(`g.eq[data-etiqueta="${d.sala.pcs[0].etiqueta}"]`)).toHaveCount(1);

        // La sesión de clase quedó registrada en la base.
        const sesiones = await consultar(
            'SELECT id_sesion, id_sala, grupo FROM sesiones_docente WHERE id_usuario = ? AND id_sala = ? ORDER BY id_sesion DESC',
            [d.usuarios.docente.id_usuario, d.sala.id_sala]
        );
        expect(sesiones.length).toBeGreaterThan(0);
        expect(sesiones[0].grupo).toBe('E2E-Grupo 1');
    });

    // ── E2E-5: reportar una falla ──
    test('E2E-5 · el docente reporta una falla y queda el ticket en la base', async ({ page }) => {
        const d = datos();
        const pc = d.sala.pcs[0];

        await login(page, d.usuarios.docente);
        await elegirSala(page, {
            nombreSala: d.sala.nombre_sala,
            grupo: 'E2E-Grupo 2',
        });

        await elegirEquipo(page, pc.etiqueta);
        await reportarFalla(page, { tipo: 'No enciende' });

        // El cajón pasa a mostrar el reporte abierto.
        await expect(page.locator('.tit-reporte')).toHaveText('Reporte abierto');
        await expect(page.locator('#cajon')).toContainText('No enciende');

        // El equipo queda marcado con falla en el plano.
        await expect(page.locator(`g.eq[data-etiqueta="${pc.etiqueta}"]`)).toHaveClass(/falla/);

        // En la base: ticket pendiente del docente E2E y PC en estado falla.
        const tickets = await consultar(
            'SELECT id_ticket, id_pc, id_docente, tipo_falla, estado FROM tickets WHERE id_pc = ? ORDER BY id_ticket DESC',
            [pc.id_pc]
        );
        expect(tickets.length).toBeGreaterThan(0);
        const ticket = tickets[0];
        expect(ticket.id_docente).toBe(d.usuarios.docente.id_usuario);
        expect(ticket.tipo_falla).toBe('No enciende');
        expect(ticket.estado).toBe('pendiente');

        registrarId('tickets', ticket.id_ticket, `${pc.etiqueta} / No enciende`, 'E2E-5');

        const estado = await estadoPc(pc.id_pc);
        expect(estado.estado_actual).toBe('falla');

        const enBase = await estadoTicket(ticket.id_ticket);
        expect(enBase.estado).toBe('pendiente');
    });
});
