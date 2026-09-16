/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */
// Start: python test/run_quartix_browser.py <Dolibarr htdocs> --cross-origin-tiles
// Run: node test/run_quartix_tiles_browser.cjs http://127.0.0.1:8765
// Playwright/Chromium required; PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH is optional.
// Only loopback fixtures: no OpenStreetMap, QWS or real coordinates are requested.
const { chromium } = require('playwright');
const assert = require('node:assert/strict');
const base = new URL(process.argv[2] || 'http://127.0.0.1:8765');
assert.equal(base.hostname, '127.0.0.1', 'Use the local synthetic fixture');
const expectMissing = process.argv.includes('--expect-missing');

(async () => {
	const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH || undefined });
	try {
		const page = await browser.newPage();
		page.setDefaultTimeout(10000);
		const errors = [];
		page.on('pageerror', error => errors.push(error.message));
		await page.route('**/*', route => {
			const target = new URL(route.request().url());
			return ['127.0.0.1', 'localhost'].includes(target.hostname) ? route.continue() : route.abort();
		});
		async function tileRequests() {
			return (await page.request.get(new URL('/tile-requests', base).href)).json();
		}
		for (const dialog of [true, false]) {
			const before = (await tileRequests()).length;
			const target = new URL(dialog ? '/' : '/erp/modules/lmdbvehiclemanagement/vehicle_route.php', base);
			target.search = '?day=1&trip=private-trip-selector&quartix_id=35&token=fixture-secret';
			const response = await page.goto(target.href);
			assert.equal(response.headers()['referrer-policy'], 'same-origin');
			if (dialog) await page.getByRole('link', { name: 'Cached route', exact: true }).click();
			await page.waitForFunction(() => {
				const tiles = Array.from(document.querySelectorAll('#qx-route-map img.leaflet-tile'));
				return tiles.length > 0 && tiles.every(tile => tile.complete && tile.naturalWidth > 0);
			});
			const requests = (await tileRequests()).slice(before);
			assert.ok(requests.length > 0, 'Cross-origin tile requests reached the local server');
			assert.ok(requests.every(request => request.referer === (expectMissing ? '' : base.origin + '/')), 'Only the actual site origin is disclosed, without path, trip, vehicle or token');
			if (!expectMissing) {
				assert.ok((await page.locator('#qx-route-map img.leaflet-tile').evaluateAll(tiles => tiles.map(tile => tile.referrerPolicy))).every(policy => policy === 'strict-origin'));
			}
			assert.equal(await page.locator('#qx-route-map .leaflet-marker-icon').count(), 2);
			assert.ok(await page.locator('#qx-route-map .leaflet-overlay-pane path').count() > 0);
			console.log(`${dialog ? 'Dialog' : 'Direct page'}: ${expectMissing ? 'missing referrer reproduced' : 'origin-only referrer verified'}, route and markers retained`);
		}
		assert.deepEqual(errors, []);
	} finally {
		await browser.close();
	}
})().catch(error => { console.error(error); process.exitCode = 1; });
