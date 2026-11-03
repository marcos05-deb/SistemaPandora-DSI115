import assert from 'node:assert/strict';
import { mkdir } from 'node:fs/promises';
import { createServer } from 'vite';

// Uses the existing Vite/Vue toolchain; provide an installed Playwright module when
// it is outside node_modules. No application data, login, or database is used.
const { chromium } = await import(process.env.PLAYWRIGHT_MODULE_URL || 'playwright');
const html = `<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1"></head>
<body class="bg-pd-app-bg text-pd-text-primary"><div id="app"></div><script type="module">
import { createApp } from 'vue';
import ProtectedValue from '/resources/js/Components/UI/ProtectedValue.vue';
import '/resources/css/app.css';
createApp({ components: { ProtectedValue }, template: \`
<main class="mx-auto max-w-4xl space-y-6 p-4 sm:p-8">
<h1 class="text-2xl font-bold">Indicadores de bienestar</h1>
<p class="text-sm text-pd-text-secondary">Resultados agregados · Privacidad de los registros</p>
<section class="grid gap-4 sm:grid-cols-2">
<article class="rounded-xl border border-pd-border bg-pd-surface p-5"><h2 class="text-sm font-semibold">Consultas registradas</h2><div class="mt-4" data-primary><ProtectedValue suppression="primaria" /></div><p class="mt-2 text-xs text-pd-text-muted">Total del alcance</p></article>
<article class="rounded-xl border border-pd-border bg-pd-surface p-5"><h2 class="text-sm font-semibold">Citas y asistencia</h2><div class="mt-4" data-complementary><ProtectedValue suppression="complementaria" /></div><p class="mt-2 text-xs text-pd-text-muted">Total protegido por supresión complementaria</p></article>
</section>
<section class="overflow-hidden rounded-xl border border-pd-border bg-pd-surface"><div class="p-4 font-semibold">Reporte por área</div><div class="overflow-x-auto" data-table-scroll><table class="w-full min-w-[520px] text-sm"><thead class="bg-pd-surface-header"><tr><th class="p-4 text-left">Área</th><th class="p-4 text-right">Total</th><th class="relative w-40 p-4"><span class="sr-only">Visualización</span></th></tr></thead><tbody><tr><th class="p-4 text-left font-medium">Psicología</th><td class="p-4 text-right" data-table><ProtectedValue suppression="primaria" /></td><td class="p-4"><span aria-hidden="true" class="block h-2 rounded-full border border-dashed border-pd-border"></span></td></tr><tr class="border-t border-pd-border"><th class="p-4 text-left font-medium">Todas las áreas</th><td class="p-4 text-right"><ProtectedValue suppression="complementaria" /></td><td></td></tr></tbody></table></div></section>
<section class="rounded-xl border border-pd-border bg-pd-surface p-5"><h2 class="mb-3 font-semibold">Resumen de alertas</h2><div data-generic><ProtectedValue /></div><div class="mt-4 flex h-40 w-40 items-center justify-center" data-donut><ProtectedValue compact /></div></section>
</main>\` }).mount('#app');
</script></body></html>`;
const server = await createServer({ resolve: { alias: { vue: 'vue/dist/vue.esm-bundler.js' } }, server: { host: '127.0.0.1', port: 0, strictPort: false }, plugins: [{
    name: 'privacy-regression-fixture',
    configureServer(vite) {
        vite.middlewares.use(async (req, res, next) => {
            if (req.url !== '/__privacy_preview') return next();
            res.setHeader('Content-Type', 'text/html');
            res.end(await vite.transformIndexHtml(req.url, html));
        });
    },
}] });
let browser;
try {
    await server.listen();
    const port = server.httpServer.address().port;
    browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', (error) => errors.push(error.message));
    await page.goto(`http://127.0.0.1:${port}/__privacy_preview`);
    await page.locator('[data-primary] button').waitFor();
    const primary = page.locator('[data-primary] button');
    const complementary = page.locator('[data-complementary] button');
    assert.equal(await primary.innerText(), 'Dato protegido');
    assert.match(await primary.getAttribute('aria-describedby'), /.+/);
    await primary.focus();
    await page.locator('body > span.fixed').waitFor();
    assert.match(await page.locator('body > span.fixed').innerText(), /mínimo de 5 registros/);
    await page.keyboard.press('Escape');
    assert.equal(await page.locator('body > span.fixed').count(), 0);
    await complementary.focus();
    assert.match(await page.locator('body > span.fixed').innerText(), /deduzcan otros conteos/);
    assert.doesNotMatch(await page.locator('body > span.fixed').innerText(), /no alcanza/);
    await page.mouse.click(5, 5);
    assert.equal(await page.locator('body > span.fixed').count(), 0);
    await primary.hover();
    const tooltip = page.locator('body > span.fixed');
    await tooltip.waitFor();
    await tooltip.hover();
    await page.waitForTimeout(220);
    assert.equal(await tooltip.count(), 1, 'Tooltip stays open while hovered');
    const output = process.env.PRIVACY_SCREENSHOT_DIR;
    if (output) await mkdir(output, { recursive: true });
    for (const dark of [false, true]) {
        await page.evaluate((enabled) => document.documentElement.classList.toggle('dark', enabled), dark);
        for (const width of [1280, 768, 375]) {
            await page.setViewportSize({ width, height: 900 });
            await primary.click();
            const bounds = await tooltip.boundingBox();
            assert.ok(bounds.x >= 0 && bounds.x + bounds.width <= width, 'Tooltip stays within viewport');
            const overflow = await page.evaluate(() => document.documentElement.scrollWidth > innerWidth);
            assert.equal(overflow, false, 'Tables scroll locally without page overflow');
            const sizes = await page.locator('[data-table] button').evaluate((el) => ({ cell: el.parentElement.clientWidth, badge: el.offsetWidth }));
            assert.ok(sizes.badge <= sizes.cell, 'Badge fits table cell');
            const donutBadge = await page.locator('[data-donut] button').boundingBox();
            assert.ok(Math.hypot(donutBadge.width, donutBadge.height) <= 108.8, 'Compact badge fits donut hole');
            assert.equal(await primary.locator('..').evaluate((el) => getComputedStyle(el).position), 'relative', 'Screen-reader description has a local containing block');
            if (width === 375) assert.ok((await primary.boundingBox()).height >= 44);
            if (output) await page.screenshot({ path: `${output}/${dark ? 'dark' : 'light'}-${width}.png`, fullPage: true });
            await page.keyboard.press('Escape');
            await page.locator('[data-table-scroll]').evaluate((el) => { el.scrollLeft = el.scrollWidth; });
            await page.locator('[data-table] button').focus();
            await tooltip.waitFor();
            // Focusing a clipped cell may trigger asynchronous ancestor scrolling.
            await page.evaluate(() => new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve))));
            const tableTooltip = await tooltip.boundingBox();
            assert.ok(tableTooltip.x >= 0 && tableTooltip.x + tableTooltip.width <= width);
            assert.equal(await tooltip.evaluate((el) => el.parentElement.tagName), 'BODY', 'Tooltip is outside clipped table');
            await page.keyboard.press('Escape');
        }
    }
    assert.deepEqual(errors, []);
    console.log('PASS: primary/complementary/generic semantics; focus, Escape, hover, touch/click; viewport/table containment; light/dark desktop/mobile; no browser errors.');
} finally {
    await browser?.close();
    await server.close();
}
