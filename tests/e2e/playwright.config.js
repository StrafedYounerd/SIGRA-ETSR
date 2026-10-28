// ============================================================
// SIGRA-ETSR · Suite E2E — Configuración de Playwright
// ============================================================
// playwright.config.js
//
// La suite corre contra la app que ya está levantada por
// docker-compose (http://localhost:8080 por defecto). No levanta
// ningún servidor: sólo navega.
//
// El reporte se deja en tests/e2e/informes/ (requisito de la
// tanda): el informe final en Markdown lo arma e2e_informe.js.
//

const { defineConfig } = require('@playwright/test');
const path = require('path');

module.exports = defineConfig({
    testDir: path.join(__dirname, 'casos'),
    globalSetup: require.resolve('./e2e_global_setup.js'),
    globalTeardown: require.resolve('./e2e_global_teardown.js'),
    // La app es de intranet y el arranque de PHP puede demorar.
    timeout: 60000,
    expect: { timeout: 10000 },
    // Los casos comparten la base (crean y borran tickets): se corren
    // de a uno para que los ids sean predecibles.
    workers: 1,
    fullyParallel: false,
    forbidOnly: true,
    retries: 0,
    reporter: [
        ['list'],
        ['json', { outputFile: path.join(__dirname, 'informes', 'resultados.json') }],
    ],
    outputDir: path.join(__dirname, 'informes', 'artefactos'),
    use: {
        baseURL: process.env.E2E_BASE_URL || 'http://localhost:8080',
        browserName: 'chromium',
        headless: true,
        locale: 'es-UY',
        timezoneId: 'America/Montevideo',
        screenshot: 'only-on-failure',
        trace: 'retain-on-failure',
        video: 'off',
        actionTimeout: 15000,
        navigationTimeout: 30000,
    },
});
