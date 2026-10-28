// ============================================================
// SIGRA-ETSR · Suite E2E — Caso 7: flujo del técnico
// ============================================================
// e2e_04_tecnico.spec.js
//
// E2E-7: el docente reporta una falla y el técnico la mueve por el
//        tablero: pendiente → en reparación → solucionado.
//
// Se comprueba la pantalla del tablero Y el estado real en la base
// (tickets, pcs e historial_tickets).
//

const { test, expect } = require('@playwright/test');
const { login, irA, elegirSala, elegirEquipo, reportarFalla } = require('../e2e_paginas');
const { datos, registrarId, estadoTicket, estadoPc, historialDe } = require('../e2e_spec');
const { consultar } = require('../e2e_db');

test.describe('Flujo del técnico', () => {
    test('E2E-7 · el técnico lleva el ticket de pendiente a solucionado', async ({ page }) => {
        const d = datos();
        const pc = d.sala.pcs[2];

        // ── El docente reporta la falla ──
        await login(page, d.usuarios.docente);
        await elegirSala(page, {
            nombreSala: d.sala.nombre_sala,
            grupo: 'E2E-Grupo 4',
        });
        await elegirEquipo(page, pc.etiqueta);
        await reportarFalla(page, { tipo: 'Sin internet' });
        await expect(page.locator('.tit-reporte')).toHaveText('Reporte abierto');

        const tickets = await consultar(
            'SELECT id_ticket FROM tickets WHERE id_pc = ? ORDER BY id_ticket DESC',
            [pc.id_pc]
        );
        const idTicket = tickets[0].id_ticket;
        registrarId('tickets', idTicket, `${pc.etiqueta} / Sin internet`, 'E2E-7');

        // ── El técnico entra al tablero ──
        await login(page, d.usuarios.tecnico);
        await irA(page, '/tecnico/panel.php');

        // El ticket aparece en la columna Pendiente.
        const tarjeta = page.locator(`.tarjeta-ticket:has(input[value="${idTicket}"])`);
        await expect(tarjeta).toHaveCount(1);
        await expect(tarjeta).toContainText('Sin internet');
        await expect(tarjeta).toContainText(pc.etiqueta);

        // ── Pendiente → En reparación ──
        await Promise.all([
            page.waitForURL(/panel\.php\?tipo=ok/, { timeout: 15000 }),
            tarjeta.locator('button:has-text("Marcar en reparación")').click(),
        ]);
        await expect(page.locator('.mensaje-ok')).toContainText(`Ticket #${idTicket} actualizado a: en_reparacion`);

        let estado = await estadoTicket(idTicket);
        expect(estado.estado).toBe('en_reparacion');

        // Ahora está en la columna En reparación.
        const tarjeta2 = page.locator(`.tarjeta-ticket:has(input[value="${idTicket}"])`);
        await expect(tarjeta2.locator('.form-tarjeta input[value="solucionado"]')).toHaveCount(1);

        // ── En reparación → Solucionado ──
        await Promise.all([
            page.waitForURL(/panel\.php\?tipo=ok/, { timeout: 15000 }),
            tarjeta2.locator('button:has-text("Marcar solucionado")').click(),
        ]);
        await expect(page.locator('.mensaje-ok')).toContainText(`Ticket #${idTicket} actualizado a: solucionado`);

        estado = await estadoTicket(idTicket);
        expect(estado.estado).toBe('solucionado');
        expect(estado.fecha_cierre).not.toBeNull();

        // La PC vuelve a "funcional" en la base.
        const pcEstado = await estadoPc(pc.id_pc);
        expect(pcEstado.estado_actual).toBe('funcional');

        // Los dos movimientos quedaron en el historial.
        const movimientos = await historialDe(idTicket);
        expect(movimientos.length).toBe(2);
        expect(movimientos[0].estado_anterior).toBe('pendiente');
        expect(movimientos[0].estado_nuevo).toBe('en_reparacion');
        expect(movimientos[0].id_usuario).toBe(d.usuarios.tecnico.id_usuario);
        expect(movimientos[1].estado_anterior).toBe('en_reparacion');
        expect(movimientos[1].estado_nuevo).toBe('solucionado');

        // El ticket ya no está en el tablero de activos; aparece en
        // la columna "Solucionado hoy".
        await irA(page, '/tecnico/panel.php');
        const columna = page.locator('section[aria-label="Solucionado hoy"]');
        await expect(columna.locator(`.tarjeta-ticket:has(input[value="${idTicket}"])`)).toHaveCount(1);
        await expect(
            page.locator('section[aria-label="Pendiente"]').locator(
                `.tarjeta-ticket:has(input[value="${idTicket}"])`
            )
        ).toHaveCount(0);
    });
});
