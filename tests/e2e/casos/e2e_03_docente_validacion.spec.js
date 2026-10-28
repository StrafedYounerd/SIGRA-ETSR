// ============================================================
// SIGRA-ETSR · Suite E2E — Caso 6: validaciones del reporte
// ============================================================
// e2e_03_docente_validacion.spec.js
//
// E2E-6: al reportar una falla:
//        a) un tipo que exige detalle no se envía sin detalle,
//        b) la misma PC no admite un segundo reporte activo.
//
// La validación de verdad la hace el servidor (reportar-falla.php),
// así que además del aviso en pantalla se comprueba la respuesta
// JSON del endpoint.
//

const { test, expect } = require('@playwright/test');
const { login, irA, elegirSala, elegirEquipo, reportarFalla } = require('../e2e_paginas');
const { datos, registrarId } = require('../e2e_spec');
const { consultar } = require('../e2e_db');

test.describe('Validaciones del reporte de falla', () => {
    test('E2E-6 · el servidor rechaza el detalle faltante y el reporte duplicado', async ({ page }) => {
        const d = datos();
        const pc = d.sala.pcs[1];

        await login(page, d.usuarios.docente);
        await elegirSala(page, {
            nombreSala: d.sala.nombre_sala,
            grupo: 'E2E-Grupo 3',
        });

        await elegirEquipo(page, pc.etiqueta);

        // ── a) Tipo que exige detalle, sin escribirlo ──
        // El propio JS lo avisa antes de enviar (el detalle queda oculto
        // al elegir el tipo, pero el servidor también lo exige).
        await page.selectOption('#campo-tipo-falla', 'Falta de software');
        await page.waitForSelector('#contenedor-detalle:not([hidden])');
        await page.fill('#campo-detalle', '');

        const respuestaSinDetalle = await page.request.post('/docente/reportar-falla.php', {
            form: { id_pc: String(pc.id_pc), tipo_falla: 'Falta de software', detalle_texto: '' },
        });
        const jsonSinDetalle = await respuestaSinDetalle.json();
        expect(jsonSinDetalle.exito).toBe(false);
        expect(jsonSinDetalle.mensaje).toMatch(/Especificá el detalle/);

        // En pantalla, el botón no crea nada: sigue el formulario de reporte.
        await expect(page.locator('.tit-reporte')).toHaveText('Reportar falla');

        // ── b) Reporte válido: se crea el ticket ──
        await reportarFalla(page, { tipo: 'Falta de software', detalle: 'E2E falta instalar Python' });
        await expect(page.locator('.tit-reporte')).toHaveText('Reporte abierto');

        const tickets = await consultar(
            'SELECT id_ticket, tipo_falla, detalle_texto, estado FROM tickets WHERE id_pc = ? ORDER BY id_ticket DESC',
            [pc.id_pc]
        );
        expect(tickets.length).toBe(1);
        expect(tickets[0].tipo_falla).toBe('Falta de software');
        expect(tickets[0].detalle_texto).toBe('E2E falta instalar Python');
        registrarId('tickets', tickets[0].id_ticket, `${pc.etiqueta} / Falta de software`, 'E2E-6');

        // ── c) Segundo reporte sobre la misma PC: rechazado ──
        const respuestaDuplicado = await page.request.post('/docente/reportar-falla.php', {
            form: { id_pc: String(pc.id_pc), tipo_falla: 'No enciende', detalle_texto: '' },
        });
        const jsonDuplicado = await respuestaDuplicado.json();
        expect(jsonDuplicado.exito).toBe(false);
        expect(jsonDuplicado.mensaje).toMatch(/ya tiene un reporte activo/);

        // Sólo hay un ticket para esa PC.
        const trasDuplicado = await consultar(
            'SELECT COUNT(*) AS total FROM tickets WHERE id_pc = ?',
            [pc.id_pc]
        );
        expect(Number(trasDuplicado[0].total)).toBe(1);

        // En el plano, esa PC ya no ofrece el formulario de reporte.
        await elegirEquipo(page, pc.etiqueta, { esperarFormulario: false });
        await expect(page.locator('#campo-tipo-falla')).toHaveCount(0);
        await expect(page.locator('.tit-reporte')).toHaveText('Reporte abierto');
    });
});
